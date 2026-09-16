import '../../../core/formatters/formatters.dart';

class ReservationSummary {
  const ReservationSummary({
    required this.id,
    required this.reservationNumber,
    required this.status,
    this.businessId,
    this.businessName,
    this.resourceName,
    this.resourceCode,
    this.startAt,
    this.endAt,
    this.date,
    this.startTime,
    this.endTime,
    this.totalAmount,
    this.currency = 'UZS',
    this.paymentStatus,
    this.discountAmount,
    this.promoCode,
  });

  final String id;
  final String reservationNumber;
  final String status;
  final String? businessId;
  final String? businessName;
  final String? resourceName;
  final String? resourceCode;
  final String? startAt;
  final String? endAt;
  final String? date;
  final String? startTime;
  final String? endTime;
  final int? totalAmount;
  final String currency;
  final String? paymentStatus;
  final int? discountAmount;
  final String? promoCode;

  bool get canCancel =>
      status == 'pending' || status == 'confirmed';

  bool get canReview => status == 'completed';

  bool get canPayOnline =>
      (paymentStatus == 'pay_at_venue' || paymentStatus == 'unpaid') &&
      (status == 'pending' || status == 'confirmed') &&
      (totalAmount ?? 0) > 0;

  String get scheduleLabel {
    if (date != null && startTime != null && endTime != null) {
      return '$date · $startTime–$endTime';
    }
    return startAt ?? '—';
  }

  factory ReservationSummary.fromJson(Map<String, dynamic> json) {
    final business = json['business'];
    final resource = json['resource'];
    final promo = json['promo'];
    return ReservationSummary(
      id: json['id'] as String,
      reservationNumber:
          json['reservation_number'] as String? ?? json['id'] as String,
      status: json['status'] as String? ?? 'unknown',
      businessId: business is Map ? business['id'] as String? : null,
      businessName: business is Map ? business['name'] as String? : null,
      resourceName: resource is Map ? resource['name'] as String? : null,
      resourceCode: resource is Map ? resource['code'] as String? : null,
      startAt: json['start_at'] as String?,
      endAt: json['end_at'] as String?,
      date: json['date'] as String?,
      startTime: json['start_time'] as String?,
      endTime: json['end_time'] as String?,
      totalAmount: asApiInt(json['total_amount']),
      currency: json['currency'] as String? ?? 'UZS',
      paymentStatus: json['payment_status'] as String?,
      discountAmount: asApiInt(json['discount_amount']),
      promoCode: promo is Map ? promo['code'] as String? : null,
    );
  }
}

class AvailabilityResourceRow {
  const AvailabilityResourceRow({
    required this.id,
    required this.name,
    required this.status,
    this.code,
    this.reason,
    this.hourlyRateAmount,
  });

  final String id;
  final String name;
  final String status;
  final String? code;
  final String? reason;
  final int? hourlyRateAmount;

  bool get isAvailable => status == 'available';

  factory AvailabilityResourceRow.fromJson(Map<String, dynamic> json) {
    return AvailabilityResourceRow(
      id: json['id'] as String,
      name: json['name'] as String? ?? '',
      code: json['code'] as String?,
      status: json['status'] as String? ?? 'unavailable',
      reason: json['reason'] as String?,
      hourlyRateAmount:
          asApiInt(json['hourly_rate_amount']) ?? asApiInt(json['price']),
    );
  }
}
