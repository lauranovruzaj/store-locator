<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SL_Geocoder {

	const ENDPOINT = 'https://maps.googleapis.com/maps/api/geocode/json';

	public static function build_query( string $address, string $cap, string $city, string $province, string $country ): string {
		$parts = array_filter( [
			trim( $address ),
			trim( $cap . ' ' . $city ),
			trim( $province ),
			trim( $country ),
		], static fn( $v ) => $v !== '' );
		return implode( ', ', $parts );
	}

	public static function address_hash( string $query ): string {
		return sha1( mb_strtolower( trim( preg_replace( '/\s+/', ' ', $query ) ) ) );
	}

	/**
	 * Throttle to ~10 requests/second using a microtime transient.
	 */
	public static function throttle(): void {
		$last = (float) get_transient( 'sl_geocode_last' );
		$now  = microtime( true );
		$min_gap = 0.1; // 100 ms => 10 req/s
		if ( $last && ( $now - $last ) < $min_gap ) {
			usleep( (int) ( ( $min_gap - ( $now - $last ) ) * 1_000_000 ) );
		}
		set_transient( 'sl_geocode_last', microtime( true ), 60 );
	}

	/**
	 * @return array{lat:float,lng:float,formatted_address:string}|WP_Error
	 */
	public static function lookup( string $query, string $region = 'it' ) {
		$key = SL_Settings::get( 'geocode_key' ) ?: SL_Settings::get( 'maps_key' );
		if ( ! $key ) {
			return new WP_Error( 'sl_no_key', __( 'Chiave Google Geocoding non configurata.', 'store-locator' ) );
		}
		if ( $query === '' ) {
			return new WP_Error( 'sl_empty_query', __( 'Indirizzo vuoto.', 'store-locator' ) );
		}

		self::throttle();

		$url = add_query_arg( [
			'address' => rawurlencode( $query ),
			'region'  => $region,
			'key'     => $key,
		], self::ENDPOINT );

		$response = wp_remote_get( $url, [ 'timeout' => 10 ] );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'sl_bad_response', __( 'Risposta non valida da Google.', 'store-locator' ) );
		}

		if ( ( $body['status'] ?? '' ) !== 'OK' || empty( $body['results'][0] ) ) {
			$status = $body['status'] ?? 'UNKNOWN';
			$message = $body['error_message'] ?? $status;
			return new WP_Error( 'sl_geocode_failed', $message, [ 'status' => $status, 'query' => $query ] );
		}

		$loc = $body['results'][0]['geometry']['location'] ?? null;
		if ( ! $loc ) {
			return new WP_Error( 'sl_no_location', __( 'Risultato senza coordinate.', 'store-locator' ) );
		}

		return [
			'lat'               => (float) $loc['lat'],
			'lng'               => (float) $loc['lng'],
			'formatted_address' => (string) ( $body['results'][0]['formatted_address'] ?? $query ),
		];
	}
}
