import '../../../core/formatters/formatters.dart';

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
    this.resources = const ResourceOccupancySnapshot(),
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
  final ResourceOccupancySnapshot resources;
  final List<OwnerReservation> upcoming;

  /// Occupied / (available + occupied) among bookable resources.
  double? get occupancyPercent {
    final bookable = resources.available + resources.occupied;
    if (bookable <= 0) return null;
    return (resources.occupied / bookable) * 100;
  }

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
    final resourcesRaw = json['resources'];

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
      resources: resourcesRaw is Map
          ? ResourceOccupancySnapshot.fromJson(
              Map<String, dynamic>.from(resourcesRaw),
            )
          : const ResourceOccupancySnapshot(),
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

class ResourceOccupancySnapshot {
  const ResourceOccupancySnapshot({
    this.total = 0,
    this.available = 0,
    this.occupied = 0,
    this.maintenance = 0,
    this.inactive = 0,
  });

  final int total;
  final int available;
  final int occupied;
  final int maintenance;
  final int inactive;

  factory ResourceOccupancySnapshot.fromJson(Map<String, dynamic> json) {
    return ResourceOccupancySnapshot(
      total: (json['total'] as num?)?.toInt() ?? 0,
      available: (json['available'] as num?)?.toInt() ?? 0,
      occupied: (json['occupied'] as num?)?.toInt() ?? 0,
      maintenance: (json['maintenance'] as num?)?.toInt() ?? 0,
      inactive: (json['inactive'] as num?)?.toInt() ?? 0,
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
    this.paymentStatus,
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
  final String? paymentStatus;

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
  bool get canMarkPaid =>
      paymentStatus == 'pay_at_venue' || paymentStatus == 'unpaid';

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
      totalAmount: (json['total_amount'] as num?)?.toInt(),
      paymentStatus: json['payment_status'] as String?,
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
      categoryName: category is Map
          ? localizedApiString(
              category['localized_name'] ?? category['name'],
            )
          : null,
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

class WorkingHourDay {
  WorkingHourDay({
    required this.weekday,
    this.isClosed = false,
    this.isOpen24h = false,
    this.opensAt = '09:00',
    this.closesAt = '22:00',
  });

  final int weekday;
  bool isClosed;
  bool isOpen24h;
  String opensAt;
  String closesAt;

  factory WorkingHourDay.fromJson(Map<String, dynamic> json) {
    return WorkingHourDay(
      weekday: (json['weekday'] as num?)?.toInt() ?? 1,
      isClosed: json['is_closed'] as bool? ?? false,
      isOpen24h: json['is_open_24h'] as bool? ?? false,
      opensAt: json['opens_at'] as String? ?? '09:00',
      closesAt: json['closes_at'] as String? ?? '22:00',
    );
  }

  Map<String, dynamic> toJson() {
    if (isClosed) {
      return {
        'weekday': weekday,
        'is_closed': true,
        'is_open_24h': false,
        'opens_at': null,
        'closes_at': null,
      };
    }
    if (isOpen24h) {
      return {
        'weekday': weekday,
        'is_closed': false,
        'is_open_24h': true,
        'opens_at': null,
        'closes_at': null,
      };
    }
    return {
      'weekday': weekday,
      'is_closed': false,
      'is_open_24h': false,
      'opens_at': opensAt,
      'closes_at': closesAt,
    };
  }
}

class BusinessProfile {
  const BusinessProfile({
    required this.id,
    required this.name,
    this.phone,
    this.email,
    this.city,
    this.district,
    this.addressLine,
    this.description,
    this.status,
  });

  final String id;
  final String name;
  final String? phone;
  final String? email;
  final String? city;
  final String? district;
  final String? addressLine;
  final String? description;
  final String? status;

  factory BusinessProfile.fromJson(Map<String, dynamic> json) {
    return BusinessProfile(
      id: json['id'] as String,
      name: json['name'] as String? ?? '',
      phone: json['phone'] as String?,
      email: json['email'] as String?,
      city: json['city'] as String?,
      district: json['district'] as String?,
      addressLine: json['address_line'] as String?,
      description: json['description'] as String?,
      status: json['status'] as String?,
    );
  }
}

class OwnerCalendarDay {
  const OwnerCalendarDay({
    required this.date,
    this.resources = const [],
  });

  final String date;
  final List<OwnerCalendarResource> resources;

  factory OwnerCalendarDay.fromJson(Map<String, dynamic> json) {
    final resourcesRaw = json['resources'];
    return OwnerCalendarDay(
      date: json['date'] as String? ?? '',
      resources: resourcesRaw is List
          ? resourcesRaw
              .whereType<Map>()
              .map(
                (e) => OwnerCalendarResource.fromJson(
                  Map<String, dynamic>.from(e),
                ),
              )
              .toList()
          : const [],
    );
  }
}

class OwnerCalendarResource {
  const OwnerCalendarResource({
    required this.id,
    required this.name,
    this.code,
    this.status,
    this.blocks = const [],
  });

  final String id;
  final String name;
  final String? code;
  final String? status;
  final List<OwnerCalendarBlock> blocks;

  factory OwnerCalendarResource.fromJson(Map<String, dynamic> json) {
    final resource = json['resource'] is Map
        ? Map<String, dynamic>.from(json['resource'] as Map)
        : json;
    final blocksRaw = json['blocks'];
    return OwnerCalendarResource(
      id: resource['id'] as String? ?? '',
      name: resource['name'] as String? ?? '',
      code: resource['code'] as String?,
      status: resource['status'] as String?,
      blocks: blocksRaw is List
          ? blocksRaw
              .whereType<Map>()
              .map(
                (e) => OwnerCalendarBlock.fromJson(
                  Map<String, dynamic>.from(e),
                ),
              )
              .toList()
          : const [],
    );
  }
}

class OwnerCalendarBlock {
  const OwnerCalendarBlock({
    required this.type,
    required this.startTime,
    required this.endTime,
    this.label,
    this.reservationId,
    this.reservationNumber,
    this.reservationStatus,
    this.customerName,
    this.customerPhone,
  });

  final String type;
  final String startTime;
  final String endTime;
  final String? label;
  final String? reservationId;
  final String? reservationNumber;
  final String? reservationStatus;
  final String? customerName;
  final String? customerPhone;

  bool get isBookedLike =>
      type == 'booked' ||
      type == 'active' ||
      type == 'completed' ||
      type == 'no_show' ||
      type == 'cancelled';

  factory OwnerCalendarBlock.fromJson(Map<String, dynamic> json) {
    final reservation = json['reservation'] is Map
        ? Map<String, dynamic>.from(json['reservation'] as Map)
        : null;
    return OwnerCalendarBlock(
      type: json['type'] as String? ?? 'other',
      startTime: json['start_time'] as String? ?? '',
      endTime: json['end_time'] as String? ?? '',
      label: json['label'] as String?,
      reservationId: reservation?['id'] as String?,
      reservationNumber: reservation?['reservation_number'] as String?,
      reservationStatus: reservation?['status'] as String?,
      customerName: reservation?['customer_name'] as String?,
      customerPhone: reservation?['customer_phone'] as String?,
    );
  }
}

class StaffMember {
  const StaffMember({
    required this.id,
    required this.memberRole,
    this.jobTitle,
    this.status,
    this.userName,
    this.userPhone,
  });

  final String id;
  final String memberRole;
  final String? jobTitle;
  final String? status;
  final String? userName;
  final String? userPhone;

  bool get isOwner => memberRole == 'owner';

  factory StaffMember.fromJson(Map<String, dynamic> json) {
    final user = json['user'] is Map
        ? Map<String, dynamic>.from(json['user'] as Map)
        : null;
    return StaffMember(
      id: json['id'] as String,
      memberRole: json['member_role'] as String? ?? 'staff',
      jobTitle: json['job_title'] as String?,
      status: json['status'] as String?,
      userName: user?['name'] as String?,
      userPhone: user?['phone'] as String?,
    );
  }
}

class StaffInvitation {
  const StaffInvitation({
    required this.id,
    required this.phone,
    required this.memberRole,
    required this.status,
    this.expiresAt,
  });

  final String id;
  final String phone;
  final String memberRole;
  final String status;
  final String? expiresAt;

  factory StaffInvitation.fromJson(Map<String, dynamic> json) {
    return StaffInvitation(
      id: json['id'] as String,
      phone: json['phone'] as String? ?? '',
      memberRole: json['member_role'] as String? ?? 'staff',
      status: json['status'] as String? ?? '',
      expiresAt: json['expires_at'] as String?,
    );
  }
}

