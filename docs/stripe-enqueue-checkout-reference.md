Here’s a **robust markdown reference document** tailored for your plugin use case (**LNMC Member Hub**, with Stripe/Checkout integration) that you can share with your developer or include in your workflow tool. It covers best practices, relevant links, and code patterns to guide the enqueue/checkout fix.

---

# Reference — Enqueue + Stripe Checkout Integration for WordPress

**Project**: LNMC Member Hub
**Focus**: Ensuring proper asset loading (Stripe JS + plugin checkout JS) and checkout flow integration.

---

## 🧠 Why This Matters

* Proper asset loading ensures that your checkout assets (e.g., `https://js.stripe.com/v3/`, your plugin’s `stripe-checkout.js`) are loaded only when needed (improves performance, avoids conflicts) and work correctly with page builder environments (e.g., Divi).
* Conditional enqueue logic that fails (e.g., due to builder modules or caching) leads to missing scripts → checkout form cannot initialise → membership purchase blocked.
* Stripe Checkout flow also demands correct client side setup + server side SDK + webhook configuration; asset loading is the gateway to front-end ability to trigger checkout.

---

## 📚 Key Reference Documentation

| Topic                                                   | Source                                                                                                                                                                                                                | Notes                                                                                             |
| ------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------- |
| `wp_enqueue_script()` & best practices                  | [WordPress Developer Reference](https://developer.wordpress.org/reference/functions/wp_enqueue_script/) ([WordPress Developer Resources][1])                                                                          | Use `wp_enqueue_scripts` hook for front-end scripts; register then enqueue; observe dependencies. |
| Loading scripts only when shortcode present             | [WPExplorer – Load Scripts If Shortcode Exists](https://www.wpexplorer.com/load-scripts-shortcode/) ([WPExplorer][2])                                                                                                 | Shows pattern: register script early, then detect shortcode in content and enqueue.               |
| Enqueue logic discussion with builder metadata fallback | [StackExchange – Enqueue Scripts/Styles when shortcode is present](https://wordpress.stackexchange.com/questions/165754/enqueue-scripts-styles-when-shortcode-is-present) ([WordPress Development Stack Exchange][3]) | Contains example for checking post_meta when builder hides shortcode in content.                  |
| General asset loading & performance best practice       | [SitePoint – Enqueuing Scripts/Styles in WP](https://www.sitepoint.com/enqueuing-scripts-styles-wordpress/) ([SitePoint][4])                                                                                          | Useful to understand when and how to conditionally load assets.                                   |

---

## 🔧 Proven Code Patterns

Below are patterns you can adapt. They reflect the conditional enqueue logic your plugin needs.

### 1. Registering scripts

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
        plugin_dir_url(__FILE__) . 'assets/js/stripe-checkout.js',
        ['stripe-js','jquery'],
        '1.0.0',
        true
    );
});
```

### 2. Conditional enqueue (shortcode + slug/ID + builder metadata fallback)

```php
add_action( 'wp_enqueue_scripts', function(){
    global $post;
    $should_enqueue = false;

    if ( is_page( 'membership' ) || is_page( 44 ) ) {
        $should_enqueue = true;
    } elseif ( is_a( $post, 'WP_Post' ) ) {
        if ( has_shortcode( $post->post_content, 'lnmc_membership_hub' ) ) {
            $should_enqueue = true;
        } else {
            // fallback: check Divi builder metadata for shortcode
            $meta = get_post_meta( $post->ID, '_et_pb_builder_data', true );
            if ( ! empty($meta) && strpos( $meta, 'lnmc_membership_hub' ) !== false ) {
                $should_enqueue = true;
            }
        }
    }

    if ( $should_enqueue ) {
        wp_enqueue_script( 'stripe-js' );
        wp_enqueue_script( 'lnmc-stripe-checkout' );
    }
});
```

### 3. Shortcode context enqueue (alternative)

If detection in `wp_enqueue_scripts` is unreliable in certain builder contexts, you can enqueue directly in shortcode handler:

```php
function lnmc_membership_form_shortcode( $atts ){
    wp_enqueue_script( 'stripe-js' );
    wp_enqueue_script( 'lnmc-stripe-checkout' );
    // render form markup …
}
add_shortcode( 'lnmc_membership_form', 'lnmc_membership_form_shortcode' );
```

---

## ✅ Checklist for Developer / QA

* [ ] Ensure `stripe-js` and `lnmc-stripe-checkout` scripts are registered.
* [ ] Verify that on `/membership` (slug) page the scripts appear in page source (search `<script src="https://js.stripe.com/v3/">`).
* [ ] In browser DevTools → Network tab (filter “JS”) confirm both scripts load with HTTP 200 status.
* [ ] Submit the membership form → check AJAX to `admin-ajax.php?action=create_stripe_checkout_session` returns valid session and redirect via `stripe.redirectToCheckout()`.
* [ ] Cancel checkout → ensure redirect back to `/membership`.
* [ ] Test with Divi builder caching cleared: Divi → Builder → Performance → Clear Static CSS/JS Files.
* [ ] Switch to default theme temporarily (Twenty Twenty-Three) to check builder/theme interference.
* [ ] Check `debug.log` if you log via `error_log()` in detection logic to verify which path is used (slug/ID vs shortcode vs metadata fallback).
* [ ] Remove or wrap `error_log()` statements behind `if ( WP_DEBUG )` before production release.
* [ ] Ensure both test mode and live mode keys are set correctly in `wp-config.php`, `LNMC_STRIPE_MODE`, `LNMC_STRIPE_TEST_PUBLISHABLE_KEY`, `LNMC_STRIPE_TEST_SECRET_KEY`, `LNMC_STRIPE_TEST_WEBHOOK_SECRET` (when ready).

---

## 📌 Best Practices Summary

* **Register scripts separately**, then enqueue only when satisfied conditions. ([Kinsta®][5])
* Use `has_shortcode()` for shortcode detection, **and** provide fallback detection (e.g., page slug/ID or builder metadata) when page builder or caching hides it. ([WordPress Development Stack Exchange][3])
* Hook into `wp_enqueue_scripts` for front-end assets; avoid enqueuing inside shortcode callback unless unavoidable (because of timing issues). ([WordPress.org][6])
* Enqueue scripts in footer (`true` in `$in_footer` parameter) when possible for better performance. ([Kinsta®][5])
* Minimize loading scripts on pages where they’re not used — reduces overhead and improves compatibility.
* When working with page builders (Divi, Elementor, etc), detect builder metadata or enforce slug/ID fallback logic because builder modules may store shortcode differently.
* Always test in builder mode, turn off caching/minification during dev, and verify presence of scripts before launching.
* For Stripe specifically: load `https://js.stripe.com/v3/` only when needed, ensure your plugin’s script depends on it, localize with publishable key & AJAX URL, ensure server SDK and webhooks are configured for full flow.

---

Feel free to distribute this markdown document to your developer (cursor), include it in your Windsurf workflow, or keep it in your repo as `docs/stripe-enqueue-checkout-reference.md`. If you want, I can also **generate a PDF version** of this reference doc.

[1]: https://developer.wordpress.org/reference/functions/wp_enqueue_script/?utm_source=chatgpt.com "wp_enqueue_script() – Function - WordPress Developer Resources"
[2]: https://www.wpexplorer.com/load-scripts-shortcode/?utm_source=chatgpt.com "How to Load a Script in WordPress if a Shortcode Exists - WPExplorer"
[3]: https://wordpress.stackexchange.com/questions/165754/enqueue-scripts-styles-when-shortcode-is-present?utm_source=chatgpt.com "Enqueue Scripts / Styles when shortcode is present"
[4]: https://www.sitepoint.com/enqueuing-scripts-styles-wordpress/?utm_source=chatgpt.com "Enqueuing Scripts and Styles in WordPress - SitePoint"
[5]: https://kinsta.com/blog/wp-enqueue-scripts/?utm_source=chatgpt.com "wp_enqueue_scripts - How to Enqueue Your Assets in WordPress"
[6]: https://wordpress.org/support/topic/enqueue-scripts-and-styles-via-shortcode/?utm_source=chatgpt.com "Enqueue scripts and styles via shortcode - WordPress.org"
