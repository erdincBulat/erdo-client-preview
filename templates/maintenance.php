<?php
defined( 'ABSPATH' ) || exit;
// Variables passed from Erdo_Client_Preview_Frontend::render_maintenance_page() via $cfg array.
/* phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound */
$sm_heading           = $cfg['heading'];
$sm_message           = $cfg['message'];
$sm_page_title        = $cfg['page_title'] ?? '';
$sm_logo_url          = $cfg['logo_url'];
$sm_bg_color          = $cfg['bg_color'];
$sm_bg_image_url      = $cfg['bg_image_url'];
$sm_text_color        = $cfg['text_color'];
$sm_accent_color      = $cfg['accent_color'];
$sm_countdown_enable  = (bool) $cfg['countdown_enable'];
$sm_countdown_ts      = (int) $cfg['countdown_ts'];
$sm_social_links      = (array) $cfg['social_links'];
$sm_is_preview        = (bool) $cfg['is_preview'];
$sm_mode              = $cfg['mode'] ?? 'maintenance';
$sm_subscribe_enable  = (bool) ( $cfg['subscribe_enable'] ?? false );
$sm_subscribe_label   = $cfg['subscribe_label'] ?? 'Notify me when we launch';
$sm_subscribed        = isset( $_GET['em_subscribed'] ) ? sanitize_key( $_GET['em_subscribed'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$sm_visitor_counter_enable = (bool) ( $cfg['visitor_counter_enable'] ?? false );
$sm_visitor_counter_label  = $cfg['visitor_counter_label'] ?? '%d people are waiting';
$sm_visitor_count          = (int) ( $cfg['visitor_count'] ?? 0 );
$sm_feedback_enable   = (bool) ( $cfg['feedback_enable'] ?? false );
$sm_feedback_nonce    = $cfg['feedback_nonce'] ?? '';
$sm_feedback_rest_url = $cfg['feedback_rest_url'] ?? '';
$sm_feedback_sent     = (bool) ( $cfg['feedback_sent'] ?? false );
// Social icon SVGs
$sm_social_icons = array(
	'twitter'   => '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.734-8.835L1.254 2.25H8.08l4.253 5.622zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
	'instagram'  => '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>',
	'facebook'  => '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
	'linkedin'  => '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>',
	'youtube'   => '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M23.495 6.205a3.007 3.007 0 00-2.088-2.088c-1.87-.501-9.396-.501-9.396-.501s-7.507-.01-9.396.501A3.007 3.007 0 00.527 6.205a31.247 31.247 0 00-.522 5.805 31.247 31.247 0 00.522 5.783 3.007 3.007 0 002.088 2.088c1.868.502 9.396.502 9.396.502s7.506 0 9.396-.502a3.007 3.007 0 002.088-2.088 31.247 31.247 0 00.5-5.783 31.247 31.247 0 00-.5-5.805zM9.609 15.601V8.408l6.264 3.602z"/></svg>',
);
/* phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound */
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if ( ! $sm_is_preview && 'coming_soon' !== $sm_mode ) : ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<title><?php echo esc_html( $sm_page_title ?: get_bloginfo( 'name' ) ); ?> — <?php echo 'coming_soon' === $sm_mode ? esc_html__( 'Coming Soon', 'erdo-client-preview' ) : esc_html__( 'Maintenance', 'erdo-client-preview' ); ?></title>
<?php
/* phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound */
$sm_root_css = sprintf(
	":root {\n\t--sm-bg:     %s;\n\t--sm-text:   %s;\n\t--sm-accent: %s;\n}\n",
	esc_attr( $sm_bg_color ),
	esc_attr( $sm_text_color ),
	esc_attr( $sm_accent_color )
);

$sm_body_css = "body {\n\tmin-height: 100vh;\n\tdisplay: flex;\n\talign-items: center;\n\tjustify-content: center;\n\tbackground-color: var(--sm-bg);\n";
if ( $sm_bg_image_url ) {
	$sm_body_css .= sprintf(
		"\tbackground-image: url('%s');\n\tbackground-size: cover;\n\tbackground-position: center;\n\tbackground-attachment: fixed;\n",
		esc_url( $sm_bg_image_url )
	);
}
$sm_body_css .= "\tcolor: var(--sm-text);\n\tfont-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;\n\tpadding: 2rem;\n\tline-height: 1.6;\n}\n";

$sm_bg_overlay_css = $sm_bg_image_url
	? "body::before {\n\tcontent: '';\n\tposition: fixed;\n\tinset: 0;\n\tbackground: rgba(0,0,0,.45);\n\tz-index: 0;\n}\n.sm-wrap { position: relative; z-index: 1; }\n"
	: '';

$sm_static_css = <<<'CSS'
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

.sm-wrap {
	max-width: 560px;
	width: 100%;
	text-align: center;
	animation: sm-fadein .6s ease both;
}

@keyframes sm-fadein {
	from { opacity: 0; transform: translateY(16px); }
	to   { opacity: 1; transform: translateY(0); }
}

.sm-logo { margin-bottom: 2rem; }
.sm-logo img { max-height: 72px; max-width: 240px; width: auto; }
.sm-icon { font-size: 3rem; margin-bottom: 1.5rem; display: block; }

h1.sm-heading {
	font-size: clamp(1.6rem, 4vw, 2.4rem);
	font-weight: 700;
	margin-bottom: 1rem;
	letter-spacing: -0.02em;
}

.sm-message { font-size: 1.05rem; opacity: .85; margin-bottom: 2.5rem; }

/* Countdown */
.sm-countdown {
	display: flex; gap: 1.25rem; justify-content: center;
	flex-wrap: wrap; margin-bottom: 2.5rem;
}
.sm-unit { display: flex; flex-direction: column; align-items: center; min-width: 72px; }
.sm-num {
	font-size: 2.8rem; font-weight: 800; line-height: 1;
	color: var(--sm-accent); font-variant-numeric: tabular-nums;
}
.sm-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .1em; opacity: .6; margin-top: .25rem; }
.sm-divider { font-size: 2.4rem; font-weight: 300; align-self: flex-start; margin-top: .25rem; opacity: .4; }

.sm-bar { height: 3px; background: rgba(255,255,255,.1); border-radius: 2px; overflow: hidden; margin-bottom: 2rem; }
.sm-bar-fill { height: 100%; background: var(--sm-accent); border-radius: 2px; transition: width 1s linear; }

/* Social */
.sm-social { display: flex; gap: 14px; justify-content: center; margin-top: 2rem; flex-wrap: wrap; }
.sm-social a {
	display: flex; align-items: center; justify-content: center;
	width: 40px; height: 40px; border-radius: 50%;
	background: rgba(255,255,255,.1); color: var(--sm-text);
	transition: background .2s, transform .2s; text-decoration: none;
}
.sm-social a:hover { background: var(--sm-accent); transform: translateY(-2px); }
.sm-social svg { width: 18px; height: 18px; fill: currentColor; }

/* Preview banner */
.sm-preview-banner {
	position: fixed; top: 0; left: 0; right: 0;
	background: var(--sm-accent); color: #fff;
	text-align: center; padding: 8px;
	font-size: 13px; font-weight: 600; z-index: 9999;
}

/* Subscribe form */
.sm-subscribe { margin-top: 2rem; }
.sm-subscribe form { display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; }
.sm-subscribe input[type="email"] {
	flex: 1 1 220px; padding: 10px 16px; border: none; border-radius: 6px;
	font-size: .95rem; outline: none; min-width: 0;
	background: rgba(255,255,255,.15); color: var(--sm-text);
}
.sm-subscribe input[type="email"]::placeholder { opacity: .6; }
.sm-subscribe button {
	padding: 10px 20px; background: var(--sm-accent); color: #fff; border: none;
	border-radius: 6px; font-size: .95rem; font-weight: 600; cursor: pointer;
	transition: opacity .2s; white-space: nowrap;
}
.sm-subscribe button:hover { opacity: .85; }
.sm-subscribe .sm-subscribed-msg {
	font-size: .9rem; opacity: .8; padding: 8px 16px;
	background: rgba(255,255,255,.1); border-radius: 6px; display: inline-block;
}

.sm-visitor-count {
	font-size: .85rem; opacity: .65; margin-top: 1rem;
	letter-spacing: .03em;
}

@media (max-width: 400px) {
	.sm-num { font-size: 2rem; }
	.sm-unit { min-width: 56px; }
}
CSS;

$sm_css = $sm_root_css . $sm_body_css . $sm_bg_overlay_css . $sm_static_css;
/* phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound */

wp_register_style( 'erdo-client-preview-maintenance', false, array(), ERDO_CLIENT_PREVIEW_VERSION );
wp_add_inline_style( 'erdo-client-preview-maintenance', $sm_css );
wp_enqueue_style( 'erdo-client-preview-maintenance' );
wp_print_styles( 'erdo-client-preview-maintenance' );
?>
<?php if ( $sm_feedback_enable ) : ?>
<?php
wp_enqueue_style( 'erdo-client-preview-feedback-widget', plugins_url( 'assets/css/feedback-widget.css', ERDO_CLIENT_PREVIEW_PLUGIN_FILE ), array(), ERDO_CLIENT_PREVIEW_VERSION );
wp_print_styles( 'erdo-client-preview-feedback-widget' );
?>
<?php endif; ?>
</head>
<body>

<?php if ( $sm_is_preview ) : ?>
<div class="sm-preview-banner">
	🔍 <?php esc_html_e( 'Preview Mode — This is how visitors see your maintenance page', 'erdo-client-preview' ); ?>
</div>
<div style="height:40px"></div>
<?php endif; ?>

<div class="sm-wrap">

	<?php if ( $sm_logo_url ) : ?>
		<div class="sm-logo">
			<img src="<?php echo esc_url( $sm_logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
		</div>
	<?php else : ?>
		<span class="sm-icon" aria-hidden="true"><?php echo 'coming_soon' === $sm_mode ? '🚀' : '🔧'; ?></span>
	<?php endif; ?>

	<h1 class="sm-heading"><?php echo esc_html( $sm_heading ); ?></h1>
	<div class="sm-message"><?php echo wp_kses_post( $sm_message ); ?></div>

	<?php if ( $sm_countdown_enable && $sm_countdown_ts > time() ) : ?>
		<div class="sm-countdown" id="sm-countdown"
			data-target="<?php echo (int) $sm_countdown_ts; ?>"
			data-start="<?php echo (int) time(); ?>">
			<div class="sm-unit">
				<span class="sm-num" id="sm-d">--</span>
				<span class="sm-label"><?php esc_html_e( 'Days', 'erdo-client-preview' ); ?></span>
			</div>
			<div class="sm-divider" aria-hidden="true">:</div>
			<div class="sm-unit">
				<span class="sm-num" id="sm-h">--</span>
				<span class="sm-label"><?php esc_html_e( 'Hours', 'erdo-client-preview' ); ?></span>
			</div>
			<div class="sm-divider" aria-hidden="true">:</div>
			<div class="sm-unit">
				<span class="sm-num" id="sm-m">--</span>
				<span class="sm-label"><?php esc_html_e( 'Minutes', 'erdo-client-preview' ); ?></span>
			</div>
			<div class="sm-divider" aria-hidden="true">:</div>
			<div class="sm-unit">
				<span class="sm-num" id="sm-s">--</span>
				<span class="sm-label"><?php esc_html_e( 'Seconds', 'erdo-client-preview' ); ?></span>
			</div>
		</div>
		<div class="sm-bar"><div class="sm-bar-fill" id="sm-bar"></div></div>
	<?php endif; ?>

	<?php if ( $sm_subscribe_enable && 'coming_soon' === $sm_mode ) : ?>
		<div class="sm-subscribe">
			<?php if ( '1' === $sm_subscribed ) : ?>
				<p class="sm-subscribed-msg">✓ <?php esc_html_e( "You're on the list! We'll notify you when we launch.", 'erdo-client-preview' ); ?></p>
			<?php elseif ( 'invalid' === $sm_subscribed ) : ?>
				<p class="sm-subscribed-msg"><?php esc_html_e( 'Please enter a valid email address.', 'erdo-client-preview' ); ?></p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'em_subscribe' ); ?>
					<input type="hidden" name="action" value="em_subscribe">
					<input type="email" name="em_email"
						placeholder="<?php esc_attr_e( 'Your email address', 'erdo-client-preview' ); ?>"
						required>
					<button type="submit"><?php echo esc_html( $sm_subscribe_label ); ?></button>
				</form>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $sm_visitor_counter_enable && 'coming_soon' === $sm_mode && $sm_visitor_count > 0 ) : ?>
		<p class="sm-visitor-count">
			<?php echo esc_html( sprintf( $sm_visitor_counter_label, $sm_visitor_count ) ); ?>
		</p>
	<?php endif; ?>

	<?php if ( ! empty( $sm_social_links ) ) : ?>
		<div class="sm-social">
			<?php foreach ( $sm_social_links as $sm_platform => $sm_url ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
				if ( ! isset( $sm_social_icons[ $sm_platform ] ) ) continue;
				?>
				<a href="<?php echo esc_url( $sm_url ); ?>" target="_blank" rel="noopener noreferrer"
					aria-label="<?php echo esc_attr( ucfirst( $sm_platform ) ); ?>">
					<?php echo $sm_social_icons[ $sm_platform ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG is hardcoded above ?>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

</div>

<?php if ( $sm_feedback_enable ) : ?>
<?php include ERDO_CLIENT_PREVIEW_PLUGIN_DIR . 'templates/partials/feedback-widget.php'; ?>
<?php
wp_enqueue_script( 'erdo-client-preview-feedback-widget', plugins_url( 'assets/js/feedback-widget.js', ERDO_CLIENT_PREVIEW_PLUGIN_FILE ), array(), ERDO_CLIENT_PREVIEW_VERSION, true );
wp_localize_script( 'erdo-client-preview-feedback-widget', 'erdoClientPreviewFeedback', array(
	'restUrl'   => $sm_feedback_rest_url,
	'statusUrl' => rest_url( 'erdo-client-preview/v1/feedback/status' ),
	'nonce'     => $sm_feedback_nonce,
	'i18n'      => array(
		'submit'  => __( 'Send Feedback', 'erdo-client-preview' ),
		'sending' => __( 'Sending…', 'erdo-client-preview' ),
		'success' => __( 'Thanks! Your feedback has been sent.', 'erdo-client-preview' ),
		'error'   => __( 'An error occurred. Please try again.', 'erdo-client-preview' ),
		'reply'   => __( 'Reply:', 'erdo-client-preview' ),
	),
) );
wp_print_scripts( 'erdo-client-preview-feedback-widget' );
?>
<?php endif; ?>

<?php if ( $sm_countdown_enable && $sm_countdown_ts > time() ) : ?>
<?php
wp_enqueue_script( 'erdo-client-preview-countdown', plugins_url( 'assets/js/countdown.js', ERDO_CLIENT_PREVIEW_PLUGIN_FILE ), array(), ERDO_CLIENT_PREVIEW_VERSION, true );
wp_print_scripts( 'erdo-client-preview-countdown' );
?>
<?php endif; ?>
</body>
</html>
