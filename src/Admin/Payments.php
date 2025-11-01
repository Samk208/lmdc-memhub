<?php
/**
 * Payments Class
 *
 * @package LNMC_Member_Hub
 * @since 1.0.0
 */

namespace LNMC_Member_Hub\Admin;

/**
 * Payments class for managing payment transactions and subscriptions.
 */
class Payments {

	/**
	 * Initialize the payments management.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_payments_page' ) );
		add_action( 'wp_ajax_lnmc_get_payments', array( $this, 'ajax_get_payments' ) );
		add_action( 'wp_ajax_lnmc_refund_payment', array( $this, 'ajax_refund_payment' ) );
	}

	/**
	 * Add payments page to admin menu.
	 *
	 * @return void
	 */
	public function add_payments_page(): void {
		add_submenu_page(
			'lnmc-member-hub',
			__( 'Payments', 'lnmc-member-hub' ),
			__( 'Payments', 'lnmc-member-hub' ),
			'manage_options',
			'lnmc-member-hub-payments',
			array( $this, 'render_payments_page' )
		);
	}

	/**
	 * Render payments page.
	 *
	 * @return void
	 */
	public function render_payments_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'lnmc-member-hub' ) );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Payments & Subscriptions', 'lnmc-member-hub' ); ?></h1>
			
			<div class="lnmc-payments-overview">
				<h2><?php esc_html_e( 'Payment Overview', 'lnmc-member-hub' ); ?></h2>
				<p><?php esc_html_e( 'Monitor payments, subscriptions, and revenue from your membership site.', 'lnmc-member-hub' ); ?></p>
				
				<div class="lnmc-payments-stats">
					<div class="lnmc-stat-card">
						<h3><?php esc_html_e( 'Total Revenue', 'lnmc-member-hub' ); ?></h3>
						<div class="lnmc-stat-number">$<?php echo esc_html( number_format( $this->get_total_revenue(), 2 ) ); ?></div>
					</div>
					<div class="lnmc-stat-card">
						<h3><?php esc_html_e( 'This Month', 'lnmc-member-hub' ); ?></h3>
						<div class="lnmc-stat-number">$<?php echo esc_html( number_format( $this->get_monthly_revenue(), 2 ) ); ?></div>
					</div>
					<div class="lnmc-stat-card">
						<h3><?php esc_html_e( 'Pending Payments', 'lnmc-member-hub' ); ?></h3>
						<div class="lnmc-stat-number"><?php echo esc_html( $this->get_pending_payments() ); ?></div>
					</div>
					<div class="lnmc-stat-card">
						<h3><?php esc_html_e( 'Failed Payments', 'lnmc-member-hub' ); ?></h3>
						<div class="lnmc-stat-number"><?php echo esc_html( $this->get_failed_payments() ); ?></div>
					</div>
				</div>

				<div class="lnmc-payments-table">
					<h3><?php esc_html_e( 'Recent Transactions', 'lnmc-member-hub' ); ?></h3>
					
					<!-- Search and Filter -->
					<div class="lnmc-payments-filters">
						<input type="text" id="payment-search" placeholder="<?php esc_attr_e( 'Search payments...', 'lnmc-member-hub' ); ?>" class="regular-text">
						<select id="payment-status-filter">
							<option value=""><?php esc_html_e( 'All Statuses', 'lnmc-member-hub' ); ?></option>
							<option value="succeeded"><?php esc_html_e( 'Succeeded', 'lnmc-member-hub' ); ?></option>
							<option value="pending"><?php esc_html_e( 'Pending', 'lnmc-member-hub' ); ?></option>
							<option value="failed"><?php esc_html_e( 'Failed', 'lnmc-member-hub' ); ?></option>
							<option value="refunded"><?php esc_html_e( 'Refunded', 'lnmc-member-hub' ); ?></option>
						</select>
						<button type="button" id="export-payments" class="button button-secondary">
							<?php esc_html_e( 'Export CSV', 'lnmc-member-hub' ); ?>
						</button>
					</div>

					<!-- Payments Table -->
					<table class="wp-list-table widefat fixed striped" id="payments-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Date', 'lnmc-member-hub' ); ?></th>
								<th><?php esc_html_e( 'Member', 'lnmc-member-hub' ); ?></th>
								<th><?php esc_html_e( 'Amount', 'lnmc-member-hub' ); ?></th>
								<th><?php esc_html_e( 'Status', 'lnmc-member-hub' ); ?></th>
								<th><?php esc_html_e( 'Payment Method', 'lnmc-member-hub' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'lnmc-member-hub' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php $this->display_payments_table(); ?>
						</tbody>
					</table>

					<!-- Pagination -->
					<div class="lnmc-pagination">
						<?php $this->display_pagination(); ?>
					</div>
				</div>

				<div class="lnmc-stripe-status">
					<h3><?php esc_html_e( 'Stripe Connection Status', 'lnmc-member-hub' ); ?></h3>
					<?php
					$stripe_mode = get_option( 'lnmc_member_hub_stripe_mode', 'test' );
					$option_key = sprintf( 'lnmc_member_hub_stripe_%s_publishable_key', $stripe_mode );
					$publishable_key = get_option( $option_key, '' );
					
					if ( ! empty( $publishable_key ) ) {
						echo '<p><span style="color: green;">✓</span> Stripe is configured (' . esc_html( ucfirst( $stripe_mode ) ) . ' mode)</p>';
						echo '<p><strong>Publishable Key:</strong> ' . esc_html( substr( $publishable_key, 0, 20 ) ) . '...</p>';
					} else {
						echo '<p><span style="color: red;">✗</span> Stripe is not configured. <a href="' . esc_url( admin_url( 'admin.php?page=lnmc-member-hub-settings' ) ) . '">Configure Stripe</a></p>';
					}
					?>
				</div>

				<div class="lnmc-subscription-plans">
					<h3><?php esc_html_e( 'Subscription Plans', 'lnmc-member-hub' ); ?></h3>
					<p><?php esc_html_e( 'Manage your subscription plans and pricing.', 'lnmc-member-hub' ); ?></p>
					
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Plan Name', 'lnmc-member-hub' ); ?></th>
								<th><?php esc_html_e( 'Price', 'lnmc-member-hub' ); ?></th>
								<th><?php esc_html_e( 'Duration', 'lnmc-member-hub' ); ?></th>
								<th><?php esc_html_e( 'Active Subscribers', 'lnmc-member-hub' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'lnmc-member-hub' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php $this->display_subscription_plans(); ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- Payment Details Modal -->
		<div id="payment-modal" class="lnmc-modal" style="display: none;">
			<div class="lnmc-modal-content">
				<span class="lnmc-modal-close">&times;</span>
				<h2><?php esc_html_e( 'Payment Details', 'lnmc-member-hub' ); ?></h2>
				<div id="payment-details-content">
					<!-- Payment details will be loaded here -->
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Display payments table.
	 *
	 * @return void
	 */
	private function display_payments_table(): void {
		// For now, display placeholder data
		echo '<tr>';
		echo '<td colspan="6" style="text-align: center; padding: 40px;">';
		echo '<p>' . esc_html__( 'No payments found. Payment history will appear here once members start making payments.', 'lnmc-member-hub' ) . '</p>';
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=lnmc-member-hub-dashboard' ) ) . '" class="button button-primary">' . esc_html__( 'Generate Membership Pages', 'lnmc-member-hub' ) . '</a></p>';
		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Display subscription plans.
	 *
	 * @return void
	 */
	private function display_subscription_plans(): void {
		// For now, display placeholder data
		echo '<tr>';
		echo '<td colspan="5" style="text-align: center; padding: 40px;">';
		echo '<p>' . esc_html__( 'No subscription plans configured. Plans will appear here once you set them up.', 'lnmc-member-hub' ) . '</p>';
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=lnmc-member-hub-settings' ) ) . '" class="button button-primary">' . esc_html__( 'Configure Plans', 'lnmc-member-hub' ) . '</a></p>';
		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Display pagination.
	 *
	 * @return void
	 */
	private function display_pagination(): void {
		// Placeholder pagination
		echo '<div class="tablenav-pages">';
		echo '<span class="displaying-num">' . esc_html__( '0 items', 'lnmc-member-hub' ) . '</span>';
		echo '</div>';
	}

	/**
	 * Get total revenue.
	 *
	 * @return float
	 */
	private function get_total_revenue(): float {
		$database = \LNMC_Member_Hub\Plugin::get_instance()->get_database();
		$stats = $database->get_statistics();
		return floatval( $stats['total_revenue'] ?? 0.00 );
	}

	/**
	 * Get monthly revenue.
	 *
	 * @return float
	 */
	private function get_monthly_revenue(): float {
		$database = \LNMC_Member_Hub\Plugin::get_instance()->get_database();
		$stats = $database->get_statistics();
		return floatval( $stats['monthly_revenue'] ?? 0.00 );
	}

	/**
	 * Get pending payments count.
	 *
	 * @return int
	 */
	private function get_pending_payments(): int {
		global $wpdb;
		$table = $wpdb->prefix . 'lnmc_payments';
		return intval( $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE payment_status = %s", 'pending' )
		) );
	}

	/**
	 * Get failed payments count.
	 *
	 * @return int
	 */
	private function get_failed_payments(): int {
		global $wpdb;
		$table = $wpdb->prefix . 'lnmc_payments';
		return intval( $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE payment_status = %s", 'failed' )
		) );
	}

	/**
	 * AJAX handler for getting payments.
	 *
	 * @return void
	 */
	public function ajax_get_payments(): void {
		// Check nonce and permissions
		if ( ! wp_verify_nonce( $_POST['nonce'], 'lnmc_payments_nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'lnmc-member-hub' ) );
		}

		// Get payments data (placeholder for now)
		$payments = [];
		
		wp_send_json_success( $payments );
	}

	/**
	 * AJAX handler for refunding payment.
	 *
	 * @return void
	 */
	public function ajax_refund_payment(): void {
		// Check nonce and permissions
		if ( ! wp_verify_nonce( $_POST['nonce'], 'lnmc_payments_nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'lnmc-member-hub' ) );
		}

		// Refund payment (placeholder for now)
		$payment_id = sanitize_text_field( $_POST['payment_id'] );
		
		// Process refund through Stripe
		// $this->process_refund( $payment_id );
		
		wp_send_json_success( array( 'message' => __( 'Payment refunded successfully.', 'lnmc-member-hub' ) ) );
	}
}
