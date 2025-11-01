<?php
/**
 * Uninstall script for LNMC Member Hub
 *
 * This file is executed when the plugin is deleted from WordPress.
 * It cleans up all plugin data from the database.
 *
 * @package LNMC_Member_Hub
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Load WordPress functions
require_once ABSPATH . 'wp-admin/includes/plugin.php';

// Check if the plugin class exists before trying to use it
if ( class_exists( 'LNMC_Member_Hub\Plugin' ) ) {
	\LNMC_Member_Hub\Plugin::uninstall();
} else {
	// Fallback cleanup if the plugin class is not available
	lnmc_member_hub_cleanup_data();
}

/**
 * Fallback cleanup function
 */
function lnmc_member_hub_cleanup_data() {
	// Remove all plugin options
	$options_to_delete = array(
		'lnmc_member_hub_stripe_mode',
		'lnmc_member_hub_stripe_test_publishable_key',
		'lnmc_member_hub_stripe_test_secret_key',
		'lnmc_member_hub_stripe_live_publishable_key',
		'lnmc_member_hub_stripe_live_secret_key',
		'lnmc_member_hub_membership_mode',
		'lnmc_member_hub_api_enabled',
		'lnmc_member_hub_api_key',
		'lnmc_member_hub_version'
	);

	foreach ( $options_to_delete as $option ) {
		delete_option( $option );
	}

	// Remove any transients
	delete_transient( 'lnmc_member_hub_cache' );
	delete_transient( 'lnmc_member_hub_stripe_webhook' );

	// Clear any cached data
	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}
}
