<?php
/**
 * Frontend Assets Class
 *
 * @package LNMC_Member_Hub
 * @since 1.0.0
 */

namespace LNMC_Member_Hub\Frontend;

/**
 * Handles frontend assets for the plugin.
 */
class Assets {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_conditional_scripts' ) );
	}

	/**
	 * Enqueue scripts only when shortcodes are present.
	 *
	 * @return void
	 */
	public function enqueue_conditional_scripts(): void {
		// Only enqueue on singular pages
		if ( ! is_singular() ) {
			return;
		}

		// Get the current post content
		$post = get_post();
		if ( ! $post ) {
			return;
		}

		// Check if our shortcodes are present (also detect hub which nests the form)
		$has_shortcodes = has_shortcode( $post->post_content, 'lnmc_member_badge' ) ||
						  has_shortcode( $post->post_content, 'lnmc_member_dashboard' ) ||
						  has_shortcode( $post->post_content, 'lnmc_membership_form' ) ||
						  has_shortcode( $post->post_content, 'lnmc_membership_hub' ) ||
						  has_shortcode( $post->post_content, 'lnmc_subscribe_button' ) ||
						  has_shortcode( $post->post_content, 'lnmc_members_only' );

		if ( ! $has_shortcodes ) {
			return;
		}

		// Enqueue the frontend script
		wp_enqueue_script(
			'lnmc-member-hub-frontend',
			LNMC_MEMBER_HUB_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			LNMC_MEMBER_HUB_VERSION,
			true
		);

		// Enqueue frontend styles
		wp_enqueue_style(
			'lnmc-member-hub-frontend',
			LNMC_MEMBER_HUB_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			LNMC_MEMBER_HUB_VERSION
		);

		// Enqueue Stripe checkout styles if membership form is present
		if ( has_shortcode( $post->post_content, 'lnmc_membership_form' ) ) {
			wp_enqueue_style(
				'lnmc-member-hub-stripe-checkout',
				LNMC_MEMBER_HUB_PLUGIN_URL . 'assets/css/stripe-checkout.css',
				array(),
				LNMC_MEMBER_HUB_VERSION
			);
		}

		// Localize the script with necessary data
		wp_localize_script(
			'lnmc-member-hub-frontend',
			'LNMC_Hub',
			array(
				'restUrl' => rest_url(),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * Enqueue frontend scripts and styles.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets(): void {
		// Enqueue frontend styles
		wp_enqueue_style(
			'lnmc-frontend',
			plugin_dir_url( LNMC_MEMBER_HUB_PLUGIN_FILE ) . 'assets/css/frontend.css',
			array(),
			LNMC_MEMBER_HUB_VERSION
		);

		// Enqueue frontend scripts
		wp_enqueue_script(
			'lnmc-frontend',
			plugin_dir_url( LNMC_MEMBER_HUB_PLUGIN_FILE ) . 'assets/js/frontend.js',
			array( 'jquery' ),
			LNMC_MEMBER_HUB_VERSION,
			true
		);

		// Localize script for AJAX
		wp_localize_script(
			'lnmc-frontend',
			'lnmc_ajax',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'lnmc_frontend_nonce' ),
			)
		);

		// Enqueue Stripe.js if on hub/membership pages or when hub/form shortcode present
		$__post = get_post();
		$__content = $__post && isset($__post->post_content) ? $__post->post_content : '';
		if (
			is_page( array( 'membership', 'membership-hub', 'member-dashboard', 'pricing', 'thank-you' ) )
			|| ( ! empty($__content) && ( has_shortcode( $__content, 'lnmc_membership_form' ) || has_shortcode( $__content, 'lnmc_membership_hub' ) ) )
		) {
			wp_enqueue_script(
				'stripe-js',
				'https://js.stripe.com/v3/',
				array(),
				null,
				true
			);

			wp_enqueue_script(
				'lnmc-stripe-checkout',
				plugin_dir_url( LNMC_MEMBER_HUB_PLUGIN_FILE ) . 'assets/js/stripe-checkout.js',
				array( 'jquery', 'stripe-js' ),
				LNMC_MEMBER_HUB_VERSION,
				true
			);

			// Keep compatibility: fallback publishable key (deprecated path)
			$stripe_publishable_key = get_option( 'lnmc_stripe_publishable_key' );
			
			wp_localize_script(
				'lnmc-stripe-checkout',
				'lnmc_stripe',
				array(
					'publishable_key' => $stripe_publishable_key,
					'ajax_url'        => admin_url( 'admin-ajax.php' ),
					'nonce'           => wp_create_nonce( 'lnmc_stripe_checkout' ),
				)
			);
		}
	}
}
