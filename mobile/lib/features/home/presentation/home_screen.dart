import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/formatters/formatters.dart';
import '../../../core/location/location_providers.dart';
import '../../../core/location/location_service.dart';
import '../../../core/network/api_client.dart';
import '../../../core/theme/rezera_theme.dart';
import '../../../core/widgets/rezera_error_view.dart';
import '../../favorites/presentation/favorite_providers.dart';
import '../../favorites/presentation/favorite_toggle_button.dart';
import '../../notifications/presentation/notification_bell.dart';
import '../../search/data/business_models.dart';
import '../../search/data/business_repository.dart';

final businessRepositoryProvider = Provider<BusinessRepository>((ref) {
  // Cache is loaded async; first requests may skip cache until prefs ready.
  final cacheAsync = ref.watch(apiResponseCacheProvider);
  return BusinessRepository(
    ref.watch(apiClientProvider),
    cache: cacheAsync.asData?.value,
  );
});

class DiscoveryQuery {
  const DiscoveryQuery({
    this.q = '',
    this.nearby = false,
    this.latitude,
    this.longitude,
  });

  final String q;
  final bool nearby;
  final double? latitude;
  final double? longitude;

  @override
  bool operator ==(Object other) =>
      other is DiscoveryQuery &&
      other.q == q &&
      other.nearby == nearby &&
      other.latitude == latitude &&
      other.longitude == longitude;

  @override
  int get hashCode => Object.hash(q, nearby, latitude, longitude);
}

final discoveryProvider =
    FutureProvider.autoDispose.family<List<BusinessCard>, DiscoveryQuery>(
        (ref, query) {
  return ref.watch(businessRepositoryProvider).discover(
        query: query.q,
        latitude: query.nearby ? query.latitude : null,
        longitude: query.nearby ? query.longitude : null,
        radiusKm: query.nearby ? 50 : null,
        sort: query.nearby ? 'nearest' : null,
      );
});

class HomeScreen extends ConsumerStatefulWidget {
  const HomeScreen({super.key});

  @override
  ConsumerState<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends ConsumerState<HomeScreen> {
  final _search = TextEditingController();
  String _query = '';
  bool _nearby = false;
  bool _locating = false;
  double? _lat;
  double? _lng;

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  DiscoveryQuery get _discoveryQuery => DiscoveryQuery(
        q: _query,
        nearby: _nearby && _lat != null && _lng != null,
        latitude: _lat,
        longitude: _lng,
      );

  Future<void> _toggleNearby(bool value) async {
    if (!value) {
      setState(() {
        _nearby = false;
        _locating = false;
        _lat = null;
        _lng = null;
      });
      return;
    }
    if (_locating) return;

    setState(() => _locating = true);
    final service = ref.read(locationServiceProvider);
    final result = await service.resolve();
    if (!mounted) return;

    if (!result.isOk || result.location == null) {
      setState(() {
        _nearby = false;
        _locating = false;
        _lat = null;
        _lng = null;
      });
      await _showLocationFailure(result.failure, service);
      return;
    }

    setState(() {
      _nearby = true;
      _locating = false;
      _lat = result.location!.latitude;
      _lng = result.location!.longitude;
    });
  }

  Future<void> _showLocationFailure(
    LocationFailureReason? reason,
    LocationService service,
  ) async {
    final messenger = ScaffoldMessenger.of(context);
    switch (reason) {
      case LocationFailureReason.serviceDisabled:
        messenger.showSnackBar(
          SnackBar(
            content: Text('home_location_disabled'.tr()),
            action: SnackBarAction(
              label: 'home_location_open_settings'.tr(),
              onPressed: () => service.openLocationSettings(),
            ),
          ),
        );
      case LocationFailureReason.deniedForever:
        messenger.showSnackBar(
          SnackBar(
            content: Text('home_location_denied_forever'.tr()),
            action: SnackBarAction(
              label: 'home_location_open_settings'.tr(),
              onPressed: () => service.openAppSettings(),
            ),
          ),
        );
      case LocationFailureReason.denied:
        messenger.showSnackBar(
          SnackBar(content: Text('home_location_denied'.tr())),
        );
      case LocationFailureReason.unavailable:
      case null:
        messenger.showSnackBar(
          SnackBar(content: Text('home_location_unavailable'.tr())),
        );
    }
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(discoveryProvider(_discoveryQuery));
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
                                  color: RezeraColors.slate
                                      .withValues(alpha: 0.7),
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
                    const SizedBox(height: 10),
                    FilterChip(
                      selected: _nearby,
                      showCheckmark: !_locating,
                      label: Text(
                        _locating
                            ? 'home_nearby_locating'.tr()
                            : 'home_nearby'.tr(),
                      ),
                      avatar: _locating
                          ? const SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : Icon(
                              Icons.near_me_rounded,
                              size: 18,
                              color: _nearby ? RezeraColors.ink : null,
                            ),
                      onSelected: _locating ? null : _toggleNearby,
                    ),
                  ],
                ),
              ),
              Expanded(
                child: async.when(
                  loading: () =>
                      const Center(child: CircularProgressIndicator()),
                  error: (e, _) => RezeraErrorView(
                    error: e,
                    onRetry: () =>
                        ref.invalidate(discoveryProvider(_discoveryQuery)),
                  ),
                  data: (items) {
                    if (items.isEmpty) {
                      return Center(
                        child: Padding(
                          padding: const EdgeInsets.all(24),
                          child: Text(
                            _nearby
                                ? 'home_nearby_empty'.tr()
                                : 'home_empty'.tr(),
                            textAlign: TextAlign.center,
                          ),
                        ),
                      );
                    }
                    return RefreshIndicator(
                      onRefresh: () async {
                        ref.invalidate(discoveryProvider(_discoveryQuery));
                        ref.invalidate(favoriteBusinessesProvider);
                      },
                      child: ListView.separated(
                        padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
                        itemCount: items.length,
                        separatorBuilder: (context, index) =>
                            const SizedBox(height: 12),
                        itemBuilder: (context, index) {
                          final item = items[index];
                          return _BusinessTile(
                            business: item,
                            onTap: () =>
                                context.push('/business/${item.id}'),
                            onFavoriteChanged: (_) {
                              ref.invalidate(
                                discoveryProvider(_discoveryQuery),
                              );
                            },
                          );
                        },
                      ),
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
  const _BusinessTile({
    required this.business,
    required this.onTap,
    this.onFavoriteChanged,
  });

  final BusinessCard business;
  final VoidCallback onTap;
  final ValueChanged<bool>? onFavoriteChanged;

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
          padding: const EdgeInsets.fromLTRB(16, 16, 4, 16),
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
                  FavoriteToggleButton(
                    businessId: business.id,
                    isFavorite: business.isFavorite ?? false,
                    onChanged: onFavoriteChanged,
                  ),
                ],
              ),
              if (business.categoryName != null) ...[
                const SizedBox(height: 6),
                Text(
                  business.categoryName!,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: RezeraColors.slate.withValues(alpha: 0.75),
                  ),
                ),
              ],
              if (location.isNotEmpty) ...[
                const SizedBox(height: 4),
                Text(location, style: theme.textTheme.bodyMedium),
              ],
              if (business.distanceKm != null) ...[
                const SizedBox(height: 4),
                Text(
                  'home_distance_km'.tr(
                    args: [business.distanceKm!.toStringAsFixed(1)],
                  ),
                  style: theme.textTheme.labelLarge?.copyWith(
                    color: RezeraColors.seafoam,
                  ),
                ),
              ],
              const SizedBox(height: 10),
              Row(
                children: [
                  if (business.ratingAverage != null) ...[
                    const Icon(
                      Icons.star_rounded,
                      size: 18,
                      color: RezeraColors.amberDeep,
                    ),
                    const SizedBox(width: 4),
                    Text(
                      '${business.ratingAverage!.toStringAsFixed(1)} (${business.ratingCount})',
                      style: theme.textTheme.labelLarge,
                    ),
                    const SizedBox(width: 12),
                  ],
                  if (business.priceFrom != null)
                    Text(
                      'home_from_price'.tr(
                        args: [MoneyFormat.uzs(business.priceFrom)],
                      ),
                      style: theme.textTheme.labelLarge?.copyWith(
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
