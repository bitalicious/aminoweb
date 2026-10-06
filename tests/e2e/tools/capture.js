// @ts-check
/**
 * Screenshot capture for visual QA and for the theme's screenshot.png.
 *
 * Run inside the Playwright container:
 *
 *     docker compose run --rm --entrypoint node e2e tools/capture.js
 *
 * Outputs to tests/e2e/screenshots/ (git-ignored, except the theme screenshot).
 */
const fs = require('fs');
const path = require('path');
const { chromium } = require('@playwright/test');

const BASE_URL = process.env.BASE_URL || 'http://localhost:8080';
const OUT_DIR = path.join(__dirname, '..', 'screenshots');

const DESKTOP = { width: 1440, height: 1000 };
const MOBILE = { width: 390, height: 844 };

/**
 * Accept the entry gate when present.
 *
 * @param {import('playwright').Page} page
 */
async function acceptGate(page) {
  const gate = page.locator('#ah-age-gate');

  if ((await gate.count()) === 0 || !(await gate.isVisible())) {
    return;
  }

  await page.check('#ah-confirm-age');
  await page.check('#ah-confirm-research');
  await page.click('[data-ah-age-enter]');
  await gate.waitFor({ state: 'hidden' });
}

async function main() {
  fs.mkdirSync(OUT_DIR, { recursive: true });

  const browser = await chromium.launch();

  const shots = [
    { name: 'home-desktop', url: '/', viewport: DESKTOP, fullPage: true },
    { name: 'home-above-fold', url: '/', viewport: DESKTOP, fullPage: false },
    { name: 'home-mobile', url: '/', viewport: MOBILE, fullPage: true },
    { name: 'shop-desktop', url: '/shop/', viewport: DESKTOP, fullPage: true },
    { name: 'product-desktop', url: '/product/glp3-rt/', viewport: DESKTOP, fullPage: true },
    { name: 'product-mobile', url: '/product/glp3-rt/', viewport: MOBILE, fullPage: true },
    { name: 'quality-desktop', url: '/quality-coas/', viewport: DESKTOP, fullPage: true },
    { name: 'cart-desktop', url: '/cart/', viewport: DESKTOP, fullPage: true },
  ];

  for (const shot of shots) {
    const context = await browser.newContext({ viewport: shot.viewport, baseURL: BASE_URL });
    const page = await context.newPage();

    if (shot.name === 'cart-desktop') {
      // Give the cart something to render.
      await page.goto('/product/kpv/', { waitUntil: 'domcontentloaded' });
      await acceptGate(page);
      const pack = page.locator('form.variations_form select').last();
      await pack.selectOption({ label: '2 Vials' });
      await page.click('button.single_add_to_cart_button');
      await page.waitForLoadState('domcontentloaded');
    }

    await page.goto(shot.url, { waitUntil: 'domcontentloaded' });
    await acceptGate(page);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(400);

    const file = path.join(OUT_DIR, `${shot.name}.png`);
    await page.screenshot({ path: file, fullPage: shot.fullPage });
    console.log(`wrote ${file}`);

    await context.close();
  }

  // The first catalog card, for card-by-card comparison with the reference.
  {
    const cardContext = await browser.newContext({ viewport: DESKTOP, baseURL: BASE_URL });
    const cardPage = await cardContext.newPage();
    await cardPage.goto('/', { waitUntil: 'domcontentloaded' });
    await acceptGate(cardPage);
    await cardPage.waitForLoadState('networkidle');
    await cardPage.waitForTimeout(400);

    const card = cardPage.locator('#catalog ul.products > li.ah-card').first();
    await card.screenshot({ path: path.join(OUT_DIR, 'card-first.png') });
    console.log('wrote card-first.png');

    const gatedCard = cardPage.locator('#catalog ul.products > li.ah-card').filter({ hasText: 'GLP3-RT' }).first();
    await gatedCard.screenshot({ path: path.join(OUT_DIR, 'card-multi-dose.png') });
    console.log('wrote card-multi-dose.png');

    await cardContext.close();
  }

  // The age gate itself, for accessibility/visual review.
  const context = await browser.newContext({ viewport: DESKTOP, baseURL: BASE_URL });
  const page = await context.newPage();
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(300);
  await page.screenshot({ path: path.join(OUT_DIR, 'age-gate.png'), fullPage: false });
  console.log('wrote age-gate.png');
  await context.close();

  // WordPress requires a 1200x900 theme screenshot.
  const themeContext = await browser.newContext({ viewport: { width: 1200, height: 900 }, baseURL: BASE_URL });
  const themePage = await themeContext.newPage();
  await themePage.goto('/', { waitUntil: 'domcontentloaded' });
  await acceptGate(themePage);
  await themePage.waitForLoadState('networkidle');
  await themePage.waitForTimeout(500);
  await themePage.screenshot({ path: path.join(OUT_DIR, 'theme-screenshot.png'), fullPage: false });
  console.log('wrote theme-screenshot.png (1200x900)');
  await themeContext.close();

  await browser.close();
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
