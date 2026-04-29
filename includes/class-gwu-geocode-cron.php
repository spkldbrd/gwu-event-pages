<?php
/**
 * Daily WP-Cron job to pre-warm OSM Nominatim geocode transients for map pins.
 *
 * @package GWU_Event_Pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GWU_Geocode_Cron {

	public const HOOK = 'gwu_ep_geocode_warm_daily';

	public static function init(): void {
		add_action( self::HOOK, array( __CLASS__, 'run_warm' ) );
	}

	public static function activate(): void {
		self::schedule_if_missing();
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Ensure the daily event exists (covers plugin updates without re-activation).
	 */
	public static function schedule_if_missing(): void {
		if ( wp_next_scheduled( self::HOOK ) ) {
			return;
		}
		$ts = self::next_midnight_site_timestamp();
		wp_schedule_event( $ts, 'daily', self::HOOK );
	}

	/**
	 * Next calendar midnight (00:00:00) in the site timezone.
	 */
	private static function next_midnight_site_timestamp(): int {
		$tz   = wp_timezone();
		$next = new DateTimeImmutable( 'tomorrow', $tz );
		return $next->setTime( 0, 0, 0 )->getTimestamp();
	}

	public static function run_warm(): void {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 );
		}
		@ini_set( 'max_execution_time', '300' );

		$t0 = microtime( true );

		$payload = GWU_Shortcode::fetch_public_events_payload();
		if ( null === $payload ) {
			error_log( 'GWU Event Pages: geocode warm cron — fetch failed' );
			return;
		}

		$events = $payload['events'] ?? array();
		if ( ! is_array( $events ) ) {
			$events = array();
		}

		$n = GWU_Map_Coords::warm_geocode_cache_for_events( $events );

		$ms = (int) round( ( microtime( true ) - $t0 ) * 1000 );
		error_log(
			sprintf(
				'GWU Event Pages: geocode warm cron — %d city/state pair(s) in %d ms',
				$n,
				$ms
			)
		);
	}
}
