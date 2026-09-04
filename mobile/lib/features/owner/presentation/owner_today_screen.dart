import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/app_failure.dart';
import '../../../core/formatters/formatters.dart';
import '../../../core/theme/rezera_theme.dart';
import '../data/owner_models.dart';
import 'owner_providers.dart';

class OwnerTodayScreen extends ConsumerWidget {
  const OwnerTodayScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(ownerTodayProvider);

    return Scaffold(
      appBar: AppBar(title: Text('owner_today_list_title'.tr())),
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Text(e is AppFailure ? e.message.tr() : 'error_unknown'.tr()),
        ),
        data: (items) {
          if (items.isEmpty) {
            return Center(child: Text('owner_today_empty'.tr()));
          }
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(ownerTodayProvider),
            child: ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: items.length,
              separatorBuilder: (context, index) => const SizedBox(height: 10),
              itemBuilder: (context, index) {
                final item = items[index];
                return _OwnerReservationCard(
                  reservation: item,
                  onAction: (status) => _runStatus(context, ref, item, status),
                  onCheckIn: () => _runCheckIn(context, ref, item),
                  onQrCheckIn: () => _runQrCheckIn(context, ref, item),
                  onCheckOut: () => _runCheckOut(context, ref, item),
                );
              },
            ),
          );
        },
      ),
    );
  }

  Future<void> _runStatus(
    BuildContext context,
    WidgetRef ref,
    OwnerReservation item,
    String status,
  ) async {
    final businessId = ref.read(selectedBusinessIdProvider);
    if (businessId == null) return;
    try {
      await ref.read(ownerRepositoryProvider).changeStatus(
            businessId: businessId,
            reservationId: item.id,
            status: status,
          );
      ref.invalidate(ownerTodayProvider);
      ref.invalidate(ownerDashboardProvider);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('owner_status_updated'.tr())),
        );
      }
    } on AppFailure catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.message.tr())),
        );
      }
    }
  }

  Future<void> _runCheckIn(
    BuildContext context,
    WidgetRef ref,
    OwnerReservation item,
  ) async {
    final businessId = ref.read(selectedBusinessIdProvider);
    if (businessId == null) return;
    try {
      await ref.read(ownerRepositoryProvider).checkIn(
            businessId: businessId,
            reservationId: item.id,
          );
      ref.invalidate(ownerTodayProvider);
      ref.invalidate(ownerDashboardProvider);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('owner_checked_in'.tr())),
        );
      }
    } on AppFailure catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.message.tr())),
        );
      }
    }
  }

  Future<void> _runQrCheckIn(
    BuildContext context,
    WidgetRef ref,
    OwnerReservation item,
  ) async {
    final ok = await context.push<bool>(
      '/owner/check-in/qr',
      extra: {
        'reservationId': item.id,
        'customerName': item.customerName ?? item.reservationNumber,
      },
    );
    if (ok == true) {
      ref.invalidate(ownerTodayProvider);
      ref.invalidate(ownerDashboardProvider);
    }
  }

  Future<void> _runCheckOut(
    BuildContext context,
    WidgetRef ref,
    OwnerReservation item,
  ) async {
    final businessId = ref.read(selectedBusinessIdProvider);
    if (businessId == null) return;
    try {
      await ref.read(ownerRepositoryProvider).checkOut(
            businessId: businessId,
            reservationId: item.id,
          );
      ref.invalidate(ownerTodayProvider);
      ref.invalidate(ownerDashboardProvider);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('owner_checked_out'.tr())),
        );
      }
    } on AppFailure catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.message.tr())),
        );
      }
    }
  }
}

class _OwnerReservationCard extends StatelessWidget {
  const _OwnerReservationCard({
    required this.reservation,
    required this.onAction,
    required this.onCheckIn,
    required this.onQrCheckIn,
    required this.onCheckOut,
  });

  final OwnerReservation reservation;
  final void Function(String status) onAction;
  final VoidCallback onCheckIn;
  final VoidCallback onQrCheckIn;
  final VoidCallback onCheckOut;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.88),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: RezeraColors.sand),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  reservation.customerName ?? '—',
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              Text(
                reservation.status,
                style: theme.textTheme.labelLarge?.copyWith(
                  color: RezeraColors.seafoam,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
          if (reservation.customerPhone != null)
            Text(reservation.customerPhone!),
          const SizedBox(height: 6),
          Text(
            '${reservation.resourceName ?? '—'} · ${reservation.scheduleLabel}',
          ),
          if (reservation.totalAmount != null) ...[
            const SizedBox(height: 4),
            Text(
              MoneyFormat.uzs(reservation.totalAmount),
              style: theme.textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              if (reservation.canConfirm)
                FilledButton(
                  onPressed: () => onAction('confirmed'),
                  child: Text('owner_action_confirm'.tr()),
                ),
              if (reservation.canReject)
                OutlinedButton(
                  onPressed: () => onAction('rejected'),
                  child: Text('owner_action_reject'.tr()),
                ),
              if (reservation.canCheckIn) ...[
                FilledButton(
                  onPressed: onCheckIn,
                  child: Text('owner_action_check_in'.tr()),
                ),
                OutlinedButton.icon(
                  onPressed: onQrCheckIn,
                  icon: const Icon(Icons.qr_code_scanner_rounded, size: 18),
                  label: Text('owner_action_qr_check_in'.tr()),
                ),
              ],
              if (reservation.canNoShow)
                OutlinedButton(
                  onPressed: () => onAction('no_show'),
                  child: Text('owner_action_no_show'.tr()),
                ),
              if (reservation.canCheckOut)
                FilledButton(
                  onPressed: onCheckOut,
                  child: Text('owner_action_check_out'.tr()),
                ),
            ],
          ),
        ],
      ),
    );
  }
}
