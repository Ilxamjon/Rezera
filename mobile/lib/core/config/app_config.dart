/// Compile-time API config. Override with:
/// `flutter run --dart-define=API_BASE_URL=https://api.example.uz`
/// Release APKs must use HTTPS (cleartext disabled on Android release).
class AppConfig {
  AppConfig._();

  /// Android emulator → host machine. Physical device: use LAN IP (debug only).
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000',
  );

  static const String apiPrefix = '/api/v1';

  static String get apiRoot => '$apiBaseUrl$apiPrefix';

  static const String appVersion = '1.0.0';
}
