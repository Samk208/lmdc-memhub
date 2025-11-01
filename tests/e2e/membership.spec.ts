import { test, expect } from '@playwright/test';

const STRIPE_SCRIPT_URL = 'https://js.stripe.com/v3/';
const CHECKOUT_ENDPOINT_PATTERN = '**/admin-ajax.php';

test.describe('LNMC Membership checkout', () => {
  test.beforeEach(async ({ page }) => {
    await page.route(STRIPE_SCRIPT_URL, async (route) => {
      await route.fulfill({
        contentType: 'application/javascript',
        body: `window.__PLAYWRIGHT_STRIPE_REDIRECTS = [];
window.Stripe = function () {
  return {
    redirectToCheckout: async function (options) {
      window.__PLAYWRIGHT_STRIPE_REDIRECTS.push(options.sessionId);
      return { error: undefined };
    }
  };
};`,
      });
    });
  });

  test('membership page enqueues Stripe assets', async ({ page }) => {
    await page.goto('/membership/');

    await expect(page.locator('body')).toBeVisible();
    await expect(page.locator(`script[src="${STRIPE_SCRIPT_URL}"]`)).toHaveCount(1);
    await expect(page.locator('script[src*="stripe-checkout.js"]')).toHaveCount(1);

    await expect(page.locator('.lnmc-membership-form-inner')).toBeVisible();
    await expect(page.locator('.lnmc-submit-button, .lnmc-stripe-checkout-button')).toBeVisible();
  });

  test('submitting the membership form requests a Stripe Checkout session', async ({ page }) => {
    page.on('console', (msg) => {
      // eslint-disable-next-line no-console
      console.log(`[browser:${msg.type()}] ${msg.text()}`);
    });

    const stubbedSessionId = 'cs_test_stubbed_session';

    await page.route(CHECKOUT_ENDPOINT_PATTERN, async (route, request) => {
      const postData = request.postDataJSON() as Record<string, unknown> | undefined;
      if (postData?.action !== 'create_stripe_checkout_session') {
        return route.fallback();
      }
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            session_id: stubbedSessionId,
          },
        }),
      });
    });

    await page.goto('/membership/');

    const emailField = page.locator('#lnmc-email');
    await expect(emailField).toBeVisible();
    await emailField.fill(`playwright+${Date.now()}@example.com`);

    await page.evaluate(() => {
      const form = document.querySelector('form.lnmc-membership-form');
      if (form) {
        form.addEventListener('submit', () => {
          console.log('[Playwright] form submit event fired');
        });
      }
    });

    const submitButton = page.locator('form.lnmc-membership-form button[type="submit"], .lnmc-submit-button').first();
    await expect(submitButton).toBeEnabled();

    const requestPromise = page.waitForRequest(CHECKOUT_ENDPOINT_PATTERN, (request) => {
      const postBody = request.postDataJSON() as Record<string, unknown> | undefined;
      return postBody?.action === 'create_stripe_checkout_session';
    });
    await page.evaluate(() => {
      const form = document.querySelector('form.lnmc-membership-form');
      if (form) {
        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit();
        } else {
          const event = new Event('submit', { bubbles: true, cancelable: true });
          form.dispatchEvent(event);
        }
      }
    });
    const ajaxRequest = await requestPromise;

    expect(ajaxRequest.method()).toBe('POST');
    const postData = ajaxRequest.postDataJSON() as Record<string, unknown>;
    expect(postData).toMatchObject({ action: 'create_stripe_checkout_session' });

    await expect(async () => {
      const lastSession = await page.evaluate(() => (window as any).__PLAYWRIGHT_STRIPE_REDIRECTS?.at(-1));
      expect(lastSession).toBe(stubbedSessionId);
    }).toPass();
  });
});

