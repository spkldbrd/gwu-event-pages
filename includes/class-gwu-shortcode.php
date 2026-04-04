<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders [public_event_list] on grantwritingusa.com by fetching live event
 * data from the Hostlinks subdomain's public REST endpoint.
 *
 * Results are cached in a WordPress transient (default 15 minutes) so the
 * subdomain isn't hit on every page load.  Admins can pass cache="0" to the
 * shortcode to force a fresh fetch.
 *
 * Usage:
 *   [public_event_list]
 *   [public_event_list cache="0"]
 *   [public_event_list left_heading="Writing Workshops" right_heading="Management Workshops"]
 */
class GWU_Shortcode {

	const TRANSIENT_KEY = 'gwu_public_events';
	const CACHE_TTL     = 900; // 15 minutes

	public function register(): void {
		add_shortcode( 'public_event_list', array( $this, 'render' ) );
	}

	public function render( $atts ): string {
		$atts = shortcode_atts( array(
			'cache' => '1',
		), $atts, 'public_event_list' );

		$bust_cache = ( $atts['cache'] === '0' );

		$payload = $bust_cache ? false : get_transient( self::TRANSIENT_KEY );

		if ( $payload === false ) {
			$payload = $this->fetch_events();
			if ( $payload !== null ) {
				set_transient( self::TRANSIENT_KEY, $payload, self::CACHE_TTL );
			}
		}

		if ( empty( $payload['events'] ) ) {
			// Use stale cache if any is left (e.g. on a failed fresh fetch).
			$stale = get_transient( self::TRANSIENT_KEY . '_stale' );
			if ( $stale ) {
				$payload = $stale;
			} else {
				return '<p class="hpl-no-events">Upcoming events will be listed here shortly. Please check back soon.</p>';
			}
		}

		// Save a longer-lived stale backup.
		if ( ! empty( $payload['events'] ) ) {
			set_transient( self::TRANSIENT_KEY . '_stale', $payload, DAY_IN_SECONDS );
		}

		return $this->render_list( $payload );
	}

	// -------------------------------------------------------------------------
	// HTTP fetch
	// -------------------------------------------------------------------------

	private function fetch_events(): ?array {
		$url      = rtrim( GWU_EP_HMO_API, '/' ) . '/public-events';
		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			error_log( 'GWU Event Pages: fetch error — ' . $response->get_error_message() );
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			error_log( 'GWU Event Pages: unexpected response ' . $code . ' from ' . $url );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! isset( $data['events'] ) ) {
			error_log( 'GWU Event Pages: malformed response from ' . $url );
			return null;
		}

		return $data;
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	private function render_list( array $payload ): string {
		$events = $payload['events'];
		$meta   = $payload['meta'] ?? array();

		$left_heading      = $meta['left_heading']      ?? 'Grant Writing Workshops';
		$left_heading_tag  = $this->safe_tag( $meta['left_heading_tag']  ?? 'h2' );
		$left_desc         = $meta['left_desc']         ?? '';
		$left_desc_tag     = $this->safe_tag( $meta['left_desc_tag']     ?? 'p' );
		$right_heading     = $meta['right_heading']     ?? 'Grant Management Workshops';
		$right_heading_tag = $this->safe_tag( $meta['right_heading_tag'] ?? 'h2' );
		$right_desc        = $meta['right_desc']        ?? '';
		$right_desc_tag    = $this->safe_tag( $meta['right_desc_tag']    ?? 'p' );
		$zoom_east         = $meta['zoom_east']         ?? '9:30-4:30 EST';
		$zoom_west         = $meta['zoom_west']         ?? '8:00-3:00 PST';
		$zoom_default      = $meta['zoom_default']      ?? '9:30-4:30 EST';

		$left_events  = array();
		$right_events = array();

		foreach ( $events as $ev ) {
			if ( $ev['column'] === 'left' ) {
				$left_events[] = $ev;
			} elseif ( $ev['column'] === 'right' ) {
				$right_events[] = $ev;
			}
		}

		ob_start();
		?>
		<div class="hpl-wrapper">

			<!-- Left column -->
			<div class="hpl-column">
				<?php if ( $left_heading ) : ?>
				<<?php echo esc_attr( $left_heading_tag ); ?> class="hpl-col-heading"><?php echo esc_html( $left_heading ); ?></<?php echo esc_attr( $left_heading_tag ); ?>>
				<?php endif; ?>
				<?php if ( $left_desc ) : ?>
				<<?php echo esc_attr( $left_desc_tag ); ?> class="hpl-col-desc"><?php echo wp_kses_post( $left_desc ); ?></<?php echo esc_attr( $left_desc_tag ); ?>>
				<?php endif; ?>

				<ul class="hpl-event-list">
				<?php foreach ( $left_events as $ev ) : ?>
					<li class="hpl-event-item">
						<?php echo $this->render_event( $ev, $zoom_east, $zoom_west, $zoom_default ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</li>
				<?php endforeach; ?>
				<?php if ( empty( $left_events ) ) : ?>
					<li class="hpl-no-events">No upcoming events at this time.</li>
				<?php endif; ?>
				</ul>
			</div>

			<!-- Right column -->
			<div class="hpl-column">
				<?php if ( $right_heading ) : ?>
				<<?php echo esc_attr( $right_heading_tag ); ?> class="hpl-col-heading"><?php echo esc_html( $right_heading ); ?></<?php echo esc_attr( $right_heading_tag ); ?>>
				<?php endif; ?>
				<?php if ( $right_desc ) : ?>
				<<?php echo esc_attr( $right_desc_tag ); ?> class="hpl-col-desc"><?php echo wp_kses_post( $right_desc ); ?></<?php echo esc_attr( $right_desc_tag ); ?>>
				<?php endif; ?>

				<ul class="hpl-event-list">
				<?php foreach ( $right_events as $ev ) : ?>
					<li class="hpl-event-item">
						<?php echo $this->render_event( $ev, $zoom_east, $zoom_west, $zoom_default ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</li>
				<?php endforeach; ?>
				<?php if ( empty( $right_events ) ) : ?>
					<li class="hpl-no-events">No upcoming events at this time.</li>
				<?php endif; ?>
				</ul>
			</div>

		</div><!-- .hpl-wrapper -->
		<?php
		return ob_get_clean();
	}

	private function render_event( array $ev, string $zoom_east, string $zoom_west, string $zoom_default ): string {
		$is_zoom = ( ( $ev['zoom'] ?? '' ) === 'yes' );

		// Title.
		if ( $is_zoom ) {
			$title = 'ZOOM WEBINAR';
		} else {
			$title = $this->extract_city_state( $ev['location'] ?? '' );
			if ( $ev['city'] && $ev['state'] ) {
				$title = $ev['city'] . ', ' . $ev['state'];
			}
		}

		// Date string.
		$date_str = $this->format_date_range( $ev['start'] ?? '', $ev['end'] ?? '' );

		// Zoom time string.
		$time_str = '';
		if ( $is_zoom ) {
			if ( ! empty( $ev['zoom_time'] ) ) {
				$time_str = $ev['zoom_time'];
			} else {
				$haystack = strtolower( ( $ev['location'] ?? '' ) . ' ' . ( $ev['cvent_title'] ?? '' ) );
				if ( preg_match( '/\b(est|east|eastern)\b/', $haystack ) ) {
					$time_str = $zoom_east;
				} elseif ( preg_match( '/\b(pst|west|western|pacific)\b/', $haystack ) ) {
					$time_str = $zoom_west;
				} else {
					$time_str = $zoom_default;
				}
			}
		}

		$web_url = esc_url( $ev['web_url'] ?? '' );

		$out  = '<div class="hpl-event-title">';
		$out .= '<strong>' . esc_html( $title ) . '</strong>';
		$out .= '&nbsp;&nbsp;' . esc_html( $date_str );
		if ( $time_str ) {
			$out .= ' <span class="hpl-zoom-time">| ' . esc_html( $time_str ) . '</span>';
		}
		$out .= '</div>';

		$out .= '<div class="hpl-event-details-row">';
		$out .= 'Click for event ';
		if ( $web_url ) {
			$out .= '<a href="' . $web_url . '" class="hpl-details-link">details</a>';
		} else {
			$out .= '<span class="hpl-details-link hpl-details-pending">details</span>';
		}
		if ( $is_zoom ) {
			$out .= ' <span class="hpl-zoom-badge">Zoom</span>';
		}
		$out .= '</div>';

		return $out;
	}

	// -------------------------------------------------------------------------
	// Helpers
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

	private function safe_tag( string $tag ): string {
		return in_array( $tag, array( 'h2', 'h3', 'h4', 'h5', 'p' ), true ) ? $tag : 'h2';
	}
}
