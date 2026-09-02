# Rezera — Admin Panel & Platform Management

**Status:** Foundation implemented (Prompt #13)

---

## 1. Role separation

| Level | Roles | API prefix |
|---|---|---|
| Platform | `super_admin`, `platform_admin` (legacy), `admin`, `support` | `/api/v1/admin/*` |
| Business | `owner`, `manager`, `staff` | `/api/v1/manage/businesses/{business}/*` |
| Customer | authenticated user | `/api/v1/me/*` |

Business membership does **not** grant platform admin access.

---

## 2. Platform permissions

| Permission | Super/Platform Admin | Admin | Support |
|---|---|---|---|
| users.view | ✓ | ✓ | ✓ |
| users.manage | ✓ | ✓ | ✗ |
| businesses.view/manage | ✓ | ✓ | view only |
| reservations.view/manage | ✓ | ✓ | view only |
| payments.view | ✓ | ✓ | ✓ |
| categories.manage | ✓ | ✓ | ✗ |
| settings.manage | ✓ | ✗ | ✗ |
| audit_logs.view | ✓ | ✓ | ✗ |

Implemented in `PlatformAuthorizationService`.

---

## 3. API endpoints

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/admin/dashboard` |
| `GET` | `/api/v1/admin/search?q=` |
| `GET/PATCH` | `/api/v1/admin/users`, `/users/{id}`, `/users/{id}/status` |
| `GET/PATCH` | `/api/v1/admin/businesses`, `/businesses/{id}`, `/status`, `/verification` |
| CRUD | `/api/v1/admin/categories` |
| `GET/PATCH` | `/api/v1/admin/reservations`, `/reservations/{id}/status` |
| `GET` | `/api/v1/admin/payments`, `/payments/{id}` |
| `GET` | `/api/v1/admin/notification-deliveries` |
| `GET/PATCH` | `/api/v1/admin/settings`, `/settings/{key}` |
| `GET` | `/api/v1/admin/audit-logs` |
| `GET` | `/api/v1/config` (public settings) |

---

## 4. Dashboard

Uses database aggregates (COUNT/SUM). Platform date filters use **UTC**.

Query: `?date_from=2026-09-01&date_to=2026-09-30`

---

## 5. User status

Statuses: `active`, `suspended`, `blocked`

Suspended/blocked users should be prevented from prohibited actions via `UserStatus::canAuthenticate()`.

Historical data is preserved.

---

## 6. Business status & visibility

- **Status:** existing `BusinessStatus` enum (draft, pending_review, approved, suspended, etc.)
- **Visibility:** `is_publicly_listed` boolean
- **Verification:** `verification_status` (`pending`, `verified`, `rejected`)

Public discovery requires `approved` status **and** `is_publicly_listed=true`.

---

## 7. Audit logs

Append-only `audit_logs` table. No update/delete API.

Recorded actions: user status changes, business status/verification, category changes, platform settings.

---

## 8. Security

- All `/admin/*` routes: `auth:sanctum` + `platform.admin` + `throttle:admin`
- Reservation status changes reuse `ChangeReservationStatusAction`
- Payments: view only — no manual "mark as paid"
- Secrets never stored in `platform_settings`

---

## 9. Maintenance mode

`maintenance_mode` public setting exists. Enforcement middleware is **PLANNED**.

---

## 10. Key files

| Area | Path |
|---|---|
| Authorization | `app/Services/Authorization/PlatformAuthorizationService.php` |
| Dashboard | `app/Services/Admin/AdminDashboardService.php` |
| Audit | `app/Actions/Platform/CreateAuditLogAction.php` |
| Controllers | `app/Http/Controllers/Api/V1/Admin/*` |
| Tests | `tests/Feature/Api/V1/Admin/AdminApiTest.php` |
