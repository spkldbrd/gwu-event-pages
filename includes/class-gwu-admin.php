<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the "Event Pages" admin menu on the primary domain (grantwritingusa.com).
 *
 * Provides two tabs:
 *   Settings — API URL, cache TTL, default page status, test connection, shortcode reference.
 *   Pages    — Table of all auto-generated event marketing pages.
 */
class GWU_Admin {

	const MENU_SLUG    = 'gwu-event-pages';
	const CAP_REQUIRED = 'manage_options';

	// WP option keys for settings.
	const OPT_HMO_API    = 'gwu_ep_hmo_api';
	const OPT_CACHE_TTL  = 'gwu_ep_cache_ttl';
	const OPT_DEF_STATUS = 'gwu_ep_default_status';

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	public function register(): void {
		add_action( 'admin_menu',            array( $this, 'add_menu' ) );
		add_action( 'wp_ajax_gwu_ep_test_api',    array( $this, 'ajax_test_api' ) );
		add_action( 'wp_ajax_gwu_ep_clear_cache', array( $this, 'ajax_clear_cache' ) );
	}

	public function add_menu(): void {
		add_menu_page(
			'GWU Event Pages',
			'Event Pages',
			self::CAP_REQUIRED,
			self::MENU_SLUG,
			array( $this, 'render_page' ),
			'dashicons-calendar-alt',
			58
		);
	}

	// -------------------------------------------------------------------------
	// Page render
	// -------------------------------------------------------------------------

	public function render_page(): void {
		if ( ! current_user_can( self::CAP_REQUIRED ) ) {
			wp_die( 'Unauthorized' );
		}

		$notice     = '';
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';

		// ---- Handle settings save ----
		if ( isset( $_POST['gwu_ep_save_settings'] ) ) {
			check_admin_referer( 'gwu_ep_settings' );

			$api_url = esc_url_raw( trim( $_POST['gwu_ep_hmo_api'] ?? '' ) );
			if ( ! $api_url ) {
				$api_url = GWU_EP_HMO_API;
			}

			$cache_ttl = max( 1, (int) ( $_POST['gwu_ep_cache_ttl'] ?? 15 ) );
			$status    = in_array( $_POST['gwu_ep_default_status'] ?? '', array( 'publish', 'draft' ), true )
				? sanitize_key( $_POST['gwu_ep_default_status'] )
				: 'publish';

			update_option( self::OPT_HMO_API,    $api_url,   false );
			update_option( self::OPT_CACHE_TTL,  $cache_ttl, false );
			update_option( self::OPT_DEF_STATUS, $status,    false );

			// Bust the transient cache so next page load fetches fresh data.
			delete_transient( 'gwu_ep_public_events' );

			$notice = '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
		}

		$tabs = array(
			'settings' => 'Settings',
			'pages'    => 'Event Pages',
		);

		?>
		<div class="wrap gwu-ep-wrap">
			<h1>GWU Event Pages</h1>
			<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<nav class="nav-tab-wrapper" style="margin-bottom:20px;">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=' . $slug ) ); ?>"
					   class="nav-tab <?php echo $active_tab === $slug ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php if ( $active_tab === 'settings' ) : ?>
				<?php $this->render_settings_tab(); ?>
			<?php elseif ( $active_tab === 'pages' ) : ?>
				<?php $this->render_pages_tab(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Settings tab
	// -------------------------------------------------------------------------

	private function render_settings_tab(): void {
		$api_url    = self::get_hmo_api();
		$cache_ttl  = (int) get_option( self::OPT_CACHE_TTL,  15 );
		$def_status = get_option( self::OPT_DEF_STATUS, 'publish' );
		$api_nonce   = wp_create_nonce( 'gwu_ep_test_api' );
		$cache_nonce = wp_create_nonce( 'gwu_ep_clear_cache' );
		$const_api  = defined( 'GWU_EP_HMO_API' ) ? GWU_EP_HMO_API : '';
		?>

		<form method="post" action="">
			<?php wp_nonce_field( 'gwu_ep_settings' ); ?>
			<input type="hidden" name="gwu_ep_save_settings" value="1">

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="gwu_ep_hmo_api">Subdomain API URL</label>
					</th>
					<td>
						<input
							type="url"
							id="gwu_ep_hmo_api"
							name="gwu_ep_hmo_api"
							value="<?php echo esc_attr( $api_url ); ?>"
							class="regular-text"
						>
						<p class="description">
							REST API base for the Hostlinks subdomain.
							<?php if ( $const_api ) : ?>
								Constant <code>GWU_EP_HMO_API</code> is defined in <code>wp-config.php</code>
								(<code><?php echo esc_html( $const_api ); ?></code>) and overrides this field.
							<?php else : ?>
								Default: <code><?php echo esc_html( GWU_EP_HMO_API ); ?></code>
							<?php endif; ?>
						</p>
						<p>
							<button type="button" id="gwu-ep-test-api" class="button"
								data-nonce="<?php echo esc_attr( $api_nonce ); ?>">
								Test Connection
							</button>
							<span id="gwu-ep-test-status" style="margin-left:10px;font-style:italic;"></span>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="gwu_ep_cache_ttl">Event List Cache (minutes)</label>
					</th>
					<td>
						<input type="number" id="gwu_ep_cache_ttl" name="gwu_ep_cache_ttl"
							value="<?php echo (int) $cache_ttl; ?>" min="1" max="1440" style="width:80px;">
						<p class="description">
							How long the <code>[public_event_list]</code> shortcode caches the API response.
							Use <code>[public_event_list cache="0"]</code> on any page to force-refresh on that load.
						</p>
						<p>
							<button type="button" id="gwu-ep-clear-cache" class="button"
								data-nonce="<?php echo esc_attr( $cache_nonce ); ?>">
								Clear Event Cache
							</button>
							<span id="gwu-ep-cache-status" style="margin-left:10px;font-style:italic;"></span>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="gwu_ep_default_status">Default Page Status</label>
					</th>
					<td>
						<select id="gwu_ep_default_status" name="gwu_ep_default_status">
							<option value="publish" <?php selected( $def_status, 'publish' ); ?>>Published</option>
							<option value="draft"   <?php selected( $def_status, 'draft' );   ?>>Draft</option>
						</select>
						<p class="description">
							Status applied to new event marketing pages created via the Hostlinks subdomain.
							<em>Note:</em> This setting is informational here; the actual value must be set
							via <code>GWU_PAGE_STATUS</code> constant or the subdomain's Marketing Ops settings.
						</p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary">Save Settings</button>
			</p>
		</form>

		<hr>

		<h2>Shortcode Reference</h2>
		<table class="widefat striped" style="max-width:680px;">
			<thead>
				<tr><th>Shortcode</th><th>Description</th></tr>
			</thead>
			<tbody>
				<tr>
					<td><code>[public_event_list]</code></td>
					<td>Displays the upcoming event calendar fetched from the Hostlinks subdomain. Add to any page or post.</td>
				</tr>
				<tr>
					<td><code>[public_event_list cache="0"]</code></td>
					<td>Same as above but bypasses the transient cache for this page load only.</td>
				</tr>
			</tbody>
		</table>

		<h2 style="margin-top:28px;">Custom Page Template</h2>
		<p>
			Auto-generated event marketing pages use the <strong>Event Marketing Page</strong> template
			(registered by this plugin). To change the page layout:
		</p>
		<ol>
			<li>
				Copy <code>gwu-event-pages/templates/page-event-marketing.php</code> to your theme as
				<code>page-event-marketing.php</code> and edit it there — theme copies take priority.
			</li>
			<li>
				Or edit the plugin template directly at:<br>
				<code><?php echo esc_html( GWU_EP_PLUGIN_DIR . 'templates/page-event-marketing.php' ); ?></code>
			</li>
		</ol>
		<p>
			<strong>Page content</strong> (the boilerplate text for each section) is edited on the
			<strong>Hostlinks subdomain</strong> under
			<em>Marketing Ops → Settings → Page Template</em>.
		</p>

		<script>
		jQuery(function($){
			$('#gwu-ep-clear-cache').on('click', function(){
				var btn    = $(this);
				var status = $('#gwu-ep-cache-status');
				btn.prop('disabled', true).text('Clearing…');
				status.css('color','').text('');

				$.post(ajaxurl, {
					action      : 'gwu_ep_clear_cache',
					_ajax_nonce : btn.data('nonce')
				}, function(resp){
					btn.prop('disabled', false).text('Clear Event Cache');
					if ( resp.success ) {
						status.css('color','green').text(resp.data.message);
					} else {
						status.css('color','red').text('Error: ' + (resp.data || 'Unknown'));
					}
				}).fail(function(){
					btn.prop('disabled', false).text('Clear Event Cache');
					status.css('color','red').text('Request failed.');
				});
			});

			$('#gwu-ep-test-api').on('click', function(){
				var btn    = $(this);
				var status = $('#gwu-ep-test-status');
				btn.prop('disabled', true).text('Testing…');
				status.css('color','').text('');

				$.post(ajaxurl, {
					action      : 'gwu_ep_test_api',
					_ajax_nonce : btn.data('nonce')
				}, function(resp){
					btn.prop('disabled', false).text('Test Connection');
					if ( resp.success ) {
						status.css('color','green').text(resp.data.message);
					} else {
						status.css('color','red').text('Error: ' + (resp.data || 'Unknown'));
					}
				}).fail(function(){
					btn.prop('disabled', false).text('Test Connection');
					status.css('color','red').text('Request failed.');
				});
			});
		});
		</script>
		<?php
	}

	// -------------------------------------------------------------------------
	// Pages tab
	// -------------------------------------------------------------------------

	private function render_pages_tab(): void {
		$query = new WP_Query( array(
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft', 'pending' ),
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => '_gwu_event_id',
					'compare' => 'EXISTS',
				),
			),
			'orderby' => 'date',
			'order'   => 'DESC',
		) );

		$pages = $query->posts;
		?>

		<h2 style="margin-top:0;">
			Auto-Generated Event Marketing Pages
			<span style="font-weight:400;font-size:14px;color:#666;margin-left:8px;">
				(<?php echo count( $pages ); ?> page<?php echo count( $pages ) !== 1 ? 's' : ''; ?>)
			</span>
		</h2>

		<?php if ( empty( $pages ) ) : ?>
			<p>No event marketing pages have been created yet. They are auto-generated by the Hostlinks subdomain when a new event is saved.</p>
		<?php else : ?>
		<table class="widefat striped gwu-ep-pages-table">
			<thead>
				<tr>
					<th>Page Title</th>
					<th style="width:80px;">Event ID</th>
					<th style="width:90px;">Status</th>
					<th style="width:140px;">Created</th>
					<th style="width:180px;">Actions</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $pages as $page ) :
				$event_id = (int) get_post_meta( $page->ID, '_gwu_event_id', true );
				$status   = get_post_status( $page );
				$status_labels = array(
					'publish' => '<span style="color:green;">Published</span>',
					'draft'   => '<span style="color:#b46;">Draft</span>',
					'pending' => '<span style="color:#f90;">Pending</span>',
				);
				$status_html = isset( $status_labels[ $status ] )
					? $status_labels[ $status ]
					: '<span>' . esc_html( ucfirst( $status ) ) . '</span>';
			?>
			<tr>
				<td>
					<strong><?php echo esc_html( get_the_title( $page ) ); ?></strong>
					<?php if ( $status === 'publish' ) : ?>
						<br><small style="color:#666;"><?php echo esc_html( get_permalink( $page ) ); ?></small>
					<?php endif; ?>
				</td>
				<td><?php echo $event_id > 0 ? (int) $event_id : '—'; ?></td>
				<td><?php echo $status_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				<td><?php echo esc_html( date_i18n( 'm/d/Y', strtotime( $page->post_date ) ) ); ?></td>
				<td>
					<a href="<?php echo esc_url( get_edit_post_link( $page->ID ) ); ?>" class="button button-small">Edit</a>
					<?php if ( $status === 'publish' ) : ?>
						&nbsp;<a href="<?php echo esc_url( get_permalink( $page->ID ) ); ?>" class="button button-small" target="_blank">View</a>
					<?php endif; ?>
				</td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<p style="color:#666;font-size:13px;margin-top:8px;">
			To update the <em>content</em> of existing pages, use the Regenerate button on each event's detail page
			in Marketing Ops on the subdomain, or use <em>Settings → Page Template → Regenerate All Future Event Pages</em>.
		</p>
		<?php endif; ?>

		<style>
		.gwu-ep-pages-table { max-width: 1200px; }
		.gwu-ep-pages-table td { vertical-align: middle; }
		</style>
		<?php
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Returns the active HMO API URL: constant overrides option, option overrides default.
	 */
	public static function get_hmo_api(): string {
		if ( defined( 'GWU_EP_HMO_API' ) ) {
			return GWU_EP_HMO_API;
		}
		return get_option( self::OPT_HMO_API, 'https://hostlinks.grantwritingusa.com/wp-json/hmo/v1' );
	}

	// -------------------------------------------------------------------------
	// AJAX: test API connection
	// -------------------------------------------------------------------------

	public function ajax_test_api(): void {
		check_ajax_referer( 'gwu_ep_test_api' );

		if ( ! current_user_can( self::CAP_REQUIRED ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		$api_url = self::get_hmo_api();

		$response = wp_remote_get( rtrim( $api_url, '/' ) . '/public-events', array(
			'timeout' => 8,
		) );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code === 200 && isset( $data['events'] ) ) {
			wp_send_json_success( array(
				'message' => 'Connected! Found ' . count( $data['events'] ) . ' upcoming event(s).',
			) );
		} else {
			wp_send_json_error( 'HTTP ' . $code . '. Unexpected response from subdomain API.' );
		}
	}

	// -------------------------------------------------------------------------
	// AJAX: clear event list transient cache
	// -------------------------------------------------------------------------

	public function ajax_clear_cache(): void {
		check_ajax_referer( 'gwu_ep_clear_cache' );

		if ( ! current_user_can( self::CAP_REQUIRED ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		$deleted = 0;

		// Upcoming events transient.
		if ( delete_transient( 'gwu_ep_public_events' ) ) {
			$deleted++;
		}
		// Also clear the stale-backup transient used during API outages.
		delete_transient( 'gwu_ep_public_events_stale' );

		// Past events transients (keyed by years 1–10).
		for ( $y = 1; $y <= 10; $y++ ) {
			if ( delete_transient( 'gwu_ep_past_events_' . $y ) ) {
				$deleted++;
			}
		}

		wp_send_json_success( array(
			'message' => $deleted > 0
				? 'Cache cleared (' . $deleted . ' transient(s) deleted). Next page load will fetch fresh data.'
				: 'Cache was already empty — no transients found.',
		) );
	}
}
