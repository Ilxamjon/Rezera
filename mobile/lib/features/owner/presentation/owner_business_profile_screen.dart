import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/app_failure.dart';
import '../../../core/navigation/rezera_nav.dart';
import '../../../core/theme/rezera_theme.dart';
import '../../../core/widgets/rezera_error_view.dart';
import '../../auth/presentation/session_controller.dart';
import '../data/owner_models.dart';
import 'owner_providers.dart';

final ownerBusinessProfileProvider =
    FutureProvider.autoDispose<BusinessProfile>((ref) async {
  final businessId = ref.watch(selectedBusinessIdProvider);
  if (businessId == null) {
    throw const UnknownFailure('error_unknown');
  }
  return ref.watch(ownerRepositoryProvider).businessProfile(businessId);
});

class OwnerBusinessProfileScreen extends ConsumerStatefulWidget {
  const OwnerBusinessProfileScreen({super.key});

  @override
  ConsumerState<OwnerBusinessProfileScreen> createState() =>
      _OwnerBusinessProfileScreenState();
}

class _OwnerBusinessProfileScreenState
    extends ConsumerState<OwnerBusinessProfileScreen> {
  final _name = TextEditingController();
  final _phone = TextEditingController();
  final _email = TextEditingController();
  final _city = TextEditingController();
  final _district = TextEditingController();
  final _address = TextEditingController();
  final _description = TextEditingController();
  bool _hydrated = false;
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    _email.dispose();
    _city.dispose();
    _district.dispose();
    _address.dispose();
    _description.dispose();
    super.dispose();
  }

  void _fill(BusinessProfile profile) {
    if (_hydrated) return;
    _name.text = profile.name;
    _phone.text = profile.phone ?? '';
    _email.text = profile.email ?? '';
    _city.text = profile.city ?? '';
    _district.text = profile.district ?? '';
    _address.text = profile.addressLine ?? '';
    _description.text = profile.description ?? '';
    _hydrated = true;
  }

  Future<void> _save() async {
    final businessId = ref.read(selectedBusinessIdProvider);
    if (businessId == null) return;
    final name = _name.text.trim();
    if (name.isEmpty) {
      setState(() => _error = 'error_validation'.tr());
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await ref.read(ownerRepositoryProvider).updateBusinessProfile(
            businessId: businessId,
            payload: {
              'name': name,
              'phone': _phone.text.trim().isEmpty ? null : _phone.text.trim(),
              'email': _email.text.trim().isEmpty ? null : _email.text.trim(),
              'city': _city.text.trim().isEmpty ? null : _city.text.trim(),
              'district':
                  _district.text.trim().isEmpty ? null : _district.text.trim(),
              'address_line':
                  _address.text.trim().isEmpty ? null : _address.text.trim(),
              'description': _description.text.trim().isEmpty
                  ? null
                  : _description.text.trim(),
            },
          );
      ref.invalidate(ownerBusinessProfileProvider);
      await ref.read(sessionControllerProvider.notifier).refreshUser(
            selectBusinessId: businessId,
          );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('owner_profile_saved'.tr())),
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
    final async = ref.watch(ownerBusinessProfileProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        leading: rezeraBackButton(context, fallback: '/owner/more'),
        title: Text('owner_profile_title'.tr()),
      ),
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => RezeraErrorView(
          error: e,
          onRetry: () {
            _hydrated = false;
            ref.invalidate(ownerBusinessProfileProvider);
          },
        ),
        data: (profile) {
          _fill(profile);
          return ListView(
            padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
            children: [
              Text(
                'owner_profile_hint'.tr(),
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: RezeraColors.slate.withValues(alpha: 0.85),
                ),
              ),
              const SizedBox(height: 16),
              TextField(
                controller: _name,
                decoration: InputDecoration(labelText: 'biz_name'.tr()),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _phone,
                keyboardType: TextInputType.phone,
                decoration: InputDecoration(labelText: 'biz_phone'.tr()),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _email,
                keyboardType: TextInputType.emailAddress,
                decoration: InputDecoration(labelText: 'owner_profile_email'.tr()),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _city,
                decoration: InputDecoration(labelText: 'biz_city'.tr()),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _district,
                decoration: InputDecoration(labelText: 'biz_district'.tr()),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _address,
                decoration: InputDecoration(labelText: 'biz_address'.tr()),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _description,
                maxLines: 3,
                decoration:
                    InputDecoration(labelText: 'owner_profile_description'.tr()),
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
                    : Text('owner_profile_save'.tr()),
              ),
            ],
          );
        },
      ),
    );
  }
}
