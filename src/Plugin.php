<?php
/**
 * Main Plugin Class
 *
 * @package LNMC_Member_Hub
 * @since 1.0.0
 */

namespace LNMC_Member_Hub;

/**
 * Main plugin class that initializes all components.
 */
class Plugin {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Admin settings instance.
	 *
	 * @var Admin\Settings
	 */
	private Admin\Settings $settings;

	/**
	 * REST API instance.
	 *
	 * @var REST\API
	 */
	private REST\API $rest_api;

	/**
	 * Nonce helper instance.
	 *
	 * @var Utils\Nonce_Helper
	 */
	private Utils\Nonce_Helper $nonce_helper;

	/**
	 * Shortcodes instance.
	 *
	 * @var Shortcodes\Shortcodes
	 */
	private Shortcodes\Shortcodes $shortcodes;

	/**
	 * Frontend assets instance.
	 *
	 * @var Frontend\Assets
	 */
	private Frontend\Assets $frontend_assets;

	/**
	 * Stripe checkout instance.
	 *
	 * @var Payments\StripeCheckout
	 */
	private Payments\StripeCheckout $stripe_checkout;

	/**
	 * Database instance.
	 *
	 * @var Database\Database
	 */
	private Database\Database $database;

	/**
	 * Get plugin instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
		$this->load_dependencies();
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Load plugin dependencies.
	 *
	 * @return void
	 */
	private function load_dependencies(): void {
		$this->init_components();
	}

	/**
	 * Initialize plugin components.
	 *
	 * @return void
	 */
	private function init_components(): void {
		// Initialize database first (only when needed)
		$this->database = new Database\Database();

		// Initialize admin components only when in admin area
		if ( is_admin() ) {
			$this->settings = new Admin\Settings();
			// Only initialize other admin components on plugin pages to save memory
			add_action( 'admin_init', array( $this, 'init_admin_components' ) );
		}

		// Initialize frontend components only when needed
		add_action( 'wp_loaded', array( $this, 'init_frontend_components' ) );

		// Initialize REST API only when needed
		add_action( 'rest_api_init', array( $this, 'init_rest_api' ) );

		// Initialize shortcodes only when needed
		add_action( 'init', array( $this, 'init_shortcodes' ) );

		// Initialize utility components only when needed
		add_action( 'init', array( $this, 'init_utility_components' ) );
	}

	/**
	 * Initialize admin components only when needed.
	 *
	 * @return void
	 */
	public function init_admin_components(): void {
		// Only initialize on plugin admin pages to save memory
		$current_page = $_GET['page'] ?? '';
		if ( strpos( $current_page, 'lnmc-member-hub' ) !== false ) {
			new Admin\Dashboard();
			new Admin\Members();
			new Admin\Payments();
		}
	}

	/**
	 * Initialize frontend components only when needed.
	 *
	 * @return void
	 */
	public function init_frontend_components(): void {
		// Only initialize if we're on a page that needs these components
		if ( ! is_admin() && ! wp_doing_ajax() ) {
			new Frontend\Assets();
			new Frontend\Protection();
			
			// Initialize payment components only when needed
			if ( ! isset($this->stripe_checkout) ) {
				$this->stripe_checkout = new Payments\StripeCheckout();
			}
		}
	}

	/**
	 * Initialize REST API only when needed.
	 *
	 * @return void
	 */
	public function init_rest_api(): void {
		// Only initialize REST API when actually needed
		if ( ! isset($this->rest_api) ) {
			$this->rest_api = new REST\API();
		}
	}

	/**
	 * Initialize shortcodes only when needed.
	 *
	 * @return void
	 */
	public function init_shortcodes(): void {
		// Only initialize shortcodes when needed
		if ( ! isset($this->shortcodes) ) {
			$this->shortcodes = new Shortcodes\Shortcodes();
		}
	}

	/**
	 * Initialize utility components only when needed.
	 *
	 * @return void
	 */
	public function init_utility_components(): void {
		// Only initialize utility components when needed
		if ( ! isset($this->nonce_helper) ) {
			$this->nonce_helper = new Utils\Nonce_Helper();
		}
		
		// Initialize other utilities only when needed
		if ( ! class_exists( 'LNMC_Member_Hub\Utils\Compatibility_Helper' ) ) {
			new Utils\Compatibility_Helper();
		}
		
		if ( ! class_exists( 'LNMC_Member_Hub\Utils\Email_Helper' ) ) {
			new Utils\Email_Helper();
		}
		
		// Log helper is static, no need to instantiate
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'lnmc-member-hub',
			false,
			dirname( LNMC_MEMBER_HUB_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Enqueue frontend scripts and styles.
	 * Note: Conditional script enqueuing is handled by Frontend\Assets class.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		// Enqueue frontend styles for shortcodes (always loaded for styling)
		wp_enqueue_style(
			'lnmc-member-hub-frontend',
			LNMC_MEMBER_HUB_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			LNMC_MEMBER_HUB_VERSION
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @return void
	 */
	public function enqueue_admin_scripts(): void {
		$screen = get_current_screen();
		
		// Check if we're on any LNMC Member Hub admin page
		if ( $screen && (
			'toplevel_page_lnmc-member-hub' === $screen->id ||
			'lnmc-member-hub_page_lnmc-member-hub-dashboard' === $screen->id ||
			'lnmc-member-hub_page_lnmc-member-hub-members' === $screen->id ||
			'lnmc-member-hub_page_lnmc-member-hub-payments' === $screen->id
		) ) {
			wp_enqueue_script(
				'lnmc-member-hub-admin',
				LNMC_MEMBER_HUB_PLUGIN_URL . 'assets/js/admin.js',
				array( 'jquery' ),
				LNMC_MEMBER_HUB_VERSION,
				true
			);

			wp_enqueue_style(
				'lnmc-member-hub-admin',
				LNMC_MEMBER_HUB_PLUGIN_URL . 'assets/css/admin.css',
				array(),
				LNMC_MEMBER_HUB_VERSION
			);

			wp_localize_script(
				'lnmc-member-hub-admin',
				'lnmcMemberHub',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'lnmc_member_hub_admin' ),
				)
			);
		}
	}

	/**
	 * Plugin activation hook.
	 *
	 * @return void
	 */
	public static function activate(): void {
		// Create default options
		$default_options = array(
			'membership_mode' => 'off',
			'webhook_url'     => '',
			'stripe_mode'     => 'test',
		);

		foreach ( $default_options as $option => $value ) {
			if ( false === get_option( 'lnmc_member_hub_' . $option ) ) {
				add_option( 'lnmc_member_hub_' . $option, $value );
			}
		}

		// Initialize database and create tables
		$database = new Database\Database();
		$database->create_tables();

		// Flush rewrite rules for REST API
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation hook.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Plugin uninstall hook.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		// Remove all plugin options
		$options = array(
			'lnmc_member_hub_membership_mode',
			'lnmc_member_hub_webhook_url',
			'lnmc_member_hub_stripe_mode',
			'lnmc_member_hub_stripe_test_publishable_key',
			'lnmc_member_hub_stripe_test_secret_key',
			'lnmc_member_hub_stripe_live_publishable_key',
			'lnmc_member_hub_stripe_live_secret_key',
			'lnmc_member_hub_stripe_webhook_secret',
			'lnmc_member_hub_db_version',
		);

		foreach ( $options as $option ) {
			delete_option( $option );
		}

		// Drop all plugin tables
		self::drop_all_tables();
	}

	/**
	 * Get settings instance.
	 *
	 * @return Admin\Settings
	 */
	public function get_settings(): Admin\Settings {
		return $this->settings;
	}

	/**
	 * Get REST API instance.
	 *
	 * @return REST\API
	 */
	public function get_rest_api(): REST\API {
		return $this->rest_api;
	}

	/**
	 * Get nonce helper instance.
	 *
	 * @return Utils\Nonce_Helper
	 */
	public function get_nonce_helper(): Utils\Nonce_Helper {
		return $this->nonce_helper;
	}

	/**
	 * Get shortcodes instance.
	 *
	 * @return Shortcodes\Shortcodes
	 */
	public function get_shortcodes(): Shortcodes\Shortcodes {
		return $this->shortcodes;
	}

	/**
	 * Get frontend assets instance.
	 *
	 * @return Frontend\Assets
	 */
	public function get_frontend_assets(): Frontend\Assets {
		return $this->frontend_assets;
	}

	/**
	 * Get Stripe checkout instance.
	 *
	 * @return Payments\StripeCheckout
	 */
	public function get_stripe_checkout(): Payments\StripeCheckout {
		return $this->stripe_checkout;
	}

	/**
	 * Get database instance.
	 *
	 * @return Database\Database
	 */
	public function get_database(): Database\Database {
		return $this->database;
	}

	/**
	 * Drop all plugin tables.
	 *
	 * @return void
	 */
	private static function drop_all_tables(): void {
		global $wpdb;

		$tables = array(
			'lnmc_members',
			'lnmc_payments',
			'lnmc_activity_log',
			'lnmc_member_flags',
			'lnmc_system_log',
		);

		foreach ( $tables as $table ) {
			$table_name = $wpdb->prefix . $table;
			$wpdb->query( "DROP TABLE IF EXISTS $table_name" );
		}
	}
}
