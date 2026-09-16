import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/formatters/formatters.dart';
import '../../../core/navigation/rezera_nav.dart';
import '../../../core/theme/rezera_theme.dart';
import '../../../core/widgets/rezera_error_view.dart';
import '../../favorites/presentation/favorite_toggle_button.dart';
import '../../home/presentation/home_screen.dart';
import '../../reviews/presentation/business_reviews_section.dart';
import '../../search/data/business_models.dart';

final businessDetailProvider =
    FutureProvider.autoDispose.family<BusinessDetail, String>((ref, id) {
  return ref.watch(businessRepositoryProvider).show(id);
});

class BusinessDetailScreen extends ConsumerWidget {
  const BusinessDetailScreen({super.key, required this.businessId});

  final String businessId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(businessDetailProvider(businessId));
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Text('app_name'.tr()),
        leading: rezeraBackButton(context),
        actions: [
          async.maybeWhen(
            data: (business) => FavoriteToggleButton(
              businessId: business.id,
              isFavorite: business.isFavorite ?? false,
              onChanged: (_) =>
                  ref.invalidate(businessDetailProvider(businessId)),
            ),
            orElse: () => const SizedBox.shrink(),
          ),
        ],
      ),
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => RezeraErrorView(
          error: e,
          onRetry: () => ref.invalidate(businessDetailProvider(businessId)),
        ),
        data: (business) {
          return ListView(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
            children: [
              Text(
                business.name,
                style: theme.textTheme.headlineLarge?.copyWith(
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.8,
                ),
              ),
              const SizedBox(height: 8),
              if (business.openNow != null)
                Text(
                  business.openNow!
                      ? 'home_open_now'.tr()
                      : 'home_closed'.tr(),
                  style: theme.textTheme.titleMedium?.copyWith(
                    color: business.openNow!
                        ? RezeraColors.seafoam
                        : RezeraColors.slate,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              if (business.addressLine != null) ...[
                const SizedBox(height: 8),
                Text(business.addressLine!, style: theme.textTheme.bodyLarge),
              ],
              if (business.description != null) ...[
                const SizedBox(height: 16),
                Text(business.description!, style: theme.textTheme.bodyLarge),
              ],
              if (business.ratingAverage != null) ...[
                const SizedBox(height: 12),
                Text(
                  '${business.ratingAverage!.toStringAsFixed(1)} · ${business.ratingCount} ${'business_reviews'.tr().toLowerCase()}',
                  style: theme.textTheme.titleMedium,
                ),
              ],
              const SizedBox(height: 28),
              Text(
                'business_resources'.tr(),
                style: theme.textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 12),
              if (business.resources.isEmpty)
                Text('home_empty'.tr())
              else
                ...business.resources.map(
                  (r) => Container(
                    margin: const EdgeInsets.only(bottom: 10),
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.75),
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: RezeraColors.sand),
                    ),
                    child: Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                r.name,
                                style: theme.textTheme.titleMedium?.copyWith(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              if (r.code != null)
                                Text(
                                  r.code!,
                                  style: theme.textTheme.bodySmall,
                                ),
                            ],
                          ),
                        ),
                        Text(
                          MoneyFormat.uzs(r.hourlyRateAmount),
                          style: theme.textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              const SizedBox(height: 24),
              BusinessReviewsSection(businessId: business.id),
              const SizedBox(height: 20),
              FilledButton(
                onPressed: () {
                  context.push(
                    '/book/${business.id}',
                    extra: {
                      'name': business.name,
                    },
                  );
                },
                child: Text('business_book'.tr()),
              ),
            ],
          );
        },
      ),
    );
  }
}
