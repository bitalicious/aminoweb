// @ts-check
/**
 * Editorial pages, search, 404 and order tracking.
 */
const { test, expect } = require('@playwright/test');
const { gotoStorefront } = require('./helpers');

test.describe('Content pages', () => {
  test('Quality / COAs page presents the testing and certificate information', async ({ page }) => {
    await gotoStorefront(page, '/quality-coas/');

    await expect(page.locator('h1')).toHaveCount(1);
    await expect(page.locator('.ah-entry-content h1')).toHaveText('Quality and Certificates of Analysis');
    const content = page.locator('.ah-entry-content');
    await expect(content).toContainText('independent laboratory');
    await expect(content).toContainText('mass spectrometry');
    await expect(content).toContainText('high-performance liquid chromatography');
    await expect(content.locator('.ah-notice')).toContainText('Certificates pending publication');
  });

  test('About page carries the source storefront positioning', async ({ page }) => {
    await gotoStorefront(page, '/about/');

    await expect(page.locator('h1')).toHaveCount(1);
    await expect(page.locator('.ah-entry-content h1')).toHaveText('Discover. Innovate. Elevate.');
    const content = page.locator('.ah-entry-content');
    await expect(content).toContainText('American-synthesized');
    await expect(content).toContainText('503A');
    await expect(content).toContainText('503B');
    await expect(content).toContainText('1 PM PST');
  });

  test('Contact page exposes the store email and the scam warning', async ({ page }) => {
    await gotoStorefront(page, '/contact/');

    await expect(page.locator('h1')).toHaveCount(1);
    await expect(page.locator('.ah-entry-content h1')).toHaveText('Research support');
    await expect(page.locator('.ah-entry-content')).toContainText('hello@aminoheaven.com');
    await expect(page.locator('.ah-entry-content')).toContainText('Scam warning');
  });

  test('Research Use and Terms page carries the compliance statement', async ({ page }) => {
    await gotoStorefront(page, '/research-use-terms/');

    const content = page.locator('.ah-entry-content');
    await expect(content).toContainText('21 years of age or older');
    await expect(content).toContainText('not for human consumption');
    await expect(content).toContainText('in vitro research');
    await expect(content).toContainText('Food and Drug Administration');
  });

  test('search finds products', async ({ page }) => {
    await gotoStorefront(page, '/?s=glp');

    await expect(page.locator('.ah-search-section h1')).toContainText('glp');

    const results = page.locator('.ah-post-card-title');
    await expect(results.first()).toBeVisible();

    const titles = (await results.allInnerTexts()).join(' ');
    expect(titles.toLowerCase()).toContain('glp');
  });

  test('an unknown URL returns a themed 404 with recovery links', async ({ page }) => {
    const response = await page.goto('/definitely-not-a-page/', { waitUntil: 'domcontentloaded' });
    expect(response.status()).toBe(404);

    await expect(page.locator('.ah-404 h1')).toHaveText('Page not found');
    await expect(page.locator('.ah-404-actions a')).toHaveCount(2);
  });

  test('footer compliance and legal content matches the source', async ({ page }) => {
    await gotoStorefront(page, '/');

    await expect(page.locator('.compliance-statement')).toContainText('in vitro research');
    await expect(page.locator('.compliance-statement')).toContainText('Section 503A');
    await expect(page.locator('.legal')).toContainText('Research Use & Terms');
    await expect(page.locator('.legal')).toContainText('NOT FOR HUMAN OR ANIMAL CONSUMPTION');

    // The newsletter form is a real, nonce-protected form.
    const form = page.locator('.ah-newsletter');
    await expect(form).toHaveAttribute('method', 'post');
    await expect(form.locator('input[name="ah_newsletter_nonce"]')).toHaveCount(1);
    await expect(form.locator('input[type="email"]')).toBeVisible();
  });

  test('newsletter rejects an invalid address and accepts a valid one', async ({ page }) => {
    await gotoStorefront(page, '/');

    const form = page.locator('.ah-newsletter');
    const email = form.locator('input[type="email"]');

    // Browsers block an invalid address client-side because the input is required.
    await email.fill('not-an-email');
    const validity = await email.evaluate((el) => el.checkValidity());
    expect(validity).toBe(false);

    await email.fill('researcher@example.com');
    await Promise.all([
      page.waitForLoadState('domcontentloaded'),
      form.locator('button[type="submit"]').click(),
    ]);

    await expect(page.locator('.ah-newsletter-status')).toBeVisible();
  });
});
