import 'package:intl/intl.dart';

abstract final class MoneyFormat {
  static final _uzs = NumberFormat.decimalPattern('ru');

  static String uzs(int? amount) {
    if (amount == null) return '—';
    return '${_uzs.format(amount)} UZS';
  }
}

abstract final class PhoneFormat {
  /// Best-effort normalize to E.164 for Uzbekistan (+998…).
  static String toE164(String raw) {
    final digits = raw.replaceAll(RegExp(r'\D'), '');
    if (digits.startsWith('998') && digits.length >= 12) {
      return '+$digits';
    }
    if (digits.length == 9) {
      return '+998$digits';
    }
    if (digits.startsWith('0') && digits.length == 10) {
      return '+998${digits.substring(1)}';
    }
    if (raw.trim().startsWith('+')) {
      return '+$digits';
    }
    return digits.isEmpty ? raw.trim() : '+$digits';
  }
}

/// API may return a plain string or an i18n map `{uz, ru, ...}`.
String? localizedApiString(dynamic value) {
  if (value == null) return null;
  if (value is String) return value;
  if (value is Map) {
    final map = Map<String, dynamic>.from(value);
    for (final key in ['uz', 'ru', 'en', 'kaa']) {
      final v = map[key];
      if (v is String && v.trim().isNotEmpty) return v;
    }
    for (final v in map.values) {
      if (v is String && v.trim().isNotEmpty) return v;
    }
  }
  return null;
}

int? asApiInt(dynamic value) {
  if (value == null) return null;
  if (value is int) return value;
  if (value is num) return value.toInt();
  if (value is String) return int.tryParse(value);
  return null;
}
