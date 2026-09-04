# Rezera — Notification Architecture

**Status:** Phase 8 — in-app inbox in Flutter + FCM HTTP v1 provider (opt-in)  
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
Provider abstraction (mock or FCM HTTP v1)
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
| `push` | Abstraction + mock + FCM HTTP v1 |
| `sms` | Abstraction + mock provider |
| `email` | Laravel Mail |
| `telegram` | Abstraction + mock provider |

### Provider status

| Provider | Status |
|---|---|
| Mock push/SMS/Telegram | **IMPLEMENTED** (tests) |
| Firebase FCM | **IMPLEMENTED** (HTTP v1; enable with credentials) |
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
- `NOTIFICATION_PUSH_DRIVER=mock` or `fcm`
- `FCM_PROJECT_ID`
- `FCM_SERVICE_ACCOUNT_PATH` (default `storage/app/firebase-credentials.json`)

### Enabling FCM (confirm / reject push)

1. Create a Firebase project and enable Cloud Messaging.
2. Create a service account with Firebase Cloud Messaging Admin and save the JSON to `storage/app/firebase-credentials.json` (gitignored).
3. Set:

```
NOTIFICATION_PUSH_ENABLED=true
NOTIFICATION_PUSH_DRIVER=fcm
FCM_PROJECT_ID=your-project-id
FCM_SERVICE_ACCOUNT_PATH=storage/app/firebase-credentials.json
```

4. Run a queue worker (`DeliverNotificationJob`). Confirm and cancel already dispatch the push channel.
5. Flutter currently registers the device on login and shows the in-app inbox. Device FCM tokens require adding `firebase_messaging` + `google-services.json` / `GoogleService-Info.plist` (not committed). Until then, customers still see confirm/reject in the inbox.

Stale FCM tokens (`UNREGISTERED` / `NOT_FOUND`) deactivate the device row.

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
| FCM client | `app/Services/Notifications/Providers/Fcm/FcmClient.php` |
| Tests | `tests/Feature/Api/V1/Notification/NotificationApiTest.php`, `tests/Feature/Notifications/FcmPushNotificationProviderTest.php` |
