<?php
/**
 * Ring buffer of geocode / pin placement events for admin review.
 *
 * @package GWU_Event_Pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GWU_Geocode_Log {

	public const OPTION_KEY = 'gwu_ep_geocode_log';

	public const MAX_ENTRIES = 150;

	/** @var array<string, true> */
	private static $dedupe = array();

	/**
	 * @param array<string, mixed> $row Must include string "kind".
	 */
	public static function append( array $row ): void {
		$row['t'] = gmdate( 'c' );
		$log      = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		array_unshift( $log, $row );
		if ( count( $log ) > self::MAX_ENTRIES ) {
			$log = array_slice( $log, 0, self::MAX_ENTRIES );
		}
		update_option( self::OPTION_KEY, $log, false );
	}

	/**
	 * One log line per HTTP request per dedupe key (e.g. same city|state on one page load).
	 *
	 * @param array<string, mixed> $row
	 */
	public static function append_once_per_request( string $dedup_key, array $row ): void {
		if ( isset( self::$dedupe[ $dedup_key ] ) ) {
			return;
		}
		self::$dedupe[ $dedup_key ] = true;
		self::append( $row );
	}

	public static function clear(): void {
		delete_option( self::OPTION_KEY );
		self::$dedupe = array();
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_entries(): array {
		$log = get_option( self::OPTION_KEY, array() );
		return is_array( $log ) ? $log : array();
	}
}
