import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/app_failure.dart';
import '../../../core/formatters/formatters.dart';
import '../../../core/network/api_client.dart';
import '../../../core/theme/rezera_theme.dart';
import '../../../core/widgets/rezera_error_view.dart';
import '../../auth/presentation/session_controller.dart';
import '../../payments/data/payment_repository.dart';
import '../../reviews/presentation/write_review_sheet.dart';
import '../data/reservation_models.dart';
import 'reservation_providers.dart';

final paymentRepositoryProvider = Provider<PaymentRepository>((ref) {
  return PaymentRepository(ref.watch(apiClientProvider));
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
              error: (e, _) => RezeraErrorView(
                error: e,
                onRetry: () => ref.invalidate(myReservationsProvider),
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
                        onReview: item.canReview
                            ? () => _review(context, ref, item)
                            : null,
                        onPay: item.canPayOnline
                            ? () => _pay(context, ref, item)
                            : null,
                      );
                    },
                  ),
                );
              },
            ),
    );
  }

  Future<void> _review(
    BuildContext context,
    WidgetRef ref,
    ReservationSummary item,
  ) async {
    final ok = await showWriteReviewSheet(
      context,
      ref,
      reservationId: item.id,
      businessName: item.businessName,
    );
    if (ok && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('reviews_thanks'.tr())),
      );
    }
  }

  Future<void> _pay(
    BuildContext context,
    WidgetRef ref,
    ReservationSummary item,
  ) async {
    try {
      final payment = await ref.read(paymentRepositoryProvider).createForReservation(
            reservationId: item.id,
            provider: 'mock',
          );
      final synced = await ref
          .read(paymentRepositoryProvider)
          .show(payment.id, sync: true);
      ref.invalidate(myReservationsProvider);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              'bookings_paid_status'.tr(args: [synced.status]),
            ),
          ),
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
  const _ReservationTile({
    required this.reservation,
    this.onCancel,
    this.onReview,
    this.onPay,
  });

  final ReservationSummary reservation;
  final VoidCallback? onCancel;
  final VoidCallback? onReview;
  final VoidCallback? onPay;

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
          if (reservation.paymentStatus != null) ...[
            const SizedBox(height: 4),
            Text(
              'payment_status_${reservation.paymentStatus}'.tr(),
              style: theme.textTheme.labelMedium,
            ),
          ],
          if (reservation.promoCode != null)
            Text('book_promo_used'.tr(args: [reservation.promoCode!])),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: Text(
                  '#${reservation.reservationNumber}',
                  style: theme.textTheme.labelMedium,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              if (reservation.totalAmount != null) ...[
                const SizedBox(width: 8),
                Flexible(
                  child: Text(
                    MoneyFormat.uzs(reservation.totalAmount),
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                    overflow: TextOverflow.ellipsis,
                    textAlign: TextAlign.end,
                  ),
                ),
              ],
            ],
          ),
          if (onCancel != null || onReview != null || onPay != null) ...[
            const SizedBox(height: 8),
            Wrap(
              spacing: 4,
              runSpacing: 0,
              alignment: WrapAlignment.end,
              children: [
                if (onPay != null)
                  TextButton(
                    onPressed: onPay,
                    style: TextButton.styleFrom(
                      visualDensity: VisualDensity.compact,
                      padding: const EdgeInsets.symmetric(horizontal: 8),
                    ),
                    child: Text('bookings_pay_mock'.tr()),
                  ),
                if (onReview != null)
                  TextButton(
                    onPressed: onReview,
                    style: TextButton.styleFrom(
                      visualDensity: VisualDensity.compact,
                      padding: const EdgeInsets.symmetric(horizontal: 8),
                    ),
                    child: Text('reviews_write_action'.tr()),
                  ),
                if (onCancel != null)
                  TextButton(
                    onPressed: onCancel,
                    style: TextButton.styleFrom(
                      visualDensity: VisualDensity.compact,
                      padding: const EdgeInsets.symmetric(horizontal: 8),
                    ),
                    child: Text('bookings_cancel_action'.tr()),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}
