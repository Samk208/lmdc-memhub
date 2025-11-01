<?php
/**
 * Members Class
 *
 * @package LNMC_Member_Hub
 * @since 1.0.0
 */

namespace LNMC_Member_Hub\Admin;

/**
 * Members class for managing member data and subscriptions.
 */
class Members {

	/**
	 * Initialize the members management.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_members_page' ) );
		add_action( 'wp_ajax_lnmc_get_members', array( $this, 'ajax_get_members' ) );
		add_action( 'wp_ajax_lnmc_update_member', array( $this, 'ajax_update_member' ) );
	}

	/**
	 * Add members page to admin menu.
	 *
	 * @return void
	 */
	public function add_members_page(): void {
		add_submenu_page(
			'lnmc-member-hub',
			__( 'Members', 'lnmc-member-hub' ),
			__( 'Members', 'lnmc-member-hub' ),
			'manage_options',
			'lnmc-member-hub-members',
			array( $this, 'render_members_page' )
		);
	}

	/**
	 * Render members page.
	 *
	 * @return void
	 */
	public function render_members_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'lnmc-member-hub' ) );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Members Management', 'lnmc-member-hub' ); ?></h1>
			
			<div class="lnmc-members-overview">
				<h2><?php esc_html_e( 'Members', 'lnmc-member-hub' ); ?></h2>
				<p><?php esc_html_e( 'Manage your membership users and their subscriptions.', 'lnmc-member-hub' ); ?></p>
				
				<div class="lnmc-members-stats">
					<div class="lnmc-stat-card">
						<h3><?php esc_html_e( 'Total Members', 'lnmc-member-hub' ); ?></h3>
						<div class="lnmc-stat-number"><?php echo esc_html( $this->get_total_members() ); ?></div>
					</div>
					<div class="lnmc-stat-card">
						<h3><?php esc_html_e( 'Active Members', 'lnmc-member-hub' ); ?></h3>
						<div class="lnmc-stat-number"><?php echo esc_html( $this->get_active_members() ); ?></div>
					</div>
					<div class="lnmc-stat-card">
						<h3><?php esc_html_e( 'New This Month', 'lnmc-member-hub' ); ?></h3>
						<div class="lnmc-stat-number"><?php echo esc_html( $this->get_new_members_this_month() ); ?></div>
					</div>
				</div>

				<div class="lnmc-members-table">
					<h3><?php esc_html_e( 'Member List', 'lnmc-member-hub' ); ?></h3>
					
					<!-- Search and Filter -->
					<div class="lnmc-members-filters">
						<input type="text" id="member-search" placeholder="<?php esc_attr_e( 'Search members...', 'lnmc-member-hub' ); ?>" class="regular-text">
						<select id="member-status-filter">
							<option value=""><?php esc_html_e( 'All Statuses', 'lnmc-member-hub' ); ?></option>
							<option value="active"><?php esc_html_e( 'Active', 'lnmc-member-hub' ); ?></option>
							<option value="inactive"><?php esc_html_e( 'Inactive', 'lnmc-member-hub' ); ?></option>
							<option value="pending"><?php esc_html_e( 'Pending', 'lnmc-member-hub' ); ?></option>
						</select>
						<button type="button" id="export-members" class="button button-secondary">
							<?php esc_html_e( 'Export CSV', 'lnmc-member-hub' ); ?>
						</button>
					</div>

					<!-- Members Table -->
					<table class="wp-list-table widefat fixed striped" id="members-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Name', 'lnmc-member-hub' ); ?></th>
								<th><?php esc_html_e( 'Email', 'lnmc-member-hub' ); ?></th>
								<th><?php esc_html_e( 'Status', 'lnmc-member-hub' ); ?></th>
								<th><?php esc_html_e( 'Plan', 'lnmc-member-hub' ); ?></th>
								<th><?php esc_html_e( 'Join Date', 'lnmc-member-hub' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'lnmc-member-hub' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php $this->display_members_table(); ?>
						</tbody>
					</table>

					<!-- Pagination -->
					<div class="lnmc-pagination">
						<?php $this->display_pagination(); ?>
					</div>
				</div>
			</div>
		</div>

		<!-- Member Details Modal -->
		<div id="member-modal" class="lnmc-modal" style="display: none;">
			<div class="lnmc-modal-content">
				<span class="lnmc-modal-close">&times;</span>
				<h2><?php esc_html_e( 'Member Details', 'lnmc-member-hub' ); ?></h2>
				<div id="member-details-content">
					<!-- Member details will be loaded here -->
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Display members table.
	 *
	 * @return void
	 */
	private function display_members_table(): void {
		// Get database instance
		$database = \LNMC_Member_Hub\Plugin::get_instance()->get_database();
		
		// Get current page and filters
		$page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
		
		// Build query arguments
		$args = array(
			'page' => $page,
			'per_page' => 20,
			'search' => $search,
			'status' => $status_filter,
		);
		
		// Get members
		$members_data = $database->get_members( $args );
		$members = $members_data['members'];
		$total = $members_data['total'];
		$total_pages = $members_data['total_pages'];
		
		if ( empty( $members ) ) {
			echo '<tr>';
			echo '<td colspan="6" style="text-align: center; padding: 40px;">';
			echo '<p>' . esc_html__( 'No members found.', 'lnmc-member-hub' ) . '</p>';
			echo '</td>';
			echo '</tr>';
			return;
		}
		
		foreach ( $members as $member ) {
			$user_data = $member->user_data ?? array();
			$display_name = $user_data['display_name'] ?? __( 'Unknown User', 'lnmc-member-hub' );
			$email = $user_data['user_email'] ?? '';
			$status = $member->membership_status;
			$membership_type = $member->membership_type;
			$join_date = $member->join_date;
			
			// Status badge
			$status_class = 'lnmc-status-' . $status;
			$status_text = ucfirst( $status );
			
			// Membership type badge
			$type_class = 'lnmc-type-' . $membership_type;
			$type_text = ucfirst( $membership_type );
			
			echo '<tr>';
			echo '<td><strong>' . esc_html( $display_name ) . '</strong></td>';
			echo '<td>' . esc_html( $email ) . '</td>';
			echo '<td><span class="lnmc-status-badge ' . esc_attr( $status_class ) . '">' . esc_html( $status_text ) . '</span></td>';
			echo '<td><span class="lnmc-type-badge ' . esc_attr( $type_class ) . '">' . esc_html( $type_text ) . '</span></td>';
			echo '<td>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $join_date ) ) ) . '</td>';
			echo '<td>';
			echo '<a href="#" class="button button-small view-member" data-member-id="' . esc_attr( $member->id ) . '">' . esc_html__( 'View', 'lnmc-member-hub' ) . '</a> ';
			echo '<a href="#" class="button button-small edit-member" data-member-id="' . esc_attr( $member->id ) . '">' . esc_html__( 'Edit', 'lnmc-member-hub' ) . '</a>';
			echo '</td>';
			echo '</tr>';
		}
	}

	/**
	 * Display pagination.
	 *
	 * @return void
	 */
	private function display_pagination(): void {
		// Get database instance
		$database = \LNMC_Member_Hub\Plugin::get_instance()->get_database();
		
		// Get current page and filters
		$page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
		
		// Build query arguments
		$args = array(
			'page' => $page,
			'per_page' => 20,
			'search' => $search,
			'status' => $status_filter,
		);
		
		// Get members data for pagination
		$members_data = $database->get_members( $args );
		$total = $members_data['total'];
		$total_pages = $members_data['total_pages'];
		
		echo '<div class="tablenav-pages">';
		echo '<span class="displaying-num">' . sprintf( esc_html( _n( '%s item', '%s items', $total, 'lnmc-member-hub' ) ), number_format_i18n( $total ) ) . '</span>';
		
		if ( $total_pages > 1 ) {
			echo '<span class="pagination-links">';
			
			// Previous page
			if ( $page > 1 ) {
				$prev_url = add_query_arg( 'paged', $page - 1 );
				echo '<a class="prev-page" href="' . esc_url( $prev_url ) . '">‹</a>';
			}
			
			// Page numbers
			$start_page = max( 1, $page - 2 );
			$end_page = min( $total_pages, $page + 2 );
			
			for ( $i = $start_page; $i <= $end_page; $i++ ) {
				if ( $i === $page ) {
					echo '<span class="paging-input">';
					echo '<span class="tablenav-paging-text">' . $i . ' <span class="tablenav-paging-text">' . esc_html__( 'of', 'lnmc-member-hub' ) . ' <span class="total-pages">' . $total_pages . '</span></span></span>';
					echo '</span>';
				} else {
					$page_url = add_query_arg( 'paged', $i );
					echo '<a class="paging-input" href="' . esc_url( $page_url ) . '">' . $i . '</a>';
				}
			}
			
			// Next page
			if ( $page < $total_pages ) {
				$next_url = add_query_arg( 'paged', $page + 1 );
				echo '<a class="next-page" href="' . esc_url( $next_url ) . '">›</a>';
			}
			
			echo '</span>';
		}
		
		echo '</div>';
	}

	/**
	 * Get total members count.
	 *
	 * @return int
	 */
	private function get_total_members(): int {
		$database = \LNMC_Member_Hub\Plugin::get_instance()->get_database();
		$stats = $database->get_statistics();
		return intval( $stats['total_members'] ?? 0 );
	}

	/**
	 * Get active members count.
	 *
	 * @return int
	 */
	private function get_active_members(): int {
		$database = \LNMC_Member_Hub\Plugin::get_instance()->get_database();
		$stats = $database->get_statistics();
		return intval( $stats['active_members'] ?? 0 );
	}

	/**
	 * Get new members this month.
	 *
	 * @return int
	 */
	private function get_new_members_this_month(): int {
		$database = \LNMC_Member_Hub\Plugin::get_instance()->get_database();
		$stats = $database->get_statistics();
		return intval( $stats['new_members_this_month'] ?? 0 );
	}

	/**
	 * AJAX handler for getting members.
	 *
	 * @return void
	 */
	public function ajax_get_members(): void {
		// Check nonce and permissions
		if ( ! wp_verify_nonce( $_POST['nonce'], 'lnmc_members_nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'lnmc-member-hub' ) );
		}

		// Get members data (placeholder for now)
		$members = [];
		
		wp_send_json_success( $members );
	}

	/**
	 * AJAX handler for updating member.
	 *
	 * @return void
	 */
	public function ajax_update_member(): void {
		// Check nonce and permissions
		if ( ! wp_verify_nonce( $_POST['nonce'], 'lnmc_members_nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'lnmc-member-hub' ) );
		}

		// Update member data (placeholder for now)
		$member_id = intval( $_POST['member_id'] );
		$status = sanitize_text_field( $_POST['status'] );
		
		// Update member status
		// $this->update_member_status( $member_id, $status );
		
		wp_send_json_success( array( 'message' => __( 'Member updated successfully.', 'lnmc-member-hub' ) ) );
	}
}
