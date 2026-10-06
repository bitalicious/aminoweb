=== Amino Heaven ===

Contributors: aminoheaven
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: e-commerce, custom-logo, custom-menu, featured-images, translation-ready, accessibility-ready

A custom WordPress + WooCommerce theme for Amino Heaven, a research-compound
storefront selling analytical-grade laboratory reference materials with
independent Certificates of Analysis.

== Description ==

Amino Heaven is a standalone classic theme (not a child theme of anything)
converted from a Lovable / TanStack React storefront. It keeps the original
visual design, responsive behaviour, copy and compliance positioning while
moving all commerce onto WooCommerce.

Features:

* Native WooCommerce products and variations. The catalog's 1/2/5-vial packs are
  real variations with real prices, so cart and order totals are always
  recalculated server-side.
* Catalog cards that are real add-to-cart forms: the pack selector posts
  `variation_id`, so purchasing works with JavaScript disabled.
* A 21+ / research-use entry gate with two required confirmations, session
  scoping, a focus trap, and a `<noscript>` fallback form.
* Certificate of Analysis product meta with a dedicated product-data tab, plus a
  Quality / COAs page. Missing certificates degrade to the COA hub rather than a
  dead control.
* Design system ported from the source: metallic-blue `oklch()` tokens, the
  animated DNA/protein helix backdrop, the promotional ribbon, trust strip,
  quality band and compliance footer.
* Only two WooCommerce template overrides (the catalog card and the quantity
  input); everything else uses WooCommerce hooks and CSS.
* No JavaScript framework, no build step, no icon font.
* Real, nonce-protected newsletter form with a honeypot, forwarding server-side.
* Minimal, plugin-aware SEO output (description, Open Graph, canonical) that
  stands down when an SEO plugin is active.

== Installation ==

1. Upload the `amino-heaven` folder to `/wp-content/themes/`, or install the ZIP
   through Appearance → Themes → Add New → Upload Theme.
2. Activate the theme.
3. Install and activate WooCommerce.
4. Either run the bundled provisioning commands:

   `wp amino-heaven setup-store`
   `wp amino-heaven setup-content`
   `wp amino-heaven import-products`

   or configure WooCommerce manually and create products in the normal way.

== Frequently Asked Questions ==

= Does this theme require any plugins? =

Only WooCommerce.

= How do I change a price? =

In the normal WooCommerce product editor. Each pack size is a variation with its
own regular and sale price. If you change the 1-vial price, run
`wp amino-heaven recalculate-tiers` to re-derive the 2- and 5-vial prices.

= How do I publish a Certificate of Analysis? =

Edit a product and open the "Certificate of Analysis" tab. Paste the COA URL and,
optionally, the lot number.

= Does the entry gate block search engines? =

No. The gate is skipped for recognised crawlers, administrators, WP-CLI, and
REST/AJAX/cron/feed requests.

== Credits ==

* Icon shapes from [lucide](https://lucide.dev) (ISC licence), inlined as SVG.
* Product photography supplied with the original Amino Heaven project.

== Changelog ==

= 1.0.0 =
* Initial release: full conversion from the Lovable React storefront.
