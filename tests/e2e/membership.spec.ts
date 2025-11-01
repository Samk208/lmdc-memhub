import { test, expect } from '@playwright/test';

const STRIPE_SCRIPT_URL = 'https://js.stripe.com/v3/';
const CHECKOUT_ENDPOINT_PATTERN = '**/admin-ajax.php?action=create_stripe_checkout_session';

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
    const stubbedSessionId = 'cs_test_stubbed_session';

    await page.route(CHECKOUT_ENDPOINT_PATTERN, async (route) => {
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

    const submitButton = page.locator('.lnmc-membership-form-inner button[type="submit"], .lnmc-submit-button');
    await expect(submitButton).toBeEnabled();

    const requestPromise = page.waitForRequest(CHECKOUT_ENDPOINT_PATTERN);
    await submitButton.click();
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

