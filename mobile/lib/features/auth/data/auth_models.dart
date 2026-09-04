class BusinessMembership {
  const BusinessMembership({
    required this.businessId,
    required this.memberRole,
    this.businessName,
    this.jobTitle,
    this.status = 'active',
  });

  final String businessId;
  final String memberRole;
  final String? businessName;
  final String? jobTitle;
  final String status;

  factory BusinessMembership.fromJson(Map<String, dynamic> json) {
    return BusinessMembership(
      businessId: json['business_id']?.toString() ?? '',
      businessName: json['business_name'] as String?,
      memberRole: json['member_role'] as String? ?? 'staff',
      jobTitle: json['job_title'] as String?,
      status: json['status'] as String? ?? 'active',
    );
  }
}

class UserSession {
  const UserSession({
    required this.id,
    required this.name,
    required this.phone,
    this.email,
    this.locale = 'ru',
    this.platformRole = 'user',
    this.hasBusinessMemberships = false,
    this.memberships = const [],
  });

  final String id;
  final String name;
  final String phone;
  final String? email;
  final String locale;
  final String platformRole;
  final bool hasBusinessMemberships;
  final List<BusinessMembership> memberships;

  bool get canUseOwnerMode =>
      hasBusinessMemberships || memberships.isNotEmpty;

  factory UserSession.fromJson(Map<String, dynamic> json) {
    final caps = json['capabilities'];
    final rawMemberships = json['business_memberships'];
    final memberships = rawMemberships is List
        ? rawMemberships
            .whereType<Map>()
            .map((e) => BusinessMembership.fromJson(Map<String, dynamic>.from(e)))
            .toList()
        : <BusinessMembership>[];

    return UserSession(
      id: json['id']?.toString() ?? '',
      name: json['name'] as String? ?? '',
      phone: json['phone'] as String? ?? '',
      email: json['email'] as String?,
      locale: json['locale'] as String? ?? 'ru',
      platformRole: json['platform_role'] as String? ?? 'user',
      hasBusinessMemberships: (caps is Map &&
              caps['has_business_memberships'] == true) ||
          memberships.isNotEmpty,
      memberships: memberships,
    );
  }
}

class AuthTokenPayload {
  const AuthTokenPayload({
    required this.user,
    required this.token,
    this.tokenType = 'Bearer',
  });

  final UserSession user;
  final String token;
  final String tokenType;

  factory AuthTokenPayload.fromJson(Map<String, dynamic> json) {
    return AuthTokenPayload(
      user: UserSession.fromJson(
        Map<String, dynamic>.from(json['user'] as Map),
      ),
      token: json['token'] as String,
      tokenType: json['token_type'] as String? ?? 'Bearer',
    );
  }
}
