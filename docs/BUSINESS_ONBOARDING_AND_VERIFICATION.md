# Business Onboarding & Verification

Structured lifecycle for bringing businesses onto Rezera, separate from subscriptions and reservations.

## Architecture

```text
Business Created
       ↓
Onboarding
       ↓
Profile Complete
       ↓
Ready for Verification
       ↓
Verification Pending
       ↓
Approved / Rejected
       ↓
Publication Rules
       ↓
Reservation Ready
```

## Concepts (kept separate)

| Concept | Storage | Purpose |
|---------|---------|---------|
| Business lifecycle | `businesses.status` | draft, pending_review, approved, rejected, suspended, archived |
| Verification | `businesses.verification_status` + `business_verifications` | Trust/moderation workflow |
| Onboarding | `businesses.onboarding_status` | Setup progress |
| Publication | `is_publicly_listed` + `BusinessVisibilityService` | Discovery eligibility |
| Reservation readiness | `BusinessReadinessService` | Operational booking prerequisites |

## Onboarding Steps

Centralized in `BusinessOnboardingService` / `BusinessOnboardingStep`:

1. `business_profile` — name, category, description
2. `location` — city, address, coordinates, timezone
3. `contact_information` — phone or email
4. `working_hours` — 7 weekdays with at least one open schedule
5. `resource_setup` — at least one active resource
6. `pricing_setup` — priced resource or active pricing rule
7. `reservation_settings` — booking policy exists

Percentage = completed required steps / total required steps.

## Verification Model

Table: `business_verifications`

| Field | Notes |
|-------|-------|
| status | pending, approved, rejected, cancelled |
| submitted_by_user_id | Owner/manager submission |
| rejection_reason | Shown to business on rejection |
| admin_notes | Internal only |

Partial unique index: one pending verification per business.

### State transitions

- `pending` → `approved` | `rejected` | `cancelled`
- Resubmission creates a new pending record after rejection

Business `verification_status`: `unverified`, `pending`, `verified`, `rejected`.

## Publication Rules

`BusinessVisibilityService::isPubliclyDiscoverable()` requires (configurable):

- `status = approved`
- `is_publicly_listed = true`
- `verification_status = verified` (if enabled)
- `onboarding_status = completed` (if enabled)
- not soft-deleted

Used by `Business::scopePubliclyVisible()`, discovery search, favorites, reservations.

## Reservation Readiness

`BusinessReadinessService::isReadyForReservations()` is separate from public discovery:

- Operational business status
- Onboarding complete
- Verified (if configured)
- Does not replace `AvailabilityEngine` / reservation validation

## API Endpoints

### Manage

| Method | Endpoint |
|--------|----------|
| GET | `/api/v1/manage/businesses/{business}/onboarding` |
| GET | `/api/v1/manage/businesses/{business}/readiness` |
| GET | `/api/v1/manage/businesses/{business}/verification` |
| POST | `/api/v1/manage/businesses/{business}/verification` |
| POST | `/api/v1/manage/businesses/{business}/verification/resubmit` |

### Admin

| Method | Endpoint |
|--------|----------|
| GET | `/api/v1/admin/business-verifications` |
| GET | `/api/v1/admin/business-verifications/{verification}` |
| POST | `/api/v1/admin/business-verifications/{verification}/approve` |
| POST | `/api/v1/admin/business-verifications/{verification}/reject` |

Legacy admin PATCH `/admin/businesses/{business}/verification` remains for backward compatibility.

## Authorization

- Verification submit/resubmit: owner or manager (`manageVerification`)
- Admin approve/reject: `BusinessesManage` permission
- Support: view queue only

## Notifications

- `business_verification_submitted` → platform admins
- `business_verification_approved` / `rejected` → business managers
- `business_onboarding_completed` → business managers
- `business_ready_for_reservations` → business managers

## Migration Strategy

Existing approved businesses are backfilled to:

- `verification_status = verified`
- `onboarding_status = completed`

New businesses start `unverified` / `in_progress`.

## Configuration

`config/business_onboarding.php`:

- `public_requires_verification`
- `public_requires_onboarding_complete`
- `reservation_requires_verification`
- `reservation_requires_onboarding_complete`

## Future KYC Extension

`business_verifications.metadata` can store document references and provider payloads without changing the core onboarding/verification workflow.
