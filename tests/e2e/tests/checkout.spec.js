// @ts-check
/**
 * Flow 3 — Checkout, for both enabled offline gateways.
 *
 * Confirms that the cart total, the flat-rate shipping charge, the chosen
 * payment method and the final order total are all consistent, and that
 * WooCommerce creates a real order.
 */
const { test, expect } = require('@playwright/test');
const {
  gotoStorefront,
  emptyCart,
  addProductFromSinglePage,
  fillCheckoutBilling,
  selectShipping,
  money,
} = require('./helpers');

/**
 * Place an order with the given payment gateway.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} productPath
 * @param {string} pack
 * @param {string} gatewayId
 * @param {string} email
 * @returns {Promise<{orderNumber: string, subtotal: number, shipping: number, total: number}>}
 */
async function placeOrder(page, productPath, pack, gatewayId, email) {
  await emptyCart(page);
  await addProductFromSinglePage(page, productPath, { pack });

  await page.goto('/checkout/', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#order_review')).toBeVisible();
  await expect(page.locator('.ah-checkout-notice')).toContainText('Research use only');

  await fillCheckoutBilling(page, email);
  await page.waitForLoadState('networkidle');

  // Flat-rate shipping must be selected and priced.
  const shippingLabel = await selectShipping(page, /flat rate/i);
  expect(shippingLabel.toLowerCase()).toContain('flat rate');

  const shipping = money(await page.locator('.woocommerce-shipping-totals .amount').first().innerText());
  expect(shipping).toBeGreaterThan(0);

  // Choose the requested gateway.
  const gateway = page.locator(`#payment_method_${gatewayId}`);
  await expect(gateway).toBeVisible();
  await gateway.check();

  // Accept the research-use terms required at checkout.
  const terms = page.locator('#terms');
  if ((await terms.count()) > 0) {
    await terms.check();
  }

  const subtotal = money(await page.locator('.cart-subtotal .amount').last().innerText());
  const total = money(await page.locator('.order-total .amount').last().innerText());

  expect(total).toBeCloseTo(subtotal + shipping, 2);

  await Promise.all([
    page.waitForLoadState('domcontentloaded'),
    page.click('#place_order'),
  ]);

  await expect(page.locator('.woocommerce-order')).toBeVisible();
  await expect(page.locator('.woocommerce-thankyou-order-received')).toBeVisible();

  const orderNumber = (await page.locator('.woocommerce-order-overview__order strong').innerText()).trim();
  expect(orderNumber).toMatch(/^\d+$/);

  const confirmationTotal = money(await page.locator('.woocommerce-order-overview__total .amount').innerText());
  expect(confirmationTotal).toBeCloseTo(total, 2);

  return { orderNumber, subtotal, shipping, total };
}

test.describe('Checkout', () => {
  test('places an order with direct bank transfer and flat-rate shipping', async ({ page }) => {
    const result = await placeOrder(
      page,
      '/product/bpc-157/',
      '1 Vial',
      'bacs',
      `bacs.${Date.now()}@example.com`
    );

    // $49.95 + $9.95 flat rate.
    expect(result.subtotal).toBeCloseTo(49.95, 2);
    expect(result.shipping).toBeCloseTo(9.95, 2);
    expect(result.total).toBeCloseTo(59.9, 2);

    // The BACS instructions and (placeholder) bank details are shown.
    await expect(page.locator('.woocommerce-order')).toContainText('bank');
  });

  test('places an order with cash on delivery', async ({ page }) => {
    const result = await placeOrder(
      page,
      '/product/sermorelin/',
      '1 Vial',
      'cod',
      `cod.${Date.now()}@example.com`
    );

    expect(result.subtotal).toBeCloseTo(39.95, 2);
    expect(result.shipping).toBeCloseTo(9.95, 2);
    expect(result.total).toBeCloseTo(49.9, 2);

    await expect(page.locator('.woocommerce-order')).toContainText('Cash on delivery');
  });

  test('refuses checkout with an empty cart', async ({ page }) => {
    await emptyCart(page);

    await page.goto('/checkout/', { waitUntil: 'domcontentloaded' });

    // WooCommerce redirects to the cart and explains why.
    await expect(page).toHaveURL(/\/cart\/?$/);
    await expect(page.locator('.woocommerce-info')).toContainText('cart');
  });

  test('the order-tracking page accepts an order number and email', async ({ page }) => {
    await gotoStorefront(page, '/track-order/');

    await expect(page.locator('form.track_order')).toBeVisible();
    await expect(page.locator('#orderid')).toBeVisible();
    await expect(page.locator('#order_email')).toBeVisible();
  });
});
