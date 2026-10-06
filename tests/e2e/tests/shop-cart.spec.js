// @ts-check
/**
 * Flow 2 — Shop, product page and cart.
 *
 * Verifies the shop archive, the catalog card's 1/2/5-vial tier pricing, the
 * native WooCommerce single-product form, and that server-side cart totals match
 * the selected variation.
 */
const { test, expect } = require('@playwright/test');
const {
  gotoStorefront,
  emptyCart,
  cartCount,
  addProductFromSinglePage,
  cardFor,
  money,
} = require('./helpers');

test.describe('Shop archive', () => {
  test('lists products with result count, ordering and pagination', async ({ page }) => {
    await gotoStorefront(page, '/shop/');

    await expect(page.locator('.ah-section-heading h1')).toHaveText('Featured compounds');
    await expect(page.locator('.woocommerce-result-count')).toContainText('19');

    const cards = page.locator('ul.products > li.ah-card');
    await expect(cards).toHaveCount(12);

    // Pagination reaches the remaining 7 products.
    await expect(page.locator('.woocommerce-pagination')).toBeVisible();
    await page.click('.woocommerce-pagination a.next');

    await expect(cards).toHaveCount(7);
  });

  test('orders products by price', async ({ page }) => {
    await gotoStorefront(page, '/shop/');

    await Promise.all([
      page.waitForURL(/orderby=price/),
      page.selectOption('.woocommerce-ordering select', 'price'),
    ]);
    await page.waitForLoadState('domcontentloaded');

    const prices = await page.locator('ul.products > li.ah-card [data-ah-price]').allInnerTexts();
    const values = prices.map(money);

    expect(values.length).toBeGreaterThan(1);

    for (let index = 1; index < values.length; index += 1) {
      expect(values[index], `price order at ${index}`).toBeGreaterThanOrEqual(values[index - 1]);
    }
  });

  test('product category archives contain only their products', async ({ page }) => {
    await gotoStorefront(page, '/product-category/glp-research/');

    const cards = page.locator('ul.products > li.ah-card');
    await expect(cards).toHaveCount(3);

    const titles = await cards.locator('.ah-card-title').allInnerTexts();
    expect(titles.join(' ')).toContain('GLP1-SM');
    expect(titles.join(' ')).toContain('GLP2-TR');
    expect(titles.join(' ')).toContain('GLP3-RT');
  });
});

test.describe('Catalog card tiers', () => {
  test('offers 1/2/5-vial packs with the source pricing and savings badges', async ({ page }) => {
    await gotoStorefront(page, '/');

    // BPC-157 10mg: single strength, unit price $49.95.
    const card = cardFor(page, /BPC-157\s+10mg/);
    await expect(card.locator('.ah-card-title')).toContainText('BPC-157');
    await expect(card.locator('.ah-card-title')).toContainText('10mg');

    const options = card.locator('.ah-pack-option');
    await expect(options).toHaveCount(3);

    // 1 vial.
    await expect(options.nth(0)).toContainText('1 Vial');
    await expect(options.nth(0)).toContainText('49.95');
    await expect(options.nth(0)).toContainText('64.94');

    // 2 vials: 5% bulk discount on $99.90.
    await expect(options.nth(1)).toContainText('2 Vials');
    await expect(options.nth(1)).toContainText('94.91');
    await expect(options.nth(1)).toContainText('123.38');
    await expect(options.nth(1)).toContainText('Save 5%');

    // 5 vials: 15% bulk discount on $249.75.
    await expect(options.nth(2)).toContainText('5 Vials');
    await expect(options.nth(2)).toContainText('212.29');
    await expect(options.nth(2)).toContainText('275.98');
    await expect(options.nth(2)).toContainText('Save 15%');

    // The bulk summary line reflects the largest pack.
    await expect(card.locator('[data-ah-bulk-price]')).toContainText('212.29');

    // Selecting a pack updates the displayed total immediately.
    await options.nth(2).click();
    await expect(card.locator('[data-ah-total-value]')).toContainText('212.29');

    // Quantity multiplies the selected pack price.
    await card.locator('[data-ah-qty="increase"]').click();
    await expect(card.locator('[data-ah-total-value]')).toContainText('424.58');
  });

  test('a multi-strength card switches dose, image and price together', async ({ page }) => {
    await gotoStorefront(page, '/');

    const card = cardFor(page, /GLP3-RT/);
    await expect(card.locator('.ah-dose-selector button')).toHaveCount(2);

    // 10mg is selected first.
    await expect(card.locator('[data-ah-price]')).toContainText('69.95');

    const imageBefore = await card.locator('[data-ah-card-image]').getAttribute('src');

    await card.locator('[data-ah-dose-option="60mg"]').click();

    await expect(card.locator('[data-ah-price]')).toContainText('249.95');
    await expect(card.locator('.ah-card-title')).toContainText('60mg');
    // Prices above 999 carry WooCommerce's thousands separator.
    expect(money(await card.locator('[data-ah-bulk-price]').innerText())).toBeCloseTo(1062.29, 2);

    const imageAfter = await card.locator('[data-ah-card-image]').getAttribute('src');
    expect(imageAfter).not.toEqual(imageBefore);
  });

  test('adding a 5-vial pack from the card produces the correct server-side cart total', async ({ page }) => {
    await emptyCart(page);
    await gotoStorefront(page, '/');

    const card = cardFor(page, /BPC-157\s+10mg/);
    await card.locator('.ah-pack-option').nth(2).click();
    await expect(card.locator('[data-ah-total-value]')).toContainText('212.29');

    await Promise.all([
      page.waitForLoadState('domcontentloaded'),
      card.locator('[data-ah-add]').click(),
    ]);

    // WooCommerce recalculated the cart on the server.
    await expect(page.locator('.woocommerce-message')).toContainText('cart');

    await page.goto('/cart/', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('form.woocommerce-cart-form tr.cart_item')).toHaveCount(1);

    const subtotal = money(await page.locator('.cart-subtotal .amount').last().innerText());
    expect(subtotal).toBeCloseTo(212.29, 2);

    // The cart total is the subtotal plus the flat-rate shipping charge.
    const shipping = money(await page.locator('.woocommerce-shipping-totals .amount').first().innerText());
    const total = money(await page.locator('.order-total .amount').last().innerText());
    expect(total).toBeCloseTo(subtotal + shipping, 2);
  });
});

test.describe('Single product page', () => {
  test('shows the product, its variations, COA control and research-use notice', async ({ page }) => {
    await gotoStorefront(page, '/product/glp3-rt/');

    await expect(page.locator('.product_title')).toHaveText('GLP3-RT');
    await expect(page.locator('form.variations_form')).toBeVisible();
    await expect(page.locator('.single_add_to_cart_button')).toBeVisible();
    await expect(page.locator('.ah-single-coa')).toBeVisible();
    await expect(page.locator('.ah-research-notice')).toContainText('research use only');
    await expect(page.locator('.woocommerce-tabs')).toBeVisible();
    await expect(page.locator('.related.products')).toBeVisible();

    // The two strengths are offered as real WooCommerce variation attributes.
    const selects = page.locator('form.variations_form select');
    await expect(selects).toHaveCount(2);
    await expect(selects.nth(0).locator('option')).toHaveCount(3); // "Choose an option" + 10mg + 60mg
    await expect(selects.nth(1).locator('option')).toHaveCount(4); // + 1/2/5 vials
  });

  test('adds a chosen variation and quantity to the cart at the right price', async ({ page }) => {
    await emptyCart(page);

    await addProductFromSinglePage(page, '/product/glp2-tr/', {
      dose: '60mg',
      pack: '2 Vials',
      quantity: 2,
    });

    expect(await cartCount(page)).toBe(2);

    await page.goto('/cart/', { waitUntil: 'domcontentloaded' });

    const subtotal = money(await page.locator('.cart_item .product-subtotal').first().innerText());
    // 2 x the 2-vial 60mg pack = 2 x 379.91.
    expect(subtotal).toBeCloseTo(759.82, 2);
  });

  test('removing an item empties the cart', async ({ page }) => {
    await emptyCart(page);
    await addProductFromSinglePage(page, '/product/kpv/', { pack: '1 Vial' });

    await page.goto('/cart/', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('.cart_item')).toHaveCount(1);

    await Promise.all([
      page.waitForLoadState('domcontentloaded'),
      page.locator('a.remove').first().click(),
    ]);

    await expect(page.locator('.cart_item')).toHaveCount(0);
    await expect(page.locator('.cart-empty')).toBeVisible();
  });
});
