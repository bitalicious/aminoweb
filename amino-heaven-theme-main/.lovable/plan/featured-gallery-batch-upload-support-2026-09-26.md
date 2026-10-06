# Featured gallery batch-upload support

## Summary
Add an image-management layer to the Featured compounds section so it supports up to 21 product images, populated incrementally through sequential uploads of up to 10 files at a time, with uniform thumbnails at every screen size.

## Changes
- Update the top ribbon text to “Fall Sale 30% Off Sitewide - No Code Needed” (adds the missing “Off”), keeping the existing static glow/pulse effect.
- Add a gallery manager to the Featured compounds section:
  - 21 fixed slots rendered in the existing responsive product grid (1 column on small screens, 2 at 640px, 3 at 1024px).
  - An “Add images” file picker accepting up to 10 images per batch; each batch fills the next available slots, so the user can upload repeatedly until all 21 slots are filled.
  - Per-slot remove button and a “Clear all” action; a counter shows “N / 21 images”.
  - Images persist in the browser (localStorage) so batches survive page reloads; object URLs are revoked on removal.
  - Empty slots show a dashed placeholder with the slot number so the grid keeps a uniform aspect ratio regardless of how many images are uploaded.
- Existing product cards, pricing, pack/quantity controls, and all other sections remain unchanged.

## Technical details
- New component file under src/components, imported by the index route; no backend or storage service involved.
- Batch input uses `multiple` with `accept="image/*"`; if more than 10 files are picked, only the first 10 are accepted and a notice is shown.
- Verify at desktop and mobile sizes: slot count, batch fill order, removal, persistence, and console cleanliness.
