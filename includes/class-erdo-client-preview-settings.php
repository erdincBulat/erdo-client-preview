<?php
defined( 'ABSPATH' ) || exit;

class Erdo_Client_Preview_Settings {

	const OPTION_KEY = 'erdo_client_preview_settings';

	private array $settings;

	public function __construct() {
		$this->settings = $this->load();
	}

	public function register( Erdo_Client_Preview_Loader $loader ): void {
		$loader->add_action( 'admin_init', $this, 'register_settings' );
	}

	public function register_settings(): void {
		register_setting(
			'erdo_client_preview',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);
	}

	public static function set_defaults(): void {
		$saved  = (array) get_option( self::OPTION_KEY, array() );
		// Always merge so new fields added in upgrades are included.
		$merged = wp_parse_args( $saved, self::defaults() );

		if ( empty( $merged['rescue_key'] ) ) {
			$merged['rescue_key'] = wp_generate_password( 24, false, false );
		}

		update_option( self::OPTION_KEY, $merged );
	}

	public static function defaults(): array {
		return array(
			// Core
			'enabled'          => false,
			'mode'             => 'maintenance', // 'maintenance' (503) | 'coming_soon' (200)
			'heading'          => "We'll be back soon!",
			'message'          => 'We are making some improvements to our website. Please check back later.',
			'page_title'       => '', // empty = use site name
			// Design
			'logo_url'         => '',
			'bg_color'         => '#1a1a2e',
			'bg_image_url'     => '',
			'text_color'       => '#ffffff',
			'accent_color'     => '#e94560',
			// Countdown
			'countdown_enable' => false,
			'countdown_date'   => '',
			// One-time schedule
			'schedule_enable'  => false,
			'schedule_start'   => '',
			'schedule_end'     => '',
			// Recurring schedule
			'recurring_enable'     => false,
			'recurring_days'       => array(), // ISO weekday: '1'=Mon … '7'=Sun
			'recurring_start_time' => '02:00',
			'recurring_end_time'   => '04:00',
			// Access
			'ip_whitelist'     => '',
			'bypass_admins'    => true,
			// Page exclusions
			'page_exclusions_enable' => false,
			'excluded_paths'         => '', // one URL path per line
			'excluded_post_types'    => '', // one post type slug per line
			// Social
			'social_twitter'   => '',
			'social_instagram' => '',
			'social_facebook'  => '',
			'social_linkedin'  => '',
			'social_youtube'   => '',
			// Notifications
			'notify_enable'    => false,
			'notify_email'     => '',
			// Subscribe (Coming Soon)
			'subscribe_enable' => false,
			'subscribe_label'  => 'Notify me when we launch',
			// Visitor counter
			'visitor_counter_enable' => false,
			'visitor_counter_label'  => '%d people are waiting',
			// White label
			'whitelabel_enable'  => false,
			'whitelabel_name'    => '',
			'whitelabel_logo_url' => '',
			// Visitor feedback
			'feedback_enable'  => true,
			// Visual annotations
			'annotation_enable' => false,
			// Security
			'rescue_key'       => '',
		);
	}

	public function sanitize( array $input ): array {
		// Preserve rescue_key — never overwrite from form input.
		$existing_key = $this->get( 'rescue_key' ) ?: wp_generate_password( 24, false, false );

		$clean = self::defaults();

		$clean['enabled']          = ! empty( $input['enabled'] );
		$clean['mode']             = in_array( $input['mode'] ?? '', array( 'maintenance', 'coming_soon' ), true ) ? $input['mode'] : 'maintenance';
		$clean['heading']          = sanitize_text_field( $input['heading'] ?? '' );
		$clean['message']          = wp_kses_post( $input['message'] ?? '' );
		$clean['page_title']       = sanitize_text_field( $input['page_title'] ?? '' );
		$clean['logo_url']         = esc_url_raw( $input['logo_url'] ?? '' );
		$clean['bg_color']         = sanitize_hex_color( $input['bg_color'] ?? '#1a1a2e' ) ?: '#1a1a2e';
		$clean['bg_image_url']     = esc_url_raw( $input['bg_image_url'] ?? '' );
		$clean['text_color']       = sanitize_hex_color( $input['text_color'] ?? '#ffffff' ) ?: '#ffffff';
		$clean['accent_color']     = sanitize_hex_color( $input['accent_color'] ?? '#e94560' ) ?: '#e94560';
		$clean['countdown_enable'] = ! empty( $input['countdown_enable'] );
		$clean['countdown_date']   = sanitize_text_field( $input['countdown_date'] ?? '' );
		$clean['schedule_enable']  = ! empty( $input['schedule_enable'] );
		$clean['schedule_start']   = sanitize_text_field( $input['schedule_start'] ?? '' );
		$clean['schedule_end']     = sanitize_text_field( $input['schedule_end'] ?? '' );
		$clean['ip_whitelist']     = sanitize_textarea_field( $input['ip_whitelist'] ?? '' );
		$clean['bypass_admins']    = ! empty( $input['bypass_admins'] );
		$clean['social_twitter']   = esc_url_raw( $input['social_twitter'] ?? '' );
		$clean['social_instagram'] = esc_url_raw( $input['social_instagram'] ?? '' );
		$clean['social_facebook']  = esc_url_raw( $input['social_facebook'] ?? '' );
		$clean['social_linkedin']  = esc_url_raw( $input['social_linkedin'] ?? '' );
		$clean['social_youtube']   = esc_url_raw( $input['social_youtube'] ?? '' );
		$clean['notify_enable']    = ! empty( $input['notify_enable'] );
		$clean['notify_email']     = sanitize_email( $input['notify_email'] ?? '' );
		$clean['subscribe_enable'] = ! empty( $input['subscribe_enable'] );
		$clean['subscribe_label']  = sanitize_text_field( $input['subscribe_label'] ?? '' );

		// Recurring schedule
		$clean['recurring_enable'] = ! empty( $input['recurring_enable'] );
		$raw_days   = isset( $input['recurring_days'] ) ? (array) $input['recurring_days'] : array();
		$valid_days = array( '1', '2', '3', '4', '5', '6', '7' );
		$clean['recurring_days'] = array_values( array_filter( $raw_days, static function ( $d ) use ( $valid_days ) {
			return in_array( (string) $d, $valid_days, true );
		} ) );
		$time_re = '/^\d{2}:\d{2}$/';
		$raw_s   = sanitize_text_field( $input['recurring_start_time'] ?? '02:00' );
		$raw_e   = sanitize_text_field( $input['recurring_end_time']   ?? '04:00' );
		$clean['recurring_start_time'] = preg_match( $time_re, $raw_s ) ? $raw_s : '02:00';
		$clean['recurring_end_time']   = preg_match( $time_re, $raw_e ) ? $raw_e : '04:00';

		// Page exclusions
		$clean['page_exclusions_enable'] = ! empty( $input['page_exclusions_enable'] );
		$clean['excluded_paths']         = sanitize_textarea_field( $input['excluded_paths'] ?? '' );
		$clean['excluded_post_types']    = sanitize_textarea_field( $input['excluded_post_types'] ?? '' );

		// Visitor counter
		$clean['visitor_counter_enable'] = ! empty( $input['visitor_counter_enable'] );
		$clean['visitor_counter_label']  = sanitize_text_field( $input['visitor_counter_label'] ?? '%d people are waiting' );

		// White label
		$clean['whitelabel_enable']   = ! empty( $input['whitelabel_enable'] );
		$clean['whitelabel_name']     = sanitize_text_field( $input['whitelabel_name'] ?? '' );
		$clean['whitelabel_logo_url'] = esc_url_raw( $input['whitelabel_logo_url'] ?? '' );

		// Visitor feedback
		$clean['feedback_enable'] = ! empty( $input['feedback_enable'] );

		// Visual annotations
		$clean['annotation_enable'] = ! empty( $input['annotation_enable'] );

		$clean['rescue_key'] = $existing_key;

		if ( ! empty( $input['regenerate_rescue_key'] ) ) {
			$clean['rescue_key'] = wp_generate_password( 24, false, false );
		}

		$this->settings = $clean;
		return $clean;
	}

	private function load(): array {
		$saved = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( $saved, self::defaults() );
	}

	public function get( string $key, $default = null ) {
		return $this->settings[ $key ] ?? $default;
	}

	public function all(): array {
		return $this->settings;
	}

	public function is_enabled(): bool {
		return (bool) $this->get( 'enabled' );
	}

	/**
	 * Returns true if maintenance should be shown right now.
	 * Checks manual toggle AND scheduled window.
	 */
	public function is_active(): bool {
		if ( $this->is_enabled() ) {
			return true;
		}

		$now = time();
		$tz  = wp_timezone();

		// One-time schedule check
		if ( $this->get( 'schedule_enable' ) ) {
			$start = $this->get( 'schedule_start' );
			$end   = $this->get( 'schedule_end' );
			if ( $start && $end ) {
				if ( $now >= ( new DateTime( $start, $tz ) )->getTimestamp()
					&& $now <= ( new DateTime( $end, $tz ) )->getTimestamp() ) {
					return true;
				}
			} elseif ( $start ) {
				if ( $now >= ( new DateTime( $start, $tz ) )->getTimestamp() ) {
					return true;
				}
			}
		}

		// Recurring schedule check
		if ( $this->get( 'recurring_enable' ) ) {
			$days  = (array) $this->get( 'recurring_days', array() );
			$rstart = $this->get( 'recurring_start_time', '02:00' );
			$rend   = $this->get( 'recurring_end_time',   '04:00' );
			if ( ! empty( $days ) && $rstart && $rend ) {
				$now_dt = new DateTime( 'now', $tz );
				$dow    = $now_dt->format( 'N' ); // '1'=Mon … '7'=Sun
				if ( in_array( $dow, $days, true ) ) {
					$today    = $now_dt->format( 'Y-m-d' );
					$start_ts = ( new DateTime( $today . ' ' . $rstart, $tz ) )->getTimestamp();
					$end_ts   = ( new DateTime( $today . ' ' . $rend,   $tz ) )->getTimestamp();
					if ( $now >= $start_ts && $now <= $end_ts ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	public function get_mode(): string {
		return $this->get( 'mode', 'maintenance' );
	}

	public function get_ip_whitelist(): array {
		$raw = $this->get( 'ip_whitelist', '' );
		if ( empty( $raw ) ) {
			return array();
		}
		return array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
	}

	public function get_excluded_paths(): array {
		$raw = $this->get( 'excluded_paths', '' );
		if ( empty( $raw ) ) {
			return array();
		}
		return array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
	}

	public function get_excluded_post_types(): array {
		$raw = $this->get( 'excluded_post_types', '' );
		if ( empty( $raw ) ) {
			return array();
		}
		return array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
	}

	public function get_social_links(): array {
		$links = array();
		$fields = array( 'twitter', 'instagram', 'facebook', 'linkedin', 'youtube' );
		foreach ( $fields as $platform ) {
			$url = $this->get( 'social_' . $platform, '' );
			if ( $url ) {
				$links[ $platform ] = $url;
			}
		}
		return $links;
	}
}
