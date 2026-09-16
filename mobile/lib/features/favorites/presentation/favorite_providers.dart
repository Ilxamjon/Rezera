import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../auth/presentation/session_controller.dart';
import '../../search/data/business_models.dart';
import '../data/favorite_repository.dart';

final favoriteRepositoryProvider = Provider<FavoriteRepository>((ref) {
  return FavoriteRepository(ref.watch(apiClientProvider));
});

final favoriteBusinessesProvider =
    FutureProvider.autoDispose<List<BusinessCard>>((ref) async {
  final session = ref.watch(sessionControllerProvider);
  if (!session.isAuthenticated) return const [];
  return ref.watch(favoriteRepositoryProvider).list();
});
