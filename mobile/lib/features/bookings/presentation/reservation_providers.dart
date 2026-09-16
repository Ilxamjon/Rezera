import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../auth/presentation/session_controller.dart';
import '../data/reservation_models.dart';
import '../data/reservation_repository.dart';

final reservationRepositoryProvider = Provider<ReservationRepository>((ref) {
  return ReservationRepository(ref.watch(apiClientProvider));
});

final myReservationsProvider =
    FutureProvider.autoDispose<List<ReservationSummary>>((ref) async {
  final session = ref.watch(sessionControllerProvider);
  if (!session.isAuthenticated) return const [];
  return ref.watch(reservationRepositoryProvider).mine();
});
