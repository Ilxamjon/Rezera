import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/app_failure.dart';
import '../../../core/navigation/rezera_nav.dart';
import '../../../core/theme/rezera_theme.dart';
import '../../../core/widgets/rezera_error_view.dart';
import '../data/owner_models.dart';
import 'owner_providers.dart';

final ownerWorkingHoursProvider =
    FutureProvider.autoDispose<List<WorkingHourDay>>((ref) async {
  final businessId = ref.watch(selectedBusinessIdProvider);
  if (businessId == null) {
    throw const UnknownFailure('error_unknown');
  }
  return ref.watch(ownerRepositoryProvider).workingHours(businessId);
});

class OwnerWorkingHoursScreen extends ConsumerStatefulWidget {
  const OwnerWorkingHoursScreen({super.key});

  @override
  ConsumerState<OwnerWorkingHoursScreen> createState() =>
      _OwnerWorkingHoursScreenState();
}

class _OwnerWorkingHoursScreenState
    extends ConsumerState<OwnerWorkingHoursScreen> {
  List<WorkingHourDay>? _days;
  bool _saving = false;
  String? _error;

  void _hydrate(List<WorkingHourDay> loaded) {
    if (_days != null) return;
    final byWeekday = {for (final d in loaded) d.weekday: d};
    _days = List.generate(7, (i) {
      final weekday = i + 1;
      final existing = byWeekday[weekday];
      if (existing != null) {
        return WorkingHourDay(
          weekday: weekday,
          isClosed: existing.isClosed,
          isOpen24h: existing.isOpen24h,
          opensAt: existing.opensAt,
          closesAt: existing.closesAt,
        );
      }
      return WorkingHourDay(weekday: weekday, isClosed: true);
    });
  }

  Future<void> _save() async {
    final businessId = ref.read(selectedBusinessIdProvider);
    final days = _days;
    if (businessId == null || days == null) return;
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await ref.read(ownerRepositoryProvider).updateWorkingHours(
            businessId: businessId,
            workingHours: days.map((d) => d.toJson()).toList(),
          );
      ref.invalidate(ownerWorkingHoursProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('owner_hours_saved'.tr())),
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

  TimeOfDay _parseHm(String value) {
    final parts = value.split(':');
    return TimeOfDay(
      hour: int.tryParse(parts.first) ?? 9,
      minute: parts.length > 1 ? int.tryParse(parts[1]) ?? 0 : 0,
    );
  }

  String _fmtHm(TimeOfDay t) =>
      '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(ownerWorkingHoursProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        leading: rezeraBackButton(context, fallback: '/owner/more'),
        title: Text('owner_hours_title'.tr()),
      ),
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => RezeraErrorView(
          error: e,
          onRetry: () => ref.invalidate(ownerWorkingHoursProvider),
        ),
        data: (loaded) {
          _hydrate(loaded);
          final days = _days!;
          return ListView(
            padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
            children: [
              Text(
                'owner_hours_hint'.tr(),
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: RezeraColors.slate.withValues(alpha: 0.85),
                ),
              ),
              const SizedBox(height: 16),
              for (final day in days) ...[
                Row(
                  children: [
                    Expanded(
                      flex: 2,
                      child: Text('weekday_${day.weekday}'.tr()),
                    ),
                    Switch(
                      value: !day.isClosed,
                      onChanged: (open) => setState(() {
                        day.isClosed = !open;
                        if (open) day.isOpen24h = false;
                      }),
                    ),
                    Text(day.isClosed ? 'biz_closed'.tr() : ''),
                  ],
                ),
                if (!day.isClosed) ...[
                  CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    title: Text('owner_hours_24h'.tr()),
                    value: day.isOpen24h,
                    onChanged: (v) => setState(() {
                      day.isOpen24h = v ?? false;
                    }),
                  ),
                  if (!day.isOpen24h)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: Row(
                        children: [
                          Expanded(
                            child: OutlinedButton(
                              onPressed: () async {
                                final t = await showTimePicker(
                                  context: context,
                                  initialTime: _parseHm(day.opensAt),
                                );
                                if (t != null) {
                                  setState(() => day.opensAt = _fmtHm(t));
                                }
                              },
                              child: Text('${'biz_opens'.tr()}: ${day.opensAt}'),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: OutlinedButton(
                              onPressed: () async {
                                final t = await showTimePicker(
                                  context: context,
                                  initialTime: _parseHm(day.closesAt),
                                );
                                if (t != null) {
                                  setState(() => day.closesAt = _fmtHm(t));
                                }
                              },
                              child:
                                  Text('${'biz_closes'.tr()}: ${day.closesAt}'),
                            ),
                          ),
                        ],
                      ),
                    ),
                ],
              ],
              if (_error != null) ...[
                const SizedBox(height: 8),
                Text(
                  _error!,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: RezeraColors.danger,
                  ),
                ),
              ],
              const SizedBox(height: 16),
              FilledButton(
                onPressed: _saving ? null : _save,
                child: _saving
                    ? const SizedBox(
                        width: 22,
                        height: 22,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : Text('owner_hours_save'.tr()),
              ),
            ],
          );
        },
      ),
    );
  }
}
