<?php
/**
 * Compatibility Helper Class
 *
 * Handles compatibility with common WordPress security and performance plugins.
 *
 * @package LNMC_Member_Hub
 * @since 1.0.0
 */

namespace LNMC_Member_Hub\Utils;

/**
 * Compatibility helper for handling plugin conflicts.
 */
class Compatibility_Helper {

    /**
     * Initialize compatibility hooks.
     *
     * @return void
     */
    public static function init(): void {
        add_action( 'init', array( __CLASS__, 'setup_compatibility_hooks' ) );
        add_action( 'wp_loaded', array( __CLASS__, 'handle_security_plugin_conflicts' ) );
        add_action( 'rest_api_init', array( __CLASS__, 'handle_rest_api_compatibility' ) );
        add_filter( 'rest_pre_dispatch', array( __CLASS__, 'bypass_cache_for_rest_api' ), 10, 3 );
    }

    /**
     * Setup compatibility hooks for various plugins.
     *
     * @return void
     */
    public static function setup_compatibility_hooks(): void {
        // Wordfence compatibility
        if ( self::is_wordfence_active() ) {
            self::setup_wordfence_compatibility();
        }

        // Cache plugin compatibility
        if ( self::is_cache_plugin_active() ) {
            self::setup_cache_plugin_compatibility();
        }

        // Security plugin compatibility
        if ( self::is_security_plugin_active() ) {
            self::setup_security_plugin_compatibility();
        }

        // WooCommerce compatibility
        if ( self::is_woocommerce_active() ) {
            self::setup_woocommerce_compatibility();
        }
    }

    /**
     * Handle security plugin conflicts.
     *
     * @return void
     */
    public static function handle_security_plugin_conflicts(): void {
        // Add headers to prevent security plugin blocking
        if ( self::is_rest_api_request() ) {
            self::add_security_headers();
        }

        // Whitelist our endpoints in security plugins
        self::whitelist_endpoints();
    }

    /**
     * Handle REST API compatibility.
     *
     * @return void
     */
    public static function handle_rest_api_compatibility(): void {
        // Add cache-busting headers for REST API
        add_filter( 'rest_post_dispatch', array( __CLASS__, 'add_rest_cache_headers' ), 10, 3 );
        
        // Ensure proper CORS headers
        add_action( 'rest_api_init', array( __CLASS__, 'add_cors_headers' ) );
    }

    /**
     * Bypass cache for REST API requests.
     *
     * @param mixed           $result  Response to replace the requested version with.
     * @param \WP_REST_Server $server  Server instance.
     * @param \WP_REST_Request $request Request used to generate the response.
     * @return mixed
     */
    public static function bypass_cache_for_rest_api( $result, $server, $request ) {
        if ( self::is_lnmc_endpoint( $request ) ) {
            // Add no-cache headers
            header( 'Cache-Control: no-cache, no-store, must-revalidate' );
            header( 'Pragma: no-cache' );
            header( 'Expires: 0' );
        }
        return $result;
    }

    /**
     * Setup Wordfence compatibility.
     *
     * @return void
     */
    private static function setup_wordfence_compatibility(): void {
        // Add our endpoints to Wordfence whitelist
        add_filter( 'wordfence_ls_2fa_bypass', array( __CLASS__, 'wordfence_2fa_bypass' ), 10, 2 );
        
        // Ensure webhook endpoints are not blocked
        add_action( 'init', array( __CLASS__, 'wordfence_webhook_whitelist' ) );
    }

    /**
     * Setup cache plugin compatibility.
     *
     * @return void
     */
    private static function setup_cache_plugin_compatibility(): void {
        // WP Rocket compatibility
        if ( self::is_wp_rocket_active() ) {
            add_filter( 'rocket_cache_reject_uri', array( __CLASS__, 'wp_rocket_exclude_lnmc_endpoints' ) );
            add_filter( 'rocket_cache_reject_cookies', array( __CLASS__, 'wp_rocket_exclude_lnmc_cookies' ) );
        }

        // W3 Total Cache compatibility
        if ( self::is_w3_total_cache_active() ) {
            add_filter( 'w3tc_can_cache', array( __CLASS__, 'w3tc_exclude_lnmc_requests' ), 10, 2 );
        }

        // WP Super Cache compatibility
        if ( self::is_wp_super_cache_active() ) {
            add_action( 'init', array( __CLASS__, 'wp_super_cache_exclude_lnmc' ) );
        }

        // Autoptimize compatibility
        if ( self::is_autoptimize_active() ) {
            add_filter( 'autoptimize_filter_js_exclude', array( __CLASS__, 'autoptimize_exclude_lnmc_js' ) );
            add_filter( 'autoptimize_filter_css_exclude', array( __CLASS__, 'autoptimize_exclude_lnmc_css' ) );
        }
    }

    /**
     * Setup security plugin compatibility.
     *
     * @return void
     */
    private static function setup_security_plugin_compatibility(): void {
        // Sucuri compatibility
        if ( self::is_sucuri_active() ) {
            add_filter( 'sucuri_block_request', array( __CLASS__, 'sucuri_allow_lnmc_requests' ), 10, 2 );
        }

        // iThemes Security compatibility
        if ( self::is_ithemes_security_active() ) {
            add_filter( 'itsec_filter_apache_server_config_modification', array( __CLASS__, 'ithemes_allow_lnmc_endpoints' ) );
        }

        // All In One WP Security compatibility
        if ( self::is_all_in_one_wp_security_active() ) {
            add_filter( 'aiowps_firewall_rules', array( __CLASS__, 'aiowps_allow_lnmc_endpoints' ) );
        }
    }

    /**
     * Setup WooCommerce compatibility.
     *
     * @return void
     */
    private static function setup_woocommerce_compatibility(): void {
        // Prevent WooCommerce from interfering with our payment processing
        add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'prevent_woocommerce_payment_conflict' ) );
        
        // Ensure our shortcodes don't conflict with WooCommerce
        add_filter( 'woocommerce_shortcode_products_query', array( __CLASS__, 'prevent_woocommerce_shortcode_conflict' ), 10, 2 );
    }

    /**
     * Add security headers for REST API requests.
     *
     * @return void
     */
    private static function add_security_headers(): void {
        if ( self::is_lnmc_rest_request() ) {
            header( 'X-Content-Type-Options: nosniff' );
            header( 'X-Frame-Options: DENY' );
            header( 'X-XSS-Protection: 1; mode=block' );
            header( 'Referrer-Policy: strict-origin-when-cross-origin' );
        }
    }

    /**
     * Whitelist endpoints in security plugins.
     *
     * @return void
     */
    private static function whitelist_endpoints(): void {
        $endpoints = array(
            '/wp-json/lnmc/v1/ping',
            '/wp-json/lnmc/v1/member/flag',
            '/wp-json/lnmc/v1/payments/create-checkout',
            '/wp-json/lnmc/v1/payments/webhook'
        );

        foreach ( $endpoints as $endpoint ) {
            // Add to various security plugin whitelists
            self::add_to_security_whitelist( $endpoint );
        }
    }

    /**
     * Add endpoint to security plugin whitelists.
     *
     * @param string $endpoint The endpoint to whitelist.
     * @return void
     */
    private static function add_to_security_whitelist( string $endpoint ): void {
        // Wordfence whitelist
        if ( self::is_wordfence_active() ) {
            $whitelist = get_option( 'wordfence_whitelist', array() );
            if ( ! in_array( $endpoint, $whitelist, true ) ) {
                $whitelist[] = $endpoint;
                update_option( 'wordfence_whitelist', $whitelist );
            }
        }

        // Sucuri whitelist
        if ( self::is_sucuri_active() ) {
            $sucuri_whitelist = get_option( 'sucuri_whitelist', array() );
            if ( ! in_array( $endpoint, $sucuri_whitelist, true ) ) {
                $sucuri_whitelist[] = $endpoint;
                update_option( 'sucuri_whitelist', $sucuri_whitelist );
            }
        }
    }

    /**
     * Add REST cache headers.
     *
     * @param \WP_REST_Response $response Response object.
     * @param \WP_REST_Server   $server   Server instance.
     * @param \WP_REST_Request  $request  Request object.
     * @return \WP_REST_Response
     */
    public static function add_rest_cache_headers( $response, $server, $request ) {
        if ( self::is_lnmc_endpoint( $request ) ) {
            $response->header( 'Cache-Control', 'no-cache, no-store, must-revalidate' );
            $response->header( 'Pragma', 'no-cache' );
            $response->header( 'Expires', '0' );
        }
        return $response;
    }

    /**
     * Add CORS headers.
     *
     * @return void
     */
    public static function add_cors_headers(): void {
        if ( self::is_lnmc_rest_request() ) {
            header( 'Access-Control-Allow-Origin: *' );
            header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
            header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce' );
        }
    }

    /**
     * Wordfence 2FA bypass for our endpoints.
     *
     * @param bool   $bypass Whether to bypass 2FA.
     * @param string $url    The URL being accessed.
     * @return bool
     */
    public static function wordfence_2fa_bypass( $bypass, $url ): bool {
        if ( self::is_lnmc_endpoint_url( $url ) ) {
            return true;
        }
        return $bypass;
    }

    /**
     * Wordfence webhook whitelist.
     *
     * @return void
     */
    public static function wordfence_webhook_whitelist(): void {
        if ( self::is_webhook_request() ) {
            // Add webhook IPs to Wordfence whitelist
            $stripe_ips = self::get_stripe_ips();
            foreach ( $stripe_ips as $ip ) {
                self::whitelist_ip_in_wordfence( $ip );
            }
        }
    }

    /**
     * WP Rocket exclude LNMC endpoints.
     *
     * @param array $uris URIs to exclude from caching.
     * @return array
     */
    public static function wp_rocket_exclude_lnmc_endpoints( $uris ): array {
        $lnmc_uris = array(
            '/wp-json/lnmc/',
            '/membership-success',
            '/membership-cancel'
        );
        return array_merge( $uris, $lnmc_uris );
    }

    /**
     * WP Rocket exclude LNMC cookies.
     *
     * @param array $cookies Cookies to exclude from caching.
     * @return array
     */
    public static function wp_rocket_exclude_lnmc_cookies( $cookies ): array {
        $lnmc_cookies = array(
            'lnmc_member_hub_session',
            'lnmc_payment_session'
        );
        return array_merge( $cookies, $lnmc_cookies );
    }

    /**
     * W3 Total Cache exclude LNMC requests.
     *
     * @param bool $can_cache Whether the request can be cached.
     * @param string $buffer  The page buffer.
     * @return bool
     */
    public static function w3tc_exclude_lnmc_requests( $can_cache, $buffer ): bool {
        if ( self::is_lnmc_rest_request() || self::is_lnmc_page() ) {
            return false;
        }
        return $can_cache;
    }

    /**
     * WP Super Cache exclude LNMC.
     *
     * @return void
     */
    public static function wp_super_cache_exclude_lnmc(): void {
        if ( self::is_lnmc_rest_request() || self::is_lnmc_page() ) {
            define( 'DONOTCACHEPAGE', true );
        }
    }

    /**
     * Autoptimize exclude LNMC JavaScript.
     *
     * @param string $exclude JavaScript exclusions.
     * @return string
     */
    public static function autoptimize_exclude_lnmc_js( $exclude ): string {
        $lnmc_js = 'lnmc-member-hub,stripe.js';
        return $exclude . ',' . $lnmc_js;
    }

    /**
     * Autoptimize exclude LNMC CSS.
     *
     * @param string $exclude CSS exclusions.
     * @return string
     */
    public static function autoptimize_exclude_lnmc_css( $exclude ): string {
        $lnmc_css = 'lnmc-member-hub';
        return $exclude . ',' . $lnmc_css;
    }

    /**
     * Sucuri allow LNMC requests.
     *
     * @param bool   $block Whether to block the request.
     * @param string $url   The URL being accessed.
     * @return bool
     */
    public static function sucuri_allow_lnmc_requests( $block, $url ): bool {
        if ( self::is_lnmc_endpoint_url( $url ) ) {
            return false; // Don't block
        }
        return $block;
    }

    /**
     * iThemes Security allow LNMC endpoints.
     *
     * @param string $modification The Apache configuration modification.
     * @return string
     */
    public static function ithemes_allow_lnmc_endpoints( $modification ): string {
        $lnmc_rules = '
# Allow LNMC Member Hub endpoints
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule ^wp-json/lnmc/ - [L]
</IfModule>';
        return $modification . $lnmc_rules;
    }

    /**
     * All In One WP Security allow LNMC endpoints.
     *
     * @param array $rules Firewall rules.
     * @return array
     */
    public static function aiowps_allow_lnmc_endpoints( $rules ): array {
        $lnmc_rules = array(
            'lnmc_webhook' => array(
                'type' => 'whitelist',
                'path' => '/wp-json/lnmc/v1/payments/webhook',
                'description' => 'LNMC Stripe Webhook'
            )
        );
        return array_merge( $rules, $lnmc_rules );
    }

    /**
     * Prevent WooCommerce payment conflict.
     *
     * @param array $gateways Payment gateways.
     * @return array
     */
    public static function prevent_woocommerce_payment_conflict( $gateways ): array {
        // Remove WooCommerce Stripe gateway if our plugin is handling payments
        if ( self::is_lnmc_payment_page() ) {
            $gateways = array_filter( $gateways, function( $gateway ) {
                return strpos( $gateway, 'stripe' ) === false;
            } );
        }
        return $gateways;
    }

    /**
     * Prevent WooCommerce shortcode conflict.
     *
     * @param array $query_args Query arguments.
     * @param array $atts       Shortcode attributes.
     * @return array
     */
    public static function prevent_woocommerce_shortcode_conflict( $query_args, $atts ): array {
        // Prevent WooCommerce from interfering with our shortcodes
        if ( self::is_lnmc_shortcode_context() ) {
            $query_args['post__not_in'] = array( -1 ); // Empty result
        }
        return $query_args;
    }

    /**
     * Get Stripe IP addresses for whitelisting.
     *
     * @return array
     */
    private static function get_stripe_ips(): array {
        return array(
            '3.18.12.63',
            '3.130.192.231',
            '13.235.14.237',
            '13.235.122.149',
            '18.211.135.69',
            '35.154.171.200',
            '52.15.183.38',
            '54.187.174.169',
            '54.187.205.235',
            '54.187.216.72',
            '54.241.31.99',
            '54.241.31.102',
            '54.241.34.107'
        );
    }

    /**
     * Whitelist IP in Wordfence.
     *
     * @param string $ip IP address to whitelist.
     * @return void
     */
    private static function whitelist_ip_in_wordfence( string $ip ): void {
        if ( self::is_wordfence_active() ) {
            $whitelist = get_option( 'wordfence_whitelist_ips', array() );
            if ( ! in_array( $ip, $whitelist, true ) ) {
                $whitelist[] = $ip;
                update_option( 'wordfence_whitelist_ips', $whitelist );
            }
        }
    }

    /**
     * Check if Wordfence is active.
     *
     * @return bool
     */
    private static function is_wordfence_active(): bool {
        return self::is_plugin_active( 'wordfence/wordfence.php' );
    }

    /**
     * Check if cache plugin is active.
     *
     * @return bool
     */
    private static function is_cache_plugin_active(): bool {
        return self::is_wp_rocket_active() || 
               self::is_w3_total_cache_active() || 
               self::is_wp_super_cache_active() || 
               self::is_autoptimize_active();
    }

    /**
     * Check if security plugin is active.
     *
     * @return bool
     */
    private static function is_security_plugin_active(): bool {
        return self::is_sucuri_active() || 
               self::is_ithemes_security_active() || 
               self::is_all_in_one_wp_security_active();
    }

    /**
     * Check if WooCommerce is active.
     *
     * @return bool
     */
    private static function is_woocommerce_active(): bool {
        return self::is_plugin_active( 'woocommerce/woocommerce.php' );
    }

    /**
     * Check if WP Rocket is active.
     *
     * @return bool
     */
    private static function is_wp_rocket_active(): bool {
        return self::is_plugin_active( 'wp-rocket/wp-rocket.php' );
    }

    /**
     * Check if W3 Total Cache is active.
     *
     * @return bool
     */
    private static function is_w3_total_cache_active(): bool {
        return self::is_plugin_active( 'w3-total-cache/w3-total-cache.php' );
    }

    /**
     * Check if WP Super Cache is active.
     *
     * @return bool
     */
    private static function is_wp_super_cache_active(): bool {
        return self::is_plugin_active( 'wp-super-cache/wp-cache.php' );
    }

    /**
     * Check if Autoptimize is active.
     *
     * @return bool
     */
    private static function is_autoptimize_active(): bool {
        return self::is_plugin_active( 'autoptimize/autoptimize.php' );
    }

    /**
     * Check if Sucuri is active.
     *
     * @return bool
     */
    private static function is_sucuri_active(): bool {
        return self::is_plugin_active( 'sucuri-scanner/sucuri.php' );
    }

    /**
     * Check if iThemes Security is active.
     *
     * @return bool
     */
    private static function is_ithemes_security_active(): bool {
        return self::is_plugin_active( 'better-wp-security/better-wp-security.php' );
    }

    /**
     * Check if All In One WP Security is active.
     *
     * @return bool
     */
    private static function is_all_in_one_wp_security_active(): bool {
        return self::is_plugin_active( 'all-in-one-wp-security-and-firewall/wp-security.php' );
    }

    /**
     * Check if plugin is active.
     *
     * @param string $plugin_file Plugin file path.
     * @return bool
     */
    private static function is_plugin_active( string $plugin_file ): bool {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active( $plugin_file );
    }

    /**
     * Check if current request is a REST API request.
     *
     * @return bool
     */
    private static function is_rest_api_request(): bool {
        return defined( 'REST_REQUEST' ) && REST_REQUEST;
    }

    /**
     * Check if current request is an LNMC REST request.
     *
     * @return bool
     */
    private static function is_lnmc_rest_request(): bool {
        return self::is_rest_api_request() && 
               ( strpos( $_SERVER['REQUEST_URI'] ?? '', '/wp-json/lnmc/' ) !== false );
    }

    /**
     * Check if request is for an LNMC endpoint.
     *
     * @param \WP_REST_Request $request Request object.
     * @return bool
     */
    private static function is_lnmc_endpoint( $request ): bool {
        $route = $request->get_route();
        return strpos( $route, '/lnmc/' ) !== false;
    }

    /**
     * Check if URL is an LNMC endpoint.
     *
     * @param string $url URL to check.
     * @return bool
     */
    private static function is_lnmc_endpoint_url( string $url ): bool {
        return strpos( $url, '/wp-json/lnmc/' ) !== false;
    }

    /**
     * Check if current request is a webhook request.
     *
     * @return bool
     */
    private static function is_webhook_request(): bool {
        return strpos( $_SERVER['REQUEST_URI'] ?? '', '/wp-json/lnmc/v1/payments/webhook' ) !== false;
    }

    /**
     * Check if current page is an LNMC page.
     *
     * @return bool
     */
    private static function is_lnmc_page(): bool {
        global $post;
        if ( $post ) {
            return has_shortcode( $post->post_content, 'lnmc_member_hub' ) ||
                   has_shortcode( $post->post_content, 'lnmc_payment_form' );
        }
        return false;
    }

    /**
     * Check if current page is an LNMC payment page.
     *
     * @return bool
     */
    private static function is_lnmc_payment_page(): bool {
        return is_page( 'membership-payment' ) || 
               strpos( $_SERVER['REQUEST_URI'] ?? '', 'membership' ) !== false;
    }

    /**
     * Check if current context is an LNMC shortcode context.
     *
     * @return bool
     */
    private static function is_lnmc_shortcode_context(): bool {
        global $post;
        if ( $post ) {
            return has_shortcode( $post->post_content, 'lnmc_member_hub' ) ||
                   has_shortcode( $post->post_content, 'lnmc_payment_form' );
        }
        return false;
    }
}
