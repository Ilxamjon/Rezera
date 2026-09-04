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
