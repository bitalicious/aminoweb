// @ts-check
/**
 * Flow 1 — Homepage.
 *
 * Verifies the gate, the hero, navigation, the trust strip, the featured catalog
 * and the footer against the source storefront's composition.
 */
const { test, expect } = require('@playwright/test');
const { gotoStorefront, money } = require('./helpers');

test.describe('Homepage', () => {
  test('shows the gate, then the full storefront composition', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#ah-age-gate')).toBeVisible();

    await page.check('#ah-confirm-age');
    await page.check('#ah-confirm-research');
    await page.click('[data-ah-age-enter]');
    await expect(page.locator('#ah-age-gate')).toBeHidden();

    // Announcement ribbon.
    await expect(page.locator('.sale-ticker div')).toHaveText('Fall Sale 30% Off Sitewide - No Code Needed');

    // Brand + navigation.
    await expect(page.locator('.brand b')).toContainText('Amino Heaven');
    const navLinks = page.locator('.desktop-nav .ah-menu a');
    await expect(navLinks).toHaveCount(4);
    await expect(navLinks.nth(0)).toHaveText('Shop');
    await expect(navLinks.nth(1)).toHaveText('Quality / COAs');
    await expect(navLinks.nth(2)).toHaveText('About');
    await expect(navLinks.nth(3)).toHaveText('Contact');

    // Hero.
    await expect(page.locator('.hero-band h1')).toHaveText('Research compounds you can actually verify');
    await expect(page.locator('.hero-copy .eyebrow')).toContainText('Independent U.S. Lab Verification');
    await expect(page.locator('.hero-actions a').first()).toContainText('Browse the catalog');
    await expect(page.locator('.hero-product img')).toBeVisible();

    // Trust strip.
    await expect(page.locator('.trust-strip > div')).toHaveCount(3);
    await expect(page.locator('.trust-strip')).toContainText('99% Purity');
    await expect(page.locator('.trust-strip')).toContainText('Third-Party COAs');
    await expect(page.locator('.trust-strip')).toContainText('Ships same day');

    // Featured catalog: the source storefront lists all 19 compounds.
    await expect(page.locator('#catalog .section-heading h2')).toHaveText('Featured compounds');
    const cards = page.locator('#catalog ul.products > li.ah-card');
    await expect(cards).toHaveCount(19);

    // Quality band.
    await expect(page.locator('.quality-band h2')).toHaveText('Quality you can verify');
    await expect(page.locator('.quality-band')).toContainText('Batch-level testing');

    // Footer: four columns, three compliance paragraphs, legal bar.
    await expect(page.locator('.footer-grid .footer-brand')).toBeVisible();
    await expect(page.locator('.footer-grid .footer-column')).toHaveCount(3);
    await expect(page.locator('.compliance-statement p')).toHaveCount(3);
    await expect(page.locator('.legal')).toContainText('RESEARCH USE ONLY');
    await expect(page.locator('.ah-newsletter')).toBeVisible();
  });

  test('lists the catalog in the source storefront order', async ({ page }) => {
    await gotoStorefront(page, '/');

    // Order is part of the design: the source storefront renders the catalog in
    // exactly this sequence, which the importer preserves as the products'
    // menu_order.
    const expected = [
      'BPC-157/TB-500',
      'BPC-157',
      'CJC-1295 (No DAC) IPAMORELIN',
      'CJC-1295 (No DAC)',
      'GLP1-SM',
      'GLP2-TR',
      'GLP3-RT',
      'GHK-CU',
      'GLOW',
      'IPAMORELIN',
      'KLOW',
      'KPV',
      'MELANOTAN II',
      'MOTS-c',
      'NAD+',
      'PT-141',
      'SERMORELIN',
      'TB-500',
      'Tesamorelin',
    ];

    const names = await page.locator('#catalog ul.products > li.ah-card [data-ah-card-name]').allInnerTexts();

    expect(names.map((name) => name.trim())).toEqual(expected);
  });

  test('renders the source catalog titles, strengths and prices', async ({ page }) => {
    await gotoStorefront(page, '/');

    // The first card reproduces the source's "NAME STRENGTH" title.
    const firstCard = page.locator('#catalog ul.products > li.ah-card').first();
    await expect(firstCard.locator('.ah-card-title')).toContainText('BPC-157/TB-500');
    await expect(firstCard.locator('.ah-card-title')).toContainText('5mg/5mg');
    await expect(firstCard.locator('[data-ah-price]')).toContainText('49.95');
    await expect(firstCard.locator('[data-ah-regular]')).toContainText('64.94');

    // Every card carries a struck-through list price and a live sale price.
    const cardCount = await page.locator('#catalog ul.products > li.ah-card').count();

    for (let index = 0; index < cardCount; index += 1) {
      const card = page.locator('#catalog ul.products > li.ah-card').nth(index);
      const sale = money(await card.locator('[data-ah-price]').innerText());
      const list = money(await card.locator('[data-ah-regular]').innerText());

      expect(sale, `card ${index} sale price`).toBeGreaterThan(0);
      expect(list, `card ${index} list price`).toBeGreaterThan(sale);
    }
  });

  test('mobile navigation toggles and is keyboard operable', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await gotoStorefront(page, '/');

    const toggle = page.locator('[data-ah-menu-toggle]');
    const nav = page.locator('[data-ah-mobile-nav]');

    await expect(toggle).toBeVisible();
    await expect(nav).toBeHidden();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');

    await toggle.click();
    await expect(nav).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(nav.locator('a')).toHaveCount(4);

    // Escape closes it and returns focus to the toggle.
    await page.keyboard.press('Escape');
    await expect(nav).toBeHidden();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');

    // Following a link closes the panel and navigates.
    await toggle.click();
    await nav.locator('a', { hasText: 'Shop' }).click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page).toHaveURL(/\/shop\/?$/);
  });

  test('anchor navigation reaches the catalog and quality sections', async ({ page }) => {
    await gotoStorefront(page, '/');

    await page.click('.hero-actions a:has-text("Browse the catalog")');
    await expect(page.locator('#catalog')).toBeInViewport();

    await page.click('.hero-actions a:has-text("View COAs")');
    await page.waitForLoadState('domcontentloaded');
    await expect(page).toHaveURL(/quality-coas/);
  });
});
