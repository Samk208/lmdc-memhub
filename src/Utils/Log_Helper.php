<?php
/**
 * Log Helper Class
 *
 * @package LNMC_Member_Hub
 * @since 1.0.1
 */

namespace LNMC_Member_Hub\Utils;

/**
 * Handles system logging with PII protection.
 */
class Log_Helper {

	/**
	 * Log admin action with PII masking.
	 *
	 * @param int    $actor_id Actor ID (user ID).
	 * @param string $action   Action performed.
	 * @param array  $before   State before action (will be sanitized).
	 * @param array  $after    State after action (will be sanitized).
	 * @param string $context  Optional context.
	 * @return bool Success status.
	 */
	public static function lmnc_log_admin_action( int $actor_id, string $action, array $before = array(), array $after = array(), ?string $context = null ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'lnmc_system_log';

		// Sanitize data to remove PII and card data
		$sanitized_before = self::sanitize_log_data( $before );
		$sanitized_after  = self::sanitize_log_data( $after );

		// Prepare data for insertion
		$data = array(
			'actor_id'    => $actor_id,
			'action'      => substr( $action, 0, 120 ), // Ensure it fits in VARCHAR(120)
			'before_json' => wp_json_encode( $sanitized_before ),
			'after_json'  => wp_json_encode( $sanitized_after ),
			'context'     => $context ? substr( $context, 0, 120 ) : null,
			'created_at'  => current_time( 'mysql' ),
		);

		// Insert log entry
		$result = $wpdb->insert( $table_name, $data );

		if ( false === $result ) {
			error_log( '[LNMC Log Error] Failed to insert log entry: ' . $wpdb->last_error );
			return false;
		}

		return true;
	}

	/**
	 * Sanitize data to remove PII and sensitive information.
	 *
	 * @param array $data Data to sanitize.
	 * @return array Sanitized data.
	 */
	private static function sanitize_log_data( array $data ): array {
		if ( empty( $data ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $data as $key => $value ) {
			// Skip card-related fields
			if ( self::is_card_field( $key ) ) {
				continue;
			}

			// Mask email addresses
			if ( self::is_email_field( $key ) && is_string( $value ) ) {
				$sanitized[ $key ] = self::mask_email( $value );
				continue;
			}

			// Recursively sanitize nested arrays
			if ( is_array( $value ) ) {
				$sanitized[ $key ] = self::sanitize_log_data( $value );
				continue;
			}

			// Keep other data as-is
			$sanitized[ $key ] = $value;
		}

		return $sanitized;
	}

	/**
	 * Check if field is card-related.
	 *
	 * @param string $field_name Field name.
	 * @return bool True if card-related.
	 */
	private static function is_card_field( string $field_name ): bool {
		$card_fields = array(
			'card_number',
			'card_token',
			'payment_method_id',
			'payment_method',
			'cvc',
			'cvv',
			'expiry',
			'exp_month',
			'exp_year',
			'card_brand',
			'last4',
			'fingerprint',
			'funding',
			'wallet',
		);

		$field_lower = strtolower( $field_name );
		
		foreach ( $card_fields as $card_field ) {
			if ( strpos( $field_lower, $card_field ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if field is email-related.
	 *
	 * @param string $field_name Field name.
	 * @return bool True if email-related.
	 */
	private static function is_email_field( string $field_name ): bool {
		$email_fields = array(
			'email',
			'user_email',
			'customer_email',
			'payer_email',
			'contact_email',
		);

		$field_lower = strtolower( $field_name );
		
		foreach ( $email_fields as $email_field ) {
			if ( strpos( $field_lower, $email_field ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Mask email address for privacy.
	 *
	 * @param string $email Email address.
	 * @return string Masked email.
	 */
	private static function mask_email( string $email ): string {
		if ( ! is_email( $email ) ) {
			return $email;
		}

		$parts = explode( '@', $email );
		if ( count( $parts ) !== 2 ) {
			return $email;
		}

		$username = $parts[0];
		$domain   = $parts[1];

		// Mask username (keep first 2 characters)
		if ( strlen( $username ) > 2 ) {
			$masked_username = substr( $username, 0, 2 ) . '***';
		} else {
			$masked_username = $username;
		}

		return $masked_username . '@' . $domain;
	}

	/**
	 * Get recent log entries.
	 *
	 * @param int $limit Number of entries to retrieve.
	 * @return array Log entries.
	 */
	public static function get_recent_logs( int $limit = 10 ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'lnmc_system_log';

		$query = $wpdb->prepare(
			"SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d",
			$limit
		);

		$results = $wpdb->get_results( $query, ARRAY_A );

		if ( ! $results ) {
			return array();
		}

		// Decode JSON fields
		foreach ( $results as &$row ) {
			if ( ! empty( $row['before_json'] ) ) {
				$row['before'] = json_decode( $row['before_json'], true );
			}
			if ( ! empty( $row['after_json'] ) ) {
				$row['after'] = json_decode( $row['after_json'], true );
			}
			unset( $row['before_json'], $row['after_json'] );
		}

		return $results;
	}
}
