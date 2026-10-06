# WordPress + WooCommerce Migration Plan — Amino Heaven

This document is the audit and architecture record for converting the Lovable /
TanStack React storefront in `amino-heaven-theme-main/` into a native
WordPress + WooCommerce theme.

It is written to be read **before** the code, and it is kept up to date as the
implementation record.

---

## 1. Existing architecture (audit)

### 1.1 Source repository

| Item | Finding |
| --- | --- |
| Framework | TanStack Start (React 19) + Vite 8, TypeScript |
| Package manager | Bun (`bun.lock`, `bunfig.toml`) |
| Styling | Tailwind CSS v4 via `@tailwindcss/vite`, design tokens in `oklch()` |
| UI kit | shadcn/ui + Radix primitives (`src/components/ui/*`) |
| Icons | `lucide-react` |
| Routing | TanStack Router, **one route only**: `/` (`src/routeTree.gen.ts`) |
| State | React local state only |
| Backend | None. No Supabase, no API calls, no auth, no database |
| Persistence | `sessionStorage` (age gate) and `localStorage` (unused gallery tool) |
| Cart / checkout | **Do not exist.** The "Add" and "View COA" buttons call `() => undefined` |
| Analytics / third party | None found |

The entire storefront is a single 275-line route: `src/routes/index.tsx`.

### 1.2 Pages and sections

There is exactly one URL. Its sections and anchor targets are:

| Section | Anchor | Notes |
| --- | --- | --- |
| Sale ribbon | — | Static text with a glow animation |
| Header | — | Brand, nav, cart icon, mobile toggle |
| Hero | `#top` | Eyebrow, h1, paragraph, two CTAs, vial image, helix backdrop |
| Trust strip | — | 3 claim tiles |
| Featured catalog | `#catalog` | 19 product cards |
| Quality band | `#quality` | Heading + one "Batch-level testing" panel |
| Footer | `#contact` | 4 columns, 3 compliance paragraphs, legal bar |

Notable defects in the source:

* the header links to `#about`, but **no `#about` element exists** (dead link);
* the footer's "Track Order" link points at `#top`;
* the "View COA" button is inert even though the site's whole pitch is
  independent Certificates of Analysis;
* `src/components/GalleryManager.tsx` is dead code (never imported) — a
  developer-only bulk image uploader that wrote to `localStorage`.

### 1.3 Reusable components

Header, promo ribbon, brand lockup, desktop nav, mobile nav, hero (with
`HelixBackdrop` SVG), trust strip, section heading, product card, dose selector,
pack tier grid, quantity stepper, COA button, quality band, footer columns,
newsletter form, compliance statement, legal bar, and the
`AgeVerificationModal`.

### 1.4 Product model in the source

```ts
type ProductVariant = { dose: string; price: number; image: string };
type Product        = { name: string; variants: ProductVariant[] };
```

19 products, 21 dose variants (`GLP2-TR` and `GLP3-RT` each have 10mg and 60mg).
Prices are declared per **dose**, and the displayed prices are derived:

```ts
const listPrice = (price) => Math.round(price * 1.3 * 100) / 100;
const tierPrice = (unit, n) =>
  Math.round(unit * n * (n === 2 ? 0.95 : n === 5 ? 0.85 : 1) * 100) / 100;
```

So each product exposes:

| Source field | Meaning |
| --- | --- |
| `name` | Compound name |
| `variants[].dose` | Strength, e.g. `10mg`, `5mg/5mg` |
| `variants[].price` | 1-vial selling price |
| `variants[].image` | Lovable CDN asset pointer |
| `listPrice(price)` | Struck-through "sitewide sale" price (1.3×) |
| `tierPrice(unit, 2/5)` | Bulk pack price (5% / 15% off) |

The card's pack selector chooses 1, 2 or 5 vials; a separate quantity stepper
multiplies it. The source had **no** SKU, stock, category, COA, description or
taxonomy data.

### 1.5 Assets

Product photography is stored as Lovable CDN **asset pointers**
(`*.asset.json`), not as committed binaries — only `GLP3-RT60mg.png` and
`amino-heaven-vial-branded.jpg` are real files in the repository. The binaries
were fetched from the published site by `scripts/fetch-source-assets.sh` into
`assets-source/` (21 product images + 4 brand images).

### 1.6 Live site vs repository

The published site at <https://amino-heaven-theme.lovable.app> was fetched and
compared: identical title, meta, copy and **identical prices** to the source in
the repository, and it references the same asset ids. The repository is current;
the live site is its deployed build.

Two findings worth recording:

1. `src/styles.css` declares `--font-sans: "Manrope"` and
   `--font-display: "Barlow Condensed"`, but **no `@font-face` and no webfont
   link exist** anywhere in the built CSS. The published site therefore renders
   in system fonts. The WordPress theme keeps the identical font stacks to
   preserve visual parity (see §12).
2. The age gate is client-side only (`sessionStorage`). It is trivially
   bypassable and invisible to the server.

---

## 2. WordPress architecture

**WordPress core + WooCommerce + one custom theme.** No site builder, no page
builder, no framework, no custom post types, no custom tables, no REST layer of
our own.

```
WordPress 7.1.2
├── Theme: amino-heaven (custom, classic, from scratch)
├── Plugin: WooCommerce 11.1.2 (the only plugin; commerce engine)
└── Content
    ├── Pages   → Home, Quality / COAs, About, Contact, Research Use & Terms, Track Order
    ├── Menus   → Primary, Footer quick links, Footer legal
    ├── Products→ 19 native variable products, 63 variations (WooCommerce)
    └── Media   → 21 imported product photographs + site icon
```

### 2.1 Theme layout

```
amino-heaven/
├── style.css                     Theme header only; real CSS is enqueued
├── functions.php                 Module loader
├── theme.json                    Design tokens for the block editor
├── screenshot.png                1200×900
├── header.php footer.php sidebar.php
├── index.php front-page.php page.php single.php archive.php search.php 404.php
├── woocommerce.php               WooCommerce catch-all (keeps WC's hook contract)
├── inc/
│   ├── setup.php                 Theme supports, menus, widget areas
│   ├── icons.php                 Inline SVG set (lucide shapes, no icon font)
│   ├── template-functions.php    Card data, price helpers, grid renderer, copy
│   ├── template-hooks.php        Skip link, nav fallbacks, admin-bar link
│   ├── enqueue.php               Assets + age-gate critical CSS
│   ├── seo.php                   meta description / OG / canonical (plugin-aware)
│   ├── customizer.php            Storefront options
│   ├── compliance.php            Age gate + research-use copy
│   ├── coa.php                   Certificate of Analysis product meta
│   ├── newsletter.php            Real, nonce-protected sign-up handling
│   ├── woocommerce.php           WooCommerce hooks and layout
│   ├── cli.php                   WP-CLI provisioning commands
│   ├── class-amino-heaven-product-importer.php
│   ├── class-amino-heaven-store-setup.php
│   ├── class-amino-heaven-content-setup.php
│   └── data/catalog.json         Migration seed (input only, not runtime data)
├── assets/{css,js,images}
├── template-parts/{header,home,product,content}
└── woocommerce/
    ├── content-product.php       Delegates to the shared card
    └── global/quantity-input.php Adds −/+ steppers, preserves all WC attributes
```

Only **two** WooCommerce templates are overridden. Archive layout, the single
product page, cart, checkout and account are customised through WooCommerce's own
hooks and CSS, so WooCommerce updates cannot silently break template copies.

---

## 3. WooCommerce architecture

### 3.1 Products

Every sellable item is a native `product` post of type **`variable`** with native
`product_variation` children.

* **Strengths** are a product attribute (`dose`).
  * one strength → not used for variations, only shown
  * two strengths → used for variations (`GLP2-TR`, `GLP3-RT`)
* **Vial packs** are a product attribute (`pack`) used for variations:
  `1 Vial`, `2 Vials`, `5 Vials`.
* **Selling price** → `_sale_price` on each variation.
* **Struck-through "sitewide sale" price** → `_regular_price` on each variation.

This is what makes the pricing native rather than cosmetic:

| Requirement | How it is met |
| --- | --- |
| Pack price is real | Each pack is a variation with its own `_sale_price` |
| Cart total is server-authoritative | WooCommerce recalculates from variation prices; no JS total is trusted |
| Quantity works | WooCommerce's own `quantity` parameter; `pack price × qty` |
| Admin can change prices | Normal WooCommerce product/variation editor |
| Sale display | Native `_regular_price` vs `_sale_price` (no hardcoded 1.3×) |

`wp amino-heaven recalculate-tiers` re-derives the 2/5-vial prices from the
1-vial price when an administrator changes a base price.

### 3.2 Why variations, not "quantity discounts"

The source presents the pack as an explicit, selectable purchase option
(1 VIAL / 2 VIALS / 5 VIALS) alongside a separate quantity stepper. It is a
purchasable option, not a cart-quantity rule, so variations are the correct
native model. It also keeps all pricing inside WooCommerce, with no plugin and no
custom cart hooks.

### 3.3 COA / quality

`wc_get_template` is not involved; the COA is product meta:

| Meta key | Purpose |
| --- | --- |
| `_amino_heaven_coa_url` | Direct link to the batch certificate |
| `_amino_heaven_coa_label` | Optional button label |
| `_amino_heaven_lot_number` | Optional lot reference |

Edited from a dedicated **Certificate of Analysis** tab in the product data panel
(registered with `woocommerce_product_data_tabs` / `_panels`), sanitised, and
registered with `register_post_meta()` so it is REST-visible.

When a product has no certificate yet, the button links to the Quality / COAs
page with an accessible explanation instead of rendering a dead control. No
certificate documents exist in the source, so none were invented.

### 3.4 Catalog card

The card reproduces the source design *and* is a real WooCommerce add-to-cart
form:

```html
<form class="ah-card-form cart" method="post" action="<product permalink>">
  <input type="hidden" name="add-to-cart" value="123">
  <fieldset class="pack-grid ah-pack-grid">
    <label><input type="radio" name="variation_id" value="456" checked> 1 Vial …</label>
    …
  </fieldset>
  <input type="number" name="quantity" value="1">
  <button type="submit">Add</button>
</form>
```

Because the radios are literally named `variation_id` and the action is the
product permalink (`woocommerce_add_to_cart_form_action`, the same value
WooCommerce's own single-product form uses), the POST is handled by
`WC_Form_Handler::add_to_cart_action()` with **no JavaScript at all**.
JavaScript only enhances: switching strength, swapping imagery, live totals.

---

## 4. Template mapping

| Source | WordPress | Notes |
| --- | --- | --- |
| `/` hero + trust + catalog + quality | `front-page.php` + `template-parts/home/*` | Static front page ("Home") |
| Product grid | `woocommerce/content-product.php` → `template-parts/product/card.php` | One card used by the homepage and the shop |
| Product detail (did not exist) | WooCommerce `woocommerce.php` + hooks | Native variable product form |
| Shop / catalog (did not exist) | Same card, `ul.products` | 12 per page, 3 columns |
| `#about` (broken link) | `page.php` + the "About" page | Fixed |
| `#quality` | Quality band (homepage) + "Quality / COAs" page | |
| `#contact` | Footer + "Contact" page | |
| Cart / checkout (did not exist) | Native WooCommerce cart + checkout | Styled, not replaced |
| Order tracking (dead link) | "Track Order" page with `[woocommerce_order_tracking]` | Fixed |
| My account (did not exist) | Native WooCommerce account | |
| COA (inert button) | Product meta + COA button | |
| Age gate | `inc/compliance.php` + `assets/js/theme.js` | Server- and client-aware |
| Newsletter form | `inc/newsletter.php` | Real POST with nonce |

---

## 5. Product-data mapping

| Source | WooCommerce |
| --- | --- |
| `name` | `post_title` (+ `dose` attribute for the strength) |
| — (derived) | `post_name` (slug), stable and used by the seed |
| — (generated) | `_sku` = `AH-<SLUG>` |
| `variants[].dose` | attribute `dose` — variation attribute when >1 strength |
| pack tiers 1/2/5 | attribute `pack` — always a variation attribute |
| `variants[].price` | variation `_sale_price` at pack 1 |
| `tierPrice(unit, n)` | variation `_sale_price` at pack n |
| `listPrice(x)` | variation `_regular_price` |
| `variants[].image` | Media library attachment; featured image and per-variation image |
| — (new) | Category: `research-peptides`, plus `peptide-blends` / `glp-research` |
| — (new) | Short + long description, research-use wording only |
| — (new) | `_amino_heaven_coa_url` (empty until a real certificate is uploaded) |
| `featured` (implied) | `_featured` = `yes` for all 19, so the homepage matches the source |
| source ordering | `menu_order`, preserving the catalog order |
| stock | `_stock_status = instock`, stock management off (source always shows "In Stock") |

**Tags**: none. The source exposes no tag data, and inventing tags would add
noise an administrator has to clean up.

**Categories** (3): `Research Peptides` (parent, 14 products) with children
`Peptide Blends` (2 products whose dose format shows two compounds:
`BPC-157/TB-500`, `CJC-1295 (No DAC) IPAMORELIN`) and `GLP Research`
(3 products sharing the `GLP` prefix). Category archives include children, so
`/product-category/research-peptides/` lists the whole catalog.

---

## 6. Required WooCommerce configuration

Applied by `wp amino-heaven setup-store` (idempotent).

| Setting | Value | Why |
| --- | --- | --- |
| Country | `US` | Requirement |
| Currency | `USD`, position left, 2 decimals, `.` / `,` | Requirement; matches `$49.95` |
| Locale | `en_US` | Requirement |
| Timezone | `America/Los_Angeles` | The source's shipping cut-off is "1 PM PST" |
| Selling / shipping locations | United States only | Requirement |
| Taxes | Calculation **off**, no rates | The source specifies no tax rates; none are invented |
| Payment: Direct bank transfer (BACS) | Enabled, placeholder account details | Requirement |
| Payment: Cash on delivery (COD) | Enabled | Requirement |
| Shipping | One "United States" zone with a native **Flat rate** method | Requirement |
| Flat rate cost | `SHIPPING_FLAT_RATE_COST`, default `9.95` — **documented placeholder** | The source publishes no shipping price |
| Guest checkout | Enabled | The source has no accounts |
| Reviews / ratings | Disabled | No reviews in the source |
| Store visibility | `woocommerce_coming_soon = no` | WooCommerce 9.1+ otherwise hides a fresh store behind "Coming soon" |
| Terms page | "Research Use & Terms" | Gives checkout a native required research acknowledgement |
| Permalinks | `/%postname%/` (+ `.htaccess` written via `save_mod_rewrite_rules()`) | Clean product URLs |

Deliberately **not** installed: no Stripe, PayPal, Apple Pay or wallet gateway;
no SEO plugin; no caching plugin; no page builder.

---

## 7. Required theme files

See §2.1. 37 PHP files, two CSS files, three JS files, one theme.json.

## 8. Required plugins

**WooCommerce only** (11.1.2, pinned). Everything else is WordPress core or the
theme. The theme integrates through `woocommerce_*` hooks and the WC CRUD API.

## 9. Custom functionality

1. **Age / research-use entry gate** — two required confirmations, session-scoped
   (`sessionStorage` + a session cookie), server-aware (PHP skips rendering the
   dialog once accepted), focus-trapped, Esc-safe, and fully operable with
   JavaScript disabled via a nonce-protected `<noscript>` form. Never shown to
   administrators, WP-CLI, REST/AJAX/cron/feed requests or recognised crawlers.
2. **Catalog card add-to-cart** — native WooCommerce POST, no custom cart.
3. **COA product meta + admin tab** — WordPress metadata, not a CPT.
4. **Newsletter sign-up** — real POST with nonce and honeypot. Forwards to a
   configurable endpoint server-side (so API keys never reach the browser) or
   emails the store address. The source's form was client-side only with no
   destination; no credentials are invented.
5. **Provisioning CLI** — `setup-store`, `setup-content`, `import-products`,
   `recalculate-tiers`, `catalog-status`, `all`.
6. **Minimal, plugin-aware SEO output** — description, Open Graph and canonical
   tags only when no SEO plugin is active.

## 10. Migration / import strategy

* **Input**: `inc/data/catalog.json`, generated from the source by
  `scripts/build-catalog-json.py` (provenance documented in the file).
* **Images**: `scripts/fetch-source-assets.sh` downloads the Lovable CDN binaries
  once into `assets-source/`; the importer sideloads them into the media library.
  Products therefore never depend on Lovable at runtime. If the local directory
  is absent the importer falls back to the recorded `imageUrl`.
* **Idempotency**: SKU is the stable key.
  * not found → create
  * found, no `--update` → **skip** (never overwritten)
  * found, with `--update` → refresh seeded fields, delete and recreate its
    variations
  * products whose SKU is not in the seed are never read or written
* **Media idempotency**: `_amino_heaven_source_image` meta prevents duplicates.
* **Dry run**: `--dry-run` reports without writing.

## 11. Testing strategy

| Layer | Tool | Command |
| --- | --- | --- |
| PHP syntax | `php -l` in the pinned container | `scripts/test.sh php` |
| Catalog correctness | PHP assertions via `wp eval-file` (691 assertions: type, category, attributes, every variation price, SKUs, stock, media) | `scripts/test.sh catalog` |
| Route smoke test | `curl` across 19 routes, asserting HTTP status and absence of PHP errors | `scripts/test.sh http` |
| Browser flows | Playwright (age gate incl. no-JS, homepage, shop, card tiers, product, cart, checkout ×2 gateways, content pages) | `scripts/test.sh e2e` |
| Responsive | Playwright at 320/375/390/430/768/1024/1280/1440/1920 across 8 routes, asserting no horizontal overflow | part of `e2e` |
| Order verification | WP-CLI inspection of created orders, totals, shipping lines and gateways | manual + `wp wc`/`wp post list` |

Everything runs against the real Docker stack. No mocks.

## 12. Known limitations and decisions

1. **Fonts.** The source declared Manrope / Barlow Condensed but never loaded
   them, so the published site renders in system fonts. The theme keeps the
   identical stacks for exact visual parity. Adding the webfonts later upgrades
   the design with no other change; self-hosting was rejected because it would
   make the WordPress site visibly *different* from the reference it must match.
2. **No COA documents.** The source contains none. The COA field is modelled,
   editable, and degrades to the COA hub page. Real certificates must be uploaded
   per product by the store administrator.
3. **Placeholder values.** Bank account details and the flat rate cost are
   explicitly marked placeholders, since the source publishes neither. They are
   surfaced in `.env.example`, in the CLI output and in the README.
4. **No tax rates.** The source specifies none, so tax calculation is off rather
   than inventing a rate.
5. **Product page variation controls stay native.** The single product page uses
   WooCommerce's `<select>` variation controls (styled), rather than converting
   them into buttons. The catalog card carries the button-based tier UI. Keeping
   the native control protects accessibility and forward compatibility.
6. **Product tags omitted.** No tag data exists in the source.
7. **Pagination.** The shop paginates at 12 per page (WooCommerce convention)
   while the homepage shows the whole catalog, matching the source's single-page
   presentation.
8. **No caching plugin.** Caching is not part of the requirements; the age gate
   uses a session cookie, so page caching must be configured to vary by cookie if
   it is added later.
9. **Email delivery.** The Docker stack has no MTA, so WordPress mail (order
   confirmations, newsletter notifications) is not delivered locally. Use an SMTP
   plugin or a catcher such as Mailpit when testing email.
10. **`wp rewrite structure --hard`** cannot write `.htaccess` from WP-CLI in this
    container; `scripts/setup.sh` calls WordPress's own
    `save_mod_rewrite_rules()` instead.
11. **WooCommerce template overrides** are pinned to the versions noted in each
    file's docblock (`content-product.php` → 9.4.0,
    `global/quantity-input.php` → 10.1.0). WooCommerce may report them as
    "outdated" after a major update; each is a thin, reviewed file.
