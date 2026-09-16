import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/app_failure.dart';
import '../../../core/navigation/rezera_nav.dart';
import '../../../core/theme/rezera_theme.dart';
import '../../../core/widgets/rezera_error_view.dart';
import '../data/owner_models.dart';
import 'owner_providers.dart';

final ownerCalendarDateProvider = StateProvider.autoDispose<DateTime>((ref) {
  final now = DateTime.now();
  return DateTime(now.year, now.month, now.day);
});

final ownerCalendarDayProvider =
    FutureProvider.autoDispose<OwnerCalendarDay>((ref) async {
  final businessId = ref.watch(selectedBusinessIdProvider);
  if (businessId == null) {
    throw const UnknownFailure('error_unknown');
  }
  final date = ref.watch(ownerCalendarDateProvider);
  final dateStr =
      '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
  return ref.watch(ownerRepositoryProvider).calendarDay(
        businessId: businessId,
        date: dateStr,
      );
});

class OwnerCalendarScreen extends ConsumerWidget {
  const OwnerCalendarScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final date = ref.watch(ownerCalendarDateProvider);
    final async = ref.watch(ownerCalendarDayProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        leading: rezeraBackButton(context, fallback: '/owner'),
        title: Text('owner_calendar_title'.tr()),
        actions: [
          IconButton(
            tooltip: 'owner_nav_today'.tr(),
            onPressed: () => context.go('/owner/today'),
            icon: const Icon(Icons.list_alt_rounded),
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 8, 12, 4),
            child: Row(
              children: [
                IconButton(
                  onPressed: () {
                    ref.read(ownerCalendarDateProvider.notifier).state =
                        date.subtract(const Duration(days: 1));
                  },
                  icon: const Icon(Icons.chevron_left_rounded),
                ),
                Expanded(
                  child: TextButton(
                    onPressed: () async {
                      final picked = await showDatePicker(
                        context: context,
                        initialDate: date,
                        firstDate: DateTime(2020),
                        lastDate: DateTime.now().add(const Duration(days: 365)),
                      );
                      if (picked != null) {
                        ref.read(ownerCalendarDateProvider.notifier).state =
                            DateTime(picked.year, picked.month, picked.day);
                      }
                    },
                    child: Text(
                      DateFormat.yMMMEd(context.locale.toString()).format(date),
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ),
                IconButton(
                  onPressed: () {
                    ref.read(ownerCalendarDateProvider.notifier).state =
                        date.add(const Duration(days: 1));
                  },
                  icon: const Icon(Icons.chevron_right_rounded),
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
                onRetry: () => ref.invalidate(ownerCalendarDayProvider),
              ),
              data: (day) {
                if (day.resources.isEmpty) {
                  return Center(child: Text('owner_calendar_empty'.tr()));
                }
                return RefreshIndicator(
                  onRefresh: () async =>
                      ref.invalidate(ownerCalendarDayProvider),
                  child: ListView.separated(
                    padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                    itemCount: day.resources.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 12),
                    itemBuilder: (context, index) {
                      final resource = day.resources[index];
                      return _ResourceDayCard(resource: resource);
                    },
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _ResourceDayCard extends StatelessWidget {
  const _ResourceDayCard({required this.resource});

  final OwnerCalendarResource resource;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.9),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  resource.name,
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              if (resource.status != null)
                _StatusBadge(status: resource.status!),
            ],
          ),
          if (resource.code != null) ...[
            const SizedBox(height: 2),
            Text(resource.code!, style: theme.textTheme.bodySmall),
          ],
          const SizedBox(height: 10),
          if (resource.blocks.isEmpty)
            Text('owner_calendar_no_blocks'.tr())
          else
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: resource.blocks
                  .map((b) => _BlockChip(block: b))
                  .toList(),
            ),
        ],
      ),
    );
  }
}

class _BlockChip extends StatelessWidget {
  const _BlockChip({required this.block});

  final OwnerCalendarBlock block;

  Color _color() {
    switch (block.type) {
      case 'available':
        return RezeraColors.seafoam.withValues(alpha: 0.25);
      case 'booked':
      case 'active':
        return RezeraColors.amber.withValues(alpha: 0.35);
      case 'maintenance':
      case 'inactive':
        return RezeraColors.slate.withValues(alpha: 0.25);
      case 'closed':
        return Colors.black12;
      case 'completed':
        return Colors.blueGrey.withValues(alpha: 0.2);
      case 'cancelled':
      case 'no_show':
        return RezeraColors.danger.withValues(alpha: 0.2);
      default:
        return Colors.grey.withValues(alpha: 0.2);
    }
  }

  @override
  Widget build(BuildContext context) {
    final label = block.customerName ??
        block.reservationNumber ??
        'owner_calendar_block_${block.type}'.tr();
    return Material(
      color: _color(),
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        borderRadius: BorderRadius.circular(10),
        onTap: block.reservationId == null
            ? null
            : () => _showBlockDetail(context, block),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '${block.startTime}–${block.endTime}',
                style: Theme.of(context).textTheme.labelLarge?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
              ),
              Text(
                label,
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _showBlockDetail(
    BuildContext context,
    OwnerCalendarBlock block,
  ) async {
    await showModalBottomSheet<void>(
      context: context,
      builder: (context) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  block.customerName ??
                      block.reservationNumber ??
                      'owner_calendar_title'.tr(),
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                ),
                const SizedBox(height: 8),
                Text('${block.startTime} – ${block.endTime}'),
                if (block.reservationStatus != null)
                  Text(
                    block.reservationStatus!,
                    style: Theme.of(context).textTheme.labelLarge?.copyWith(
                          color: RezeraColors.seafoam,
                          fontWeight: FontWeight.w700,
                        ),
                  ),
                if (block.customerPhone != null) Text(block.customerPhone!),
                if (block.reservationNumber != null)
                  Text(block.reservationNumber!),
                const SizedBox(height: 16),
                if (block.reservationId != null &&
                    (block.reservationStatus == 'confirmed' ||
                        block.reservationStatus == 'pending'))
                  FilledButton.icon(
                    onPressed: () {
                      Navigator.pop(context);
                      context.go('/owner/today');
                    },
                    icon: const Icon(Icons.login_rounded),
                    label: Text('owner_action_check_in'.tr()),
                  ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final key = 'owner_status_$status';
    final label = key.tr();
    final color = switch (status) {
      'active' => RezeraColors.seafoam,
      'maintenance' => RezeraColors.amber,
      'inactive' => RezeraColors.slate,
      _ => RezeraColors.slate,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        label == key ? status : label,
        style: Theme.of(context).textTheme.labelMedium?.copyWith(
              color: color,
              fontWeight: FontWeight.w700,
            ),
      ),
    );
  }
}
