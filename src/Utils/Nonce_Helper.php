<?php
/**
 * Nonce Helper Class
 *
 * @package LNMC_Member_Hub
 * @since 1.0.0
 */

namespace LNMC_Member_Hub\Utils;

/**
 * Utility class for nonce generation and verification.
 */
class Nonce_Helper {

	/**
	 * Nonce action prefix.
	 *
	 * @var string
	 */
	private const NONCE_PREFIX = 'lnmc_member_hub_';

	/**
	 * Constructor.
	 */
	public function __construct() {
		// No initialization needed
	}

	/**
	 * Create a nonce for a specific action.
	 *
	 * @param string $action The action name.
	 * @return string
	 */
	public function create_nonce( string $action ): string {
		return wp_create_nonce( self::NONCE_PREFIX . $action );
	}

	/**
	 * Verify a nonce for a specific action.
	 *
	 * @param string $nonce The nonce to verify.
	 * @param string $action The action name.
	 * @return bool
	 */
	public function verify_nonce( string $nonce, string $action ): bool {
		return wp_verify_nonce( $nonce, self::NONCE_PREFIX . $action ) !== false;
	}

	/**
	 * Create a nonce field for forms.
	 *
	 * @param string $action The action name.
	 * @param string $name The field name (default: '_wpnonce').
	 * @param bool $referer Whether to include the referer field.
	 * @return string
	 */
	public function create_nonce_field( string $action, string $name = '_wpnonce', bool $referer = true ): string {
		return wp_nonce_field( self::NONCE_PREFIX . $action, $name, $referer, false );
	}

	/**
	 * Create a nonce URL.
	 *
	 * @param string $actionurl The URL to add nonce to.
	 * @param string $action The action name.
	 * @param string $name The nonce name (default: '_wpnonce').
	 * @return string
	 */
	public function create_nonce_url( string $actionurl, string $action, string $name = '_wpnonce' ): string {
		return wp_nonce_url( $actionurl, self::NONCE_PREFIX . $action, $name );
	}

	/**
	 * Check if a nonce is valid and die if not.
	 *
	 * @param string $action The action name.
	 * @param string $name The nonce name (default: '_wpnonce').
	 * @return void
	 */
	public function check_admin_referer( string $action, string $name = '_wpnonce' ): void {
		check_admin_referer( self::NONCE_PREFIX . $action, $name );
	}

	/**
	 * Check if a nonce is valid and die if not (for AJAX requests).
	 *
	 * @param string $action The action name.
	 * @param string $name The nonce name (default: '_wpnonce').
	 * @return void
	 */
	public function check_ajax_referer( string $action, string $name = '_wpnonce' ): void {
		check_ajax_referer( self::NONCE_PREFIX . $action, $name );
	}

	/**
	 * Get the nonce prefix.
	 *
	 * @return string
	 */
	public function get_nonce_prefix(): string {
		return self::NONCE_PREFIX;
	}

	/**
	 * Create a nonce for REST API requests.
	 *
	 * @param string $action The action name.
	 * @return string
	 */
	public function create_rest_nonce( string $action ): string {
		return wp_create_nonce( 'wp_rest' );
	}

	/**
	 * Verify a REST API nonce.
	 *
	 * @param string $nonce The nonce to verify.
	 * @return bool
	 */
	public function verify_rest_nonce( string $nonce ): bool {
		return wp_verify_nonce( $nonce, 'wp_rest' ) !== false;
	}

	/**
	 * Create a nonce for AJAX requests.
	 *
	 * @param string $action The action name.
	 * @return string
	 */
	public function create_ajax_nonce( string $action ): string {
		return wp_create_nonce( self::NONCE_PREFIX . 'ajax_' . $action );
	}

	/**
	 * Verify an AJAX nonce.
	 *
	 * @param string $nonce The nonce to verify.
	 * @param string $action The action name.
	 * @return bool
	 */
	public function verify_ajax_nonce( string $nonce, string $action ): bool {
		return wp_verify_nonce( $nonce, self::NONCE_PREFIX . 'ajax_' . $action ) !== false;
	}
}
