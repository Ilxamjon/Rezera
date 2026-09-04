# Rezera Mobile (Flutter)

Phase 6 customer app — auth, discovery, business detail. Booking create/cancel is next.

## Stack

- Flutter + Riverpod + go_router + Dio
- `flutter_secure_storage` for Sanctum tokens
- easy_localization (RU default, UZ/EN keys ready)

## Run

```bash
# Terminal 1 — Laravel API
cd ..
php artisan serve --host=0.0.0.0 --port=8000

# Terminal 2 — Flutter
cd mobile
flutter pub get

# Android emulator (maps host loopback via 10.0.2.2)
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000

# iOS simulator / desktop
flutter run --dart-define=API_BASE_URL=http://127.0.0.1:8000

# Physical device (replace with your PC LAN IP)
flutter run --dart-define=API_BASE_URL=http://192.168.x.x:8000
```

## Structure

```
lib/
  app/           router, shell, MaterialApp
  core/          config, network, theme, storage, errors, formatters
  features/
    auth/
    home/
    search/
    business/
    bookings/
    notifications/
    profile/
```

## Auth

- `POST /api/v1/auth/register|login` → Bearer token
- Guest browse is allowed; profile shows login CTA
- Phone normalized to E.164 (`+998…`)

## Modes

- **Customer:** discovery → book → my bookings
- **Owner:** Profile → Owner mode → dashboard / today ops / resources
  - Create / edit / delete resources
  - Generate printable resource QR (`rezera://check-in/{token}`)
  - Staff / QR check-in + check-out on today's bookings

Owner APIs use `/api/v1/manage/businesses/{id}/…`.

## Notifications (Phase 8)

- In-app inbox: `/notifications` (bell on Home/Profile, unread badge on Profile / More)
- Device row registered on login (`POST /api/v1/me/devices`); deactivated on logout
- Confirm/reject already create in-app + optional FCM on the API
- OS push: add FlutterFire (`firebase_messaging`) and client Google config files, then send `push_token` on device register. Backend FCM is ready (`NOTIFICATION_PUSH_DRIVER=fcm`).

## APK

### LAN / local beta (HTTP)

Debug/profile builds allow cleartext. Laravel must listen on all interfaces:

```bash
php artisan serve --host=0.0.0.0 --port=8000

cd mobile
flutter build apk --debug --dart-define=API_BASE_URL=http://192.168.1.90:8000
```

Output: `mobile/build/app/outputs/flutter-apk/app-debug.apk`

### Production (HTTPS)

Release builds require HTTPS and disable cleartext traffic.

```powershell
# From repo root
.\scripts\build-release-apk.ps1 -ApiBaseUrl https://api.your-domain.uz
```

Or:

```bash
cd mobile
flutter build apk --release --dart-define=API_BASE_URL=https://api.your-domain.uz
```

Output: `mobile/build/app/outputs/flutter-apk/app-release.apk`

Install:

```bash
adb install -r build/app/outputs/flutter-apk/app-release.apk
```

Signing: still **debug keys** (internal beta). Play Store needs a real keystore later.

