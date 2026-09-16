import '../../../core/network/api_client.dart';

class ReservationRules {
  const ReservationRules({
    this.minDurationMinutes,
    this.maxDurationMinutes,
    this.cancellationDeadlineMinutes,
    this.noShowGraceMinutes,
    this.customerCanCancel = true,
    this.autoConfirm = false,
  });

  final int? minDurationMinutes;
  final int? maxDurationMinutes;
  final int? cancellationDeadlineMinutes;
  final int? noShowGraceMinutes;
  final bool customerCanCancel;
  final bool autoConfirm;

  factory ReservationRules.fromPublicJson(Map<String, dynamic> json) {
    return ReservationRules(
      minDurationMinutes: (json['minimum_duration_minutes'] as num?)?.toInt(),
      maxDurationMinutes: (json['maximum_duration_minutes'] as num?)?.toInt(),
      cancellationDeadlineMinutes:
          (json['cancellation_deadline_minutes'] as num?)?.toInt(),
      noShowGraceMinutes: (json['no_show_grace_minutes'] as num?)?.toInt(),
      customerCanCancel: json['customer_can_cancel'] as bool? ?? true,
      autoConfirm: json['auto_confirm'] as bool? ?? false,
    );
  }

  factory ReservationRules.fromManageJson(Map<String, dynamic> json) {
    return ReservationRules(
      minDurationMinutes: (json['min_duration_minutes'] as num?)?.toInt(),
      maxDurationMinutes: (json['max_duration_minutes'] as num?)?.toInt(),
      cancellationDeadlineMinutes:
          (json['cancellation_deadline_minutes'] as num?)?.toInt(),
      noShowGraceMinutes: (json['no_show_grace_minutes'] as num?)?.toInt(),
      customerCanCancel: json['customer_can_cancel'] as bool? ?? true,
      autoConfirm: json['auto_confirm_reservations'] as bool? ??
          json['confirmation_mode'] == 'instant',
    );
  }
}

class ReservationSettingsRepository {
  ReservationSettingsRepository(this._api);

  final ApiClient _api;

  Future<ReservationRules> publicRules(String businessId) async {
    final response = await _api.get<Map<String, dynamic>>(
      '/businesses/$businessId/reservation-rules',
    );
    final data = response.data?['data'];
    if (data is! Map) {
      return const ReservationRules();
    }
    return ReservationRules.fromPublicJson(Map<String, dynamic>.from(data));
  }

  Future<ReservationRules> manageSettings(String businessId) async {
    final response = await _api.get<Map<String, dynamic>>(
      '/manage/businesses/$businessId/reservation-settings',
    );
    final data = response.data?['data'];
    if (data is! Map) {
      return const ReservationRules();
    }
    return ReservationRules.fromManageJson(Map<String, dynamic>.from(data));
  }

  Future<ReservationRules> updateManageSettings({
    required String businessId,
    required int minDurationMinutes,
    required int cancellationDeadlineMinutes,
    required int noShowGraceMinutes,
  }) async {
    final response = await _api.put<Map<String, dynamic>>(
      '/manage/businesses/$businessId/reservation-settings',
      data: {
        'min_duration_minutes': minDurationMinutes,
        'cancellation_deadline_minutes': cancellationDeadlineMinutes,
        'no_show_grace_minutes': noShowGraceMinutes,
      },
    );
    final data = response.data?['data'];
    if (data is! Map) {
      return const ReservationRules();
    }
    return ReservationRules.fromManageJson(Map<String, dynamic>.from(data));
  }
}
