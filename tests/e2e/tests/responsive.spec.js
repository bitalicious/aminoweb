// @ts-check
/**
 * Responsive behaviour.
 *
 * For each required breakpoint, asserts that the storefront has no unintended
 * horizontal scrolling and that the key layouts collapse or expand as designed.
 *
 * The WooCommerce cart lives in the WC session (a cookie), so a snapshot of a
 * context with a populated cart is captured once and reused. Without this every
 * fresh Playwright context would see an empty cart.
 */
const { test, expect } = require('@playwright/test');
const { gotoStorefront, emptyCart, addProductFromSinglePage } = require('./helpers');

const BASE_URL = process.env.BASE_URL || 'http://wordpress';

const BREAKPOINTS = [
  { width: 320, height: 720 },
  { width: 375, height: 812 },
  { width: 390, height: 844 },
  { width: 430, height: 932 },
  { width: 768, height: 1024 },
  { width: 1024, height: 768 },
  { width: 1280, height: 900 },
  { width: 1440, height: 1000 },
  { width: 1920, height: 1080 },
];

const PATHS = [
  ['/', 'homepage'],
  ['/shop/', 'shop'],
  ['/product/glp3-rt/', 'product'],
  ['/cart/', 'cart'],
  ['/checkout/', 'checkout'],
  ['/about/', 'page'],
  ['/quality-coas/', 'quality'],
  ['/track-order/', 'track order'],
];

/** @type {object|null} */
let cartState = null;

/**
 * Create (once) a storage snapshot of a context that has an item in its cart.
 *
 * @param {import('@playwright/test').Browser} browser
 * @returns {Promise<object>}
 */
async function ensureCartState(browser) {
  if (cartState) {
    return cartState;
  }

  const context = await browser.newContext({ baseURL: BASE_URL });
  const page = await context.newPage();

  await emptyCart(page);
  await addProductFromSinglePage(page, '/product/kpv/', { pack: '2 Vials' });

  await page.goto('/cart/', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('.cart_item')).toHaveCount(1);

  cartState = await context.storageState();
  await context.close();

  return cartState;
}

/**
 * Report elements wider than the viewport, if any.
 *
 * @param {import('@playwright/test').Page} page
 * @returns {Promise<{scrollWidth: number, clientWidth: number, offenders: string[]}>}
 */
async function horizontalOverflow(page) {
  return page.evaluate(() => {
    const doc = document.documentElement;
    const limit = doc.clientWidth;
    const offenders = [];

    document.querySelectorAll('body *').forEach((element) => {
      const rect = element.getBoundingClientRect();

      if (rect.width === 0 || rect.height === 0) {
        return;
      }

      // Decorative, intentionally clipped layers.
      if (element.closest('.helix-field')) {
        return;
      }

      if (rect.right > limit + 1 || rect.left < -1) {
        const id = element.id ? '#' + element.id : '';
        const cls = element.className && typeof element.className === 'string'
          ? '.' + element.className.trim().split(/\s+/).slice(0, 3).join('.')
          : '';

        offenders.push(element.tagName.toLowerCase() + id + cls + ' right=' + Math.round(rect.right) + ' left=' + Math.round(rect.left));
      }
    });

    return {
      scrollWidth: doc.scrollWidth,
      clientWidth: limit,
      offenders: offenders.slice(0, 8),
    };
  });
}

/**
 * Open a page in a context that shares the populated cart.
 *
 * @param {import('@playwright/test').Browser} browser
 * @param {{width:number,height:number}} viewport
 */
async function openWithCart(browser, viewport) {
  const state = await ensureCartState(browser);
  const context = await browser.newContext({ baseURL: BASE_URL, viewport, storageState: state });
  const page = await context.newPage();

  return { context, page };
}

test.describe('Responsive layout', () => {
  for (const breakpoint of BREAKPOINTS) {
    for (const [path, name] of PATHS) {
      test('no horizontal overflow at ' + breakpoint.width + 'px on the ' + name, async ({ browser }) => {
        const { context, page } = await openWithCart(browser, breakpoint);

        await gotoStorefront(page, path);

        const { scrollWidth, clientWidth, offenders } = await horizontalOverflow(page);

        expect(
          scrollWidth,
          'document scrollWidth ' + scrollWidth + ' exceeds viewport ' + clientWidth + ' on ' + path + '\noffenders:\n' + offenders.join('\n')
        ).toBeLessThanOrEqual(clientWidth + 1);

        await context.close();
      });
    }
  }

  test('product grid columns follow the design breakpoints', async ({ browser }) => {
    const expectations = [
      { width: 390, columns: 1 },
      { width: 768, columns: 2 },
      { width: 1440, columns: 3 },
    ];

    // Both the shop archive and the front page must honour the grid: the front
    // page renders the loop without WooCommerce's `.woocommerce` wrapper.
    for (const path of ['/shop/', '/']) {
      for (const expectation of expectations) {
        const context = await browser.newContext({
          baseURL: BASE_URL,
          viewport: { width: expectation.width, height: 900 },
        });
        const page = await context.newPage();

        await gotoStorefront(page, path);

        const columns = await page.locator('ul.products').first().evaluate((element) => {
          return getComputedStyle(element).gridTemplateColumns.split(' ').length;
        });

        expect(columns, path + ' at ' + expectation.width + 'px').toBe(expectation.columns);

        await context.close();
      }
    }
  });

  test('desktop navigation is hidden on mobile and shown on desktop', async ({ browser }) => {
    const context = await browser.newContext({ baseURL: BASE_URL, viewport: { width: 390, height: 844 } });
    const page = await context.newPage();

    await gotoStorefront(page, '/');
    await expect(page.locator('.desktop-nav')).toBeHidden();
    await expect(page.locator('[data-ah-menu-toggle]')).toBeVisible();

    await page.setViewportSize({ width: 1024, height: 800 });
    await expect(page.locator('.desktop-nav')).toBeVisible();
    await expect(page.locator('[data-ah-menu-toggle]')).toBeHidden();

    await context.close();
  });

  test('the age gate fits and stays operable at 320px', async ({ browser }) => {
    const context = await browser.newContext({ baseURL: BASE_URL, viewport: { width: 320, height: 640 } });
    const page = await context.newPage();

    await page.goto('/', { waitUntil: 'domcontentloaded' });

    const gate = page.locator('#ah-age-gate');
    await expect(gate).toBeVisible();

    const box = await page.locator('[data-ah-age-dialog]').boundingBox();
    expect(box.width).toBeLessThanOrEqual(320);

    await page.check('#ah-confirm-age');
    await page.check('#ah-confirm-research');
    await page.click('[data-ah-age-enter]');
    await expect(gate).toBeHidden();

    await context.close();
  });
});
