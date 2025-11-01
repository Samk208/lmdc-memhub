<?php
/**
 * Plugin Name: LNMC Member Hub
 * Plugin URI: https://lnmc.org/member-hub
 * Description: A comprehensive member management system for LNMC with REST API endpoints and admin settings.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: LNMC Development Team
 * Author URI: https://lnmc.org
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lnmc-member-hub
 * Domain Path: /languages
 * Network: false
 *
 * @package LNMC_Member_Hub
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'LNMC_MEMBER_HUB_VERSION', '1.0.1' );
define( 'LNMC_MEMBER_HUB_PLUGIN_FILE', __FILE__ );
define( 'LNMC_MEMBER_HUB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LNMC_MEMBER_HUB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LNMC_MEMBER_HUB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Include the Composer autoloader
if ( file_exists( LNMC_MEMBER_HUB_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once LNMC_MEMBER_HUB_PLUGIN_DIR . 'vendor/autoload.php';
}

// Simple autoloader for plugin classes
if ( ! class_exists( 'ComposerAutoloaderInit' ) ) {
    spl_autoload_register( function( $class ) {
        // Only handle our plugin classes
        if ( strpos( $class, 'LNMC_Member_Hub\\' ) !== 0 ) {
            return;
        }

        // Convert namespace to file path
        $class_path = str_replace( 'LNMC_Member_Hub\\', '', $class );
        $class_path = str_replace( '\\', '/', $class_path );
        $file_path = LNMC_MEMBER_HUB_PLUGIN_DIR . 'src/' . $class_path . '.php';

        // Load the file if it exists
        if ( file_exists( $file_path ) ) {
            require_once $file_path;
        }
    } );
}

// Initialize the plugin
add_action( 'plugins_loaded', function() {
	// Check PHP version
	if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>';
			printf(
				/* translators: %s: PHP version */
				esc_html__( 'LNMC Member Hub requires PHP version %s or higher. You are running version %s.', 'lnmc-member-hub' ),
				'8.1',
				esc_html( PHP_VERSION )
			);
			echo '</p></div>';
		} );
		return;
	}

	// Check WordPress version
	if ( version_compare( get_bloginfo( 'version' ), '6.5', '<' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>';
			printf(
				/* translators: %s: WordPress version */
				esc_html__( 'LNMC Member Hub requires WordPress version %s or higher. You are running version %s.', 'lnmc-member-hub' ),
				'6.5',
				esc_html( get_bloginfo( 'version' ) )
			);
			echo '</p></div>';
		} );
		return;
	}

	// Check if Plugin class exists
	if ( ! class_exists( '\\LNMC_Member_Hub\\Plugin' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'LNMC Member Hub plugin files are missing or corrupted. Please reinstall the plugin.', 'lnmc-member-hub' );
			echo '</p></div>';
		} );
		return;
	}

	// Initialize the plugin
	\LNMC_Member_Hub\Plugin::get_instance();
} );

// Activation hook
register_activation_hook( __FILE__, function() {
	\LNMC_Member_Hub\Plugin::activate();
} );

// Deactivation hook
register_deactivation_hook( __FILE__, function() {
	\LNMC_Member_Hub\Plugin::deactivate();
} );

// Uninstall hook is handled by uninstall.php file
