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

	private const TRANSIENT_PREFIX = 'gwu_ep_geo_v4_';

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
			GWU_Geocode_Log::append_once_per_request(
				'tf|' . md5( $city . '|' . $state_abbr ),
				array(
					'kind'    => 'transient_miss_cached',
					'city'    => $city,
					'state'   => $state_abbr,
					'message' => 'Using cached geocode miss (Nominatim not called until TTL expires).',
				)
			);
			return null;
		}

		self::throttle_before_request();

		$query_text = $city . ', ' . self::state_name_for_query( $state_abbr ) . ', United States';
		$url        = add_query_arg(
			array(
				'format'         => 'json',
				'limit'          => '1',
				'q'              => $query_text,
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
			GWU_Geocode_Log::append(
				array(
					'kind'    => 'nominatim_wp_error',
					'city'    => $city,
					'state'   => $state_abbr,
					'query'   => $query_text,
					'message' => $response->get_error_message(),
				)
			);
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		if ( $code < 200 || $code >= 300 ) {
			set_transient( $key, 'fail', self::TTL_MISS );
			self::$request_cache[ $key ] = null;
			GWU_Geocode_Log::append(
				array(
					'kind'    => 'nominatim_http_error',
					'city'    => $city,
					'state'   => $state_abbr,
					'query'   => $query_text,
					'http'    => $code,
					'message' => substr( $body, 0, 200 ),
				)
			);
			return null;
		}

		$rows = json_decode( $body, true );
		if ( ! is_array( $rows ) || $rows === array() ) {
			set_transient( $key, 'fail', self::TTL_MISS );
			self::$request_cache[ $key ] = null;
			GWU_Geocode_Log::append(
				array(
					'kind'    => 'nominatim_empty',
					'city'    => $city,
					'state'   => $state_abbr,
					'query'   => $query_text,
					'http'    => $code,
					'message' => 'No results',
				)
			);
			return null;
		}

		$first = $rows[0];
		if ( ! is_array( $first ) ) {
			set_transient( $key, 'fail', self::TTL_MISS );
			self::$request_cache[ $key ] = null;
			GWU_Geocode_Log::append(
				array(
					'kind'  => 'nominatim_bad_row',
					'city'  => $city,
					'state' => $state_abbr,
					'query' => $query_text,
					'http'  => $code,
				)
			);
			return null;
		}

		$lat = isset( $first['lat'] ) ? (float) $first['lat'] : null;
		$lng = isset( $first['lon'] ) ? (float) $first['lon'] : null;
		if ( null === $lat || null === $lng || ( $lat === 0.0 && $lng === 0.0 ) ) {
			set_transient( $key, 'fail', self::TTL_MISS );
			self::$request_cache[ $key ] = null;
			GWU_Geocode_Log::append(
				array(
					'kind'  => 'nominatim_no_coords',
					'city'  => $city,
					'state' => $state_abbr,
					'query' => $query_text,
					'http'  => $code,
				)
			);
			return null;
		}

		$label = isset( $first['display_name'] ) ? (string) $first['display_name'] : '';

		$out = array( 'lat' => $lat, 'lng' => $lng );
		set_transient( $key, $out, self::TTL_HIT );
		self::$request_cache[ $key ] = $out;

		GWU_Geocode_Log::append(
			array(
				'kind'  => 'nominatim_ok',
				'city'  => $city,
				'state' => $state_abbr,
				'lat'   => $lat,
				'lng'   => $lng,
				'http'  => $code,
				'query' => $query_text,
				'label' => substr( $label, 0, 200 ),
			)
		);

		return $out;
	}

	/**
	 * USPS / territory code → full state or territory name (Nominatim `q=`, location parsing).
	 *
	 * @return array<string, string>
	 */
	private static function abbrev_to_us_state_name_map(): array {
		return array(
			'AL' => 'Alabama',
			'AK' => 'Alaska',
			'AZ' => 'Arizona',
			'AR' => 'Arkansas',
			'CA' => 'California',
			'CO' => 'Colorado',
			'CT' => 'Connecticut',
			'DE' => 'Delaware',
			'DC' => 'District of Columbia',
			'FL' => 'Florida',
			'GA' => 'Georgia',
			'HI' => 'Hawaii',
			'ID' => 'Idaho',
			'IL' => 'Illinois',
			'IN' => 'Indiana',
			'IA' => 'Iowa',
			'KS' => 'Kansas',
			'KY' => 'Kentucky',
			'LA' => 'Louisiana',
			'ME' => 'Maine',
			'MD' => 'Maryland',
			'MA' => 'Massachusetts',
			'MI' => 'Michigan',
			'MN' => 'Minnesota',
			'MS' => 'Mississippi',
			'MO' => 'Missouri',
			'MT' => 'Montana',
			'NE' => 'Nebraska',
			'NV' => 'Nevada',
			'NH' => 'New Hampshire',
			'NJ' => 'New Jersey',
			'NM' => 'New Mexico',
			'NY' => 'New York',
			'NC' => 'North Carolina',
			'ND' => 'North Dakota',
			'OH' => 'Ohio',
			'OK' => 'Oklahoma',
			'OR' => 'Oregon',
			'PA' => 'Pennsylvania',
			'RI' => 'Rhode Island',
			'SC' => 'South Carolina',
			'SD' => 'South Dakota',
			'TN' => 'Tennessee',
			'TX' => 'Texas',
			'UT' => 'Utah',
			'VT' => 'Vermont',
			'VA' => 'Virginia',
			'WA' => 'Washington',
			'WV' => 'West Virginia',
			'WI' => 'Wisconsin',
			'WY' => 'Wyoming',
			'PR' => 'Puerto Rico',
			'GU' => 'Guam',
			'VI' => 'U.S. Virgin Islands',
			'AS' => 'American Samoa',
			'MP' => 'Northern Mariana Islands',
		);
	}

	/**
	 * Expand abbr for Nominatim `q=` (e.g. MO → Missouri) so border twin cities resolve correctly.
	 *
	 * @return string Full name or the original abbr if unknown.
	 */
	private static function state_name_for_query( string $abbr ): string {
		$names = self::abbrev_to_us_state_name_map();
		return $names[ $abbr ] ?? $abbr;
	}

	/**
	 * Normalize API or location text to a two-letter state/territory code when possible.
	 */
	public static function resolve_state_token( string $token ): string {
		$token = trim( $token );
		if ( '' === $token ) {
			return '';
		}
		$u = strtoupper( $token );
		if ( preg_match( '/^[A-Z]{2}$/', $u ) ) {
			return $u;
		}
		$key = strtolower( $token );
		static $by_lower_name = null;
		if ( null === $by_lower_name ) {
			$by_lower_name = array();
			foreach ( self::abbrev_to_us_state_name_map() as $abbr => $name ) {
				$by_lower_name[ strtolower( $name ) ] = $abbr;
			}
		}
		return $by_lower_name[ $key ] ?? '';
	}

	/**
	 * Read geocode transient only (no HTTP, no in-request cache fill).
	 *
	 * @return 'hit'|'miss'|'empty'|'n/a'
	 */
	public static function peek_city_state_cache_status( string $city, string $state_abbr ): string {
		$city       = trim( $city );
		$state_abbr = strtoupper( trim( $state_abbr ) );
		if ( '' === $city || ! preg_match( '/^[A-Z]{2}$/', $state_abbr ) ) {
			return 'n/a';
		}
		$key = self::TRANSIENT_PREFIX . md5( $city . '|' . $state_abbr );
		$v   = get_transient( $key );
		if ( is_array( $v ) && isset( $v['lat'], $v['lng'] ) ) {
			return 'hit';
		}
		if ( $v === 'fail' ) {
			return 'miss';
		}
		return 'empty';
	}

	/**
	 * Read cached coordinates only (no HTTP). Returns null if not a hit.
	 *
	 * @return array{lat: float, lng: float}|null
	 */
	public static function peek_city_state_coordinates( string $city, string $state_abbr ): ?array {
		$city       = trim( $city );
		$state_abbr = strtoupper( trim( $state_abbr ) );
		if ( '' === $city || ! preg_match( '/^[A-Z]{2}$/', $state_abbr ) ) {
			return null;
		}
		$key = self::TRANSIENT_PREFIX . md5( $city . '|' . $state_abbr );
		$v   = get_transient( $key );
		if ( is_array( $v ) && isset( $v['lat'], $v['lng'] ) ) {
			return array(
				'lat' => (float) $v['lat'],
				'lng' => (float) $v['lng'],
			);
		}
		return null;
	}

	/**
	 * Delete the Nominatim result transient and drop the in-request cache entry for this pair.
	 */
	public static function delete_city_state_cache( string $city, string $state_abbr ): void {
		$city       = trim( $city );
		$state_abbr = strtoupper( trim( $state_abbr ) );
		if ( '' === $city || ! preg_match( '/^[A-Z]{2}$/', $state_abbr ) ) {
			return;
		}
		$key = self::TRANSIENT_PREFIX . md5( $city . '|' . $state_abbr );
		delete_transient( $key );
		unset( self::$request_cache[ $key ] );
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
