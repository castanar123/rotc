# Changelog (Key Fixes)

- 2025-11-14
  - ID Card 3x3 (A4 portrait) stabilized with uniform scaling and print overrides.
  - Cadet Profile CSV: `CITY PROVINCE` single column (single space, no comma), header updated.
  - Roster CSV: Address formatted as `City Province`; contact numbers normalized to `0XXXXXXXXXX` and exported as `="099…"` for Excel.
  - Region normalized to codes (e.g., `IV-A`, `NCR`) in Cadet Profile.

- 2025-11-13 and earlier
  - Initial AER document exports (Summary, Roster, Beneficiaries, Cadet Profile) with grouping and field sanitization.
