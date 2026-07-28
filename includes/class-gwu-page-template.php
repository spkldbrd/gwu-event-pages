<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Editable boilerplate for event marketing pages on grantwritingusa.com.
 *
 * Six contexts: grant writing / management / subaward × in-person / Zoom.
 * Stored as WP options on this site (not Hostlinks). Use with [event_section] shortcodes in DIVI.
 */
class GWU_Page_Template {

	const OPT_PREFIX = 'gwu_ep_pt_';

	/**
	 * @return array<string, string>
	 */
	public static function get_contexts(): array {
		return array(
			'writing_inperson'    => 'Grant Writing — In-Person',
			'writing_zoom'        => 'Grant Writing — Zoom',
			'management_inperson' => 'Grant Management — In-Person',
			'management_zoom'     => 'Grant Management — Zoom',
			'subaward_inperson'   => 'Subaward — In-Person',
			'subaward_zoom'       => 'Subaward — Zoom',
		);
	}

	/**
	 * Resolve template context from an event snapshot row.
	 *
	 * @param array<string, mixed> $ev
	 */
	public static function context_from_event( array $ev ): string {
		$type_map = array(
			1 => 'writing',
			2 => 'management',
			3 => 'subaward',
		);
		$type = $type_map[ (int) ( $ev['eve_type'] ?? 0 ) ] ?? 'writing';
		$mode = ( ( $ev['eve_zoom'] ?? '' ) === 'yes' ) ? 'zoom' : 'inperson';
		$key  = $type . '_' . $mode;
		$all  = self::get_contexts();
		return array_key_exists( $key, $all ) ? $key : 'writing_inperson';
	}

	/**
	 * @return array<string, array{label:string,description:string,default_heading:string,tokens:array<string,string>}>
	 */
	public static function get_sections(): array {
		return array(
			'welcome' => array(
				'label'           => 'Welcome',
				'description'     => 'Opening paragraph.',
				'default_heading' => 'Welcome!',
				'tokens'          => array(),
			),
			'itinerary' => array(
				'label'           => 'Itinerary & Location / Date & Time',
				'description'     => 'Schedule and venue (in-person) or date/time (Zoom).',
				'default_heading' => '',
				'tokens'          => array(
					'{{DATE_LONG}}'  => 'Formatted date range',
					'{{MAP_URL}}'    => 'Google Maps link (in-person)',
					'{{HOST_LINE}}'  => '"Hosted by …" line or empty',
					'{{ADDR_BLOCK}}' => 'Street / city address block or empty',
				),
			),
			'format' => array(
				'label'           => 'Course Type',
				'description'     => 'Grant writing vs. management, or Zoom format note.',
				'default_heading' => '',
				'tokens'          => array(),
			),
			'tuition' => array(
				'label'           => 'Tuition',
				'description'     => 'Tuition amount and inclusions.',
				'default_heading' => 'Tuition',
				'tokens'          => array(),
			),
			'covid' => array(
				'label'           => 'COVID Guidelines',
				'description'     => 'Health and safety notice (often off for Zoom).',
				'default_heading' => 'COVID Guidelines',
				'tokens'          => array(),
			),
			'ceu' => array(
				'label'           => 'CEU Credits',
				'description'     => 'Continuing education information.',
				'default_heading' => 'CEU Credits',
				'tokens'          => array(),
			),
			'payment' => array(
				'label'           => 'Payment Policy',
				'description'     => 'Credit card and check instructions.',
				'default_heading' => 'Payment Policy',
				'tokens'          => array(),
			),
			'purchase_orders' => array(
				'label'           => 'Purchase Orders',
				'description'     => 'PO instructions for government agencies.',
				'default_heading' => 'Purchase Orders',
				'tokens'          => array(),
			),
			'cancel' => array(
				'label'           => 'Cancel Policy',
				'description'     => 'Withdrawal and refund terms.',
				'default_heading' => 'Cancel Policy',
				'tokens'          => array(),
			),
			'questions' => array(
				'label'           => 'Questions / Contact',
				'description'     => 'Client services contact info.',
				'default_heading' => 'Questions?',
				'tokens'          => array(),
			),
			'ready_to_enroll' => array(
				'label'           => 'Ready to Enroll',
				'description'     => 'Closing line before a second register button (use [event_register_button] in DIVI).',
				'default_heading' => 'Ready to enroll?',
				'tokens'          => array(),
			),
		);
	}

	public static function is_section_enabled( string $context, string $section ): bool {
		$sections = self::get_sections();
		if ( ! isset( $sections[ $section ] ) ) {
			return false;
		}
		$val = get_option( self::option_key_enabled( $context, $section ), null );
		if ( $val === null ) {
			return self::default_section_enabled( $context, $section );
		}
		return (string) $val === '1';
	}

	public static function default_section_enabled( string $context, string $section ): bool {
		if ( $section === 'covid' && str_ends_with( $context, '_zoom' ) ) {
			return false;
		}
		return true;
	}

	public static function get_section_heading( string $context, string $section ): string {
		$sections = self::get_sections();
		if ( ! isset( $sections[ $section ] ) ) {
			return '';
		}
		$saved = get_option( self::option_key_heading( $context, $section ), null );
		if ( $saved !== null && $saved !== false ) {
			return (string) $saved;
		}
		return $sections[ $section ]['default_heading'];
	}

	public static function get_section_content( string $context, string $section ): string {
		$sections = self::get_sections();
		if ( ! isset( $sections[ $section ] ) ) {
			return '';
		}
		$saved = get_option( self::option_key_content( $context, $section ), null );
		if ( $saved !== null && $saved !== '' ) {
			return (string) $saved;
		}
		return self::get_default_content( $context, $section );
	}

	public static function render_section_html( string $context, string $section, array $tokens = array(), bool $show_heading = true ): string {
		if ( ! self::is_section_enabled( $context, $section ) ) {
			return '';
		}

		$content = self::get_section_content( $context, $section );
		if ( $content === '' ) {
			return '';
		}

		if ( ! empty( $tokens ) ) {
			$content = str_replace( array_keys( $tokens ), array_values( $tokens ), $content );
		}

		$html = '';
		if ( $show_heading ) {
			$heading = self::get_section_heading( $context, $section );
			if ( $heading !== '' ) {
				$html .= '<h2>' . esc_html( $heading ) . '</h2>' . "\n";
			}
		}

		$html .= $content;
		if ( $html !== '' && ! str_ends_with( trim( $html ), "\n" ) ) {
			$html .= "\n";
		}

		return $html;
	}

	public static function save_section( string $context, string $section, string $content, bool $enabled, string $heading ): void {
		$contexts = self::get_contexts();
		$sections = self::get_sections();
		if ( ! isset( $contexts[ $context ], $sections[ $section ] ) ) {
			return;
		}

		update_option( self::option_key_content( $context, $section ), wp_kses_post( $content ), false );
		update_option( self::option_key_enabled( $context, $section ), $enabled ? '1' : '0', false );
		update_option( self::option_key_heading( $context, $section ), sanitize_text_field( $heading ), false );
	}

	public static function reset_section( string $context, string $section ): void {
		delete_option( self::option_key_content( $context, $section ) );
		delete_option( self::option_key_enabled( $context, $section ) );
		delete_option( self::option_key_heading( $context, $section ) );
	}

	public static function option_key_content( string $context, string $section ): string {
		return self::OPT_PREFIX . sanitize_key( $context ) . '_' . sanitize_key( $section );
	}

	public static function option_key_enabled( string $context, string $section ): string {
		return self::option_key_content( $context, $section ) . '_en';
	}

	public static function option_key_heading( string $context, string $section ): string {
		return self::option_key_content( $context, $section ) . '_h';
	}

	public static function get_default_content( string $context, string $section ): string {
		$is_zoom = str_ends_with( $context, '_zoom' );
		$type    = explode( '_', $context )[0] ?? 'writing';

		$defaults = array(
			'welcome' =>
				'<p>If you\'re ready to learn how to find funding sources and write winning grant proposals, you\'ve come to the right place. Beginning and experienced grant writers from city, county and state agencies as well as healthcare organizations, nonprofits, K-12, colleges and universities are encouraged to attend. You <em>do not</em> need to work in the same profession as the host agency.</p>',

			'itinerary_inperson' =>
				'<p><strong>Itinerary and Location:</strong> This workshop is {{DATE_LONG}}, 9-4 both days with lunch on your own from noon to 1:20. View a <a href="{{MAP_URL}}" target="_blank">map of the workshop location</a> and review the <a href="https://www.grantwritingusa.com/grant-writing-course-content/">learning objectives</a> for this course.{{HOST_LINE}}{{ADDR_BLOCK}}</p>',

			'itinerary_zoom' =>
				'<p><strong>Date and Time:</strong> This webinar is {{DATE_LONG}}, 9:30&ndash;4:30 ET / 8:00&ndash;3:00 MT / 7:00&ndash;2:00 PT. A Zoom link will be emailed to all registered participants prior to the event. You do not need to download any software; participation requires only a computer, tablet, or smartphone with internet access.</p>',

			'format_writing_inperson' =>
				'<p>This is a:</p>' . "\n" .
				'<p>&radic;&nbsp;<strong>grant writing class</strong><br>' . "\n" .
				'&nbsp;&nbsp;&nbsp;grant management class<br>' . "\n" .
				'<em>what\'s the <a href="https://www.grantwritingusa.com/difference/">difference</a>?</em></p>',

			'format_management_inperson' =>
				'<p>This is a:</p>' . "\n" .
				'<p>&nbsp;&nbsp;&nbsp;grant writing class<br>' . "\n" .
				'&radic;&nbsp;<strong>grant management class</strong><br>' . "\n" .
				'<em>what\'s the <a href="https://www.grantwritingusa.com/difference/">difference</a>?</em></p>',

			'format_subaward_inperson' =>
				'<p>This is a <strong>Managing Subawards</strong> in-person workshop.</p>',

			'format_zoom' =>
				'<p>This is a <strong>Zoom webinar</strong>. Instruction is identical to our in-person workshops &mdash; just delivered online. Please have a reliable internet connection and a device with audio.</p>',

			'tuition' =>
				'<p>Tuition is $525 and includes everything: two days of terrific instruction, workbook, and lifetime access to our Alumni Resource Center that\'s packed full of helpful resources and sample grant proposals.</p>',

			'covid' =>
				'<p>Local health and safety guidelines will be followed. If online learning is more comfortable for you, please visit our <a href="https://www.grantwritingusa.com/events.html">complete calendar of events</a> for a list of our monthly Zoom classes.</p>',

			'ceu' =>
				'<p>Various CEUs and university credit are available for this class. For complete details click <a href="https://www.grantwritingusa.com/ceu-credits/">here</a>.</p>',

			'payment' =>
				'<p>Payment by credit card at the time of enrollment is preferred, however, you may pay later by check. Our registration system will auto-generate a personalized invoice/receipt for you immediately after you enroll. If you choose to pay by check, it is your responsibility to print the online invoice and guide it through your purchasing channels. We do not mail invoices. Payment by check or card is required by the workshop date unless other arrangements are made.</p>',

			'purchase_orders' =>
				'<p>If you work for a government agency and want to pay by purchase order, when you register online choose the &ldquo;pay by check&rdquo; option. The web site will auto-generate a printable invoice. Print the invoice, give it and your purchase order to your purchasing department and they\'ll send the check. That\'s it!</p>',

			'cancel' =>
				'<p>Tuition is set regardless of method of instruction and will not be refunded if instruction occurs remotely at another time. Withdrawals are allowed up to one week prior to the workshop. Tuition refunds &mdash; less a $30 admin charge &mdash; are made by check and mailed within 5 working days of receiving your cancellation. If you cancel within one week of the workshop or if you\'re registered for a workshop and fail to show up, you are obliged to submit your tuition in full and are then prepaid for and welcome to attend any future workshop we offer within one year of the workshop you cancelled. If you register within 10 days of the class, you may cancel your registration up to 5 days after by notifying us via email at <a href="mailto:cs@grantwritingusa.com">cs@grantwritingusa.com</a>. Tuition refunds &mdash; less a $30 admin charge &mdash; are made within 5 working days of receiving your cancellation notice.</p>',

			'questions' =>
				'<p><a href="mailto:cs@grantwritingusa.com">Email</a> or call The Client Services Team at Grant Writing USA, at 800.814.8191, 8:00 am to 4:00 pm (PT).</p>',

			'ready_to_enroll' =>
				'<p>Great &mdash; it\'s easy!</p>',
		);

		if ( $section === 'itinerary' ) {
			return $is_zoom ? $defaults['itinerary_zoom'] : $defaults['itinerary_inperson'];
		}
		if ( $section === 'format' ) {
			if ( $is_zoom ) {
				return $defaults['format_zoom'];
			}
			$key = 'format_' . $type . '_inperson';
			return $defaults[ $key ] ?? $defaults['format_writing_inperson'];
		}

		return $defaults[ $section ] ?? '';
	}

	public static function register_ajax(): void {
		add_action( 'wp_ajax_gwu_ep_reset_page_section', array( __CLASS__, 'ajax_reset_section' ) );
	}

	public static function ajax_reset_section(): void {
		check_ajax_referer( 'gwu_ep_page_content' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		$context = sanitize_key( $_POST['context'] ?? '' );
		$section = sanitize_key( $_POST['section'] ?? '' );

		if ( ! isset( self::get_contexts()[ $context ], self::get_sections()[ $section ] ) ) {
			wp_send_json_error( 'Invalid context or section.' );
		}

		self::reset_section( $context, $section );
		wp_send_json_success( array( 'message' => 'Section reset to defaults.' ) );
	}
}
