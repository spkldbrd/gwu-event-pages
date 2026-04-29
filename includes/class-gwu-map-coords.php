<?php
/**
 * Map pin coordinates: prefers city + state via OSM Nominatim (cached), with
 * state-centroid + jitter fallback when geocoding is unavailable.
 *
 * @package GWU_Event_Pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GWU_Map_Coords {

	/** @var array<string, array{0: float, 1: float}> */
	private static $centers = array(
		'AK' => array( 63.588753, -154.493062 ),
		'AL' => array( 32.318231, -86.902298 ),
		'AR' => array( 35.20105, -91.831833 ),
		'AZ' => array( 34.048928, -111.093731 ),
		'CA' => array( 36.778261, -119.417932 ),
		'CO' => array( 39.550051, -105.782067 ),
		'CT' => array( 41.603221, -73.087749 ),
		'DC' => array( 38.905985, -77.033418 ),
		'DE' => array( 38.910832, -75.52767 ),
		'FL' => array( 27.664827, -81.515754 ),
		'GA' => array( 32.157435, -82.907123 ),
		'HI' => array( 19.898682, -155.665857 ),
		'IA' => array( 41.878003, -93.097702 ),
		'ID' => array( 44.068202, -114.742041 ),
		'IL' => array( 40.633125, -89.398528 ),
		'IN' => array( 40.551217, -85.602364 ),
		'KS' => array( 39.011902, -98.484246 ),
		'KY' => array( 37.839333, -84.270018 ),
		'LA' => array( 31.244823, -92.145024 ),
		'MA' => array( 42.407211, -71.382437 ),
		'MD' => array( 39.045755, -76.641271 ),
		'ME' => array( 45.253783, -69.445469 ),
		'MI' => array( 44.314844, -85.602364 ),
		'MN' => array( 46.729553, -94.6859 ),
		'MO' => array( 37.964253, -91.831833 ),
		'MS' => array( 32.354668, -89.398528 ),
		'MT' => array( 46.879682, -110.362566 ),
		'NC' => array( 35.759573, -79.0193 ),
		'ND' => array( 47.551493, -101.002012 ),
		'NE' => array( 41.492537, -99.901813 ),
		'NH' => array( 43.193852, -71.572395 ),
		'NJ' => array( 40.058324, -74.405661 ),
		'NM' => array( 34.97273, -105.032363 ),
		'NV' => array( 38.80261, -116.419389 ),
		'NY' => array( 43.299428, -74.217933 ),
		'OH' => array( 40.417287, -82.907123 ),
		'OK' => array( 35.007752, -97.092877 ),
		'OR' => array( 43.804133, -120.554201 ),
		'PA' => array( 41.203322, -77.194525 ),
		'PR' => array( 18.220833, -66.590149 ),
		'RI' => array( 41.580095, -71.477429 ),
		'SC' => array( 33.836081, -81.163725 ),
		'SD' => array( 43.969515, -99.901813 ),
		'TN' => array( 35.517491, -86.580447 ),
		'TX' => array( 31.968599, -99.901813 ),
		'UT' => array( 39.32098, -111.093731 ),
		'VA' => array( 37.431573, -78.656894 ),
		'VT' => array( 44.558803, -72.577841 ),
		'WA' => array( 47.751074, -120.740139 ),
		'WI' => array( 43.78444, -88.787868 ),
		'WV' => array( 38.597626, -80.454903 ),
		'WY' => array( 43.075968, -107.290284 ),
	);

	/**
	 * @param array<string, mixed> $ev Event row from public-events API.
	 * @return array{lat: float, lng: float}|null
	 */
	public static function pin_for_event( array $ev ): ?array {
		if ( ( $ev['zoom'] ?? '' ) === 'yes' ) {
			return null;
		}

		$abbr = self::state_abbr( $ev );
		$city = self::city_for_geocode( $ev );
		$id   = (int) ( $ev['id'] ?? 0 );

		if ( '' !== $city && preg_match( '/^[A-Z]{2}$/', $abbr ) ) {
			$geo = GWU_Geocode::lookup_city_state( $city, $abbr );
			if ( is_array( $geo ) ) {
				$j = self::jitter_city( $id );
				return array(
					'lat' => $geo['lat'] + $j[0],
					'lng' => $geo['lng'] + $j[1],
				);
			}
			GWU_Geocode_Log::append_once_per_request(
				'fb|' . md5( $city . '|' . $abbr ),
				array(
					'kind'    => 'pin_state_fallback',
					'city'    => $city,
					'state'   => $abbr,
					'message' => 'Geocode miss or unavailable; map pin uses state centroid + spread (not city center).',
				)
			);
		}

		if ( '' === $abbr || ! isset( self::$centers[ $abbr ] ) ) {
			return null;
		}

		$c    = self::$centers[ $abbr ];
		$j    = self::jitter_state( $id );
		$latf = (float) $c[0] + $j[0];
		$lngf = (float) $c[1] + $j[1];

		return array(
			'lat' => $latf,
			'lng' => $lngf,
		);
	}

	/**
	 * Pre-warm Nominatim transients for every unique city/state the map would geocode.
	 *
	 * @param array<int, mixed> $events Raw events from public-events API.
	 * @return int Number of unique (city, state) pairs processed.
	 */
	public static function warm_geocode_cache_for_events( array $events ): int {
		$pairs = array();
		foreach ( $events as $ev ) {
			if ( ! is_array( $ev ) ) {
				continue;
			}
			if ( ( $ev['zoom'] ?? '' ) === 'yes' ) {
				continue;
			}
			$abbr = self::state_abbr( $ev );
			$city = self::city_for_geocode( $ev );
			if ( '' === $city || ! preg_match( '/^[A-Z]{2}$/', $abbr ) ) {
				continue;
			}
			$key = $city . '|' . $abbr;
			if ( ! isset( $pairs[ $key ] ) ) {
				$pairs[ $key ] = array( 'city' => $city, 'state' => $abbr );
			}
		}
		foreach ( $pairs as $row ) {
			GWU_Geocode::lookup_city_state( $row['city'], $row['state'] );
		}
		return count( $pairs );
	}

	/**
	 * Parse a leading "City, ST" segment from a location string (same rule as list titles).
	 *
	 * @return array{city: string, state: string}|null
	 */
	public static function match_location_city_state( string $location ): ?array {
		$location = trim( $location );
		if ( '' === $location ) {
			return null;
		}
		if ( preg_match( '/^([A-Za-z][A-Za-z0-9\s\/\-\.]+),\s*([A-Z]{2})\b/u', $location, $m ) ) {
			return array(
				'city'  => trim( $m[1] ),
				'state' => strtoupper( $m[2] ),
			);
		}
		if ( preg_match( '/^(.+),[ \t]+([A-Za-z][A-Za-z0-9\s\.\-]+)\s*$/u', $location, $m ) ) {
			$tail = trim( $m[2] );
			$abbr = GWU_Geocode::resolve_state_token( $tail );
			if ( '' !== $abbr ) {
				return array(
					'city'  => trim( $m[1] ),
					'state' => $abbr,
				);
			}
		}
		return null;
	}

	/**
	 * City + state line for display when the API omits structured city/state fields.
	 */
	public static function format_city_state_line( string $location ): string {
		$parsed = self::match_location_city_state( $location );
		if ( null !== $parsed ) {
			return $parsed['city'] . ', ' . $parsed['state'];
		}
		return trim( $location );
	}

	/**
	 * City name for geocoding: API `city`, else parsed from `location` "City, ST".
	 *
	 * @param array<string, mixed> $ev Event row.
	 */
	private static function city_for_geocode( array $ev ): string {
		$c = trim( (string) ( $ev['city'] ?? '' ) );
		if ( '' !== $c ) {
			return $c;
		}
		$parsed = self::match_location_city_state( (string) ( $ev['location'] ?? '' ) );
		return null !== $parsed ? $parsed['city'] : '';
	}

	/**
	 * @param array<string, mixed> $ev Event row.
	 */
	public static function state_abbr( array $ev ): string {
		$raw = trim( (string) ( $ev['state'] ?? '' ) );
		$api = GWU_Geocode::resolve_state_token( $raw );
		if ( '' !== $api ) {
			return $api;
		}
		$loc = (string) ( $ev['location'] ?? '' );
		if ( preg_match( '/,\s*([A-Z]{2})\b/', $loc, $m ) ) {
			return $m[1];
		}
		$parsed = self::match_location_city_state( $loc );
		if ( null !== $parsed ) {
			return strtoupper( (string) $parsed['state'] );
		}
		return '';
	}

	/**
	 * Offset for city-level pins (~2 km max from geocoded center) so same-city workshops separate slightly.
	 *
	 * @return array{0: float, 1: float} lat, lng deltas in degrees.
	 */
	private static function jitter_city( int $id ): array {
		$t = ( ( $id * 137 ) % 6283 ) / 1000.0 * M_PI;
		$r = 0.018;
		return array( $r * sin( $t ), $r * cos( $t ) );
	}

	/**
	 * Larger offset for state-centroid fallback pins.
	 *
	 * @return array{0: float, 1: float} lat, lng deltas in degrees.
	 */
	private static function jitter_state( int $id ): array {
		$t = ( ( $id * 137 ) % 6283 ) / 1000.0 * M_PI;
		$r = 0.22;
		return array( $r * sin( $t ), $r * cos( $t ) );
	}

	/**
	 * City + ST pair used for the Nominatim transient, or null when the map does not run city geocode.
	 *
	 * @param array<string, mixed> $ev Event row from public-events API.
	 * @return array{city: string, state: string}|null
	 */
	public static function geocode_cache_pair_for_event( array $ev ): ?array {
		if ( ( $ev['zoom'] ?? '' ) === 'yes' ) {
			return null;
		}
		$abbr = self::state_abbr( $ev );
		$city = self::city_for_geocode( $ev );
		if ( '' === $city || ! preg_match( '/^[A-Z]{2}$/', $abbr ) ) {
			return null;
		}
		return array(
			'city'  => $city,
			'state' => $abbr,
		);
	}

	/**
	 * Pin diagnostics for admin screens without calling Nominatim.
	 *
	 * @param array<string, mixed> $ev Event row from public-events API.
	 * @return array{
	 *   zoom: bool,
	 *   event_id: int,
	 *   api_city: string,
	 *   api_state: string,
	 *   location: string,
	 *   resolved_city: string,
	 *   resolved_st: string,
	 *   cache_status: string,
	 *   pin_source: string,
	 *   lat: float|null,
	 *   lng: float|null,
	 *   pin_note: string
	 * }
	 */
	public static function admin_describe_pin( array $ev ): array {
		$zoom      = ( $ev['zoom'] ?? '' ) === 'yes';
		$api_city  = trim( (string) ( $ev['city'] ?? '' ) );
		$api_state = trim( (string) ( $ev['state'] ?? '' ) );
		$location  = (string) ( $ev['location'] ?? '' );
		$event_id  = (int) ( $ev['id'] ?? 0 );
		$abbr      = self::state_abbr( $ev );
		$city      = self::city_for_geocode( $ev );
		$pair      = self::geocode_cache_pair_for_event( $ev );

		$base = array(
			'zoom'          => $zoom,
			'event_id'      => $event_id,
			'api_city'      => $api_city,
			'api_state'     => $api_state,
			'location'      => $location,
			'resolved_city' => $city,
			'resolved_st'   => $abbr,
		);

		if ( $zoom ) {
			return array_merge(
				$base,
				array(
					'cache_status' => 'n/a',
					'pin_source'   => 'zoom',
					'lat'          => null,
					'lng'          => null,
					'pin_note'     => '',
				)
			);
		}

		$cache_status = null !== $pair
			? GWU_Geocode::peek_city_state_cache_status( $pair['city'], $pair['state'] )
			: 'n/a';

		$lat       = null;
		$lng       = null;
		$pin_note  = '';
		$pin_source = 'no_pin';

		if ( null !== $pair ) {
			$geo_coords = GWU_Geocode::peek_city_state_coordinates( $pair['city'], $pair['state'] );
			if ( is_array( $geo_coords ) ) {
				$j          = self::jitter_city( $event_id );
				$lat        = $geo_coords['lat'] + $j[0];
				$lng        = $geo_coords['lng'] + $j[1];
				$pin_source = 'nominatim';
			} else {
				$pin_source = ( 'miss' === $cache_status ) ? 'state_centroid_geocode_miss' : 'state_centroid_pending';
				if ( '' !== $abbr && isset( self::$centers[ $abbr ] ) ) {
					$c   = self::$centers[ $abbr ];
					$j   = self::jitter_state( $event_id );
					$lat = (float) $c[0] + $j[0];
					$lng = (float) $c[1] + $j[1];
				} else {
					$pin_source = 'no_pin';
				}
				if ( 'empty' === $cache_status ) {
					$pin_note = 'Coordinates shown are the state-centroid fallback until Nominatim caches a city hit.';
				}
			}
		} elseif ( '' !== $abbr && isset( self::$centers[ $abbr ] ) ) {
			$c          = self::$centers[ $abbr ];
			$j          = self::jitter_state( $event_id );
			$lat        = (float) $c[0] + $j[0];
			$lng        = (float) $c[1] + $j[1];
			$pin_source = 'state_centroid_no_city_state_query';
		}

		return array_merge(
			$base,
			array(
				'cache_status' => $cache_status,
				'pin_source'   => $pin_source,
				'lat'          => $lat,
				'lng'          => $lng,
				'pin_note'     => $pin_note,
			)
		);
	}
}
