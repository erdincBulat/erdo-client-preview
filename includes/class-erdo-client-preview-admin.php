<?php
defined( 'ABSPATH' ) || exit;

class Erdo_Client_Preview_Admin {

	private Erdo_Client_Preview_Settings $settings;
	private Erdo_Client_Preview_Token $token;
	private Erdo_Client_Preview_DB $db;

	public function __construct( Erdo_Client_Preview_Settings $settings, Erdo_Client_Preview_Token $token, Erdo_Client_Preview_DB $db ) {
		$this->settings = $settings;
		$this->token    = $token;
		$this->db       = $db;
	}

	public function register( Erdo_Client_Preview_Loader $loader ): void {
		$loader->add_action( 'admin_menu',                                                             $this, 'add_settings_page' );
		$loader->add_action( 'admin_enqueue_scripts',                                                  $this, 'enqueue_assets' );
		$loader->add_action( 'admin_post_em_new_link',                                                 $this, 'handle_new_link' );
		$loader->add_action( 'admin_post_em_revoke',                                                   $this, 'handle_revoke' );
		$loader->add_action( 'admin_post_em_delete',                                                   $this, 'handle_delete' );
		$loader->add_action( 'admin_post_em_toggle',                                                   $this, 'handle_toggle' );
		$loader->add_action( 'admin_post_erdo_client_preview_export_settings',                         $this, 'handle_export_settings' );
		$loader->add_action( 'admin_post_erdo_client_preview_import_settings',                         $this, 'handle_import_settings' );
		$loader->add_action( 'admin_init',                                                             $this, 'handle_feedback_actions' );
		$loader->add_action( 'admin_init',                                                             $this, 'handle_annotation_actions' );
		$loader->add_action( 'wp_ajax_erdo_client_preview_feedback_reply',                                    $this, 'ajax_save_feedback_reply' );
		$loader->add_action( 'wp_ajax_erdo_client_preview_annotation_reply',                                  $this, 'ajax_save_annotation_reply' );
		$loader->add_action( 'admin_bar_menu',                                                         $this, 'admin_bar_toggle', 100, 1 );
		$loader->add_action( 'update_option_' . Erdo_Client_Preview_Settings::OPTION_KEY,                $this, 'reschedule_cron', 10, 2 );
		$loader->add_filter( 'plugin_action_links_' . plugin_basename( ERDO_CLIENT_PREVIEW_PLUGIN_FILE ), $this, 'plugin_action_links', 10, 1 );
		$loader->add_filter( 'admin_footer_text',                                                      $this, 'whitelabel_footer', 100 );
		$loader->add_filter( 'site_status_tests',                                                      $this, 'register_site_health_tests' );
	}

	public function add_settings_page(): void {
		$name = $this->settings->get( 'whitelabel_enable' ) && $this->settings->get( 'whitelabel_name' )
			? $this->settings->get( 'whitelabel_name' )
			: __( 'Erdo Client Preview', 'erdo-client-preview' );
		add_menu_page(
			$name,
			__( 'Client Preview', 'erdo-client-preview' ),
			'manage_options',
			'erdo-client-preview',
			array( $this, 'render_page' ),
			'dashicons-visibility'
		);
	}

	public function whitelabel_footer( string $text ): string {
		if ( ! $this->settings->get( 'whitelabel_enable' ) ) {
			return $text;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'toplevel_page_erdo-client-preview' !== $screen->id ) {
			return $text;
		}
		$name = $this->settings->get( 'whitelabel_name' ) ?: get_bloginfo( 'name' );
		return esc_html( $name );
	}

	public function enqueue_assets( string $hook ): void {
		if ( 'toplevel_page_erdo-client-preview' !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style(
			'erdo-client-preview-admin',
			plugins_url( 'assets/css/admin.css', ERDO_CLIENT_PREVIEW_PLUGIN_FILE ),
			array( 'wp-color-picker' ),
			ERDO_CLIENT_PREVIEW_VERSION
		);
		wp_enqueue_script(
			'erdo-client-preview-admin',
			plugins_url( 'assets/js/admin.js', ERDO_CLIENT_PREVIEW_PLUGIN_FILE ),
			array( 'jquery', 'wp-color-picker', 'media-upload' ),
			ERDO_CLIENT_PREVIEW_VERSION,
			true
		);
		wp_localize_script( 'erdo-client-preview-admin', 'erdoClientPreviewAdmin', array(
			'mediaTitle'     => __( 'Choose Logo', 'erdo-client-preview' ),
			'mediaButton'    => __( 'Use This Image', 'erdo-client-preview' ),
			'mediaBgTitle'   => __( 'Choose Background Image', 'erdo-client-preview' ),
			'mediaBgButton'  => __( 'Use as Background', 'erdo-client-preview' ),
			'mediaWlTitle'   => __( 'Choose Brand Logo', 'erdo-client-preview' ),
			'mediaWlButton'  => __( 'Use as Brand Logo', 'erdo-client-preview' ),
			'detectedIp'     => $this->get_current_ip(),
			'copied'         => __( 'Copied!', 'erdo-client-preview' ),
			'remove'         => __( 'Remove', 'erdo-client-preview' ),
			'previewNonce'   => wp_create_nonce( 'em_preview_maintenance' ),
			'previewUrl'     => home_url( '/' ),
		) );

		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		if ( in_array( $tab, array( 'feedback', 'annotations' ), true ) ) {
			wp_enqueue_script(
				'erdo-client-preview-admin-feedback',
				plugins_url( 'assets/js/admin-feedback.js', ERDO_CLIENT_PREVIEW_PLUGIN_FILE ),
				array(),
				ERDO_CLIENT_PREVIEW_VERSION,
				true
			);
			wp_localize_script( 'erdo-client-preview-admin-feedback', 'erdoClientPreviewFeedbackAdmin', array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'erdo_client_preview_admin_reply' ),
				'i18n'    => array(
					'saving' => __( 'Saving…', 'erdo-client-preview' ),
					'saved'  => __( 'Saved.', 'erdo-client-preview' ),
					'error'  => __( 'An error occurred. Please try again.', 'erdo-client-preview' ),
				),
			) );
		}
	}

	// -------------------------------------------------------------------------
	// Settings page
	// -------------------------------------------------------------------------

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'erdo-client-preview' ) );
		}

		$cfg         = $this->settings->all();
		$links       = $this->token->get_all_links();
		$msg         = isset( $_GET['em_msg'] ) ? sanitize_key( $_GET['em_msg'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$rescue_url  = add_query_arg( 'em_rescue', $cfg['rescue_key'], home_url( '/' ) );
		$preview_url = add_query_arg( 'em_preview', wp_create_nonce( 'em_preview_maintenance' ), home_url( '/' ) );
		$key         = Erdo_Client_Preview_Settings::OPTION_KEY;
		$tab         = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification
		$base_url    = admin_url( 'admin.php?page=erdo-client-preview' );
		?>
		<div class="wrap sm-settings-wrap">
			<?php
			$wl_active = ! empty( $cfg['whitelabel_enable'] );
			$wl_name   = $wl_active && ! empty( $cfg['whitelabel_name'] ) ? $cfg['whitelabel_name'] : __( 'Erdo Client Preview', 'erdo-client-preview' );
			$wl_logo   = $wl_active && ! empty( $cfg['whitelabel_logo_url'] ) ? $cfg['whitelabel_logo_url'] : '';
			?>
			<h1>
				<?php if ( $wl_logo ) : ?>
					<img src="<?php echo esc_url( $wl_logo ); ?>" style="max-height:36px;vertical-align:middle;margin-right:6px" alt="">
				<?php endif; ?>
				<?php echo esc_html( $wl_name ); ?>
				<?php if ( $cfg['enabled'] ) : ?>
					<span class="sm-status-badge sm-status-badge--on"><?php esc_html_e( 'ACTIVE', 'erdo-client-preview' ); ?></span>
				<?php else : ?>
					<span class="sm-status-badge sm-status-badge--off"><?php esc_html_e( 'Inactive', 'erdo-client-preview' ); ?></span>
				<?php endif; ?>
				<a href="<?php echo esc_url( $preview_url ); ?>" target="_blank" class="button sm-preview-btn" style="margin-left:10px">
					👁 <?php esc_html_e( 'Preview Maintenance Page', 'erdo-client-preview' ); ?>
				</a>
				<?php if ( 'annotations' === $tab ) : ?>
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" class="button button-primary sm-h1-save">
						<?php esc_html_e( 'View Notes on Site', 'erdo-client-preview' ); ?>
					</a>
				<?php elseif ( 'feedback' !== $tab ) : ?>
					<button type="submit" form="erdo-client-preview-form" class="button button-primary sm-h1-save">
						<?php esc_html_e( 'Save Settings', 'erdo-client-preview' ); ?>
					</button>
				<?php endif; ?>
			</h1>

			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( $base_url ); ?>" class="nav-tab <?php echo ! in_array( $tab, array( 'feedback', 'annotations' ), true ) ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'erdo-client-preview' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'feedback', $base_url ) ); ?>" class="nav-tab <?php echo 'feedback' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php
					printf(
						/* translators: %d: number of feedback entries */
						esc_html__( 'Feedback (%d)', 'erdo-client-preview' ),
						(int) $this->db->get_feedback_count()
					);
					?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'annotations', $base_url ) ); ?>" class="nav-tab <?php echo 'annotations' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php
					printf(
						/* translators: %d: number of annotation entries */
						esc_html__( 'Annotations (%d)', 'erdo-client-preview' ),
						(int) $this->db->get_annotation_count()
					);
					?>
				</a>
			</h2>

			<?php
			$messages = array(
				'saved'                     => __( 'Settings saved.', 'erdo-client-preview' ),
				'created'                   => __( 'Magic link created.', 'erdo-client-preview' ),
				'revoked'                   => __( 'Magic link revoked.', 'erdo-client-preview' ),
				'deleted'                   => __( 'Magic link deleted.', 'erdo-client-preview' ),
				'rescued'                   => __( 'Maintenance mode has been disabled via emergency rescue link.', 'erdo-client-preview' ),
				'feedback_deleted'          => __( 'Feedback deleted.', 'erdo-client-preview' ),
				'feedback_status_updated'   => __( 'Feedback status updated.', 'erdo-client-preview' ),
				'annotation_deleted'        => __( 'Annotation deleted.', 'erdo-client-preview' ),
				'annotation_status_updated' => __( 'Annotation status updated.', 'erdo-client-preview' ),
				'settings_imported'         => __( 'Settings imported.', 'erdo-client-preview' ),
			);
			$error_messages = array(
				'settings_import_failed' => __( 'Could not import settings — the file was invalid or not exported from this plugin.', 'erdo-client-preview' ),
			);
			if ( $msg && isset( $messages[ $msg ] ) ) :
				?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html( $messages[ $msg ] ); ?></p>
				</div>
			<?php elseif ( $msg && isset( $error_messages[ $msg ] ) ) : ?>
				<div class="notice notice-error is-dismissible">
					<p><?php echo esc_html( $error_messages[ $msg ] ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( 'feedback' === $tab ) : ?>

				<?php
				$feedback_table = new Erdo_Client_Preview_Feedback_List( $this->db );
				$feedback_table->prepare_items();
				?>
				<form method="post">
					<input type="hidden" name="page" value="erdo-client-preview" />
					<input type="hidden" name="tab" value="feedback" />
					<?php $feedback_table->display(); ?>
				</form>

			<?php elseif ( 'annotations' === $tab ) : ?>

				<?php
				$annotation_table = new Erdo_Client_Preview_Annotation_List( $this->db );
				$annotation_table->prepare_items();
				?>
				<form method="post">
					<input type="hidden" name="page" value="erdo-client-preview" />
					<input type="hidden" name="tab" value="annotations" />
					<?php $annotation_table->display(); ?>
				</form>

			<?php else : ?>

			<div class="sm-layout">

				<!-- Left: Settings -->
				<div class="sm-col-main">
					<form method="post" action="options.php" id="erdo-client-preview-form">
						<?php settings_fields( 'erdo_client_preview' ); ?>

						<!-- Enable / Bypass -->
						<div class="sm-card">
							<h2><?php esc_html_e( 'Maintenance Mode', 'erdo-client-preview' ); ?></h2>
							<label class="sm-toggle-label">
								<span><?php esc_html_e( 'Enable maintenance mode', 'erdo-client-preview' ); ?></span>
								<div class="sm-toggle">
									<input type="checkbox" name="<?php echo esc_attr( $key ); ?>[enabled]" value="1" <?php checked( $cfg['enabled'] ); ?>>
									<span class="sm-toggle-slider"></span>
								</div>
							</label>
							<label class="sm-toggle-label">
								<span><?php esc_html_e( 'Allow administrators to bypass', 'erdo-client-preview' ); ?></span>
								<div class="sm-toggle">
									<input type="checkbox" name="<?php echo esc_attr( $key ); ?>[bypass_admins]" value="1" <?php checked( $cfg['bypass_admins'] ); ?>>
									<span class="sm-toggle-slider"></span>
								</div>
							</label>
						</div>

						<!-- Page Design -->
						<div class="sm-card">
							<h2><?php esc_html_e( 'Page Design', 'erdo-client-preview' ); ?></h2>

							<!-- Logo -->
							<div class="sm-field">
								<label><?php esc_html_e( 'Logo', 'erdo-client-preview' ); ?></label>
								<div class="sm-media-row">
									<input type="text" id="sm-logo-url" name="<?php echo esc_attr( $key ); ?>[logo_url]"
										value="<?php echo esc_attr( $cfg['logo_url'] ); ?>" class="regular-text">
									<button type="button" class="button" id="sm-logo-btn"><?php esc_html_e( 'Choose', 'erdo-client-preview' ); ?></button>
									<?php if ( $cfg['logo_url'] ) : ?>
										<button type="button" class="button sm-media-remove" data-target="#sm-logo-url"><?php esc_html_e( 'Remove', 'erdo-client-preview' ); ?></button>
									<?php endif; ?>
								</div>
								<?php if ( $cfg['logo_url'] ) : ?>
									<img src="<?php echo esc_url( $cfg['logo_url'] ); ?>" class="sm-logo-preview" alt="">
								<?php endif; ?>
							</div>

							<!-- Background image -->
							<div class="sm-field">
								<label><?php esc_html_e( 'Background Image', 'erdo-client-preview' ); ?></label>
								<div class="sm-media-row">
									<input type="text" id="sm-bg-image-url" name="<?php echo esc_attr( $key ); ?>[bg_image_url]"
										value="<?php echo esc_attr( $cfg['bg_image_url'] ); ?>" class="regular-text">
									<button type="button" class="button" id="sm-bg-image-btn"><?php esc_html_e( 'Choose', 'erdo-client-preview' ); ?></button>
									<?php if ( $cfg['bg_image_url'] ) : ?>
										<button type="button" class="button sm-media-remove" data-target="#sm-bg-image-url"><?php esc_html_e( 'Remove', 'erdo-client-preview' ); ?></button>
									<?php endif; ?>
								</div>
								<p class="description"><?php esc_html_e( 'If set, image overrides background color.', 'erdo-client-preview' ); ?></p>
							</div>

							<!-- Colors -->
							<div class="sm-field-row">
								<div class="sm-field">
									<label><?php esc_html_e( 'Background Color', 'erdo-client-preview' ); ?></label>
									<input type="text" name="<?php echo esc_attr( $key ); ?>[bg_color]"
										value="<?php echo esc_attr( $cfg['bg_color'] ); ?>" class="sm-color-picker" data-default-color="#1a1a2e">
								</div>
								<div class="sm-field">
									<label><?php esc_html_e( 'Text Color', 'erdo-client-preview' ); ?></label>
									<input type="text" name="<?php echo esc_attr( $key ); ?>[text_color]"
										value="<?php echo esc_attr( $cfg['text_color'] ); ?>" class="sm-color-picker" data-default-color="#ffffff">
								</div>
								<div class="sm-field">
									<label><?php esc_html_e( 'Accent Color', 'erdo-client-preview' ); ?></label>
									<input type="text" name="<?php echo esc_attr( $key ); ?>[accent_color]"
										value="<?php echo esc_attr( $cfg['accent_color'] ); ?>" class="sm-color-picker" data-default-color="#e94560">
								</div>
							</div>

							<!-- Heading & Message -->
							<div class="sm-field">
								<label><?php esc_html_e( 'Page Title', 'erdo-client-preview' ); ?></label>
								<input type="text" name="<?php echo esc_attr( $key ); ?>[page_title]"
									value="<?php echo esc_attr( $cfg['page_title'] ); ?>" class="large-text"
									placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
								<p class="description"><?php esc_html_e( 'Browser tab title. Defaults to site name if empty.', 'erdo-client-preview' ); ?></p>
							</div>
							<div class="sm-field">
								<label><?php esc_html_e( 'Heading', 'erdo-client-preview' ); ?></label>
								<input type="text" name="<?php echo esc_attr( $key ); ?>[heading]"
									value="<?php echo esc_attr( $cfg['heading'] ); ?>" class="large-text">
							</div>
							<div class="sm-field">
								<label><?php esc_html_e( 'Message', 'erdo-client-preview' ); ?></label>
								<textarea name="<?php echo esc_attr( $key ); ?>[message]" rows="3" class="large-text"><?php echo esc_textarea( $cfg['message'] ); ?></textarea>
							</div>
						</div>

						<!-- Social Links -->
						<div class="sm-card">
							<h2><?php esc_html_e( 'Social Links', 'erdo-client-preview' ); ?></h2>
							<p class="description" style="margin-bottom:12px"><?php esc_html_e( 'Icons appear on the maintenance page. Leave empty to hide.', 'erdo-client-preview' ); ?></p>
							<?php
							$socials = array(
								'twitter'   => array( 'label' => 'X / Twitter', 'placeholder' => 'https://x.com/yourhandle' ),
								'instagram' => array( 'label' => 'Instagram',   'placeholder' => 'https://instagram.com/yourhandle' ),
								'facebook'  => array( 'label' => 'Facebook',    'placeholder' => 'https://facebook.com/yourpage' ),
								'linkedin'  => array( 'label' => 'LinkedIn',    'placeholder' => 'https://linkedin.com/company/yourco' ),
								'youtube'   => array( 'label' => 'YouTube',     'placeholder' => 'https://youtube.com/c/yourchannel' ),
							);
							foreach ( $socials as $platform => $info ) :
								?>
								<div class="sm-field sm-social-field">
									<label><?php echo esc_html( $info['label'] ); ?></label>
									<input type="url" name="<?php echo esc_attr( $key ); ?>[social_<?php echo esc_attr( $platform ); ?>]"
										value="<?php echo esc_attr( $cfg[ 'social_' . $platform ] ?? '' ); ?>"
										placeholder="<?php echo esc_attr( $info['placeholder'] ); ?>"
										class="regular-text">
								</div>
							<?php endforeach; ?>
						</div>

						<!-- Countdown -->
						<div class="sm-card">
							<h2><?php esc_html_e( 'Countdown Timer', 'erdo-client-preview' ); ?></h2>
							<label class="sm-toggle-label">
								<span><?php esc_html_e( 'Show countdown', 'erdo-client-preview' ); ?></span>
								<div class="sm-toggle">
									<input type="checkbox" id="sm-countdown-toggle"
										name="<?php echo esc_attr( $key ); ?>[countdown_enable]"
										value="1" <?php checked( $cfg['countdown_enable'] ); ?>>
									<span class="sm-toggle-slider"></span>
								</div>
							</label>
							<div class="sm-field sm-countdown-field <?php echo $cfg['countdown_enable'] ? '' : 'sm-hidden'; ?>">
								<label><?php esc_html_e( 'Return date & time', 'erdo-client-preview' ); ?></label>
								<input type="datetime-local"
									name="<?php echo esc_attr( $key ); ?>[countdown_date]"
									value="<?php echo esc_attr( $cfg['countdown_date'] ); ?>"
									class="sm-datetime">
								<p class="description"><?php esc_html_e( 'Site timezone is used.', 'erdo-client-preview' ); ?></p>
							</div>
						</div>

						<!-- Access Control -->
						<div class="sm-card">
							<h2><?php esc_html_e( 'Access Control', 'erdo-client-preview' ); ?></h2>
							<div class="sm-field">
								<label><?php esc_html_e( 'IP Whitelist', 'erdo-client-preview' ); ?></label>
								<textarea name="<?php echo esc_attr( $key ); ?>[ip_whitelist]"
									rows="4" class="large-text"
									placeholder="<?php esc_attr_e( 'One IP address per line', 'erdo-client-preview' ); ?>"><?php echo esc_textarea( $cfg['ip_whitelist'] ); ?></textarea>
								<p class="description">
									<?php
									printf(
										/* translators: %s: current IP address */
										esc_html__( 'Your current IP: %s', 'erdo-client-preview' ),
										'<code>' . esc_html( $this->get_current_ip() ) . '</code>'
									);
									?>
									<button type="button" class="button button-small sm-add-ip" data-ip="<?php echo esc_attr( $this->get_current_ip() ); ?>">
										<?php esc_html_e( '+ Add My IP', 'erdo-client-preview' ); ?>
									</button>
								</p>
							</div>
						</div>

						<!-- Page Exclusions -->
						<div class="sm-card">
							<h2><?php esc_html_e( 'Page Exclusions', 'erdo-client-preview' ); ?></h2>
							<p class="description" style="margin-bottom:12px">
								<?php esc_html_e( 'Keep specific pages accessible even when maintenance mode is active.', 'erdo-client-preview' ); ?>
							</p>
							<label class="sm-toggle-label">
								<span><?php esc_html_e( 'Enable page exclusions', 'erdo-client-preview' ); ?></span>
								<div class="sm-toggle">
									<input type="checkbox" id="sm-page-excl-toggle"
										name="<?php echo esc_attr( $key ); ?>[page_exclusions_enable]"
										value="1" <?php checked( $cfg['page_exclusions_enable'] ?? false ); ?>>
									<span class="sm-toggle-slider"></span>
								</div>
							</label>
							<div class="sm-page-excl-fields <?php echo ! empty( $cfg['page_exclusions_enable'] ) ? '' : 'sm-hidden'; ?>" style="margin-top:12px">
								<div class="sm-field">
									<label><?php esc_html_e( 'Excluded URL paths', 'erdo-client-preview' ); ?></label>
									<textarea name="<?php echo esc_attr( $key ); ?>[excluded_paths]"
										rows="4" class="large-text"
										placeholder="<?php esc_attr_e( "/contact\n/about\n/shop", 'erdo-client-preview' ); ?>"><?php echo esc_textarea( $cfg['excluded_paths'] ?? '' ); ?></textarea>
									<p class="description"><?php esc_html_e( 'One URL path per line. /shop also covers /shop/cart and deeper paths.', 'erdo-client-preview' ); ?></p>
								</div>
								<div class="sm-field">
									<label><?php esc_html_e( 'Excluded post types', 'erdo-client-preview' ); ?></label>
									<textarea name="<?php echo esc_attr( $key ); ?>[excluded_post_types]"
										rows="3" class="large-text"
										placeholder="<?php esc_attr_e( "page\nproduct", 'erdo-client-preview' ); ?>"><?php echo esc_textarea( $cfg['excluded_post_types'] ?? '' ); ?></textarea>
									<p class="description"><?php esc_html_e( 'One post type slug per line (e.g. page, post, product).', 'erdo-client-preview' ); ?></p>
								</div>
							</div>
						</div>

						<!-- Emergency Access -->
						<div class="sm-card sm-card-danger">
							<h2>🚨 <?php esc_html_e( 'Emergency Access', 'erdo-client-preview' ); ?></h2>
							<p class="description"><?php esc_html_e( 'If you ever get locked out of the admin while maintenance mode is active, use this secret URL to disable it instantly — no login required.', 'erdo-client-preview' ); ?></p>
							<div class="sm-field" style="margin-top:12px">
								<div class="sm-media-row">
									<input type="text" readonly id="sm-rescue-url"
										value="<?php echo esc_attr( $rescue_url ); ?>" class="large-text sm-link-url">
									<button type="button" class="button sm-copy-btn" data-url="<?php echo esc_attr( $rescue_url ); ?>">
										<?php esc_html_e( 'Copy', 'erdo-client-preview' ); ?>
									</button>
								</div>
								<p class="description sm-danger-note">
									<?php esc_html_e( 'Keep this URL secret and store it somewhere safe (e.g. password manager).', 'erdo-client-preview' ); ?>
								</p>
							</div>
							<label class="sm-toggle-label" style="margin-top:8px">
								<span><?php esc_html_e( 'Regenerate rescue key on save', 'erdo-client-preview' ); ?></span>
								<div class="sm-toggle">
									<input type="checkbox" name="<?php echo esc_attr( $key ); ?>[regenerate_rescue_key]" value="1">
									<span class="sm-toggle-slider"></span>
								</div>
							</label>
						</div>

						<!-- Mode: Maintenance vs Coming Soon -->
						<div class="sm-card">
							<h2><?php esc_html_e( 'Mode', 'erdo-client-preview' ); ?></h2>
							<div class="sm-mode-row">
								<label class="sm-mode-option <?php echo 'maintenance' === $cfg['mode'] ? 'sm-mode-selected' : ''; ?>">
									<input type="radio" name="<?php echo esc_attr( $key ); ?>[mode]"
										value="maintenance" <?php checked( $cfg['mode'], 'maintenance' ); ?>>
									<span class="sm-mode-icon">🔧</span>
									<strong><?php esc_html_e( 'Maintenance', 'erdo-client-preview' ); ?></strong>
									<small><?php esc_html_e( 'HTTP 503 — tells search engines to come back later', 'erdo-client-preview' ); ?></small>
								</label>
								<label class="sm-mode-option <?php echo 'coming_soon' === $cfg['mode'] ? 'sm-mode-selected' : ''; ?>">
									<input type="radio" name="<?php echo esc_attr( $key ); ?>[mode]"
										value="coming_soon" <?php checked( $cfg['mode'], 'coming_soon' ); ?>>
									<span class="sm-mode-icon">🚀</span>
									<strong><?php esc_html_e( 'Coming Soon', 'erdo-client-preview' ); ?></strong>
									<small><?php esc_html_e( 'HTTP 200 — search engines can index the page', 'erdo-client-preview' ); ?></small>
								</label>
							</div>
						</div>

						<!-- Scheduled Maintenance -->
						<div class="sm-card">
							<h2><?php esc_html_e( 'Scheduled Maintenance', 'erdo-client-preview' ); ?></h2>
							<p class="description" style="margin-bottom:12px">
								<?php esc_html_e( 'Automatically enable maintenance mode within a time window — no need to stay up at midnight.', 'erdo-client-preview' ); ?>
							</p>
							<label class="sm-toggle-label">
								<span><?php esc_html_e( 'Enable schedule', 'erdo-client-preview' ); ?></span>
								<div class="sm-toggle">
									<input type="checkbox" id="sm-schedule-toggle"
										name="<?php echo esc_attr( $key ); ?>[schedule_enable]"
										value="1" <?php checked( $cfg['schedule_enable'] ); ?>>
									<span class="sm-toggle-slider"></span>
								</div>
							</label>
							<div class="sm-schedule-fields <?php echo $cfg['schedule_enable'] ? '' : 'sm-hidden'; ?>">
								<div class="sm-field-row" style="margin-top:12px">
									<div class="sm-field">
										<label><?php esc_html_e( 'Start', 'erdo-client-preview' ); ?></label>
										<input type="datetime-local"
											name="<?php echo esc_attr( $key ); ?>[schedule_start]"
											value="<?php echo esc_attr( $cfg['schedule_start'] ); ?>"
											class="sm-datetime">
									</div>
									<div class="sm-field">
										<label><?php esc_html_e( 'End', 'erdo-client-preview' ); ?></label>
										<input type="datetime-local"
											name="<?php echo esc_attr( $key ); ?>[schedule_end]"
											value="<?php echo esc_attr( $cfg['schedule_end'] ); ?>"
											class="sm-datetime">
									</div>
								</div>
								<p class="description"><?php esc_html_e( 'Site timezone is used. Manual toggle above takes priority.', 'erdo-client-preview' ); ?></p>
							</div>

							<!-- Recurring schedule -->
							<div style="margin-top:16px;border-top:1px solid #f0f0f0;padding-top:16px">
								<label class="sm-toggle-label">
									<span><?php esc_html_e( 'Recurring schedule (e.g. every Monday night)', 'erdo-client-preview' ); ?></span>
									<div class="sm-toggle">
										<input type="checkbox" id="sm-recurring-toggle"
											name="<?php echo esc_attr( $key ); ?>[recurring_enable]"
											value="1" <?php checked( $cfg['recurring_enable'] ?? false ); ?>>
										<span class="sm-toggle-slider"></span>
									</div>
								</label>
								<div class="sm-recurring-fields <?php echo ! empty( $cfg['recurring_enable'] ) ? '' : 'sm-hidden'; ?>" style="margin-top:12px">
									<div class="sm-field">
										<label><?php esc_html_e( 'Active days', 'erdo-client-preview' ); ?></label>
										<div class="sm-days-row">
											<?php
											$day_map    = array(
												'1' => __( 'Mon', 'erdo-client-preview' ),
												'2' => __( 'Tue', 'erdo-client-preview' ),
												'3' => __( 'Wed', 'erdo-client-preview' ),
												'4' => __( 'Thu', 'erdo-client-preview' ),
												'5' => __( 'Fri', 'erdo-client-preview' ),
												'6' => __( 'Sat', 'erdo-client-preview' ),
												'7' => __( 'Sun', 'erdo-client-preview' ),
											);
											$saved_days = (array) ( $cfg['recurring_days'] ?? array() );
											foreach ( $day_map as $num => $day_label ) :
												?>
												<label class="sm-day-label">
													<input type="checkbox"
														name="<?php echo esc_attr( $key ); ?>[recurring_days][]"
														value="<?php echo esc_attr( $num ); ?>"
														<?php checked( in_array( $num, $saved_days, true ) ); ?>>
													<?php echo esc_html( $day_label ); ?>
												</label>
											<?php endforeach; ?>
										</div>
									</div>
									<div class="sm-field-row" style="margin-top:8px">
										<div class="sm-field">
											<label><?php esc_html_e( 'Start time', 'erdo-client-preview' ); ?></label>
											<input type="time"
												name="<?php echo esc_attr( $key ); ?>[recurring_start_time]"
												value="<?php echo esc_attr( $cfg['recurring_start_time'] ?? '02:00' ); ?>"
												class="sm-time-input">
										</div>
										<div class="sm-field">
											<label><?php esc_html_e( 'End time', 'erdo-client-preview' ); ?></label>
											<input type="time"
												name="<?php echo esc_attr( $key ); ?>[recurring_end_time]"
												value="<?php echo esc_attr( $cfg['recurring_end_time'] ?? '04:00' ); ?>"
												class="sm-time-input">
										</div>
									</div>
									<p class="description"><?php esc_html_e( 'Activates automatically on selected days between the set times. Site timezone is used.', 'erdo-client-preview' ); ?></p>
								</div>
							</div>
						</div>

						<!-- Notification -->
						<div class="sm-card">
							<h2><?php esc_html_e( 'Magic Link Notification', 'erdo-client-preview' ); ?></h2>
							<label class="sm-toggle-label">
								<span><?php esc_html_e( 'Email me when a magic link is used', 'erdo-client-preview' ); ?></span>
								<div class="sm-toggle">
									<input type="checkbox" id="sm-notify-toggle"
										name="<?php echo esc_attr( $key ); ?>[notify_enable]"
										value="1" <?php checked( $cfg['notify_enable'] ); ?>>
									<span class="sm-toggle-slider"></span>
								</div>
							</label>
							<div class="sm-field sm-notify-field <?php echo $cfg['notify_enable'] ? '' : 'sm-hidden'; ?>" style="margin-top:12px">
								<label><?php esc_html_e( 'Notification email', 'erdo-client-preview' ); ?></label>
								<input type="email"
									name="<?php echo esc_attr( $key ); ?>[notify_email]"
									value="<?php echo esc_attr( $cfg['notify_email'] ?: get_option( 'admin_email' ) ); ?>"
									placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"
									class="regular-text">
								<p class="description"><?php esc_html_e( 'Defaults to site admin email if empty.', 'erdo-client-preview' ); ?></p>
							</div>
						</div>

						<!-- Email Subscription (Coming Soon) -->
						<div class="sm-card">
							<h2><?php esc_html_e( 'Email Subscription', 'erdo-client-preview' ); ?></h2>
							<p class="description" style="margin-bottom:12px">
								<?php esc_html_e( 'Show a subscribe form on the Coming Soon page. Emails are stored in WordPress and visible below.', 'erdo-client-preview' ); ?>
							</p>
							<label class="sm-toggle-label">
								<span><?php esc_html_e( 'Show subscription form (Coming Soon mode only)', 'erdo-client-preview' ); ?></span>
								<div class="sm-toggle">
									<input type="checkbox" name="<?php echo esc_attr( $key ); ?>[subscribe_enable]"
										value="1" <?php checked( $cfg['subscribe_enable'] ?? false ); ?>>
									<span class="sm-toggle-slider"></span>
								</div>
							</label>
							<div class="sm-field" style="margin-top:12px">
								<label><?php esc_html_e( 'Button label', 'erdo-client-preview' ); ?></label>
								<input type="text" name="<?php echo esc_attr( $key ); ?>[subscribe_label]"
									value="<?php echo esc_attr( $cfg['subscribe_label'] ?? 'Notify me when we launch' ); ?>"
									class="regular-text">
							</div>

							<!-- Visitor counter -->
							<div style="margin-top:16px;border-top:1px solid #f0f0f0;padding-top:16px">
								<label class="sm-toggle-label">
									<span><?php esc_html_e( 'Show visitor counter (Coming Soon mode)', 'erdo-client-preview' ); ?></span>
									<div class="sm-toggle">
										<input type="checkbox" name="<?php echo esc_attr( $key ); ?>[visitor_counter_enable]"
											value="1" <?php checked( $cfg['visitor_counter_enable'] ?? false ); ?>>
										<span class="sm-toggle-slider"></span>
									</div>
								</label>
								<p class="description" style="margin-top:4px">
									<?php esc_html_e( 'Shows subscriber count as social proof (e.g. "42 people are waiting"). Requires at least 1 subscriber.', 'erdo-client-preview' ); ?>
								</p>
								<div class="sm-field" style="margin-top:8px">
									<label><?php esc_html_e( 'Counter label', 'erdo-client-preview' ); ?></label>
									<input type="text" name="<?php echo esc_attr( $key ); ?>[visitor_counter_label]"
										value="<?php echo esc_attr( $cfg['visitor_counter_label'] ?? '%d people are waiting' ); ?>"
										class="regular-text"
										placeholder="%d people are waiting">
									<p class="description">
										<?php
										/* translators: %d: number of subscribers */
										esc_html_e( '%d is replaced with the subscriber count.', 'erdo-client-preview' );
										?>
									</p>
								</div>
							</div>
						</div>

						<!-- Visitor Feedback -->
						<div class="sm-card">
							<h2><?php esc_html_e( 'Visitor Feedback', 'erdo-client-preview' ); ?></h2>
							<p class="description" style="margin-bottom:12px">
								<?php esc_html_e( 'Show a feedback widget on the maintenance/coming soon page, and on the live site for visitors using a magic link, so they can leave a message.', 'erdo-client-preview' ); ?>
							</p>
							<label class="sm-toggle-label">
								<span><?php esc_html_e( 'Enable visitor feedback widget', 'erdo-client-preview' ); ?></span>
								<div class="sm-toggle">
									<input type="checkbox" name="<?php echo esc_attr( $key ); ?>[feedback_enable]"
										value="1" <?php checked( $cfg['feedback_enable'] ?? false ); ?>>
									<span class="sm-toggle-slider"></span>
								</div>
							</label>
							<p class="description" style="margin-top:8px">
								<?php
								printf(
									/* translators: %s: link to the Feedback tab */
									esc_html__( 'Submitted messages appear under the %s tab.', 'erdo-client-preview' ),
									'<a href="' . esc_url( add_query_arg( 'tab', 'feedback', $base_url ) ) . '">' . esc_html__( 'Feedback', 'erdo-client-preview' ) . '</a>'
								);
								?>
							</p>
						</div>

						<!-- Visual Annotations -->
						<div class="sm-card">
							<h2><?php esc_html_e( 'Visual Annotations', 'erdo-client-preview' ); ?></h2>
							<p class="description" style="margin-bottom:12px">
								<?php esc_html_e( 'Let visitors using a magic link click any element on the live site and pin a note about it directly on the page.', 'erdo-client-preview' ); ?>
							</p>
							<label class="sm-toggle-label">
								<span><?php esc_html_e( 'Enable visual annotations', 'erdo-client-preview' ); ?></span>
								<div class="sm-toggle">
									<input type="checkbox" name="<?php echo esc_attr( $key ); ?>[annotation_enable]"
										value="1" <?php checked( $cfg['annotation_enable'] ?? false ); ?>>
									<span class="sm-toggle-slider"></span>
								</div>
							</label>
							<p class="description" style="margin-top:8px">
								<?php
								printf(
									/* translators: %s: link to the Annotations tab */
									esc_html__( 'Submitted notes appear under the %s tab.', 'erdo-client-preview' ),
									'<a href="' . esc_url( add_query_arg( 'tab', 'annotations', $base_url ) ) . '">' . esc_html__( 'Annotations', 'erdo-client-preview' ) . '</a>'
								);
								?>
							</p>
						</div>

						<!-- White Label -->
						<div class="sm-card">
							<h2><?php esc_html_e( 'White Label', 'erdo-client-preview' ); ?></h2>
							<p class="description" style="margin-bottom:12px">
								<?php esc_html_e( 'Replace "Erdo Client Preview" with your agency name so clients see your brand.', 'erdo-client-preview' ); ?>
							</p>
							<label class="sm-toggle-label">
								<span><?php esc_html_e( 'Enable white label', 'erdo-client-preview' ); ?></span>
								<div class="sm-toggle">
									<input type="checkbox" id="sm-wl-toggle"
										name="<?php echo esc_attr( $key ); ?>[whitelabel_enable]"
										value="1" <?php checked( $cfg['whitelabel_enable'] ?? false ); ?>>
									<span class="sm-toggle-slider"></span>
								</div>
							</label>
							<div class="sm-wl-fields <?php echo ! empty( $cfg['whitelabel_enable'] ) ? '' : 'sm-hidden'; ?>" style="margin-top:12px">
								<div class="sm-field">
									<label><?php esc_html_e( 'Brand name', 'erdo-client-preview' ); ?></label>
									<input type="text"
										name="<?php echo esc_attr( $key ); ?>[whitelabel_name]"
										value="<?php echo esc_attr( $cfg['whitelabel_name'] ?? '' ); ?>"
										class="regular-text"
										placeholder="<?php esc_attr_e( 'Your Agency Name', 'erdo-client-preview' ); ?>">
									<p class="description"><?php esc_html_e( 'Replaces "Erdo Client Preview" in the settings page title and admin menu.', 'erdo-client-preview' ); ?></p>
								</div>
								<div class="sm-field">
									<label><?php esc_html_e( 'Brand logo', 'erdo-client-preview' ); ?></label>
									<div class="sm-media-row">
										<input type="text" id="sm-wl-logo-url"
											name="<?php echo esc_attr( $key ); ?>[whitelabel_logo_url]"
											value="<?php echo esc_attr( $cfg['whitelabel_logo_url'] ?? '' ); ?>"
											class="regular-text">
										<button type="button" class="button" id="sm-wl-logo-btn"><?php esc_html_e( 'Choose', 'erdo-client-preview' ); ?></button>
										<?php if ( ! empty( $cfg['whitelabel_logo_url'] ) ) : ?>
											<button type="button" class="button sm-media-remove" data-target="#sm-wl-logo-url"><?php esc_html_e( 'Remove', 'erdo-client-preview' ); ?></button>
										<?php endif; ?>
									</div>
									<?php if ( ! empty( $cfg['whitelabel_logo_url'] ) ) : ?>
										<img src="<?php echo esc_url( $cfg['whitelabel_logo_url'] ); ?>" class="sm-logo-preview" alt="" style="margin-top:8px">
									<?php endif; ?>
									<p class="description"><?php esc_html_e( 'Displayed in the settings page header instead of the plugin name.', 'erdo-client-preview' ); ?></p>
								</div>
							</div>
						</div>

						<?php submit_button( __( 'Save Settings', 'erdo-client-preview' ) ); ?>
					</form>
				</div>

				<!-- Right: Magic Links sidebar -->
				<div class="sm-col-side">
					<div class="sm-card">
						<h2><?php esc_html_e( 'Magic Links', 'erdo-client-preview' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Share a magic link to let someone bypass the maintenance page without logging in.', 'erdo-client-preview' ); ?></p>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sm-new-link-form">
							<?php wp_nonce_field( 'em_new_link' ); ?>
							<input type="hidden" name="action" value="em_new_link">
							<div class="sm-field">
								<input type="text" name="em_label"
									placeholder="<?php esc_attr_e( 'Label (e.g. Client Name)', 'erdo-client-preview' ); ?>"
									class="regular-text">
							</div>
							<div class="sm-field">
								<select name="em_expires">
									<option value=""><?php esc_html_e( 'Never expires', 'erdo-client-preview' ); ?></option>
									<option value="+24 hours"><?php esc_html_e( '24 hours', 'erdo-client-preview' ); ?></option>
									<option value="+48 hours"><?php esc_html_e( '48 hours', 'erdo-client-preview' ); ?></option>
									<option value="+7 days"><?php esc_html_e( '7 days', 'erdo-client-preview' ); ?></option>
									<option value="+30 days"><?php esc_html_e( '30 days', 'erdo-client-preview' ); ?></option>
								</select>
							</div>
							<div class="sm-field">
								<input type="url" name="em_redirect_url"
									placeholder="<?php esc_attr_e( 'Redirect URL (optional, defaults to home)', 'erdo-client-preview' ); ?>"
									class="regular-text">
								<p class="description"><?php esc_html_e( 'Where to send the visitor after clicking the magic link.', 'erdo-client-preview' ); ?></p>
							</div>
							<button type="submit" class="button button-primary">
								<?php esc_html_e( '+ Generate Magic Link', 'erdo-client-preview' ); ?>
							</button>
						</form>

						<?php if ( ! empty( $links ) ) : ?>
							<div class="sm-links-list">
								<?php foreach ( array_reverse( $links ) as $link ) :
									$is_expired = $this->token->is_expired( $link['expires_at'] ?? null );
									$is_active  = $link['is_active'] && ! $is_expired;
									$url        = $this->token->build_url( $link['token_raw'] );
									?>
									<div class="sm-link-item <?php echo $is_active ? 'sm-link-active' : 'sm-link-inactive'; ?>">
										<div class="sm-link-header">
											<span class="sm-link-label">
												<?php echo esc_html( $link['label'] ?: __( '(no label)', 'erdo-client-preview' ) ); ?>
											</span>
											<span class="sm-link-badge <?php echo $is_active ? 'sm-badge-active' : 'sm-badge-inactive'; ?>">
												<?php echo $is_active ? esc_html__( 'Active', 'erdo-client-preview' ) : esc_html__( 'Inactive', 'erdo-client-preview' ); ?>
											</span>
										</div>

										<?php if ( $is_active ) : ?>
											<div class="sm-link-url-row">
												<input type="text" readonly value="<?php echo esc_attr( $url ); ?>" class="sm-link-url">
												<button type="button" class="button sm-copy-btn" data-url="<?php echo esc_attr( $url ); ?>">
													<?php esc_html_e( 'Copy', 'erdo-client-preview' ); ?>
												</button>
											</div>
										<?php endif; ?>

										<div class="sm-link-meta">
											<?php if ( $link['expires_at'] ) : ?>
												<?php if ( $is_expired ) : ?>
													<span class="sm-expired"><?php esc_html_e( 'Expired', 'erdo-client-preview' ); ?></span>
												<?php else : ?>
													<span>
													<?php
													printf(
														/* translators: %s: human-readable time */
														esc_html__( 'Expires in %s', 'erdo-client-preview' ),
														esc_html( human_time_diff( strtotime( $link['expires_at'] ), time() ) )
													);
													?>
													</span>
												<?php endif; ?>
											<?php else : ?>
												<span><?php esc_html_e( 'No expiry', 'erdo-client-preview' ); ?></span>
											<?php endif; ?>
											<span class="sm-views">
											<?php
											printf(
												/* translators: %d: view count */
												esc_html__( '%d view(s)', 'erdo-client-preview' ),
												(int) $link['view_count']
											);
											?>
											</span>
										</div>

										<?php
										$last_access = $this->token->get_last_access( $link );
										$access_log  = array_reverse( (array) ( $link['access_log'] ?? array() ) );
										?>
										<?php if ( $last_access ) : ?>
											<div class="sm-link-last-access">
												<?php
												printf(
													/* translators: %1$s: human-readable time since last access (e.g. "2 hours"), %2$s: visitor IP address */
													esc_html__( 'Last seen %1$s ago — %2$s', 'erdo-client-preview' ),
													esc_html( human_time_diff( strtotime( $last_access['time'] ), time() ) ),
													esc_html( $last_access['ip'] )
												);
												?>
											</div>
											<?php if ( count( $access_log ) > 1 ) : ?>
												<details class="sm-access-log">
													<summary>
													<?php
													printf(
														/* translators: %d: number of recorded accesses */
														esc_html__( 'Access History (%d)', 'erdo-client-preview' ),
														count( $access_log )
													);
													?>
													</summary>
													<ul>
														<?php foreach ( $access_log as $entry ) : ?>
															<li>
																<?php
																echo esc_html(
																	wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry['time'] ) ) . ' — ' . $entry['ip']
																);
																?>
															</li>
														<?php endforeach; ?>
													</ul>
												</details>
											<?php endif; ?>
										<?php endif; ?>

										<div class="sm-link-actions">
											<?php if ( $link['is_active'] && ! $is_expired ) : ?>
												<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
													<?php wp_nonce_field( 'em_revoke_' . $link['id'] ); ?>
													<input type="hidden" name="action" value="em_revoke">
													<input type="hidden" name="em_link_id" value="<?php echo esc_attr( $link['id'] ); ?>">
													<button type="submit" class="button button-small"><?php esc_html_e( 'Revoke', 'erdo-client-preview' ); ?></button>
												</form>
											<?php endif; ?>
											<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
												<?php wp_nonce_field( 'em_delete_' . $link['id'] ); ?>
												<input type="hidden" name="action" value="em_delete">
												<input type="hidden" name="em_link_id" value="<?php echo esc_attr( $link['id'] ); ?>">
												<button type="submit" class="button button-small sm-delete-btn"
													onclick="return confirm('<?php echo esc_js( __( 'Delete this magic link?', 'erdo-client-preview' ) ); ?>')">
													<?php esc_html_e( 'Delete', 'erdo-client-preview' ); ?>
												</button>
											</form>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>

					<?php
					$subscribers = (array) get_option( Erdo_Client_Preview_Frontend::SUBSCRIBERS_KEY, array() );
					if ( ! empty( $subscribers ) ) : ?>
					<div class="sm-card">
						<h2>
							<?php
							printf(
								/* translators: %d: subscriber count */
								esc_html__( 'Subscribers (%d)', 'erdo-client-preview' ),
								count( $subscribers )
							);
							?>
						</h2>
						<div class="sm-subscriber-list">
							<?php foreach ( array_reverse( $subscribers ) as $sub ) : ?>
								<div class="sm-subscriber-row">
									<span class="sm-subscriber-email"><?php echo esc_html( $sub['email'] ); ?></span>
									<span class="sm-subscriber-date"><?php echo esc_html( $sub['created_at'] ?? '' ); ?></span>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
					<?php endif; ?>

					<!-- Import / Export Settings -->
					<div class="sm-card">
						<h2><?php esc_html_e( 'Import / Export Settings', 'erdo-client-preview' ); ?></h2>
						<p class="description" style="margin-bottom:12px">
							<?php esc_html_e( 'Copy this configuration to another site — handy when you manage several client sites with the same setup.', 'erdo-client-preview' ); ?>
						</p>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:16px">
							<?php wp_nonce_field( 'erdo_client_preview_export_settings' ); ?>
							<input type="hidden" name="action" value="erdo_client_preview_export_settings">
							<button type="submit" class="button"><?php esc_html_e( 'Export Settings (.json)', 'erdo-client-preview' ); ?></button>
						</form>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
							<?php wp_nonce_field( 'erdo_client_preview_import_settings' ); ?>
							<input type="hidden" name="action" value="erdo_client_preview_import_settings">
							<div class="sm-field">
								<input type="file" name="erdo_client_preview_import_file" accept="application/json,.json" required>
							</div>
							<p class="description"><?php esc_html_e( 'The emergency rescue key is site-specific and is never exported or overwritten by an import.', 'erdo-client-preview' ); ?></p>
							<button type="submit" class="button button-primary"
								onclick="return confirm('<?php echo esc_js( __( 'This will overwrite your current settings. Continue?', 'erdo-client-preview' ) ); ?>')">
								<?php esc_html_e( 'Import Settings', 'erdo-client-preview' ); ?>
							</button>
						</form>
					</div>

				</div>

			</div>

			<?php endif; ?>

		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Form handlers
	// -------------------------------------------------------------------------

	public function handle_new_link(): void {
		check_admin_referer( 'em_new_link' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'erdo-client-preview' ) );
		}
		$label           = sanitize_text_field( wp_unslash( $_POST['em_label'] ?? '' ) );
		$allowed_expires = array( '', '+24 hours', '+48 hours', '+7 days', '+30 days' );
		$expires_raw     = sanitize_text_field( wp_unslash( $_POST['em_expires'] ?? '' ) );
		$expires         = in_array( $expires_raw, $allowed_expires, true ) ? $expires_raw : '';
		$expires_at      = $expires ? gmdate( 'Y-m-d H:i:s', strtotime( $expires, time() ) ) : null;
		$redirect_url    = esc_url_raw( wp_unslash( $_POST['em_redirect_url'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$this->token->generate( $label, $expires_at, $redirect_url );
		wp_safe_redirect( add_query_arg( 'em_msg', 'created', admin_url( 'admin.php?page=erdo-client-preview' ) ) );
		exit;
	}

	public function handle_revoke(): void {
		$id = sanitize_text_field( wp_unslash( $_POST['em_link_id'] ?? '' ) );
		check_admin_referer( 'em_revoke_' . $id );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'erdo-client-preview' ) );
		}
		$this->token->revoke( $id );
		wp_safe_redirect( add_query_arg( 'em_msg', 'revoked', admin_url( 'admin.php?page=erdo-client-preview' ) ) );
		exit;
	}

	public function handle_delete(): void {
		$id = sanitize_text_field( wp_unslash( $_POST['em_link_id'] ?? '' ) );
		check_admin_referer( 'em_delete_' . $id );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'erdo-client-preview' ) );
		}
		$this->token->delete( $id );
		wp_safe_redirect( add_query_arg( 'em_msg', 'deleted', admin_url( 'admin.php?page=erdo-client-preview' ) ) );
		exit;
	}

	/**
	 * Reads the bulk action selected in a WP_List_Table form (top or bottom
	 * "Bulk actions" dropdown). Returns '' if none was selected.
	 */
	private function get_bulk_action(): string {
		$action  = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$action2 = isset( $_POST['action2'] ) ? sanitize_key( wp_unslash( $_POST['action2'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		if ( $action && '-1' !== $action ) {
			return $action;
		}

		if ( $action2 && '-1' !== $action2 ) {
			return $action2;
		}

		return '';
	}

	public function handle_feedback_actions(): void {
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$page   = 'admin.php?page=erdo-client-preview';

		if ( 'erdo_client_preview_feedback_delete' === $action ) {
			$feedback_id = isset( $_GET['feedback_id'] ) ? absint( $_GET['feedback_id'] ) : 0;
			if ( ! $feedback_id ) {
				return;
			}
			check_admin_referer( 'erdo_client_preview_feedback_delete_' . $feedback_id );
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Unauthorized.', 'erdo-client-preview' ) );
			}
			$this->db->delete_feedback( $feedback_id );
			wp_safe_redirect( add_query_arg( array( 'tab' => 'feedback', 'em_msg' => 'feedback_deleted' ), admin_url( $page ) ) );
			exit;
		}

		if ( 'erdo_client_preview_feedback_status' === $action ) {
			$feedback_id = isset( $_GET['feedback_id'] ) ? absint( $_GET['feedback_id'] ) : 0;
			$status      = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			if ( ! $feedback_id ) {
				return;
			}
			check_admin_referer( 'erdo_client_preview_feedback_status_' . $feedback_id );
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Unauthorized.', 'erdo-client-preview' ) );
			}
			$this->db->update_feedback_status( $feedback_id, $status );
			wp_safe_redirect( add_query_arg( array( 'tab' => 'feedback', 'em_msg' => 'feedback_status_updated' ), admin_url( $page ) ) );
			exit;
		}

		$bulk_action = $this->get_bulk_action();

		if ( $bulk_action && isset( $_POST['feedback_ids'] ) && is_array( $_POST['feedback_ids'] ) ) {
			check_admin_referer( 'bulk-erdo_client_preview_feedbacks' );
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Unauthorized.', 'erdo-client-preview' ) );
			}

			$ids = array_map( 'absint', wp_unslash( $_POST['feedback_ids'] ) );
			$msg = '';

			switch ( $bulk_action ) {
				case 'delete':
					$this->db->delete_feedback_bulk( $ids );
					$msg = 'feedback_deleted';
					break;
				case 'mark_completed':
					$this->db->update_feedback_status_bulk( $ids, Erdo_Client_Preview_DB::FEEDBACK_STATUS_DONE );
					$msg = 'feedback_status_updated';
					break;
				case 'mark_in_progress':
					$this->db->update_feedback_status_bulk( $ids, Erdo_Client_Preview_DB::FEEDBACK_STATUS_OPEN );
					$msg = 'feedback_status_updated';
					break;
			}

			wp_safe_redirect( add_query_arg( array( 'tab' => 'feedback', 'em_msg' => $msg ), admin_url( $page ) ) );
			exit;
		}
	}

	public function handle_annotation_actions(): void {
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$page   = 'admin.php?page=erdo-client-preview';

		if ( 'erdo_client_preview_annotation_delete' === $action ) {
			$annotation_id = isset( $_GET['annotation_id'] ) ? absint( $_GET['annotation_id'] ) : 0;
			if ( ! $annotation_id ) {
				return;
			}
			check_admin_referer( 'erdo_client_preview_annotation_delete_' . $annotation_id );
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Unauthorized.', 'erdo-client-preview' ) );
			}
			$this->db->delete_annotation( $annotation_id );
			wp_safe_redirect( add_query_arg( array( 'tab' => 'annotations', 'em_msg' => 'annotation_deleted' ), admin_url( $page ) ) );
			exit;
		}

		if ( 'erdo_client_preview_annotation_status' === $action ) {
			$annotation_id = isset( $_GET['annotation_id'] ) ? absint( $_GET['annotation_id'] ) : 0;
			$status        = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			if ( ! $annotation_id ) {
				return;
			}
			check_admin_referer( 'erdo_client_preview_annotation_status_' . $annotation_id );
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Unauthorized.', 'erdo-client-preview' ) );
			}
			$this->db->update_annotation_status( $annotation_id, $status );
			wp_safe_redirect( add_query_arg( array( 'tab' => 'annotations', 'em_msg' => 'annotation_status_updated' ), admin_url( $page ) ) );
			exit;
		}

		$bulk_action = $this->get_bulk_action();

		if ( $bulk_action && isset( $_POST['annotation_ids'] ) && is_array( $_POST['annotation_ids'] ) ) {
			check_admin_referer( 'bulk-erdo_client_preview_annotations' );
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Unauthorized.', 'erdo-client-preview' ) );
			}

			$ids = array_map( 'absint', wp_unslash( $_POST['annotation_ids'] ) );
			$msg = '';

			switch ( $bulk_action ) {
				case 'delete':
					$this->db->delete_annotation_bulk( $ids );
					$msg = 'annotation_deleted';
					break;
				case 'mark_completed':
					$this->db->update_annotation_status_bulk( $ids, Erdo_Client_Preview_DB::FEEDBACK_STATUS_DONE );
					$msg = 'annotation_status_updated';
					break;
				case 'mark_in_progress':
					$this->db->update_annotation_status_bulk( $ids, Erdo_Client_Preview_DB::FEEDBACK_STATUS_OPEN );
					$msg = 'annotation_status_updated';
					break;
			}

			wp_safe_redirect( add_query_arg( array( 'tab' => 'annotations', 'em_msg' => $msg ), admin_url( $page ) ) );
			exit;
		}
	}

	public function ajax_save_feedback_reply(): void {
		check_ajax_referer( 'erdo_client_preview_admin_reply' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'erdo-client-preview' ) ), 403 );
		}

		$item_id = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
		$reply   = isset( $_POST['reply'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reply'] ) ) : '';

		if ( ! $item_id ) {
			wp_send_json_error( array( 'message' => __( 'An error occurred. Please try again.', 'erdo-client-preview' ) ), 400 );
		}

		$this->db->update_feedback_reply( $item_id, $reply );

		wp_send_json_success();
	}

	public function ajax_save_annotation_reply(): void {
		check_ajax_referer( 'erdo_client_preview_admin_reply' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'erdo-client-preview' ) ), 403 );
		}

		$item_id = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
		$reply   = isset( $_POST['reply'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reply'] ) ) : '';

		if ( ! $item_id ) {
			wp_send_json_error( array( 'message' => __( 'An error occurred. Please try again.', 'erdo-client-preview' ) ), 400 );
		}

		$this->db->update_annotation_reply( $item_id, $reply );

		wp_send_json_success();
	}

	// -------------------------------------------------------------------------
	// Admin bar
	// -------------------------------------------------------------------------

	public function admin_bar_toggle( WP_Admin_Bar $bar ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$active = $this->settings->is_active();
		$mode   = $this->settings->get_mode();
		$label  = 'coming_soon' === $mode
			? esc_html__( 'Coming Soon', 'erdo-client-preview' )
			: esc_html__( 'Maintenance', 'erdo-client-preview' );
		$bar->add_node( array(
			'id'    => 'erdo-client-preview',
			'title' => $active
				? '<span style="color:#ff4444">● ' . $label . ' ON</span>'
				: '<span style="color:#46b450">● ' . $label . ' OFF</span>',
			'href'  => admin_url( 'admin.php?page=erdo-client-preview' ),
			'meta'  => array( 'title' => __( 'Erdo Client Preview Settings', 'erdo-client-preview' ) ),
		) );
		$bar->add_node( array(
			'id'     => 'erdo-client-preview-toggle',
			'parent' => 'erdo-client-preview',
			'title'  => $active
				? esc_html__( '⏹ Turn OFF', 'erdo-client-preview' )
				: esc_html__( '▶ Turn ON', 'erdo-client-preview' ),
			'href'   => wp_nonce_url( admin_url( 'admin-post.php?action=em_toggle' ), 'em_toggle' ),
		) );
		$bar->add_node( array(
			'id'     => 'erdo-client-preview-settings',
			'parent' => 'erdo-client-preview',
			'title'  => esc_html__( '⚙ Settings', 'erdo-client-preview' ),
			'href'   => admin_url( 'admin.php?page=erdo-client-preview' ),
		) );
	}

	public function handle_toggle(): void {
		check_admin_referer( 'em_toggle' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'erdo-client-preview' ) );
		}
		$settings            = (array) get_option( Erdo_Client_Preview_Settings::OPTION_KEY, array() );
		$settings['enabled'] = ! $this->settings->is_enabled();
		update_option( Erdo_Client_Preview_Settings::OPTION_KEY, $settings );
		$redirect = wp_get_referer() ?: admin_url( 'admin.php?page=erdo-client-preview' );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Downloads the current settings as a JSON file so they can be re-imported
	 * on another site (e.g. rolling out the same setup across client sites).
	 * The rescue key is deliberately excluded — it's a site-specific secret.
	 */
	public function handle_export_settings(): void {
		check_admin_referer( 'erdo_client_preview_export_settings' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'erdo-client-preview' ) );
		}

		$settings = $this->settings->all();
		unset( $settings['rescue_key'] );

		$payload = array(
			'plugin'      => 'erdo-client-preview',
			'version'     => ERDO_CLIENT_PREVIEW_VERSION,
			'exported_at' => gmdate( 'c' ),
			'settings'    => $settings,
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="erdo-client-preview-settings-' . gmdate( 'Y-m-d' ) . '.json"' );
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Restores settings from a previously exported JSON file. Runs the same
	 * sanitize() whitelist used when saving the form normally, so an import
	 * can't introduce a field the settings page itself wouldn't allow — and
	 * sanitize() always preserves this site's own rescue key regardless of
	 * what (if anything) the imported file contains for it.
	 */
	public function handle_import_settings(): void {
		check_admin_referer( 'erdo_client_preview_import_settings' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'erdo-client-preview' ) );
		}

		$redirect = admin_url( 'admin.php?page=erdo-client-preview' );

		$has_file = isset( $_FILES['erdo_client_preview_import_file']['tmp_name'], $_FILES['erdo_client_preview_import_file']['error'] )
			&& UPLOAD_ERR_OK === $_FILES['erdo_client_preview_import_file']['error'];

		if ( ! $has_file ) {
			wp_safe_redirect( add_query_arg( 'em_msg', 'settings_import_failed', $redirect ) );
			exit;
		}

		$tmp_name = sanitize_text_field( wp_unslash( $_FILES['erdo_client_preview_import_file']['tmp_name'] ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		$raw  = file_get_contents( $tmp_name );
		$data = json_decode( (string) $raw, true );

		if ( ! is_array( $data ) || 'erdo-client-preview' !== ( $data['plugin'] ?? '' ) || ! is_array( $data['settings'] ?? null ) ) {
			wp_safe_redirect( add_query_arg( 'em_msg', 'settings_import_failed', $redirect ) );
			exit;
		}

		$sanitized = $this->settings->sanitize( $data['settings'] );
		update_option( Erdo_Client_Preview_Settings::OPTION_KEY, $sanitized );

		wp_safe_redirect( add_query_arg( 'em_msg', 'settings_imported', $redirect ) );
		exit;
	}

	public function reschedule_cron( $old_value, $new_value ): void {
		wp_clear_scheduled_hook( 'erdo_client_preview_schedule_end' );
		if ( ! empty( $new_value['schedule_enable'] ) && ! empty( $new_value['schedule_end'] ) ) {
			$end_ts = ( new DateTime( $new_value['schedule_end'], wp_timezone() ) )->getTimestamp();
			if ( $end_ts > time() ) {
				wp_schedule_single_event( $end_ts, 'erdo_client_preview_schedule_end' );
			}
		}
	}

	public function plugin_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=erdo-client-preview' ) ),
			esc_html__( 'Settings', 'erdo-client-preview' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	private function get_current_ip(): string {
		return isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';
	}

	// -------------------------------------------------------------------------
	// Site Health
	// -------------------------------------------------------------------------

	public function register_site_health_tests( array $tests ): array {
		$tests['direct']['erdo_client_preview_auth_keys'] = array(
			'label' => __( 'Erdo Client Preview: magic link signing keys', 'erdo-client-preview' ),
			'test'  => array( $this, 'site_health_test_auth_keys' ),
		);
		return $tests;
	}

	/**
	 * Magic link tokens and the bypass cookie are HMAC-signed using AUTH_KEY
	 * and SECURE_AUTH_KEY (see Erdo_Client_Preview_Token). If either is left
	 * at its wp-config-sample.php placeholder, empty, or identical to the
	 * other, that signature is far weaker than it looks — flag it here
	 * rather than silently trusting whatever wp-config.php happens to have.
	 */
	public function site_health_test_auth_keys(): array {
		$result = array(
			'label'       => __( 'Magic link signing keys are properly configured', 'erdo-client-preview' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Security', 'erdo-client-preview' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'Erdo Client Preview signs magic link tokens and the bypass cookie using your site’s AUTH_KEY and SECURE_AUTH_KEY. Both are defined and look like unique secrets.', 'erdo-client-preview' )
			),
			'actions'     => '',
			'test'        => 'erdo_client_preview_auth_keys',
		);

		$problem = $this->weak_auth_key_message();

		if ( '' !== $problem ) {
			$result['status']         = 'critical';
			$result['label']          = __( 'Magic link signing keys need attention', 'erdo-client-preview' );
			$result['badge']['color'] = 'red';
			$result['description']    = '<p>' . esc_html( $problem ) . '</p>';
			$result['actions']        = sprintf(
				'<p><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
				esc_url( 'https://developer.wordpress.org/apis/wp-config-php/#security-keys' ),
				esc_html__( 'Learn how to generate new security keys', 'erdo-client-preview' )
			);
		}

		return $result;
	}

	/**
	 * @return string Empty string if the keys look fine, otherwise a
	 *                human-readable description of the problem.
	 */
	private function weak_auth_key_message(): string {
		if ( ! defined( 'AUTH_KEY' ) || ! defined( 'SECURE_AUTH_KEY' ) ) {
			return __( 'AUTH_KEY and/or SECURE_AUTH_KEY are not defined in wp-config.php. Erdo Client Preview needs both to securely sign magic link tokens and the bypass cookie.', 'erdo-client-preview' );
		}

		$placeholder = 'put your unique phrase here';

		if ( '' === AUTH_KEY || '' === SECURE_AUTH_KEY || $placeholder === AUTH_KEY || $placeholder === SECURE_AUTH_KEY ) {
			return __( 'AUTH_KEY and/or SECURE_AUTH_KEY are still empty or set to the wp-config-sample.php placeholder. Magic links and the bypass cookie rely on these being unique, random secrets — replace them with values from the WordPress.org secret-key generator.', 'erdo-client-preview' );
		}

		if ( AUTH_KEY === SECURE_AUTH_KEY ) {
			return __( 'AUTH_KEY and SECURE_AUTH_KEY are set to the same value in wp-config.php. They should each be a distinct random secret — replace one of them with a fresh value from the WordPress.org secret-key generator.', 'erdo-client-preview' );
		}

		return '';
	}
}
