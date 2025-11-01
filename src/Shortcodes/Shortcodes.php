<?php

/**
 * Shortcodes Class
 *
 * @package LNMC_Member_Hub
 * @since 1.0.0
 */

namespace LNMC_Member_Hub\Shortcodes;

/**
 * Handles plugin shortcodes for Divi compatibility.
 * 
 * Note: WordPress functions are used without namespace prefix as per WordPress coding standards.
 * IDE linter may show false positives for these functions.
 */
class Shortcodes
{

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->register_shortcodes();
		// Ensure Stripe JS is enqueued when the hub shortcode is present (mirrors CSS logic)
		\add_action('wp_enqueue_scripts', function() {
			$this->enqueue_membership_hub_scripts();
		}, 20);
	}

	/**
	 * Conditionally enqueue Stripe scripts when the membership hub shortcode is in use.
	 *
	 * @return void
	 */
	public function enqueue_membership_hub_scripts(): void
	{
		global $post;

		if (! \is_a($post, 'WP_Post') || ! \has_shortcode($post->post_content, 'lnmc_membership_hub')) {
			return;
		}

		try {
			$plugin = \LNMC_Member_Hub\Plugin::get_instance();
			if (! $plugin) {
				return;
			}

			$stripe_checkout = $plugin->get_stripe_checkout();
			if (! $stripe_checkout || ! \method_exists($stripe_checkout, 'enqueue_stripe_scripts')) {
				return;
			}

			$stripe_checkout->enqueue_stripe_scripts();
		} catch ( \Throwable $e ) {
			\error_log( '[LNMC Member Hub] Failed to enqueue Stripe scripts: ' . $e->getMessage() );
		}
	}

	/**
	 * Register all shortcodes.
	 *
	 * @return void
	 */
	private function register_shortcodes(): void
	{
		add_shortcode('lnmc_member_badge', array($this, 'render_member_badge'));
		add_shortcode('lnmc_member_dashboard', array($this, 'render_member_dashboard'));
		add_shortcode('lnmc_membership_form', array($this, 'render_membership_form'));
		add_shortcode('lnmc_pricing_table', array($this, 'render_pricing_table'));
		add_shortcode('lnmc_subscribe_button', array($this, 'render_subscribe_button'));
		add_shortcode('lnmc_members_only', array($this, 'render_members_only'));
		add_shortcode('lnmc_membership_hub', array($this, 'render_membership_hub'));
		add_shortcode('lnmc_member_profile', array($this, 'render_member_profile'));
		add_shortcode('lnmc_member_resources', array($this, 'render_member_resources'));

		// Enqueue minimal CSS for membership hub
		add_action('wp_enqueue_scripts', array($this, 'enqueue_membership_hub_styles'));
	}

	/**
	 * Render member badge shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_member_badge($atts = array()): string
	{
		// Parse attributes
		$atts = \shortcode_atts(
			array(
				'class' => '',
			),
			$atts,
			'lnmc_member_badge'
		);

		// Check if user is logged in
		if (! \is_user_logged_in()) {
			return '';
		}

		// Get current user
		$user = \wp_get_current_user();
		if (! $user->exists()) {
			return '';
		}

		// Check membership status via filter (default false)
		$is_member = \apply_filters('lnmc/is_member', false, $user->ID);

		// Check subscription status
		$subscription_status = \get_user_meta($user->ID, 'lnmc_subscription_status', true);
		$has_active_subscription = ('active' === $subscription_status);

		// Prepare CSS classes
		$classes = array('lnmc-member-badge');
		if ($has_active_subscription) {
			$classes[] = 'lnmc-member-active';
			$classes[] = 'lnmc-premium-member';
		} elseif ($is_member) {
			$classes[] = 'lnmc-member-active';
		} else {
			$classes[] = 'lnmc-member-inactive';
		}
		if (! empty($atts['class'])) {
			$classes[] = \sanitize_html_class($atts['class']);
		}

		// Prepare status text
		if ($has_active_subscription) {
			$status_text = \esc_html__('Premium', 'lnmc-member-hub');
		} elseif ($is_member) {
			$status_text = \esc_html__('Member Active', 'lnmc-member-hub');
		} else {
			$status_text = \esc_html__('Member Inactive', 'lnmc-member-hub');
		}

		// Build the badge HTML
		$badge_html = \sprintf(
			'<span class="%1$s" role="status" aria-label="%2$s">%3$s</span>',
			\esc_attr(\implode(' ', $classes)),
			\esc_attr($status_text),
			$status_text
		);

		return $badge_html;
	}

	/**
	 * Render member dashboard shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_member_dashboard($atts = array()): string
	{
		// Parse attributes
		$atts = \shortcode_atts(
			array(
				'class' => '',
			),
			$atts,
			'lnmc_member_dashboard'
		);

		// Check if user is logged in
		if (! \is_user_logged_in()) {
			return \sprintf(
				'<div class="lnmc-member-dashboard lnmc-not-logged-in %1$s" role="region" aria-label="%2$s"><p>%3$s</p></div>',
				\esc_attr(\sanitize_html_class($atts['class'])),
				\esc_attr__('Member Dashboard', 'lnmc-member-hub'),
				\esc_html__('Please log in to view your dashboard.', 'lnmc-member-hub')
			);
		}

		// Get current user
		$user = \wp_get_current_user();
		if (! $user->exists()) {
			return '';
		}

		// Prepare CSS classes
		$classes = array('lnmc-member-dashboard');
		if (! empty($atts['class'])) {
			$classes[] = \sanitize_html_class($atts['class']);
		}

		// Get user data
		$username = \esc_html($user->display_name ?: $user->user_login);
		$email    = $this->obfuscate_email($user->user_email);
		$last_login = $this->get_last_login($user->ID);

		// Build the dashboard HTML
		$dashboard_html = \sprintf(
			'<div class="%1$s" role="region" aria-label="%2$s">
				<div class="lnmc-dashboard-card">
					<h3 class="lnmc-dashboard-title">%3$s</h3>
					<div class="lnmc-dashboard-content">
						<div class="lnmc-dashboard-field">
							<strong>%4$s:</strong> %5$s
						</div>
						<div class="lnmc-dashboard-field">
							<strong>%6$s:</strong> %7$s
						</div>',
			\esc_attr(\implode(' ', $classes)),
			\esc_attr__('Member Dashboard', 'lnmc-member-hub'),
			\esc_html__('Member Dashboard', 'lnmc-member-hub'),
			\esc_html__('Username', 'lnmc-member-hub'),
			$username,
			\esc_html__('Email', 'lnmc-member-hub'),
			$email
		);

		// Add last login if available
		if ($last_login) {
			$dashboard_html .= \sprintf(
				'<div class="lnmc-dashboard-field">
					<strong>%1$s:</strong> %2$s
				</div>',
				\esc_html__('Last Login', 'lnmc-member-hub'),
				\esc_html($last_login)
			);
		}

		$dashboard_html .= '</div></div></div>';

		return $dashboard_html;
	}

	/**
	 * Obfuscate email address for security.
	 *
	 * @param string $email The email address to obfuscate.
	 * @return string
	 */
	private function obfuscate_email(string $email): string
	{
		if (empty($email) || ! \is_email($email)) {
			return \esc_html__('Not available', 'lnmc-member-hub');
		}

		$parts = \explode('@', $email);
		if (\count($parts) !== 2) {
			return \esc_html__('Not available', 'lnmc-member-hub');
		}

		$local_part = $parts[0];
		$domain     = $parts[1];

		// Obfuscate local part (show first and last character)
		if (\strlen($local_part) <= 2) {
			$obfuscated_local = $local_part;
		} else {
			$obfuscated_local = \substr($local_part, 0, 1) . '***' . \substr($local_part, -1);
		}

		// Obfuscate domain (show first and last character)
		if (\strlen($domain) <= 2) {
			$obfuscated_domain = $domain;
		} else {
			$obfuscated_domain = \substr($domain, 0, 1) . '***' . \substr($domain, -1);
		}

		return \esc_html($obfuscated_local . '@' . $obfuscated_domain);
	}

	/**
	 * Get user's last login time.
	 *
	 * @param int $user_id The user ID.
	 * @return string|false
	 */
	private function get_last_login(int $user_id)
	{
		$last_login = \get_user_meta($user_id, 'last_login', true);

		if (empty($last_login)) {
			return false;
		}

		// Format the date
		$timestamp = \strtotime($last_login);
		if (false === $timestamp) {
			return false;
		}

		return \date_i18n(\get_option('date_format') . ' ' . \get_option('time_format'), $timestamp);
	}

	/**
	 * Render membership form shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_membership_form($atts = array()): string
	{
		// Parse attributes
		$atts = \shortcode_atts(
			array(
				'class' => '',
				'amount' => '2500', // Default $25.00 in cents
				'currency' => 'usd',
				'title' => __('Join LNMC Membership', 'lnmc-member-hub'),
				'description' => __('Complete your membership payment securely with Stripe.', 'lnmc-member-hub'),
			),
			$atts,
			'lnmc_membership_form'
		);

		// Check if Stripe is configured
		$stripe_checkout = \LNMC_Member_Hub\Plugin::get_instance()->get_stripe_checkout();
		if (! $stripe_checkout->is_configured()) {
			return \sprintf(
				'<div class="lnmc-membership-form-error" style="color: #dc3232; background: #fff; border-left: 4px solid #dc3232; padding: 12px; margin: 10px 0;">
					<strong>%1$s</strong><br>%2$s
				</div>',
				\esc_html__('Payment System Unavailable', 'lnmc-member-hub'),
				\esc_html__('Stripe payment system is not configured. Please contact the administrator.', 'lnmc-member-hub')
			);
		}

		// Get current user email if logged in
		$current_email = '';
		if (\is_user_logged_in()) {
			$user = \wp_get_current_user();
			$current_email = $user->user_email;
		}

		// Prepare CSS classes
		$classes = array('lnmc-membership-form');
		if (! empty($atts['class'])) {
			$classes[] = \sanitize_html_class($atts['class']);
		}

		// Format amount for display
		$display_amount = \number_format(\intval($atts['amount']) / 100, 2);

		// Build the form HTML
		$form_html = \sprintf(
			'<div class="%1$s" role="region" aria-label="%2$s">
				<div class="lnmc-membership-card">
					<h3 class="lnmc-membership-title">%3$s</h3>
					<p class="lnmc-membership-description">%4$s</p>
					
					<form method="post" class="lnmc-membership-form lnmc-membership-form-inner">
						<div class="lnmc-form-field">
							<label for="lnmc-email" class="lnmc-form-label">%5$s *</label>
							<input 
								type="email" 
								id="lnmc-email" 
								name="email" 
								value="%6$s" 
								required 
								class="lnmc-form-input"
								placeholder="%7$s"
							/>
						</div>
						
						<div class="lnmc-form-field">
							<label for="lnmc-amount" class="lnmc-form-label">%8$s</label>
							<input 
								type="hidden" 
								id="lnmc-amount" 
								name="amount" 
								value="%9$s" 
							/>
							<div class="lnmc-amount-display">$%10$s</div>
						</div>
						
						<button type="submit" class="lnmc-submit-button">
							%11$s
						</button>
					</form>
					
					<div class="lnmc-payment-info">
						<p><small>%12$s</small></p>
					</div>
				</div>
			</div>',
			\esc_attr(\implode(' ', $classes)),
			\esc_attr__('Membership Payment Form', 'lnmc-member-hub'),
			\esc_html($atts['title']),
			\esc_html($atts['description']),
			\esc_html__('Email Address', 'lnmc-member-hub'),
			\esc_attr($current_email),
			\esc_attr__('Enter your email address', 'lnmc-member-hub'),
			\esc_html__('Membership Amount', 'lnmc-member-hub'),
			\esc_attr($atts['amount']),
			\esc_html($display_amount),
			\esc_html__('Pay with Card', 'lnmc-member-hub'),
			\esc_html__('Your payment will be processed securely by Stripe. You will be redirected to Stripe to complete your payment.', 'lnmc-member-hub')
		);

		return $form_html;
	}

	/**
	 * Render pricing table shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_pricing_table($atts = array()): string
	{
		// Parse attributes
		$atts = \shortcode_atts(
			array(
				'class' => '',
				'title' => __('Membership Pricing', 'lnmc-member-hub'),
				'description' => __('Choose the membership level that best suits your needs.', 'lnmc-member-hub'),
				'button_text' => __('Join Now', 'lnmc-member-hub'),
				'button_url' => \home_url('/membership'),
				'button_class' => 'lnmc-pricing-button',
			),
			$atts,
			'lnmc_pricing_table'
		);

		// Prepare CSS classes
		$classes = array('lnmc-pricing-table');
		if (! empty($atts['class'])) {
			$classes[] = \sanitize_html_class($atts['class']);
		}

		// Build the pricing table HTML
		$pricing_table_html = \sprintf(
			'<div class="%1$s" role="region" aria-label="%2$s">
				<div class="lnmc-pricing-card">
					<h3 class="lnmc-pricing-title">%3$s</h3>
					<p class="lnmc-pricing-description">%4$s</p>
					
					<div class="lnmc-pricing-features">
						<ul>
							<li><strong>%5$s:</strong> %6$s</li>
							<li><strong>%7$s:</strong> %8$s</li>
							<li><strong>%9$s:</strong> %10$s</li>
							<li><strong>%11$s:</strong> %12$s</li>
						</ul>
					</div>
					
					<a href="%13$s" class="%14$s" role="button">%15$s</a>
				</div>
			</div>',
			\esc_attr(\implode(' ', $classes)),
			\esc_attr__('Membership Pricing', 'lnmc-member-hub'),
			\esc_html($atts['title']),
			\esc_html($atts['description']),
			\esc_html__('Basic Access', 'lnmc-member-hub'),
			\esc_html__('Access to core content and resources.', 'lnmc-member-hub'),
			\esc_html__('Premium Features', 'lnmc-member-hub'),
			\esc_html__('Access to exclusive content, advanced tools, and priority support.', 'lnmc-member-hub'),
			\esc_html__('Community Benefits', 'lnmc-member-hub'),
			\esc_html__('Participation in member-only discussions, exclusive events, and community updates.', 'lnmc-member-hub'),
			\esc_html__('Pricing', 'lnmc-member-hub'),
			\esc_html__('$25.00 / month', 'lnmc-member-hub'),
			\esc_url($atts['button_url']),
			\esc_attr($atts['button_class']),
			\esc_html($atts['button_text'])
		);

		return $pricing_table_html;
	}

	/**
	 * Render subscribe button shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_subscribe_button($atts = array()): string
	{
		// Parse attributes
		$atts = \shortcode_atts(
			array(
				'class' => '',
				'subscribe_text' => __('Subscribe', 'lnmc-member-hub'),
				'manage_text' => __('Manage Billing', 'lnmc-member-hub'),
				'button_class' => 'lnmc-subscribe-button',
			),
			$atts,
			'lnmc_subscribe_button'
		);

		// Check if user is logged in
		if (! \is_user_logged_in()) {
			return \sprintf(
				'<a href="%1$s" class="%2$s %3$s" role="button">%4$s</a>',
				\esc_url(\wp_login_url(\get_permalink())),
				\esc_attr($atts['button_class']),
				\esc_attr(\sanitize_html_class($atts['class'])),
				\esc_html($atts['subscribe_text'])
			);
		}

		// Get current user
		$user = \wp_get_current_user();
		if (! $user->exists()) {
			return '';
		}

		// Check subscription status
		$subscription_status = \get_user_meta($user->ID, 'lnmc_subscription_status', true);
		$subscription_id = \get_user_meta($user->ID, 'lnmc_subscription_id', true);
		$has_active_subscription = ('active' === $subscription_status);

		// Prepare CSS classes
		$classes = array($atts['button_class']);
		if ($has_active_subscription) {
			$classes[] = 'lnmc-manage-billing';
		} else {
			$classes[] = 'lnmc-subscribe';
		}
		if (! empty($atts['class'])) {
			$classes[] = \sanitize_html_class($atts['class']);
		}

		// Determine button text and action
		if ($has_active_subscription) {
			$button_text = $atts['manage_text'];
			$button_url = \home_url('/membership-billing'); // You can customize this URL
			$button_title = __('Manage your subscription billing', 'lnmc-member-hub');
		} else {
			$button_text = $atts['subscribe_text'];
			$button_url = \home_url('/membership'); // You can customize this URL
			$button_title = __('Subscribe to LNMC membership', 'lnmc-member-hub');
		}

		// Build the button HTML
		$button_html = \sprintf(
			'<a href="%1$s" class="%2$s" title="%3$s" role="button">%4$s</a>',
			\esc_url($button_url),
			\esc_attr(\implode(' ', $classes)),
			\esc_attr($button_title),
			\esc_html($button_text)
		);

		return $button_html;
	}

	/**
	 * Render members only content restriction shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @param string $content The content to restrict.
	 * @return string
	 */
	public function render_members_only($atts = array(), $content = ''): string
	{
		// Parse attributes
		$atts = \shortcode_atts(
			array(
				'class' => '',
				'message' => __('This content is only available to active members. Please subscribe to access this content.', 'lnmc-member-hub'),
				'show_login' => 'true',
				'login_text' => __('Log In', 'lnmc-member-hub'),
				'subscribe_text' => __('Subscribe', 'lnmc-member-hub'),
			),
			$atts,
			'lnmc_members_only'
		);

		// Check if user is logged in
		if (! \is_user_logged_in()) {
			$message_html = \sprintf(
				'<div class="lnmc-members-only-message %1$s">
					<p>%2$s</p>',
				\esc_attr(\sanitize_html_class($atts['class'])),
				\esc_html($atts['message'])
			);

			if ('true' === $atts['show_login']) {
				$message_html .= \sprintf(
					'<div class="lnmc-members-only-actions">
						<a href="%1$s" class="lnmc-login-button">%2$s</a>
						<a href="%3$s" class="lnmc-subscribe-button">%4$s</a>
					</div>',
					\esc_url(\wp_login_url(\get_permalink())),
					\esc_html($atts['login_text']),
					\esc_url(\home_url('/membership')),
					\esc_html($atts['subscribe_text'])
				);
			}

			$message_html .= '</div>';
			return $message_html;
		}

		// Get current user
		$user = \wp_get_current_user();
		if (! $user->exists()) {
			return '';
		}

		// Check subscription status
		$subscription_status = \get_user_meta($user->ID, 'lnmc_subscription_status', true);
		$has_active_subscription = ('active' === $subscription_status);

		// If user has active subscription, show the content
		if ($has_active_subscription) {
			return \sprintf(
				'<div class="lnmc-members-only-content %1$s">%2$s</div>',
				\esc_attr(\sanitize_html_class($atts['class'])),
				\do_shortcode($content)
			);
		}

		// User is logged in but doesn't have active subscription
		$message_html = \sprintf(
			'<div class="lnmc-members-only-message lnmc-upgrade-required %1$s">
				<p>%2$s</p>',
			\esc_attr(\sanitize_html_class($atts['class'])),
			\esc_html($atts['message'])
		);

		if ('true' === $atts['show_login']) {
			$message_html .= \sprintf(
				'<div class="lnmc-members-only-actions">
					<a href="%1$s" class="lnmc-subscribe-button">%2$s</a>
				</div>',
				\esc_url(\home_url('/membership')),
				\esc_html($atts['subscribe_text'])
			);
		}

		$message_html .= '</div>';
		return $message_html;
	}

	/**
	 * Render membership hub shortcode (Divi-optimized).
	 * 
	 * This shortcode renders a complete membership landing page with hero,
	 * benefits, pricing, and signup sections. Designed to avoid Divi "Save Failed"
	 * errors by keeping markup compact and using wp_add_inline_style for CSS.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_membership_hub($atts = array()): string
	{
		// Parse attributes with defaults
		$atts = \shortcode_atts(
			array(
				'title'          => __('Welcome to LNMC Member Hub', 'lnmc-member-hub'),
				'subtitle'       => __('Join our exclusive membership community and unlock premium content, resources, and benefits designed just for you.', 'lnmc-member-hub'),
				'pricing_sc'     => '[lnmc_pricing_table]',
				'form_sc'        => '[lnmc_membership_form]',
				'dashboard_url'  => \home_url('/member-dashboard/'),
				'cta_text'       => __('Get Started', 'lnmc-member-hub'),
				'anchor_pricing' => 'plans',
				'class'          => '',
			),
			$atts,
			'lnmc_membership_hub'
		);

		// Prepare CSS classes (namespaced, no et_pb_* collisions)
		$wrapper_classes = array('lnmc-hub-wrapper');
		if (! empty($atts['class'])) {
			$wrapper_classes[] = \sanitize_html_class($atts['class']);
		}

		// Build compact HTML (no large inline styles)
		$html = \sprintf(
			'<div class="%s" role="main" aria-label="%s">',
			\esc_attr(\implode(' ', $wrapper_classes)),
			\esc_attr__('Membership Hub', 'lnmc-member-hub')
		);

		// Hero Section
		$html .= \sprintf(
			'<section class="lnmc-hub-hero">
	            <h1 class="lnmc-hub-title">%s</h1>
	            <p class="lnmc-hub-subtitle">%s</p>
	            <a href="#%s" class="lnmc-hub-cta">%s</a>
	        </section>',
			\esc_html($atts['title']),
			\esc_html($atts['subtitle']),
			\esc_attr($atts['anchor_pricing']),
			\esc_html($atts['cta_text'])
		);

		// Benefits Section
		$html .= \sprintf(
			'<section class="lnmc-hub-benefits">
	            <h2 class="lnmc-hub-section-title">%s</h2>
	            <div class="lnmc-hub-benefits-grid">
	                <div class="lnmc-hub-benefit">
	                    <div class="lnmc-hub-benefit-icon">✨</div>
	                    <h3 class="lnmc-hub-benefit-title">%s</h3>
	                    <p class="lnmc-hub-benefit-desc">%s</p>
	                </div>
	                <div class="lnmc-hub-benefit">
	                    <div class="lnmc-hub-benefit-icon">🎓</div>
	                    <h3 class="lnmc-hub-benefit-title">%s</h3>
	                    <p class="lnmc-hub-benefit-desc">%s</p>
	                </div>
	                <div class="lnmc-hub-benefit">
	                    <div class="lnmc-hub-benefit-icon">👥</div>
	                    <h3 class="lnmc-hub-benefit-title">%s</h3>
	                    <p class="lnmc-hub-benefit-desc">%s</p>
	                </div>
	            </div>
	        </section>',
			\esc_html__('Membership Benefits', 'lnmc-member-hub'),
			\esc_html__('Exclusive Content', 'lnmc-member-hub'),
			\esc_html__('Access premium articles, videos, and resources available only to members.', 'lnmc-member-hub'),
			\esc_html__('Expert Training', 'lnmc-member-hub'),
			\esc_html__('Get access to courses, webinars, and training materials from industry experts.', 'lnmc-member-hub'),
			\esc_html__('Community Access', 'lnmc-member-hub'),
			\esc_html__('Connect with like-minded members in our private community forums.', 'lnmc-member-hub')
		);

		// Pricing Section (nested shortcode)
		$html .= \sprintf(
			'<section class="lnmc-hub-pricing" id="%s">
	            <h2 class="lnmc-hub-section-title">%s</h2>
	            %s
	        </section>',
			\esc_attr($atts['anchor_pricing']),
			\esc_html__('Choose Your Plan', 'lnmc-member-hub'),
			\do_shortcode($atts['pricing_sc'])
		);

		// Signup Section (nested shortcode)
		$html .= \sprintf(
			'<section class="lnmc-hub-signup">
	            <h2 class="lnmc-hub-section-title">%s</h2>
	            <p class="lnmc-hub-signup-desc">%s</p>
	            <div class="lnmc-hub-form-wrapper">%s</div>
	        </section>',
			\esc_html__('Get Started Today', 'lnmc-member-hub'),
			\esc_html__('Ready to join? Sign up now and start enjoying all the benefits of membership!', 'lnmc-member-hub'),
			\do_shortcode($atts['form_sc'])
		);

		// Member Login Section
		$html .= \sprintf(
			'<section class="lnmc-hub-member-login">
	            <h3 class="lnmc-hub-login-title">%s</h3>
	            <a href="%s" class="lnmc-hub-login-link">%s</a>
	        </section>',
			\esc_html__('Already a Member?', 'lnmc-member-hub'),
			\esc_url($atts['dashboard_url']),
			\esc_html__('Access Your Dashboard →', 'lnmc-member-hub')
		);

		$html .= '</div>'; // Close wrapper

		return $html;
	}

	/**
	 * Render member profile shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_member_profile($atts = array()): string
	{
		$atts = \shortcode_atts(
			array(
				'class' => '',
			),
			$atts,
			'lnmc_member_profile'
		);

		if (! \is_user_logged_in()) {
			return '';
		}

		$user = \wp_get_current_user();
		if (! $user->exists()) {
			return '';
		}

		$classes = array('lnmc-member-profile');
		if (! empty($atts['class'])) {
			$classes[] = \sanitize_html_class($atts['class']);
		}

		$display_name = \sanitize_text_field($user->display_name ?: $user->user_login);
		$username     = \esc_html($display_name);
		$avatar_letter = \esc_html(\strtoupper(\substr($display_name, 0, 1)));
		$email         = $this->obfuscate_email($user->user_email);
		$member_since  = \sanitize_text_field((string) \get_user_meta($user->ID, 'lnmc_member_since', true));
		$membership_type = \sanitize_text_field((string) \get_user_meta($user->ID, 'lnmc_membership_type', true));

		$profile_html = \sprintf(
			'<div class="%1$s" role="region" aria-label="%2$s">
				<div class="lnmc-profile-card">
					<div class="lnmc-profile-header">
						<div class="lnmc-profile-avatar">%3$s</div>
						<h3 class="lnmc-profile-username">%4$s</h3>
					</div>
					<div class="lnmc-profile-content">',
			\esc_attr(\implode(' ', $classes)),
			\esc_attr__('Member Profile', 'lnmc-member-hub'),
			$avatar_letter,
			$username
		);

		$profile_html .= \sprintf(
			'<div class="lnmc-profile-field"><strong>%1$s:</strong> %2$s</div>',
			\esc_html__('Email', 'lnmc-member-hub'),
			$email
		);

		if (! empty($member_since)) {
			$profile_html .= \sprintf(
				'<div class="lnmc-profile-field"><strong>%1$s:</strong> %2$s</div>',
				\esc_html__('Member Since', 'lnmc-member-hub'),
				\esc_html($member_since)
			);
		}

		if (! empty($membership_type)) {
			$profile_html .= \sprintf(
				'<div class="lnmc-profile-field"><strong>%1$s:</strong> %2$s</div>',
				\esc_html__('Membership Type', 'lnmc-member-hub'),
				\esc_html(\ucfirst($membership_type))
			);
		}

		$profile_html .= '</div></div></div>';

		return $profile_html;
	}

	/**
	 * Render member resources shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_member_resources($atts = array()): string
	{
		// Parse attributes
		$atts = \shortcode_atts(
			array(
				'class' => '',
				'title' => __('Member Resources', 'lnmc-member-hub'),
				'description' => __('Access exclusive resources available only to our members.', 'lnmc-member-hub'),
			),
			$atts,
			'lnmc_member_resources'
		);

		// Check if user is logged in and is a member
		if (! \is_user_logged_in()) {
			return '';
		}

		$user = \wp_get_current_user();
		if (! $user->exists()) {
			return '';
		}

		// Check membership status
		$is_member = \apply_filters('lnmc/is_member', false, $user->ID);
		if (! $is_member) {
			return '';
		}

		// Prepare CSS classes
		$classes = array('lnmc-member-resources');
		if (! empty($atts['class'])) {
			$classes[] = \sanitize_html_class($atts['class']);
		}

		// Sample resources - in a real implementation, this would come from a database
		$resources = array(
			array(
				'title' => __('Premium Guidebook', 'lnmc-member-hub'),
				'description' => __('Comprehensive guide to getting the most out of your membership.', 'lnmc-member-hub'),
				'link' => '#',
				'icon' => '📚',
			),
			array(
				'title' => __('Video Tutorials', 'lnmc-member-hub'),
				'description' => __('Step-by-step video tutorials for advanced features.', 'lnmc-member-hub'),
				'link' => '#',
				'icon' => '🎥',
			),
			array(
				'title' => __('Community Forum', 'lnmc-member-hub'),
				'description' => __('Connect with other members and share your experiences.', 'lnmc-member-hub'),
				'link' => '#',
				'icon' => '💬',
			),
			array(
				'title' => __('Monthly Webinars', 'lnmc-member-hub'),
				'description' => __('Join our expert-led webinars on industry topics.', 'lnmc-member-hub'),
				'link' => '#',
				'icon' => '📅',
			),
		);

		// Build the resources HTML
		$resources_html = \sprintf(
			'<div class="%1$s" role="region" aria-label="%2$s">
				<h3 class="lnmc-resources-title">%3$s</h3>
				<p class="lnmc-resources-description">%4$s</p>
				<div class="lnmc-resources-grid">',
			\esc_attr(\implode(' ', $classes)),
			\esc_attr__('Member Resources', 'lnmc-member-hub'),
			\esc_html($atts['title']),
			\esc_html($atts['description'])
		);

		foreach ($resources as $resource) {
			$resources_html .= \sprintf(
				'<div class="lnmc-resource-item">
					<div class="lnmc-resource-icon">%1$s</div>
					<h4 class="lnmc-resource-title">%2$s</h4>
					<p class="lnmc-resource-desc">%3$s</p>
					<a href="%4$s" class="lnmc-resource-link">%5$s</a>
				</div>',
				\esc_html($resource['icon']),
				\esc_html($resource['title']),
				\esc_html($resource['description']),
				\esc_url($resource['link']),
				\esc_html__('Access Resource', 'lnmc-member-hub')
			);
		}

		$resources_html .= '</div></div>';

		return $resources_html;
	}

	/**
	 * Enqueue minimal CSS for membership hub via wp_add_inline_style.
	 * This avoids bloating page content and prevents Divi "Save Failed" errors.
	 *
	 * @return void
	 */
	public function enqueue_membership_hub_styles(): void
	{
		// Only enqueue if shortcode is used on the page
		global $post;
		if (! \is_a($post, 'WP_Post') || ! \has_shortcode($post->post_content, 'lnmc_membership_hub')) {
			return;
		}

		// Register a dummy handle
		\wp_register_style('lnmc-hub-styles', false);
		\wp_enqueue_style('lnmc-hub-styles');

		// Minimal, Divi-friendly CSS (namespaced, no conflicts)
		$css = '
        .lnmc-hub-wrapper{max-width:1200px;margin:0 auto;padding:20px}
        .lnmc-hub-hero{text-align:center;padding:60px 20px;background:#f8f9fa;border-radius:10px;margin-bottom:60px}
        .lnmc-hub-title{font-size:48px;font-weight:700;margin-bottom:20px;color:#2c3e50}
        .lnmc-hub-subtitle{font-size:20px;color:#7f8c8d;max-width:700px;margin:0 auto 30px}
        .lnmc-hub-cta{display:inline-block;padding:15px 40px;background:#667eea;color:#fff;text-decoration:none;border-radius:5px;font-weight:600;transition:background .3s}
        .lnmc-hub-cta:hover{background:#764ba2}
        .lnmc-hub-section-title{text-align:center;font-size:36px;font-weight:600;margin-bottom:40px;color:#2c3e50}
        .lnmc-hub-benefits{margin-bottom:60px}
        .lnmc-hub-benefits-grid{display:flex;gap:30px;flex-wrap:wrap;justify-content:center}
        .lnmc-hub-benefit{flex:1;min-width:280px;max-width:350px;padding:30px;text-align:center;background:#fff;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.08);transition:transform .3s}
        .lnmc-hub-benefit:hover{transform:translateY(-5px);box-shadow:0 5px 20px rgba(0,0,0,.15)}
        .lnmc-hub-benefit-icon{font-size:48px;margin-bottom:15px}
        .lnmc-hub-benefit-title{font-size:24px;font-weight:600;margin-bottom:10px;color:#2c3e50}
        .lnmc-hub-benefit-desc{font-size:16px;color:#7f8c8d;line-height:1.6}
        .lnmc-hub-pricing{margin-bottom:60px}
        .lnmc-hub-signup{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);padding:60px 40px;border-radius:15px;text-align:center;margin-bottom:60px}
        .lnmc-hub-signup .lnmc-hub-section-title{color:#fff}
        .lnmc-hub-signup-desc{font-size:18px;color:#fff;margin-bottom:40px;opacity:.9}
        .lnmc-hub-form-wrapper{background:#fff;padding:40px;border-radius:10px;max-width:600px;margin:0 auto}
        .lnmc-hub-member-login{text-align:center;padding:40px 20px;background:#f8f9fa;border-radius:10px}
        .lnmc-hub-login-title{font-size:24px;font-weight:600;margin-bottom:20px;color:#2c3e50}
        .lnmc-hub-login-link{display:inline-block;padding:15px 40px;background:#2c3e50;color:#fff;text-decoration:none;border-radius:5px;font-weight:600;transition:background .3s}
        .lnmc-hub-login-link:hover{background:#34495e}
        
        /* Member Profile Styles */
        .lnmc-member-profile{max-width:600px;margin:2rem auto;background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.1);overflow:hidden}
        .lnmc-profile-card{padding:2rem}
        .lnmc-profile-header{display:flex;align-items:center;margin-bottom:2rem}
        .lnmc-profile-avatar{width:80px;height:80px;border-radius:50%;background:#667eea;color:#fff;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;margin-right:1.5rem}
        .lnmc-profile-username{font-size:1.5rem;font-weight:700;color:#2c3e50;margin:0}
        .lnmc-profile-content{background:#f8f9fa;border-radius:8px;padding:1.5rem}
        .lnmc-profile-field{display:flex;justify-content:space-between;align-items:center;padding:.75rem 0;border-bottom:1px solid #e1e8ed}
        .lnmc-profile-field:last-child{border-bottom:none}
        .lnmc-profile-field strong{color:#2c3e50;font-weight:600}
        
        /* Member Resources Styles */
        .lnmc-member-resources{margin:2rem 0}
        .lnmc-resources-title{font-size:2rem;font-weight:700;color:#2c3e50;margin-bottom:1rem;text-align:center}
        .lnmc-resources-description{text-align:center;color:#7f8c8d;margin-bottom:3rem;font-size:1.1rem}
        .lnmc-resources-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:30px}
        .lnmc-resource-item{background:#fff;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.08);padding:2rem;text-align:center;transition:transform .3s,box-shadow .3s}
        .lnmc-resource-item:hover{transform:translateY(-5px);box-shadow:0 5px 20px rgba(0,0,0,.15)}
        .lnmc-resource-icon{font-size:3rem;margin-bottom:1rem}
        .lnmc-resource-title{font-size:1.5rem;font-weight:600;color:#2c3e50;margin-bottom:.5rem}
        .lnmc-resource-desc{color:#7f8c8d;margin-bottom:1.5rem;line-height:1.6}
        .lnmc-resource-link{display:inline-block;padding:10px 20px;background:#667eea;color:#fff;text-decoration:none;border-radius:5px;font-weight:600;transition:background .3s}
        .lnmc-resource-link:hover{background:#764ba2}
        
        @media (max-width:768px){
            .lnmc-hub-title{font-size:32px}
            .lnmc-hub-section-title{font-size:28px}
            .lnmc-hub-benefits-grid{flex-direction:column}
            .lnmc-resources-grid{grid-template-columns:1fr}
            .lnmc-profile-header{flex-direction:column;text-align:center}
            .lnmc-profile-avatar{margin-right:0;margin-bottom:1rem}
        }
    ';

		\wp_add_inline_style('lnmc-hub-styles', $css);
	}

	/**
	 * Get shortcode documentation.
	 *
	 * @return array
	 */
	public static function get_shortcode_docs(): array
	{
		return array(
			'lnmc_member_badge' => array(
				'description' => __('Displays a small inline badge showing the current user\'s membership status.', 'lnmc-member-hub'),
				'attributes'  => array(
					'class' => __('Additional CSS classes to apply to the badge.', 'lnmc-member-hub'),
				),
				'example'     => '[lnmc_member_badge class="custom-badge"]',
			),
			'lnmc_member_dashboard' => array(
				'description' => __('Displays a dashboard card with user information for logged-in users.', 'lnmc-member-hub'),
				'attributes'  => array(
					'class' => __('Additional CSS classes to apply to the dashboard.', 'lnmc-member-hub'),
				),
				'example'     => '[lnmc_member_dashboard class="custom-dashboard"]',
			),
			'lnmc_membership_form' => array(
				'description' => __('Displays a membership payment form with Stripe integration.', 'lnmc-member-hub'),
				'attributes'  => array(
					'class' => __('Additional CSS classes to apply to the form.', 'lnmc-member-hub'),
					'amount' => __('Payment amount in cents (e.g., 2500 for $25.00).', 'lnmc-member-hub'),
					'currency' => __('Currency code (default: usd).', 'lnmc-member-hub'),
					'title' => __('Form title.', 'lnmc-member-hub'),
					'description' => __('Form description.', 'lnmc-member-hub'),
				),
				'example'     => '[lnmc_membership_form amount="2500" title="Join LNMC" description="Complete your membership payment."]',
			),
			'lnmc_pricing_table' => array(
				'description' => __('Displays a pricing table for LNMC membership options.', 'lnmc-member-hub'),
				'attributes'  => array(
					'class' => __('Additional CSS classes to apply to the pricing table.', 'lnmc-member-hub'),
					'title' => __('Table title.', 'lnmc-member-hub'),
					'description' => __('Table description.', 'lnmc-member-hub'),
					'button_text' => __('Text for the join button.', 'lnmc-member-hub'),
					'button_url' => __('URL for the join button.', 'lnmc-member-hub'),
					'button_class' => __('CSS class for the button element.', 'lnmc-member-hub'),
				),
				'example'     => '[lnmc_pricing_table title="Membership Pricing" description="Choose the membership level that best suits your needs." button_text="Join Now" button_url="/membership" button_class="lnmc-pricing-button"]',
			),
			'lnmc_subscribe_button' => array(
				'description' => __('Displays a subscribe button that changes to "Manage Billing" for active subscribers.', 'lnmc-member-hub'),
				'attributes'  => array(
					'class' => __('Additional CSS classes to apply to the button.', 'lnmc-member-hub'),
					'subscribe_text' => __('Text to show for non-subscribers (default: Subscribe).', 'lnmc-member-hub'),
					'manage_text' => __('Text to show for active subscribers (default: Manage Billing).', 'lnmc-member-hub'),
					'button_class' => __('CSS class for the button element (default: lnmc-subscribe-button).', 'lnmc-member-hub'),
				),
				'example'     => '[lnmc_subscribe_button subscribe_text="Join Now" manage_text="My Account"]',
			),
			'lnmc_membership_hub' => array(
				'description' => __('Renders a complete membership landing page with hero, benefits, pricing, and signup sections. Divi-optimized to avoid "Save Failed" errors.', 'lnmc-member-hub'),
				'attributes'  => array(
					'title'          => __('Hero section title (default: Welcome to LNMC Member Hub).', 'lnmc-member-hub'),
					'subtitle'       => __('Hero section subtitle/description.', 'lnmc-member-hub'),
					'pricing_sc'     => __('Pricing shortcode to render (default: [lnmc_pricing_table]).', 'lnmc-member-hub'),
					'form_sc'        => __('Form shortcode to render (default: [lnmc_membership_form]).', 'lnmc-member-hub'),
					'dashboard_url'  => __('URL for "Already a Member" link (default: /member-dashboard/).', 'lnmc-member-hub'),
					'cta_text'       => __('Call-to-action button text (default: Get Started).', 'lnmc-member-hub'),
					'anchor_pricing' => __('ID for pricing section anchor link (default: plans).', 'lnmc-member-hub'),
					'class'          => __('Additional CSS classes to apply to the wrapper.', 'lnmc-member-hub'),
				),
				'example'     => '[lnmc_membership_hub title="Join LNMC" subtitle="Unlock exclusive benefits" cta_text="Join Now"]',
			),
			'lnmc_member_profile' => array(
				'description' => __('Displays the current member\'s profile information.', 'lnmc-member-hub'),
				'attributes'  => array(
					'class' => __('Additional CSS classes to apply to the profile.', 'lnmc-member-hub'),
				),
				'example'     => '[lnmc_member_profile class="custom-profile"]',
			),
			'lnmc_member_resources' => array(
				'description' => __('Displays a grid of member resources available to logged-in members.', 'lnmc-member-hub'),
				'attributes'  => array(
					'class' => __('Additional CSS classes to apply to the resources section.', 'lnmc-member-hub'),
					'title' => __('Section title.', 'lnmc-member-hub'),
					'description' => __('Section description.', 'lnmc-member-hub'),
				),
				'example'     => '[lnmc_member_resources title="Member Resources" description="Access exclusive content and tools."]',
			),
		);
	}
}
