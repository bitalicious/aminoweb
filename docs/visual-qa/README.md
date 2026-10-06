# Visual QA — WordPress implementation vs the published Lovable storefront

Reference: <https://amino-heaven-theme.lovable.app>
Implementation: <http://localhost:8080>

Both sets of screenshots were captured with the **same tooling** (Playwright
Chromium, identical viewports, hidden scrollbars) so the comparison is
like-for-like rather than dependent on the machine's font rendering.

Regenerate the implementation side:

```bash
docker compose run --rm --entrypoint node e2e tools/capture.js
```

Regenerate the reference side (needs outbound network access):

```bash
docker compose run --rm --entrypoint node e2e tools/capture-reference.js
```

| File | What it shows |
| --- | --- |
| `01-reference-homepage-desktop.png` | Source homepage above the fold, 1440px |
| `02-wordpress-homepage-desktop.png` | WordPress homepage above the fold, 1440px |
| `03-reference-card.png` | Source catalog card (`BPC-157/TB-500 5mg/5mg`) |
| `04-wordpress-card.png` | WordPress catalog card, same product |
| `05-reference-card-multi-dose.png` | Source multi-strength card (`GLP3-RT`, 10mg selected) |
| `06-wordpress-card-multi-dose.png` | WordPress multi-strength card, same state |
| `07-age-gate.jpg` | WordPress 21+ / research-use entry gate |
| `08-wordpress-product-page.png` | WordPress single product page (no source equivalent) |
| `09-reference-homepage-320.jpg` | Source homepage at 320px, full page |
| `10-wordpress-homepage-mobile.jpg` | WordPress homepage at 390px, full page |

## Result

Above the fold and the product cards are effectively pixel-equivalent: the same
ribbon, brand lockup, navigation, hero composition, helix backdrop, typography
scale, colours, button treatment (44px, uppercase, 12px bold, `shadow-metal`),
price presentation, pack tiers with savings badges, quantity stepper and totals.

## Differences found during visual QA, and their resolution

| Finding | Resolution |
| --- | --- |
| Buttons rendered in sentence case and at the wrong size | Read the source's customised `Button` component (`src/components/ui/button.tsx`) and reproduced it exactly: `min-h-11`, `px-5`, `text-xs font-bold uppercase`, `rounded-sm`, primary/outline/ghost variants, `shadow-metal` on primary |
| A stray `0` cart badge appeared when the cart was empty | The hide rule keyed off an attribute that was output valueless; the count is now written into `data-ah-cart-count` and refreshed by the native cart-fragments filter |
| The homepage catalog rendered as a **single column** | The grid rules were scoped to a `.woocommerce` ancestor that the front page (which renders the same loop directly) does not have. The grid is now wrapper-independent, and the responsive suite asserts 1/2/3 columns on both `/` and `/shop/` |
| Every card's "View COA" button was dashed/outlined | The source renders a filled primary button. The button style now matches, and the "no batch certificate yet" state is carried by `is-pending` (retained as a child-theme hook), an accessible description, and the explicit note on the single product page plus the Quality / COAs page |
| A multi-strength card showed **both** strengths' pack grids at once | The `.ah-pack-option.is-dose-hidden` rule was missing. Added, with a `<noscript>` override so the no-JS experience still exposes every strength/pack combination |

## Deliberate, documented deviations

* **Fonts.** The source declares Manrope / Barlow Condensed but never loads them
  (no `@font-face` anywhere in its built CSS), so both sites render in system
  fonts. The identical stacks are kept so the two remain visually identical.
* **Catalog size per page.** The homepage shows the whole catalog exactly like the
  source; the shop archive paginates at 12, which the source has no equivalent of.
* **COA pending state.** The source's "View COA" button was inert. Here it is a
  working link: to the batch certificate when one is published, otherwise to the
  Quality / COAs page with an accessible explanation.
