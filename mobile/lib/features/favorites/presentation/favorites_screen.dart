import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/formatters/formatters.dart';
import '../../../core/navigation/rezera_nav.dart';
import '../../../core/theme/rezera_theme.dart';
import '../../../core/widgets/rezera_error_view.dart';
import '../../auth/presentation/session_controller.dart';
import 'favorite_providers.dart';
import 'favorite_toggle_button.dart';

class FavoritesScreen extends ConsumerWidget {
  const FavoritesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(sessionControllerProvider);
    final async = ref.watch(favoriteBusinessesProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Text('favorites_title'.tr()),
        leading: rezeraBackButton(context, fallback: '/profile'),
      ),
      body: !session.isAuthenticated
          ? Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      'favorites_login_required'.tr(),
                      textAlign: TextAlign.center,
                      style: theme.textTheme.titleMedium,
                    ),
                    const SizedBox(height: 16),
                    FilledButton(
                      onPressed: () => context.push('/login'),
                      child: Text('auth_login_action'.tr()),
                    ),
                  ],
                ),
              ),
            )
          : async.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (e, _) => RezeraErrorView(
                error: e,
                onRetry: () => ref.invalidate(favoriteBusinessesProvider),
              ),
              data: (items) {
                if (items.isEmpty) {
                  return Center(child: Text('favorites_empty'.tr()));
                }
                return RefreshIndicator(
                  onRefresh: () async {
                    ref.invalidate(favoriteBusinessesProvider);
                  },
                  child: ListView.separated(
                    padding: const EdgeInsets.all(20),
                    itemCount: items.length,
                    separatorBuilder: (_, index) => const SizedBox(height: 10),
                    itemBuilder: (context, index) {
                      final item = items[index];
                      return Material(
                        color: Colors.white.withValues(alpha: 0.78),
                        borderRadius: BorderRadius.circular(16),
                        child: InkWell(
                          borderRadius: BorderRadius.circular(16),
                          onTap: () => context.push('/business/${item.id}'),
                          child: Padding(
                            padding: const EdgeInsets.fromLTRB(14, 12, 4, 12),
                            child: Row(
                              children: [
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        item.name,
                                        style: theme.textTheme.titleMedium
                                            ?.copyWith(
                                          fontWeight: FontWeight.w700,
                                        ),
                                      ),
                                      if (item.district != null ||
                                          item.city != null)
                                        Text(
                                          [
                                            if (item.district != null)
                                              item.district,
                                            if (item.city != null) item.city,
                                          ].join(', '),
                                          style: theme.textTheme.bodyMedium
                                              ?.copyWith(
                                            color: RezeraColors.slate
                                                .withValues(alpha: 0.75),
                                          ),
                                        ),
                                      if (item.priceFrom != null)
                                        Text(
                                          'home_from_price'.tr(
                                            args: [
                                              MoneyFormat.uzs(item.priceFrom),
                                            ],
                                          ),
                                          style: theme.textTheme.labelLarge,
                                        ),
                                    ],
                                  ),
                                ),
                                FavoriteToggleButton(
                                  businessId: item.id,
                                  isFavorite: item.isFavorite ?? true,
                                  onChanged: (_) => ref.invalidate(
                                    favoriteBusinessesProvider,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      );
                    },
                  ),
                );
              },
            ),
    );
  }
}
