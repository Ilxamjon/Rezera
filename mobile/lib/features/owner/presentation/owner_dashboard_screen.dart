import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/formatters/formatters.dart';
import '../../../core/theme/rezera_theme.dart';
import '../../../core/widgets/rezera_error_view.dart';
import '../../auth/presentation/session_controller.dart';
import 'owner_providers.dart';

class OwnerDashboardScreen extends ConsumerWidget {
  const OwnerDashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(sessionControllerProvider);
    final async = ref.watch(ownerDashboardProvider);
    final theme = Theme.of(context);
    final membership = session.selectedMembership;

    return Scaffold(
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [Color(0xFF1A222E), RezeraColors.mist],
            stops: [0.0, 0.28],
          ),
        ),
        child: SafeArea(
          child: RefreshIndicator(
            onRefresh: () async => ref.invalidate(ownerDashboardProvider),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 28),
              children: [
                Text(
                  'owner_mode_badge'.tr(),
                  style: theme.textTheme.labelLarge?.copyWith(
                    color: RezeraColors.amber,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  membership?.businessName ?? 'Rezera',
                  style: theme.textTheme.headlineMedium?.copyWith(
                    color: RezeraColors.onInk,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                if ((session.user?.memberships.length ?? 0) > 1) ...[
                  const SizedBox(height: 8),
                  Align(
                    alignment: Alignment.centerLeft,
                    child: TextButton.icon(
                      onPressed: () => _pickBusiness(context, ref),
                      icon: const Icon(Icons.storefront_outlined, size: 18),
                      label: Text('owner_switch_business'.tr()),
                      style: TextButton.styleFrom(
                        foregroundColor: RezeraColors.amber,
                      ),
                    ),
                  ),
                ],
                const SizedBox(height: 20),
                async.when(
                  loading: () => const Padding(
                    padding: EdgeInsets.only(top: 48),
                    child: Center(child: CircularProgressIndicator()),
                  ),
                  error: (e, _) => RezeraErrorView(
                    error: e,
                    onRetry: () => ref.invalidate(ownerDashboardProvider),
                  ),
                  data: (dash) {
                    return Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Text(
                          'owner_today_title'.tr(args: [dash.date]),
                          style: theme.textTheme.titleMedium?.copyWith(
                            color: RezeraColors.onInk.withValues(alpha: 0.85),
                          ),
                        ),
                        const SizedBox(height: 12),
                        Wrap(
                          spacing: 10,
                          runSpacing: 10,
                          children: [
                            _StatChip(
                              label: 'owner_stat_total'.tr(),
                              value: '${dash.todayTotal}',
                            ),
                            _StatChip(
                              label: 'owner_stat_pending'.tr(),
                              value: '${dash.todayPending}',
                            ),
                            _StatChip(
                              label: 'owner_stat_confirmed'.tr(),
                              value: '${dash.todayConfirmed}',
                            ),
                            _StatChip(
                              label: 'owner_stat_checked_in'.tr(),
                              value: '${dash.todayCheckedIn}',
                            ),
                            _StatChip(
                              label: 'owner_stat_sessions'.tr(),
                              value: '${dash.activeSessions}',
                            ),
                            _StatChip(
                              label: 'owner_stat_revenue'.tr(),
                              value: MoneyFormat.uzs(dash.revenuePaid ?? 0),
                            ),
                            if (dash.occupancyPercent != null)
                              _StatChip(
                                label: 'owner_stat_occupancy'.tr(),
                                value:
                                    '${dash.occupancyPercent!.round()}%',
                              ),
                            if (dash.resources.total > 0)
                              _StatChip(
                                label: 'owner_stat_resources'.tr(),
                                value:
                                    '${dash.resources.occupied}/${dash.resources.available + dash.resources.occupied}',
                              ),
                          ],
                        ),
                        if (dash.resources.maintenance > 0) ...[
                          const SizedBox(height: 10),
                          Text(
                            'owner_resources_maintenance_count'.tr(
                              args: ['${dash.resources.maintenance}'],
                            ),
                            style: theme.textTheme.bodyMedium?.copyWith(
                              color: RezeraColors.onInk.withValues(alpha: 0.8),
                            ),
                          ),
                        ],
                        if (dash.overdueCheckIns > 0) ...[
                          const SizedBox(height: 14),
                          Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: RezeraColors.danger.withValues(alpha: 0.12),
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: Text(
                              'owner_overdue_checkins'
                                  .tr(args: ['${dash.overdueCheckIns}']),
                              style: theme.textTheme.bodyMedium?.copyWith(
                                color: RezeraColors.danger,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                        ],
                        const SizedBox(height: 20),
                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: [
                            FilledButton.tonalIcon(
                              onPressed: () =>
                                  context.push('/owner/calendar'),
                              icon: const Icon(Icons.calendar_month_outlined),
                              label: Text('owner_nav_calendar'.tr()),
                            ),
                            FilledButton.tonalIcon(
                              onPressed: () => context.go('/owner/today'),
                              icon: const Icon(Icons.today_outlined),
                              label: Text('owner_nav_today'.tr()),
                            ),
                            FilledButton.tonalIcon(
                              onPressed: () => context.go('/owner/resources'),
                              icon: const Icon(Icons.grid_view_rounded),
                              label: Text('owner_nav_resources'.tr()),
                            ),
                          ],
                        ),
                        const SizedBox(height: 24),
                        Row(
                          children: [
                            Text(
                              'owner_upcoming'.tr(),
                              style: theme.textTheme.titleLarge?.copyWith(
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            const Spacer(),
                            TextButton(
                              onPressed: () => context.go('/owner/today'),
                              child: Text('owner_see_today'.tr()),
                            ),
                          ],
                        ),
                        if (dash.upcoming.isEmpty)
                          Text('owner_no_upcoming'.tr())
                        else
                          ...dash.upcoming.map(
                            (r) => Container(
                              margin: const EdgeInsets.only(bottom: 8),
                              padding: const EdgeInsets.all(14),
                              decoration: BoxDecoration(
                                color: Colors.white.withValues(alpha: 0.86),
                                borderRadius: BorderRadius.circular(14),
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    r.customerName ?? r.reservationNumber,
                                    style: theme.textTheme.titleMedium
                                        ?.copyWith(fontWeight: FontWeight.w700),
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    '${r.resourceName ?? '—'} · ${r.scheduleLabel}',
                                  ),
                                  Text(
                                    r.status,
                                    style: theme.textTheme.labelLarge?.copyWith(
                                      color: RezeraColors.seafoam,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                      ],
                    );
                  },
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _pickBusiness(BuildContext context, WidgetRef ref) async {
    final memberships =
        ref.read(sessionControllerProvider).user?.memberships ?? [];
    final selected = await showModalBottomSheet<String>(
      context: context,
      builder: (context) {
        return SafeArea(
          child: ListView(
            shrinkWrap: true,
            children: [
              Padding(
                padding: const EdgeInsets.all(16),
                child: Text(
                  'owner_switch_business'.tr(),
                  style: Theme.of(context).textTheme.titleLarge,
                ),
              ),
              ...memberships.map(
                (m) => ListTile(
                  title: Text(m.businessName ?? m.businessId),
                  subtitle: Text(m.memberRole),
                  onTap: () => Navigator.pop(context, m.businessId),
                ),
              ),
            ],
          ),
        );
      },
    );
    if (selected != null) {
      ref.read(sessionControllerProvider.notifier).selectBusiness(selected);
      ref.invalidate(ownerDashboardProvider);
      ref.invalidate(ownerTodayProvider);
      ref.invalidate(ownerResourcesProvider);
    }
  }
}

class _StatChip extends StatelessWidget {
  const _StatChip({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 150,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.9),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            value,
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
          ),
          const SizedBox(height: 4),
          Text(label, style: Theme.of(context).textTheme.labelMedium),
        ],
      ),
    );
  }
}
