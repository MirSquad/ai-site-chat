<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'site_chat_api_key' );
delete_option( 'site_chat_enabled' );
delete_option( 'site_chat_rate_limit' );
delete_option( 'site_chat_custom_instructions' );
delete_option( 'site_chat_post_types' );
delete_option( 'site_chat_log_enabled' );
delete_option( 'site_chat_contact_url' );
delete_option( 'site_chat_newsletter_url' );
delete_option( 'site_chat_write_abilities' );
delete_option( 'site_chat_db_version' );
delete_transient( 'site_chat_context_cache' );

// Drop conversation log table.
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}site_chat_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Remove all per-IP rate-limit transients.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_site_chat_rl_%' OR option_name LIKE '_transient_timeout_site_chat_rl_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
