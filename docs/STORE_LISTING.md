# Store listing checklist (Play / App Store)

## Assets (repo)

| Asset | Path |
|---|---|
| Adaptive Android icon | `mobile/android/app/src/main/res/mipmap-*/` |
| Play 512 | `mobile/assets/branding/play_store_512.png` |
| Master 1024 | `mobile/assets/branding/rezera_icon_1024.png` |
| In-app logo | `mobile/assets/branding/logo.png` |

## Required before submit

- [ ] Privacy policy URL live: `https://<APP_URL>/privacy`
- [ ] Release signing (not debug keystore) — see `mobile/README.md`
- [ ] Production API HTTPS only (`build-release-apk.ps1`)
- [ ] Screenshots: home, book flow, bookings, owner today (phone + 7" if needed)
- [ ] Short description + full description (uz/ru/en as needed)
- [ ] Content rating questionnaire completed
- [ ] Data safety / App Privacy: phone, bookings, optional location, crash logs

## Deep links

Custom scheme registered for notifications / QR:

- Android: `rezera://` intent-filter in `AndroidManifest.xml`
- iOS: `CFBundleURLSchemes` = `rezera` in `Info.plist`

Examples:

- `rezera://bookings`
- `rezera://reservation/{id}`
- `rezera://owner/today`
- `rezera://check-in/{token}` (owner QR scanner still parses payload)

## Crash reporting

```powershell
flutter run --dart-define=API_BASE_URL=https://api.example.uz --dart-define=SENTRY_DSN=https://...@o....ingest.sentry.io/...
```

Firebase Crashlytics remains optional (add FlutterFire when `google-services.json` is ready).
