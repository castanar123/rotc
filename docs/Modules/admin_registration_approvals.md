# Module: admin/registration_approvals.php

## Overview
Admin approvals page with single, batch, and approve-all operations.

## Actions (POST)
- `approve_single`, `reject_single`, `approve_selected`, `reject_selected`, `approve_all`
- Effects:
  - users.approval_status → `approved|rejected`
  - users.status → `active|inactive`
  - cadet_profiles.status → `Active` on approval

## Logging
- Uses `SecurityLogger` to record admin access and data modifications.

## UI
- Statistics, batch action toolbar, pending table with per-row actions.
