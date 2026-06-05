# ID Card Generator (Admin)

![ID 3x3 A4 Print Preview](../images/idcard_print_preview.png)

## Admin Guide
- Select cadets via the search + checkbox UI, then preview.
- Prints 9 per page (3x3) on A4 in Portrait.
- Two page types:
  - IDs page(s)
  - QR-only page(s) (3x3)
- Use the Print settings:
  - Paper A4, Portrait
  - Scale 100% (or Fit to page width)
  - Margins None/Minimum
  - Background graphics On
  - Headers/Footers Off

## Developer Notes
- File: `admin/id_card.php`
- Pagination/batching: PHP slices selected cadets into groups of 9.
- Layout: CSS Grid for 3 columns; fixed gaps; print-specific `@media print` overrides.
- 3x3 constraints: IDs are landscape; in portrait 3 columns, rows won’t fully fill page height due to aspect ratio.
- Separate QR pages hide ID canvases and size QR boxes to the same 3-column grid.
- Scaling: uniform `transform: scale()` applied to the canvas to avoid overlapping absolute-positioned layers.

## Known Invariants
- 9 per page, portrait; QR pages separated.
- Canvas base size ~1011x639; scaled uniformly to cell width.
- Avoid browser auto-scaling; enforce 100% scale in print dialog.
