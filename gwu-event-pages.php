<?php
/**
 * Plugin Name: GWU Event Pages
 * Plugin URI:  https://github.com/spkldbrd/gwu-event-pages
 * Description: Renders the public event list shortcode (fed from Hostlinks via REST) and provides the Event Marketing Page template used by auto-generated event pages.
 * Version:     1.1.2
 * Author:      Digital Solution
 * Author URI:  https://digitalsolution.com
 * License:     GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GWU_EP_VERSION',    '1.1.2' );
define( 'GWU_EP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GWU_EP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GWU_EP_PLUGIN_FILE', __FILE__ );

// Allow the subdomain API URL to be overridden in wp-config.php.
if ( ! defined( 'GWU_EP_HMO_API' ) ) {
	define( 'GWU_EP_HMO_API', 'https://hostlinks.grantwritingusa.com/wp-json/hmo/v1' );
}

require_once GWU_EP_PLUGIN_DIR . 'includes/class-gwu-assets.php';
require_once GWU_EP_PLUGIN_DIR . 'includes/class-gwu-shortcode.php';
require_once GWU_EP_PLUGIN_DIR . 'includes/class-gwu-past-shortcode.php';
require_once GWU_EP_PLUGIN_DIR . 'includes/class-gwu-admin.php';
require_once GWU_EP_PLUGIN_DIR . 'includes/class-gwu-updater.php';

GWU_Updater::init( __FILE__, 'spkldbrd', 'gwu-event-pages' );

add_action( 'plugins_loaded', function() {
	// Register shortcodes.
	$shortcode      = new GWU_Shortcode();
	$past_shortcode = new GWU_Past_Shortcode();
	add_action( 'init', array( $shortcode,      'register' ) );
	add_action( 'init', array( $past_shortcode, 'register' ) );

	// Enqueue front-end assets.
	$assets = new GWU_Assets();
	add_action( 'wp_enqueue_scripts', array( $assets, 'enqueue' ) );

	// Register the Event Marketing Page template so WordPress knows it exists.
	add_filter( 'theme_page_templates', array( 'GWU_Assets', 'register_page_template' ) );
	add_filter( 'template_include',     array( 'GWU_Assets', 'load_page_template' ) );

	// Register custom post meta so the REST API can set _gwu_event_id on pages.
	register_post_meta( 'page', '_gwu_event_id', array(
		'show_in_rest'      => true,
		'single'            => true,
		'type'              => 'integer',
		'auth_callback'     => function() { return current_user_can( 'edit_posts' ); },
		'sanitize_callback' => 'absint',
	) );

	// Admin menu (loaded only in WP admin).
	if ( is_admin() ) {
		$admin = new GWU_Admin();
		$admin->register();
	}
} );
