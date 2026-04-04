<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GWU_Assets {

	const TEMPLATE_KEY = 'gwu-event-pages/templates/page-event-marketing.php';

	public function enqueue(): void {
		// Only load on pages that use the public event list or the marketing template.
		if ( is_singular( 'page' ) && get_post_meta( get_the_ID(), '_wp_page_template', true ) === self::TEMPLATE_KEY ) {
			wp_enqueue_style(
				'gwu-event-pages',
				GWU_EP_PLUGIN_URL . 'assets/css/frontend.css',
				array(),
				GWU_EP_VERSION
			);
			return;
		}

		// Always enqueue if the shortcode is present (WordPress doesn't know until render-time,
		// so we enqueue on all front-end pages and let it be unused when not needed).
		wp_enqueue_style(
			'gwu-event-pages',
			GWU_EP_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			GWU_EP_VERSION
		);
	}

	/**
	 * Register the Event Marketing Page template so it appears in the WP admin
	 * page template dropdown and can be set via the REST API.
	 */
	public static function register_page_template( array $templates ): array {
		$templates[ self::TEMPLATE_KEY ] = 'Event Marketing Page';
		return $templates;
	}

	/**
	 * Redirect WordPress to the plugin's template file when a page uses
	 * the Event Marketing Page template.
	 */
	public static function load_page_template( string $template ): string {
		if ( ! is_singular( 'page' ) ) {
			return $template;
		}
		$set = get_post_meta( get_the_ID(), '_wp_page_template', true );
		if ( $set !== self::TEMPLATE_KEY ) {
			return $template;
		}
		$plugin_template = GWU_EP_PLUGIN_DIR . 'templates/page-event-marketing.php';
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}
		return $template;
	}
}
