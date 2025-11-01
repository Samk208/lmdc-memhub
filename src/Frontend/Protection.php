<?php
/**
 * Content Protection Class
 *
 * @package LNMC_Member_Hub
 * @since 1.0.0
 */

namespace LNMC_Member_Hub\Frontend;

/**
 * Content Protection class for restricting access to member-only content.
 */
class Protection {

	/**
	 * Initialize the content protection.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init_protection' ) );
		add_filter( 'the_content', array( $this, 'protect_content' ) );
		add_action( 'wp_head', array( $this, 'add_noindex_for_protected' ) );
		add_filter( 'wp_list_pages_excludes', array( $this, 'exclude_protected_pages' ) );
	}

	/**
	 * Initialize protection hooks.
	 *
	 * @return void
	 */
	public function init_protection(): void {
		// Check if current page/post should be protected
		if ( $this->is_protected_content() && ! $this->user_has_access() ) {
			$this->redirect_to_membership_page();
		}
	}

	/**
	 * Check if current content should be protected.
	 *
	 * @return bool
	 */
	private function is_protected_content(): bool {
		global $post;

		if ( ! $post ) {
			return false;
		}

		// Check if post has protection meta
		$is_protected = get_post_meta( $post->ID, '_lnmc_members_only', true );
		
		// Check if post is in protected category
		$protected_categories = get_option( 'lnmc_protected_categories', array() );
		$post_categories = wp_get_post_categories( $post->ID );
		
		$category_protected = false;
		foreach ( $post_categories as $cat_id ) {
			if ( in_array( $cat_id, $protected_categories, true ) ) {
				$category_protected = true;
				break;
			}
		}

		return $is_protected || $category_protected;
	}

	/**
	 * Check if current user has access to protected content.
	 *
	 * @return bool
	 */
	private function user_has_access(): bool {
		// Allow administrators
		if ( current_user_can( 'administrator' ) ) {
			return true;
		}

		// Check if user is logged in and has active membership
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user_id = get_current_user_id();
		$membership_status = get_user_meta( $user_id, 'lnmc_membership_status', true );

		return $membership_status === 'active';
	}

	/**
	 * Redirect non-members to membership page.
	 *
	 * @return void
	 */
	private function redirect_to_membership_page(): void {
		$membership_page_id = get_option( 'lnmc_membership_page_id' );
		
		if ( $membership_page_id ) {
			$membership_url = get_permalink( $membership_page_id );
			wp_redirect( $membership_url );
			exit;
		} else {
			// Fallback to home page with message
			wp_redirect( home_url( '?membership_required=1' ) );
			exit;
		}
	}

	/**
	 * Protect content by adding membership notice.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function protect_content( string $content ): string {
		global $post;

		if ( ! $post || ! $this->is_protected_content() ) {
			return $content;
		}

		// If user has access, show content
		if ( $this->user_has_access() ) {
			return $content;
		}

		// Show membership notice instead of content
		return $this->get_membership_notice();
	}

	/**
	 * Get membership notice HTML.
	 *
	 * @return string
	 */
	private function get_membership_notice(): string {
		$membership_page_id = get_option( 'lnmc_membership_page_id' );
		$membership_url = $membership_page_id ? get_permalink( $membership_page_id ) : home_url();

		ob_start();
		?>
		<div class="lnmc-membership-notice">
			<div class="lnmc-notice-content">
				<h3><?php esc_html_e( 'Members Only Content', 'lnmc-member-hub' ); ?></h3>
				<p><?php esc_html_e( 'This content is exclusively available to our members. Join our community to access this and much more!', 'lnmc-member-hub' ); ?></p>
				
				<div class="lnmc-notice-benefits">
					<h4><?php esc_html_e( 'Member Benefits:', 'lnmc-member-hub' ); ?></h4>
					<ul>
						<li><?php esc_html_e( 'Exclusive content and resources', 'lnmc-member-hub' ); ?></li>
						<li><?php esc_html_e( 'Member-only events and workshops', 'lnmc-member-hub' ); ?></li>
						<li><?php esc_html_e( 'Networking opportunities', 'lnmc-member-hub' ); ?></li>
						<li><?php esc_html_e( 'Premium support and assistance', 'lnmc-member-hub' ); ?></li>
					</ul>
				</div>

				<div class="lnmc-notice-actions">
					<a href="<?php echo esc_url( $membership_url ); ?>" class="lnmc-join-button">
						<?php esc_html_e( 'Join Now', 'lnmc-member-hub' ); ?>
					</a>
					<?php if ( ! is_user_logged_in() ) : ?>
						<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="lnmc-login-link">
							<?php esc_html_e( 'Already a member? Sign in', 'lnmc-member-hub' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Add noindex meta for protected content.
	 *
	 * @return void
	 */
	public function add_noindex_for_protected(): void {
		if ( $this->is_protected_content() && ! $this->user_has_access() ) {
			echo '<meta name="robots" content="noindex, nofollow" />' . "\n";
		}
	}

	/**
	 * Exclude protected pages from sitemaps and lists.
	 *
	 * @param array $excludes Array of page IDs to exclude.
	 * @return array
	 */
	public function exclude_protected_pages( array $excludes ): array {
		global $wpdb;

		// Get all protected pages
		$protected_pages = $wpdb->get_col(
			"SELECT post_id FROM {$wpdb->postmeta} 
			WHERE meta_key = '_lnmc_members_only' 
			AND meta_value = '1'"
		);

		return array_merge( $excludes, $protected_pages );
	}

	/**
	 * Check if user can access specific content.
	 *
	 * @param int $post_id Post ID to check.
	 * @return bool
	 */
	public static function can_access_content( int $post_id ): bool {
		// Allow administrators
		if ( current_user_can( 'administrator' ) ) {
			return true;
		}

		// Check if post is protected
		$is_protected = get_post_meta( $post_id, '_lnmc_members_only', true );
		if ( ! $is_protected ) {
			return true;
		}

		// Check if user is logged in and has active membership
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user_id = get_current_user_id();
		$membership_status = get_user_meta( $user_id, 'lnmc_membership_status', true );

		return $membership_status === 'active';
	}

	/**
	 * Get protected content preview (first few words).
	 *
	 * @param string $content Full content.
	 * @param int    $word_limit Number of words to show.
	 * @return string
	 */
	public static function get_content_preview( string $content, int $word_limit = 20 ): string {
		$words = explode( ' ', strip_tags( $content ) );
		$preview = array_slice( $words, 0, $word_limit );
		
		return implode( ' ', $preview ) . ( count( $words ) > $word_limit ? '...' : '' );
	}
}
