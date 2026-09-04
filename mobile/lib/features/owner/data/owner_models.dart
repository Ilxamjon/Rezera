class ManagedBusiness {
  const ManagedBusiness({
    required this.id,
    required this.name,
    this.city,
    this.status,
  });

  final String id;
  final String name;
  final String? city;
  final String? status;

  factory ManagedBusiness.fromJson(Map<String, dynamic> json) {
    return ManagedBusiness(
      id: json['id'] as String,
      name: json['name'] as String? ?? '',
      city: json['city'] as String?,
      status: json['status'] as String?,
    );
  }
}

class OwnerDashboard {
  const OwnerDashboard({
    required this.businessId,
    required this.businessName,
    required this.date,
    required this.todayTotal,
    required this.todayPending,
    required this.todayConfirmed,
    required this.todayCheckedIn,
    required this.upcomingCount,
    required this.activeSessions,
    required this.overdueCheckIns,
    this.revenuePaid,
    this.upcoming = const [],
  });

  final String businessId;
  final String businessName;
  final String date;
  final int todayTotal;
  final int todayPending;
  final int todayConfirmed;
  final int todayCheckedIn;
  final int upcomingCount;
  final int activeSessions;
  final int overdueCheckIns;
  final int? revenuePaid;
  final List<OwnerReservation> upcoming;

  factory OwnerDashboard.fromJson(Map<String, dynamic> json) {
    final business = json['business'];
    final today = json['today'] is Map
        ? Map<String, dynamic>.from(json['today'] as Map)
        : <String, dynamic>{};
    final operations = json['operations'] is Map
        ? Map<String, dynamic>.from(json['operations'] as Map)
        : <String, dynamic>{};
    final revenue = json['revenue_today'];
    final upcomingRaw = json['upcoming_reservations'];

    return OwnerDashboard(
      businessId: business is Map ? business['id'] as String? ?? '' : '',
      businessName: business is Map ? business['name'] as String? ?? '' : '',
      date: json['date'] as String? ?? '',
      todayTotal: (today['total'] as num?)?.toInt() ?? 0,
      todayPending: (today['pending'] as num?)?.toInt() ?? 0,
      todayConfirmed: (today['confirmed'] as num?)?.toInt() ?? 0,
      todayCheckedIn: (today['checked_in'] as num?)?.toInt() ?? 0,
      upcomingCount: (json['upcoming'] as num?)?.toInt() ?? 0,
      activeSessions: (operations['active_sessions'] as num?)?.toInt() ??
          (today['active_sessions'] as num?)?.toInt() ??
          0,
      overdueCheckIns: (operations['overdue_check_ins'] as num?)?.toInt() ?? 0,
      revenuePaid: revenue is Map
          ? (revenue['amount_paid'] as num?)?.toInt()
          : null,
      upcoming: upcomingRaw is List
          ? upcomingRaw
              .whereType<Map>()
              .map(
                (e) => OwnerReservation.fromJson(Map<String, dynamic>.from(e)),
              )
              .toList()
          : const [],
    );
  }
}

class OwnerReservation {
  const OwnerReservation({
    required this.id,
    required this.reservationNumber,
    required this.status,
    this.resourceName,
    this.resourceCode,
    this.customerName,
    this.customerPhone,
    this.date,
    this.startTime,
    this.endTime,
    this.totalAmount,
  });

  final String id;
  final String reservationNumber;
  final String status;
  final String? resourceName;
  final String? resourceCode;
  final String? customerName;
  final String? customerPhone;
  final String? date;
  final String? startTime;
  final String? endTime;
  final int? totalAmount;

  String get scheduleLabel {
    if (date != null && startTime != null && endTime != null) {
      return '$date · $startTime–$endTime';
    }
    return [startTime, endTime].whereType<String>().join('–');
  }

  bool get canConfirm => status == 'pending';
  bool get canReject => status == 'pending';
  bool get canCheckIn => status == 'confirmed';
  bool get canNoShow => status == 'confirmed';
  bool get canCheckOut => status == 'checked_in';

  factory OwnerReservation.fromJson(Map<String, dynamic> json) {
    final resource = json['resource'];
    final customer = json['customer'];
    return OwnerReservation(
      id: json['id'] as String,
      reservationNumber:
          json['reservation_number'] as String? ?? json['id'] as String,
      status: json['status'] as String? ?? 'unknown',
      resourceName: resource is Map ? resource['name'] as String? : null,
      resourceCode: resource is Map ? resource['code'] as String? : null,
      customerName: customer is Map
          ? customer['name'] as String?
          : json['customer_name_snapshot'] as String?,
      customerPhone: customer is Map
          ? customer['phone'] as String?
          : json['customer_phone_snapshot'] as String?,
      date: json['date'] as String?,
      startTime: json['start_time'] as String?,
      endTime: json['end_time'] as String?,
      totalAmount: json['total_amount'] as int?,
    );
  }
}

class OwnerResource {
  const OwnerResource({
    required this.id,
    required this.name,
    this.code,
    this.status,
    this.price,
    this.currency = 'UZS',
    this.resourceType = 'pc',
    this.resourceCategoryId,
    this.categoryName,
    this.description,
    this.capacity = 1,
  });

  final String id;
  final String name;
  final String? code;
  final String? status;
  final int? price;
  final String currency;
  final String resourceType;
  final String? resourceCategoryId;
  final String? categoryName;
  final String? description;
  final int capacity;

  factory OwnerResource.fromJson(Map<String, dynamic> json) {
    final category = json['category'];
    return OwnerResource(
      id: json['id'] as String,
      name: json['name'] as String? ?? '',
      code: json['code'] as String?,
      status: json['status'] as String?,
      price: json['price'] as int? ?? json['hourly_rate_amount'] as int?,
      currency: json['currency'] as String? ?? 'UZS',
      resourceType: json['resource_type'] as String? ?? 'pc',
      resourceCategoryId: json['resource_category_id'] as String?,
      categoryName: category is Map ? category['name'] as String? : null,
      description: json['description'] as String?,
      capacity: (json['capacity'] as num?)?.toInt() ?? 1,
    );
  }
}

class ResourceCategoryOption {
  const ResourceCategoryOption({required this.id, required this.name});

  final String id;
  final String name;

  factory ResourceCategoryOption.fromJson(Map<String, dynamic> json) {
    return ResourceCategoryOption(
      id: json['id'] as String,
      name: json['name'] as String? ?? '',
    );
  }
}

class ResourceQrPayload {
  const ResourceQrPayload({
    required this.resourceId,
    required this.token,
    required this.qrPayload,
  });

  final String resourceId;
  final String token;
  final String qrPayload;

  factory ResourceQrPayload.fromJson(Map<String, dynamic> json) {
    return ResourceQrPayload(
      resourceId: json['resource_id'] as String,
      token: json['token'] as String,
      qrPayload: json['qr_payload'] as String? ??
          'rezera://check-in/${json['token']}',
    );
  }
}

