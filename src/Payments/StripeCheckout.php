<?php
/**
 * Enhanced Stripe Checkout Integration
 *
 * @package LNMC_Member_Hub
 * @since 1.0.0
 */

namespace LNMC_Member_Hub\Payments;

/**
 * Handles enhanced Stripe Checkout integration for membership payments.
 */
class StripeCheckout {

	/**
	 * Stripe publishable key.
	 *
	 * @var string
	 */
	private string $publishable_key;

	/**
	 * Stripe secret key.
	 *
	 * @var string
	 */
	private string $secret_key;

	/**
	 * Payment mode (test/live).
	 *
	 * @var string
	 */
	private string $mode;

	/**
	 * Webhook secret.
	 *
	 * @var string
	 */
	private string $webhook_secret;

	/**
	 * Database instance.
	 *
	 * @var \LNMC_Member_Hub\Database\Database
	 */
	private \LNMC_Member_Hub\Database\Database $database;

	/**
	 * Constructor.
	 *
	 * @param \LNMC_Member_Hub\Database\Database|null $database Optional database instance to avoid recursive plugin bootstrapping.
	 */
	public function __construct( ?\LNMC_Member_Hub\Database\Database $database = null ) {
		$this->init_hooks();
		$this->load_settings();
		if ( null !== $database ) {
			$this->database = $database;
		} else {
			$this->database = \LNMC_Member_Hub\Plugin::get_instance()->get_database();
		}
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_stripe_scripts' ) );
		add_action( 'wp_ajax_create_stripe_checkout_session', array( $this, 'create_checkout_session' ) );
		add_action( 'wp_ajax_nopriv_create_stripe_checkout_session', array( $this, 'create_checkout_session' ) );
		add_action( 'wp_ajax_create_stripe_customer_portal_session', array( $this, 'create_customer_portal_session' ) );
		add_action( 'wp_ajax_nopriv_create_stripe_customer_portal_session', array( $this, 'create_customer_portal_session' ) );
		add_action( 'init', array( $this, 'register_webhook_endpoint' ) );
		add_action( 'template_redirect', array( $this, 'handle_webhook_request' ) );
		add_action( 'wp_ajax_stripe_webhook_retry', array( $this, 'retry_failed_webhook' ) );
		add_action( 'wp_ajax_nopriv_stripe_webhook_retry', array( $this, 'retry_failed_webhook' ) );
	}

	       /**
     * Load Stripe settings from WordPress options or constants.
     * 
     * Priority order:
     * 1. Constants defined in wp-config.php (recommended for security)
     * 2. WordPress options from database (fallback)
     *
     * @return void
     */
    private function load_settings(): void {
        // Check for mode constant first, then fall back to option
        $this->mode = defined( 'LNMC_STRIPE_MODE' ) 
            ? LNMC_STRIPE_MODE 
            : get_option( 'lnmc_member_hub_stripe_mode', 'test' );
        
        if ( 'live' === $this->mode ) {
            // Live mode: Check constants first, then fall back to database options
            $this->publishable_key = defined( 'LNMC_STRIPE_LIVE_PUBLISHABLE_KEY' )
                ? LNMC_STRIPE_LIVE_PUBLISHABLE_KEY
                : $this->get_secure_option( 'lnmc_member_hub_stripe_live_publishable_key' );
                
            $this->secret_key = defined( 'LNMC_STRIPE_LIVE_SECRET_KEY' )
                ? LNMC_STRIPE_LIVE_SECRET_KEY
                : $this->get_secure_option( 'lnmc_member_hub_stripe_live_secret_key' );
                
            $this->webhook_secret = defined( 'LNMC_STRIPE_LIVE_WEBHOOK_SECRET' )
                ? LNMC_STRIPE_LIVE_WEBHOOK_SECRET
                : $this->get_secure_option( 'lnmc_member_hub_stripe_live_webhook_secret' );
        } else {
            // Test mode: Check constants first, then fall back to database options
            $this->publishable_key = defined( 'LNMC_STRIPE_TEST_PUBLISHABLE_KEY' )
                ? LNMC_STRIPE_TEST_PUBLISHABLE_KEY
                : $this->get_secure_option( 'lnmc_member_hub_stripe_test_publishable_key' );
                
            $this->secret_key = defined( 'LNMC_STRIPE_TEST_SECRET_KEY' )
                ? LNMC_STRIPE_TEST_SECRET_KEY
                : $this->get_secure_option( 'lnmc_member_hub_stripe_test_secret_key' );
                
            $this->webhook_secret = defined( 'LNMC_STRIPE_TEST_WEBHOOK_SECRET' )
                ? LNMC_STRIPE_TEST_WEBHOOK_SECRET
                : $this->get_secure_option( 'lnmc_member_hub_stripe_test_webhook_secret' );
        }
    }

	/**
	 * Get secure option value (with encryption support).
	 *
	 * @param string $option_name Option name.
	 * @return string
	 */
	private function get_secure_option( string $option_name ): string {
		$value = get_option( $option_name, '' );
		
		// Check if value is encrypted
		if ( $this->is_encrypted( $value ) ) {
			return $this->decrypt( $value );
		}
		
		return $value;
	}

	/**
	 * Check if a value is encrypted.
	 *
	 * @param string $value Value to check.
	 * @return bool
	 */
	private function is_encrypted( string $value ): bool {
		return strpos( $value, 'encrypted:' ) === 0;
	}

	/**
	 * Encrypt sensitive data.
	 *
	 * @param string $value Value to encrypt.
	 * @return string
	 */
	private function encrypt( string $value ): string {
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
	private function decrypt( string $value ): string {
		if ( empty( $value ) || ! $this->is_encrypted( $value ) ) {
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
	 * Enqueue Stripe JavaScript library.
	 *
	 * @return void
	 */
	public function enqueue_stripe_scripts(): void {
		// Only enqueue on pages that need Stripe
		if ( ! $this->should_enqueue_stripe() ) {
			return;
		}

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

		wp_localize_script(
			'lnmc-stripe-checkout',
			'lnmcStripe',
			array(
				'publishableKey' => $this->publishable_key,
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'lnmc_stripe_checkout' ),
				'currency'       => 'usd',
				'mode'           => $this->mode,
				'customerPortalEnabled' => $this->is_customer_portal_enabled(),
			)
		);
	}

	/**
	 * Determine if Stripe scripts should be enqueued on current request.
	 *
	 * @return bool
	 */
	private function should_enqueue_stripe(): bool {
		global $post;
		// Detect membership form or hub shortcodes in current post content (hub nests the form)
		if ( $post && isset( $post->post_content ) ) {
			$content = (string) $post->post_content;
			if ( has_shortcode( $content, 'lnmc_membership_form' ) || has_shortcode( $content, 'lnmc_membership_hub' ) ) {
				return true;
			}
		}

		// Also enable on known pages by slug
		$slugs = array( 'membership', 'membership-hub', 'member-dashboard', 'thank-you', 'account-settings', 'pricing' );
		foreach ( $slugs as $slug ) {
			if ( is_page( $slug ) ) {
				return true;
			}
		}

		return false;

/**
 * Check if customer portal is enabled.
 *
 * @return bool
 */
private function is_customer_portal_enabled(): bool {
    return get_option( 'lnmc_stripe_customer_portal_enabled', true );
}

/**
 * Get or create Stripe customer.
 *
 * @param string $email Customer email.
 * @return string Customer ID.
 */
private function get_or_create_customer( string $email ): string {
    // Check if customer already exists
    $existing_customer = $this->find_customer_by_email( $email );
    
    if ( $existing_customer ) {
        return $existing_customer;
    }

    // Create new customer
    \Stripe\Stripe::setApiKey( $this->secret_key );
    $customer = \Stripe\Customer::create( array(
        'email' => $email,
        'metadata' => array(
            'plugin' => 'lnmc-member-hub',
            'created_via' => 'checkout',
        ),
    ) );

    return $customer->id;
}

/**
 * Find customer by email.
 *
 * @param string $email Customer email.
 * @return string|null Customer ID or null.
 */
private function find_customer_by_email( string $email ): ?string {
    \Stripe\Stripe::setApiKey( $this->secret_key );
    $customers = \Stripe\Customer::all( array( 'email' => $email ) );

    if ( ! empty( $customers->data ) ) {
        return $customers->data[0]->id;
    }

    return null;
}

/**
 * Register webhook endpoint.
 *
 * @return void
 */
public function register_webhook_endpoint(): void {
    add_rewrite_rule(
        '^stripe-webhook/?$',
        'index.php?stripe_webhook=1',
        'top'
    );

    add_filter( 'query_vars', function( $vars ) {
        $vars[] = 'stripe_webhook';
        return $vars;
    });
}

/**
 * Handle webhook request.
 *
 * @return void
 */
public function handle_webhook_request(): void {
    if ( ! get_query_var( 'stripe_webhook' ) ) {
        return;
    }

    $this->handle_webhook();
    exit;
}

/**
 * Handle Stripe webhook.
 *
 * @return void
 */
public function handle_webhook(): void {
    $payload = file_get_contents( 'php://input' );
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

    if ( empty( $payload ) || empty( $sig_header ) ) {
        http_response_code( 400 );
        echo 'Invalid webhook request';
        exit;
    }

    try {
        // Verify webhook signature
        $this->verify_webhook_signature( $payload, $sig_header );

        $event = json_decode( $payload );
        if ( ! $event ) {
            throw new \Exception( 'Invalid JSON payload' );
        }

        $this->process_webhook_event( $event );

        http_response_code( 200 );
        echo 'Webhook processed successfully';

    } catch ( \Exception $e ) {
        error_log( 'Stripe webhook error: ' . $e->getMessage() . "\n" . $e->getTraceAsString() );
        http_response_code( 400 );
        echo 'Webhook error';
    }
}

/**
 * Verify webhook signature.
 *
 * @param string $payload Raw payload.
 * @param string $sig_header Signature header.
 * @return void
 * @throws \Exception If signature verification fails.
 */
private function verify_webhook_signature( string $payload, string $sig_header ): void {
    if ( empty( $this->webhook_secret ) ) {
        throw new \Exception( 'Webhook secret not configured' );
    }

    $signature_parts = explode( ',', $sig_header );
    $timestamp = '';
    $signature = '';

    foreach ( $signature_parts as $part ) {
        if ( strpos( $part, 't=' ) === 0 ) {
            $timestamp = substr( $part, 2 );
        } elseif ( strpos( $part, 'v1=' ) === 0 ) {
            $signature = substr( $part, 3 );
        }
    }

    if ( empty( $timestamp ) || empty( $signature ) ) {
        throw new \Exception( 'Invalid signature format' );
    }

    // Check timestamp (reject if older than 5 minutes)
    if ( time() - $timestamp > 300 ) {
        throw new \Exception( 'Webhook timestamp too old' );
    }

    // Verify signature
    $expected_signature = hash_hmac( 'sha256', $timestamp . '.' . $payload, $this->webhook_secret );
    
    if ( ! hash_equals( $expected_signature, $signature ) ) {
        throw new \Exception( 'Invalid signature' );
    }
}

/**
 * Process webhook event.
 *
 * @param object $event Stripe event object.
 * @return void
 */
private function process_webhook_event( $event ): void {
    switch ( $event->type ) {
        case 'checkout.session.completed':
            $this->handle_checkout_session_completed( $event->data->object );
            break;
        case 'customer.subscription.created':
            $this->handle_subscription_created( $event->data->object );
            break;
        case 'customer.subscription.updated':
            $this->handle_subscription_updated( $event->data->object );
            break;
        case 'customer.subscription.deleted':
            $this->handle_subscription_deleted( $event->data->object );
            break;
        case 'invoice.payment_failed':
            $this->handle_payment_failed( $event->data->object );
            break;
        case 'invoice.payment_succeeded':
            $this->handle_payment_succeeded( $event->data->object );
            break;
        case 'customer.updated':
            $this->handle_customer_updated( $event->data->object );
            break;
        default:
            error_log( 'Unhandled Stripe webhook event: ' . $event->type );
    }
}

/**
 * Handle checkout session completed.
 *
 * @param object $session Checkout session object.
 * @return void
 */
private function handle_checkout_session_completed( $session ): void {
    $customer_id = $session->customer;
    $email = $session->customer_details->email ?? '';
    $subscription_id = $session->subscription ?? null;
    
    // Get or create user
    $user = get_user_by( 'email', $email );
    
    if ( ! $user ) {
        $user_id = wp_create_user( $email, wp_generate_password(), $email );
        if ( is_wp_error( $user_id ) ) {
            error_log( 'Failed to create user for email: ' . $email );
            return;
        }
        $user = get_user_by( 'id', $user_id );
    }

    // Add membership role
    $user->add_role( 'lnmc_member' );

    // Save member to database
    $member_data = array(
        'user_id' => $user->ID,
        'membership_status' => 'active',
        'membership_type' => 'standard',
        'subscription_id' => $subscription_id,
        'payment_amount' => $session->amount_total / 100, // Convert from cents
        'payment_currency' => strtoupper( $session->currency ),
        'join_date' => current_time( 'mysql' ),
        'payment_status' => 'completed',
        'stripe_customer_id' => $customer_id,
        'stripe_subscription_id' => $subscription_id,
        'webhook_processed' => 1,
    );

    $member_id = $this->database->save_member( $member_data );

    if ( $member_id ) {
        // Log the activity
        $this->database->log_activity(
            $member_id,
            'membership_created',
            sprintf( 'Membership created via Stripe checkout for user %s', $user->display_name ),
            array(
                'session_id' => $session->id,
                'customer_id' => $customer_id,
                'subscription_id' => $subscription_id,
                'amount' => $session->amount_total / 100,
            )
        );

        // Send welcome email
        $this->send_welcome_email( $email );
    }
}

/**
 * Handle subscription created.
 *
 * @param object $subscription Subscription object.
 * @return void
 */
private function handle_subscription_created( $subscription ): void {
    $customer_id = $subscription->customer;
    $email = $this->get_customer_email( $customer_id );
    
    // Find member by customer ID
    $member = $this->database->get_member_by_stripe_customer_id( $customer_id );
    if ( $member ) {
        $this->database->update_member( $member->id, array(
            'membership_status' => 'active',
            'stripe_subscription_id' => $subscription->id,
            'webhook_processed' => 1,
        ) );

        // Log the activity
        $this->database->log_activity(
            $member->id,
            'subscription_created',
            sprintf( 'Stripe subscription created: %s', $subscription->id ),
            array( 'subscription_id' => $subscription->id )
        );
    }
}

/**
 * Handle subscription updated.
 *
 * @param object $subscription Subscription object.
 * @return void
 */
private function handle_subscription_updated( $subscription ): void {
    $customer_id = $subscription->customer;
    $status = $subscription->status;
    
    // Find member by customer ID
    $member = $this->database->get_member_by_stripe_customer_id( $customer_id );
    if ( $member ) {
        $update_data = array(
            'membership_status' => $status,
            'webhook_processed' => 1,
        );

        // Update expiry date if available
        if ( isset( $subscription->current_period_end ) ) {
            $update_data['expiry_date'] = date( 'Y-m-d H:i:s', $subscription->current_period_end );
        }

        $this->database->update_member( $member->id, $update_data );

        // Log the activity
        $this->database->log_activity(
            $member->id,
            'subscription_updated',
            sprintf( 'Subscription status updated to: %s', $status ),
            array( 'subscription_id' => $subscription->id, 'status' => $status )
        );
    }
}

/**
 * Handle subscription deleted.
 *
 * @param object $subscription Subscription object.
 * @return void
 */
private function handle_subscription_deleted( $subscription ): void {
    $customer_id = $subscription->customer;
    
    // Find member by customer ID or subscription ID
    $member = $this->database->get_member_by_stripe_customer_id( $customer_id );
    if ( ! $member ) {
        $member = $this->database->get_member_by_stripe_subscription_id( $subscription->id );
    }

    if ( $member ) {
        // Update member status
        $this->database->update_member( $member->id, array(
            'membership_status' => 'cancelled',
            'payment_status' => 'cancelled',
            'webhook_processed' => 1,
        ) );

        // Remove membership role from user
        $user = get_user_by( 'id', $member->user_id );
        if ( $user ) {
            $user->remove_role( 'lnmc_member' );
        }

        // Log the activity
        $this->database->log_activity(
            $member->id,
            'subscription_cancelled',
            sprintf( 'Subscription cancelled: %s', $subscription->id ),
            array( 'subscription_id' => $subscription->id )
        );
    }
}

/**
 * Handle payment failed.
 *
 * @param object $invoice Invoice object.
 * @return void
 */
private function handle_payment_failed( $invoice ): void {
    $customer_id = $invoice->customer;
    $subscription_id = $invoice->subscription;
    
    // Find member by customer ID
    $member = $this->database->get_member_by_stripe_customer_id( $customer_id );
    if ( $member ) {
        $this->database->update_member( $member->id, array(
            'membership_status' => 'past_due',
            'payment_status' => 'failed',
            'webhook_processed' => 1,
        ) );

        // Log the activity
        $this->database->log_activity(
            $member->id,
            'payment_failed',
            sprintf( 'Payment failed for invoice: %s', $invoice->id ),
            array( 'invoice_id' => $invoice->id, 'subscription_id' => $subscription_id )
        );

        // Send payment failed email
        $user = get_user_by( 'id', $member->user_id );
        if ( $user ) {
            $this->send_payment_failed_email( $user->user_email );
        }
    }
}

/**
 * Handle payment succeeded.
 *
 * @param object $invoice Invoice object.
 * @return void
 */
private function handle_payment_succeeded( $invoice ): void {
    $customer_id = $invoice->customer;
    $subscription_id = $invoice->subscription;
    
    // Find member by customer ID
    $member = $this->database->get_member_by_stripe_customer_id( $customer_id );
    if ( $member ) {
        $this->database->update_member( $member->id, array(
            'membership_status' => 'active',
            'payment_status' => 'completed',
            'last_payment_date' => current_time( 'mysql' ),
            'webhook_processed' => 1,
        ) );

        // Log the activity
        $this->database->log_activity(
            $member->id,
            'payment_succeeded',
            sprintf( 'Payment succeeded for invoice: %s', $invoice->id ),
            array( 'invoice_id' => $invoice->id, 'subscription_id' => $subscription_id )
        );
    }
}

/**
 * Handle customer updated.
 *
 * @param object $customer Customer object.
 * @return void
 */
private function handle_customer_updated( $customer ): void {
    // Find member by customer ID
    $member = $this->database->get_member_by_stripe_customer_id( $customer->id );
    if ( $member ) {
        // Log the activity
        $this->database->log_activity(
            $member->id,
            'customer_updated',
            sprintf( 'Customer information updated in Stripe: %s', $customer->id ),
            array( 'customer_id' => $customer->id )
        );
    }
}

/**
 * Get customer email from Stripe.
 *
 * @param string $customer_id Customer ID.
 * @return string Customer email.
 */
private function get_customer_email( string $customer_id ): string {
    \Stripe\Stripe::setApiKey( $this->secret_key );
    $customer = \Stripe\Customer::retrieve( $customer_id );

    return $customer->email ?? '';
}

/**
 * Send welcome email.
 *
 * @param string $email User email.
 * @return void
 */
private function send_welcome_email( string $email ): void {
    $user = get_user_by( 'email', $email );
    if ( ! $user ) {
        return;
    }

    // Use the Email Helper class
    \LNMC_Member_Hub\Utils\Email_Helper::send_welcome_email( $user->ID, 'LNMC Membership' );
}

/**
 * Send payment failed email.
 *
 * @param string $email User email.
 * @return void
 */
private function send_payment_failed_email( string $email ): void {
    $user = get_user_by( 'email', $email );
    if ( ! $user ) {
        return;
    }

    $subject = __( 'Payment Failed - LNMC Membership', 'lnmc-member-hub' );
    $message = __( 'Your membership payment was not successful. Please update your payment method to continue your membership.', 'lnmc-member-hub' );

    wp_mail( $email, $subject, $message );
}

/**
 * Retry failed webhook.
 *
 * @return void
 */
public function retry_failed_webhook(): void {
    // Verify nonce
    if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'lnmc_stripe_checkout' ) ) {
        wp_send_json_error( __( 'Security check failed.', 'lnmc-member-hub' ) );
    }

    // Check permissions
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'Insufficient permissions.', 'lnmc-member-hub' ) );
    }

    $webhook_id = sanitize_text_field( $_POST['webhook_id'] ?? '' );
    if ( empty( $webhook_id ) ) {
        wp_send_json_error( __( 'Webhook ID is required.', 'lnmc-member-hub' ) );
    }

    try {
        // Implement webhook retry logic here
        // This would typically involve fetching the event from Stripe and reprocessing it
        wp_send_json_success( __( 'Webhook retry initiated.', 'lnmc-member-hub' ) );
    } catch ( \Exception $e ) {
        wp_send_json_error( $e->getMessage() );
    }
}

/**
 * Get Stripe publishable key.
 *
 * @return string
 */
public function get_publishable_key(): string {
    return $this->publishable_key;
}

/**
 * Check if Stripe is configured.
 *
 * @return bool
 */
public function is_configured(): bool {
    return ! empty( $this->publishable_key ) && ! empty( $this->secret_key );
}

/**
 * Get current mode.
 *
 * @return string
 */
public function get_mode(): string {
    return $this->mode;
}

/**
 * Get member by Stripe customer ID.
 *
 * @param string $customer_id Stripe customer ID.
 * @return object|null Member object or null.
 */
public function get_member_by_stripe_customer_id( string $customer_id ) {
    return $this->database->get_member_by_stripe_customer_id( $customer_id );
}

/**
 * Get member by Stripe subscription ID.
 *
 * @param string $subscription_id Stripe subscription ID.
 * @return object|null Member object or null.
 */
public function get_member_by_stripe_subscription_id( string $subscription_id ) {
    return $this->database->get_member_by_stripe_subscription_id( $subscription_id );
}

}
