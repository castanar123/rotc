# Module: admin_dashboard.php

## Overview
Admin command center with KPIs, quick actions, QR integration, and approvals snippet.

## Data Sources
- Users and cadet_profiles for counts and pending lists.
- Attendance (today’s rate and presence).
- Recent activities via `audit_logs` or fallbacks.

## Actions
- AJAX approval updates (single/all) with logging.

## UI Sections
- Stats grid (users, cadets, officers, attendance rate, etc.)
- QR system integration shortcuts
- Recent activities
- Quick actions
- Pending approvals (if any)
