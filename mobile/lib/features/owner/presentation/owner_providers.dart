import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../auth/presentation/session_controller.dart';
import '../data/owner_models.dart';
import '../data/owner_repository.dart';

final ownerRepositoryProvider = Provider<OwnerRepository>((ref) {
  return OwnerRepository(ref.watch(apiClientProvider));
});

final selectedBusinessIdProvider = Provider<String?>((ref) {
  return ref.watch(sessionControllerProvider).selectedBusinessId;
});

final ownerDashboardProvider =
    FutureProvider.autoDispose<OwnerDashboard>((ref) async {
  final businessId = ref.watch(selectedBusinessIdProvider);
  if (businessId == null) {
    throw StateError('No business selected');
  }
  return ref.watch(ownerRepositoryProvider).dashboard(businessId);
});

final ownerTodayProvider =
    FutureProvider.autoDispose<List<OwnerReservation>>((ref) async {
  final businessId = ref.watch(selectedBusinessIdProvider);
  if (businessId == null) return const [];
  return ref.watch(ownerRepositoryProvider).todayReservations(businessId);
});

final ownerResourcesProvider =
    FutureProvider.autoDispose<List<OwnerResource>>((ref) async {
  final businessId = ref.watch(selectedBusinessIdProvider);
  if (businessId == null) return const [];
  return ref.watch(ownerRepositoryProvider).resources(businessId);
});
