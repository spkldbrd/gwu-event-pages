<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcodes for DIVI (or any) event marketing page layouts.
 */
class GWU_Event_Shortcodes {

	public function register(): void {
		add_shortcode( 'event_register_button', array( $this, 'register_button' ) );
		add_shortcode( 'event_section', array( $this, 'event_section' ) );
		add_shortcode( 'event_hotels', array( $this, 'event_hotels' ) );
		add_shortcode( 'event_special_instructions', array( $this, 'event_special_instructions' ) );
	}

	/**
	 * [event_register_button label="Click here to register!"]
	 */
	public function register_button( $atts ): string {
		$atts = shortcode_atts(
			array(
				'label' => 'Click here to register!',
				'class' => '',
			),
			$atts,
			'event_register_button'
		);

		$url = GWU_Event_Data::get_reg_url();
		if ( $url === '' ) {
			return '';
		}

		$classes = trim( 'gwu-reg-btn ' . $atts['class'] );
		return sprintf(
			'<p class="gwu-reg-btn-wrap" style="margin:12px 0;"><a href="%s" class="%s">%s</a></p>',
			esc_url( $url ),
			esc_attr( $classes ),
			esc_html( $atts['label'] )
		);
	}

	/**
	 * [event_section section="welcome" show_heading="1"]
	 */
	public function event_section( $atts ): string {
		$atts = shortcode_atts(
			array(
				'section'      => '',
				'show_heading' => '1',
			),
			$atts,
			'event_section'
		);

		$section = sanitize_key( $atts['section'] );
		if ( $section === '' || ! isset( GWU_Page_Template::get_sections()[ $section ] ) ) {
			return '';
		}

		$ev = GWU_Event_Data::get_for_page();
		if ( ! $ev ) {
			return '';
		}

		$context = GWU_Page_Template::context_from_event( $ev );
		$tokens  = GWU_Event_Data::build_tokens( $ev );
		$html    = GWU_Page_Template::render_section_html(
			$context,
			$section,
			$tokens,
			filter_var( $atts['show_heading'], FILTER_VALIDATE_BOOLEAN )
		);

		return $html;
	}

	/**
	 * [event_hotels] — lodging block when the event has hotel HTML.
	 */
	public function event_hotels( $atts ): string {
		$ev = GWU_Event_Data::get_for_page();
		if ( ! $ev ) {
			return '';
		}

		$hotels = trim( (string) ( $ev['hotels'] ?? '' ) );
		if ( $hotels === '' ) {
			return '';
		}

		$html  = '<h2>Traveling and need lodging?</h2>' . "\n";
		$html .= '<p>These hotels are near the training location.</p>' . "\n";
		$html .= wp_kses_post( $hotels );

		return $html;
	}

	/**
	 * [event_special_instructions]
	 */
	public function event_special_instructions( $atts ): string {
		$ev = GWU_Event_Data::get_for_page();
		if ( ! $ev ) {
			return '';
		}

		$special = trim( (string) ( $ev['special_instructions'] ?? '' ) );
		if ( $special === '' ) {
			return '';
		}

		return '<p>' . wp_kses_post( $special ) . '</p>';
	}
}
