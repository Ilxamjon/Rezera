import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/app_failure.dart';
import '../../../core/formatters/formatters.dart';
import '../../../core/network/api_client.dart';
import '../../../core/theme/rezera_theme.dart';
import '../../auth/presentation/session_controller.dart';
import '../data/reservation_models.dart';
import '../data/reservation_repository.dart';

final reservationRepositoryProvider = Provider<ReservationRepository>((ref) {
  return ReservationRepository(ref.watch(apiClientProvider));
});

class BookScreen extends ConsumerStatefulWidget {
  const BookScreen({
    super.key,
    required this.businessId,
    required this.businessName,
    this.preselectedResourceId,
  });

  final String businessId;
  final String businessName;
  final String? preselectedResourceId;

  @override
  ConsumerState<BookScreen> createState() => _BookScreenState();
}

class _BookScreenState extends ConsumerState<BookScreen> {
  DateTime _date = DateTime.now().add(const Duration(days: 0));
  TimeOfDay _start = const TimeOfDay(hour: 18, minute: 0);
  TimeOfDay _end = const TimeOfDay(hour: 20, minute: 0);
  List<AvailabilityResourceRow> _rows = const [];
  String? _selectedResourceId;
  bool _checking = false;
  bool _submitting = false;
  String? _error;

  String get _dateStr => DateFormat('yyyy-MM-dd').format(_date);
  String _fmt(TimeOfDay t) =>
      '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';

  @override
  void initState() {
    super.initState();
    _selectedResourceId = widget.preselectedResourceId;
    Future.microtask(_checkAvailability);
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _date,
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 60)),
    );
    if (picked != null) {
      setState(() => _date = picked);
      await _checkAvailability();
    }
  }

  Future<void> _pickTime({required bool isStart}) async {
    final picked = await showTimePicker(
      context: context,
      initialTime: isStart ? _start : _end,
    );
    if (picked != null) {
      setState(() {
        if (isStart) {
          _start = picked;
        } else {
          _end = picked;
        }
      });
      await _checkAvailability();
    }
  }

  Future<void> _checkAvailability() async {
    setState(() {
      _checking = true;
      _error = null;
    });
    try {
      final rows = await ref.read(reservationRepositoryProvider).availability(
            businessId: widget.businessId,
            date: _dateStr,
            startTime: _fmt(_start),
            endTime: _fmt(_end),
          );
      setState(() {
        _rows = rows;
        if (_selectedResourceId == null ||
            !rows.any((r) => r.id == _selectedResourceId && r.isAvailable)) {
          final availableIds =
              rows.where((r) => r.isAvailable).map((r) => r.id).toList();
          _selectedResourceId =
              availableIds.isEmpty ? null : availableIds.first;
        }
      });
    } on AppFailure catch (e) {
      setState(() => _error = e.message.tr());
    } catch (_) {
      setState(() => _error = 'error_unknown'.tr());
    } finally {
      if (mounted) setState(() => _checking = false);
    }
  }

  Future<void> _submit() async {
    final session = ref.read(sessionControllerProvider);
    if (!session.isAuthenticated) {
      context.push('/login');
      return;
    }
    if (_selectedResourceId == null) {
      setState(() => _error = 'book_pick_resource'.tr());
      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final reservation =
          await ref.read(reservationRepositoryProvider).create(
                businessId: widget.businessId,
                resourceId: _selectedResourceId!,
                date: _dateStr,
                startTime: _fmt(_start),
                endTime: _fmt(_end),
              );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'book_success'.tr(args: [reservation.reservationNumber]),
          ),
        ),
      );
      context.go('/bookings');
    } on AppFailure catch (e) {
      setState(() => _error = e.message.tr());
      if (e is ConflictFailure) {
        await _checkAvailability();
      }
    } catch (_) {
      setState(() => _error = 'error_unknown'.tr());
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final available = _rows.where((r) => r.isAvailable).toList();

    return Scaffold(
      appBar: AppBar(title: Text('business_book'.tr())),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Text(
            widget.businessName,
            style: theme.textTheme.headlineSmall?.copyWith(
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 20),
          _PickerTile(
            label: 'book_date'.tr(),
            value: _dateStr,
            onTap: _pickDate,
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: _PickerTile(
                  label: 'book_start'.tr(),
                  value: _fmt(_start),
                  onTap: () => _pickTime(isStart: true),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _PickerTile(
                  label: 'book_end'.tr(),
                  value: _fmt(_end),
                  onTap: () => _pickTime(isStart: false),
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),
          Row(
            children: [
              Text(
                'book_available'.tr(),
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
              ),
              const Spacer(),
              if (_checking)
                const SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              else
                IconButton(
                  onPressed: _checkAvailability,
                  icon: const Icon(Icons.refresh_rounded),
                ),
            ],
          ),
          if (available.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 16),
              child: Text('book_no_slots'.tr()),
            )
          else
            ...available.map((row) {
              final selected = row.id == _selectedResourceId;
              return Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Material(
                  color: selected
                      ? RezeraColors.amber.withValues(alpha: 0.25)
                      : Colors.white.withValues(alpha: 0.8),
                  borderRadius: BorderRadius.circular(14),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(14),
                    onTap: () =>
                        setState(() => _selectedResourceId = row.id),
                    child: Padding(
                      padding: const EdgeInsets.all(14),
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  row.name,
                                  style: theme.textTheme.titleMedium
                                      ?.copyWith(fontWeight: FontWeight.w700),
                                ),
                                if (row.code != null)
                                  Text(row.code!, style: theme.textTheme.bodySmall),
                              ],
                            ),
                          ),
                          Text(
                            MoneyFormat.uzs(row.hourlyRateAmount),
                            style: theme.textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              );
            }),
          if (_error != null) ...[
            const SizedBox(height: 8),
            Text(
              _error!,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: RezeraColors.danger,
              ),
            ),
          ],
          const SizedBox(height: 20),
          FilledButton(
            onPressed: _submitting ? null : _submit,
            child: _submitting
                ? const SizedBox(
                    height: 22,
                    width: 22,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : Text('book_confirm'.tr()),
          ),
        ],
      ),
    );
  }
}

class _PickerTile extends StatelessWidget {
  const _PickerTile({
    required this.label,
    required this.value,
    required this.onTap,
  });

  final String label;
  final String value;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white.withValues(alpha: 0.8),
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: Theme.of(context).textTheme.labelMedium),
              const SizedBox(height: 4),
              Text(
                value,
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
