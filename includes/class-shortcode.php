<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SL_Shortcode {

	const TAG = 'store_locator';

	public static function init(): void {
		add_shortcode( self::TAG, [ __CLASS__, 'render' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_assets' ] );
	}

	public static function register_assets(): void {
		wp_register_style(
			'store-locator',
			SL_PLUGIN_URL . 'assets/css/store-locator.css',
			[],
			SL_VERSION
		);

		wp_register_script(
			'store-locator',
			SL_PLUGIN_URL . 'assets/js/store-locator.js',
			[],
			SL_VERSION,
			true
		);

		$maps_key = (string) SL_Settings::get( 'maps_key' );
		if ( $maps_key ) {
			wp_register_script(
				'google-maps',
				add_query_arg( [
					'key'       => $maps_key,
					'libraries' => 'marker',
					'loading'   => 'async',
					'v'         => 'weekly',
					'callback'  => 'slInitMap',
				], 'https://maps.googleapis.com/maps/api/js' ),
				[],
				null,
				[ 'in_footer' => true, 'strategy' => 'async' ]
			);
		}
	}

	public static function render( $atts = [], $content = '' ): string {
		wp_enqueue_style( 'store-locator' );
		wp_enqueue_script( 'store-locator' );
		if ( wp_script_is( 'google-maps', 'registered' ) ) {
			wp_enqueue_script( 'google-maps' );
		}

		wp_localize_script( 'store-locator', 'SL_DATA', [
			'rest_url'     => esc_url_raw( rest_url( SL_REST_API::NAMESPACE . '/' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'has_maps_key' => (bool) SL_Settings::get( 'maps_key' ),
			'center'       => [
				'lat' => (float) SL_Settings::get( 'center_lat' ),
				'lng' => (float) SL_Settings::get( 'center_lng' ),
			],
			'zoom'         => (int) SL_Settings::get( 'zoom' ),
			'suggerimenti' => (string) SL_Settings::get( 'suggerimenti' ),
			'pins'         => [
				'default' => SL_PLUGIN_URL . 'assets/img/pin-default.svg',
				'active'  => SL_PLUGIN_URL . 'assets/img/pin-active.svg',
			],
			'strings'      => [
				'no_results'    => __( 'Nessun punto vendita trovato nell\'area selezionata.', 'store-locator' ),
				'search_failed' => __( 'Indirizzo non trovato.', 'store-locator' ),
				'distance'      => __( '%s KM', 'store-locator' ),
			],
		] );

		ob_start();
		include SL_PLUGIN_DIR . 'templates/locator.php';
		return (string) ob_get_clean();
	}
}
