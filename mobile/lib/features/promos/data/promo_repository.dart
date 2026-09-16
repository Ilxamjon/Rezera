import '../../../core/network/api_client.dart';

class PromoValidation {
  const PromoValidation({
    required this.valid,
    required this.subtotal,
    required this.total,
    this.currency = 'UZS',
    this.code,
    this.discountType,
    this.discountValue,
    this.discountAmount,
    this.reason,
  });

  final bool valid;
  final int subtotal;
  final int total;
  final String currency;
  final String? code;
  final String? discountType;
  final int? discountValue;
  final int? discountAmount;
  final String? reason;

  factory PromoValidation.fromJson(Map<String, dynamic> json) {
    return PromoValidation(
      valid: json['valid'] as bool? ?? false,
      subtotal: (json['subtotal'] as num?)?.toInt() ?? 0,
      total: (json['total'] as num?)?.toInt() ?? 0,
      currency: json['currency'] as String? ?? 'UZS',
      code: json['code'] as String?,
      discountType: json['discount_type'] as String?,
      discountValue: (json['discount_value'] as num?)?.toInt(),
      discountAmount: (json['discount_amount'] as num?)?.toInt(),
      reason: json['reason'] as String?,
    );
  }
}

class PromoCodeItem {
  const PromoCodeItem({
    required this.id,
    required this.code,
    required this.name,
    required this.discountType,
    required this.discountValue,
    this.isActive = true,
  });

  final String id;
  final String code;
  final String name;
  final String discountType;
  final int discountValue;
  final bool isActive;

  factory PromoCodeItem.fromJson(Map<String, dynamic> json) {
    return PromoCodeItem(
      id: json['id'] as String,
      code: json['code'] as String? ?? '',
      name: json['name'] as String? ?? '',
      discountType: json['discount_type'] as String? ?? 'percentage',
      discountValue: (json['discount_value'] as num?)?.toInt() ?? 0,
      isActive: json['is_active'] as bool? ?? true,
    );
  }
}

class PromoRepository {
  PromoRepository(this._api);

  final ApiClient _api;

  Future<PromoValidation> validate({
    required String businessId,
    required String code,
    required String resourceId,
    required String date,
    required String startTime,
    required String endTime,
  }) async {
    final response = await _api.post<Map<String, dynamic>>(
      '/businesses/$businessId/promo-codes/validate',
      data: {
        'code': code,
        'resource_id': resourceId,
        'date': date,
        'start_time': startTime,
        'end_time': endTime,
      },
    );
    return PromoValidation.fromJson(_unwrap(response.data));
  }

  Future<List<PromoCodeItem>> list(String businessId) async {
    final response = await _api.get<Map<String, dynamic>>(
      '/manage/businesses/$businessId/promo-codes',
      queryParameters: {'per_page': 50},
    );
    return _items(response.data).map(PromoCodeItem.fromJson).toList();
  }

  Future<PromoCodeItem> create({
    required String businessId,
    required String code,
    required String name,
    required String discountType,
    required int discountValue,
    String? currency,
  }) async {
    final response = await _api.post<Map<String, dynamic>>(
      '/manage/businesses/$businessId/promo-codes',
      data: {
        'code': code,
        'name': name,
        'discount_type': discountType,
        'discount_value': discountValue,
        if (discountType == 'fixed') 'currency': currency ?? 'UZS',
        'is_active': true,
      },
    );
    return PromoCodeItem.fromJson(_unwrap(response.data));
  }

  Future<void> delete({
    required String businessId,
    required String promoId,
  }) async {
    await _api.delete<Map<String, dynamic>>(
      '/manage/businesses/$businessId/promo-codes/$promoId',
    );
  }

  Map<String, dynamic> _unwrap(Map<String, dynamic>? body) {
    if (body == null) throw StateError('Empty API body');
    final data = body['data'];
    if (data is Map<String, dynamic>) return data;
    if (data is Map) return Map<String, dynamic>.from(data);
    return body;
  }

  List<Map<String, dynamic>> _items(Map<String, dynamic>? body) {
    final data = body?['data'];
    final items = data is Map ? data['items'] : (data is List ? data : null);
    if (items is! List) return const [];
    return items
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();
  }
}
