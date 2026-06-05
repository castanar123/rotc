# Module: Document Generation UI (document_generation.php)

- Path: `document_generation.php`
- Audience: Admin, Developers
- Purpose: UI for generating AER/ASR documents (CSV and related outputs) and routing to generator endpoints.
- Access & Roles: Admin/authorized users depending on implementation.

## Related Generators
- `generate_document.php` (server-side CSV generation for Roster, Summary, Beneficiaries, Cadet Profile, etc.)

## Screenshots
- Add: document_generation_ui.png

## Developer Notes
- See also: `docs/Modules/generate_document.md` for server logic, field normalization rules (e.g., City Province, Excel-safe contact formulas).
