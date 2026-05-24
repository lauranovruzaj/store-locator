<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SL_Importer {

	const CHUNK_SIZE = 25;
	const COLUMNS    = [ 'CodCli', 'RagioneSociale', 'Indirizzo', 'CAP', 'Citta', 'PR', 'ISO', 'Stato', 'Telefono', 'CodCat', 'Categoria', 'UltAcq' ];

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
		add_action( 'admin_post_sl_import_upload', [ __CLASS__, 'handle_upload' ] );
		add_action( 'wp_ajax_sl_import_chunk', [ __CLASS__, 'ajax_chunk' ] );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'store-locator import', [ __CLASS__, 'cli_import' ] );
		}
	}

	public static function add_menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . SL_CPT::POST_TYPE,
			__( 'Import CSV', 'store-locator' ),
			__( 'Import CSV', 'store-locator' ),
			'manage_options',
			'sl-import',
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function enqueue( string $hook ): void {
		if ( $hook !== SL_CPT::POST_TYPE . '_page_sl-import' ) {
			return;
		}
		wp_enqueue_script(
			'sl-importer',
			SL_PLUGIN_URL . 'assets/js/store-locator-admin.js',
			[],
			SL_VERSION,
			true
		);
		wp_localize_script( 'sl-importer', 'SL_IMPORT', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'sl_import_chunk' ),
			'strings'  => [
				'starting'  => __( 'Avvio import…', 'store-locator' ),
				'progress'  => __( 'Elaborazione %1$d / %2$d righe…', 'store-locator' ),
				'done'      => __( 'Import completato.', 'store-locator' ),
				'failed'    => __( 'Errore durante l\'import.', 'store-locator' ),
				'download'  => __( 'Scarica righe non importate', 'store-locator' ),
			],
		] );
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Store Locator – Import CSV', 'store-locator' ); ?></h1>
			<p><?php esc_html_e( 'CSV esportato da TeamSystem (UTF-8, separatore virgola, intestazioni: CodCli, RagioneSociale, Indirizzo, CAP, Citta, PR, ISO, Stato, Telefono, CodCat, Categoria, UltAcq).', 'store-locator' ); ?></p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'sl_import_upload' ); ?>
				<input type="hidden" name="action" value="sl_import_upload">
				<p><input type="file" name="csv" accept=".csv,text/csv" required></p>
				<p><label><input type="checkbox" name="force_geocode" value="1"> <?php esc_html_e( 'Forza ri-geocodifica anche se l\'indirizzo non è cambiato', 'store-locator' ); ?></label></p>
				<p><?php submit_button( __( 'Carica e avvia import', 'store-locator' ), 'primary', 'submit', false ); ?></p>
			</form>

			<?php if ( isset( $_GET['token'] ) ) :
				$token = sanitize_text_field( wp_unslash( $_GET['token'] ) );
				$total = isset( $_GET['total'] ) ? (int) $_GET['total'] : 0;
				$force = isset( $_GET['force'] ) ? (int) $_GET['force'] : 0;
				?>
				<hr>
				<h2><?php esc_html_e( 'Avanzamento import', 'store-locator' ); ?></h2>
				<div id="sl-import-status" data-token="<?php echo esc_attr( $token ); ?>" data-total="<?php echo esc_attr( $total ); ?>" data-force="<?php echo esc_attr( $force ); ?>">
					<progress id="sl-import-bar" value="0" max="<?php echo esc_attr( $total ); ?>" style="width:400px;"></progress>
					<p id="sl-import-msg"></p>
					<ul id="sl-import-summary" style="display:none;"></ul>
					<p id="sl-import-download"></p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function handle_upload(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Non autorizzato.', 'store-locator' ) );
		}
		check_admin_referer( 'sl_import_upload' );

		if ( empty( $_FILES['csv']['tmp_name'] ) ) {
			wp_die( esc_html__( 'Nessun file caricato.', 'store-locator' ) );
		}

		$upload_dir = self::ensure_dir();
		$token      = wp_generate_password( 16, false, false );
		$dest       = trailingslashit( $upload_dir ) . $token . '.csv';

		if ( ! move_uploaded_file( $_FILES['csv']['tmp_name'], $dest ) ) {
			wp_die( esc_html__( 'Impossibile salvare il file caricato.', 'store-locator' ) );
		}

		$rows = self::count_rows( $dest );
		$force = ! empty( $_POST['force_geocode'] ) ? 1 : 0;

		$redirect = add_query_arg( [
			'post_type' => SL_CPT::POST_TYPE,
			'page'      => 'sl-import',
			'token'     => $token,
			'total'     => $rows,
			'force'     => $force,
		], admin_url( 'edit.php' ) );
		wp_safe_redirect( $redirect );
		exit;
	}

	public static function ajax_chunk(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Non autorizzato.', 'store-locator' ) ], 403 );
		}
		check_ajax_referer( 'sl_import_chunk', 'nonce' );

		$token  = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
		$offset = max( 0, (int) ( $_POST['offset'] ?? 0 ) );
		$force  = ! empty( $_POST['force'] );

		if ( ! preg_match( '/^[a-z0-9]+$/i', $token ) ) {
			wp_send_json_error( [ 'message' => 'Bad token' ], 400 );
		}

		$dir  = self::ensure_dir();
		$file = trailingslashit( $dir ) . $token . '.csv';
		if ( ! file_exists( $file ) ) {
			wp_send_json_error( [ 'message' => 'File not found' ], 404 );
		}

		$stats_file = trailingslashit( $dir ) . $token . '.stats.json';
		$stats      = file_exists( $stats_file ) ? json_decode( (string) file_get_contents( $stats_file ), true ) : null;
		if ( ! is_array( $stats ) ) {
			$stats = [ 'created' => 0, 'updated' => 0, 'geocoded' => 0, 'geocode_failed' => 0, 'skipped' => 0, 'failures' => [] ];
		}

		$result = self::process_chunk( $file, $offset, self::CHUNK_SIZE, $force, $stats );
		file_put_contents( $stats_file, wp_json_encode( $stats ) );

		$next_offset = $offset + $result['processed'];
		$done        = $result['processed'] === 0 || $next_offset >= $result['total'];

		$response = [
			'offset'    => $next_offset,
			'total'     => $result['total'],
			'processed' => $result['processed'],
			'stats'     => [
				'created'        => $stats['created'],
				'updated'        => $stats['updated'],
				'geocoded'       => $stats['geocoded'],
				'geocode_failed' => $stats['geocode_failed'],
				'skipped'        => $stats['skipped'],
			],
			'done'      => $done,
		];

		if ( $done ) {
			$response['download_url'] = self::write_failures_csv( $token, $stats['failures'] );
			// Best-effort cleanup of source csv (keep failures csv).
			@unlink( $file );
			@unlink( $stats_file );
		}

		wp_send_json_success( $response );
	}

	/** @return array{processed:int,total:int} */
	private static function process_chunk( string $file, int $offset, int $chunk, bool $force, array &$stats ): array {
		$total     = self::count_rows( $file );
		$processed = 0;

		$handle = fopen( $file, 'r' );
		if ( ! $handle ) {
			return [ 'processed' => 0, 'total' => $total ];
		}
		$header = fgetcsv( $handle );
		if ( ! $header ) {
			fclose( $handle );
			return [ 'processed' => 0, 'total' => 0 ];
		}
		$header = array_map( static fn( $h ) => trim( (string) $h, " \t\n\r\0\x0B\xEF\xBB\xBF" ), $header );

		// Skip to offset.
		$skipped = 0;
		while ( $skipped < $offset ) {
			if ( fgetcsv( $handle ) === false ) {
				fclose( $handle );
				return [ 'processed' => 0, 'total' => $total ];
			}
			$skipped++;
		}

		while ( $processed < $chunk ) {
			$row = fgetcsv( $handle );
			if ( $row === false ) {
				break;
			}
			$assoc = self::assoc_row( $header, $row );
			self::upsert_store( $assoc, $force, $stats );
			$processed++;
		}
		fclose( $handle );
		return [ 'processed' => $processed, 'total' => $total ];
	}

	private static function assoc_row( array $header, array $row ): array {
		$assoc = [];
		foreach ( $header as $i => $col ) {
			$assoc[ $col ] = isset( $row[ $i ] ) ? trim( (string) $row[ $i ] ) : '';
		}
		return $assoc;
	}

	private static function upsert_store( array $r, bool $force_geocode, array &$stats ): void {
		$ext_id = $r['CodCli'] ?? '';
		$name   = $r['RagioneSociale'] ?? '';
		if ( $ext_id === '' || $name === '' ) {
			$stats['skipped']++;
			$stats['failures'][] = array_merge( $r, [ 'error' => 'Missing CodCli or RagioneSociale' ] );
			return;
		}

		$existing = get_posts( [
			'post_type'      => SL_CPT::POST_TYPE,
			'post_status'    => 'any',
			'numberposts'    => 1,
			'meta_key'       => '_sl_external_id',
			'meta_value'     => $ext_id,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'suppress_filters' => false,
		] );

		$post_id = $existing[0] ?? 0;
		$is_new  = ! $post_id;

		$postarr = [
			'post_type'   => SL_CPT::POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => $name,
		];
		if ( $post_id ) {
			$postarr['ID'] = $post_id;
			$post_id = wp_update_post( $postarr, true );
		} else {
			$post_id = wp_insert_post( $postarr, true );
		}
		if ( is_wp_error( $post_id ) ) {
			$stats['skipped']++;
			$stats['failures'][] = array_merge( $r, [ 'error' => $post_id->get_error_message() ] );
			return;
		}

		$iso = strtoupper( $r['ISO'] ?? '' ) ?: 'IT';
		$phone_raw = $r['Telefono'] ?? '';
		$phone_tel = self::normalize_phone( $phone_raw, $iso );

		update_post_meta( $post_id, '_sl_external_id', $ext_id );
		update_post_meta( $post_id, '_sl_address', sanitize_text_field( $r['Indirizzo'] ?? '' ) );
		update_post_meta( $post_id, '_sl_cap', sanitize_text_field( $r['CAP'] ?? '' ) );
		update_post_meta( $post_id, '_sl_city', sanitize_text_field( $r['Citta'] ?? '' ) );
		update_post_meta( $post_id, '_sl_province', strtoupper( sanitize_text_field( $r['PR'] ?? '' ) ) );
		update_post_meta( $post_id, '_sl_country', $iso );
		update_post_meta( $post_id, '_sl_phone_raw', sanitize_text_field( $phone_raw ) );
		update_post_meta( $post_id, '_sl_phone_tel', $phone_tel );
		update_post_meta( $post_id, '_sl_category', sanitize_text_field( $r['Categoria'] ?? '' ) );
		update_post_meta( $post_id, '_sl_category_code', sanitize_text_field( $r['CodCat'] ?? '' ) );

		// Taxonomy
		$cat = $r['Categoria'] ?? '';
		if ( $cat !== '' ) {
			$display = self::category_display( $cat );
			$term    = term_exists( $display, SL_CPT::TAXONOMY );
			if ( ! $term ) {
				$term = wp_insert_term( $display, SL_CPT::TAXONOMY, [ 'slug' => sanitize_title( $cat ) ] );
			}
			if ( ! is_wp_error( $term ) ) {
				wp_set_object_terms( $post_id, (int) $term['term_id'], SL_CPT::TAXONOMY );
			}
		}

		// Geocode
		$query = SL_Geocoder::build_query(
			$r['Indirizzo'] ?? '',
			$r['CAP'] ?? '',
			$r['Citta'] ?? '',
			$r['PR'] ?? '',
			$iso
		);
		$new_hash    = SL_Geocoder::address_hash( $query );
		$old_hash    = (string) get_post_meta( $post_id, '_sl_address_hash', true );
		$has_coords  = (float) get_post_meta( $post_id, '_sl_lat', true ) && (float) get_post_meta( $post_id, '_sl_lng', true );
		$needs_geo   = $force_geocode || ! $has_coords || $new_hash !== $old_hash;

		if ( $needs_geo && $query ) {
			$res = SL_Geocoder::lookup( $query );
			if ( is_wp_error( $res ) ) {
				$stats['geocode_failed']++;
				$stats['failures'][] = array_merge( $r, [ 'error' => 'Geocode: ' . $res->get_error_message() ] );
			} else {
				update_post_meta( $post_id, '_sl_lat', $res['lat'] );
				update_post_meta( $post_id, '_sl_lng', $res['lng'] );
				update_post_meta( $post_id, '_sl_address_hash', $new_hash );
				$stats['geocoded']++;
			}
		}

		if ( $is_new ) {
			$stats['created']++;
		} else {
			$stats['updated']++;
		}
	}

	private static function category_display( string $raw ): string {
		$map = [
			'ERBORISTERIE'  => 'Erboristerie',
			'FARMACIE'      => 'Farmacie',
			'PARAFARMACIA'  => 'Parafarmacia',
			'PARAFARMACIE'  => 'Parafarmacia',
		];
		$upper = strtoupper( trim( $raw ) );
		return $map[ $upper ] ?? mb_convert_case( mb_strtolower( $raw ), MB_CASE_TITLE, 'UTF-8' );
	}

	private static function normalize_phone( string $raw, string $iso ): string {
		$digits = preg_replace( '/[^\d+]/', '', $raw );
		if ( $digits === '' ) {
			return '';
		}
		if ( str_starts_with( $digits, '+' ) ) {
			return $digits;
		}
		if ( str_starts_with( $digits, '00' ) ) {
			return '+' . substr( $digits, 2 );
		}
		$prefix = match ( strtoupper( $iso ) ) {
			'IT'      => '+39',
			'SM'      => '+378',
			'CH'      => '+41',
			'FR'      => '+33',
			'DE'      => '+49',
			'AT'      => '+43',
			'VA'      => '+379',
			default   => '+39',
		};
		// If already has the trunk prefix (e.g. starts with "39" for IT and is long enough), keep as +XX…
		$cc_digits = substr( $prefix, 1 );
		if ( str_starts_with( $digits, $cc_digits ) && strlen( $digits ) > strlen( $cc_digits ) + 5 ) {
			return $prefix . substr( $digits, strlen( $cc_digits ) );
		}
		return $prefix . $digits;
	}

	private static function count_rows( string $file ): int {
		$h = fopen( $file, 'r' );
		if ( ! $h ) {
			return 0;
		}
		$n = 0;
		// Skip header.
		fgetcsv( $h );
		while ( fgetcsv( $h ) !== false ) {
			$n++;
		}
		fclose( $h );
		return $n;
	}

	private static function ensure_dir(): string {
		$upload = wp_upload_dir();
		$dir    = trailingslashit( $upload['basedir'] ) . 'store-locator';
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
			file_put_contents( $dir . '/index.html', '' );
		}
		return $dir;
	}

	private static function write_failures_csv( string $token, array $failures ): string {
		if ( empty( $failures ) ) {
			return '';
		}
		$upload = wp_upload_dir();
		$dir    = self::ensure_dir();
		$file   = $dir . '/' . $token . '-failures.csv';
		$h      = fopen( $file, 'w' );
		if ( ! $h ) {
			return '';
		}
		$cols = array_merge( self::COLUMNS, [ 'error' ] );
		fputcsv( $h, $cols );
		foreach ( $failures as $row ) {
			$out = [];
			foreach ( $cols as $c ) {
				$out[] = $row[ $c ] ?? '';
			}
			fputcsv( $h, $out );
		}
		fclose( $h );
		return trailingslashit( $upload['baseurl'] ) . 'store-locator/' . $token . '-failures.csv';
	}

	/**
	 * WP-CLI: wp store-locator import <file> [--force-geocode]
	 */
	public static function cli_import( array $args, array $assoc_args ): void {
		if ( empty( $args[0] ) ) {
			\WP_CLI::error( 'Usage: wp store-locator import <file> [--force-geocode]' );
		}
		$file = $args[0];
		if ( ! file_exists( $file ) ) {
			\WP_CLI::error( "File non trovato: $file" );
		}
		$force = ! empty( $assoc_args['force-geocode'] );

		$total  = self::count_rows( $file );
		$stats  = [ 'created' => 0, 'updated' => 0, 'geocoded' => 0, 'geocode_failed' => 0, 'skipped' => 0, 'failures' => [] ];
		$offset = 0;
		$progress = \WP_CLI\Utils\make_progress_bar( 'Importing stores', $total );
		while ( $offset < $total ) {
			$res = self::process_chunk( $file, $offset, self::CHUNK_SIZE, $force, $stats );
			if ( $res['processed'] === 0 ) {
				break;
			}
			$offset += $res['processed'];
			for ( $i = 0; $i < $res['processed']; $i++ ) {
				$progress->tick();
			}
		}
		$progress->finish();
		\WP_CLI::success( sprintf(
			'Created %d, updated %d, geocoded %d, geocode-failed %d, skipped %d',
			$stats['created'], $stats['updated'], $stats['geocoded'], $stats['geocode_failed'], $stats['skipped']
		) );
		if ( ! empty( $stats['failures'] ) ) {
			$token = 'cli-' . gmdate( 'Ymd-His' );
			$url = self::write_failures_csv( $token, $stats['failures'] );
			if ( $url ) {
				\WP_CLI::warning( 'Failed rows written to: ' . $url );
			}
		}
	}
}
