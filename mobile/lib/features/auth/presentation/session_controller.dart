import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/storage/token_storage.dart';
import '../../notifications/data/device_repository.dart';
import '../data/auth_models.dart';
import '../data/auth_repository.dart';

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepository(ref.watch(apiClientProvider));
});

enum AuthStatus { unknown, authenticated, guest }

enum AppMode { customer, owner }

class SessionState {
  const SessionState({
    required this.status,
    this.user,
    this.mode = AppMode.customer,
    this.selectedBusinessId,
  });

  final AuthStatus status;
  final UserSession? user;
  final AppMode mode;
  final String? selectedBusinessId;

  bool get isAuthenticated => status == AuthStatus.authenticated;
  bool get isOwnerMode => mode == AppMode.owner;

  BusinessMembership? get selectedMembership {
    final list = user?.memberships ?? const <BusinessMembership>[];
    if (list.isEmpty) return null;
    for (final membership in list) {
      if (membership.businessId == selectedBusinessId) {
        return membership;
      }
    }
    return list.first;
  }

  SessionState copyWith({
    AuthStatus? status,
    UserSession? user,
    AppMode? mode,
    String? selectedBusinessId,
    bool clearBusiness = false,
  }) {
    return SessionState(
      status: status ?? this.status,
      user: user ?? this.user,
      mode: mode ?? this.mode,
      selectedBusinessId: clearBusiness
          ? null
          : (selectedBusinessId ?? this.selectedBusinessId),
    );
  }
}

class SessionController extends StateNotifier<SessionState> {
  SessionController(this._ref)
      : super(const SessionState(status: AuthStatus.unknown));

  final Ref _ref;

  AuthRepository get _auth => _ref.read(authRepositoryProvider);
  TokenStorage get _tokens => _ref.read(tokenStorageProvider);

  Future<void> bootstrap() async {
    try {
      final token = await _tokens.readToken().timeout(
        const Duration(seconds: 3),
        onTimeout: () => null,
      );
      if (token == null || token.isEmpty) {
        state = const SessionState(status: AuthStatus.guest);
        return;
      }

      final user = await _auth.me().timeout(const Duration(seconds: 8));
      state = SessionState(
        status: AuthStatus.authenticated,
        user: user,
        selectedBusinessId: user.memberships.isNotEmpty
            ? user.memberships.first.businessId
            : null,
      );
      await _registerDevice();
    } catch (_) {
      try {
        await _tokens.clearToken();
      } catch (_) {}
      state = const SessionState(status: AuthStatus.guest);
    }
  }

  Future<void> applyAuth(AuthTokenPayload payload) async {
    await _tokens.writeToken(payload.token);
    state = SessionState(
      status: AuthStatus.authenticated,
      user: payload.user,
      selectedBusinessId: payload.user.memberships.isNotEmpty
          ? payload.user.memberships.first.businessId
          : null,
    );
    await _registerDevice();
  }

  /// Reload `/auth/me` after creating a business or accepting an invite.
  Future<void> refreshUser({String? selectBusinessId}) async {
    final user = await _auth.me();
    final selected = selectBusinessId ??
        state.selectedBusinessId ??
        (user.memberships.isNotEmpty ? user.memberships.first.businessId : null);
    state = state.copyWith(
      status: AuthStatus.authenticated,
      user: user,
      selectedBusinessId: selected,
    );
  }

  void enterOwnerMode({String? businessId}) {
    final user = state.user;
    if (user == null || !user.canUseOwnerMode) return;
    final id = businessId ??
        state.selectedBusinessId ??
        user.memberships.first.businessId;
    state = state.copyWith(mode: AppMode.owner, selectedBusinessId: id);
  }

  void enterCustomerMode() {
    state = state.copyWith(mode: AppMode.customer);
  }

  void selectBusiness(String businessId) {
    state = state.copyWith(selectedBusinessId: businessId);
  }

  Future<void> logout() async {
    try {
      await _ref.read(deviceRepositoryProvider).deactivate();
    } catch (_) {}
    try {
      await _auth.logout();
    } catch (_) {
      // Still clear local session.
    }
    await _tokens.clearToken();
    state = const SessionState(status: AuthStatus.guest);
  }

  Future<void> _registerDevice() async {
    try {
      await _ref.read(deviceRepositoryProvider).register();
    } catch (_) {}
  }
}

final sessionControllerProvider =
    StateNotifierProvider<SessionController, SessionState>((ref) {
  return SessionController(ref);
});
