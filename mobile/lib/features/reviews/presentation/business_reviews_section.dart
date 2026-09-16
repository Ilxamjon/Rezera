import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/rezera_theme.dart';
import '../../../core/widgets/rezera_error_view.dart';
import '../data/review_repository.dart';
import 'review_providers.dart';

class BusinessReviewsSection extends ConsumerWidget {
  const BusinessReviewsSection({super.key, required this.businessId});

  final String businessId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(businessReviewsProvider(businessId));
    final theme = Theme.of(context);

    return async.when(
      loading: () => const Padding(
        padding: EdgeInsets.symmetric(vertical: 16),
        child: Center(child: CircularProgressIndicator()),
      ),
      error: (e, _) => RezeraErrorView(
        error: e,
        onRetry: () => ref.invalidate(businessReviewsProvider(businessId)),
      ),
      data: (result) {
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    'business_reviews'.tr(),
                    style: theme.textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
                if (result.average != null)
                  Text(
                    '${result.average!.toStringAsFixed(1)} · ${result.count}',
                    style: theme.textTheme.titleMedium,
                  ),
              ],
            ),
            const SizedBox(height: 12),
            if (result.items.isEmpty)
              Text(
                'reviews_empty'.tr(),
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: RezeraColors.slate.withValues(alpha: 0.7),
                ),
              )
            else
              ...result.items.map((r) => _ReviewCard(review: r)),
          ],
        );
      },
    );
  }
}

class _ReviewCard extends StatelessWidget {
  const _ReviewCard({required this.review});

  final ReviewItem review;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.75),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: RezeraColors.sand),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  review.authorName ?? 'reviews_anonymous'.tr(),
                  style: theme.textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              Row(
                children: List.generate(
                  5,
                  (i) => Icon(
                    i < review.rating
                        ? Icons.star_rounded
                        : Icons.star_outline_rounded,
                    size: 16,
                    color: RezeraColors.amberDeep,
                  ),
                ),
              ),
            ],
          ),
          if (review.title != null && review.title!.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(review.title!, style: theme.textTheme.titleSmall),
          ],
          if (review.body != null && review.body!.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(review.body!, style: theme.textTheme.bodyMedium),
          ],
          if (review.businessResponse != null &&
              review.businessResponse!.isNotEmpty) ...[
            const SizedBox(height: 10),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: RezeraColors.mist,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Text(
                '${'reviews_owner_reply'.tr()}: ${review.businessResponse}',
                style: theme.textTheme.bodySmall,
              ),
            ),
          ],
        ],
      ),
    );
  }
}
