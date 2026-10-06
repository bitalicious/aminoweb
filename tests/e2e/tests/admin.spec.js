// @ts-check
/**
 * Admin experience: a store administrator must be able to run the shop without
 * a developer — editing products and prices, publishing COAs, managing orders,
 * shipping and payment methods, all through stock WordPress/WooCommerce screens.
 *
 * Requires WP_ADMIN_USER and WP_ADMIN_PASSWORD (see .env).
 */
const { test, expect } = require('@playwright/test');
const { placeOrder } = require('./helpers');

const ADMIN_USER = process.env.WP_ADMIN_USER || 'admin';
const ADMIN_PASSWORD = process.env.WP_ADMIN_PASSWORD || '';

/**
 * Sign in to wp-admin.
 *
 * @param {import('@playwright/test').Page} page
 */
async function login(page) {
  await page.goto('/wp-login.php', { waitUntil: 'domcontentloaded' });
  await page.fill('#user_login', ADMIN_USER);
  await page.fill('#user_pass', ADMIN_PASSWORD);
  await Promise.all([
    page.waitForURL(/wp-admin/, { timeout: 30_000 }),
    page.click('#wp-submit'),
  ]);
}

test.describe('Store administration', () => {
  test.skip(!ADMIN_PASSWORD, 'WP_ADMIN_PASSWORD is not set; skipping admin checks.');

  test('an administrator can reach wp-admin and see the catalog', async ({ page }) => {
    await login(page);

    await expect(page.locator('#wpadminbar')).toBeVisible();

    await page.goto('/wp-admin/edit.php?post_type=product', { waitUntil: 'domcontentloaded' });

    // All 19 products are listed and editable by the store owner.
    const rows = page.locator('#the-list tr');
    await expect(rows).toHaveCount(19);
    await expect(page.locator('#the-list')).toContainText('BPC-157');
    await expect(page.locator('#the-list')).toContainText('GLP3-RT');
  });

  test('product editing exposes price, SKU, image and the COA tab', async ({ page }) => {
    await login(page);

    // Find the BPC-157 product via the list search and open its editor.
    await page.goto('/wp-admin/edit.php?s=BPC-157&post_type=product', { waitUntil: 'domcontentloaded' });
    await page.locator('#the-list tr .row-title').first().click();
    await expect(page.locator('form#post')).toBeVisible();

    // The normal WooCommerce editing surface is available.
    await expect(page.locator('#title')).toBeVisible();
    await expect(page.locator('#woocommerce-product-data')).toBeVisible();
    await expect(page.locator('input#_sku')).toBeVisible();

    // The theme adds a Certificate of Analysis tab with real, saved fields.
    const coaTab = page.locator('a[href="#amino_heaven_coa_product_data"]');
    await expect(coaTab).toHaveCount(1);
    await expect(coaTab).toContainText('Certificate of Analysis');

    await expect(page.locator('input#_amino_heaven_coa_url')).toHaveCount(1);
    await expect(page.locator('input#_amino_heaven_lot_number')).toHaveCount(1);

    // Variations carry the per-pack prices the storefront displays.
    await expect(page.locator('.variations_tab a, a[href="#variable_product_options"]').first()).toHaveCount(1);

    // No PHP notices leaked into the editor.
    const body = await page.locator('body').innerText();
    expect(body).not.toContain('Fatal error');
    expect(body).not.toContain('Warning:');
    expect(body).not.toContain('Notice:');
  });

  test('a product variation exposes editable regular and sale prices', async ({ page }) => {
    await login(page);

    await page.goto('/wp-admin/edit.php?s=KPV&post_type=product', { waitUntil: 'domcontentloaded' });
    await page.locator('#the-list tr .row-title').first().click();
    await expect(page.locator('form#post')).toBeVisible();

    await expect(page.locator('#variable_product_options')).toBeAttached();

    // Each of the three vial packs is an editable variation with its own regular
    // and sale price — this is exactly how the store owner changes tier pricing.
    const tab = page.locator('a[href="#variable_product_options"]').first();
    const regularInputs = page.locator('#variable_product_options input[name*="_regular_price"]');
    const saleInputs = page.locator('#variable_product_options input[name*="_sale_price"]');

    // WooCommerce renders the variation rows when its admin script binds the tab
    // handler, which can land after the page reports itself loaded. Clicking again
    // on each poll iteration makes this deterministic instead of timing-dependent.
    await expect.poll(
      async () => {
        if ((await regularInputs.count()) >= 3) {
          return 3;
        }

        await tab.click({ timeout: 5000 }).catch(() => {});
        await page.waitForTimeout(800);

        return regularInputs.count();
      },
      { timeout: 40_000, intervals: [1000] }
    ).toBeGreaterThanOrEqual(3);

    expect(await saleInputs.count()).toBeGreaterThanOrEqual(3);

    const body = await page.locator('body').innerText();
    expect(body).not.toContain('Fatal error');
  });

  test('payment methods and the shipping zone are configured and editable', async ({ page }) => {
    await login(page);

    // Payments: bank transfer and cash on delivery, both listed and editable.
    await page.goto('/wp-admin/admin.php?page=wc-settings&tab=checkout', { waitUntil: 'domcontentloaded' });

    // WooCommerce 11 renders Payments as a React "Payment providers" screen in
    // which the offline gateways sit inside a collapsible section. The
    // authoritative enabled/disabled state is verified by
    // tests/wp/verify-store.php; here we only prove the screen is reachable and
    // exposes both gateways to the store owner.
    const payments = page.locator('#wpbody-content');
    await expect(payments).toContainText(/offline payments/i);

    // The offline gateways live behind a collapsible "Take offline payments"
    // section; expanding it reveals the store owner's Manage links.
    await page.getByText('Take offline payments').first().click();
    await page.waitForTimeout(1500);

    await expect(payments).toContainText('Direct bank transfer');
    await expect(payments).toContainText('Cash on delivery');
    expect(await payments.locator('text=Manage').count()).toBeGreaterThanOrEqual(2);

    // Shipping: a United States zone with a flat rate.
    await page.goto('/wp-admin/admin.php?page=wc-settings&tab=shipping', { waitUntil: 'domcontentloaded' });

    await expect(page.locator('#wpbody-content')).toContainText('United States');
    await expect(page.locator('#wpbody-content')).toContainText(/flat rate/i);

    // General settings: currency and store address are editable.
    await page.goto('/wp-admin/admin.php?page=wc-settings&tab=general', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#woocommerce_currency')).toHaveValue('USD');
    await expect(page.locator('#woocommerce_store_address')).toHaveCount(1);
  });

  test('orders created at checkout are visible and manageable', async ({ page }) => {
    // Place an order first so this check never depends on another spec having
    // run (Playwright executes specs in alphabetical order).
    await placeOrder(page, {
      productPath: '/product/tb-500/',
      pack: '1 Vial',
      gatewayId: 'cod',
      email: `admin.check.${Date.now()}@example.com`,
    });

    await login(page);

    await page.goto('/wp-admin/edit.php?post_type=shop_order', { waitUntil: 'domcontentloaded' });

    const rows = page.locator('#the-list tr');
    expect(await rows.count()).toBeGreaterThan(0);

    // Open the first order (HPOS and legacy URLs both handled) and confirm the
    // gateway, shipping line and total are recorded on the order itself.
    const link = page
      .locator('#the-list tr a[href*="page=wc-orders"][href*="action=edit"], #the-list tr a[href*="post.php?post="]')
      .first();

    expect(await link.count()).toBeGreaterThan(0);

    const href = await link.getAttribute('href');
    await page.goto(href, { waitUntil: 'domcontentloaded' });

    const orderScreen = await page.locator('body').innerText();
    expect(orderScreen).toMatch(/Direct bank transfer|Cash on delivery/i);
    expect(orderScreen).toMatch(/Flat rate/i);
    expect(orderScreen).not.toContain('Fatal error');
  });
});
