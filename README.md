# Amino Heaven — WordPress + WooCommerce storefront

A production-ready **custom WordPress theme + WooCommerce store** converted from
the original Lovable / TanStack React site in
[`amino-heaven-theme-main/`](amino-heaven-theme-main/).

* WordPress is the CMS.
* **WooCommerce is the commerce engine** — native products, variations, cart,
  checkout, orders, customers, pricing and inventory.
* `amino-heaven` is a **standalone custom theme** (not a child theme of anything).
* The original React application is kept only as the migration source of truth;
  nothing at runtime depends on it or on Lovable's CDN.

| | |
| --- | --- |
| Theme slug | `amino-heaven` |
| Storefront | <http://localhost:8080> |
| WP admin | <http://localhost:8080/wp-admin> |
| Architecture record | [WORDPRESS-MIGRATION-PLAN.md](WORDPRESS-MIGRATION-PLAN.md) |
| Pinned versions | WordPress 7.1.2, WooCommerce 11.1.2, PHP 8.3, MariaDB 11.8, WP-CLI 2.12.0, Playwright 1.49.1 |

---

## Contents

1. [Requirements](#1-requirements)
2. [Quick start](#2-quick-start)
3. [Manual setup](#3-manual-setup)
4. [Store configuration](#4-store-configuration)
5. [Product migration](#5-product-migration)
6. [Administering the store](#6-administering-the-store)
7. [Testing](#7-testing)
8. [Development workflow](#8-development-workflow)
9. [Architecture decisions](#9-architecture-decisions)
10. [Repository layout](#10-repository-layout)
11. [Production deployment](#11-production-deployment)
12. [Troubleshooting](#12-troubleshooting)

---

## 1. Requirements

* Docker with Compose v2 (Docker Desktop 4.34+ for the host-networked test runner)
* `curl`, `python3` (only for regenerating the catalog seed and fetching assets)
* Roughly 3 GB of disk for images, volumes and the Playwright browser image

Everything else runs in containers. You do **not** need PHP, Node, WP-CLI or
MySQL installed locally.

---

## 2. Quick start

```bash
cp .env.example .env          # then set DB_PASSWORD and DB_ROOT_PASSWORD
scripts/setup.sh              # start the stack, install WP + WooCommerce, seed the catalog
```

`scripts/setup.sh` is idempotent. When it finishes:

* storefront → <http://localhost:8080>
* admin → <http://localhost:8080/wp-admin> (credentials from `.env`)

Then verify everything:

```bash
scripts/test.sh               # php lint + catalog + http + browser flows
```

To start over from scratch:

```bash
docker compose down -v && rm -rf wordpress/wp-content/uploads wordpress/wp-config.php
scripts/setup.sh
```

---

## 3. Manual setup

`scripts/setup.sh` performs these steps; each can be run on its own.

```bash
# 1. Database
docker compose up -d db

# 2. WordPress core + wp-config.php (bootstrapped by the official image)
docker compose up -d wordpress

# 3. WordPress itself
scripts/wp.sh core install \
  --url="http://localhost:8080" --title="Amino Heaven" \
  --admin_user=admin --admin_password='…' --admin_email=admin@example.com --skip-email

# 4. Theme
scripts/wp.sh theme activate amino-heaven

# 5. WooCommerce (the only plugin)
scripts/wp.sh plugin install woocommerce --activate

# 6. Clean permalinks
scripts/wp.sh rewrite structure '/%postname%/'
scripts/wp.sh eval 'require_once ABSPATH . "wp-admin/includes/misc.php";
  require_once ABSPATH . "wp-admin/includes/file.php"; save_mod_rewrite_rules();'
```

### WP-CLI

`scripts/wp.sh` is a thin wrapper around the WP-CLI sidecar container:

```bash
scripts/wp.sh plugin list
scripts/wp.sh post list --post_type=product --fields=ID,post_title
scripts/wp.sh wc --help
```

### Environment variables

`.env` is git-ignored; `.env.example` documents every key.

| Key | Purpose |
| --- | --- |
| `WP_URL`, `WP_PORT` | Site URL and published port |
| `DB_*` | Database name, user, passwords, table prefix, host port |
| `WP_ADMIN_*` | Administrator created by `setup.sh` |
| `STORE_EMAIL` | Store and administration email |
| `BANK_*` | **Placeholder** BACS account details |
| `SHIPPING_FLAT_RATE_COST` | **Placeholder** flat-rate cost (default `9.95`) |

Never commit `.env`, database dumps or `wordpress/wp-config.php`.

---

## 4. Store configuration

```bash
scripts/wp.sh amino-heaven setup-store
```

Configures, idempotently:

| Area | Setting |
| --- | --- |
| Region | Country `US`; selling and shipping limited to the United States |
| Currency | `USD`, symbol on the left, 2 decimals, `.` decimal / `,` thousands |
| Locale | `en_US` |
| Timezone | `America/Los_Angeles` (the source's "1 PM PST" cut-off) |
| Tax | Calculation **off**, no rates invented |
| Payments | **Direct bank transfer (BACS)** and **Cash on delivery (COD)** |
| Shipping | A "United States" zone with a native **Flat rate** method |
| Checkout | Guest checkout, sign-up from checkout, no redirect-after-add |
| Visibility | `woocommerce_coming_soon = no` (WooCommerce 9.1+ default is "Coming soon") |

No Stripe, PayPal, Apple Pay or wallet gateway is installed or enabled.

### ⚠️ Placeholders you must replace before taking orders

The source project publishes **no** bank details and **no** shipping price, so
those values are deliberately obvious placeholders:

* **Bank account details** — WooCommerce → Settings → Payments → Direct bank
  transfer. Defaults come from `BANK_*` in `.env` and are prefixed `PLACEHOLDER`.
* **Flat rate cost** — WooCommerce → Settings → Shipping → United States.
  Default `9.95` from `SHIPPING_FLAT_RATE_COST`; `setup-store` prints a warning
  when the placeholder is used.
* **Store address** — WooCommerce → Settings → General. Set to `PLACEHOLDER`.

### Content and navigation

```bash
scripts/wp.sh amino-heaven setup-content            # create pages, menus, front page
scripts/wp.sh amino-heaven setup-content --update-content   # refresh page copy
```

Creates:

* **Pages** — Home (front page), Quality / COAs, About, Contact,
  Research Use & Terms, Track Order
* **Menus** — Primary (Shop, Quality / COAs, About, Contact), Footer quick links
  (Shop Catalog, Quality Control / COAs, Track Order, About Us), Footer legal
  (Research Use & Terms, Privacy Policy)
* the site icon, from the theme's bundled favicon
* a one-time cleanup of WordPress's pre-assigned starter widgets

Navigation, footer links and editorial copy are all editable in wp-admin.

---

## 5. Product migration

```bash
scripts/wp.sh amino-heaven import-products --dry-run   # preview
scripts/wp.sh amino-heaven import-products             # create missing products
scripts/wp.sh amino-heaven import-products --update    # also refresh existing ones
scripts/wp.sh amino-heaven catalog-status              # report
```

### What the importer creates

19 **native WooCommerce variable products** with **63 variations**:

* one variation per strength × pack size (`1 Vial`, `2 Vials`, `5 Vials`)
* `_sale_price` = the selling price, `_regular_price` = the struck-through list
  price, so the source's "30% off sitewide" is native WooCommerce sale pricing
* SKUs (`AH-BPC-157`, `AH-BPC-157-10MG-1V`, …) used as the stable identity
* 21 product photographs sideloaded into the media library
* 3 product categories

### Idempotency and safety

SKU is the stable identifier:

| Situation | Behaviour |
| --- | --- |
| SKU not found | Product is created |
| SKU found, no `--update` | **Skipped** — never modified |
| SKU found, with `--update` | Seeded fields refreshed; its variations deleted and recreated |
| Product whose SKU is not in the seed | Never read or written |

Media imports are deduplicated via the `_amino_heaven_source_image` attachment
meta, so re-running never duplicates images.

### Tier pricing

The source derives every price from the 1-vial price:

```
list(x)      = round(x × 1.3 × 100) / 100      # struck-through "sitewide sale"
tier(u, 1)   = u
tier(u, 2)   = round(u × 2 × 0.95 × 100) / 100  # 5% off
tier(u, 5)   = round(u × 5 × 0.85 × 100) / 100  # 15% off
```

Those values are written as **real variation prices**, so the cart and order
totals are always recalculated by WooCommerce. Change prices in the normal
product editor; then, if you changed a 1-vial price and want the bulk tiers
re-derived:

```bash
scripts/wp.sh amino-heaven recalculate-tiers --dry-run
scripts/wp.sh amino-heaven recalculate-tiers
```

### Regenerating the seed from source

```bash
scripts/fetch-source-assets.sh      # fetch the original imagery into assets-source/
python3 scripts/build-catalog-json.py
scripts/wp.sh amino-heaven import-products --update
```

### Certificates of Analysis

Each product has a **Certificate of Analysis** tab in the product editor:

| Field | Meta |
| --- | --- |
| COA URL | `_amino_heaven_coa_url` |
| COA button label | `_amino_heaven_coa_label` |
| Lot / batch number | `_amino_heaven_lot_number` |

No certificates exist in the source project, so none were invented. Until a
certificate URL is set, the product button links to the **Quality / COAs** page
with an accessible explanation instead of a dead control.

---

## 6. Administering the store

Everything is managed through stock WordPress and WooCommerce screens — no
developer required:

| Task | Where |
| --- | --- |
| Create / edit a product | Products → Add New / edit |
| Change prices, bulk tiers, sale prices | Product → Variation → *Sale price* / *Regular price* |
| Upload product images | Product → Product image / gallery |
| Publish a COA | Product → **Certificate of Analysis** tab |
| Manage stock | Product → Inventory |
| Categories | Products → Categories |
| Orders, refunds, notes | WooCommerce → Orders |
| Customers | WooCommerce → Customers |
| Shipping rate | WooCommerce → Settings → Shipping |
| Bank transfer / COD | WooCommerce → Settings → Payments |
| Currency, address, taxes | WooCommerce → Settings → General |
| Promo ribbon, footer copy, contact email, age gate, COA hub, newsletter | Appearance → Customize → **Amino Heaven storefront** |
| Navigation | Appearance → Menus (Primary / Footer quick links / Footer legal) |
| Editorial pages | Pages |
| Widget areas | Appearance → Widgets (*Shop sidebar*, *Footer extra*) |

### Theme options (Customizer)

Promotional ribbon text · footer tagline · footer support line · contact email ·
scam warning · newsletter blurb · homepage catalog size · COA hub URL ·
newsletter endpoint · 21+ gate on/off · footer newsletter on/off.

---

## 7. Testing

```bash
scripts/test.sh              # everything
scripts/test.sh php          # PHP syntax check (37 files)
scripts/test.sh catalog      # 691 assertions over products, variations, prices, media
scripts/test.sh http         # 19-route HTTP smoke test, asserts no PHP errors
scripts/test.sh e2e          # Playwright browser flows
scripts/test.sh e2e --grep checkout
```

### What the suites cover

* **PHP lint** — every theme PHP file through the pinned PHP 8.3 container.
* **Catalog verification** (`tests/wp/verify-catalog.php`) — for all 19 products:
  type, published status, featured flag, image, descriptions, category
  assignment, attribute options and variation flags, and for all 63 variations
  the regular/sale/active price, stock, SKU and image. Also verifies every media
  import and category hierarchy.
* **HTTP smoke test** — homepage, shop (both pages), products, category
  archives, all content pages, cart, account, search, sitemap, 404, plus the
  empty-cart checkout redirect.
* **Playwright browser flows**
  * age gate: both confirmations required, session scope, new session re-gated,
    keyboard focus trap, ARIA wiring, **works with JavaScript disabled**, hidden
    from crawlers
  * homepage: ribbon, brand, 4 nav links, hero, trust strip, 19 catalog cards,
    quality band, 4 footer columns, 3 compliance paragraphs
  * catalog card: 1/2/5 tiers with the exact source prices and Save 5% / Save 15%
    badges, live totals, quantity multiplication, dose switching with image swap,
    and a **server-side** cart total after adding a 5-vial pack
  * shop: result count, ordering by price, pagination, category archives
  * product: native variation form, COA control, research-use notice, tabs,
    related products, correct line totals after add
  * cart: line totals, quantity, removal, empty state
  * checkout: full order placement with **both** bank transfer and cash on
    delivery, asserting flat-rate shipping, subtotal + shipping = total, order
    number and confirmation total
  * content pages: Quality/COAs, About, Contact, Research Use & Terms, search,
    404, footer, newsletter validation
  * responsive: 9 breakpoints × 8 routes asserting no horizontal overflow, grid
    columns per breakpoint, mobile nav behaviour, and the gate at 320 px
* **Order verification** — after a browser run, inspect the created orders:

```bash
scripts/wp.sh wc shop_order list --user=admin --fields=id,status,total,payment_method_title
scripts/wp.sh post list --post_type=shop_order --post_status=any --fields=ID,post_status
```

### Screenshots for visual review

```bash
docker compose run --rm --entrypoint node e2e tools/capture.js
# → tests/e2e/screenshots/
```

---

## 8. Development workflow

```
edit theme files  →  hard-refresh the browser (assets are cache-busted by mtime)
edit PHP          →  no build step; there is no compilation
change CSS/JS     →  no bundler; plain CSS and ES5-compatible JS
```

* The theme is bind-mounted at `wordpress/wp-content/themes/amino-heaven`, so
  host edits are live immediately.
* `amino_heaven_asset_version()` appends the file mtime, so a changed stylesheet
  or script is always re-fetched.
* WordPress debug logging is on (`WP_DEBUG_LOG`), writing to
  `wordpress/wp-content/debug.log` with display off:

```bash
tail -f wordpress/wp-content/debug.log
```

* Regenerate the seed data after changing prices in the source:

```bash
python3 scripts/build-catalog-json.py
```

---

## 9. Architecture decisions

1. **Products are native WooCommerce variations.** The 1/2/5-vial packs are
   genuine purchase options, so each is a variation with its own price — not
   custom HTML, not a custom table, not JavaScript state.
2. **No custom post type, no custom commerce system.** There is no
   `amino_product` CPT, no parallel cart, no bespoke checkout.
3. **Two WooCommerce template overrides only.** The catalog card and the quantity
   input. Archive, single product, cart, checkout and account are customised with
   WooCommerce hooks and CSS, so core updates are safe.
4. **No JavaScript framework.** Plain PHP templates, CSS and vanilla JS. The React
   runtime was deliberately not ported.
5. **Progressive enhancement.** Cards add to cart with JavaScript disabled; the
   age gate has a no-script form.
6. **Server-authoritative pricing.** JavaScript only mirrors prices for display;
   WooCommerce recalculates every total.
7. **Fonts match the reference exactly.** The source declared Manrope / Barlow
   Condensed but never loaded them, so it renders in system fonts. The theme
   keeps the identical stacks for pixel parity.
8. **WooCommerce is the only plugin.**

See [WORDPRESS-MIGRATION-PLAN.md](WORDPRESS-MIGRATION-PLAN.md) for the full audit,
data mapping, and the list of deliberate limitations.

---

## 10. Repository layout

```
.
├── docker-compose.yml           db · wordpress · cli · e2e
├── .env.example                 every configuration key, documented
├── scripts/
│   ├── setup.sh                 one-shot provisioning
│   ├── wp.sh                    WP-CLI wrapper
│   ├── test.sh                  test runner
│   ├── fetch-source-assets.sh   download the original imagery
│   └── build-catalog-json.py    regenerate the migration seed
├── assets-source/               original imagery (committed) + manifest
├── docker/php/uploads.ini
├── tests/
│   ├── wp/verify-catalog.php    WooCommerce catalog assertions
│   └── e2e/                     Playwright specs, config and tools
├── wordpress/                   runtime (core/plugins/uploads are git-ignored)
│   └── wp-content/themes/amino-heaven/   ← the deliverable theme
└── amino-heaven-theme-main/     original React source (reference only)
```

The only committed part of `wordpress/` is the theme.

---

## 11. Production deployment

1. **Build the theme for a real install.**
   ```bash
   cd wordpress/wp-content/themes && zip -r amino-heaven.zip amino-heaven
   ```
   Or copy the directory to `wp-content/themes/` on the target server.
2. **Create a fresh database and WordPress install** on the target host.
3. **Install WooCommerce** (Settings → Plugins).
4. **Activate the theme** (Appearance → Themes).
5. **Configure the store** — either run the CLI commands against production:
   ```bash
   wp amino-heaven setup-store
   wp amino-heaven setup-content
   wp amino-heaven import-products
   ```
   or configure WooCommerce manually and import products through
   Products → Import (the seed's SKUs keep re-imports idempotent).
6. **Replace every placeholder**: bank details, flat-rate cost, store address,
   store email.
7. **Upload real Certificates of Analysis** per product.
8. **Harden the install (not done by this repository):**
   * set `WP_DEBUG`, `WP_DEBUG_LOG` and `WP_DEBUG_DISPLAY` to `false`
     (`WP_DEBUG_LOG` currently writes to `wp-content/debug.log` in development)
   * move `wp-config.php` above the web root, or protect it
   * set real salts, correct file ownership, and `DISALLOW_FILE_EDIT`
   * force HTTPS and set `WP_HOME` / `WP_SITEURL` to the canonical `https://` URL
     (the theme has no hardcoded URLs)
   * add SMTP so order emails are delivered (the dev stack has no MTA)
   * if you add page caching, vary the cache by cookie — the age gate uses a
     session cookie
9. **Smoke test** with `scripts/test.sh http` pointed at the production URL
   (`WP_URL=https://… scripts/test.sh http`).

---

## 12. Troubleshooting

| Symptom | Cause and fix |
| --- | --- |
| `set DB_PASSWORD in .env` | `.env` missing a required value — `cp .env.example .env` and fill it in |
| `service "wordpress" is not running` | `docker compose up -d`, or run `scripts/setup.sh` |
| Pretty permalinks 404 | `.htaccess` missing. `scripts/setup.sh` writes it via `save_mod_rewrite_rules()`; or re-save Settings → Permalinks in wp-admin |
| Storefront shows "Coming soon" | `woocommerce_coming_soon` is `yes`. Run `scripts/wp.sh amino-heaven setup-store` |
| Products missing | `scripts/wp.sh amino-heaven catalog-status` then `… import-products` |
| Images missing after import | Confirm `assets-source/product-images/` exists, or let the importer download from the recorded `imageUrl` |
| Playwright tests all fail on sub-resources | The `e2e` service needs `network_mode: host` so the browser can resolve `WP_URL`; the site's asset URLs are absolute |
| `'setup-store' is not a registered subcommand` | The theme is not active, or an older copy is installed — re-activate `amino-heaven` |
| Age gate reappears every page | A plugin or browser is dropping `sessionStorage` and session cookies. The gate is intentionally session-scoped |
| WooCommerce reports an outdated template | Expected after a WooCommerce major update; each override documents the version it was written against |

---

## Attribution and licensing

* Theme code: GPL v2 or later (see `style.css`).
* Icons: [lucide](https://lucide.dev) shapes (ISC), inlined as SVG.
* Product photography: supplied with the original Amino Heaven project, migrated
  into the WordPress media library.
* Compliance and research-use wording is carried over unchanged from the source
  storefront. No medical, therapeutic or consumer-health claims were added.
