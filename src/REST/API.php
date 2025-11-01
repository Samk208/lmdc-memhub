<?php
/**
 * REST API Class
 *
 * @package LNMC_Member_Hub
 * @since 1.0.0
 */

namespace LNMC_Member_Hub\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Handles REST API endpoints for the plugin.
 */
class API {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private const NAMESPACE = 'lnmc/v1';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Ping endpoint - supports both GET and POST
		register_rest_route(
			self::NAMESPACE,
			'/ping',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'ping_endpoint' ),
					'permission_callback' => array( $this, 'check_ping_permissions' ),
					'args'                => array(
						'hello' => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'ping_endpoint' ),
					'permission_callback' => array( $this, 'check_ping_permissions' ),
					'args'                => array(
						'hello' => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// Member flag endpoint
		register_rest_route(
			self::NAMESPACE,
			'/member/flag',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'member_flag_endpoint' ),
					'permission_callback' => array( $this, 'check_member_flag_permissions' ),
					'args'                => array(
						'user_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'validate_callback' => array( $this, 'validate_user_id' ),
						),
						'flag'    => array(
							'required'          => true,
							'type'              => 'string',
							'enum'              => array( 'trusted', 'review' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
						'comment' => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => array( $this, 'sanitize_comment' ),
							'validate_callback' => array( $this, 'validate_comment' ),
						),
					),
				),
			)
		);

		// Stripe Checkout endpoint
		register_rest_route(
			self::NAMESPACE,
			'/payments/create-checkout',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_checkout_endpoint' ),
					'permission_callback' => array( $this, 'check_checkout_permissions' ),
					'args'                => array(
						'amount' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'validate_callback' => array( $this, 'validate_amount' ),
						),
						'email'  => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_email',
							'validate_callback' => array( $this, 'validate_email' ),
						),
						'currency' => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => 'usd',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => array( $this, 'validate_currency' ),
						),
					),
				),
			)
		);

		// Stripe Webhook endpoint
		register_rest_route(
			self::NAMESPACE,
			'/payments/webhook',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'stripe_webhook_endpoint' ),
					'permission_callback' => '__return_true', // No auth required for webhooks
				),
			)
		);
	}

	/**
	 * Check permissions for ping endpoint.
	 * Supports both cookie+nonce and application password authentication.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return bool|WP_Error
	 */
	public function check_ping_permissions( WP_REST_Request $request ) {
		// Check if user is logged in and has read capability
		if ( ! current_user_can( 'read' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to access this endpoint.', 'lnmc-member-hub' ),
				array( 'status' => 403 )
			);
		}

		// For cookie authentication, require X-WP-Nonce header
		if ( ! is_application_password_request() ) {
			$nonce = $request->get_header( 'X-WP-Nonce' );
			if ( empty( $nonce ) ) {
				return new WP_Error(
					'rest_cookie_invalid_nonce',
					__( 'X-WP-Nonce header is required for cookie authentication.', 'lnmc-member-hub' ),
					array( 'status' => 401 )
				);
			}

			if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				return new WP_Error(
					'rest_cookie_invalid_nonce',
					__( 'Invalid nonce.', 'lnmc-member-hub' ),
					array( 'status' => 401 )
				);
			}
		}

		return true;
	}

	/**
	 * Check permissions for member flag endpoint.
	 * Requires manage_options capability.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return bool|WP_Error
	 */
	public function check_member_flag_permissions( WP_REST_Request $request ) {
		// Check if user has manage_options capability
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to flag members.', 'lnmc-member-hub' ),
				array( 'status' => 403 )
			);
		}

		// For cookie authentication, require X-WP-Nonce header
		if ( ! is_application_password_request() ) {
			$nonce = $request->get_header( 'X-WP-Nonce' );
			if ( empty( $nonce ) ) {
				return new WP_Error(
					'rest_cookie_invalid_nonce',
					__( 'X-WP-Nonce header is required for cookie authentication.', 'lnmc-member-hub' ),
					array( 'status' => 401 )
				);
			}

			if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				return new WP_Error(
					'rest_cookie_invalid_nonce',
					__( 'Invalid nonce.', 'lnmc-member-hub' ),
					array( 'status' => 401 )
				);
			}
		}

		return true;
	}

	/**
	 * Check permissions for checkout endpoint.
	 * Requires read capability and nonce verification.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return bool|WP_Error
	 */
	public function check_checkout_permissions( WP_REST_Request $request ) {
		// Check if user is logged in and has read capability
		if ( ! current_user_can( 'read' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to create checkout sessions.', 'lnmc-member-hub' ),
				array( 'status' => 403 )
			);
		}

		// For cookie authentication, require X-WP-Nonce header
		if ( ! is_application_password_request() ) {
			$nonce = $request->get_header( 'X-WP-Nonce' );
			if ( empty( $nonce ) ) {
				return new WP_Error(
					'rest_cookie_invalid_nonce',
					__( 'X-WP-Nonce header is required for cookie authentication.', 'lnmc-member-hub' ),
					array( 'status' => 401 )
				);
			}

			if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				return new WP_Error(
					'rest_cookie_invalid_nonce',
					__( 'Invalid nonce.', 'lnmc-member-hub' ),
					array( 'status' => 401 )
				);
			}
		}

		return true;
	}

	/**
	 * Validate user ID for member flag endpoint.
	 *
	 * @param int $user_id The user ID to validate.
	 * @return bool|WP_Error
	 */
	public function validate_user_id( $user_id ) {
		if ( ! is_numeric( $user_id ) || $user_id <= 0 ) {
			return new WP_Error(
				'rest_invalid_user_id',
				__( 'Invalid user ID provided.', 'lnmc-member-hub' ),
				array( 'status' => 400 )
			);
		}

		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			return new WP_Error(
				'rest_user_not_found',
				__( 'User not found.', 'lnmc-member-hub' ),
				array( 'status' => 404 )
			);
		}

		return true;
	}

	/**
	 * Sanitize comment field for member flag endpoint.
	 *
	 * @param string $comment The comment to sanitize.
	 * @return string
	 */
	public function sanitize_comment( $comment ) {
		return trim( sanitize_text_field( $comment ) );
	}

	/**
	 * Validate comment field for member flag endpoint.
	 *
	 * @param string $comment The comment to validate.
	 * @return bool|WP_Error
	 */
	public function validate_comment( $comment ) {
		if ( strlen( $comment ) > 140 ) {
			return new WP_Error(
				'rest_comment_too_long',
				__( 'Comment must be 140 characters or less.', 'lnmc-member-hub' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Validate amount for checkout endpoint.
	 *
	 * @param int $amount The amount to validate.
	 * @return bool|WP_Error
	 */
	public function validate_amount( $amount ) {
		if ( $amount <= 0 ) {
			return new WP_Error(
				'rest_invalid_amount',
				__( 'Amount must be greater than 0.', 'lnmc-member-hub' ),
				array( 'status' => 400 )
			);
		}

		if ( $amount > 999999 ) {
			return new WP_Error(
				'rest_amount_too_high',
				__( 'Amount is too high.', 'lnmc-member-hub' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Validate email for checkout endpoint.
	 *
	 * @param string $email The email to validate.
	 * @return bool|WP_Error
	 */
	public function validate_email( $email ) {
		if ( ! is_email( $email ) ) {
			return new WP_Error(
				'rest_invalid_email',
				__( 'Please provide a valid email address.', 'lnmc-member-hub' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Validate currency for checkout endpoint.
	 *
	 * @param string $currency The currency to validate.
	 * @return bool|WP_Error
	 */
	public function validate_currency( $currency ) {
		$allowed_currencies = array( 'usd', 'eur', 'gbp', 'cad', 'aud' );
		
		if ( ! in_array( strtolower( $currency ), $allowed_currencies, true ) ) {
			return new WP_Error(
				'rest_invalid_currency',
				__( 'Currency not supported.', 'lnmc-member-hub' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Ping endpoint callback.
	 * Enhanced to handle JSON body and return sanitized response.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function ping_endpoint( WP_REST_Request $request ) {
		try {
			$user = wp_get_current_user();
			$hello = $request->get_param( 'hello' );
			
			$message = __( 'Pong! LNMC Member Hub is working correctly.', 'lnmc-member-hub' );
			if ( ! empty( $hello ) ) {
				$message = sprintf( __( 'Hello %s! LNMC Member Hub is working correctly.', 'lnmc-member-hub' ), $hello );
			}

			$response_data = array(
				'status'  => 'success',
				'message' => $message,
				'data'    => array(
					'timestamp' => current_time( 'mysql' ),
					'user_id'   => $user->ID,
					'user_name' => $user->display_name,
					'plugin_version' => LNMC_MEMBER_HUB_VERSION,
					'site_url'  => get_site_url(),
				),
			);

			// Add membership mode if user has appropriate permissions
			if ( current_user_can( 'manage_options' ) ) {
				$settings = \LNMC_Member_Hub\Plugin::get_instance()->get_settings();
				$response_data['data']['membership_mode'] = $settings->get_membership_mode();
			}

			return wp_send_json_success( $response_data );

		} catch ( \Exception $e ) {
			return wp_send_json_error(
				array(
					'code'    => 'ping_error',
					'message' => __( 'An error occurred while processing the ping request.', 'lnmc-member-hub' ),
				),
				500
			);
		}
	}

	/**
	 * Member flag endpoint callback.
	 * Handles flagging users with trusted/review status.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function member_flag_endpoint( WP_REST_Request $request ) {
		try {
			$user_id = $request->get_param( 'user_id' );
			$flag    = $request->get_param( 'flag' );
			$comment = $request->get_param( 'comment' );

			// Get database instance
			$database = \LNMC_Member_Hub\Plugin::get_instance()->get_database();
			
			// Get member by user ID
			$member = $database->get_member_by_user_id( $user_id );
			if ( ! $member ) {
				return wp_send_json_error(
					array(
						'code'    => 'member_not_found',
						'message' => __( 'Member not found.', 'lnmc-member-hub' ),
					),
					404
				);
			}

			// Store flag in database
			global $wpdb;
			$flags_table = $wpdb->prefix . 'lnmc_member_flags';
			
			$current_user = wp_get_current_user();
			
			$flag_data = array(
				'member_id' => $member->id,
				'flag_type' => $flag,
				'flag_reason' => $comment,
				'flagged_by' => $current_user->ID,
				'flag_status' => 'active',
			);
			
			$result = $wpdb->insert(
				$flags_table,
				$flag_data,
				array(
					'%d', // member_id
					'%s', // flag_type
					'%s', // flag_reason
					'%d', // flagged_by
					'%s', // flag_status
				)
			);
			
			if ( false === $result ) {
				return wp_send_json_error(
					array(
						'code'    => 'flag_update_failed',
						'message' => __( 'Failed to update member flag.', 'lnmc-member-hub' ),
					),
					500
				);
			}

			// Log the activity
			$database->log_activity(
				$member->id,
				'member_flagged',
				sprintf( 'Member flagged as %s by admin %s', $flag, $current_user->display_name ),
				array( 'flag_type' => $flag, 'comment' => $comment )
			);

			return wp_send_json_success(
				array(
					'member_id' => $member->id,
					'user_id' => $user_id,
					'flag'    => $flag,
					'flag_id' => $wpdb->insert_id,
				)
			);

		} catch ( \Exception $e ) {
			return wp_send_json_error(
				array(
					'code'    => 'flag_error',
					'message' => __( 'An error occurred while processing the flag request.', 'lnmc-member-hub' ),
				),
				500
			);
		}
	}

	/**
	 * Create checkout endpoint callback.
	 * Creates Stripe Checkout session and returns session URL.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_checkout_endpoint( WP_REST_Request $request ) {
		try {
			$amount   = $request->get_param( 'amount' );
			$email    = $request->get_param( 'email' );
			$currency = $request->get_param( 'currency' );

			// Get Stripe checkout instance
			$stripe_checkout = \LNMC_Member_Hub\Plugin::get_instance()->get_stripe_checkout();
			
			// Check if Stripe is configured
			if ( ! $stripe_checkout->is_configured() ) {
				return wp_send_json_error(
					array(
						'code'    => 'stripe_not_configured',
						'message' => __( 'Stripe payment system is not configured.', 'lnmc-member-hub' ),
					),
					503
				);
			}

			// Create session data
			$session_data = array(
				'payment_method_types' => array( 'card' ),
				'line_items'           => array(
					array(
						'price_data' => array(
							'currency'     => $currency,
							'product_data' => array(
								'name' => __( 'LNMC Membership', 'lnmc-member-hub' ),
							),
							'unit_amount'  => $amount,
						),
						'quantity'   => 1,
					),
				),
				'mode'                 => 'payment',
				'success_url'          => home_url( '/membership-success?session_id={CHECKOUT_SESSION_ID}' ),
				'cancel_url'           => home_url( '/membership-cancel' ),
				'customer_email'       => $email,
				'metadata'             => array(
					'user_email' => $email,
					'plugin'     => 'lnmc-member-hub',
					'user_id'    => get_current_user_id(),
				),
			);

			// Get Stripe secret key
			$settings = \LNMC_Member_Hub\Plugin::get_instance()->get_settings();
			$mode = $settings->get_stripe_mode();
			
			if ( 'live' === $mode ) {
				$secret_key = $settings->get_stripe_live_secret_key();
			} else {
				$secret_key = $settings->get_stripe_test_secret_key();
			}

			if ( empty( $secret_key ) ) {
				return wp_send_json_error(
					array(
						'code'    => 'stripe_key_missing',
						'message' => __( 'Stripe secret key is not configured.', 'lnmc-member-hub' ),
					),
					503
				);
			}

			// Create Stripe checkout session
			$response = wp_remote_post(
				'https://api.stripe.com/v1/checkout/sessions',
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $secret_key,
						'Content-Type'  => 'application/x-www-form-urlencoded',
					),
					'body'    => http_build_query( $session_data ),
				)
			);

			if ( is_wp_error( $response ) ) {
				return wp_send_json_error(
					array(
						'code'    => 'stripe_request_failed',
						'message' => __( 'Failed to communicate with Stripe.', 'lnmc-member-hub' ),
					),
					500
				);
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( ! isset( $data['id'] ) ) {
				$error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Failed to create checkout session.', 'lnmc-member-hub' );
				return wp_send_json_error(
					array(
						'code'    => 'stripe_session_failed',
						'message' => $error_message,
					),
					400
				);
			}

			// Log the checkout session creation
			$current_user = wp_get_current_user();
			error_log( sprintf(
				'[LNMC Member Hub] Checkout session created for user %s (ID: %d) with amount %d %s',
				$current_user->display_name,
				$current_user->ID,
				$amount,
				strtoupper( $currency )
			) );

			return wp_send_json_success(
				array(
					'session_id' => $data['id'],
					'session_url' => $data['url'],
					'amount'     => $amount,
					'currency'   => $currency,
					'email'      => $email,
				)
			);

		} catch ( \Exception $e ) {
			return wp_send_json_error(
				array(
					'code'    => 'checkout_error',
					'message' => __( 'An error occurred while creating the checkout session.', 'lnmc-member-hub' ),
				),
				500
			);
		}
	}

	/**
	 * Stripe webhook endpoint callback.
	 * Handles Stripe webhook events with signature verification.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function stripe_webhook_endpoint( WP_REST_Request $request ) {
		try {
			$payload = file_get_contents( 'php://input' );
			$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

			if ( empty( $payload ) || empty( $sig_header ) ) {
				error_log( '[LNMC Member Hub] Invalid webhook request: missing payload or signature' );
				return new WP_Error(
					'webhook_invalid_request',
					__( 'Invalid webhook request.', 'lnmc-member-hub' ),
					array( 'status' => 400 )
				);
			}

			// Get webhook secret from settings
			$settings = \LNMC_Member_Hub\Plugin::get_instance()->get_settings();
			$webhook_secret = $settings->get_stripe_webhook_secret();
			
			if ( empty( $webhook_secret ) ) {
				error_log( '[LNMC Member Hub] Webhook secret not configured' );
				return new WP_Error(
					'webhook_not_configured',
					__( 'Webhook secret not configured.', 'lnmc-member-hub' ),
					array( 'status' => 503 )
				);
			}

			// Verify webhook signature
			$expected_signature = hash_hmac( 'sha256', $payload, $webhook_secret );
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
				error_log( '[LNMC Member Hub] Invalid signature format in webhook' );
				return new WP_Error(
					'webhook_invalid_signature',
					__( 'Invalid signature format.', 'lnmc-member-hub' ),
					array( 'status' => 400 )
				);
			}

			// Check timestamp (reject if older than 5 minutes)
			if ( time() - $timestamp > 300 ) {
				error_log( '[LNMC Member Hub] Webhook timestamp too old: ' . $timestamp );
				return new WP_Error(
					'webhook_timestamp_expired',
					__( 'Webhook timestamp too old.', 'lnmc-member-hub' ),
					array( 'status' => 400 )
				);
			}

			// Verify signature
			if ( ! hash_equals( $expected_signature, $signature ) ) {
				error_log( '[LNMC Member Hub] Invalid webhook signature' );
				return new WP_Error(
					'webhook_invalid_signature',
					__( 'Invalid signature.', 'lnmc-member-hub' ),
					array( 'status' => 400 )
				);
			}

			// Parse event
			$event = json_decode( $payload );
			if ( ! $event ) {
				error_log( '[LNMC Member Hub] Invalid JSON payload in webhook' );
				return new WP_Error(
					'webhook_invalid_payload',
					__( 'Invalid JSON payload.', 'lnmc-member-hub' ),
					array( 'status' => 400 )
				);
			}

			// Process webhook event
			$this->process_stripe_webhook_event( $event );

			// Log successful webhook processing
			error_log( sprintf(
				'[LNMC Member Hub] Webhook processed successfully: %s (ID: %s)',
				$event->type,
				$event->id ?? 'unknown'
			) );

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => 'Webhook processed successfully',
					'event_type' => $event->type,
					'event_id' => $event->id ?? 'unknown',
				),
				200
			);

		} catch ( \Exception $e ) {
			error_log( '[LNMC Member Hub] Webhook error: ' . $e->getMessage() );
			return new WP_Error(
				'webhook_processing_error',
				__( 'An error occurred while processing the webhook.', 'lnmc-member-hub' ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Process Stripe webhook event.
	 *
	 * @param object $event Stripe event object.
	 * @return void
	 */
	private function process_stripe_webhook_event( $event ): void {
		switch ( $event->type ) {
			case 'checkout.session.completed':
				$this->handle_checkout_session_completed( $event->data->object );
				break;
			case 'customer.subscription.deleted':
				$this->handle_subscription_deleted( $event->data->object );
				break;
			case 'invoice.payment_succeeded':
				$this->handle_invoice_payment_succeeded( $event->data->object );
				break;
			case 'invoice.payment_failed':
				$this->handle_invoice_payment_failed( $event->data->object );
				break;
			default:
				// Log unhandled event types
				error_log( sprintf(
					'[LNMC Member Hub] Unhandled Stripe webhook event: %s (ID: %s)',
					$event->type,
					$event->id ?? 'unknown'
				) );
		}
	}

	/**
	 * Handle checkout.session.completed event.
	 *
	 * @param object $session Stripe checkout session object.
	 * @return void
	 */
	private function handle_checkout_session_completed( $session ): void {
		$email = $session->customer_email ?? '';
		$amount = $session->amount_total ?? 0;
		$subscription_id = $session->subscription ?? null;
		$customer_id = $session->customer ?? null;

		// Get database instance
		$database = \LNMC_Member_Hub\Plugin::get_instance()->get_database();

		// Get or create user
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			$user_id = wp_create_user( $email, wp_generate_password(), $email );
			if ( is_wp_error( $user_id ) ) {
				error_log( '[LNMC Member Hub] Failed to create user for email: ' . $email );
				return;
			}
			$user = get_user_by( 'id', $user_id );
		}

		// Create or update member record
		$member_data = array(
			'user_id' => $user->ID,
			'membership_status' => 'active',
			'membership_type' => 'standard',
			'payment_amount' => $amount / 100, // Convert from cents
			'payment_currency' => strtoupper( $session->currency ?? 'usd' ),
			'payment_status' => 'succeeded',
			'stripe_customer_id' => $customer_id,
			'stripe_subscription_id' => $subscription_id,
			'last_payment_date' => current_time( 'mysql' ),
			'privacy_consent' => 1,
		);

		$member_id = $database->save_member( $member_data );
		if ( ! $member_id ) {
			error_log( '[LNMC Member Hub] Failed to save member data for user: ' . $user->ID );
			return;
		}

		// Add membership role
		$user->add_role( 'lnmc_member' );

		// Log the activity
		$database->log_activity(
			$member_id,
			'payment_succeeded',
			sprintf( 'Payment completed for $%.2f', $amount / 100 ),
			array(
				'amount' => $amount,
				'currency' => $session->currency ?? 'usd',
				'session_id' => $session->id,
			)
		);

		// Log the event
		error_log( sprintf(
			'[LNMC Member Hub] Checkout session completed for user %s (ID: %d) with amount %d',
			$user->display_name,
			$user->ID,
			$amount
		) );
	}

	/**
	 * Handle customer.subscription.deleted event.
	 *
	 * @param object $subscription Stripe subscription object.
	 * @return void
	 */
	private function handle_subscription_deleted( $subscription ): void {
		$subscription_id = $subscription->id ?? '';
		$customer_id = $subscription->customer ?? '';

		// Get database instance
		$database = \LNMC_Member_Hub\Plugin::get_instance()->get_database();

		// Find member by subscription ID or customer ID
		global $wpdb;
		$members_table = $wpdb->prefix . 'lnmc_members';
		
		$member = null;
		if ( ! empty( $subscription_id ) ) {
			$member = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM $members_table WHERE stripe_subscription_id = %s",
					$subscription_id
				)
			);
		}
		
		if ( ! $member && ! empty( $customer_id ) ) {
			$member = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM $members_table WHERE stripe_customer_id = %s",
					$customer_id
				)
			);
		}

		if ( $member && $database ) {
			// Update member status
			$update_data = array(
				'membership_status' => 'cancelled',
				'payment_status' => 'cancelled',
			);
			
			global $wpdb;
			$wpdb->update(
				$members_table,
				$update_data,
				array( 'id' => $member->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			// Remove membership role from user
			$user = get_user_by( 'ID', $member->user_id );
			if ( $user ) {
				$user->remove_role( 'lnmc_member' );
			}

			// Log the activity
			$database->log_activity(
				$member->id,
				'subscription_cancelled',
				'Subscription cancelled by Stripe',
				array( 'subscription_id' => $subscription_id )
			);

			// Log the event
			error_log( sprintf(
				'[LNMC Member Hub] Subscription cancelled for member ID: %d',
				$member->id
			) );
		} else {
			error_log( '[LNMC Member Hub] Could not find member for cancelled subscription: ' . $subscription_id );
		}
	}

	/**
	 * Handle invoice.payment_succeeded event.
	 *
	 * @param object $invoice Stripe invoice object.
	 * @return void
	 */
	private function handle_invoice_payment_succeeded( $invoice ): void {
		$subscription_id = $invoice->subscription ?? '';
		$amount = $invoice->amount_paid ?? 0;

		if ( ! empty( $subscription_id ) ) {
			$users = get_users( array(
				'meta_key' => 'lnmc_subscription_id',
				'meta_value' => $subscription_id,
				'number' => 1,
			) );

			if ( ! empty( $users ) ) {
				$user = $users[0];
				update_user_meta( $user->ID, 'lnmc_subscription_status', 'active' );
				update_user_meta( $user->ID, 'lnmc_last_payment_date', current_time( 'mysql' ) );
				update_user_meta( $user->ID, 'lnmc_last_payment_amount', $amount );

				error_log( sprintf(
					'[LNMC Member Hub] Invoice payment succeeded for user %s (ID: %d) with amount %d',
					$user->display_name,
					$user->ID,
					$amount
				) );
			}
		}
	}

	/**
	 * Handle invoice.payment_failed event.
	 *
	 * @param object $invoice Stripe invoice object.
	 * @return void
	 */
	private function handle_invoice_payment_failed( $invoice ): void {
		$subscription_id = $invoice->subscription ?? '';

		if ( ! empty( $subscription_id ) ) {
			$users = get_users( array(
				'meta_key' => 'lnmc_subscription_id',
				'meta_value' => $subscription_id,
				'number' => 1,
			) );

			if ( ! empty( $users ) ) {
				$user = $users[0];
				update_user_meta( $user->ID, 'lnmc_subscription_status', 'past_due' );
				update_user_meta( $user->ID, 'lnmc_payment_failed_date', current_time( 'mysql' ) );

				error_log( sprintf(
					'[LNMC Member Hub] Invoice payment failed for user %s (ID: %d)',
					$user->display_name,
					$user->ID
				) );
			}
		}
	}

	/**
	 * Get the REST API namespace.
	 *
	 * @return string
	 */
	public function get_namespace(): string {
		return self::NAMESPACE;
	}

	/**
	 * Generate a nonce for REST API requests.
	 *
	 * @param string $action The action name.
	 * @return string
	 */
	public function create_nonce( string $action = 'ping' ): string {
		return wp_create_nonce( 'lnmc_member_hub_' . $action );
	}

	/**
	 * Verify a nonce for REST API requests.
	 *
	 * @param string $nonce The nonce to verify.
	 * @param string $action The action name.
	 * @return bool
	 */
	public function verify_nonce( string $nonce, string $action = 'ping' ): bool {
		return wp_verify_nonce( $nonce, 'lnmc_member_hub_' . $action ) !== false;
	}
}
