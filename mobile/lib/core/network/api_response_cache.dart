import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

/// Short-lived GET response cache for weak networks.
class ApiResponseCache {
  ApiResponseCache(this._prefs);

  final SharedPreferences _prefs;
  static const _prefix = 'api_cache_v1:';

  Future<Map<String, dynamic>?> read(String key, {Duration maxAge = const Duration(minutes: 5)}) async {
    final raw = _prefs.getString('$_prefix$key');
    if (raw == null) return null;
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! Map) return null;
      final map = Map<String, dynamic>.from(decoded);
      final savedAt = DateTime.tryParse(map['saved_at'] as String? ?? '');
      if (savedAt == null) return null;
      if (DateTime.now().difference(savedAt) > maxAge) {
        await _prefs.remove('$_prefix$key');
        return null;
      }
      final body = map['body'];
      if (body is Map) return Map<String, dynamic>.from(body);
    } catch (_) {}
    return null;
  }

  Future<void> write(String key, Map<String, dynamic> body) async {
    await _prefs.setString(
      '$_prefix$key',
      jsonEncode({
        'saved_at': DateTime.now().toIso8601String(),
        'body': body,
      }),
    );
  }

  Future<void> invalidate(String key) => _prefs.remove('$_prefix$key');
}
