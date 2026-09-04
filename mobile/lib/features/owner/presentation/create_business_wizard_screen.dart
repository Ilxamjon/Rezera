import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/app_failure.dart';
import '../../../core/formatters/formatters.dart';
import '../../../core/network/api_client.dart';
import '../../../core/theme/rezera_theme.dart';
import '../../auth/presentation/session_controller.dart';
import '../data/business_setup_repository.dart';

final businessSetupRepositoryProvider = Provider<BusinessSetupRepository>((ref) {
  return BusinessSetupRepository(ref.watch(apiClientProvider));
});

class CreateBusinessWizardScreen extends ConsumerStatefulWidget {
  const CreateBusinessWizardScreen({super.key});

  @override
  ConsumerState<CreateBusinessWizardScreen> createState() =>
      _CreateBusinessWizardScreenState();
}

class _CreateBusinessWizardScreenState
    extends ConsumerState<CreateBusinessWizardScreen> {
  final _page = PageController();
  final _formProfile = GlobalKey<FormState>();
  final _formResource = GlobalKey<FormState>();

  final _name = TextEditingController();
  final _description = TextEditingController();
  final _phone = TextEditingController();
  final _city = TextEditingController(text: 'Toshkent');
  final _district = TextEditingController();
  final _address = TextEditingController();
  final _resourceName = TextEditingController();
  final _resourceCode = TextEditingController(text: 'R-01');
  final _resourcePrice = TextEditingController(text: '25000');

  List<BusinessCategoryOption> _categories = const [];
  String? _categoryId;
  String? _categorySlug;
  String? _businessId;
  String? _businessName;
  bool _loadingCats = true;
  bool _submitting = false;
  String? _error;
  int _step = 0;

  /// ISO weekday 1–7 → closed flag + open/close.
  late final List<_HourDay> _hours = List.generate(
    7,
    (i) => _HourDay(weekday: i + 1),
  );

  @override
  void initState() {
    super.initState();
    Future.microtask(_loadCategories);
  }

  @override
  void dispose() {
    _page.dispose();
    _name.dispose();
    _description.dispose();
    _phone.dispose();
    _city.dispose();
    _district.dispose();
    _address.dispose();
    _resourceName.dispose();
    _resourceCode.dispose();
    _resourcePrice.dispose();
    super.dispose();
  }

  Future<void> _loadCategories() async {
    try {
      final cats = await ref.read(businessSetupRepositoryProvider).categories();
      setState(() {
        _categories = cats;
        if (cats.isNotEmpty) {
          _categoryId = cats.first.id;
          _categorySlug = cats.first.slug;
          _resourceName.text = _defaultResourceName(cats.first.slug);
        }
        _loadingCats = false;
      });
    } catch (e) {
      setState(() {
        _loadingCats = false;
        _error = e is AppFailure ? e.message : 'error_unknown';
      });
    }
  }

  String _defaultResourceName(String slug) => switch (slug) {
        'restaurant' || 'cafe' => 'Stol 1',
        'coworking' => 'Stol 1',
        'sports_facility' => 'Maydon 1',
        'playstation_club' => 'PS 1',
        _ => 'PC-01',
      };

  String _defaultResourceType(String? slug) => switch (slug) {
        'restaurant' || 'cafe' => 'table',
        'coworking' => 'desk',
        'sports_facility' => 'court',
        'playstation_club' => 'console',
        _ => 'pc',
      };

  Future<void> _goNext() async {
    setState(() => _error = null);
    if (_step == 0) {
      if (!_formProfile.currentState!.validate()) return;
      if (_categoryId == null) return;
      setState(() => _submitting = true);
      try {
        final created =
            await ref.read(businessSetupRepositoryProvider).createBusiness({
          'category_id': _categoryId,
          'name': _name.text.trim(),
          'description': _description.text.trim(),
          'phone': PhoneFormat.toE164(_phone.text),
          'city': _city.text.trim(),
          'district': _district.text.trim().isEmpty
              ? null
              : _district.text.trim(),
          'address_line': _address.text.trim(),
          'country_code': 'UZ',
          'timezone': 'Asia/Tashkent',
          // Tashkent center — enough for beta onboarding location step.
          'latitude': 41.3111,
          'longitude': 69.2797,
        });
        _businessId = created.id;
        _businessName = created.name;
        setState(() {
          _submitting = false;
          _step = 1;
        });
        await _page.nextPage(
          duration: const Duration(milliseconds: 280),
          curve: Curves.easeOut,
        );
      } on AppFailure catch (e) {
        setState(() {
          _submitting = false;
          _error = e.message;
        });
      } catch (_) {
        setState(() {
          _submitting = false;
          _error = 'error_unknown';
        });
      }
      return;
    }

    if (_step == 1) {
      final businessId = _businessId;
      if (businessId == null) return;
      setState(() => _submitting = true);
      try {
        final payload = _hours.map((h) {
          if (h.closed) {
            return {
              'weekday': h.weekday,
              'is_closed': true,
              'is_open_24h': false,
              'opens_at': null,
              'closes_at': null,
            };
          }
          return {
            'weekday': h.weekday,
            'is_closed': false,
            'is_open_24h': false,
            'opens_at': h.opens,
            'closes_at': h.closes,
          };
        }).toList();
        await ref.read(businessSetupRepositoryProvider).updateWorkingHours(
              businessId: businessId,
              workingHours: payload,
            );
        setState(() {
          _submitting = false;
          _step = 2;
        });
        await _page.nextPage(
          duration: const Duration(milliseconds: 280),
          curve: Curves.easeOut,
        );
      } on AppFailure catch (e) {
        setState(() {
          _submitting = false;
          _error = e.message;
        });
      } catch (_) {
        setState(() {
          _submitting = false;
          _error = 'error_unknown';
        });
      }
      return;
    }

    if (_step == 2) {
      if (!_formResource.currentState!.validate()) return;
      final businessId = _businessId;
      if (businessId == null) return;
      setState(() => _submitting = true);
      try {
        final repo = ref.read(businessSetupRepositoryProvider);
        await repo.createResource(
          businessId: businessId,
          payload: {
            'name': _resourceName.text.trim(),
            'code': _resourceCode.text.trim(),
            'price': int.parse(_resourcePrice.text.trim()),
            'resource_type': _defaultResourceType(_categorySlug),
            'status': 'active',
          },
        );
        try {
          await repo.submitVerification(businessId);
        } catch (_) {
          // Onboarding may still need platform review; resource is enough for ops.
        }
        await ref.read(sessionControllerProvider.notifier).refreshUser(
              selectBusinessId: businessId,
            );
        ref.read(sessionControllerProvider.notifier).enterOwnerMode(
              businessId: businessId,
            );
        setState(() {
          _submitting = false;
          _step = 3;
        });
        await _page.nextPage(
          duration: const Duration(milliseconds: 280),
          curve: Curves.easeOut,
        );
      } on AppFailure catch (e) {
        setState(() {
          _submitting = false;
          _error = e.message;
        });
      } catch (_) {
        setState(() {
          _submitting = false;
          _error = 'error_unknown';
        });
      }
    }
  }

  void _goBack() {
    if (_step <= 0 || _step >= 3) return;
    setState(() {
      _error = null;
      _step -= 1;
    });
    _page.previousPage(
      duration: const Duration(milliseconds: 280),
      curve: Curves.easeOut,
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Text('biz_create_title'.tr()),
        leading: IconButton(
          icon: const Icon(Icons.close),
          onPressed: () => context.pop(),
        ),
      ),
      body: _loadingCats
          ? const Center(child: CircularProgressIndicator())
          : Column(
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 8, 20, 0),
                  child: Row(
                    children: List.generate(4, (i) {
                      final active = i <= _step;
                      return Expanded(
                        child: Container(
                          height: 4,
                          margin: EdgeInsets.only(right: i < 3 ? 6 : 0),
                          decoration: BoxDecoration(
                            color: active
                                ? RezeraColors.ink
                                : RezeraColors.mist,
                            borderRadius: BorderRadius.circular(4),
                          ),
                        ),
                      );
                    }),
                  ),
                ),
                if (_error != null)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(20, 12, 20, 0),
                    child: Text(
                      _error!.tr(),
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: RezeraColors.danger,
                      ),
                    ),
                  ),
                Expanded(
                  child: PageView(
                    controller: _page,
                    physics: const NeverScrollableScrollPhysics(),
                    children: [
                      _buildProfileStep(theme),
                      _buildHoursStep(theme),
                      _buildResourceStep(theme),
                      _buildDoneStep(theme),
                    ],
                  ),
                ),
                SafeArea(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
                    child: Row(
                      children: [
                        if (_step > 0 && _step < 3)
                          OutlinedButton(
                            onPressed: _submitting ? null : _goBack,
                            child: Text('biz_back'.tr()),
                          ),
                        if (_step > 0 && _step < 3) const SizedBox(width: 12),
                        Expanded(
                          child: FilledButton(
                            onPressed: _submitting
                                ? null
                                : () {
                                    if (_step == 3) {
                                      context.go('/owner/dashboard');
                                      return;
                                    }
                                    _goNext();
                                  },
                            child: _submitting
                                ? const SizedBox(
                                    width: 22,
                                    height: 22,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  )
                                : Text(
                                    _step == 3
                                        ? 'biz_open_owner'.tr()
                                        : (_step == 2
                                            ? 'biz_finish'.tr()
                                            : 'biz_next'.tr()),
                                  ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
    );
  }

  Widget _buildProfileStep(ThemeData theme) {
    return Form(
      key: _formProfile,
      child: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Text('biz_step_profile'.tr(), style: theme.textTheme.titleLarge),
          const SizedBox(height: 16),
          Text('biz_category'.tr(), style: theme.textTheme.labelLarge),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final cat in _categories)
                ChoiceChip(
                  label: Text(cat.name),
                  selected: _categoryId == cat.id,
                  onSelected: (_) {
                    setState(() {
                      _categoryId = cat.id;
                      _categorySlug = cat.slug;
                      if (_resourceName.text.isEmpty ||
                          _resourceName.text.startsWith('PC') ||
                          _resourceName.text.startsWith('Stol') ||
                          _resourceName.text.startsWith('PS') ||
                          _resourceName.text.startsWith('Maydon')) {
                        _resourceName.text = _defaultResourceName(cat.slug);
                      }
                    });
                  },
                ),
            ],
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _name,
            decoration: InputDecoration(labelText: 'biz_name'.tr()),
            validator: (v) =>
                (v == null || v.trim().length < 2) ? '…' : null,
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _description,
            maxLines: 3,
            decoration: InputDecoration(labelText: 'biz_description'.tr()),
            validator: (v) =>
                (v == null || v.trim().length < 8) ? '…' : null,
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _phone,
            keyboardType: TextInputType.phone,
            decoration: InputDecoration(
              labelText: 'biz_phone'.tr(),
              hintText: 'auth_phone_hint'.tr(),
            ),
            validator: (v) =>
                (v == null || v.trim().length < 9) ? '…' : null,
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _city,
            decoration: InputDecoration(labelText: 'biz_city'.tr()),
            validator: (v) =>
                (v == null || v.trim().isEmpty) ? '…' : null,
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _district,
            decoration: InputDecoration(labelText: 'biz_district'.tr()),
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _address,
            decoration: InputDecoration(labelText: 'biz_address'.tr()),
            validator: (v) =>
                (v == null || v.trim().length < 5) ? '…' : null,
          ),
        ],
      ),
    );
  }

  Widget _buildHoursStep(ThemeData theme) {
    return ListView(
      padding: const EdgeInsets.all(20),
      children: [
        Text('biz_step_hours'.tr(), style: theme.textTheme.titleLarge),
        const SizedBox(height: 8),
        Text(
          'biz_hours_hint'.tr(),
          style: theme.textTheme.bodyMedium?.copyWith(
            color: RezeraColors.slate.withValues(alpha: 0.8),
          ),
        ),
        const SizedBox(height: 16),
        for (final day in _hours) ...[
          Row(
            children: [
              Expanded(
                flex: 2,
                child: Text('weekday_${day.weekday}'.tr()),
              ),
              Switch(
                value: !day.closed,
                onChanged: (open) => setState(() => day.closed = !open),
              ),
              Text(day.closed ? 'biz_closed'.tr() : ''),
            ],
          ),
          if (!day.closed)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () async {
                        final t = await showTimePicker(
                          context: context,
                          initialTime: _parseHm(day.opens),
                        );
                        if (t != null) {
                          setState(() => day.opens = _fmtHm(t));
                        }
                      },
                      child: Text('${'biz_opens'.tr()}: ${day.opens}'),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () async {
                        final t = await showTimePicker(
                          context: context,
                          initialTime: _parseHm(day.closes),
                        );
                        if (t != null) {
                          setState(() => day.closes = _fmtHm(t));
                        }
                      },
                      child: Text('${'biz_closes'.tr()}: ${day.closes}'),
                    ),
                  ),
                ],
              ),
            ),
        ],
      ],
    );
  }

  Widget _buildResourceStep(ThemeData theme) {
    return Form(
      key: _formResource,
      child: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Text('biz_step_resource'.tr(), style: theme.textTheme.titleLarge),
          const SizedBox(height: 8),
          Text(
            'biz_resource_hint'.tr(),
            style: theme.textTheme.bodyMedium?.copyWith(
              color: RezeraColors.slate.withValues(alpha: 0.8),
            ),
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _resourceName,
            decoration: InputDecoration(labelText: 'owner_resource_name'.tr()),
            validator: (v) =>
                (v == null || v.trim().isEmpty) ? '…' : null,
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _resourceCode,
            decoration: InputDecoration(labelText: 'owner_resource_code'.tr()),
            validator: (v) =>
                (v == null || v.trim().isEmpty) ? '…' : null,
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _resourcePrice,
            keyboardType: TextInputType.number,
            decoration:
                InputDecoration(labelText: 'owner_resource_price'.tr()),
            validator: (v) {
              final n = int.tryParse(v?.trim() ?? '');
              if (n == null || n <= 0) return '…';
              return null;
            },
          ),
        ],
      ),
    );
  }

  Widget _buildDoneStep(ThemeData theme) {
    return Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.check_circle_outline, size: 56, color: Colors.green),
          const SizedBox(height: 16),
          Text('biz_success'.tr(), style: theme.textTheme.headlineSmall),
          const SizedBox(height: 8),
          Text(
            'biz_success_body'.tr(
              args: [_businessName ?? ''],
            ),
            style: theme.textTheme.bodyLarge,
          ),
        ],
      ),
    );
  }

  TimeOfDay _parseHm(String hm) {
    final parts = hm.split(':');
    return TimeOfDay(
      hour: int.parse(parts[0]),
      minute: int.parse(parts[1]),
    );
  }

  String _fmtHm(TimeOfDay t) =>
      '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';
}

class _HourDay {
  _HourDay({required this.weekday});

  final int weekday;
  bool closed = false;
  String opens = '10:00';
  String closes = '22:00';
}
