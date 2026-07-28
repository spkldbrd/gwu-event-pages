<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves the registration URL for the current event marketing page.
 */
class GWU_Event_Data {

	public static function get_reg_url( ?int $page_id = null ): string {
		$page_id = $page_id ?: (int) get_the_ID();
		if ( $page_id <= 0 ) {
			return '';
		}

		$reg = trim( (string) get_post_meta( $page_id, '_gwu_reg_url', true ) );
		if ( $reg !== '' ) {
			return esc_url_raw( $reg );
		}

		$event_id = (int) get_post_meta( $page_id, '_gwu_event_id', true );
		if ( $event_id > 0 ) {
			$from_api = self::fetch_reg_url_from_api( $event_id );
			if ( $from_api !== '' ) {
				return $from_api;
			}
		}

		return self::parse_reg_url_from_content( $page_id );
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

	private static function fetch_reg_url_from_api( int $event_id ): string {
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
				if ( (int) ( $row['id'] ?? 0 ) === $event_id && ! empty( $row['reg_url'] ) ) {
					return esc_url_raw( trim( (string) $row['reg_url'] ) );
				}
			}
		}
		return '';
	}
}
