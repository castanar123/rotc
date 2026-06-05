# Rifle Management & Borrowing

![Rifle Management](../images/rifle_management.png)

## Admin Guide
- Track rifles, statuses, and assignments.
- Borrow/return flow with QR support.

## Developer Notes
- Entry points:
  - `rifle_management.php`, `borrow_rifle.php`, `process_borrowing.php`
  - `rifle_scanner.php`, `rifle_scanner.html`, `rifle_scanner.js`
  - Supporting fixes/migration scripts under root (e.g., `create_rifle_tables.php`)
- Behavior:
  - Inventory CRUD, borrow/return transactions, QR integration.
  - Validation of availability and borrower identity.

## Screenshots
- Inventory list, borrow/return dialogs, QR scanner screen
