# Rezera — Payment Architecture

**Status:** Foundation implemented (Prompt #11)  
**Depends on:** `docs/RESERVATION_ENGINE.md`, `docs/BOOKING_MANAGEMENT.md`

---

## 1. Architecture overview

```
Customer
   ↓
POST /me/reservations/{id}/payments
   ↓
CreatePaymentAction
   ↓
PaymentService
   ↓
PaymentProviderResolver
   ↓
PaymentGatewayInterface
   ↓
MockPaymentGateway (implemented)
   ↓
Future: Payme / Click / Uzum / Stripe adapters
```

The reservation engine does **not** contain provider-specific logic. Payments are a separate domain with their own model, status lifecycle, and gateway abstraction.

---

## 2. Database

### `payments`

| Column | Purpose |
|---|---|
| `payment_number` | Public identifier (`RZ-PAY-YYYYMMDD-XXXXXX`) |
| `business_id`, `reservation_id`, `user_id` | Tenancy and ownership |
| `provider` | Gateway provider (`mock`, `payme`, …) |
| `provider_payment_id` | External reference |
| `status` | Payment lifecycle status |
| `amount`, `currency` | Server-calculated payable amount |
| `metadata` | Safe provider metadata (JSON) |
| Status timestamps | `paid_at`, `failed_at`, `cancelled_at`, `refunded_at` |

**Constraint:** partial unique index — one active (`pending`/`processing`) payment per reservation.

### `payment_webhook_events`

Stores webhook payloads for idempotent processing.

**Unique:** `(provider, event_id)`

---

## 3. Payment statuses

| Status | Description |
|---|---|
| `pending` | Created, awaiting provider action |
| `processing` | Provider processing |
| `paid` | Successfully paid |
| `failed` | Failed |
| `cancelled` | Cancelled before completion |
| `refunded` | Fully refunded |
| `partially_refunded` | Partial refund (foundation only) |

Transitions enforced by `PaymentTransitionService`.

---

## 4. Provider status

| Provider | Status |
|---|---|
| `mock` | **IMPLEMENTED** (development/testing) |
| `payme` | PLANNED |
| `click` | PLANNED |
| `uzum` | PLANNED |
| `stripe` | PLANNED |
| `cash` | PLANNED |

---

## 5. API endpoints

### Customer

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/v1/me/reservations/{reservation}/payments` | Create payment |
| `GET` | `/api/v1/me/payments/{payment}` | Payment detail (`?sync=1` optional) |

Request body (create):

```json
{ "provider": "mock" }
```

**Not accepted from client:** `amount`, `currency`, `user_id`, `business_id`

### Business

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/manage/businesses/{business}/payments` | List with filters |
| `GET` | `/api/v1/manage/businesses/{business}/payments/{payment}` | Detail |

Filters: `status`, `provider`, `reservation_id`, `resource_id`, `date`, `date_from`, `date_to`, `search`

### Webhooks (no customer auth)

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/v1/payments/webhooks/{provider}` | Provider callback entry point |

Mock webhook requires header `X-Mock-Webhook-Secret` when simulation is enabled.

---

## 6. Reservation integration

On **successful payment** (`paid`):

1. `reservations.payment_status` → `paid`
2. `reservations.payment_method` → provider mapping
3. If reservation is `pending` → auto-confirmed via `ChangeReservationStatusAction`

Payment record creation alone does **not** change reservation status.

---

## 7. Security

- Amount/currency always from reservation snapshot
- `PaymentPolicy` enforces customer/business isolation
- Webhook idempotency via `payment_webhook_events`
- Sensitive fields stripped from webhook payload storage
- Mock simulation disabled in production by default
- No card data stored

---

## 8. Configuration

`config/payment.php`:

| Variable | Purpose |
|---|---|
| `PAYMENT_DEFAULT_CURRENCY` | Default currency (UZS) |
| `PAYMENT_MOCK_ENABLED` | Enable mock provider |
| `PAYMENT_MOCK_ALLOW_SIMULATION` | Allow mock webhooks |
| `PAYMENT_MOCK_WEBHOOK_SECRET` | Mock webhook secret |
| `PAYMENT_PAYME_ENABLED` | Future Payme toggle |
| `PAYME_MERCHANT_ID`, `PAYME_SECRET` | Future Payme credentials |

---

## 9. Domain events

Dispatched after commit:

- `PaymentCreated`
- `PaymentProcessing`
- `PaymentSucceeded`
- `PaymentFailed`
- `PaymentCancelled`
- `PaymentRefunded`

---

## 10. Error codes

| Code | HTTP |
|---|---|
| `payment_provider_disabled` | 422 |
| `payment_provider_not_implemented` | 422 |
| `payment_not_allowed` | 422 |
| `payment_already_active` | 409 |
| `payment_invalid_state` | 422 |
| `payment_provider_error` | 502 |
| `invalid_webhook_signature` | 403 |

---

## 11. Testing

`tests/Feature/Api/V1/Payment/PaymentApiTest.php` covers:

- Payment creation and server-side amount
- Authorization and cross-tenant isolation
- Duplicate active payment protection
- Mock webhook success and idempotency
- Invalid webhook signature
- Business payment listing
- Invalid status transitions

---

## 12. Future stages

- Payme / Click / Uzum / Stripe adapter implementations
- Real webhook signature verification per provider
- Refund workflows
- Payouts and marketplace settlement
- 3-D Secure, card tokenization

---

## 13. Key files

| Area | Path |
|---|---|
| Gateway contract | `app/Contracts/Payments/PaymentGatewayInterface.php` |
| Mock gateway | `app/Services/Payments/Gateways/MockPaymentGateway.php` |
| Provider resolver | `app/Services/Payments/PaymentProviderResolver.php` |
| Payment service | `app/Services/Payments/PaymentService.php` |
| Webhook action | `app/Actions/Payments/ProcessPaymentWebhookAction.php` |
| Policy | `app/Policies/PaymentPolicy.php` |
