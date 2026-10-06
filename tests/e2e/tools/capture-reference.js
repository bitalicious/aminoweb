// @ts-check
/**
 * Capture reference screenshots of the original published Lovable storefront.
 *
 * Used for visual QA against the WordPress implementation. Requires outbound
 * network access from the Playwright container.
 *
 *     docker compose run --rm --entrypoint node \
 *       -e REFERENCE_URL=https://amino-heaven-theme.lovable.app \
 *       e2e tools/capture-reference.js
 *
 * Outputs to tests/e2e/screenshots/reference/.
 */
const fs = require('fs');
const path = require('path');
const { chromium } = require('@playwright/test');

const REFERENCE_URL = process.env.REFERENCE_URL || 'https://amino-heaven-theme.lovable.app';
const OUT_DIR = path.join(__dirname, '..', 'screenshots', 'reference');

const SHOTS = [
  { name: 'reference-home-desktop', viewport: { width: 1440, height: 1000 }, fullPage: false },
  { name: 'reference-home-full', viewport: { width: 1440, height: 1000 }, fullPage: true },
  { name: 'reference-home-mobile', viewport: { width: 390, height: 844 }, fullPage: true },
  { name: 'reference-home-320', viewport: { width: 320, height: 720 }, fullPage: true },
];

/**
 * Dismiss the source storefront's React age gate.
 *
 * @param {import('playwright').Page} page
 */
async function acceptReferenceGate(page) {
  const enter = page.getByRole('button', { name: /i agree and enter/i });

  if ((await enter.count()) === 0) {
    return;
  }

  const boxes = page.locator('.age-confirmations input[type="checkbox"]');
  const count = await boxes.count();

  for (let index = 0; index < count; index += 1) {
    await boxes.nth(index).check();
  }

  await enter.click();
  await page.waitForTimeout(600);
}

async function main() {
  fs.mkdirSync(OUT_DIR, { recursive: true });

  const browser = await chromium.launch();

  for (const shot of SHOTS) {
    const context = await browser.newContext({ viewport: shot.viewport, ignoreHTTPSErrors: true });
    const page = await context.newPage();

    await page.goto(REFERENCE_URL, { waitUntil: 'domcontentloaded', timeout: 60_000 });
    await acceptReferenceGate(page);
    await page.waitForLoadState('networkidle').catch(() => {});
    await page.waitForTimeout(1200);

    const file = path.join(OUT_DIR, `${shot.name}.png`);
    await page.screenshot({ path: file, fullPage: shot.fullPage });
    console.log(`wrote ${file}`);

    // The first catalog card, for card-by-card comparison.
    if (shot.name === 'reference-home-desktop') {
      const card = page.locator('.product-card').first();

      if ((await card.count()) > 0) {
        await card.screenshot({ path: path.join(OUT_DIR, 'reference-card-first.png') });
        console.log('wrote reference-card-first.png');
      }

      const multi = page.locator('.product-card').filter({ hasText: 'GLP3-RT' }).first();

      if ((await multi.count()) > 0) {
        await multi.screenshot({ path: path.join(OUT_DIR, 'reference-card-multi-dose.png') });
        console.log('wrote reference-card-multi-dose.png');
      }
    }

    await context.close();
  }

  await browser.close();
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
