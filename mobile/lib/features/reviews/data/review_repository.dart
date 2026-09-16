import '../../../core/network/api_client.dart';

class ReviewItem {
  const ReviewItem({
    required this.id,
    required this.rating,
    this.title,
    this.body,
    this.authorName,
    this.createdAt,
    this.businessResponse,
  });

  final String id;
  final int rating;
  final String? title;
  final String? body;
  final String? authorName;
  final String? createdAt;
  final String? businessResponse;

  factory ReviewItem.fromJson(Map<String, dynamic> json) {
    final author = json['author'];
    final response = json['business_response'];
    return ReviewItem(
      id: json['id'] as String,
      rating: (json['rating'] as num?)?.toInt() ?? 0,
      title: json['title'] as String?,
      body: json['body'] as String?,
      authorName: author is Map ? author['name'] as String? : null,
      createdAt: json['created_at'] as String?,
      businessResponse:
          response is Map ? response['body'] as String? : null,
    );
  }
}

class ReviewListResult {
  const ReviewListResult({
    required this.items,
    this.average,
    this.count = 0,
  });

  final List<ReviewItem> items;
  final double? average;
  final int count;
}

class ReviewRepository {
  ReviewRepository(this._api);

  final ApiClient _api;

  Future<ReviewListResult> forBusiness(String businessId) async {
    final response = await _api.get<Map<String, dynamic>>(
      '/businesses/$businessId/reviews',
      queryParameters: {'per_page': 20, 'sort': 'newest'},
    );
    final data = response.data?['data'];
    if (data is! Map) {
      return const ReviewListResult(items: []);
    }
    final items = data['items'];
    final rating = data['rating'];
    return ReviewListResult(
      items: items is List
          ? items
              .whereType<Map>()
              .map((e) => ReviewItem.fromJson(Map<String, dynamic>.from(e)))
              .toList()
          : const [],
      average: rating is Map && rating['average'] != null
          ? (rating['average'] as num).toDouble()
          : null,
      count: rating is Map
          ? ((rating['count'] as num?)?.toInt() ?? 0)
          : 0,
    );
  }

  Future<void> createForReservation({
    required String reservationId,
    required int rating,
    String? body,
    String? title,
  }) async {
    await _api.post<Map<String, dynamic>>(
      '/me/reservations/$reservationId/review',
      data: {
        'rating': rating,
        if (title != null && title.trim().isNotEmpty) 'title': title.trim(),
        if (body != null && body.trim().isNotEmpty) 'body': body.trim(),
      },
    );
  }
}
