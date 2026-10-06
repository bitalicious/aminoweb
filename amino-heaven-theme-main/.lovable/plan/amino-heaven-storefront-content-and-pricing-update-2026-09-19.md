# Amino Heaven storefront content and pricing update

## Summary
Update the homepage copy, sale presentation, product pricing, quality section, shipping message, contact details, compliance language, and Lovable badge while preserving the existing layout and controls.

## Changes
- Replace the promotional ribbon with the single static line “Fall Sale 30% Sitewide - No Code Needed.” Remove horizontal movement and add a continuous metallic-blue-compatible glow/pulse, with reduced-motion accessibility support.
- Replace every customer-facing instance of “kit” with “vial,” including metadata, product summaries, quantity labels, featured-product copy, and newsletter copy.
- Add a computed list price for every product at 130% of its current selling price. Show the list price struck through beside the current price, while keeping pack selection, quantity changes, savings labels, and totals functional.
- Replace the homepage company description with the supplied exact text.
- Remove the US Warehouse trust item and all remaining US Warehouse promotional references; keep the trust strip responsive with its remaining items.
- Remove the Cold-Chain Handling quality item and update the heading to “Quality you can verify.” Keep the remaining quality content balanced at all screen sizes.
- Change the shipping trust message to “Ships same day if ordered before 1 PM PST.”
- Add `hello@aminoheaven.com` as the site contact email and replace any existing contact email references if found.
- Add the supplied three-paragraph legal compliance statement beneath the footer’s existing columns and above the final copyright line.
- Hide the Lovable badge on published deployments using the project’s publishing setting.

## Technical details
- Update the homepage content and pricing display in the existing route without changing its navigation or interaction model.
- Update only the related CSS rules for the static glowing ribbon, crossed-out list prices, three-item trust layout, single quality item, and legal copy.
- Update homepage metadata so removed terminology no longer appears in search/social descriptions.
- Verify the finished page at desktop and mobile sizes, including the ribbon, every product price, quantity controls, removed sections, footer copy, and browser console.
