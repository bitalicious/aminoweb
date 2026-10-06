// @ts-check
/**
 * Flow 3 — Checkout, for both enabled offline gateways.
 *
 * Confirms that the cart total, the flat-rate shipping charge, the chosen
 * payment method and the final order total are all consistent, and that
 * WooCommerce creates a real order.
 */
const { test, expect } = require('@playwright/test');
const { gotoStorefront, emptyCart, placeOrder } = require('./helpers');

test.describe('Checkout', () => {
  test('places an order with direct bank transfer and flat-rate shipping', async ({ page }) => {
    const result = await placeOrder(page, {
      productPath: '/product/bpc-157/',
      pack: '1 Vial',
      gatewayId: 'bacs',
    });

    // $49.95 + $9.95 flat rate.
    expect(result.subtotal).toBeCloseTo(49.95, 2);
    expect(result.shipping).toBeCloseTo(9.95, 2);
    expect(result.total).toBeCloseTo(59.9, 2);

    // The BACS instructions and (placeholder) bank details are shown.
    await expect(page.locator('.woocommerce-order')).toContainText('bank');
  });

  test('places an order with cash on delivery', async ({ page }) => {
    const result = await placeOrder(page, {
      productPath: '/product/sermorelin/',
      pack: '1 Vial',
      gatewayId: 'cod',
    });

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
