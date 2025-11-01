# LNMC Member Hub – Playwright E2E Tests

These end-to-end checks verify that the membership page loads Stripe assets and that submitting the form requests a Stripe Checkout session.

## Prerequisites

- Node.js 18+ (Playwright recommends the current LTS).
- The Local site running at `http://localhost:10010` (or export `E2E_BASE_URL`).
- Stripe test keys configured via `wp-config.php` (already handled).

## Install dependencies

```bash
cd wp-content/plugins/lnmc-member-hub
npm install
npx playwright install
```

## Run the suite

```bash
# Headless
npm run test:e2e

# Debug/headed mode
npm run test:e2e:headed

# Inspector
npm run test:e2e:debug
```

### Custom base URL

If the site runs on a different host/port:

```bash
E2E_BASE_URL=http://127.0.0.1:10011 npm run test:e2e
```

## How the tests behave

- The Stripe CDN script is stubbed so tests do not contact external services.
- The AJAX call to `admin-ajax.php?action=create_stripe_checkout_session` is intercepted and answered with a fake session response.
- The tests assert that the plugin enqueues both Stripe.js and `stripe-checkout.js` and that form submission triggers the checkout flow.

You can extend the suite by adding more specs under `tests/e2e/`.

