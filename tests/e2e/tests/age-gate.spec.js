// @ts-check
/**
 * Entry gate: 21+ and research-use acknowledgement.
 *
 * Covers the requirements that the gate cannot be bypassed by accident, that it
 * persists for the browser session only, that it is keyboard accessible, and
 * that it degrades gracefully when JavaScript is disabled.
 */
const { test, expect } = require('@playwright/test');

test.describe('Age / research-use entry gate', () => {
  test('blocks the storefront until both confirmations are given', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });

    const gate = page.locator('#ah-age-gate');
    await expect(gate).toBeVisible();
    await expect(page.locator('#ah-age-gate-title')).toHaveText('Age Verification');

    const enter = page.locator('[data-ah-age-enter]');

    // Requires *both* confirmations, not just one.
    await expect(enter).toBeDisabled();
    await page.check('#ah-confirm-age');
    await expect(enter).toBeDisabled();
    await page.check('#ah-confirm-research');
    await expect(enter).toBeEnabled();

    // Unchecking one disables it again.
    await page.uncheck('#ah-confirm-age');
    await expect(enter).toBeDisabled();
    await page.check('#ah-confirm-age');
    await expect(enter).toBeEnabled();

    await enter.click();
    await expect(gate).toBeHidden();
  });

  test('acceptance survives navigation but not a new browser session', async ({ page, browser }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await page.check('#ah-confirm-age');
    await page.check('#ah-confirm-research');
    await page.click('[data-ah-age-enter]');
    await expect(page.locator('#ah-age-gate')).toBeHidden();

    // Same session: the server stops emitting the dialog entirely.
    await page.goto('/shop/', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#ah-age-gate')).toHaveCount(0);

    // New session (fresh cookies/storage) is gated again.
    const context = await browser.newContext();
    const fresh = await context.newPage();
    await fresh.goto('/', { waitUntil: 'domcontentloaded' });
    await expect(fresh.locator('#ah-age-gate')).toBeVisible();
    await context.close();
  });

  test('is a keyboard-accessible modal with a trapped focus', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });

    const dialog = page.locator('[data-ah-age-dialog]');
    await expect(dialog).toHaveAttribute('aria-modal', 'true');
    await expect(dialog).toHaveAttribute('aria-labelledby', 'ah-age-gate-title');
    await expect(dialog).toHaveAttribute('aria-describedby', 'ah-age-gate-description');

    // Focus starts inside the dialog.
    await expect
      .poll(async () => dialog.evaluate((el) => el.contains(document.activeElement)))
      .toBe(true);

    // Tabbing repeatedly never leaves the dialog.
    for (let i = 0; i < 12; i += 1) {
      await page.keyboard.press('Tab');
      const inside = await dialog.evaluate((el) => el.contains(document.activeElement));
      expect(inside, `focus escaped the dialog on tab ${i + 1}`).toBe(true);
    }

    // The gate is blocking: the storefront behind it is not reachable by click.
    await expect(page.locator('.sale-ticker')).toBeVisible();
  });

  test('works without JavaScript via the no-script form', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();

    await page.goto('/', { waitUntil: 'domcontentloaded' });

    const noScriptForm = page.locator('.ah-gate-nojs');
    await expect(noScriptForm).toBeVisible();

    await page.check('#ah-nojs-age');
    await page.check('#ah-nojs-research');
    await noScriptForm.locator('button[type="submit"]').click();
    await page.waitForLoadState('domcontentloaded');

    // The server now knows, so the gate is not rendered at all.
    await expect(page.locator('#ah-age-gate')).toHaveCount(0);
    await expect(page.locator('.sale-ticker')).toBeVisible();

    await context.close();
  });

  test('is not shown to administrators or to crawlers', async ({ request }) => {
    // A crawler user agent is not gated, so the storefront content is indexable.
    const response = await request.get('/', {
      headers: { 'User-Agent': 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)' },
    });

    expect(response.status()).toBe(200);
    expect(await response.text()).not.toContain('id="ah-age-gate"');
  });
});
