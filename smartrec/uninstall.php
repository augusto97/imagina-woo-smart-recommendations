<?php
/**
 * SmartRec Uninstall Handler.
 *
 * Cleans up all plugin data when the plugin is uninstalled.
 *
 * @package SmartRec
 */

// Exit if not called by WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Only clean up if the user opted in.
$delete_data = get_option( 'smartrec_delete_data_on_uninstall', false );

if ( ! $delete_data ) {
	return;
}

global $wpdb;

// 1. Drop all custom tables.
$tables = array(
	$wpdb->prefix . 'smartrec_events',
	$wpdb->prefix . 'smartrec_product_relationships',
	$wpdb->prefix . 'smartrec_product_scores',
	$wpdb->prefix . 'smartrec_user_profiles',
);

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// 2. Delete all options starting with smartrec_.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		'smartrec_%'
	)
);

// 3. Delete all transients starting with smartrec_.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		'_transient_smartrec_%',
		'_transient_timeout_smartrec_%'
	)
);

// 4. Remove all scheduled cron events.
wp_clear_scheduled_hook( 'smartrec_build_relationships' );
wp_clear_scheduled_hook( 'smartrec_cache_warmer' );
wp_clear_scheduled_hook( 'smartrec_data_cleanup' );

// 5. Clean up user meta.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
		'smartrec_%'
	)
);

// 6. Clean up product meta.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
		'_smartrec_%'
	)
);
