# Document Generation (AER)

![Document Generation Success](../images/document_generation_success.png)

## Admin Guide
- Trigger via UI (AER documents) or POST to API.
- Output is CSV saved under `output/` with a timestamped filename.

## API
- Endpoint: `generate_document.php` (POST JSON)
- Body:
  - `document_type`: `aer`
  - `sub_document`: `summary|roster|beneficiaries|cadet_profile`
- Response JSON: `{ success, message, file_path, download_url }`

## CSV Schemas and Transforms
- Summary: counts per MS level (MS-1, MS-32, MS-42), male/female/total.
- Roster:
  - Columns: `NR,L/NAME,F/NAME,MI,COURSE,DOB,CONTACT NUMBER,ADDRESS`
  - Contact normalization:
    - Accepts `+63/63/9XXXXXXXXX/0XXXXXXXXXX`, outputs `0XXXXXXXXXX`.
    - Emitted as `="099…"` so Excel preserves leading zero without an apostrophe.
  - Address formatting:
    - `City Province` (single space, no comma).
    - Strips `City of`, `Municipality of`, `Province of`.
    - Falls back to `cp.address` when needed.
  - DOB: formats to `d-M-y` or `N/A`.
  - Grouped by gender then MS-level with continuous numbering.
- Beneficiaries:
  - Adds `BENEFICIARY` and `RELATIONSHIP` with Father→Mother→Guardian priority.
  - Uses `beneficiary_address` if present; else cadet `address`.
- Cadet Profile:
  - Columns include `CITY PROVINCE` and `RGN`.
  - Region normalized to codes like `IV-A`, `NCR`.

## Developer Notes
- File: `generate_document.php`
- Functions:
  - `generateAERDocument($pdo, $subDocument)`
  - `generateSummaryDocument($pdo)`
  - `generateRosterDocument($pdo)`
  - `generateBeneficiariesDocument($pdo)`
  - `generateCadetProfileDocument($pdo)`
- Output directory must be writable; on success returns path for download.

## Example cURL
```bash
curl -X POST -H "Content-Type: application/json" \
  -d '{"document_type":"aer","sub_document":"roster"}' \
  http://localhost/generate%20qr/generate_document.php
```
