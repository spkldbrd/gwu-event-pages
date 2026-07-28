<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves event data for the current (or given) marketing page.
 *
 * Primary source: post meta `_gwu_event_data` JSON written by Hostlinks Page Sync.
 * Fallback: `_gwu_reg_url` + `_gwu_event_id` with a live fetch from the HMO REST API.
 */
class GWU_Event_Data {

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get_for_page( ?int $page_id = null ): ?array {
		$page_id = $page_id ?: (int) get_the_ID();
		if ( $page_id <= 0 ) {
			return null;
		}

		$raw = get_post_meta( $page_id, '_gwu_event_data', true );
		if ( is_string( $raw ) && $raw !== '' ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) && ! empty( $decoded['eve_id'] ) ) {
				return self::normalize_event_row( $decoded );
			}
		}

		$event_id = (int) get_post_meta( $page_id, '_gwu_event_id', true );
		if ( $event_id <= 0 ) {
			return null;
		}

		$fetched = self::fetch_event_from_api( $event_id );
		if ( $fetched ) {
			return $fetched;
		}

		$reg_url = trim( (string) get_post_meta( $page_id, '_gwu_reg_url', true ) );
		if ( $reg_url === '' ) {
			$reg_url = self::parse_reg_url_from_content( $page_id );
		}

		return array(
			'eve_id'          => $event_id,
			'eve_trainer_url' => $reg_url,
		);
	}

	/**
	 * @param array<string, mixed> $ev
	 * @return array<string, mixed>
	 */
	public static function normalize_event_row( array $ev ): array {
		if ( ! isset( $ev['eve_id'] ) && isset( $ev['id'] ) ) {
			$ev['eve_id'] = (int) $ev['id'];
		}
		return $ev;
	}

	public static function get_reg_url( ?int $page_id = null ): string {
		$page_id = $page_id ?: (int) get_the_ID();
		$ev      = self::get_for_page( $page_id );
		if ( $ev && ! empty( $ev['eve_trainer_url'] ) ) {
			return esc_url_raw( trim( (string) $ev['eve_trainer_url'] ) );
		}

		if ( $page_id > 0 ) {
			$reg = trim( (string) get_post_meta( $page_id, '_gwu_reg_url', true ) );
			if ( $reg !== '' ) {
				return esc_url_raw( $reg );
			}
			return self::parse_reg_url_from_content( $page_id );
		}

		return '';
	}

	/**
	 * Build {{TOKEN}} replacements for template sections.
	 *
	 * @param array<string, mixed> $ev
	 * @return array<string, string>
	 */
	public static function build_tokens( array $ev ): array {
		$city    = trim( (string) ( $ev['city'] ?? '' ) );
		$state   = trim( (string) ( $ev['state'] ?? '' ) );
		$zip     = trim( (string) ( $ev['zip_code'] ?? '' ) );
		$addr1   = trim( (string) ( $ev['street_address_1'] ?? '' ) );
		$addr2   = trim( (string) ( $ev['street_address_2'] ?? '' ) );
		$addr3   = trim( (string) ( $ev['street_address_3'] ?? '' ) );
		$venue   = trim( (string) ( $ev['location_name'] ?? '' ) );
		$host    = trim( (string) ( $ev['host_name'] ?? '' ) );
		$start   = (string) ( $ev['eve_start'] ?? '' );
		$end     = (string) ( $ev['eve_end'] ?? '' );
		$hosted_by = $host ?: $venue;

		$city_state_zip = implode( ', ', array_filter( array( $city, $state ) ) );
		if ( $zip !== '' ) {
			$city_state_zip .= ' ' . $zip;
		}
		$address_lines = array_filter( array( $addr1, $addr2, $addr3, $city_state_zip ) );
		$address_html  = implode( '<br>', array_map( 'esc_html', $address_lines ) );

		$map_query = rawurlencode( implode( ', ', array_filter( array( $addr1, $city, $state, $zip ) ) ) );
		$map_url   = 'https://maps.google.com/?q=' . $map_query;

		$host_line  = $hosted_by ? '<br>Hosted by ' . esc_html( $hosted_by ) : '';
		$addr_block = $address_html ? '<br>' . $address_html : '';

		return array(
			'{{DATE_LONG}}'  => esc_html( self::format_date_range( $start, $end ) ),
			'{{MAP_URL}}'    => esc_url( $map_url ),
			'{{HOST_LINE}}'  => $host_line,
			'{{ADDR_BLOCK}}' => $addr_block,
			'{{CITY_STATE}}' => esc_html( self::extract_city_state( (string) ( $ev['eve_location'] ?? '' ) ) ),
		);
	}

	public static function format_date_range( string $start, string $end ): string {
		if ( $start === '' ) {
			return '';
		}
		$s = date_create( $start );
		if ( ! $s ) {
			return '';
		}
		$e  = $end !== '' ? date_create( $end ) : null;
		$sm = $s->format( 'F' );
		$sd = (int) $s->format( 'j' );
		$sy = $s->format( 'Y' );

		if ( ! $e || $start === $end ) {
			return $sm . ' ' . $sd . ', ' . $sy;
		}
		$em = $e->format( 'F' );
		$ed = (int) $e->format( 'j' );
		$ey = $e->format( 'Y' );

		if ( $sm === $em && $sy === $ey ) {
			return $sm . ' ' . $sd . '-' . $ed . ', ' . $sy;
		}
		return $sm . ' ' . $sd . '-' . $em . ' ' . $ed . ', ' . $sy;
	}

	private static function extract_city_state( string $location ): string {
		$location = trim( $location );
		if ( preg_match( '/^([A-Za-z][A-Za-z0-9\s\/\-\.]+,\s*[A-Z]{2})\b/u', $location, $m ) ) {
			return trim( $m[1] );
		}
		return $location;
	}

	private static function parse_reg_url_from_content( int $page_id ): string {
		$post = get_post( $page_id );
		if ( ! $post || $post->post_content === '' ) {
			return '';
		}
		if ( preg_match( '/class="gwu-reg-btn"[^>]*href="([^"]+)"/i', $post->post_content, $m ) ) {
			return esc_url_raw( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) );
		}
		return '';
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function fetch_event_from_api( int $event_id ): ?array {
		$api = GWU_Admin::get_hmo_api();
		foreach ( array( 'public-events', 'past-events?years=10' ) as $path ) {
			$response = wp_remote_get( rtrim( $api, '/' ) . '/' . $path, array( 'timeout' => 8 ) );
			if ( is_wp_error( $response ) ) {
				continue;
			}
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $data ) || empty( $data['events'] ) ) {
				continue;
			}
			foreach ( $data['events'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				if ( (int) ( $row['id'] ?? 0 ) === $event_id ) {
					return self::api_row_to_snapshot( $row );
				}
			}
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private static function api_row_to_snapshot( array $row ): array {
		return array(
			'eve_id'          => (int) ( $row['id'] ?? 0 ),
			'eve_type'        => (int) ( $row['type_id'] ?? 0 ),
			'eve_zoom'        => (string) ( $row['zoom'] ?? '' ),
			'eve_start'       => (string) ( $row['start'] ?? '' ),
			'eve_end'         => (string) ( $row['end'] ?? '' ),
			'eve_location'    => (string) ( $row['location'] ?? '' ),
			'city'            => (string) ( $row['city'] ?? '' ),
			'state'           => (string) ( $row['state'] ?? '' ),
			'eve_trainer_url' => (string) ( $row['reg_url'] ?? '' ),
		);
	}
}
