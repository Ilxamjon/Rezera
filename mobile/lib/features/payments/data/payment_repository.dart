import '../../../core/network/api_client.dart';

class PaymentItem {
  const PaymentItem({
    required this.id,
    required this.paymentNumber,
    required this.status,
    required this.amount,
    required this.provider,
    this.currency = 'UZS',
    this.paymentUrl,
    this.reservationNumber,
  });

  final String id;
  final String paymentNumber;
  final String status;
  final int amount;
  final String provider;
  final String currency;
  final String? paymentUrl;
  final String? reservationNumber;

  factory PaymentItem.fromJson(Map<String, dynamic> json) {
    return PaymentItem(
      id: json['id'] as String,
      paymentNumber: json['payment_number'] as String? ?? '',
      status: json['status'] as String? ?? '',
      amount: (json['amount'] as num?)?.toInt() ?? 0,
      provider: json['provider'] as String? ?? '',
      currency: json['currency'] as String? ?? 'UZS',
      paymentUrl: json['payment_url'] as String?,
      reservationNumber: json['reservation_number'] as String?,
    );
  }
}

class PaymentRepository {
  PaymentRepository(this._api);

  final ApiClient _api;

  Future<PaymentItem> createForReservation({
    required String reservationId,
    String provider = 'mock',
  }) async {
    final response = await _api.post<Map<String, dynamic>>(
      '/me/reservations/$reservationId/payments',
      data: {'provider': provider},
    );
    return PaymentItem.fromJson(_unwrap(response.data));
  }

  Future<PaymentItem> show(String paymentId, {bool sync = true}) async {
    final response = await _api.get<Map<String, dynamic>>(
      '/me/payments/$paymentId',
      queryParameters: {if (sync) 'sync': 1},
    );
    return PaymentItem.fromJson(_unwrap(response.data));
  }

  Map<String, dynamic> _unwrap(Map<String, dynamic>? body) {
    if (body == null) throw StateError('Empty API body');
    final data = body['data'];
    if (data is Map<String, dynamic>) return data;
    if (data is Map) return Map<String, dynamic>.from(data);
    return body;
  }
}
