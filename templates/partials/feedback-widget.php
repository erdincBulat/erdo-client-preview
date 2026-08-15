<?php
defined( 'ABSPATH' ) || exit;
// Shared markup for the visitor feedback widget, included both on the
// maintenance/coming soon page (templates/maintenance.php) and on the live
// site for magic-link bypass visitors (Erdo_Client_Preview_Frontend::render_live_site_feedback_widget()).
/* phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound */
$sm_feedback_sent = isset( $sm_feedback_sent ) ? (bool) $sm_feedback_sent : false;
/* phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound */
?>
<div class="erdo-client-preview-feedback">
	<button type="button" class="erdo-client-preview-feedback-toggle" aria-expanded="false" aria-controls="erdo-client-preview-feedback-panel">
		<svg class="erdo-client-preview-feedback-toggle-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
			<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
		</svg>
		<span class="erdo-client-preview-feedback-toggle-label"><?php esc_html_e( 'Feedback', 'erdo-client-preview' ); ?></span>
	</button>
	<div class="erdo-client-preview-feedback-panel" id="erdo-client-preview-feedback-panel" hidden <?php echo $sm_feedback_sent ? 'data-auto-open="1"' : ''; ?>>
		<div class="erdo-client-preview-feedback-header">
			<div class="erdo-client-preview-feedback-heading">
				<h3 class="erdo-client-preview-feedback-title"><?php esc_html_e( 'Leave Feedback', 'erdo-client-preview' ); ?></h3>
				<p class="erdo-client-preview-feedback-description">
					<?php esc_html_e( 'Have a question or comment? Let us know below.', 'erdo-client-preview' ); ?>
				</p>
			</div>
			<button type="button" class="erdo-client-preview-feedback-close" aria-label="<?php esc_attr_e( 'Close', 'erdo-client-preview' ); ?>">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
					<path d="M18 6 6 18"></path>
					<path d="m6 6 12 12"></path>
				</svg>
			</button>
		</div>
		<div class="erdo-client-preview-feedback-body">
			<div class="erdo-client-preview-feedback-notice" id="erdo-client-preview-feedback-notice">
				<?php if ( $sm_feedback_sent ) : ?>
					<div class="erdo-client-preview-feedback-success" role="status">
						<svg class="erdo-client-preview-feedback-success-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
							<path d="M20 6 9 17l-5-5"></path>
						</svg>
						<span><?php esc_html_e( 'Thanks! Your feedback has been sent.', 'erdo-client-preview' ); ?></span>
					</div>
				<?php endif; ?>
			</div>

			<form method="post" class="erdo-client-preview-feedback-form" id="erdo-client-preview-feedback-form">
				<?php wp_nonce_field( 'erdo_client_preview_feedback', 'erdo_feedback_nonce' ); ?>
				<p class="erdo-client-preview-feedback-field">
					<label for="erdo-client-preview-feedback-name"><?php esc_html_e( 'Name', 'erdo-client-preview' ); ?></label>
					<input type="text" id="erdo-client-preview-feedback-name" name="erdo_feedback_name" maxlength="100" required="required" />
				</p>
				<p class="erdo-client-preview-feedback-field">
					<label for="erdo-client-preview-feedback-message"><?php esc_html_e( 'Your feedback', 'erdo-client-preview' ); ?></label>
					<textarea id="erdo-client-preview-feedback-message" name="erdo_feedback_message" rows="4" maxlength="2000" required="required"></textarea>
				</p>
				<p>
					<button type="submit" name="erdo_client_preview_feedback_submit" value="1" class="erdo-client-preview-feedback-submit">
						<span class="erdo-client-preview-feedback-submit-label"><?php esc_html_e( 'Send Feedback', 'erdo-client-preview' ); ?></span>
					</button>
				</p>
			</form>

			<div class="erdo-client-preview-feedback-history" id="erdo-client-preview-feedback-history" hidden>
				<h4 class="erdo-client-preview-feedback-history-title"><?php esc_html_e( 'Past Feedback', 'erdo-client-preview' ); ?></h4>
				<ul class="erdo-client-preview-feedback-history-list" id="erdo-client-preview-feedback-history-list"></ul>
			</div>
		</div>
	</div>
</div>
