<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SL_REST_API {

	const NAMESPACE = 'store-locator/v1';

	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function register_routes(): void {
		register_rest_route( self::NAMESPACE, '/stores', [
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => [ __CLASS__, 'get_stores' ],
		] );

		register_rest_route( self::NAMESPACE, '/geocode', [
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => [ __CLASS__, 'geocode' ],
			'args'                => [
				'q' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		] );
	}

	public static function get_stores( WP_REST_Request $request ): WP_REST_Response {
		$posts = get_posts( [
			'post_type'      => SL_CPT::POST_TYPE,
			'post_status'    => 'publish',
			'numberposts'    => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		] );

		$out = [];
		foreach ( $posts as $post ) {
			$lat = (float) get_post_meta( $post->ID, '_sl_lat', true );
			$lng = (float) get_post_meta( $post->ID, '_sl_lng', true );
			if ( ! $lat || ! $lng ) {
				continue; // skip stores without coordinates
			}
			$phone_raw = (string) get_post_meta( $post->ID, '_sl_phone_raw', true );
			$phone_tel = (string) get_post_meta( $post->ID, '_sl_phone_tel', true );
			$out[] = [
				'id'             => $post->ID,
				'name'           => get_the_title( $post ),
				'address'        => (string) get_post_meta( $post->ID, '_sl_address', true ),
				'cap'            => (string) get_post_meta( $post->ID, '_sl_cap', true ),
				'city'           => (string) get_post_meta( $post->ID, '_sl_city', true ),
				'province'       => (string) get_post_meta( $post->ID, '_sl_province', true ),
				'country'        => (string) ( get_post_meta( $post->ID, '_sl_country', true ) ?: 'IT' ),
				'phone'          => $phone_raw ?: (string) get_post_meta( $post->ID, '_sl_phone', true ),
				'phone_tel'      => $phone_tel,
				'email'          => (string) get_post_meta( $post->ID, '_sl_email', true ),
				'website'        => (string) get_post_meta( $post->ID, '_sl_website', true ),
				'hours'          => (string) get_post_meta( $post->ID, '_sl_hours', true ),
				'category'       => (string) get_post_meta( $post->ID, '_sl_category', true ),
				'lat'            => $lat,
				'lng'            => $lng,
				'directions_url' => 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( $lat . ',' . $lng ),
			];
		}

		$response = new WP_REST_Response( $out );
		$response->set_headers( [ 'Cache-Control' => 'public, max-age=300' ] );
		return $response;
	}

	public static function geocode( WP_REST_Request $request ) {
		$ip  = self::client_ip();
		$key = 'sl_geo_rl_' . md5( $ip );
		$hits = (int) get_transient( $key );
		if ( $hits >= 30 ) {
			return new WP_Error( 'sl_rate_limited', __( 'Troppe richieste, riprova tra un minuto.', 'store-locator' ), [ 'status' => 429 ] );
		}
		set_transient( $key, $hits + 1, MINUTE_IN_SECONDS );

		$query = (string) $request->get_param( 'q' );
		if ( $query === '' ) {
			return new WP_Error( 'sl_empty', __( 'Query vuota.', 'store-locator' ), [ 'status' => 400 ] );
		}

		// Bias toward Italy unless query contains a country.
		$result = SL_Geocoder::lookup( $query, 'it' );
		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code();
			$status = $code === 'sl_geocode_failed' ? 404 : 500;
			return new WP_Error( $code, $result->get_error_message(), [ 'status' => $status ] );
		}

		return new WP_REST_Response( $result );
	}

	private static function client_ip(): string {
		$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$parts = explode( ',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'] );
			$ip    = trim( $parts[0] );
		}
		return filter_var( $ip, FILTER_VALIDATE_IP ) ?: '0.0.0.0';
	}
}
