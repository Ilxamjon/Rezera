import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/app_failure.dart';
import '../../../core/navigation/rezera_nav.dart';
import '../../../core/network/api_client.dart';
import '../../../core/theme/rezera_theme.dart';
import '../../../core/widgets/rezera_error_view.dart';
import '../data/reservation_settings_repository.dart';
import 'owner_providers.dart';

final reservationSettingsRepositoryProvider =
    Provider<ReservationSettingsRepository>((ref) {
  return ReservationSettingsRepository(ref.watch(apiClientProvider));
});

final ownerReservationSettingsProvider =
    FutureProvider.autoDispose<ReservationRules>((ref) async {
  final businessId = ref.watch(selectedBusinessIdProvider);
  if (businessId == null) {
    throw const UnknownFailure('error_unknown');
  }
  return ref
      .watch(reservationSettingsRepositoryProvider)
      .manageSettings(businessId);
});

class OwnerReservationRulesScreen extends ConsumerStatefulWidget {
  const OwnerReservationRulesScreen({super.key});

  @override
  ConsumerState<OwnerReservationRulesScreen> createState() =>
      _OwnerReservationRulesScreenState();
}

class _OwnerReservationRulesScreenState
    extends ConsumerState<OwnerReservationRulesScreen> {
  final _minDuration = TextEditingController();
  final _cancelDeadline = TextEditingController();
  final _grace = TextEditingController();
  bool _hydrated = false;
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _minDuration.dispose();
    _cancelDeadline.dispose();
    _grace.dispose();
    super.dispose();
  }

  void _fill(ReservationRules rules) {
    if (_hydrated) return;
    _minDuration.text = '${rules.minDurationMinutes ?? 60}';
    _cancelDeadline.text = '${rules.cancellationDeadlineMinutes ?? 60}';
    _grace.text = '${rules.noShowGraceMinutes ?? 15}';
    _hydrated = true;
  }

  Future<void> _save() async {
    final businessId = ref.read(selectedBusinessIdProvider);
    if (businessId == null) return;
    final min = int.tryParse(_minDuration.text.trim());
    final cancel = int.tryParse(_cancelDeadline.text.trim());
    final grace = int.tryParse(_grace.text.trim());
    if (min == null || cancel == null || grace == null) {
      setState(() => _error = 'error_validation'.tr());
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await ref.read(reservationSettingsRepositoryProvider).updateManageSettings(
            businessId: businessId,
            minDurationMinutes: min,
            cancellationDeadlineMinutes: cancel,
            noShowGraceMinutes: grace,
          );
      ref.invalidate(ownerReservationSettingsProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('owner_rules_saved'.tr())),
        );
      }
    } on AppFailure catch (e) {
      setState(() => _error = e.message.tr());
    } catch (_) {
      setState(() => _error = 'error_unknown'.tr());
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(ownerReservationSettingsProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Text('owner_rules_title'.tr()),
        leading: rezeraBackButton(context, fallback: '/owner/more'),
      ),
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => RezeraErrorView(
          error: e,
          onRetry: () {
            _hydrated = false;
            ref.invalidate(ownerReservationSettingsProvider);
          },
        ),
        data: (rules) {
          _fill(rules);
          return ListView(
            padding: const EdgeInsets.all(20),
            children: [
              Text(
                'owner_rules_hint'.tr(),
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: RezeraColors.slate.withValues(alpha: 0.75),
                ),
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _minDuration,
                keyboardType: TextInputType.number,
                decoration: InputDecoration(
                  labelText: 'owner_rules_min_duration'.tr(),
                  suffixText: 'min',
                ),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _cancelDeadline,
                keyboardType: TextInputType.number,
                decoration: InputDecoration(
                  labelText: 'owner_rules_cancel_deadline'.tr(),
                  suffixText: 'min',
                ),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _grace,
                keyboardType: TextInputType.number,
                decoration: InputDecoration(
                  labelText: 'owner_rules_no_show_grace'.tr(),
                  suffixText: 'min',
                ),
              ),
              if (_error != null) ...[
                const SizedBox(height: 12),
                Text(
                  _error!,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: RezeraColors.danger,
                  ),
                ),
              ],
              const SizedBox(height: 20),
              FilledButton(
                onPressed: _saving ? null : _save,
                child: _saving
                    ? const SizedBox(
                        width: 22,
                        height: 22,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : Text('owner_rules_save'.tr()),
              ),
            ],
          );
        },
      ),
    );
  }
}
