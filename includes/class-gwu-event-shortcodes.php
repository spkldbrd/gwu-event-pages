<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [event_register_button] — registration CTA for the event linked to this page.
 */
class GWU_Event_Shortcodes {

	public function register(): void {
		add_shortcode( 'event_register_button', array( $this, 'register_button' ) );
		add_shortcode( 'event_course_type', array( $this, 'course_type' ) );
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
			'<p class="gwu-reg-btn-wrap" style="margin:12px 0;"><a href="%s" class="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
			esc_url( $url ),
			esc_attr( $classes ),
			esc_html( $atts['label'] )
		);
	}

	/**
	 * [event_course_type] — Course Type block for the sidebar (from page meta at last regenerate).
	 */
	public function course_type( $atts ): string {
		$html = GWU_Event_Data::get_course_type_html();
		if ( $html === '' ) {
			return '';
		}

		return '<div class="gwu-course-type">' . wp_kses_post( $html ) . '</div>';
	}
}
