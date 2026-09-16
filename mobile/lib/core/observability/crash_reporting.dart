import 'package:sentry_flutter/sentry_flutter.dart';

/// Crash / error reporting facade.
///
/// Sentry is enabled when [dsn] is non-empty (pass `--dart-define=SENTRY_DSN=...`).
/// Firebase Crashlytics can be wired later behind the same [captureException] API.
class CrashReporting {
  CrashReporting._();

  static const String dsn = String.fromEnvironment('SENTRY_DSN');

  static bool get isEnabled => dsn.isNotEmpty;

  static Future<void> init(Future<void> Function() appRunner) async {
    if (!isEnabled) {
      await appRunner();
      return;
    }

    await SentryFlutter.init(
      (options) {
        options.dsn = dsn;
        options.tracesSampleRate = 0.1;
        options.environment = const String.fromEnvironment(
          'SENTRY_ENVIRONMENT',
          defaultValue: 'mobile',
        );
      },
      appRunner: appRunner,
    );
  }

  static Future<void> captureException(
    Object error, {
    StackTrace? stackTrace,
  }) async {
    if (!isEnabled) return;
    await Sentry.captureException(error, stackTrace: stackTrace);
  }
}
