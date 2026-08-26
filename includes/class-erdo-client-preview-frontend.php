<?php
defined( 'ABSPATH' ) || exit;

class Erdo_Client_Preview_Frontend {

	private Erdo_Client_Preview_Settings $settings;
	private Erdo_Client_Preview_Token $token;
	private Erdo_Client_Preview_DB $db;

	public function __construct( Erdo_Client_Preview_Settings $settings, Erdo_Client_Preview_Token $token, Erdo_Client_Preview_DB $db ) {
		$this->settings = $settings;
		$this->token    = $token;
		$this->db       = $db;
	}

	const SUBSCRIBERS_KEY = 'erdo_client_preview_subscribers';

	// Anti-spam limits for the anonymous visitor feedback endpoint.
	const FEEDBACK_RATE_LIMIT_COOLDOWN = 15;             // seconds required between two submissions from the same IP
	const FEEDBACK_RATE_LIMIT_MAX      = 5;               // max submissions per IP within the window below
	const FEEDBACK_RATE_LIMIT_WINDOW   = HOUR_IN_SECONDS;

	public function register( Erdo_Client_Preview_Loader $loader ): void {
		$loader->add_action( 'init',                    $this, 'handle_special_params',  1 );
		$loader->add_action( 'template_redirect',       $this, 'maybe_show_maintenance', 1 );
		$loader->add_action( 'admin_post_em_subscribe', $this, 'handle_subscribe' );
		$loader->add_action( 'admin_post_nopriv_em_subscribe', $this, 'handle_subscribe' );
		$loader->add_action( 'rest_api_init',           $this, 'register_rest_routes' );
		$loader->add_action( 'wp_footer',               $this, 'render_live_site_feedback_widget' );
		$loader->add_action( 'wp_footer',               $this, 'render_annotation_mode' );
		$loader->add_action( 'wp_footer',               $this, 'render_annotation_locator' );
	}

	// -------------------------------------------------------------------------
	// Step 1: Handle special URL parameters (magic token, rescue, preview)
	// -------------------------------------------------------------------------

	public function handle_special_params(): void {
		$this->handle_rescue_key();
		$this->handle_magic_token();
		$this->handle_preview();
		$this->handle_feedback_submission();
	}

	private function handle_rescue_key(): void {
		if ( ! isset( $_GET['em_rescue'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$provided = sanitize_text_field( wp_unslash( $_GET['em_rescue'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$stored   = $this->settings->get( 'rescue_key', '' );

		if ( empty( $stored ) || ! hash_equals( $stored, $provided ) ) {
			wp_die(
				esc_html__( 'Invalid rescue key.', 'erdo-client-preview' ),
				esc_html__( 'Erdo Client Preview', 'erdo-client-preview' ),
				array( 'response' => 403 )
			);
		}

		// Valid rescue key: disable maintenance and redirect to settings.
		$current = (array) get_option( Erdo_Client_Preview_Settings::OPTION_KEY, array() );
		$current['enabled'] = false;
		update_option( Erdo_Client_Preview_Settings::OPTION_KEY, $current );

		wp_safe_redirect( add_query_arg( 'em_msg', 'rescued', admin_url( 'admin.php?page=erdo-client-preview' ) ) );
		exit;
	}

	private function handle_magic_token(): void {
		if ( ! isset( $_GET['em_token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$raw = sanitize_text_field( wp_unslash( $_GET['em_token'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( ! ctype_alnum( $raw ) || strlen( $raw ) !== 32 ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		$link = $this->token->find_by_token( $raw );

		if ( ! $link ) {
			wp_die(
				esc_html__( 'This magic link is invalid or has been revoked.', 'erdo-client-preview' ),
				esc_html__( 'Erdo Client Preview', 'erdo-client-preview' ),
				array( 'response' => 403 )
			);
		}

		if ( $this->token->is_expired( $link['expires_at'] ?? null ) ) {
			wp_die(
				esc_html__( 'This magic link has expired.', 'erdo-client-preview' ),
				esc_html__( 'Erdo Client Preview', 'erdo-client-preview' ),
				array( 'response' => 410 )
			);
		}

		$this->token->set_bypass_cookie( $link['token_hash'] );
		$this->token->increment_views( $link['id'] );
		$this->token->log_access( $link['id'], $this->get_visitor_ip() );
		$this->maybe_send_access_notification( $link );

		$redirect = ! empty( $link['redirect_url'] ) ? $link['redirect_url'] : home_url( '/' );
		wp_safe_redirect( $redirect );
		exit;
	}

	private function handle_preview(): void {
		if ( ! isset( $_GET['em_preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_GET['em_preview'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! wp_verify_nonce( $nonce, 'em_preview_maintenance' ) ) {
			return;
		}
		// Show maintenance page with 200 status (preview only).
		$this->render_maintenance_page( true );
	}

	// -------------------------------------------------------------------------
	// Visitor feedback — lets a visitor leave a name + message on the
	// maintenance/coming soon page, stored in its own DB table.
	// -------------------------------------------------------------------------

	public function register_rest_routes(): void {
		register_rest_route(
			'erdo-client-preview/v1',
			'/feedback',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_submit_feedback' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'name'               => array( 'required' => true ),
					'message'            => array( 'required' => true ),
					'nonce'              => array( 'required' => true ),
					'erdo_feedback_url'  => array( 'required' => false ),
				),
			)
		);

		register_rest_route(
			'erdo-client-preview/v1',
			'/feedback/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_feedback_status' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'items' => array( 'required' => true ),
				),
			)
		);

		register_rest_route(
			'erdo-client-preview/v1',
			'/annotations',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_submit_annotation' ),
					'permission_callback' => array( $this, 'rest_permission_annotation_access' ),
					'args'                => array(
						'page_url'       => array( 'required' => true ),
						'selector'       => array( 'required' => true ),
						'x_percent'      => array( 'required' => true ),
						'y_percent'      => array( 'required' => true ),
						'page_x_percent' => array( 'required' => true ),
						'page_y_percent' => array( 'required' => true ),
						'message'        => array( 'required' => true ),
						'name'           => array( 'required' => true ),
						'nonce'          => array( 'required' => true ),
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_annotations' ),
					'permission_callback' => array( $this, 'rest_permission_annotation_access' ),
					'args'                => array(
						'page_url' => array( 'required' => true ),
					),
				),
			)
		);

		register_rest_route(
			'erdo-client-preview/v1',
			'/annotations/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_annotation_status' ),
				'permission_callback' => array( $this, 'rest_permission_annotation_access' ),
				'args'                => array(
					'ids' => array( 'required' => true ),
				),
			)
		);
	}

	/**
	 * Annotation submission/listing is restricted to magic-link reviewers
	 * and administrators — never shown to anonymous visitors.
	 */
	public function rest_permission_annotation_access(): bool {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return $this->token->has_valid_bypass_cookie();
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_submit_feedback( WP_REST_Request $request ) {
		nocache_headers();

		if ( ! $this->settings->get( 'feedback_enable' ) ) {
			return new WP_Error( 'erdo_feedback_disabled', __( 'Feedback is currently disabled.', 'erdo-client-preview' ), array( 'status' => 403 ) );
		}

		$honeypot = sanitize_text_field( (string) $request->get_param( 'erdo_feedback_url' ) );
		if ( $this->feedback_honeypot_triggered( $honeypot ) ) {
			return new WP_Error( 'erdo_feedback_failed', __( 'An error occurred. Please try again.', 'erdo-client-preview' ), array( 'status' => 400 ) );
		}

		$nonce = sanitize_text_field( (string) $request->get_param( 'nonce' ) );
		if ( ! wp_verify_nonce( $nonce, 'erdo_client_preview_feedback' ) ) {
			return new WP_Error( 'erdo_invalid_nonce', __( 'An error occurred. Please try again.', 'erdo-client-preview' ), array( 'status' => 403 ) );
		}

		$name    = sanitize_text_field( (string) $request->get_param( 'name' ) );
		$message = sanitize_textarea_field( (string) $request->get_param( 'message' ) );

		if ( '' === $name || '' === $message ) {
			return new WP_Error( 'erdo_missing_fields', __( 'An error occurred. Please try again.', 'erdo-client-preview' ), array( 'status' => 400 ) );
		}

		if ( $this->feedback_rate_limited( $this->get_visitor_ip() ) ) {
			return new WP_Error( 'erdo_feedback_rate_limited', __( "You're submitting feedback too quickly. Please wait a moment and try again.", 'erdo-client-preview' ), array( 'status' => 429 ) );
		}

		$feedback_id = $this->db->add_feedback( $name, $message );

		if ( ! $feedback_id ) {
			return new WP_Error( 'erdo_feedback_failed', __( 'An error occurred. Please try again.', 'erdo-client-preview' ), array( 'status' => 500 ) );
		}

		$this->send_feedback_notification( $name, $message );

		return rest_ensure_response(
			array(
				'success' => true,
				'item'    => array(
					'id'           => $feedback_id,
					'token'        => self::feedback_status_token( $feedback_id ),
					'name'         => $name,
					'message'      => $message,
					'status'       => Erdo_Client_Preview_DB::FEEDBACK_STATUS_OPEN,
					'status_label' => Erdo_Client_Preview_DB::get_feedback_status_label( Erdo_Client_Preview_DB::FEEDBACK_STATUS_OPEN ),
					'reply'        => '',
					'date'         => wp_date( get_option( 'date_format' ) ),
					'initial'      => mb_strtoupper( mb_substr( $name, 0, 1 ) ),
				),
			)
		);
	}

	/**
	 * A feedback row's id is sequential and guessable, so status lookups
	 * require this per-row token (derived from WordPress' secret auth
	 * salts) instead of the bare id — otherwise anyone could enumerate ids
	 * and read other visitors' admin replies.
	 */
	private static function feedback_status_token( int $id ): string {
		return wp_hash( 'erdo_client_preview_feedback_status|' . $id );
	}

	/**
	 * Read-only endpoint used by the feedback widget to sync a visitor's
	 * locally stored feedback history with the latest status. Each
	 * requested id must be paired with its status token (see
	 * feedback_status_token()) — ids without a matching token are ignored.
	 *
	 * @return WP_REST_Response
	 */
	public function rest_feedback_status( WP_REST_Request $request ) {
		nocache_headers();

		$items_param = (string) $request->get_param( 'items' );
		$pairs       = array_filter( array_map( 'trim', explode( ',', $items_param ) ) );

		$valid_ids = array();

		foreach ( $pairs as $pair ) {
			$parts = explode( ':', $pair, 2 );
			$id    = absint( $parts[0] ?? '' );
			$token = sanitize_text_field( $parts[1] ?? '' );

			if ( $id && $token && hash_equals( self::feedback_status_token( $id ), $token ) ) {
				$valid_ids[] = $id;
			}
		}

		$valid_ids = array_slice( array_unique( $valid_ids ), 0, 50 );

		$items = array();

		foreach ( $this->db->get_feedback_by_ids( $valid_ids ) as $row ) {
			$items[] = array(
				'id'           => (int) $row->id,
				'token'        => self::feedback_status_token( (int) $row->id ),
				'status'       => $row->status,
				'status_label' => Erdo_Client_Preview_DB::get_feedback_status_label( $row->status ),
				'reply'        => $row->admin_reply,
			);
		}

		return rest_ensure_response( array( 'items' => $items ) );
	}

	// -------------------------------------------------------------------------
	// Visual annotations — lets a magic-link reviewer click an element on
	// the live site and leave a pinned note, stored in its own DB table.
	// -------------------------------------------------------------------------

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_submit_annotation( WP_REST_Request $request ) {
		nocache_headers();

		if ( ! $this->settings->get( 'annotation_enable' ) ) {
			return new WP_Error( 'erdo_annotation_disabled', __( 'Annotations are currently disabled.', 'erdo-client-preview' ), array( 'status' => 403 ) );
		}

		$nonce = sanitize_text_field( (string) $request->get_param( 'nonce' ) );
		if ( ! wp_verify_nonce( $nonce, 'erdo_client_preview_annotation' ) ) {
			return new WP_Error( 'erdo_invalid_nonce', __( 'An error occurred. Please try again.', 'erdo-client-preview' ), array( 'status' => 403 ) );
		}

		$page_url       = sanitize_text_field( (string) $request->get_param( 'page_url' ) );
		$selector       = sanitize_text_field( (string) $request->get_param( 'selector' ) );
		$x_percent      = (float) $request->get_param( 'x_percent' );
		$y_percent      = (float) $request->get_param( 'y_percent' );
		$page_x_percent = (float) $request->get_param( 'page_x_percent' );
		$page_y_percent = (float) $request->get_param( 'page_y_percent' );
		$message        = sanitize_textarea_field( (string) $request->get_param( 'message' ) );
		$name           = sanitize_text_field( (string) $request->get_param( 'name' ) );

		if ( '' === $page_url || '' === $selector || '' === $message || '' === $name ) {
			return new WP_Error( 'erdo_missing_fields', __( 'An error occurred. Please try again.', 'erdo-client-preview' ), array( 'status' => 400 ) );
		}

		$annotation_id = $this->db->add_annotation( $page_url, $selector, $x_percent, $y_percent, $page_x_percent, $page_y_percent, $message, $name );

		if ( ! $annotation_id ) {
			return new WP_Error( 'erdo_annotation_failed', __( 'An error occurred. Please try again.', 'erdo-client-preview' ), array( 'status' => 500 ) );
		}

		$this->send_annotation_notification( $name, $message, $page_url );

		return rest_ensure_response(
			array(
				'success' => true,
				'item'    => array(
					'id'           => $annotation_id,
					'selector'     => $selector,
					'x_percent'    => $x_percent,
					'y_percent'    => $y_percent,
					'message'      => $message,
					'author_name'  => $name,
					'status'       => Erdo_Client_Preview_DB::FEEDBACK_STATUS_OPEN,
					'status_label' => Erdo_Client_Preview_DB::get_feedback_status_label( Erdo_Client_Preview_DB::FEEDBACK_STATUS_OPEN ),
					'reply'        => '',
				),
			)
		);
	}

	/**
	 * List existing annotations for a given page so pins can be rendered
	 * on load.
	 *
	 * @return WP_REST_Response
	 */
	public function rest_get_annotations( WP_REST_Request $request ) {
		nocache_headers();

		$page_url = sanitize_text_field( (string) $request->get_param( 'page_url' ) );

		$items = array();

		foreach ( $this->db->get_annotations_for_page( $page_url ) as $row ) {
			$items[] = array(
				'id'           => (int) $row->id,
				'selector'     => $row->selector,
				'x_percent'    => (float) $row->x_percent,
				'y_percent'    => (float) $row->y_percent,
				'message'      => $row->message,
				'author_name'  => $row->author_name,
				'status'       => $row->status,
				'status_label' => Erdo_Client_Preview_DB::get_feedback_status_label( $row->status ),
				'reply'        => $row->admin_reply,
			);
		}

		return rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * Public read-only endpoint used by the annotation pins to sync the
	 * latest status and admin reply.
	 *
	 * @return WP_REST_Response
	 */
	public function rest_annotation_status( WP_REST_Request $request ) {
		nocache_headers();

		$ids_param = (string) $request->get_param( 'ids' );
		$ids       = array_filter( array_map( 'absint', explode( ',', $ids_param ) ) );
		$ids       = array_slice( array_unique( $ids ), 0, 50 );

		$items = array();

		foreach ( $this->db->get_annotations_by_ids( $ids ) as $row ) {
			$items[] = array(
				'id'           => (int) $row->id,
				'status'       => $row->status,
				'status_label' => Erdo_Client_Preview_DB::get_feedback_status_label( $row->status ),
				'reply'        => $row->admin_reply,
			);
		}

		return rest_ensure_response( array( 'items' => $items ) );
	}

	private function send_annotation_notification( string $name, string $message, string $page_url ): void {
		$site = get_bloginfo( 'name' );

		$subject = sprintf(
			/* translators: %s: site name */
			__( 'New annotation on %s', 'erdo-client-preview' ),
			$site
		);

		$body = sprintf(
			/* translators: 1: visitor name, 2: annotation message, 3: page URL, 4: admin URL to manage annotations */
			__( "%1\$s left an annotation on your live site:\n\n%2\$s\n\nPage: %3\$s\n\nManage annotations: %4\$s", 'erdo-client-preview' ),
			$name,
			$message,
			$page_url,
			admin_url( 'admin.php?page=erdo-client-preview&tab=annotations' )
		);

		wp_mail( get_option( 'admin_email' ), $subject, $body );
	}

	/**
	 * No-JS fallback: handles a classic <form> POST submission of the
	 * feedback form and redirects back with ?erdo_feedback=sent.
	 */
	/**
	 * True if the honeypot field was filled in — a real visitor never sees
	 * or fills it, so a non-empty value means the submission came from a bot.
	 */
	private function feedback_honeypot_triggered( string $value ): bool {
		return '' !== trim( $value );
	}

	/**
	 * Per-IP flood control for the anonymous feedback endpoint: a short
	 * cooldown between submissions plus a rolling cap per hour. Also records
	 * the attempt, so this must only be called once validation would
	 * otherwise let the submission through.
	 */
	private function feedback_rate_limited( string $ip ): bool {
		if ( '' === $ip ) {
			return false;
		}

		$cooldown_key = 'erdo_fb_cd_' . md5( $ip );
		if ( false !== get_transient( $cooldown_key ) ) {
			return true;
		}

		$count_key = 'erdo_fb_ct_' . md5( $ip );
		$count     = (int) get_transient( $count_key );

		if ( $count >= self::FEEDBACK_RATE_LIMIT_MAX ) {
			return true;
		}

		set_transient( $cooldown_key, 1, self::FEEDBACK_RATE_LIMIT_COOLDOWN );
		set_transient( $count_key, $count + 1, self::FEEDBACK_RATE_LIMIT_WINDOW );

		return false;
	}

	private function handle_feedback_submission(): void {
		if ( ! isset( $_POST['erdo_client_preview_feedback_submit'] ) ) {
			return;
		}

		if ( ! $this->settings->get( 'feedback_enable' ) ) {
			return;
		}

		$honeypot = isset( $_POST['erdo_feedback_url'] ) ? sanitize_text_field( wp_unslash( $_POST['erdo_feedback_url'] ) ) : '';
		if ( $this->feedback_honeypot_triggered( $honeypot ) ) {
			return;
		}

		$nonce = isset( $_POST['erdo_feedback_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['erdo_feedback_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'erdo_client_preview_feedback' ) ) {
			return;
		}

		$name    = isset( $_POST['erdo_feedback_name'] ) ? sanitize_text_field( wp_unslash( $_POST['erdo_feedback_name'] ) ) : '';
		$message = isset( $_POST['erdo_feedback_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['erdo_feedback_message'] ) ) : '';

		if ( '' === $name || '' === $message ) {
			return;
		}

		if ( $this->feedback_rate_limited( $this->get_visitor_ip() ) ) {
			return;
		}

		$feedback_id = $this->db->add_feedback( $name, $message );

		if ( $feedback_id ) {
			$this->send_feedback_notification( $name, $message );
		}

		$redirect = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : home_url( '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		wp_safe_redirect( add_query_arg( 'erdo_feedback', 'sent', $redirect ) );
		exit;
	}

	private function send_feedback_notification( string $name, string $message ): void {
		$site = get_bloginfo( 'name' );

		$subject = sprintf(
			/* translators: %s: site name */
			__( 'New visitor feedback on %s', 'erdo-client-preview' ),
			$site
		);

		$body = sprintf(
			/* translators: 1: visitor name, 2: feedback message, 3: admin URL to manage feedback */
			__( "%1\$s left feedback on your maintenance page:\n\n%2\$s\n\nManage feedback: %3\$s", 'erdo-client-preview' ),
			$name,
			$message,
			admin_url( 'admin.php?page=erdo-client-preview&tab=feedback' )
		);

		wp_mail( get_option( 'admin_email' ), $subject, $body );
	}

	// -------------------------------------------------------------------------
	// Step 2: Check maintenance mode on every request
	// -------------------------------------------------------------------------

	public function maybe_show_maintenance(): void {
		if ( ! $this->settings->is_active() ) {
			return;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		if ( $this->settings->get( 'bypass_admins' ) && is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( is_admin() ) {
			return;
		}

		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return;
		}

		if ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'] ) {
			return;
		}

		if ( $this->is_ip_whitelisted() ) {
			return;
		}

		if ( $this->token->has_valid_bypass_cookie() ) {
			return;
		}

		if ( $this->is_page_excluded() ) {
			return;
		}

		$this->render_maintenance_page( false );
	}

	private function is_page_excluded(): bool {
		if ( ! $this->settings->get( 'page_exclusions_enable' ) ) {
			return false;
		}

		// URL path matching
		$excluded_paths = $this->settings->get_excluded_paths();
		if ( ! empty( $excluded_paths ) ) {
			$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$current_path = strtok( $request_uri, '?' );
			foreach ( $excluded_paths as $path ) {
				$pattern = '/' . ltrim( $path, '/' );
				$pattern = rtrim( $pattern, '/' );
				if ( '/' === $pattern ) {
					if ( '/' === $current_path ) {
						return true;
					}
				} elseif ( $current_path === $pattern || strpos( $current_path, $pattern . '/' ) === 0 ) {
					return true;
				}
			}
		}

		// Post type matching (works at template_redirect)
		$excluded_post_types = $this->settings->get_excluded_post_types();
		if ( ! empty( $excluded_post_types ) ) {
			$queried   = get_queried_object();
			$post_type = '';
			if ( $queried instanceof WP_Post ) {
				$post_type = $queried->post_type;
			} elseif ( $queried instanceof WP_Post_Type ) {
				$post_type = $queried->name;
			}
			if ( $post_type && in_array( $post_type, $excluded_post_types, true ) ) {
				return true;
			}
		}

		return false;
	}

	// -------------------------------------------------------------------------
	// Maintenance page output
	// -------------------------------------------------------------------------

	private function render_maintenance_page( bool $preview = false ): void {
		$retry_seconds = 3600;
		$countdown_ts  = 0;

		if ( $this->settings->get( 'countdown_enable' ) && $this->settings->get( 'countdown_date' ) ) {
			$countdown_ts  = ( new DateTime( $this->settings->get( 'countdown_date' ), wp_timezone() ) )->getTimestamp();
			$retry_seconds = max( 60, $countdown_ts - time() );
		}

		$mode = $this->settings->get_mode();
		if ( $preview || 'coming_soon' === $mode ) {
			status_header( 200 );
		} else {
			status_header( 503 );
			header( 'Retry-After: ' . (int) $retry_seconds );
		}

		header( 'Content-Type: text/html; charset=UTF-8' );
		nocache_headers();

		$subscriber_count = count( (array) get_option( self::SUBSCRIBERS_KEY, array() ) );

		$cfg = array(
			'mode'             => $mode,
			'heading'          => $this->settings->get( 'heading' ),
			'message'          => $this->settings->get( 'message' ),
			'page_title'       => $this->settings->get( 'page_title' ),
			'logo_url'         => $this->settings->get( 'logo_url' ),
			'bg_color'         => $this->settings->get( 'bg_color' ),
			'bg_image_url'     => $this->settings->get( 'bg_image_url' ),
			'text_color'       => $this->settings->get( 'text_color' ),
			'accent_color'     => $this->settings->get( 'accent_color' ),
			'countdown_enable' => $this->settings->get( 'countdown_enable' ),
			'countdown_ts'     => $countdown_ts,
			'social_links'     => $this->settings->get_social_links(),
			'subscribe_enable' => $this->settings->get( 'subscribe_enable' ),
			'subscribe_label'  => $this->settings->get( 'subscribe_label' ),
			'visitor_counter_enable' => $this->settings->get( 'visitor_counter_enable' ),
			'visitor_counter_label'  => $this->settings->get( 'visitor_counter_label', '%d people are waiting' ),
			'visitor_count'          => $subscriber_count,
			'is_preview'       => $preview,
			'feedback_enable'  => (bool) $this->settings->get( 'feedback_enable' ),
			'feedback_nonce'   => wp_create_nonce( 'erdo_client_preview_feedback' ),
			'feedback_rest_url' => rest_url( 'erdo-client-preview/v1/feedback' ),
			'feedback_sent'    => isset( $_GET['erdo_feedback'] ) && 'sent' === $_GET['erdo_feedback'], // phpcs:ignore WordPress.Security.NonceVerification
		);

		$template = ERDO_CLIENT_PREVIEW_PLUGIN_DIR . 'templates/maintenance.php';
		if ( file_exists( $template ) ) {
			include $template;
		}

		exit;
	}

	// -------------------------------------------------------------------------
	// Live site feedback widget — shown to magic-link bypass visitors so
	// reviewers/clients can leave feedback while browsing the live site.
	// -------------------------------------------------------------------------

	public function render_live_site_feedback_widget(): void {
		if ( ! $this->settings->get( 'feedback_enable' ) ) {
			return;
		}

		if ( ! $this->settings->is_active() ) {
			return;
		}

		if ( ! $this->token->has_valid_bypass_cookie() ) {
			return;
		}

		$sm_feedback_sent = isset( $_GET['erdo_feedback'] ) && 'sent' === $_GET['erdo_feedback']; // phpcs:ignore WordPress.Security.NonceVerification

		wp_enqueue_style( 'erdo-client-preview-feedback-widget', plugins_url( 'assets/css/feedback-widget.css', ERDO_CLIENT_PREVIEW_PLUGIN_FILE ), array(), ERDO_CLIENT_PREVIEW_VERSION );
		wp_print_styles( 'erdo-client-preview-feedback-widget' );
		?>
		<?php include ERDO_CLIENT_PREVIEW_PLUGIN_DIR . 'templates/partials/feedback-widget.php'; ?>
		<?php
		wp_enqueue_script( 'erdo-client-preview-feedback-widget', plugins_url( 'assets/js/feedback-widget.js', ERDO_CLIENT_PREVIEW_PLUGIN_FILE ), array(), ERDO_CLIENT_PREVIEW_VERSION, true );
		wp_localize_script( 'erdo-client-preview-feedback-widget', 'erdoClientPreviewFeedback', array(
			'restUrl'   => rest_url( 'erdo-client-preview/v1/feedback' ),
			'statusUrl' => rest_url( 'erdo-client-preview/v1/feedback/status' ),
			'nonce'     => wp_create_nonce( 'erdo_client_preview_feedback' ),
			'i18n'      => array(
				'submit'  => __( 'Send Feedback', 'erdo-client-preview' ),
				'sending' => __( 'Sending…', 'erdo-client-preview' ),
				'success' => __( 'Thanks! Your feedback has been sent.', 'erdo-client-preview' ),
				'error'   => __( 'An error occurred. Please try again.', 'erdo-client-preview' ),
				'reply'   => __( 'Reply:', 'erdo-client-preview' ),
			),
		) );
		wp_print_scripts( 'erdo-client-preview-feedback-widget' );
	}

	// -------------------------------------------------------------------------
	// Visual annotation mode — shown to magic-link bypass visitors so
	// reviewers/clients can click any element on the live site and leave a
	// pinned note for the site owner.
	// -------------------------------------------------------------------------

	public function render_annotation_mode(): void {
		if ( ! $this->settings->get( 'annotation_enable' ) ) {
			return;
		}

		if ( ! $this->settings->is_active() ) {
			return;
		}

		if ( ! $this->token->has_valid_bypass_cookie() ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$page_url    = strtok( $request_uri, '?' );

		wp_enqueue_style( 'erdo-client-preview-annotation-mode', plugins_url( 'assets/css/annotation-mode.css', ERDO_CLIENT_PREVIEW_PLUGIN_FILE ), array(), ERDO_CLIENT_PREVIEW_VERSION );
		wp_print_styles( 'erdo-client-preview-annotation-mode' );

		wp_enqueue_script( 'erdo-client-preview-annotation-mode', plugins_url( 'assets/js/annotation-mode.js', ERDO_CLIENT_PREVIEW_PLUGIN_FILE ), array(), ERDO_CLIENT_PREVIEW_VERSION, true );
		wp_localize_script( 'erdo-client-preview-annotation-mode', 'erdoClientPreviewAnnotation', array(
			'restUrl'   => rest_url( 'erdo-client-preview/v1/annotations' ),
			'statusUrl' => rest_url( 'erdo-client-preview/v1/annotations/status' ),
			'nonce'     => wp_create_nonce( 'erdo_client_preview_annotation' ),
			'pageUrl'   => $page_url,
			'i18n'      => array(
				'toggleOn'  => __( 'Leave Feedback on Page', 'erdo-client-preview' ),
				'toggleOff' => __( 'Exit Annotation Mode', 'erdo-client-preview' ),
				'name'      => __( 'Your name', 'erdo-client-preview' ),
				'message'   => __( 'Your note', 'erdo-client-preview' ),
				'submit'    => __( 'Add Note', 'erdo-client-preview' ),
				'sending'   => __( 'Sending…', 'erdo-client-preview' ),
				'error'     => __( 'An error occurred. Please try again.', 'erdo-client-preview' ),
				'cancel'    => __( 'Cancel', 'erdo-client-preview' ),
				'reply'     => __( 'Reply:', 'erdo-client-preview' ),
				'close'     => __( 'Close', 'erdo-client-preview' ),
			),
		) );
		wp_print_scripts( 'erdo-client-preview-annotation-mode' );
	}

	/**
	 * Annotation pins for admins — shows a persistent numbered pin for every
	 * annotation left on the current page (using each note's stored
	 * page-relative x/y position), just like the magic-link reviewer sees.
	 * Clicking any pin opens its details.
	 */
	public function render_annotation_locator(): void {
		if ( ! $this->settings->get( 'annotation_enable' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$page_url    = strtok( $request_uri, '?' );

		$annotations = $this->db->get_annotations_for_page( $page_url );

		if ( empty( $annotations ) ) {
			return;
		}

		$items = array();

		foreach ( $annotations as $annotation ) {
			$items[] = array(
				'id'           => (int) $annotation->id,
				'authorName'   => $annotation->author_name,
				'message'      => $annotation->message,
				'status'       => $annotation->status,
				'statusLabel'  => Erdo_Client_Preview_DB::get_feedback_status_label( $annotation->status ),
				'reply'        => $annotation->admin_reply,
				'pageXPercent' => (float) $annotation->page_x_percent,
				'pageYPercent' => (float) $annotation->page_y_percent,
			);
		}

		wp_enqueue_style( 'erdo-client-preview-annotation-mode', plugins_url( 'assets/css/annotation-mode.css', ERDO_CLIENT_PREVIEW_PLUGIN_FILE ), array(), ERDO_CLIENT_PREVIEW_VERSION );
		wp_print_styles( 'erdo-client-preview-annotation-mode' );

		wp_enqueue_script( 'erdo-client-preview-annotation-locator', plugins_url( 'assets/js/annotation-locator.js', ERDO_CLIENT_PREVIEW_PLUGIN_FILE ), array(), ERDO_CLIENT_PREVIEW_VERSION, true );
		wp_localize_script( 'erdo-client-preview-annotation-locator', 'erdoClientPreviewAnnotationLocator', array(
			'items' => $items,
			'i18n'  => array(
				'close' => __( 'Close', 'erdo-client-preview' ),
				'reply' => __( 'Reply:', 'erdo-client-preview' ),
			),
		) );
		wp_print_scripts( 'erdo-client-preview-annotation-locator' );
	}

	// -------------------------------------------------------------------------
	// IP helpers
	// -------------------------------------------------------------------------

	private function maybe_send_access_notification( array $link ): void {
		if ( ! $this->settings->get( 'notify_enable' ) ) {
			return;
		}

		$to = $this->settings->get( 'notify_email' ) ?: get_option( 'admin_email' );
		if ( ! is_email( $to ) ) {
			return;
		}

		$site  = get_bloginfo( 'name' );
		$label = $link['label'] ?: __( '(no label)', 'erdo-client-preview' );

		$subject = sprintf(
			/* translators: 1: site name, 2: magic link label */
			__( '[%1$s] Magic link accessed — %2$s', 'erdo-client-preview' ),
			$site,
			$label
		);

		$body = sprintf(
			/* translators: 1: link label, 2: site name, 3: date/time, 4: IP address */
			__(
				"A magic link was just used on your site.\n\nLink label: %1\$s\nSite: %2\$s\nTime: %3\$s\nVisitor IP: %4\$s\n\nTotal views for this link: %5\$d",
				'erdo-client-preview'
			),
			$label,
			$site,
			wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
			$this->get_visitor_ip(),
			(int) $link['view_count'] + 1
		);

		wp_mail( $to, $subject, $body );
	}

	// -------------------------------------------------------------------------
	// Email subscription (Coming Soon mode)
	// -------------------------------------------------------------------------

	public function handle_subscribe(): void {
		if ( ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ),
			'em_subscribe'
		) ) {
			wp_die( esc_html__( 'Security check failed.', 'erdo-client-preview' ), '', array( 'response' => 403 ) );
		}

		$email = sanitize_email( wp_unslash( $_POST['em_email'] ?? '' ) );

		if ( ! is_email( $email ) ) {
			wp_safe_redirect( add_query_arg( 'em_subscribed', 'invalid', home_url( '/' ) ) );
			exit;
		}

		$subscribers = (array) get_option( self::SUBSCRIBERS_KEY, array() );

		// Prevent duplicates.
		foreach ( $subscribers as $row ) {
			if ( isset( $row['email'] ) && strtolower( $row['email'] ) === strtolower( $email ) ) {
				wp_safe_redirect( add_query_arg( 'em_subscribed', '1', home_url( '/' ) ) );
				exit;
			}
		}

		$subscribers[] = array(
			'email'      => $email,
			'created_at' => current_time( 'mysql' ),
			'ip'         => $this->get_visitor_ip(),
		);
		update_option( self::SUBSCRIBERS_KEY, $subscribers, false );

		wp_safe_redirect( add_query_arg( 'em_subscribed', '1', home_url( '/' ) ) );
		exit;
	}

	private function is_ip_whitelisted(): bool {
		$whitelist = $this->settings->get_ip_whitelist();
		if ( empty( $whitelist ) ) {
			return false;
		}
		return in_array( $this->get_visitor_ip(), $whitelist, true );
	}

	private function get_visitor_ip(): string {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}
}
