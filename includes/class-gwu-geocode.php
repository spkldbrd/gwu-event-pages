<?php
/**
 * City + state → lat/lng for map pins (OpenStreetMap Nominatim), with transients
 * and in-request caching. Respects Nominatim usage policy (User-Agent, spacing).
 *
 * @package GWU_Event_Pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GWU_Geocode {

	private const TRANSIENT_PREFIX = 'gwu_ep_geo_v1_';

	private const TTL_HIT = 90 * DAY_IN_SECONDS;

	private const TTL_MISS = DAY_IN_SECONDS;

	/** @var array<string, array{lat: float, lng: float}|null> */
	private static $request_cache = array();

	/** @var float Timestamp after last HTTP request (for politeness). */
	private static $last_http_at = 0.0;

	/**
	 * @param string $city        City name from event data.
	 * @param string $state_abbr  Two-letter USPS state/territory code.
	 * @return array{lat: float, lng: float}|null
	 */
	public static function lookup_city_state( string $city, string $state_abbr ): ?array {
		$city       = trim( $city );
		$state_abbr = strtoupper( trim( $state_abbr ) );

		if ( '' === $city || ! preg_match( '/^[A-Z]{2}$/', $state_abbr ) ) {
			return null;
		}

		$key = self::TRANSIENT_PREFIX . md5( $city . '|' . $state_abbr );

		if ( array_key_exists( $key, self::$request_cache ) ) {
			return self::$request_cache[ $key ];
		}

		$cached = get_transient( $key );
		if ( is_array( $cached ) && isset( $cached['lat'], $cached['lng'] ) ) {
			$lat = (float) $cached['lat'];
			$lng = (float) $cached['lng'];
			$out = array( 'lat' => $lat, 'lng' => $lng );
			self::$request_cache[ $key ] = $out;
			return $out;
		}
		if ( $cached === 'fail' ) {
			self::$request_cache[ $key ] = null;
			return null;
		}

		self::throttle_before_request();

		$url = add_query_arg(
			array(
				'format'         => 'json',
				'limit'          => '1',
				'city'           => $city,
				'state'          => $state_abbr,
				'countrycodes'   => 'us',
				'addressdetails' => '0',
			),
			'https://nominatim.openstreetmap.org/search'
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 8,
				'headers' => array(
					'User-Agent' => self::user_agent(),
					'Accept'     => 'application/json',
				),
			)
		);

		self::$last_http_at = microtime( true );

		if ( is_wp_error( $response ) ) {
			set_transient( $key, 'fail', self::TTL_MISS );
			self::$request_cache[ $key ] = null;
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		if ( $code < 200 || $code >= 300 ) {
			set_transient( $key, 'fail', self::TTL_MISS );
			self::$request_cache[ $key ] = null;
			return null;
		}

		$rows = json_decode( $body, true );
		if ( ! is_array( $rows ) || $rows === array() ) {
			set_transient( $key, 'fail', self::TTL_MISS );
			self::$request_cache[ $key ] = null;
			return null;
		}

		$first = $rows[0];
		if ( ! is_array( $first ) ) {
			set_transient( $key, 'fail', self::TTL_MISS );
			self::$request_cache[ $key ] = null;
			return null;
		}

		$lat = isset( $first['lat'] ) ? (float) $first['lat'] : null;
		$lng = isset( $first['lon'] ) ? (float) $first['lon'] : null;
		if ( null === $lat || null === $lng || ( $lat === 0.0 && $lng === 0.0 ) ) {
			set_transient( $key, 'fail', self::TTL_MISS );
			self::$request_cache[ $key ] = null;
			return null;
		}

		$out = array( 'lat' => $lat, 'lng' => $lng );
		set_transient( $key, $out, self::TTL_HIT );
		self::$request_cache[ $key ] = $out;
		return $out;
	}

	private static function throttle_before_request(): void {
		$now  = microtime( true );
		$wait = 1.05 - ( $now - self::$last_http_at );
		if ( $wait > 0 && self::$last_http_at > 0 ) {
			usleep( (int) ceil( $wait * 1_000_000 ) );
		}
	}

	private static function user_agent(): string {
		$v = defined( 'GWU_EP_VERSION' ) ? (string) GWU_EP_VERSION : '0';
		return sprintf( 'GWU-Event-Pages/%s (public event map; +https://grantwritingusa.com)', $v );
	}
}
