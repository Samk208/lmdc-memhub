<?php
/**
 * Dashboard Class
 *
 * @package LNMC_Member_Hub
 * @since 1.0.0
 */

namespace LNMC_Member_Hub\Admin;

/**
 * Dashboard class for managing the main dashboard functionality.
 */
class Dashboard {

	/**
	 * Initialize the dashboard.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_dashboard_page' ) );
	}

	/**
	 * Add dashboard page to admin menu.
	 *
	 * @return void
	 */
	public function add_dashboard_page(): void {
		add_submenu_page(
			'lnmc-member-hub',
			__( 'Dashboard', 'lnmc-member-hub' ),
			__( 'Dashboard', 'lnmc-member-hub' ),
			'manage_options',
			'lnmc-member-hub-dashboard',
			array( $this, 'render_dashboard_page' )
		);
	}

	/**
	 * Render dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'lnmc-member-hub' ) );
		}

		// Handle page generation
		if ( isset( $_POST['generate_pages'] ) && wp_verify_nonce( $_POST['lnmc_nonce'], 'generate_pages' ) ) {
			$this->generate_membership_pages();
			self::repair_pages_option();
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Membership pages generated successfully!', 'lnmc-member-hub' ) . '</p></div>';
		}

		// Get membership statistics
		$total_members = $this->get_total_members();
		$active_members = $this->get_active_members();
		$pending_payments = $this->get_pending_payments();
		$monthly_revenue = $this->get_monthly_revenue();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'LNMC Member Hub Dashboard', 'lnmc-member-hub' ); ?></h1>
			
			<!-- Quick Stats -->
			<div class="lnmc-stats-grid">
				<div class="lnmc-stat-card">
					<h3><?php esc_html_e( 'Total Members', 'lnmc-member-hub' ); ?></h3>
					<div class="lnmc-stat-number"><?php echo esc_html( $total_members ); ?></div>
				</div>
				<div class="lnmc-stat-card">
					<h3><?php esc_html_e( 'Active Members', 'lnmc-member-hub' ); ?></h3>
					<div class="lnmc-stat-number"><?php echo esc_html( $active_members ); ?></div>
				</div>
				<div class="lnmc-stat-card">
					<h3><?php esc_html_e( 'Pending Payments', 'lnmc-member-hub' ); ?></h3>
					<div class="lnmc-stat-number"><?php echo esc_html( $pending_payments ); ?></div>
				</div>
				<div class="lnmc-stat-card">
					<h3><?php esc_html_e( 'Monthly Revenue', 'lnmc-member-hub' ); ?></h3>
					<div class="lnmc-stat-number">$<?php echo esc_html( number_format( $monthly_revenue, 2 ) ); ?></div>
				</div>
			</div>

			<!-- Quick Actions -->
			<div class="lnmc-quick-actions">
				<h2><?php esc_html_e( 'Quick Actions', 'lnmc-member-hub' ); ?></h2>
				<div class="lnmc-action-buttons">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=lnmc-member-hub-members' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'View Members', 'lnmc-member-hub' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=lnmc-member-hub-payments' ) ); ?>" class="button button-secondary">
						<?php esc_html_e( 'View Payments', 'lnmc-member-hub' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=lnmc-member-hub-pages' ) ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Manage Pages', 'lnmc-member-hub' ); ?>
					</a>
				</div>
			</div>

			<!-- Recent Activity -->
			<div class="lnmc-recent-activity">
				<h2><?php esc_html_e( 'Recent Activity', 'lnmc-member-hub' ); ?></h2>
				<div class="lnmc-activity-list">
					<?php
					$recent_activities = $this->get_recent_activities();
					if ( ! empty( $recent_activities ) ) :
						foreach ( $recent_activities as $activity ) :
							?>
							<div class="lnmc-activity-item">
								<span class="lnmc-activity-icon"><?php echo esc_html( $activity['icon'] ); ?></span>
								<div class="lnmc-activity-content">
									<div class="lnmc-activity-text"><?php echo esc_html( $activity['text'] ); ?></div>
									<div class="lnmc-activity-time"><?php echo esc_html( $activity['time'] ); ?></div>
								</div>
							</div>
							<?php
						endforeach;
					else :
						?>
						<p><?php esc_html_e( 'No recent activity to display.', 'lnmc-member-hub' ); ?></p>
						<?php
					endif;
					?>
				</div>
			</div>

			<!-- Page Generation -->
			<div class="lnmc-page-generation">
				<h2><?php esc_html_e( 'Setup Membership Pages', 'lnmc-member-hub' ); ?></h2>
				<p><?php esc_html_e( 'Generate the essential membership pages for your site. These pages will be created automatically with the proper shortcodes.', 'lnmc-member-hub' ); ?></p>
				
				<form method="post" action="">
					<?php wp_nonce_field( 'generate_pages', 'lnmc_nonce' ); ?>
					<button type="submit" name="generate_pages" class="button button-primary">
						<?php esc_html_e( 'Generate Membership Pages', 'lnmc-member-hub' ); ?>
					</button>
				</form>

				<div class="lnmc-pages-status">
					<h3><?php esc_html_e( 'Page Status', 'lnmc-member-hub' ); ?></h3>
					<?php self::repair_pages_option(); $this->display_page_status(); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Generate membership pages with Divi Builder layouts.
	 *
	 * @return void
	 */
	private function generate_membership_pages(): void {
		self::generate_membership_pages_canonical();
	}

	public static function generate_membership_pages_canonical(): array {
		$ids = array();
		$ids['membership']      = self::ensure_page('membership', 'Membership Hub', '[lnmc_membership_hub]');
		$ids['member-dashboard'] = self::ensure_page('member-dashboard', 'Member Dashboard', '[lnmc_members_only][lnmc_member_dashboard][/lnmc_members_only]');
		$ids['pricing']         = self::ensure_page('pricing', 'Membership Plans', '[lnmc_pricing_table]');
		$ids['thank-you']       = self::ensure_page('thank-you', 'Thank You', "<!-- noindex: set via SEO plugin -->\n<h2>Thanks! 🎉</h2>\n<p><a href=\"/member-dashboard/\">Go to your member dashboard →</a></p>");

		update_option('lnmc_member_hub_pages', $ids);
		return $ids;
	}

	private static function ensure_page(string $slug, string $title, string $content): int {
		$page = get_page_by_path($slug, OBJECT, 'page');
		if ( ! $page ) {
			$q = new \WP_Query(array(
				'name' => $slug,
				'post_type' => 'page',
				'post_status' => 'any',
				'posts_per_page' => 1,
			));
			if ( $q->have_posts() ) {
				$page = $q->posts[0];
			}
		}

		$maybe_error = null;
		if ( $page ) {
			if ( 'trash' === $page->post_status ) {
				wp_untrash_post( $page->ID );
			}
			$maybe_error = wp_update_post(array(
				'ID' => $page->ID,
				'post_title' => $title,
				'post_content' => $content,
				'post_status' => 'publish',
				'post_name' => $slug,
				'post_type' => 'page',
			), true);
			// Retry without forcing slug if conflict produced an error
			if ( is_wp_error( $maybe_error ) ) {
				$maybe_error = wp_update_post(array(
					'ID' => $page->ID,
					'post_title' => $title,
					'post_content' => $content,
					'post_status' => 'publish',
					'post_type' => 'page',
				), true);
			}
			$id = (int) $page->ID;
		} else {
			$maybe_error = wp_insert_post(array(
				'post_title' => $title,
				'post_content' => $content,
				'post_status' => 'publish',
				'post_type' => 'page',
				'post_name' => $slug,
			), true);
			if ( is_wp_error( $maybe_error ) ) {
				$maybe_error = wp_insert_post(array(
					'post_title' => $title,
					'post_content' => $content,
					'post_status' => 'publish',
					'post_type' => 'page',
				), true);
			}
			$id = (int) $maybe_error;
		}

		if ( $id && ! is_wp_error( $maybe_error ) ) {
			update_post_meta( $id, '_et_pb_use_builder', 'on' );
			update_post_meta( $id, '_et_pb_page_layout', 'et_no_sidebar' );
			update_post_meta( $id, '_wp_page_template', 'et_fullwidth_page.php' );
		}

		return (int) $id;
	}

	/**
	 * Get Divi layout for Membership Hub page.
	 *
	 * @return string
	 */
	private function get_membership_hub_divi_layout(): string {
		return '[lnmc_membership_hub]';
	}

	/**
	 * Get Divi layout for Member Dashboard page.
	 *
	 * @return string
	 */
	private function get_member_dashboard_divi_layout(): string {
		return '[lnmc_members_only][lnmc_member_dashboard][/lnmc_members_only]';
	}

	/**
	 * Get Divi layout for Pricing Plans page.
	 *
	 * @return string
	 */
	private function get_pricing_plans_divi_layout(): string {
		return '[lnmc_pricing_table]';
	}

	/**
	 * Get Divi layout for Thank You page.
	 *
	 * @return string
	 */
	private function get_thank_you_divi_layout(): string {
		return '<!-- noindex: set via SEO plugin -->\n<h2>Thanks! 🎉</h2>\n<p><a href="/member-dashboard/">Go to your member dashboard →</a></p>';
	}

	/**
	 * Get total members count.
	 *
	 * @return int
	 */
	private function get_total_members(): int {
		// Get users with membership meta
		$args = array(
			'meta_query' => array(
				array(
					'key'     => 'lnmc_membership_status',
					'compare' => 'EXISTS',
				),
			),
			'count_total' => true,
		);
		
		$user_query = new \WP_User_Query( $args );
		return $user_query->get_total();
	}

	/**
	 * Get active members count.
	 *
	 * @return int
	 */
	private function get_active_members(): int {
		$args = array(
			'meta_query' => array(
				array(
					'key'     => 'lnmc_membership_status',
					'value'   => 'active',
					'compare' => '=',
				),
			),
			'count_total' => true,
		);
		
		$user_query = new \WP_User_Query( $args );
		return $user_query->get_total();
	}

	/**
	 * Get pending payments count.
	 *
	 * @return int
	 */
	private function get_pending_payments(): int {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'lnmc_payments';
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE status = %s",
				'pending'
			)
		);
		
		return (int) $count;
	}

	/**
	 * Get monthly revenue.
	 *
	 * @return float
	 */
	private function get_monthly_revenue(): float {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'lnmc_payments';
		$current_month = date( 'Y-m' );
		
		$revenue = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(amount) FROM {$table_name} 
				WHERE status = %s 
				AND DATE_FORMAT(created_at, '%%Y-%%m') = %s",
				'completed',
				$current_month
			)
		);
		
		return (float) $revenue;
	}

	/**
	 * Get recent activities for dashboard.
	 *
	 * @return array
	 */
	private function get_recent_activities(): array {
		global $wpdb;
		
		$activities = array();
		
		// Get recent payments
		$table_name = $wpdb->prefix . 'lnmc_payments';
		$recent_payments = $wpdb->get_results(
			"SELECT * FROM {$table_name} 
			ORDER BY created_at DESC 
			LIMIT 5"
		);
		
		foreach ( $recent_payments as $payment ) {
			$user = get_user_by( 'id', $payment->user_id );
			$user_name = $user ? $user->display_name : 'Unknown User';
			
			$activities[] = array(
				'icon' => '💳',
				'text' => sprintf(
					__( 'Payment %s from %s - $%s', 'lnmc-member-hub' ),
					$payment->status,
					$user_name,
					number_format( $payment->amount, 2 )
				),
				'time' => human_time_diff( strtotime( $payment->created_at ), current_time( 'timestamp' ) ) . ' ago',
			);
		}
		
		// Get recent member registrations
		$recent_members = get_users( array(
			'meta_query' => array(
				array(
					'key'     => 'lnmc_membership_status',
					'compare' => 'EXISTS',
				),
			),
			'number' => 5,
			'orderby' => 'registered',
			'order' => 'DESC',
		) );
		
		foreach ( $recent_members as $member ) {
			$activities[] = array(
				'icon' => '👤',
				'text' => sprintf(
					__( 'New member joined: %s', 'lnmc-member-hub' ),
					$member->display_name
				),
				'time' => human_time_diff( strtotime( $member->user_registered ), current_time( 'timestamp' ) ) . ' ago',
			);
		}
		
		// Sort by time (most recent first)
		usort( $activities, function( $a, $b ) {
			return strtotime( $b['time'] ) - strtotime( $a['time'] );
		} );
		
		return array_slice( $activities, 0, 10 );
	}

	/**
	 * Display page status.
	 *
	 * @return void
	 */
	private function display_page_status(): void {
		$pages = get_option( 'lnmc_member_hub_pages', [] );
		$page_names = [
			'membership' => 'Membership Hub',
			'member-dashboard' => 'Member Dashboard',
			'pricing' => 'Pricing Plans',
			'thank-you' => 'Thank You Page'
		];

		echo '<ul>';
		foreach ( $page_names as $key => $name ) {
			$page_id = isset( $pages[ $key ] ) ? $pages[ $key ] : 0;
			$page = $page_id ? get_post( $page_id ) : null;
			
			if ( $page && $page->post_status === 'publish' ) {
				echo '<li><span style="color: green;">✓</span> ' . esc_html( $name ) . ' - <a href="' . esc_url( get_permalink( $page_id ) ) . '" target="_blank">View Page</a></li>';
			} else {
				echo '<li><span style="color: red;">✗</span> ' . esc_html( $name ) . ' - Not created</li>';
			}
		}
		echo '</ul>';
	}

	private static function repair_pages_option(): void {
		$opt = get_option( 'lnmc_member_hub_pages', [] );
		$slugs = [
			'membership' => 'Membership Hub',
			'member-dashboard' => 'Member Dashboard',
			'pricing' => 'Membership Plans',
			'thank-you' => 'Thank You'
		];
		$changed = false;
		foreach ( $slugs as $slug => $title ) {
			$page = get_page_by_path( $slug, OBJECT, 'page' );
			if ( $page && $page->post_status === 'publish' ) {
				if ( ! isset( $opt[ $slug ] ) || (int) $opt[ $slug ] !== (int) $page->ID ) {
					$opt[ $slug ] = (int) $page->ID;
					$changed = true;
				}
			}
		}
		if ( $changed ) {
			update_option( 'lnmc_member_hub_pages', $opt );
		}
	}

	/**
	 * Display recent activity.
	 *
	 * @return void
	 */
	private function display_recent_activity(): void {
		// For now, display placeholder activity
		echo '<ul>';
		echo '<li>Plugin activated - ' . date( 'M j, Y' ) . '</li>';
		echo '<li>Settings configured - ' . date( 'M j, Y' ) . '</li>';
		echo '</ul>';
	}
}
