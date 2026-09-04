import '../../../core/network/api_client.dart';

class BusinessCategoryOption {
  const BusinessCategoryOption({
    required this.id,
    required this.slug,
    required this.name,
  });

  final String id;
  final String slug;
  final String name;

  factory BusinessCategoryOption.fromJson(Map<String, dynamic> json) {
    return BusinessCategoryOption(
      id: json['id'] as String,
      slug: json['slug'] as String? ?? '',
      name: json['localized_name'] as String? ??
          (json['name'] is Map
              ? (json['name']['ru'] as String? ??
                  json['name']['uz'] as String? ??
                  '')
              : json['name'] as String? ?? ''),
    );
  }
}

class CreatedBusiness {
  const CreatedBusiness({
    required this.id,
    required this.name,
  });

  final String id;
  final String name;

  factory CreatedBusiness.fromJson(Map<String, dynamic> json) {
    return CreatedBusiness(
      id: json['id'] as String,
      name: json['name'] as String? ?? '',
    );
  }
}

class BusinessSetupRepository {
  BusinessSetupRepository(this._api);

  final ApiClient _api;

  Future<List<BusinessCategoryOption>> categories() async {
    final response = await _api.get<Map<String, dynamic>>('/business-categories');
    final data = response.data?['data'];
    if (data is! List) return const [];
    return data
        .whereType<Map>()
        .map(
          (e) => BusinessCategoryOption.fromJson(Map<String, dynamic>.from(e)),
        )
        .toList();
  }

  Future<CreatedBusiness> createBusiness(Map<String, dynamic> payload) async {
    final response = await _api.post<Map<String, dynamic>>(
      '/businesses',
      data: payload,
    );
    return CreatedBusiness.fromJson(_unwrap(response.data));
  }

  Future<void> updateWorkingHours({
    required String businessId,
    required List<Map<String, dynamic>> workingHours,
  }) async {
    await _api.put<Map<String, dynamic>>(
      '/manage/businesses/$businessId/working-hours',
      data: {'working_hours': workingHours},
    );
  }

  Future<void> createResource({
    required String businessId,
    required Map<String, dynamic> payload,
  }) async {
    await _api.post<Map<String, dynamic>>(
      '/manage/businesses/$businessId/resources',
      data: payload,
    );
  }

  Future<void> submitVerification(String businessId) async {
    await _api.post<Map<String, dynamic>>(
      '/manage/businesses/$businessId/verification',
    );
  }

  Map<String, dynamic> _unwrap(Map<String, dynamic>? body) {
    if (body == null) throw StateError('Empty API body');
    final data = body['data'];
    if (data is Map<String, dynamic>) return data;
    if (data is Map) return Map<String, dynamic>.from(data);
    return body;
  }
}
