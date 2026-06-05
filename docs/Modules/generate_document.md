# Module: generate_document.php

## Overview
Generates AER CSV documents: Summary, Roster, Beneficiaries, Cadet Profile, and ASR placeholder.

## Entry Point
- POST JSON to `generate_document.php`
- Body: `{ document_type: 'aer', sub_document: 'summary|roster|beneficiaries|cadet_profile' }`
- Returns JSON with `success`, `message`, and `download_url`.

## Functions
### generateAERDocument(PDO $pdo, string $subDocument)
- Purpose: Dispatch to AER sub-document generators.
- Params: `$pdo` DB handle, `$subDocument` one of supported types.
- Returns: void (echoes JSON via sub-call).
- Errors: throws on invalid sub-document.

### generateSummaryDocument(PDO $pdo)
- Purpose: Summarize enrollment by MS level with male/female counts.
- Returns: CSV file path in JSON.
- Side effects: Writes `output/AER_Summary_<timestamp>.csv`.

### generateRosterDocument(PDO $pdo)
- Purpose: Output roster grouped by gender and MS.
- Columns: `NR,L/NAME,F/NAME,MI,COURSE,DOB,CONTACT NUMBER,ADDRESS`.
- Transforms:
  - DOB → `d-M-y` or `N/A`.
  - Contact → normalize to `0XXXXXXXXXX`; CSV emits as `="099…"` for Excel.
  - Address → `City Province`; strips prefixes/suffixes; fallback to `address`.
- Side effects: Writes `output/AER_Roster_<timestamp>.csv`.

### generateBeneficiariesDocument(PDO $pdo)
- Purpose: Output beneficiary list with Father→Mother→Guardian priority.
- Columns: includes `BENEFICIARY`, `RELATIONSHIP`, `ADDRESS`.
- Side effects: Writes `output/AER_Beneficiaries_<timestamp>.csv`.

### generateCadetProfileDocument(PDO $pdo)
- Purpose: Output cadet profile sheet.
- Columns: includes `CITY PROVINCE`, `RGN` with normalized region codes.
- Side effects: Writes `output/AER_Cadet_Profile_<timestamp>.csv`.

### generateASRDocument(PDO $pdo)
- Purpose: Placeholder ASR CSV.
- Side effects: Writes `output/ASR_Document_<timestamp>.csv`.

## Invariants
- Only approved and active users/cadets are included.
- City/Province formatting and region normalization are consistent across docs.
