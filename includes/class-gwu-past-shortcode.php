<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders [past_event_list] on grantwritingusa.com by fetching completed
 * event data from the Hostlinks subdomain's /past-events REST endpoint.
 *
 * Events are grouped by year (newest first) and split into two columns
 * matching the upcoming events layout.  Past events are cached for 24 hours
 * since historical data does not change.
 *
 * Usage:
 *   [past_event_list]                — last 2 years
 *   [past_event_list years="3"]      — last 3 years (max 10)
 *   [past_event_list cache="0"]      — force fresh fetch (admin use)
 */
class GWU_Past_Shortcode {

	const TRANSIENT_BASE = 'gwu_ep_past_events';

	public function register(): void {
		add_shortcode( 'past_event_list', array( $this, 'render' ) );
	}

	public function render( $atts ): string {
		$atts = shortcode_atts( array(
			'years' => '2',
			'cache' => '1',
		), $atts, 'past_event_list' );

		$years      = max( 1, min( 10, (int) $atts['years'] ) );
		$cache_key  = self::TRANSIENT_BASE . '_' . $years;
		$bust_cache = ( $atts['cache'] === '0' );

		$payload = $bust_cache ? false : get_transient( $cache_key );

		if ( $payload === false ) {
			$payload = $this->fetch_events( $years );
			if ( $payload !== null ) {
				// Past events are historical — cache for 24 hours.
				set_transient( $cache_key, $payload, DAY_IN_SECONDS );
			}
		}

		if ( empty( $payload['events'] ) ) {
			return '<p class="hpl-no-events">No past events found.</p>';
		}

		return $this->render_list( $payload );
	}

	// -------------------------------------------------------------------------
	// HTTP fetch
	// -------------------------------------------------------------------------

	private function fetch_events( int $years ): ?array {
		$url      = rtrim( GWU_Admin::get_hmo_api(), '/' ) . '/past-events?years=' . $years;
		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			error_log( 'GWU Event Pages: past-events fetch error — ' . $response->get_error_message() );
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			error_log( 'GWU Event Pages: past-events unexpected response ' . $code . ' from ' . $url );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! isset( $data['events'] ) ) {
			error_log( 'GWU Event Pages: past-events malformed response from ' . $url );
			return null;
		}

		return $data;
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	private function render_list( array $payload ): string {
		$events = $payload['events'];

		// Group by year (API returns DESC, so newest year first).
		$by_year = array();
		foreach ( $events as $ev ) {
			$year = substr( $ev['start'] ?? '', 0, 4 );
			if ( $year ) {
				$by_year[ $year ][] = $ev;
			}
		}

		ob_start();
		?>
		<div class="hpl-past-wrapper">
		<?php foreach ( $by_year as $year => $year_events ) :
			$left_events  = array_values( array_filter( $year_events, fn( $e ) => ( $e['column'] ?? '' ) === 'left' ) );
			$right_events = array_values( array_filter( $year_events, fn( $e ) => ( $e['column'] ?? '' ) === 'right' ) );
			// Events with no column assignment go into the left column.
			$unassigned   = array_values( array_filter( $year_events, fn( $e ) => ( $e['column'] ?? '' ) === '' ) );
			$left_events  = array_merge( $left_events, $unassigned );
		?>
			<div class="hpl-past-year">
				<h3 class="hpl-past-year__heading">
					<?php echo esc_html( $year ); ?>
					<span class="hpl-past-year__count">(<?php echo count( $year_events ); ?> events)</span>
				</h3>
				<div class="hpl-past-columns">
					<div class="hpl-past-col">
						<h4 class="hpl-past-col__heading">Grant Writing Workshops</h4>
						<?php if ( empty( $left_events ) ) : ?>
							<p class="hpl-past-none">None this year</p>
						<?php else : ?>
						<ul class="hpl-past-list">
							<?php foreach ( $left_events as $ev ) : ?>
							<li><?php echo $this->render_event( $ev ); // phpcs:ignore WordPress.Security.EscapeOutput ?></li>
							<?php endforeach; ?>
						</ul>
						<?php endif; ?>
					</div>
					<div class="hpl-past-col">
						<h4 class="hpl-past-col__heading">Grant Management Workshops</h4>
						<?php if ( empty( $right_events ) ) : ?>
							<p class="hpl-past-none">None this year</p>
						<?php else : ?>
						<ul class="hpl-past-list">
							<?php foreach ( $right_events as $ev ) : ?>
							<li><?php echo $this->render_event( $ev ); // phpcs:ignore WordPress.Security.EscapeOutput ?></li>
							<?php endforeach; ?>
						</ul>
						<?php endif; ?>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_event( array $ev ): string {
		$is_zoom = ( ( $ev['zoom'] ?? '' ) === 'yes' );

		if ( $is_zoom ) {
			$location = 'Zoom Webinar';
		} elseif ( ! empty( $ev['city'] ) && ! empty( $ev['state'] ) ) {
			$location = $ev['city'] . ', ' . $ev['state'];
		} else {
			$location = $this->extract_city_state( $ev['location'] ?? '' );
		}

		$date     = $this->format_date_range( $ev['start'] ?? '', $ev['end'] ?? '' );
		$web_url  = $ev['web_url'] ?? '';
		$type     = strtolower( $ev['type_name'] ?? '' );

		$out = '<span class="hpl-past-location">';
		if ( $web_url && $web_url !== '#' ) {
			$out .= '<a href="' . esc_url( $web_url ) . '">' . esc_html( $location ) . '</a>';
		} else {
			$out .= esc_html( $location );
		}
		$out .= '</span>';

		if ( $date ) {
			$out .= ' <span class="hpl-past-date">' . esc_html( $date ) . '</span>';
		}

		// Badge for types that aren't standard writing/management (e.g. Subaward).
		if ( $type && $type !== 'writing' && $type !== 'management' ) {
			$out .= ' <span class="hpl-past-type-badge">' . esc_html( ucfirst( $ev['type_name'] ) ) . '</span>';
		}

		if ( $is_zoom ) {
			$out .= ' <span class="hpl-zoom-badge">Zoom</span>';
		}

		return $out;
	}

	// -------------------------------------------------------------------------
	// Helpers (mirrors GWU_Shortcode)
	// -------------------------------------------------------------------------

	private function extract_city_state( string $location ): string {
		$location = trim( $location );
		if ( preg_match( '/^([A-Za-z][A-Za-z0-9\s\/\-\.]+,\s*[A-Z]{2})\b/u', $location, $m ) ) {
			return trim( $m[1] );
		}
		return $location;
	}

	private function format_date_range( string $start, string $end ): string {
		if ( empty( $start ) ) {
			return '';
		}
		$s = date_create( $start );
		if ( ! $s ) {
			return '';
		}
		$e  = $end ? date_create( $end ) : null;
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
}
