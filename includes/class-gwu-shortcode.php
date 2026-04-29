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
 *   [public_event_list enable_map="1"]
 *   [public_event_list left_heading="Writing Workshops" right_heading="Management Workshops"]
 */
class GWU_Shortcode {

	const TRANSIENT_KEY = 'gwu_ep_public_events';

	public function register(): void {
		add_shortcode( 'public_event_list', array( $this, 'render' ) );
	}

	public function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'cache'      => '1',
				'enable_map' => '0',
			),
			$atts,
			'public_event_list'
		);

		$bust_cache  = ( $atts['cache'] === '0' );
		$enable_map  = in_array( strtolower( (string) $atts['enable_map'] ), array( '1', 'true', 'yes' ), true );

		$payload    = $bust_cache ? false : get_transient( self::TRANSIENT_KEY );
		$from_fresh = false;

		if ( $payload === false ) {
			$fetched = $this->fetch_events();
			if ( $fetched !== null ) {
				$payload    = $fetched;
				$from_fresh = true;
				$ttl        = max( 60, (int) get_option( GWU_Admin::OPT_CACHE_TTL, 15 ) * 60 );
				set_transient( self::TRANSIENT_KEY, $payload, $ttl );
			}
		}

		if ( empty( $payload['events'] ) ) {
			$stale = get_transient( self::TRANSIENT_KEY . '_stale' );
			if ( $stale ) {
				$payload = $stale;
			} else {
				return '<p class="hpl-no-events">Upcoming events will be listed here shortly. Please check back soon.</p>';
			}
		}

		if ( $from_fresh && ! empty( $payload['events'] ) ) {
			set_transient( self::TRANSIENT_KEY . '_stale', $payload, DAY_IN_SECONDS );
		}

		return $this->render_list( $payload, $enable_map );
	}

	// -------------------------------------------------------------------------
	// HTTP fetch
	// -------------------------------------------------------------------------

	private function fetch_events(): ?array {
		$url      = rtrim( GWU_Admin::get_hmo_api(), '/' ) . '/public-events';
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

	private function render_list( array $payload, bool $enable_map ): string {
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
			$column = $ev['column'] ?? '';
			if ( $column === 'left' ) {
				$left_events[] = $ev;
			} elseif ( $column === 'right' ) {
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
		$list_html = ob_get_clean();

		if ( ! $enable_map ) {
			return $list_html;
		}

		return $this->wrap_list_with_map( $list_html, $payload, $zoom_east, $zoom_west, $zoom_default );
	}

	/**
	 * @param string               $list_html Inner two-column markup.
	 * @param array<string, mixed> $payload   Full API payload (events + meta).
	 */
	private function wrap_list_with_map(
		string $list_html,
		array $payload,
		string $zoom_east,
		string $zoom_west,
		string $zoom_default
	): string {
		$uid       = wp_unique_id( 'gwu-hpl-' );
		$list_id   = $uid . '-list';
		$map_id    = $uid . '-map';
		$label_map = (string) get_option( GWU_Admin::OPT_MAP_LABEL_MAP, 'View map' );
		$label_list = (string) get_option( GWU_Admin::OPT_MAP_LABEL_LIST, 'View list' );
		$map_height = GWU_Admin::sanitize_map_height( (string) get_option( GWU_Admin::OPT_MAP_HEIGHT, '620px' ) );

		$markers = $this->build_map_markers( $payload['events'] ?? array(), $zoom_east, $zoom_west, $zoom_default );
		$this->enqueue_map_assets();

		$markers_json = wp_json_encode(
			$markers,
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
		);
		if ( false === $markers_json ) {
			$markers_json = '[]';
		}

		$h3_text = trim( (string) get_option( GWU_Admin::OPT_MAP_INTRO_H3, GWU_Admin::default_map_intro_h3() ) );
		if ( $h3_text === '' ) {
			$h3_text = GWU_Admin::default_map_intro_h3();
		}
		$p_text = trim( (string) get_option( GWU_Admin::OPT_MAP_INTRO_P, GWU_Admin::default_map_intro_p() ) );
		if ( $p_text === '' ) {
			$p_text = GWU_Admin::default_map_intro_p();
		}

		ob_start();
		?>
		<div class="gwu-hpl-view" data-gwu-hpl-view="list" id="<?php echo esc_attr( $uid ); ?>" data-gwu-markers="<?php echo esc_attr( $markers_json ); ?>">
			<header class="gwu-hpl-intro">
				<div class="gwu-hpl-intro__text">
					<h3 class="gwu-hpl-intro__title"><strong><?php echo esc_html( $h3_text ); ?></strong></h3>
					<p class="gwu-hpl-intro__lede"><strong><em><?php echo esc_html( $p_text ); ?></em></strong></p>
				</div>
				<div class="gwu-hpl-intro__actions" role="toolbar" aria-label="<?php echo esc_attr( 'List and map display' ); ?>">
					<button type="button" class="gwu-hpl-btn gwu-hpl-btn--map" data-gwu-hpl-show="map" aria-controls="<?php echo esc_attr( $map_id ); ?>" aria-pressed="false">
						<span class="gwu-hpl-btn__icon dashicons dashicons-location-alt" aria-hidden="true"></span>
						<span class="gwu-hpl-btn__text"><?php echo esc_html( $label_map ); ?></span>
					</button>
					<button type="button" class="gwu-hpl-btn gwu-hpl-btn--list is-active" data-gwu-hpl-show="list" aria-controls="<?php echo esc_attr( $list_id ); ?>" aria-pressed="true">
						<span class="gwu-hpl-btn__icon dashicons dashicons-list-view" aria-hidden="true"></span>
						<span class="gwu-hpl-btn__text"><?php echo esc_html( $label_list ); ?></span>
					</button>
				</div>
			</header>
			<div id="<?php echo esc_attr( $list_id ); ?>" class="gwu-hpl-pane gwu-hpl-pane--list" role="region" aria-label="<?php echo esc_attr( 'Event list' ); ?>">
				<?php echo $list_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<div id="<?php echo esc_attr( $map_id ); ?>" class="gwu-hpl-pane gwu-hpl-pane--map" role="region" aria-label="<?php echo esc_attr( 'United States map of events' ); ?>" hidden style="<?php echo esc_attr( 'height:' . $map_height . ';min-height:480px;' ); ?>">
				<div class="gwu-hpl-map-shell">
					<div class="gwu-hpl-map-filters">
						<span class="gwu-hpl-map-filters__prefix"><?php echo esc_html( 'Filter:' ); ?></span>
						<label class="gwu-hpl-map-filters__opt">
							<input type="checkbox" class="gwu-hpl-filter-col" value="left" checked>
							<?php echo esc_html( 'Grant Writing' ); ?>
						</label>
						<label class="gwu-hpl-map-filters__opt">
							<input type="checkbox" class="gwu-hpl-filter-col" value="right" checked>
							<?php echo esc_html( 'Grant Management' ); ?>
						</label>
						<label class="gwu-hpl-map-filters__opt">
							<input type="checkbox" class="gwu-hpl-filter-subaward" checked>
							<?php echo esc_html( 'Managing Subawards' ); ?>
						</label>
						<em class="gwu-hpl-map-filters__note"><?php echo esc_html( "*In-Person events only. Switch to 'list' view to see Zoom events." ); ?></em>
					</div>
					<div class="gwu-hpl-map-canvas" role="presentation"></div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * @param array<int, array<string, mixed>> $events Raw events from API.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_map_markers( array $events, string $zoom_east, string $zoom_west, string $zoom_default ): array {
		$out = array();
		foreach ( $events as $ev ) {
			if ( ! is_array( $ev ) ) {
				continue;
			}
			$pin = GWU_Map_Coords::pin_for_event( $ev );
			if ( null === $pin ) {
				continue;
			}
			$title = $this->get_event_title_text( $ev, $zoom_east, $zoom_west, $zoom_default );
			$date  = $this->format_date_range( $ev['start'] ?? '', $ev['end'] ?? '' );
			$time  = $this->get_event_time_text( $ev, $zoom_east, $zoom_west, $zoom_default );
			$url   = isset( $ev['web_url'] ) ? esc_url_raw( (string) $ev['web_url'] ) : '';

			$type_name = strtolower( trim( (string) ( $ev['type_name'] ?? '' ) ) );

			$out[] = array(
				'id'        => (int) ( $ev['id'] ?? 0 ),
				'lat'       => $pin['lat'],
				'lng'       => $pin['lng'],
				'title'     => $title,
				'date'      => $date,
				'time'      => $time,
				'url'       => $url,
				'column'    => (string) ( $ev['column'] ?? '' ),
				'type_name' => $type_name,
			);
		}
		return $out;
	}

	/**
	 * @param array<int, array<string, mixed>> $markers
	 */
	private function enqueue_map_assets(): void {
		wp_enqueue_style( 'dashicons' );

		wp_enqueue_style(
			'leaflet',
			'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
			array(),
			'1.9.4'
		);
		wp_enqueue_script(
			'leaflet',
			'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
			array(),
			'1.9.4',
			true
		);

		wp_enqueue_style(
			'gwu-event-list-map',
			GWU_EP_PLUGIN_URL . 'assets/css/event-list-map.css',
			array( 'leaflet', 'dashicons' ),
			GWU_EP_VERSION
		);
		wp_enqueue_script(
			'gwu-event-list-map',
			GWU_EP_PLUGIN_URL . 'assets/js/event-list-map.js',
			array( 'leaflet' ),
			GWU_EP_VERSION,
			true
		);

		wp_localize_script(
			'gwu-event-list-map',
			'gwuEpMapDefaults',
			array(
				'geoJsonUrl' => GWU_EP_PLUGIN_URL . 'assets/data/us-states.geojson',
			)
		);
	}

	private function render_event( array $ev, string $zoom_east, string $zoom_west, string $zoom_default ): string {
		$title    = $this->get_event_title_text( $ev, $zoom_east, $zoom_west, $zoom_default );
		$date_str = $this->format_date_range( $ev['start'] ?? '', $ev['end'] ?? '' );
		$time_str = $this->get_event_time_text( $ev, $zoom_east, $zoom_west, $zoom_default );

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
			$out .= '<a href="' . $web_url . '" class="hpl-details-link" target="_blank" rel="noopener noreferrer">details</a>';
		} else {
			$out .= '<span class="hpl-details-link hpl-details-pending">details</span>';
		}
		if ( ( $ev['zoom'] ?? '' ) === 'yes' ) {
			$out .= ' <span class="hpl-zoom-badge">Zoom</span>';
		}
		$out .= '</div>';

		return $out;
	}

	/**
	 * @param array<string, mixed> $ev Event row.
	 */
	private function get_event_title_text( array $ev, string $zoom_east, string $zoom_west, string $zoom_default ): string {
		$is_zoom = ( ( $ev['zoom'] ?? '' ) === 'yes' );

		if ( $is_zoom ) {
			$title = 'ZOOM WEBINAR';
		} else {
			$title = $this->extract_city_state( $ev['location'] ?? '' );
			if ( ! empty( $ev['city'] ) && ! empty( $ev['state'] ) ) {
				$title = $ev['city'] . ', ' . $ev['state'];
			}
		}

		if ( strtolower( $ev['type_name'] ?? '' ) === 'subaward' ) {
			$title = 'Managing Subawards ' . $title;
		}

		return $title;
	}

	/**
	 * @param array<string, mixed> $ev Event row.
	 */
	private function get_event_time_text( array $ev, string $zoom_east, string $zoom_west, string $zoom_default ): string {
		if ( ( $ev['zoom'] ?? '' ) !== 'yes' ) {
			return '';
		}
		if ( ! empty( $ev['zoom_time'] ) ) {
			return (string) $ev['zoom_time'];
		}
		$haystack = strtolower( ( $ev['location'] ?? '' ) . ' ' . ( $ev['cvent_title'] ?? '' ) );
		if ( preg_match( '/\b(est|east|eastern)\b/', $haystack ) ) {
			return $zoom_east;
		}
		if ( preg_match( '/\b(pst|west|western|pacific)\b/', $haystack ) ) {
			return $zoom_west;
		}
		return $zoom_default;
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
