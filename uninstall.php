<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}erdo_client_preview_feedback`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

delete_option( 'erdo_client_preview_settings' );
delete_option( 'erdo_client_preview_magic_links' );
delete_option( 'erdo_client_preview_subscribers' );
delete_option( 'erdo_client_preview_db_version' );
wp_clear_scheduled_hook( 'erdo_client_preview_schedule_end' );
