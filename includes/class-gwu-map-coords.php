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
	 * Parse a leading "City, ST" segment from a location string (same rule as list titles).
	 *
	 * @return array{city: string, state: string}|null
	 */
	public static function match_location_city_state( string $location ): ?array {
		$location = trim( $location );
		if ( ! preg_match( '/^([A-Za-z][A-Za-z0-9\s\/\-\.]+),\s*([A-Z]{2})\b/u', $location, $m ) ) {
			return null;
		}
		return array(
			'city'  => trim( $m[1] ),
			'state' => $m[2],
		);
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
		$s = strtoupper( trim( (string) ( $ev['state'] ?? '' ) ) );
		if ( preg_match( '/^[A-Z]{2}$/', $s ) ) {
			return $s;
		}
		$loc = (string) ( $ev['location'] ?? '' );
		if ( preg_match( '/,\s*([A-Z]{2})\b/', $loc, $m ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * Offset for city-level pins (~1.5 km) so same-city workshops separate slightly.
	 *
	 * @return array{0: float, 1: float} lat, lng deltas in degrees.
	 */
	private static function jitter_city( int $id ): array {
		$t = ( ( $id * 137 ) % 6283 ) / 1000.0 * M_PI;
		$r = 0.014;
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
}
