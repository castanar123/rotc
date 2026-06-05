# Module: admin/id_card.php

## Overview
Batch ID card/QR rendering for print (3x3 A4 portrait) with separate QR pages.

## Print Logic
- `@media print` rules to control page breaks, 3-column grid, and uniform canvas scaling.
- Separate `.qr-pages` section for QR-only sheets.

## Invariants
- 9 per page; landscape card scaled to fit portrait grid.
- Print dialog must use A4, Portrait, 100% scale, background graphics on.

## Known Constraints
- Due to ID aspect ratio, 3x3 portrait leaves vertical whitespace.
