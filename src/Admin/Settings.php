<?php
/**
 * Admin Settings Class
 *
 * @package LNMC_Member_Hub
 * @since 1.0.0
 */

namespace LNMC_Member_Hub\Admin;

/**
 * Handles admin settings page and form processing.
 */
class Settings {

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	private const SETTINGS_PAGE = 'lnmc-member-hub';

	/**
	 * Settings group name.
	 *
	 * @var string
	 */
	private const SETTINGS_GROUP = 'lnmc_member_hub_settings';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		// Only initialize settings on plugin pages to save memory
		add_action( 'admin_init', array( $this, 'maybe_init_settings' ) );
	}

	/**
	 * Maybe initialize settings only on plugin pages.
	 *
	 * @return void
	 */
	public function maybe_init_settings(): void {
		$current_page = $_GET['page'] ?? '';
		if ( strpos( $current_page, 'lnmc-member-hub' ) !== false ) {
			$this->init_settings();
		}
	}

	/**
	 * Add settings page to admin menu.
	 *
	 * @return void
	 */
	public function add_settings_page(): void {
		// Add main admin menu
		add_menu_page(
			__( 'LNMC Member Hub', 'lnmc-member-hub' ),
			__( 'LNMC Member Hub', 'lnmc-member-hub' ),
			'manage_options',
			self::SETTINGS_PAGE,
			array( $this, 'render_dashboard_page' ),
			'dashicons-groups',
			30
		);

		// Add submenu pages
		add_submenu_page(
			self::SETTINGS_PAGE,
			__( 'Dashboard', 'lnmc-member-hub' ),
			__( 'Dashboard', 'lnmc-member-hub' ),
			'manage_options',
			self::SETTINGS_PAGE,
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			self::SETTINGS_PAGE,
			__( 'Settings', 'lnmc-member-hub' ),
			__( 'Settings', 'lnmc-member-hub' ),
			'manage_options',
			'lnmc-member-hub-settings',
			array( $this, 'render_settings_page' )
		);

		// Add Pages Management submenu
		add_submenu_page(
			self::SETTINGS_PAGE,
			__( 'Pages', 'lnmc-member-hub' ),
			__( 'Pages', 'lnmc-member-hub' ),
			'manage_options',
			'lnmc-member-hub-pages',
			array( $this, 'render_pages_page' )
		);

		// Add Members submenu
		add_submenu_page(
			self::SETTINGS_PAGE,
			__( 'Members', 'lnmc-member-hub' ),
			__( 'Members', 'lnmc-member-hub' ),
			'manage_options',
			'lnmc-member-hub-members',
			array( $this, 'render_members_page' )
		);

		// Add Payments submenu
		add_submenu_page(
			self::SETTINGS_PAGE,
			__( 'Payments', 'lnmc-member-hub' ),
			__( 'Payments', 'lnmc-member-hub' ),
			'manage_options',
			'lnmc-member-hub-payments',
			array( $this, 'render_payments_page' )
		);
	}

	/**
	 * Initialize settings.
	 *
	 * @return void
	 */
	public function init_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			'lnmc_member_hub_membership_mode',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_membership_mode' ),
				'default'           => 'off',
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			'lnmc_member_hub_webhook_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_webhook_url' ),
				'default'           => '',
			)
		);

		// Stripe Settings
		register_setting(
			self::SETTINGS_GROUP,
			'lnmc_member_hub_stripe_mode',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_stripe_mode' ),
				'default'           => 'test',
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			'lnmc_member_hub_stripe_test_publishable_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_stripe_key' ),
				'default'           => '',
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			'lnmc_member_hub_stripe_test_secret_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_stripe_secret_key' ),
				'default'           => '',
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			'lnmc_member_hub_stripe_live_publishable_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_stripe_key' ),
				'default'           => '',
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			'lnmc_member_hub_stripe_live_secret_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_stripe_secret_key' ),
				'default'           => '',
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			'lnmc_member_hub_stripe_test_webhook_secret',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_webhook_secret' ),
				'default'           => '',
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			'lnmc_member_hub_stripe_live_webhook_secret',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_webhook_secret' ),
				'default'           => '',
			)
		);

		add_settings_section(
			'lnmc_member_hub_general',
			__( 'General Settings', 'lnmc-member-hub' ),
			array( $this, 'render_general_section' ),
			self::SETTINGS_PAGE
		);

		add_settings_section(
			'lnmc_member_hub_stripe',
			__( 'Stripe Payment Settings', 'lnmc-member-hub' ),
			array( $this, 'render_stripe_section' ),
			self::SETTINGS_PAGE
		);

		add_settings_field(
			'lnmc_member_hub_membership_mode',
			__( 'Membership Mode', 'lnmc-member-hub' ),
			array( $this, 'render_membership_mode_field' ),
			self::SETTINGS_PAGE,
			'lnmc_member_hub_general'
		);

		add_settings_field(
			'lnmc_member_hub_webhook_url',
			__( 'Webhook URL', 'lnmc-member-hub' ),
			array( $this, 'render_webhook_url_field' ),
			self::SETTINGS_PAGE,
			'lnmc_member_hub_general'
		);

		// Stripe Fields
		add_settings_field(
			'lnmc_member_hub_stripe_mode',
			__( 'Stripe Mode', 'lnmc-member-hub' ),
			array( $this, 'render_stripe_mode_field' ),
			self::SETTINGS_PAGE,
			'lnmc_member_hub_stripe'
		);

		add_settings_field(
			'lnmc_member_hub_stripe_test_publishable_key',
			__( 'Test Publishable Key', 'lnmc-member-hub' ),
			array( $this, 'render_stripe_test_publishable_key_field' ),
			self::SETTINGS_PAGE,
			'lnmc_member_hub_stripe'
		);

		add_settings_field(
			'lnmc_member_hub_stripe_test_secret_key',
			__( 'Test Secret Key', 'lnmc-member-hub' ),
			array( $this, 'render_stripe_test_secret_key_field' ),
			self::SETTINGS_PAGE,
			'lnmc_member_hub_stripe'
		);

		add_settings_field(
			'lnmc_member_hub_stripe_live_publishable_key',
			__( 'Live Publishable Key', 'lnmc-member-hub' ),
			array( $this, 'render_stripe_live_publishable_key_field' ),
			self::SETTINGS_PAGE,
			'lnmc_member_hub_stripe'
		);

		add_settings_field(
			'lnmc_member_hub_stripe_live_secret_key',
			__( 'Live Secret Key', 'lnmc-member-hub' ),
			array( $this, 'render_stripe_live_secret_key_field' ),
			self::SETTINGS_PAGE,
			'lnmc_member_hub_stripe'
		);

		add_settings_field(
			'lnmc_member_hub_stripe_test_webhook_secret',
			__( 'Test Webhook Secret', 'lnmc-member-hub' ),
			array( $this, 'render_stripe_test_webhook_secret_field' ),
			self::SETTINGS_PAGE,
			'lnmc_member_hub_stripe'
		);

		add_settings_field(
			'lnmc_member_hub_stripe_live_webhook_secret',
			__( 'Live Webhook Secret', 'lnmc-member-hub' ),
			array( $this, 'render_stripe_live_webhook_secret_field' ),
			self::SETTINGS_PAGE,
			'lnmc_member_hub_stripe'
		);
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'lnmc-member-hub' ) );
		}

		// Verify nonce for form submission
		if ( isset( $_POST['submit'] ) && ! wp_verify_nonce( $_POST['_wpnonce'], self::SETTINGS_GROUP . '-options' ) ) {
			wp_die( esc_html__( 'Security check failed. Please try again.', 'lnmc-member-hub' ) );
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<form method="post" action="options.php">
				<?php
				settings_fields( self::SETTINGS_GROUP );
				do_settings_sections( self::SETTINGS_PAGE );
				submit_button();
				?>
			</form>

			<div class="lnmc-member-hub-info">
				<h2><?php esc_html_e( 'Plugin Information', 'lnmc-member-hub' ); ?></h2>
				<p>
					<?php
					printf(
						/* translators: %s: plugin version */
						esc_html__( 'Version: %s', 'lnmc-member-hub' ),
						esc_html( LNMC_MEMBER_HUB_VERSION )
					);
					?>
				</p>
				<p>
					<?php esc_html_e( 'This plugin provides member management functionality for LNMC with REST API endpoints.', 'lnmc-member-hub' ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render general section description.
	 *
	 * @return void
	 */
	public function render_general_section(): void {
		echo '<p>' . esc_html__( 'Configure the basic settings for the LNMC Member Hub plugin.', 'lnmc-member-hub' ) . '</p>';
	}

	/**
	 * Render membership mode field.
	 *
	 * @return void
	 */
	public function render_membership_mode_field(): void {
		$value = get_option( 'lnmc_member_hub_membership_mode', 'off' );
		?>
		<select name="lnmc_member_hub_membership_mode" id="lnmc_member_hub_membership_mode">
			<option value="off" <?php selected( $value, 'off' ); ?>>
				<?php esc_html_e( 'Off', 'lnmc-member-hub' ); ?>
			</option>
			<option value="test" <?php selected( $value, 'test' ); ?>>
				<?php esc_html_e( 'Test', 'lnmc-member-hub' ); ?>
			</option>
			<option value="live" <?php selected( $value, 'live' ); ?>>
				<?php esc_html_e( 'Live', 'lnmc-member-hub' ); ?>
			</option>
		</select>
		<p class="description">
			<?php esc_html_e( 'Select the membership mode: Off (disabled), Test (testing mode), or Live (production mode).', 'lnmc-member-hub' ); ?>
		</p>
		<?php
	}

	/**
	 * Render webhook URL field.
	 *
	 * @return void
	 */
	public function render_webhook_url_field(): void {
		$value = get_option( 'lnmc_member_hub_webhook_url', '' );
		?>
		<input 
			type="url" 
			name="lnmc_member_hub_webhook_url" 
			id="lnmc_member_hub_webhook_url" 
			value="<?php echo esc_attr( $value ); ?>" 
			class="regular-text"
			placeholder="https://example.com/webhook"
			pattern="https://.*"
		/>
		<p class="description">
			<?php esc_html_e( 'Enter the webhook URL for external integrations (HTTPS required, optional).', 'lnmc-member-hub' ); ?>
		</p>
		<?php
	}

	/**
	 * Sanitize membership mode.
	 *
	 * @param string $value The membership mode value.
	 * @return string
	 */
	public function sanitize_membership_mode( string $value ): string {
		$allowed_modes = array( 'off', 'test', 'live' );
		
		if ( ! in_array( $value, $allowed_modes, true ) ) {
			add_settings_error(
				'lnmc_member_hub_membership_mode',
				'invalid_membership_mode',
				__( 'Invalid membership mode selected. Must be Off, Test, or Live.', 'lnmc-member-hub' )
			);
			return 'off';
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Sanitize webhook URL.
	 *
	 * @param string $value The webhook URL value.
	 * @return string
	 */
	public function sanitize_webhook_url( string $value ): string {
		if ( empty( $value ) ) {
			return '';
		}

		$url = esc_url_raw( $value );
		
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			add_settings_error(
				'lnmc_member_hub_webhook_url',
				'invalid_webhook_url',
				__( 'Please enter a valid URL for the webhook.', 'lnmc-member-hub' )
			);
			return '';
		}

		// Ensure HTTPS scheme
		$parsed_url = parse_url( $url );
		if ( ! isset( $parsed_url['scheme'] ) || 'https' !== $parsed_url['scheme'] ) {
			add_settings_error(
				'lnmc_member_hub_webhook_url',
				'invalid_webhook_scheme',
				__( 'Webhook URL must use HTTPS scheme for security.', 'lnmc-member-hub' )
			);
			return '';
		}

		return $url;
	}

	/**
	 * Get membership mode setting.
	 *
	 * @return string
	 */
	public function get_membership_mode(): string {
		return get_option( 'lnmc_member_hub_membership_mode', 'off' );
	}

	/**
	 * Get webhook URL setting.
	 *
	 * @return string
	 */
	public function get_webhook_url(): string {
		return get_option( 'lnmc_member_hub_webhook_url', '' );
	}

	/**
	 * Render Stripe section description.
	 *
	 * @return void
	 */
	public function render_stripe_section(): void {
		echo '<p>' . esc_html__( 'Configure Stripe payment settings for membership payments. Make sure to use test keys for development and live keys for production.', 'lnmc-member-hub' ) . '</p>';
	}

	/**
	 * Sanitize Stripe mode.
	 *
	 * @param string $value The mode value.
	 * @return string
	 */
	public function sanitize_stripe_mode( string $value ): string {
		return in_array( $value, array( 'test', 'live' ), true ) ? $value : 'test';
	}

	/**
	 * Sanitize Stripe publishable key.
	 *
	 * @param string $value The Stripe key value.
	 * @return string
	 */
	public function sanitize_stripe_key( string $value ): string {
		$value = sanitize_text_field( $value );
		
		// Basic validation for Stripe publishable key format
		if ( ! empty( $value ) && ! preg_match( '/^pk_(test|live)_[a-zA-Z0-9]{24}$/', $value ) ) {
			add_settings_error(
				'lnmc_stripe_key',
				'invalid_stripe_key',
				__( 'Invalid Stripe publishable key format. Please check your key.', 'lnmc-member-hub' )
			);
			return '';
		}
		
		return $value;
	}

	/**
	 * Sanitize Stripe secret key.
	 *
	 * @param string $value The Stripe secret key value.
	 * @return string
	 */
	public function sanitize_stripe_secret_key( string $value ): string {
		$value = sanitize_text_field( $value );
		
		// Basic validation for Stripe secret key format
		if ( ! empty( $value ) && strpos( $value, 'encrypted:' ) !== 0 && ! preg_match( '/^sk_(test|live)_[a-zA-Z0-9]{24}$/', $value ) ) {
			add_settings_error(
				'lnmc_stripe_secret_key',
				'invalid_stripe_secret_key',
				__( 'Invalid Stripe secret key format. Please check your key.', 'lnmc-member-hub' )
			);
			return '';
		}
		
		// Encrypt the secret key before storing
		if ( ! empty( $value ) && strpos( $value, 'encrypted:' ) !== 0 ) {
			$value = $this->encrypt_sensitive_data( $value );
		}
		
		return $value;
	}

	/**
	 * Sanitize webhook secret.
	 *
	 * @param string $value The webhook secret value.
	 * @return string
	 */
	public function sanitize_webhook_secret( string $value ): string {
		$value = sanitize_text_field( $value );
		
		// Basic validation for webhook secret format
		if ( ! empty( $value ) && strpos( $value, 'encrypted:' ) !== 0 && ! preg_match( '/^whsec_[a-zA-Z0-9\_]+$/', $value ) ) {
			add_settings_error(
				'lnmc_webhook_secret',
				'invalid_webhook_secret',
				__( 'Invalid webhook secret format. Please check your webhook secret.', 'lnmc-member-hub' )
			);
			return '';
		}
		
		// Encrypt the webhook secret before storing
		if ( ! empty( $value ) && strpos( $value, 'encrypted:' ) !== 0 ) {
			$value = $this->encrypt_sensitive_data( $value );
		}
		
		return $value;
	}

	/**
	 * Encrypt sensitive data.
	 *
	 * @param string $value Value to encrypt.
	 * @return string
	 */
	private function encrypt_sensitive_data( string $value ): string {
		if ( empty( $value ) ) {
			return '';
		}

		$key = $this->get_encryption_key();
		$method = 'AES-256-CBC';
		$iv = openssl_random_pseudo_bytes( openssl_cipher_iv_length( $method ) );
		
		$encrypted = openssl_encrypt( $value, $method, $key, 0, $iv );
		
		return 'encrypted:' . base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt sensitive data.
	 *
	 * @param string $value Value to decrypt.
	 * @return string
	 */
	private function decrypt_sensitive_data( string $value ): string {
		if ( empty( $value ) || strpos( $value, 'encrypted:' ) !== 0 ) {
			return $value;
		}

		$key = $this->get_encryption_key();
		$method = 'AES-256-CBC';
		
		$encrypted_data = base64_decode( substr( $value, 10 ) );
		$iv_length = openssl_cipher_iv_length( $method );
		$iv = substr( $encrypted_data, 0, $iv_length );
		$encrypted = substr( $encrypted_data, $iv_length );
		
		return openssl_decrypt( $encrypted, $method, $key, 0, $iv );
	}

	/**
	 * Get encryption key.
	 *
	 * @return string
	 */
	private function get_encryption_key(): string {
		$key = get_option( 'lnmc_encryption_key' );
		
		if ( empty( $key ) ) {
			$key = wp_generate_password( 32, true, true );
			update_option( 'lnmc_encryption_key', $key );
		}
		
		return $key;
	}

	/**
	 * Render Stripe mode field.
	 *
	 * @return void
	 */
	public function render_stripe_mode_field(): void {
		$value = get_option( 'lnmc_member_hub_stripe_mode', 'test' );
		?>
		<select name="lnmc_member_hub_stripe_mode" id="lnmc_member_hub_stripe_mode">
			<option value="test" <?php selected( $value, 'test' ); ?>>
				<?php esc_html_e( 'Test Mode', 'lnmc-member-hub' ); ?>
			</option>
			<option value="live" <?php selected( $value, 'live' ); ?>>
				<?php esc_html_e( 'Live Mode', 'lnmc-member-hub' ); ?>
			</option>
		</select>
		<p class="description">
			<?php esc_html_e( 'Use test mode for development and live mode for production.', 'lnmc-member-hub' ); ?>
		</p>
		<?php
	}

	/**
	 * Render Stripe test publishable key field.
	 *
	 * @return void
	 */
	public function render_stripe_test_publishable_key_field(): void {
		$value = get_option( 'lnmc_member_hub_stripe_test_publishable_key', '' );
		?>
		<input type="text" 
			   name="lnmc_member_hub_stripe_test_publishable_key" 
			   id="lnmc_member_hub_stripe_test_publishable_key" 
			   value="<?php echo esc_attr( $value ); ?>" 
			   class="regular-text" 
			   placeholder="pk_test_..." />
		<p class="description">
			<?php esc_html_e( 'Enter your Stripe test publishable key (starts with pk_test_).', 'lnmc-member-hub' ); ?>
		</p>
		<?php
	}

	/**
	 * Render Stripe test secret key field.
	 *
	 * @return void
	 */
	public function render_stripe_test_secret_key_field(): void {
		$value = get_option( 'lnmc_member_hub_stripe_test_secret_key', '' );
		$display_value = $this->decrypt_sensitive_data( $value );
		?>
		<input type="password" 
			   name="lnmc_member_hub_stripe_test_secret_key" 
			   id="lnmc_member_hub_stripe_test_secret_key" 
			   value="<?php echo esc_attr( $display_value ); ?>" 
			   class="regular-text" 
			   placeholder="sk_test_..." />
		<p class="description">
			<?php esc_html_e( 'Enter your Stripe test secret key (starts with sk_test_). This will be encrypted for security.', 'lnmc-member-hub' ); ?>
		</p>
		<?php
	}

	/**
	 * Render Stripe test webhook secret field.
	 *
	 * @return void
	 */
	public function render_stripe_test_webhook_secret_field(): void {
		$value = get_option( 'lnmc_member_hub_stripe_test_webhook_secret', '' );
		$display_value = $this->decrypt_sensitive_data( $value );
		?>
		<input type="password" 
			   name="lnmc_member_hub_stripe_test_webhook_secret" 
			   id="lnmc_member_hub_stripe_test_webhook_secret" 
			   value="<?php echo esc_attr( $display_value ); ?>" 
			   class="regular-text" 
			   placeholder="whsec_..." />
		<p class="description">
			<?php esc_html_e( 'Enter your Stripe test webhook secret (starts with whsec_). This will be encrypted for security.', 'lnmc-member-hub' ); ?>
		</p>
		<?php
	}

	/**
	 * Render Stripe live publishable key field.
	 *
	 * @return void
	 */
	public function render_stripe_live_publishable_key_field(): void {
		$value = get_option( 'lnmc_member_hub_stripe_live_publishable_key', '' );
		?>
		<input type="text" 
			   name="lnmc_member_hub_stripe_live_publishable_key" 
			   id="lnmc_member_hub_stripe_live_publishable_key" 
			   value="<?php echo esc_attr( $value ); ?>" 
			   class="regular-text" 
			   placeholder="pk_live_..." />
		<p class="description">
			<?php esc_html_e( 'Enter your Stripe live publishable key (starts with pk_live_).', 'lnmc-member-hub' ); ?>
		</p>
		<?php
	}

	/**
	 * Render Stripe live secret key field.
	 *
	 * @return void
	 */
	public function render_stripe_live_secret_key_field(): void {
		$value = get_option( 'lnmc_member_hub_stripe_live_secret_key', '' );
		$display_value = $this->decrypt_sensitive_data( $value );
		?>
		<input type="password" 
			   name="lnmc_member_hub_stripe_live_secret_key" 
			   id="lnmc_member_hub_stripe_live_secret_key" 
			   value="<?php echo esc_attr( $display_value ); ?>" 
			   class="regular-text" 
			   placeholder="sk_live_..." />
		<p class="description">
			<?php esc_html_e( 'Enter your Stripe live secret key (starts with sk_live_). This will be encrypted for security.', 'lnmc-member-hub' ); ?>
		</p>
		<?php
	}

	/**
	 * Render Stripe live webhook secret field.
	 *
	 * @return void
	 */
	public function render_stripe_live_webhook_secret_field(): void {
		$value = get_option( 'lnmc_member_hub_stripe_live_webhook_secret', '' );
		$display_value = $this->decrypt_sensitive_data( $value );
		?>
		<input type="password" 
			   name="lnmc_member_hub_stripe_live_webhook_secret" 
			   id="lnmc_member_hub_stripe_live_webhook_secret" 
			   value="<?php echo esc_attr( $display_value ); ?>" 
			   class="regular-text" 
			   placeholder="whsec_..." />
		<p class="description">
			<?php esc_html_e( 'Enter your Stripe live webhook secret (starts with whsec_). This will be encrypted for security.', 'lnmc-member-hub' ); ?>
		</p>
		<?php
	}

	/**
	 * Get Stripe mode setting.
	 *
	 * @return string
	 */
	public function get_stripe_mode(): string {
		return get_option( 'lnmc_member_hub_stripe_mode', 'test' );
	}

	/**
	 * Get Stripe test publishable key.
	 *
	 * @return string
	 */
	public function get_stripe_test_publishable_key(): string {
		return get_option( 'lnmc_member_hub_stripe_test_publishable_key', '' );
	}

	/**
	 * Get Stripe test secret key.
	 *
	 * @return string
	 */
	public function get_stripe_test_secret_key(): string {
		$encrypted = get_option( 'lnmc_member_hub_stripe_test_secret_key', '' );
		return $this->decrypt_sensitive_data( $encrypted );
	}

	/**
	 * Get Stripe live publishable key.
	 *
	 * @return string
	 */
	public function get_stripe_live_publishable_key(): string {
		return get_option( 'lnmc_member_hub_stripe_live_publishable_key', '' );
	}

	/**
	 * Get Stripe live secret key.
	 *
	 * @return string
	 */
	public function get_stripe_live_secret_key(): string {
		$encrypted = get_option( 'lnmc_member_hub_stripe_live_secret_key', '' );
		return $this->decrypt_sensitive_data( $encrypted );
	}

	/**
	 * Get Stripe test webhook secret.
	 *
	 * @return string
	 */
	public function get_stripe_test_webhook_secret(): string {
		$encrypted = get_option( 'lnmc_member_hub_stripe_test_webhook_secret', '' );
		return $this->decrypt_sensitive_data( $encrypted );
	}

	/**
	 * Get Stripe live webhook secret.
	 *
	 * @return string
	 */
	public function get_stripe_live_webhook_secret(): string {
		$encrypted = get_option( 'lnmc_member_hub_stripe_live_webhook_secret', '' );
		return $this->decrypt_sensitive_data( $encrypted );
	}

	/**
	 * Generate membership pages.
	 *
	 * @return void
	 */
	private function generate_membership_pages(): void {
		$pages = [
			'membership' => [
				'title' => 'Membership Hub',
				'content' => 'Welcome to our membership community! [lnmc_membership_form]',
				'slug' => 'membership'
			],
			'member-dashboard' => [
				'title' => 'Member Dashboard',
				'content' => '[lnmc_member_dashboard]',
				'slug' => 'member-dashboard'
			],
			'pricing' => [
				'title' => 'Membership Plans',
				'content' => '[lnmc_pricing_table]',
				'slug' => 'pricing'
			],
			'thank-you' => [
				'title' => 'Thank You',
				'content' => 'Thank you for joining our membership! You will receive a confirmation email shortly.',
				'slug' => 'thank-you'
			]
		];

		foreach ( $pages as $key => $page_data ) {
			// Check if page already exists
			$existing_page = get_page_by_path( $page_data['slug'] );
			
			if ( ! $existing_page ) {
				$page_id = wp_insert_post([
					'post_title' => $page_data['title'],
					'post_content' => $page_data['content'],
					'post_status' => 'publish',
					'post_type' => 'page',
					'post_name' => $page_data['slug']
				]);

				if ( $page_id ) {
					// Store page reference in plugin options
					$existing_pages = get_option( 'lnmc_member_hub_pages', [] );
					$existing_pages[ $key ] = $page_id;
					update_option( 'lnmc_member_hub_pages', $existing_pages );
				}
			}
		}
	}

	/**
	 * Get total members count.
	 *
	 * @return int
	 */
	private function get_total_members(): int {
		// For now, return a placeholder. This would connect to your member database
		return 0;
	}

	/**
	 * Get active members count.
	 *
	 * @return int
	 */
	private function get_active_members(): int {
		// For now, return a placeholder. This would connect to your member database
		return 0;
	}

	/**
	 * Get pending payments count.
	 *
	 * @return int
	 */
	private function get_pending_payments(): int {
		// For now, return a placeholder. This would connect to Stripe API
		return 0;
	}

	/**
	 * Get monthly revenue.
	 *
	 * @return float
	 */
	private function get_monthly_revenue(): float {
		// For now, return a placeholder. This would connect to Stripe API
		return 0.00;
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

	/**
	 * Display pages table.
	 *
	 * @return void
	 */
	private function display_pages_table(): void {
		$pages = get_option( 'lnmc_member_hub_pages', [] );
		$page_names = [
			'membership' => 'Membership Hub',
			'member-dashboard' => 'Member Dashboard',
			'pricing' => 'Pricing Plans',
			'thank-you' => 'Thank You Page'
		];

		foreach ( $page_names as $key => $name ) {
			$page_id = isset( $pages[ $key ] ) ? $pages[ $key ] : 0;
			$page = $page_id ? get_post( $page_id ) : null;
			
			echo '<tr>';
			echo '<td>' . esc_html( $name ) . '</td>';
			
			if ( $page && $page->post_status === 'publish' ) {
				echo '<td><a href="' . esc_url( get_permalink( $page_id ) ) . '" target="_blank">' . esc_url( get_permalink( $page_id ) ) . '</a></td>';
				echo '<td><span style="color: green;">✓ Published</span></td>';
				echo '<td><a href="' . esc_url( get_edit_post_link( $page_id ) ) . '" class="button button-small">Edit</a> <a href="' . esc_url( get_permalink( $page_id ) ) . '" target="_blank" class="button button-small">View</a></td>';
			} else {
				echo '<td>-</td>';
				echo '<td><span style="color: red;">✗ Not Created</span></td>';
				echo '<td>-</td>';
			}
			
			echo '</tr>';
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
					<?php $this->display_page_status(); ?>
				</div>
			</div>

			<!-- Recent Activity -->
			<div class="lnmc-recent-activity">
				<h2><?php esc_html_e( 'Recent Activity', 'lnmc-member-hub' ); ?></h2>
				<?php $this->display_recent_activity(); ?>
			</div>
		</div>
		<?php
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
				</div>

				<div class="lnmc-members-table">
					<h3><?php esc_html_e( 'Member List', 'lnmc-member-hub' ); ?></h3>
					<p><?php esc_html_e( 'No members found. Members will appear here once they sign up through the membership form.', 'lnmc-member-hub' ); ?></p>
				</div>
			</div>
		</div>
		<?php
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
						<div class="lnmc-stat-number">$<?php echo esc_html( number_format( $this->get_monthly_revenue(), 2 ) ); ?></div>
					</div>
					<div class="lnmc-stat-card">
						<h3><?php esc_html_e( 'Pending Payments', 'lnmc-member-hub' ); ?></h3>
						<div class="lnmc-stat-number"><?php echo esc_html( $this->get_pending_payments() ); ?></div>
					</div>
				</div>

				<div class="lnmc-payments-table">
					<h3><?php esc_html_e( 'Recent Transactions', 'lnmc-member-hub' ); ?></h3>
					<p><?php esc_html_e( 'No transactions found. Payment history will appear here once members start making payments.', 'lnmc-member-hub' ); ?></p>
				</div>

				<div class="lnmc-stripe-status">
					<h3><?php esc_html_e( 'Stripe Connection Status', 'lnmc-member-hub' ); ?></h3>
					<?php
					// Determine mode from constant or option
					$stripe_mode = defined( 'LNMC_STRIPE_MODE' ) ? LNMC_STRIPE_MODE : get_option( 'lnmc_member_hub_stripe_mode', 'test' );
					// Determine publishable key from constants or options based on mode
					if ( 'live' === $stripe_mode ) {
						$publishable_key = defined( 'LNMC_STRIPE_LIVE_PUBLISHABLE_KEY' ) ? LNMC_STRIPE_LIVE_PUBLISHABLE_KEY : get_option( 'lnmc_member_hub_stripe_live_publishable_key', '' );
					} else {
						$publishable_key = defined( 'LNMC_STRIPE_TEST_PUBLISHABLE_KEY' ) ? LNMC_STRIPE_TEST_PUBLISHABLE_KEY : get_option( 'lnmc_member_hub_stripe_test_publishable_key', '' );
					}
					$has_key = ! empty( $publishable_key );
					?>
					<?php if ( $has_key ) : ?>
						<?php $config_source = ( defined( 'LNMC_STRIPE_TEST_PUBLISHABLE_KEY' ) || defined( 'LNMC_STRIPE_LIVE_PUBLISHABLE_KEY' ) ) ? ' (configured in wp-config.php)' : ' (configured in database)'; ?>
						<p><span style="color: green;">✓</span> <?php echo esc_html( sprintf( 'Stripe is configured (%s mode%s)', ucfirst( $stripe_mode ), $config_source ) ); ?></p>
					<?php else : ?>
						<p><span style="color: red;">✗</span> <?php esc_html_e( 'Stripe is not configured.', 'lnmc-member-hub' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=lnmc-member-hub-settings' ) ); ?>"><?php esc_html_e( 'Configure Stripe', 'lnmc-member-hub' ); ?></a></p>
					<?php endif; ?>
				</div>
	}

	/**
	 * Render pages management page.
	 *
	 * @return void
	 */
	public function render_pages_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'lnmc-member-hub' ) );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Membership Pages Management', 'lnmc-member-hub' ); ?></h1>
			
			<div class="lnmc-pages-overview">
				<h2><?php esc_html_e( 'Membership Pages', 'lnmc-member-hub' ); ?></h2>
				<p><?php esc_html_e( 'Manage the membership pages for your site. These pages are essential for the membership functionality.', 'lnmc-member-hub' ); ?></p>
				
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Page', 'lnmc-member-hub' ); ?></th>
							<th><?php esc_html_e( 'URL', 'lnmc-member-hub' ); ?></th>
							<th><?php esc_html_e( 'Status', 'lnmc-member-hub' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'lnmc-member-hub' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php $this->display_pages_table(); ?>
					</tbody>
				</table>
			</div>

			<div class="lnmc-shortcodes-info">
				<h2><?php esc_html_e( 'Available Shortcodes', 'lnmc-member-hub' ); ?></h2>
				<p><?php esc_html_e( 'Use these shortcodes on any page to add membership functionality:', 'lnmc-member-hub' ); ?></p>
				
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Shortcode', 'lnmc-member-hub' ); ?></th>
							<th><?php esc_html_e( 'Description', 'lnmc-member-hub' ); ?></th>
							<th><?php esc_html_e( 'Example', 'lnmc-member-hub' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>[lnmc_membership_form]</code></td>
							<td><?php esc_html_e( 'Displays the membership signup form', 'lnmc-member-hub' ); ?></td>
							<td><code>[lnmc_membership_form]</code></td>
						</tr>
						<tr>
							<td><code>[lnmc_member_dashboard]</code></td>
							<td><?php esc_html_e( 'Displays the member dashboard', 'lnmc-member-hub' ); ?></td>
							<td><code>[lnmc_member_dashboard]</code></td>
						</tr>
						<tr>
							<td><code>[lnmc_pricing_table]</code></td>
							<td><?php esc_html_e( 'Displays pricing plans', 'lnmc-member-hub' ); ?></td>
							<td><code>[lnmc_pricing_table]</code></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}
}
