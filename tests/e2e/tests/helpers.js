// @ts-check
/**
 * Shared helpers for the Amino Heaven browser tests.
 */
const { expect } = require('@playwright/test');

/**
 * Money helpers. Prices are compared as numbers so formatting differences
 * (separators, symbol placement) do not cause false failures.
 */

/**
 * Parse a WooCommerce price string ("$49.95", "1,380.98") into a number.
 *
 * @param {string|null} text
 * @returns {number}
 */
function money(text) {
  if (!text) {
    return NaN;
  }
  const cleaned = String(text).replace(/[^0-9.,-]/g, '').replace(/,/g, '');
  return parseFloat(cleaned);
}

/**
 * Accept the 21+ / research-use entry gate if it is present.
 *
 * @param {import('@playwright/test').Page} page
 */
async function acceptAgeGate(page) {
  const gate = page.locator('#ah-age-gate');

  if ((await gate.count()) === 0) {
    return;
  }

  // The critical CSS hides the dialog for visitors who already accepted.
  if (!(await gate.isVisible())) {
    return;
  }

  await page.check('#ah-confirm-age');
  await page.check('#ah-confirm-research');
  await expect(page.locator('[data-ah-age-enter]')).toBeEnabled();
  await page.click('[data-ah-age-enter]');
  await expect(gate).toBeHidden();
}

/**
 * Navigate with the entry gate already dismissed.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} path
 */
async function gotoStorefront(page, path = '/') {
  await page.goto(path, { waitUntil: 'domcontentloaded' });
  await acceptAgeGate(page);
}

/**
 * Empty the WooCommerce cart so a test starts from a known state.
 *
 * @param {import('@playwright/test').Page} page
 */
async function emptyCart(page) {
  await page.goto('/cart/', { waitUntil: 'domcontentloaded' });
  await acceptAgeGate(page);

  // Remove items one at a time; the cart re-renders after each removal.
  for (let guard = 0; guard < 10; guard += 1) {
    const remove = page.locator('a.remove').first();

    if ((await remove.count()) === 0) {
      break;
    }

    await Promise.all([
      page.waitForLoadState('domcontentloaded'),
      remove.click(),
    ]);
  }
}

/**
 * Read the number of items in the header cart badge.
 *
 * @param {import('@playwright/test').Page} page
 * @returns {Promise<number>}
 */
async function cartCount(page) {
  const badge = page.locator('[data-ah-cart-count]');

  if ((await badge.count()) === 0) {
    return 0;
  }

  const text = (await badge.first().textContent()) || '';
  const value = parseInt(text.trim(), 10);

  return Number.isNaN(value) ? 0 : value;
}

/**
 * Add a product to the cart through the WooCommerce single product form.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} productPath
 * @param {{dose?: string, pack?: string, quantity?: number}} options
 */
async function addProductFromSinglePage(page, productPath, options = {}) {
  await gotoStorefront(page, productPath);

  const form = page.locator('form.variations_form');

  // Choose the strength when the product exposes one.
  if (options.dose) {
    const doseSelect = form.locator('select').first();

    if ((await doseSelect.count()) > 0) {
      await doseSelect.selectOption({ label: options.dose });
    }
  }

  // Choose the pack size. The pack attribute is the last select in the form.
  if (options.pack) {
    const selects = form.locator('select');
    const packSelect = selects.nth((await selects.count()) - 1);

    if ((await packSelect.count()) > 0) {
      const optionCount = await packSelect.locator('option').count();

      if (optionCount > 1) {
        await packSelect.selectOption({ label: options.pack });
      }
    }
  }

  if (options.quantity && options.quantity > 1) {
    await page.fill('form.cart input.qty', String(options.quantity));
  }

  const addButton = page.locator('button.single_add_to_cart_button');
  await expect(addButton).toBeEnabled();
  await addButton.click();
  await page.waitForLoadState('domcontentloaded');
}

/**
 * Fill the WooCommerce checkout billing fields with clearly fake test data.
 *
 * @param {import('@playwright/test').Page} page
 */
async function fillCheckoutBilling(page, email = 'research.test@example.com') {
  // WooCommerce's default field set varies by version (for example the company
  // field is not always present), so only fill what the checkout actually shows.
  const values = {
    '#billing_first_name': 'Test',
    '#billing_last_name': 'Researcher',
    '#billing_company': 'Example Laboratory',
    '#billing_address_1': '1 Laboratory Way',
    '#billing_address_2': 'Suite 100',
    '#billing_city': 'San Diego',
    '#billing_postcode': '92101',
    '#billing_phone': '5555550123',
    '#billing_email': email,
  };

  for (const [selector, value] of Object.entries(values)) {
    const field = page.locator(selector);

    if ((await field.count()) > 0) {
      await field.fill(value);
    }
  }

  // State/county is a select for the US, but may be a text input elsewhere.
  const state = page.locator('#billing_state');

  if ((await state.count()) > 0) {
    const tag = await state.evaluate((el) => el.tagName.toLowerCase());

    if (tag === 'select') {
      const options = await state.locator('option').all();

      for (const option of options) {
        const value = await option.getAttribute('value');

        if (value) {
          await state.selectOption(value);
          break;
        }
      }
    } else {
      await state.fill('CA');
    }
  }

  await page.waitForLoadState('networkidle');
}

/**
 * Locate a catalog card by its title, independent of catalog order.
 *
 * @param {import('@playwright/test').Page} page
 * @param {RegExp} titlePattern
 */
function cardFor(page, titlePattern) {
  return page
    .locator('ul.products > li.ah-card')
    .filter({ has: page.locator('.ah-card-title', { hasText: titlePattern }) })
    .first();
}

/**
 * Select a shipping method by its visible label, returning that label.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string|RegExp} label
 * @returns {Promise<string>}
 */
async function selectShipping(page, label) {
  // Wait for WooCommerce's order-review AJAX to render the shipping options.
  await page.locator('.woocommerce-shipping-totals').first().waitFor({ state: 'visible', timeout: 20_000 });

  const radios = page.locator('#shipping_method input[type="radio"]');
  const radioCount = await radios.count();

  if (radioCount > 0) {
    for (let index = 0; index < radioCount; index += 1) {
      const candidate = radios.nth(index);
      const id = await candidate.getAttribute('id');
      const text = await page.locator(`label[for="${id}"]`).innerText();

      if (typeof label === 'string' ? text.includes(label) : label.test(text)) {
        await candidate.check();
        return text.replace(/\s+/g, ' ').trim();
      }
    }

    throw new Error(`No shipping method matching ${label}`);
  }

  // When exactly one method is available WooCommerce auto-selects it and emits a
  // hidden input instead of a radio group, so there is nothing to click: the
  // method is already chosen and only needs to be verified.
  const hidden = page.locator('#shipping_method input.shipping_method[type="hidden"]');
  const hiddenCount = await hidden.count();

  if (hiddenCount > 0) {
    const value = await hidden.first().inputValue();
    const review = (await page.locator('.woocommerce-shipping-totals').first().innerText())
      .replace(/\s+/g, ' ')
      .trim();

    if (typeof label === 'string' ? review.includes(label) : label.test(review)) {
      return review;
    }

    throw new Error(`Single shipping method "${value}" does not match ${label}`);
  }

  throw new Error(`No shipping method matching ${label}`);
}


/**
 * Place a complete order through the storefront checkout.
 *
 * @param {import('@playwright/test').Page} page
 * @param {{productPath: string, pack: string, gatewayId: string, email?: string}} options
 * @returns {Promise<{orderNumber: string, subtotal: number, shipping: number, total: number}>}
 */
async function placeOrder(page, options) {
  const email = options.email || `${options.gatewayId}.${Date.now()}@example.com`;

  await emptyCart(page);
  await addProductFromSinglePage(page, options.productPath, { pack: options.pack });

  await page.goto('/checkout/', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#order_review')).toBeVisible();

  await fillCheckoutBilling(page, email);

  const shippingLabel = await selectShipping(page, /flat rate/i);
  const shipping = money(await page.locator('.woocommerce-shipping-totals .amount').first().innerText());

  const gateway = page.locator(`#payment_method_${options.gatewayId}`);
  await expect(gateway).toBeVisible();
  await gateway.check();

  const terms = page.locator('#terms');

  if ((await terms.count()) > 0) {
    await terms.check();
  }

  const subtotal = money(await page.locator('.cart-subtotal .amount').last().innerText());
  const total = money(await page.locator('.order-total .amount').last().innerText());

  await Promise.all([
    page.waitForLoadState('domcontentloaded'),
    page.click('#place_order'),
  ]);

  await expect(page.locator('.woocommerce-order')).toBeVisible();

  const orderNumber = (await page.locator('.woocommerce-order-overview__order strong').innerText()).trim();
  const confirmationTotal = money(await page.locator('.woocommerce-order-overview__total .amount').innerText());

  return {
    orderNumber,
    subtotal,
    shipping,
    shippingLabel,
    total,
    confirmationTotal,
  };
}

module.exports = {
  money,
  cardFor,
  acceptAgeGate,
  gotoStorefront,
  emptyCart,
  cartCount,
  addProductFromSinglePage,
  fillCheckoutBilling,
  selectShipping,
  placeOrder,
};
