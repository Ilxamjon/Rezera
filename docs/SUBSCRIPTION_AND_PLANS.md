# Business Subscription & Plans

Rezera's subscription engine provides business-level monetization through database-driven plans, generic entitlements, lifecycle management, and centralized enforcement.

## Architecture

```text
Subscription Plan
       ↓
Plan Entitlements
       ↓
Business Subscription
       ↓
Business Entitlement Service
       ↓
Feature / Usage Enforcement
       ↓
Existing Rezera Modules
```

Subscriptions belong to a **Business**, not a user. Each business has its own effective subscription and entitlements.

## Database

| Table | Purpose |
|-------|---------|
| `subscription_plans` | Plan catalog (code, prices, trial, visibility) |
| `plan_entitlements` | Generic feature/limit definitions per plan |
| `business_subscriptions` | Business subscription lifecycle state |

`payments.business_subscription_id` links subscription charges to the existing payment architecture. `payments.reservation_id` is nullable when the payment targets a subscription.

### Constraints

- Unique plan `code`
- Unique `(plan_id, feature_code)` per entitlement
- Partial unique index: one effective subscription per business (`trialing`, `active`, `past_due`, `paused`)

## Plans

Plans are seeded by `SubscriptionPlanSeeder`:

- `free` (default, public)
- `basic`, `pro`, `business` (public paid tiers)
- `enterprise` (private)

Default plan code: `config('subscriptions.default_plan')` → `free`.

Prices are stored as integer minor units (UZS). The client never supplies prices.

## Entitlements

Feature codes live in `App\Domain\Subscriptions\SubscriptionFeature`.

Implemented entitlements:

| Code | Type | Enforcement |
|------|------|-------------|
| `resources.max` | integer | Resource creation |
| `resource_categories.max` | integer | Resource category creation |
| `staff.max` | integer | Staff member addition (owner excluded) |
| `reservations.monthly.max` | integer | Usage metering foundation |
| `analytics.basic` | boolean | Informational |
| `analytics.advanced` | boolean | Advanced analytics endpoints |
| `pricing.advanced` | boolean | Pricing rule creation |
| `promo_codes` | boolean | Promo code creation |
| `reviews` | boolean | Informational |

### Resolution

```text
Business → effective subscription → plan → entitlements
```

If no effective subscription exists, the configured default plan is used.

## Services

- `BusinessEntitlementService` — `can`, `has`, `limit`, `check`, `assertWithinLimit`
- `BusinessUsageService` — `getUsage`, `getLimit`, `remaining`, `canConsume`, `summary`
- `SubscriptionLifecycleService` — create, change, cancel, resume, renew, expire
- `SubscriptionPaymentService` — creates pending payments via `PaymentGatewayInterface`
- `SubscriptionTransitionService` — explicit state machine

## Subscription Lifecycle

Statuses: `trialing`, `active`, `past_due`, `paused`, `cancelled`, `expired`.

### Subscribe

`POST /api/v1/manage/businesses/{business}/subscription`

- Free plan → immediate `active` subscription
- Paid plan with trial (first subscription) → `trialing`
- Paid upgrade → payment intent; activation on `PaymentSucceeded`
- Downgrade → `pending_plan_id`, applied at period end

### Cancellation

`POST /api/v1/manage/businesses/{business}/subscription/cancel`

Supports `at_period_end` (default) and optional `reason`.

### Resume

`POST /api/v1/manage/businesses/{business}/subscription/resume`

Clears scheduled period-end cancellation.

### Scheduler

`subscriptions:process-lifecycle` (hourly):

- Ends expired trials → `active`
- Applies period-end cancellations
- Expires cancelled subscriptions
- Applies pending downgrades and renews period timestamps

## Usage Period

Monthly reservation usage prefers the subscription billing period when available; otherwise calendar month (UTC).

## Over-limit Behavior

When usage exceeds a downgraded plan limit:

- Existing data remains
- New creation is blocked
- API returns `PLAN_LIMIT_REACHED`

Reservations are never auto-cancelled on expiration/downgrade.

## Payment Integration

Subscription payments reuse:

- `PaymentService` / `PaymentGatewayInterface`
- `PaymentSucceeded` listener → `FulfillSubscriptionPaymentAction`

Payment metadata stores `subscription_intent` for idempotent fulfillment. No real provider recurring billing is implemented in this module.

## API Endpoints

### Public

- `GET /api/v1/subscription-plans`
- `GET /api/v1/subscription-plans/{plan}`

### Business manage

- `GET /api/v1/manage/businesses/{business}/subscription`
- `POST /api/v1/manage/businesses/{business}/subscription`
- `GET /api/v1/manage/businesses/{business}/subscription/usage`
- `POST /api/v1/manage/businesses/{business}/subscription/cancel`
- `POST /api/v1/manage/businesses/{business}/subscription/resume`

### Admin

- `GET/POST /api/v1/admin/subscription-plans`
- `GET/PATCH /api/v1/admin/subscription-plans/{plan}`
- `GET/PUT /api/v1/admin/subscription-plans/{plan}/entitlements`
- `GET /api/v1/admin/subscriptions`
- `GET /api/v1/admin/subscriptions/{subscription}`

Permissions: `subscriptions.view` (support+), `subscriptions.manage` (admin+).

## Error Codes

- `PLAN_FEATURE_RESTRICTED`
- `PLAN_LIMIT_REACHED`
- `SUBSCRIPTION_NOT_ACTIVE`
- `INVALID_SUBSCRIPTION_TRANSITION`
- `PLAN_NOT_AVAILABLE`
- `SUBSCRIPTION_PAYMENT_REQUIRED`

## Migration Strategy

`SubscriptionPlanSeeder` assigns the default Free plan to existing businesses without an effective subscription. New businesses receive a Free subscription in `CreateBusinessAction`.

## Future Provider Integration

Provider adapters (Payme, Click, Uzum, Stripe) can:

1. Create recurring provider subscriptions
2. Map webhooks to `PaymentService::applyGatewayResult`
3. Drive `past_due` / `active` transitions without rewriting entitlements

Core plan, entitlement, and enforcement layers remain unchanged.
