# Rezera — Notification Architecture

**Status:** Foundation implemented (Prompt #12)  
**Depends on:** `docs/BOOKING_MANAGEMENT.md`, `docs/PAYMENT_ARCHITECTURE.md`

---

## 1. Architecture

```
Domain Event (Reservation/Payment)
        ↓
Listener (queued, after commit)
        ↓
NotificationService
        ↓
├── In-app record (user_notifications)
└── DeliverNotificationJob (push/sms/email/telegram)
        ↓
Provider abstraction (mock implemented)
```

Reservation/payment modules dispatch events only — never call external APIs directly.

---

## 2. Database tables

| Table | Purpose |
|---|---|
| `user_notifications` | In-app notification inbox |
| `notification_preferences` | Per-user channel preferences |
| `user_devices` | Push device tokens |
| `notification_deliveries` | External delivery tracking |

---

## 3. Notification types

| Type | Audience |
|---|---|
| `reservation_created` | Customer |
| `reservation_confirmed` | Customer |
| `reservation_cancelled` | Customer |
| `reservation_completed` | Customer |
| `reservation_no_show` | Customer |
| `reservation_reminder` | Customer (foundation) |
| `payment_created` | Customer |
| `payment_succeeded` | Customer |
| `payment_failed` | Customer |
| `payment_refunded` | Customer |
| `business_booking_received` | Business staff |
| `business_booking_cancelled` | Business staff |
| `system_notification` | Any (future) |

---

## 4. Channels

| Channel | Status |
|---|---|
| `database` (in-app) | **IMPLEMENTED** |
| `push` | Abstraction + mock provider |
| `sms` | Abstraction + mock provider |
| `email` | Laravel Mail |
| `telegram` | Abstraction + mock provider |

### Provider status

| Provider | Status |
|---|---|
| Mock push/SMS/Telegram | **IMPLEMENTED** (tests) |
| Firebase FCM | PLANNED |
| Eskiz / Play Mobile / Twilio | PLANNED |
| SendGrid / Mailgun | PLANNED |
| Telegram Bot API | PLANNED |

---

## 5. API endpoints

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/me/notifications` |
| `GET` | `/api/v1/me/notifications/unread-count` |
| `GET` | `/api/v1/me/notifications/{notification}` |
| `PATCH` | `/api/v1/me/notifications/{notification}/read` |
| `POST` | `/api/v1/me/notifications/read-all` |
| `GET` | `/api/v1/me/notification-preferences` |
| `PUT` | `/api/v1/me/notification-preferences` |
| `POST` | `/api/v1/me/devices` |
| `PATCH` | `/api/v1/me/devices/{device}` |
| `DELETE` | `/api/v1/me/devices/{device}` |

Filters: `?unread=true`, `?type=reservation_confirmed`

---

## 6. Default preferences

| Channel | Default |
|---|---|
| In-app | Always enabled |
| Push | Enabled when active device with token exists |
| Email | Enabled when verified email exists |
| SMS | Disabled |
| Telegram | Disabled |

---

## 7. Localization

Supported locales: `uz`, `kaa`, `ru` (+ `en` fallback)

Templates: `lang/{locale}/notifications.php`

User locale from `users.locale`, fallback to app default.

---

## 8. Idempotency

Unique key: `hash(type|user_id|entity_key)` on `user_notifications`.

Duplicate domain events do not create duplicate in-app notifications.

Delivery idempotency: unique `idempotency_key` per notification+channel on `notification_deliveries`.

---

## 9. Event integration

| Event | Notifications |
|---|---|
| `ReservationCreated` | Customer + business booking received |
| `ReservationStatusChanged` | Confirmed/cancelled/completed/no-show |
| `PaymentCreated` | Payment created |
| `PaymentSucceeded` | Payment success |
| `PaymentFailed` | Payment failed |
| `PaymentRefunded` | Payment refunded |

Listeners use `$afterCommit = true` and are queued.

---

## 10. Configuration

`config/notifications.php`:

- `NOTIFICATION_PUSH_ENABLED`
- `NOTIFICATION_SMS_ENABLED`
- `NOTIFICATION_EMAIL_ENABLED`
- `NOTIFICATION_TELEGRAM_ENABLED`
- `NOTIFICATION_PUSH_DRIVER=mock`
- `FCM_PROJECT_ID` (future)

---

## 11. Security

- Users access only own notifications, preferences, devices
- Push tokens never exposed to other users
- Sanitized webhook/delivery logs
- No provider secrets in API responses

---

## 12. Reminder foundation

`reservation_reminder` type and templates are defined. Automated scheduling is a future stage and must use business timezone.

---

## 13. Key files

| Area | Path |
|---|---|
| Orchestrator | `app/Services/Notifications/NotificationService.php` |
| Preferences | `app/Services/Notifications/NotificationPreferenceService.php` |
| Listeners | `app/Listeners/Notifications/*` |
| Delivery job | `app/Jobs/Notifications/DeliverNotificationJob.php` |
| Tests | `tests/Feature/Api/V1/Notification/NotificationApiTest.php` |
