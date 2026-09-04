import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/app_failure.dart';
import '../../../core/formatters/formatters.dart';
import '../../../core/network/api_client.dart';
import '../../../core/theme/rezera_theme.dart';
import '../../notifications/presentation/notification_bell.dart';
import '../../search/data/business_models.dart';
import '../../search/data/business_repository.dart';

final businessRepositoryProvider = Provider<BusinessRepository>((ref) {
  return BusinessRepository(ref.watch(apiClientProvider));
});

final discoveryProvider =
    FutureProvider.autoDispose.family<List<BusinessCard>, String>((ref, q) {
  return ref.watch(businessRepositoryProvider).discover(query: q);
});

class HomeScreen extends ConsumerStatefulWidget {
  const HomeScreen({super.key});

  @override
  ConsumerState<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends ConsumerState<HomeScreen> {
  final _search = TextEditingController();
  String _query = '';

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(discoveryProvider(_query));
    final theme = Theme.of(context);

    return Scaffold(
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [Color(0xFFF8F4EC), RezeraColors.mist],
          ),
        ),
        child: SafeArea(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 16, 20, 8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'app_name'.tr(),
                                style: theme.textTheme.headlineMedium?.copyWith(
                                  fontWeight: FontWeight.w800,
                                  letterSpacing: -0.8,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                'home_title'.tr(),
                                style: theme.textTheme.titleMedium?.copyWith(
                                  color: RezeraColors.slate.withValues(alpha: 0.7),
                                ),
                              ),
                            ],
                          ),
                        ),
                        const NotificationBellButton(),
                      ],
                    ),
                    const SizedBox(height: 16),
                    TextField(
                      controller: _search,
                      textInputAction: TextInputAction.search,
                      onSubmitted: (v) => setState(() => _query = v.trim()),
                      decoration: InputDecoration(
                        hintText: 'home_search_hint'.tr(),
                        prefixIcon: const Icon(Icons.search_rounded),
                        suffixIcon: IconButton(
                          onPressed: () =>
                              setState(() => _query = _search.text.trim()),
                          icon: const Icon(Icons.arrow_forward_rounded),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              Expanded(
                child: async.when(
                  loading: () => const Center(child: CircularProgressIndicator()),
                  error: (e, _) => _ErrorPane(
                    message: e is AppFailure ? e.message.tr() : 'error_unknown'.tr(),
                    onRetry: () => ref.invalidate(discoveryProvider(_query)),
                  ),
                  data: (items) {
                    if (items.isEmpty) {
                      return Center(child: Text('home_empty'.tr()));
                    }
                    return ListView.separated(
                      padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
                      itemCount: items.length,
                      separatorBuilder: (context, index) =>
                          const SizedBox(height: 12),
                      itemBuilder: (context, index) {
                        final item = items[index];
                        return _BusinessTile(
                          business: item,
                          onTap: () => context.push('/business/${item.id}'),
                        );
                      },
                    );
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _BusinessTile extends StatelessWidget {
  const _BusinessTile({required this.business, required this.onTap});

  final BusinessCard business;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final location = [
      if (business.district != null && business.district!.isNotEmpty)
        business.district,
      if (business.city != null && business.city!.isNotEmpty) business.city,
    ].join(', ');

    return Material(
      color: Colors.white.withValues(alpha: 0.78),
      borderRadius: BorderRadius.circular(18),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      business.name,
                      style: theme.textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  if (business.openNow != null)
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 4,
                      ),
                      decoration: BoxDecoration(
                        color: business.openNow!
                            ? RezeraColors.seafoam.withValues(alpha: 0.12)
                            : RezeraColors.sand,
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        business.openNow!
                            ? 'home_open_now'.tr()
                            : 'home_closed'.tr(),
                        style: theme.textTheme.labelMedium?.copyWith(
                          color: business.openNow!
                              ? RezeraColors.seafoam
                              : RezeraColors.slate,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                ],
              ),
              if (business.categoryName != null) ...[
                const SizedBox(height: 6),
                Text(
                  business.categoryName!,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: RezeraColors.seafoam,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
              if (location.isNotEmpty) ...[
                const SizedBox(height: 4),
                Text(location, style: theme.textTheme.bodyMedium),
              ],
              const SizedBox(height: 10),
              Row(
                children: [
                  if (business.ratingAverage != null) ...[
                    const Icon(Icons.star_rounded, size: 18, color: RezeraColors.amberDeep),
                    const SizedBox(width: 4),
                    Text(
                      '${business.ratingAverage!.toStringAsFixed(1)} (${business.ratingCount})',
                      style: theme.textTheme.labelLarge,
                    ),
                    const Spacer(),
                  ] else
                    const Spacer(),
                  if (business.priceFrom != null)
                    Text(
                      'home_from_price'.tr(args: [MoneyFormat.uzs(business.priceFrom)]),
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ErrorPane extends StatelessWidget {
  const _ErrorPane({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 12),
            FilledButton(onPressed: onRetry, child: Text('home_retry'.tr())),
          ],
        ),
      ),
    );
  }
}
