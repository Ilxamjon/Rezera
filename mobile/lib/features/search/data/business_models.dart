class BusinessCard {
  const BusinessCard({
    required this.id,
    required this.name,
    this.shortDescription,
    this.coverImageUrl,
    this.categoryName,
    this.city,
    this.district,
    this.priceFrom,
    this.currency = 'UZS',
    this.openNow,
    this.ratingAverage,
    this.ratingCount = 0,
    this.isFavorite,
  });

  final String id;
  final String name;
  final String? shortDescription;
  final String? coverImageUrl;
  final String? categoryName;
  final String? city;
  final String? district;
  final int? priceFrom;
  final String currency;
  final bool? openNow;
  final double? ratingAverage;
  final int ratingCount;
  final bool? isFavorite;

  factory BusinessCard.fromJson(Map<String, dynamic> json) {
    final category = json['category'];
    final rating = json['rating'];
    return BusinessCard(
      id: json['id'] as String,
      name: json['name'] as String? ?? '',
      shortDescription: json['short_description'] as String?,
      coverImageUrl: json['cover_image_url'] as String?,
      categoryName: category is Map ? category['name'] as String? : null,
      city: json['city'] as String?,
      district: json['district'] as String?,
      priceFrom: json['price_from'] as int?,
      currency: json['currency'] as String? ?? 'UZS',
      openNow: json['open_now'] as bool?,
      ratingAverage: rating is Map && rating['average'] != null
          ? (rating['average'] as num).toDouble()
          : null,
      ratingCount: rating is Map ? (rating['count'] as int? ?? 0) : 0,
      isFavorite: json['is_favorite'] as bool?,
    );
  }
}

class BusinessDetail {
  const BusinessDetail({
    required this.id,
    required this.name,
    this.description,
    this.city,
    this.district,
    this.addressLine,
    this.phone,
    this.coverImageUrl,
    this.priceFrom,
    this.openNow,
    this.ratingAverage,
    this.ratingCount = 0,
    this.resources = const [],
  });

  final String id;
  final String name;
  final String? description;
  final String? city;
  final String? district;
  final String? addressLine;
  final String? phone;
  final String? coverImageUrl;
  final int? priceFrom;
  final bool? openNow;
  final double? ratingAverage;
  final int ratingCount;
  final List<PublicResource> resources;

  factory BusinessDetail.fromJson(Map<String, dynamic> json) {
    final rating = json['rating'];
    final resources = json['resources'];
    return BusinessDetail(
      id: json['id'] as String,
      name: json['name'] as String? ?? '',
      description: json['description'] as String?,
      city: json['city'] as String?,
      district: json['district'] as String?,
      addressLine: json['address_line'] as String?,
      phone: json['phone'] as String?,
      coverImageUrl: json['cover_image_url'] as String?,
      priceFrom: json['price_from'] as int?,
      openNow: json['open_now'] as bool?,
      ratingAverage: rating is Map && rating['average'] != null
          ? (rating['average'] as num).toDouble()
          : null,
      ratingCount: rating is Map ? (rating['count'] as int? ?? 0) : 0,
      resources: resources is List
          ? resources
              .whereType<Map>()
              .map((e) => PublicResource.fromJson(Map<String, dynamic>.from(e)))
              .toList()
          : const [],
    );
  }
}

class PublicResource {
  const PublicResource({
    required this.id,
    required this.name,
    this.code,
    this.hourlyRateAmount,
    this.currency = 'UZS',
  });

  final String id;
  final String name;
  final String? code;
  final int? hourlyRateAmount;
  final String currency;

  factory PublicResource.fromJson(Map<String, dynamic> json) {
    return PublicResource(
      id: json['id'] as String,
      name: json['name'] as String? ?? '',
      code: json['code'] as String?,
      hourlyRateAmount: json['hourly_rate_amount'] as int? ??
          json['price'] as int?,
      currency: json['currency'] as String? ?? 'UZS',
    );
  }
}
