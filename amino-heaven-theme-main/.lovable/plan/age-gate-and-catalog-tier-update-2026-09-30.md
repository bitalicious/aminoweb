# Age gate and catalog tier update

## Summary
Add a mandatory Amino Heaven age-verification entry modal and replace every catalog card’s vial tiers with the requested 1/2/5 structure.

## Changes
- Show a blocking modal on first entry with Amino Heaven branding, metallic blue and gray styling, and the storefront obscured behind it.
- Require both confirmations: the visitor is 21 or older, and they acknowledge products are for laboratory research use only.
- Keep “I Agree and Enter” disabled until both confirmations are checked; dismiss the gate for the current browser session after acceptance.
- Provide an Exit action that leaves the storefront without granting access.
- Replace the existing 1, 5, and 10 vial choices on every product card with 1 vial, 2 vials at 5% off, and 5 vials at 15% off.
- Update displayed tier prices, savings badges, bulk summary, selected totals, and quantity calculations from the same pricing logic.

## Technical details
- Implement the gate in the homepage UI with accessible dialog semantics, keyboard focus, scroll locking, and session-scoped acceptance.
- Use existing Button components and semantic metallic-blue design tokens.
- Verify desktop and mobile modal behavior, checkbox gating, entry dismissal, product tier labels, price calculations, totals, and current build status.
