# Rezera — Authentication & Authorization

**Status:** Implemented (Prompt #5)  
**Depends on:** `docs/BACKEND_ARCHITECTURE.md`, `docs/DATABASE_IMPLEMENTATION.md`

---

## 1. Authentication architecture

- **Mobile client:** Flutter with Bearer tokens in secure storage
- **Backend:** Laravel Sanctum personal access tokens
- **Guard:** `auth:sanctum` on protected routes
- **Not used for mobile:** session/cookie authentication

```
Flutter → POST /api/v1/auth/login → Sanctum token → Authorization: Bearer {token}
```

---

## 2. Laravel Sanctum setup

- Package: `laravel/sanctum` (already in `composer.json`)
- Migration: `personal_access_tokens` with `uuidMorphs('tokenable')`
- User model: `HasApiTokens`
- Config: `config/sanctum.php`, `config/auth.php` (`api` guard → sanctum)

---

## 3. Token strategy

| Aspect | Decision |
|---|---|
| Token type | Sanctum personal access token (plain text returned once) |
| Token name | `device_name` request field, default `mobile` |
| Expiration | `null` (no expiry in MVP config) |
| Logout | Deletes **current token only** |
| Multi-device | Supported — each login creates a new token |
| Future | Logout-all-devices via `$user->tokens()->delete()` |

Response includes `token_type: Bearer`.

---

## 4. Registration flow

**Endpoint:** `POST /api/v1/auth/register`  
**Rate limit:** `throttle:auth` (10/min per IP+phone)

1. Validate input (Form Request)
2. Normalize phone to E.164
3. Create user (`platform_role=user`, `status=active`)
4. **Auto-login:** issue Sanctum token immediately
5. Return `201` with user + token

**Fields:**

| Field | Required | Notes |
|---|---|---|
| name | Yes | max 120 |
| phone | Yes | normalized to E.164 |
| password | Yes | confirmed, Laravel defaults |
| password_confirmation | Yes | |
| locale | No | `uz`, `kaa`, `ru` — default `ru` |
| email | No | unique if provided |
| device_name | No | default `mobile` |

---

## 5. Login flow

**Endpoint:** `POST /api/v1/auth/login`

1. Normalize phone
2. Find user (soft-deleted excluded)
3. Verify password with `Hash::check`
4. Reject if `status != active` (generic error)
5. Update `last_login_at`
6. Create token
7. Return user + token

**Invalid credentials:** always `401` with `code: invalid_credentials` — never reveals if phone exists.

---

## 6. Logout flow

**Endpoint:** `POST /api/v1/auth/logout` (requires `auth:sanctum`)

Revokes the current access token only. Returns standard success envelope.

---

## 7. Current user endpoint

**Endpoint:** `GET /api/v1/auth/me` (requires `auth:sanctum`)

Returns `UserResource` with:

- Safe identity fields
- `locale`, `platform_role`, `status`
- Active `business_memberships` (id, business name, role)
- `capabilities.is_platform_admin`, `capabilities.has_business_memberships`

Does **not** expose password, tokens, or internal security metadata.

---

## 8. Phone normalization strategy

**Class:** `App\Support\Phone\PhoneNormalizer`  
**Storage format:** E.164 `+998XXXXXXXXX`

Accepted inputs (examples):

- `+998901234567`
- `998901234567`
- `90 123 45 67`
- `90-123-45-67`

Applied in Form Requests via `NormalizesPhoneInput` trait before validation.  
Validation rule: `App\Rules\ValidUzbekPhone`

---

## 9. Preferred language strategy

**Enum:** `App\Domain\Identity\Enums\Locale` (`uz`, `kaa`, `ru`)  
**Config:** `config/rezera.php` → `supported_locales`  
**Default:** `ru`  
**User column:** `users.locale`

Flutter handles UI translations; backend stores preference for notifications and dynamic content selection later.

---

## 10. Platform-level authorization

| Role | Storage | Mechanism |
|---|---|---|
| Regular user | `users.platform_role = user` | default for registration |
| Platform admin | `users.platform_role = platform_admin` | Gate `platform-admin`, middleware `platform.admin` |

Business ownership is **not** a platform role.

---

## 11. Business-level authorization

**Table:** `business_members`  
**Roles:** `owner`, `manager`, `staff`  
**Service:** `App\Services\Authorization\BusinessAuthorizationService`

| Method | Who |
|---|---|
| `canManageBusiness` | platform admin, owner, manager |
| `canManageBookings` | platform admin, owner, manager, staff |
| `isMember` | any active member |

**Policy:** `App\Policies\BusinessPolicy` (`manage`, `manageBookings`, `view`)

User helper: `$user->canAccessBusiness($businessId, $minimumRole)`

Authorization is always scoped to a **specific business ID** from the database — never trust client-provided roles.

---

## 12. Policies and middleware

| Component | Purpose |
|---|---|
| `auth:sanctum` | Bearer token authentication |
| `platform.admin` | Platform admin API routes |
| `BusinessPolicy` | Business-scoped authorization |
| Gate `platform-admin` | Global admin checks |

No per-permission middleware explosion — use policies for business operations.

---

## 13. Rate limiting

| Limiter | Key | Limit |
|---|---|---|
| `auth` | IP + normalized phone | 10/min (`API_AUTH_RATE_LIMIT`) |
| `api` | user id or IP | 60/min |

Applied to `/auth/register` and `/auth/login`.

---

## 14. Security rules

1. Passwords hashed via Laravel `hashed` cast  
2. Never log passwords or tokens  
3. Generic login errors  
4. DB unique constraint on phone is authoritative  
5. Race on duplicate phone → `23505` translated to validation error  
6. Profile update does **not** allow phone changes (future verification flow)

---

## 15. API endpoints

| Method | Path | Auth | Description |
|---|---|---|---|
| POST | `/api/v1/auth/register` | No | Register + auto-login |
| POST | `/api/v1/auth/login` | No | Login |
| POST | `/api/v1/auth/logout` | Yes | Revoke current token |
| GET | `/api/v1/auth/me` | Yes | Current user |
| GET | `/api/v1/me/profile` | Yes | Customer profile (privacy-focused) |
| PATCH | `/api/v1/me/profile` | Yes | Update name, locale, timezone, email, avatar |
| GET | `/api/v1/profile` | Yes | Legacy profile alias |
| PATCH | `/api/v1/profile` | Yes | Legacy profile update alias |

---

## 16. Example requests

### Register

```http
POST /api/v1/auth/register
Content-Type: application/json

{
  "name": "Ilham",
  "phone": "90 123 45 67",
  "password": "password123",
  "password_confirmation": "password123",
  "locale": "uz",
  "device_name": "pixel-8"
}
```

### Login

```http
POST /api/v1/auth/login

{
  "phone": "+998901234567",
  "password": "password123"
}
```

---

## 17. Example responses

### Success (register/login)

```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "user": {
      "id": "uuid",
      "name": "Ilham",
      "phone": "+998901234567",
      "locale": "uz",
      "platform_role": "user",
      "business_memberships": [],
      "capabilities": {
        "is_platform_admin": false,
        "has_business_memberships": false
      }
    },
    "token": "1|....",
    "token_type": "Bearer"
  }
}
```

### Invalid credentials

```json
{
  "success": false,
  "message": "Invalid credentials.",
  "errors": null,
  "code": "invalid_credentials"
}
```

---

## 18. Test coverage

| Test class | Coverage |
|---|---|
| `RegistrationTest` | success, confirmation, phone, duplicate, locale |
| `LoginTest` | success, wrong password, unknown phone, blocked user |
| `LogoutTest` | revoke current token, auth required |
| `MeTest` | auth required, safe fields |
| `AuthorizationTest` | platform gate, business membership, policies |
| `ProfileTest` | view, update name/locale |

Run: `php artisan test --filter=Auth` or full `composer test` (PostgreSQL required).

---

## 19. Future integration points

| Feature | Integration point |
|---|---|
| SMS OTP | Before/after `RegisterUserAction`, verify `phone_verified_at` |
| Phone change | `POST /api/v1/me/profile/change-phone` + verification (see `docs/FAVORITES_AND_PROFILE.md`) |
| Password reset | `password_reset_tokens` table exists |
| Logout all devices | `$user->tokens()->delete()` |
| Token expiration | `config/sanctum.php` `expiration` |
| Admin user management | Filament or `/api/v1/admin` with `platform.admin` |

---

*End of authentication documentation.*
