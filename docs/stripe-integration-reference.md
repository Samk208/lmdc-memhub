# Stripe Integration Reference & Verification Guide  
**Plugin Context:** LNMC Member Hub (WordPress plugin)  
**Focus:** Front-end membership form + Stripe Checkout + Webhooks + Conditional Script Enqueue  

---

## ✅ Goals  
- Load `https://js.stripe.com/v3/` and the plugin’s `stripe-checkout.js` only when membership form or hub page appears (shortcode or slug).  
- Set up Stripe Checkout sessions via AJAX and redirect to hosted checkout page.  
- Set up Stripe Webhooks to handle `checkout.session.completed` (and other relevant events) to update membership status in WordPress.  
- Ensure detection logic works even when using page builders (e.g., Divi) and caching.

---

## 📚 Key Reference Documents  
- **Receive Stripe events in your webhook endpoint** — Stripe official docs. :contentReference[oaicite:1]{index=1}  
- **How to configure your site to accept webhooks from Stripe (WordPress)** — WP Simple Pay article. :contentReference[oaicite:2]{index=2}  
- **Stripe Webhooks Setup & Configuration for WooCommerce** — WooCommerce docs. :contentReference[oaicite:3]{index=3}  
- **How to set up WordPress Stripe Webhook (non-WooCommerce)** — WPForms blog. :contentReference[oaicite:4]{index=4}  
- **Enqueue scripts only when shortcode present** — WordPress StackExchange & blog posts (for conditional script loading). :contentReference[oaicite:5]{index=5}  

---

## 🔧 Code Patterns  
### 1. Register & Enqueue Scripts  
```php
add_action( 'wp_enqueue_scripts', function(){
    wp_register_script(
        'stripe-js',
        'https://js.stripe.com/v3/',
        [],
        null,
        true
    );
    wp_register_script(
        'lnmc-stripe-checkout',
        plugin_dir_url( __FILE__ ) . 'assets/js/stripe-checkout.js',
        ['stripe-js','jquery'],
        '1.0.0',
        true
    );
});

2. Conditional Enqueue Logic (shortcode + slug + builder metadata)
function should_enqueue_stripe() : bool {
    if ( is_page( 'membership' ) || is_page( 44 ) ) {
        return true;
    }

    global $post;
    if ( is_a( $post, 'WP_Post' ) ) {
        if ( has_shortcode( $post->post_content, 'lnmc_membership_hub' ) ) {
            return true;
        }

        // Example fallback: Divi builder metadata
        $meta = get_post_meta( $post->ID, '_et_pb_builder_data', true );
        if ( ! empty( $meta ) && strpos( $meta, 'lnmc_membership_hub' ) !== false ) {
            return true;
        }
    }

    return false;
}

add_action( 'wp_enqueue_scripts', function(){
    if ( should_enqueue_stripe() ) {
        wp_enqueue_script( 'stripe-js' );
        wp_enqueue_script( 'lnmc-stripe-checkout' );
    }
});

3. Webhook Handler Pattern
require_once 'vendor/autoload.php';
\Stripe\Stripe::setApiKey( $secret_key );

$payload    = @file_get_contents( 'php://input' );
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$event      = null;

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload, $sig_header, $endpoint_secret
    );
} catch( \UnexpectedValueException $e ) {
    http_response_code( 400 );
    exit();
} catch( \Stripe\Exception\SignatureVerificationException $e ) {
    http_response_code( 400 );
    exit();
}

// Process event
switch ( $event->type ) {
    case 'checkout.session.completed':
        $session = $event->data->object;
        // Handle membership activation
        break;

    // Add other event types if required
    default:
        // Unexpected event type
        break;
}

http_response_code(200);
```

🧪 Verification & Test Checklist  
- Clear Divi/Builder cache (static CSS/JS) and disable any page caching plugin for dev test.  
- On page /membership, view source and search for <script src="https://js.stripe.com/v3/"> and for stripe-checkout.js.  
- In DevTools → Network tab (filter “JS”) confirm both scripts loaded with HTTP 200.  
- Use test card (e.g., 4242 4242 4242 4242) via form; watch AJAX call to action=create_stripe_checkout_session returns { success: true, data: { session_id } }.  
- After redirect to Stripe Checkout and complete payment, ensure delivery of checkout.session.completed webhook and membership record created/updated in WordPress.  
- On payment cancel, verify return URL is /membership and no membership record is created.  
- Check logs (via error_log() if used) for detection path (slug, shortcode, metadata).  
- After tests succeed, remove/disable debug error_log() statements or wrap them behind if ( WP_DEBUG ).  

📌 Best Practice Guidance  
- Register your scripts once, then enqueue only when needed to reduce unnecessary asset loading.  
- Use has_shortcode() and fallback detection (slug/page ID and builder metadata) to handle page builder contexts.  
- Load Stripe.js from official URL (https://js.stripe.com/v3/) as per Stripe docs.  
- Enqueue in the footer (true last param) for performance benefit.  
- Webhooks: ensure your endpoint returns 2xx status quickly; avoid heavy synchronous work before response. (Stripe Docs)  
- During production, ensure HTTPS on webhook endpoint and live mode keys used.  
- Limit webhook event types to only those your plugin needs (to reduce unnecessary load). (WP Simple Pay)  

🧑‍💻 Summary for Developer  
Your help is needed to:  
- Review and refine the conditional enqueue logic (script loading) so it reliably works in builder + shortcode + page slug contexts.  
- Verify the membership form captures required data (email, amount/plan) and correctly triggers the AJAX + checkout flow.  
- Validate webhook endpoint is set up in Stripe dashboard (test mode) with correct signing secret and configured event types.  
- Provide a dev report identifying any missing form fields or cases where the AJAX session creation fails (e.g., 400 status).  
- Ensure we can move from test to live mode with minimal code changes by toggling LNMC_STRIPE_MODE, live keys and webhook secret.  
Thank you in advance for your expertise on this!

---

Feel free to save this as `stripe-integration-reference.md` in your repo or share it with your developer via email/PM. If you’d like, I can **provide a version with front-matter** for your documentation system (e.g., MkDocs or Docusaurus) or convert it to PDF.
::contentReference[oaicite:8]{index=8}
[web](use web search tool) "C:\Users\Lenovo\Local Sites\lmndclocal\app\public\wp-content\plugins\lnmc-member-hub\docs\stripe-enqueue-checkout-reference.md"  "C:\Users\Lenovo\Local Sites\lmndclocal\app\public\wp-content\debug.log"
