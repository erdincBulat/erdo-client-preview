<?php
/**
 * Plugin Name:       Erdo Client Preview
 * Plugin URI:        https://wordpress.org/plugins/erdo-client-preview/
 * Description:       Site access control with magic link bypass — generate a private link so clients can preview the live site while everyone else sees your coming soon or maintenance page.
 * Version:           1.4.0
 * Requires at least: 6.0
 * Tested up to:      7.1
 * Requires PHP:      7.4
 * Author:            Erdinc Bulat
 * Author URI:        https://erdincbulat.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       erdo-client-preview
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'ERDO_CLIENT_PREVIEW_VERSION',     '1.4.0' );
define( 'ERDO_CLIENT_PREVIEW_PLUGIN_FILE', __FILE__ );
define( 'ERDO_CLIENT_PREVIEW_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'ERDO_CLIENT_PREVIEW_DB_VERSION',  '1.3' );

spl_autoload_register( function ( string $class ): void {
	if ( strpos( $class, 'Erdo_Client_Preview_' ) !== 0 ) {
		return;
	}
	$file = ERDO_CLIENT_PREVIEW_PLUGIN_DIR . 'includes/class-' .
	        strtolower( str_replace( '_', '-', $class ) ) . '.php';
	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

register_activation_hook( __FILE__, static function (): void {
	Erdo_Client_Preview_Settings::set_defaults();
	Erdo_Client_Preview_DB::activate();
} );

add_action( 'erdo_client_preview_schedule_end', static function (): void {
	$settings                   = (array) get_option( Erdo_Client_Preview_Settings::OPTION_KEY, array() );
	$settings['enabled']        = false;
	$settings['schedule_enable'] = false;
	update_option( Erdo_Client_Preview_Settings::OPTION_KEY, $settings );
} );

add_action( 'plugins_loaded', static function (): void {
	$loader   = new Erdo_Client_Preview_Loader();
	$settings = new Erdo_Client_Preview_Settings();
	$token    = new Erdo_Client_Preview_Token();
	$db       = Erdo_Client_Preview_DB::get_instance();
	$frontend = new Erdo_Client_Preview_Frontend( $settings, $token, $db );
	$admin    = new Erdo_Client_Preview_Admin( $settings, $token, $db );

	$db->maybe_upgrade();

	$settings->register( $loader );
	$frontend->register( $loader );
	$admin->register( $loader );
	$loader->run();
} );
