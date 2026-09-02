# Business Staff & Role Management

Centralized staff membership, role capabilities, and invitation workflow for Rezera businesses.

## Architecture

Rezera reuses the existing **`business_members`** table and three system roles (`owner`, `manager`, `staff`). Module #28 formalizes permissions and adds invitations without a second membership system.

```
business_members (active membership)
business_member_invitations (pending onboarding)
        ↓
BusinessStaffPermissionService (role capability matrix)
BusinessAuthorizationService (gate checks / policies)
        ↓
Manage APIs + Me invitation APIs
        ↓
Audit logs + notifications
```

### Conceptual job titles vs system roles

Businesses may label staff as "Administrator", "Reception Staff", or "Operator" using optional `job_title`. Authorization always uses `member_role`:

| System role | Typical responsibilities |
|-------------|-------------------------|
| `owner` | Full business control, can add managers |
| `manager` | Operational + settings management, can add staff |
| `staff` | Booking operations (check-in, status updates) |

## Permission matrix

Configured in `config/business_staff.php` and exposed via:

- `GET /api/v1/manage/businesses/{business}/staff/roles`
- `GET /api/v1/manage/businesses/{business}/staff/me`

Key permissions include: `manage_business`, `manage_members`, `add_manager`, `operate_bookings`, `administer_bookings`, `manage_reservation_settings`, `view_analytics`, `leave_business`.

Platform admins receive all permissions when accessing a business.

## Membership flows

### Direct add (existing users only)

`POST /api/v1/manage/businesses/{business}/members`

Requires the phone to match an existing `users` row. Creates active membership immediately.

### Invitation flow

1. `POST /api/v1/manage/businesses/{business}/invitations`
2. Invitee receives in-app notification (if registered)
3. `GET /api/v1/me/business-invitations`
4. `POST /api/v1/me/business-invitations/{invitation}/accept` or `/decline`
5. Accept creates `business_members` row with snapshotted role/job title

Invitations expire after `config('business_staff.invitation_expiry_days')` (default 14).

## API endpoints

### Management

| Method | Path | Access |
|--------|------|--------|
| GET | `/manage/businesses/{business}/members` | Owner/Manager |
| GET | `/manage/businesses/{business}/members/{member}` | Owner/Manager |
| POST | `/manage/businesses/{business}/members` | Owner/Manager |
| PATCH | `/manage/businesses/{business}/members/{member}` | Owner/Manager* |
| DELETE | `/manage/businesses/{business}/members/{member}` | Owner/Manager* |
| GET | `/manage/businesses/{business}/staff/roles` | Owner/Manager |
| GET | `/manage/businesses/{business}/staff/me` | Any active member |
| GET | `/manage/businesses/{business}/invitations` | Owner/Manager |
| POST | `/manage/businesses/{business}/invitations` | Owner/Manager** |
| DELETE | `/manage/businesses/{business}/invitations/{invitation}` | Owner/Manager |

\* Manager restrictions apply for manager role changes/removals (owner only).  
\** Adding `manager` role requires owner (existing policy).

### Customer / invitee

| Method | Path |
|--------|------|
| GET | `/me/business-invitations` |
| POST | `/me/business-invitations/{invitation}/accept` |
| POST | `/me/business-invitations/{invitation}/decline` |

## Schema extensions

### `business_members`

- `job_title` (nullable) — display label
- `invited_by_user_id` (nullable)
- `joined_at` (nullable)

### `business_member_invitations`

Pending staff onboarding with unique partial index on `(business_id, phone)` where `status = pending`.

## Audit & notifications

Member add/update/remove and invitation lifecycle events are written to `audit_logs`.

Notification types:

- `business_staff_invited`
- `business_staff_added`

## Subscription limits

Non-owner staff count is enforced via `SubscriptionFeature::STAFF_MAX` in add/invite actions.

## Security

- All authorization uses server-side `business_members` rows — never client-provided roles.
- Cross-business IDOR prevented via business/member pairing checks.
- Invitation accept/decline requires authenticated user's phone to match invitation phone.
- Owner removal and ownership transfer remain blocked.

## Testing

`tests/Feature/Api/V1/Business/BusinessStaffManagementTest.php`  
Existing `BusinessMemberTest.php` covers core membership rules.
