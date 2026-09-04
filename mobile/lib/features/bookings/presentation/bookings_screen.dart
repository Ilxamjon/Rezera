import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/app_failure.dart';
import '../../../core/formatters/formatters.dart';
import '../../../core/theme/rezera_theme.dart';
import '../../auth/presentation/session_controller.dart';
import '../data/reservation_models.dart';
import 'book_screen.dart';

final myReservationsProvider =
    FutureProvider.autoDispose<List<ReservationSummary>>((ref) async {
  final session = ref.watch(sessionControllerProvider);
  if (!session.isAuthenticated) return const [];
  return ref.watch(reservationRepositoryProvider).mine();
});

class BookingsScreen extends ConsumerWidget {
  const BookingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(sessionControllerProvider);
    final async = ref.watch(myReservationsProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: Text('bookings_title'.tr())),
      body: !session.isAuthenticated
          ? Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      'bookings_login_required'.tr(),
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
              error: (e, _) => Center(
                child: Text(
                  e is AppFailure ? e.message.tr() : 'error_unknown'.tr(),
                ),
              ),
              data: (items) {
                if (items.isEmpty) {
                  return Center(child: Text('bookings_empty'.tr()));
                }
                return RefreshIndicator(
                  onRefresh: () async {
                    ref.invalidate(myReservationsProvider);
                  },
                  child: ListView.separated(
                    padding: const EdgeInsets.all(20),
                    itemCount: items.length,
                    separatorBuilder: (context, index) =>
                        const SizedBox(height: 10),
                    itemBuilder: (context, index) {
                      final item = items[index];
                      return _ReservationTile(
                        reservation: item,
                        onCancel: item.canCancel
                            ? () => _cancel(context, ref, item.id)
                            : null,
                      );
                    },
                  ),
                );
              },
            ),
    );
  }

  Future<void> _cancel(
    BuildContext context,
    WidgetRef ref,
    String id,
  ) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('bookings_cancel_title'.tr()),
        content: Text('bookings_cancel_body'.tr()),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text('bookings_cancel_no'.tr()),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text('bookings_cancel_yes'.tr()),
          ),
        ],
      ),
    );
    if (ok != true) return;

    try {
      await ref.read(reservationRepositoryProvider).cancel(id);
      ref.invalidate(myReservationsProvider);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('bookings_cancelled'.tr())),
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

class _ReservationTile extends StatelessWidget {
  const _ReservationTile({required this.reservation, this.onCancel});

  final ReservationSummary reservation;
  final VoidCallback? onCancel;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.8),
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
                  reservation.businessName ?? 'Rezera',
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
          const SizedBox(height: 6),
          Text(
            reservation.resourceName ?? '—',
            style: theme.textTheme.bodyLarge,
          ),
          if (reservation.date != null || reservation.startAt != null) ...[
            const SizedBox(height: 4),
            Text(
              reservation.scheduleLabel,
              style: theme.textTheme.bodyMedium,
            ),
          ],
          const SizedBox(height: 8),
          Row(
            children: [
              Text(
                '#${reservation.reservationNumber}',
                style: theme.textTheme.labelMedium,
              ),
              const Spacer(),
              if (reservation.totalAmount != null)
                Text(
                  MoneyFormat.uzs(reservation.totalAmount),
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
            ],
          ),
          if (onCancel != null) ...[
            const SizedBox(height: 12),
            Align(
              alignment: Alignment.centerRight,
              child: TextButton(
                onPressed: onCancel,
                child: Text('bookings_cancel_action'.tr()),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
