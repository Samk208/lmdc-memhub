<?php
/**
 * Database Management Class
 *
 * @package LNMC_Member_Hub
 * @since 1.0.0
 */

namespace LNMC_Member_Hub\Database;

/**
 * Handles database operations for member management.
 */
class Database {

	/**
	 * Database version.
	 *
	 * @var string
	 */
	private const DB_VERSION = '1.0.1';

	/**
	 * Database version option name.
	 *
	 * @var string
	 */
	private const DB_VERSION_OPTION = 'lnmc_member_hub_db_version';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'check_db_version' ) );
		add_action( 'wp_install', array( $this, 'create_tables' ) );
		add_action( 'wp_upgrade', array( $this, 'create_tables' ) );
	}

	/**
	 * Check database version and update if necessary.
	 *
	 * @return void
	 */
	public function check_db_version(): void {
		$current_version = get_option( self::DB_VERSION_OPTION, '0.0.0' );
		
		if ( version_compare( $current_version, self::DB_VERSION, '<' ) ) {
			$this->create_tables();
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
		}
	}

	/**
	 * Create database tables.
	 *
	 * @return void
	 */
	public function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Members table
		$members_table = $wpdb->prefix . 'lnmc_members';
		$members_sql = "CREATE TABLE $members_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			membership_status varchar(50) NOT NULL DEFAULT 'inactive',
			membership_type varchar(50) NOT NULL DEFAULT 'standard',
			subscription_id varchar(255) DEFAULT NULL,
			payment_amount decimal(10,2) DEFAULT 0.00,
			payment_currency varchar(3) DEFAULT 'USD',
			join_date datetime DEFAULT CURRENT_TIMESTAMP,
			expiry_date datetime DEFAULT NULL,
			last_payment_date datetime DEFAULT NULL,
			payment_status varchar(50) DEFAULT 'pending',
			stripe_customer_id varchar(255) DEFAULT NULL,
			stripe_subscription_id varchar(255) DEFAULT NULL,
			webhook_processed tinyint(1) DEFAULT 0,
			privacy_consent tinyint(1) DEFAULT 0,
			marketing_consent tinyint(1) DEFAULT 0,
			data_export_requested tinyint(1) DEFAULT 0,
			data_deletion_requested tinyint(1) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_id (user_id),
			KEY membership_status (membership_status),
			KEY subscription_id (subscription_id),
			KEY stripe_customer_id (stripe_customer_id),
			KEY join_date (join_date),
			KEY expiry_date (expiry_date)
		) $charset_collate;";

		// Member payments table
		$payments_table = $wpdb->prefix . 'lnmc_payments';
		$payments_sql = "CREATE TABLE $payments_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			member_id bigint(20) unsigned NOT NULL,
			stripe_payment_intent_id varchar(255) DEFAULT NULL,
			stripe_charge_id varchar(255) DEFAULT NULL,
			amount decimal(10,2) NOT NULL,
			currency varchar(3) DEFAULT 'USD',
			payment_status varchar(50) NOT NULL DEFAULT 'pending',
			payment_method varchar(50) DEFAULT 'card',
			description text DEFAULT NULL,
			metadata longtext DEFAULT NULL,
			webhook_processed tinyint(1) DEFAULT 0,
			refunded tinyint(1) DEFAULT 0,
			refund_amount decimal(10,2) DEFAULT 0.00,
			refund_reason text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY member_id (member_id),
			KEY stripe_payment_intent_id (stripe_payment_intent_id),
			KEY payment_status (payment_status),
			KEY created_at (created_at)
		) $charset_collate;";

		// Member activity log table
		$activity_table = $wpdb->prefix . 'lnmc_activity_log';
		$activity_sql = "CREATE TABLE $activity_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			member_id bigint(20) unsigned NOT NULL,
			activity_type varchar(50) NOT NULL,
			activity_description text NOT NULL,
			ip_address varchar(45) DEFAULT NULL,
			user_agent text DEFAULT NULL,
			metadata longtext DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY member_id (member_id),
			KEY activity_type (activity_type),
			KEY created_at (created_at)
		) $charset_collate;";

		// Member flags table
		$flags_table = $wpdb->prefix . 'lnmc_member_flags';
		$flags_sql = "CREATE TABLE $flags_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			member_id bigint(20) unsigned NOT NULL,
			flag_type varchar(50) NOT NULL,
			flag_reason text DEFAULT NULL,
			flagged_by bigint(20) unsigned NOT NULL,
			flag_status varchar(50) DEFAULT 'active',
			reviewed_by bigint(20) unsigned DEFAULT NULL,
			reviewed_at datetime DEFAULT NULL,
			review_notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY member_id (member_id),
			KEY flag_type (flag_type),
			KEY flag_status (flag_status),
			KEY flagged_by (flagged_by)
		) $charset_collate;";

		// System log table
		$system_log_table = $wpdb->prefix . 'lnmc_system_log';
		$system_log_sql = "CREATE TABLE $system_log_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			actor_id bigint(20) unsigned NOT NULL,
			action varchar(120) NOT NULL,
			before_json longtext DEFAULT NULL,
			after_json longtext DEFAULT NULL,
			context varchar(120) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY action (action),
			KEY actor_id (actor_id),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		
		// Create tables
		dbDelta( $members_sql );
		dbDelta( $payments_sql );
		dbDelta( $activity_sql );
		dbDelta( $flags_sql );
		dbDelta( $system_log_sql );

		// Log table creation
		error_log( '[LNMC Member Hub] Database tables created/updated successfully' );
	}

	/**
	 * Get member by user ID.
	 *
	 * @param int $user_id User ID.
	 * @return object|null Member object or null if not found.
	 */
	public function get_member_by_user_id( int $user_id ) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'lnmc_members';
		
		$member = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE user_id = %d",
				$user_id
			)
		);

		return $member;
	}

	/**
	 * Get member by Stripe customer ID.
	 *
	 * @param string $customer_id Stripe customer ID.
	 * @return object|null Member object or null if not found.
	 */
	public function get_member_by_stripe_customer_id( string $customer_id ) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'lnmc_members';
		
		$member = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE stripe_customer_id = %s",
				$customer_id
			)
		);

		return $member;
	}

	/**
	 * Get member by Stripe subscription ID.
	 *
	 * @param string $subscription_id Stripe subscription ID.
	 * @return object|null Member object or null if not found.
	 */
	public function get_member_by_stripe_subscription_id( string $subscription_id ) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'lnmc_members';
		
		$member = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE stripe_subscription_id = %s",
				$subscription_id
			)
		);

		return $member;
	}

	/**
	 * Get member by ID.
	 *
	 * @param int $member_id Member ID.
	 * @return object|null Member object or null if not found.
	 */
	public function get_member_by_id( int $member_id ) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'lnmc_members';
		
		$member = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE id = %d",
				$member_id
			)
		);

		return $member;
	}

	/**
	 * Create or update member.
	 *
	 * @param array $data Member data.
	 * @return int|false Member ID on success, false on failure.
	 */
	public function save_member( array $data ) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'lnmc_members';
		
		// Sanitize and validate data
		$sanitized_data = $this->sanitize_member_data( $data );
		
		// Check if member exists
		$existing_member = $this->get_member_by_user_id( $sanitized_data['user_id'] );
		
		if ( $existing_member ) {
			// Update existing member
			$result = $wpdb->update(
				$table,
				$sanitized_data,
				array( 'user_id' => $sanitized_data['user_id'] ),
				null,
				array( '%d' )
			);
			
			if ( $result !== false ) {
				$this->log_activity( $existing_member->id, 'member_updated', 'Member profile updated' );
				return $existing_member->id;
			}
		} else {
			// Create new member
			$result = $wpdb->insert(
				$table,
				$sanitized_data,
				array(
					'%d', // user_id
					'%s', // membership_status
					'%s', // membership_type
					'%s', // subscription_id
					'%f', // payment_amount
					'%s', // payment_currency
					'%s', // join_date
					'%s', // expiry_date
					'%s', // last_payment_date
					'%s', // payment_status
					'%s', // stripe_customer_id
					'%s', // stripe_subscription_id
					'%d', // webhook_processed
					'%d', // privacy_consent
					'%d', // marketing_consent
					'%d', // data_export_requested
					'%d', // data_deletion_requested
				)
			);
			
			if ( $result !== false ) {
				$member_id = $wpdb->insert_id;
				$this->log_activity( $member_id, 'member_created', 'New member registered' );
				return $member_id;
			}
		}
		
		return false;
	}

	/**
	 * Update member data.
	 *
	 * @param int   $member_id Member ID.
	 * @param array $data Data to update.
	 * @return bool True on success, false on failure.
	 */
	public function update_member( int $member_id, array $data ): bool {
		global $wpdb;
		
		$table = $wpdb->prefix . 'lnmc_members';
		
		// Sanitize data
		$sanitized_data = $this->sanitize_member_data( $data );
		
		// Remove user_id from update data (should not be changed)
		unset( $sanitized_data['user_id'] );
		
		// Update member
		$result = $wpdb->update(
			$table,
			$sanitized_data,
			array( 'id' => $member_id ),
			null,
			array( '%d' )
		);
		
		if ( $result !== false ) {
			$this->log_activity( $member_id, 'member_updated', 'Member data updated' );
			return true;
		}
		
		return false;
	}

	/**
	 * Get all members with pagination.
	 *
	 * @param array $args Query arguments.
	 * @return array Members array with pagination info.
	 */
	public function get_members( array $args = array() ) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'lnmc_members';
		
		// Default arguments
		$defaults = array(
			'status' => '',
			'type' => '',
			'search' => '',
			'orderby' => 'join_date',
			'order' => 'DESC',
			'per_page' => 20,
			'page' => 1,
		);
		
		$args = wp_parse_args( $args, $defaults );
		
		// Build WHERE clause
		$where_clauses = array();
		$where_values = array();
		
		if ( ! empty( $args['status'] ) ) {
			$where_clauses[] = 'membership_status = %s';
			$where_values[] = sanitize_text_field( $args['status'] );
		}
		
		if ( ! empty( $args['type'] ) ) {
			$where_clauses[] = 'membership_type = %s';
			$where_values[] = sanitize_text_field( $args['type'] );
		}
		
		if ( ! empty( $args['search'] ) ) {
			$search_term = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where_clauses[] = '(user_id IN (SELECT ID FROM ' . $wpdb->users . ' WHERE display_name LIKE %s OR user_email LIKE %s))';
			$where_values[] = $search_term;
			$where_values[] = $search_term;
		}
		
		$where_sql = '';
		if ( ! empty( $where_clauses ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
		}
		
		// Build ORDER BY clause
		$allowed_orderby = array( 'join_date', 'membership_status', 'payment_amount', 'user_id' );
		$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'join_date';
		$order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
		
		// Get total count
		$count_sql = "SELECT COUNT(*) FROM $table $where_sql";
		if ( ! empty( $where_values ) ) {
			$count_sql = $wpdb->prepare( $count_sql, $where_values );
		}
		$total = $wpdb->get_var( $count_sql );
		
		// Calculate pagination
		$per_page = absint( $args['per_page'] );
		$page = absint( $args['page'] );
		$offset = ( $page - 1 ) * $per_page;
		
		// Get members
		$members_sql = "SELECT * FROM $table $where_sql ORDER BY $orderby $order LIMIT %d OFFSET %d";
		$query_values = array_merge( $where_values, array( $per_page, $offset ) );
		$members_sql = $wpdb->prepare( $members_sql, $query_values );
		
		$members = $wpdb->get_results( $members_sql );
		
		// Add user data to members
		foreach ( $members as $member ) {
			$user = get_user_by( 'ID', $member->user_id );
			if ( $user ) {
				$member->user_data = array(
					'display_name' => $user->display_name,
					'user_email' => $user->user_email,
					'user_login' => $user->user_login,
				);
			}
		}
		
		return array(
			'members' => $members,
			'total' => $total,
			'per_page' => $per_page,
			'page' => $page,
			'total_pages' => ceil( $total / $per_page ),
		);
	}

	/**
	 * Log member activity.
	 *
	 * @param int    $member_id Member ID.
	 * @param string $activity_type Activity type.
	 * @param string $description Activity description.
	 * @param array  $metadata Additional metadata.
	 * @return int|false Activity log ID on success, false on failure.
	 */
	public function log_activity( int $member_id, string $activity_type, string $description, array $metadata = array() ) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'lnmc_activity_log';
		
		$data = array(
			'member_id' => $member_id,
			'activity_type' => sanitize_text_field( $activity_type ),
			'activity_description' => sanitize_textarea_field( $description ),
			'ip_address' => $this->get_client_ip(),
			'user_agent' => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
			'metadata' => ! empty( $metadata ) ? wp_json_encode( $metadata ) : null,
		);
		
		$result = $wpdb->insert(
			$table,
			$data,
			array(
				'%d', // member_id
				'%s', // activity_type
				'%s', // activity_description
				'%s', // ip_address
				'%s', // user_agent
				'%s', // metadata
			)
		);
		
		return $result !== false ? $wpdb->insert_id : false;
	}

	/**
	 * Get member activity log.
	 *
	 * @param int   $member_id Member ID.
	 * @param int   $limit Number of entries to return.
	 * @param int   $offset Offset for pagination.
	 * @return array Activity log entries.
	 */
	public function get_member_activity( int $member_id, int $limit = 50, int $offset = 0 ) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'lnmc_activity_log';
		
		$sql = $wpdb->prepare(
			"SELECT * FROM $table WHERE member_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
			$member_id,
			$limit,
			$offset
		);
		
		return $wpdb->get_results( $sql );
	}

	/**
	 * Sanitize member data.
	 *
	 * @param array $data Raw member data.
	 * @return array Sanitized member data.
	 */
	private function sanitize_member_data( array $data ): array {
		$sanitized = array();
		
		// Required fields
		if ( isset( $data['user_id'] ) ) {
			$sanitized['user_id'] = absint( $data['user_id'] );
		}
		
		// Optional fields
		if ( isset( $data['membership_status'] ) ) {
			$allowed_statuses = array( 'active', 'inactive', 'pending', 'cancelled', 'expired' );
			$sanitized['membership_status'] = in_array( $data['membership_status'], $allowed_statuses, true ) 
				? $data['membership_status'] 
				: 'inactive';
		}
		
		if ( isset( $data['membership_type'] ) ) {
			$allowed_types = array( 'standard', 'premium', 'enterprise' );
			$sanitized['membership_type'] = in_array( $data['membership_type'], $allowed_types, true ) 
				? $data['membership_type'] 
				: 'standard';
		}
		
		if ( isset( $data['subscription_id'] ) ) {
			$sanitized['subscription_id'] = sanitize_text_field( $data['subscription_id'] );
		}
		
		if ( isset( $data['payment_amount'] ) ) {
			$sanitized['payment_amount'] = floatval( $data['payment_amount'] );
		}
		
		if ( isset( $data['payment_currency'] ) ) {
			$sanitized['payment_currency'] = sanitize_text_field( $data['payment_currency'] );
		}
		
		if ( isset( $data['join_date'] ) ) {
			$sanitized['join_date'] = sanitize_text_field( $data['join_date'] );
		}
		
		if ( isset( $data['expiry_date'] ) ) {
			$sanitized['expiry_date'] = sanitize_text_field( $data['expiry_date'] );
		}
		
		if ( isset( $data['last_payment_date'] ) ) {
			$sanitized['last_payment_date'] = sanitize_text_field( $data['last_payment_date'] );
		}
		
		if ( isset( $data['payment_status'] ) ) {
			$allowed_payment_statuses = array( 'pending', 'succeeded', 'failed', 'refunded' );
			$sanitized['payment_status'] = in_array( $data['payment_status'], $allowed_payment_statuses, true ) 
				? $data['payment_status'] 
				: 'pending';
		}
		
		if ( isset( $data['stripe_customer_id'] ) ) {
			$sanitized['stripe_customer_id'] = sanitize_text_field( $data['stripe_customer_id'] );
		}
		
		if ( isset( $data['stripe_subscription_id'] ) ) {
			$sanitized['stripe_subscription_id'] = sanitize_text_field( $data['stripe_subscription_id'] );
		}
		
		// Boolean fields
		$boolean_fields = array( 'webhook_processed', 'privacy_consent', 'marketing_consent', 'data_export_requested', 'data_deletion_requested' );
		foreach ( $boolean_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$sanitized[ $field ] = (bool) $data[ $field ];
			}
		}
		
		return $sanitized;
	}

	/**
	 * Get client IP address.
	 *
	 * @return string IP address.
	 */
	private function get_client_ip(): string {
		$ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
		
		foreach ( $ip_keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) === true ) {
				foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) {
					$ip = trim( $ip );
					if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
						return $ip;
					}
				}
			}
		}
		
		return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
	}

	/**
	 * Get database statistics.
	 *
	 * @return array Statistics array.
	 */
	public function get_statistics(): array {
		global $wpdb;
		
		$members_table = $wpdb->prefix . 'lnmc_members';
		$payments_table = $wpdb->prefix . 'lnmc_payments';
		
		$stats = array();
		
		// Total members
		$stats['total_members'] = $wpdb->get_var( "SELECT COUNT(*) FROM $members_table" );
		
		// Active members
		$stats['active_members'] = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM $members_table WHERE membership_status = %s", 'active' )
		);
		
		// New members this month
		$stats['new_members_this_month'] = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $members_table WHERE MONTH(join_date) = %d AND YEAR(join_date) = %d",
				date( 'n' ),
				date( 'Y' )
			)
		);
		
		// Total revenue
		$stats['total_revenue'] = $wpdb->get_var(
			"SELECT SUM(amount) FROM $payments_table WHERE payment_status = 'succeeded'"
		);
		
		// Monthly revenue
		$stats['monthly_revenue'] = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(amount) FROM $payments_table WHERE payment_status = 'succeeded' AND MONTH(created_at) = %d AND YEAR(created_at) = %d",
				date( 'n' ),
				date( 'Y' )
			)
		);
		
		return $stats;
	}

	/**
	 * Export member data for GDPR compliance.
	 *
	 * @param int $user_id User ID.
	 * @return array Export data.
	 */
	public function export_member_data( int $user_id ): array {
		$member = $this->get_member_by_user_id( $user_id );
		if ( ! $member ) {
			return array();
		}
		
		$export_data = array(
			'member_info' => $member,
			'activity_log' => $this->get_member_activity( $member->id, 1000, 0 ),
			'payments' => $this->get_member_payments( $member->id ),
			'flags' => $this->get_member_flags( $member->id ),
		);
		
		// Mark export as requested
		$this->update_member_field( $member->id, 'data_export_requested', 1 );
		
		return $export_data;
	}

	/**
	 * Delete member data for GDPR compliance.
	 *
	 * @param int $user_id User ID.
	 * @return bool Success status.
	 */
	public function delete_member_data( int $user_id ): bool {
		global $wpdb;
		
		$member = $this->get_member_by_user_id( $user_id );
		if ( ! $member ) {
			return false;
		}
		
		// Delete member data from all tables
		$tables = array(
			'lnmc_members',
			'lnmc_payments',
			'lnmc_activity_log',
			'lnmc_member_flags',
		);
		
		foreach ( $tables as $table ) {
			$table_name = $wpdb->prefix . $table;
			$wpdb->delete( $table_name, array( 'member_id' => $member->id ), array( '%d' ) );
		}
		
		// Mark deletion as requested
		$this->update_member_field( $member->id, 'data_deletion_requested', 1 );
		
		return true;
	}

	/**
	 * Get member payments.
	 *
	 * @param int $member_id Member ID.
	 * @return array Payments array.
	 */
	private function get_member_payments( int $member_id ): array {
		global $wpdb;
		
		$table = $wpdb->prefix . 'lnmc_payments';
		
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE member_id = %d ORDER BY created_at DESC",
				$member_id
			)
		);
	}

	/**
	 * Get member flags.
	 *
	 * @param int $member_id Member ID.
	 * @return array Flags array.
	 */
	private function get_member_flags( int $member_id ): array {
		global $wpdb;
		
		$table = $wpdb->prefix . 'lnmc_member_flags';
		
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE member_id = %d ORDER BY created_at DESC",
				$member_id
			)
		);
	}

	/**
	 * Update member field.
	 *
	 * @param int    $member_id Member ID.
	 * @param string $field Field name.
	 * @param mixed  $value Field value.
	 * @return bool Success status.
	 */
	private function update_member_field( int $member_id, string $field, $value ): bool {
		global $wpdb;
		
		$table = $wpdb->prefix . 'lnmc_members';
		
		$result = $wpdb->update(
			$table,
			array( $field => $value ),
			array( 'id' => $member_id ),
			null,
			array( '%d' )
		);
		
		return $result !== false;
	}
}
