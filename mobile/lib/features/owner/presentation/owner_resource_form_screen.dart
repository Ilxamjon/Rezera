import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/app_failure.dart';
import '../../../core/navigation/rezera_nav.dart';
import '../../../core/theme/rezera_theme.dart';
import '../data/owner_models.dart';
import 'owner_providers.dart';

class OwnerResourceFormScreen extends ConsumerStatefulWidget {
  const OwnerResourceFormScreen({super.key, this.existing});

  final OwnerResource? existing;

  bool get isEdit => existing != null;

  @override
  ConsumerState<OwnerResourceFormScreen> createState() =>
      _OwnerResourceFormScreenState();
}

class _OwnerResourceFormScreenState
    extends ConsumerState<OwnerResourceFormScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _code;
  late final TextEditingController _price;
  late final TextEditingController _description;

  String _type = 'pc';
  String _status = 'active';
  String? _categoryId;
  List<ResourceCategoryOption> _categories = const [];
  bool _loading = false;
  bool _bootstrapping = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    final e = widget.existing;
    _name = TextEditingController(text: e?.name ?? '');
    _code = TextEditingController(text: e?.code ?? '');
    _price = TextEditingController(text: e?.price?.toString() ?? '');
    _description = TextEditingController(text: e?.description ?? '');
    _type = e?.resourceType ?? 'pc';
    _status = e?.status ?? 'active';
    _categoryId = e?.resourceCategoryId;
    Future.microtask(_loadCategories);
  }

  @override
  void dispose() {
    _name.dispose();
    _code.dispose();
    _price.dispose();
    _description.dispose();
    super.dispose();
  }

  Future<void> _loadCategories() async {
    final businessId = ref.read(selectedBusinessIdProvider);
    if (businessId == null) {
      setState(() => _bootstrapping = false);
      return;
    }
    try {
      final cats =
          await ref.read(ownerRepositoryProvider).resourceCategories(businessId);
      setState(() {
        _categories = cats;
        _bootstrapping = false;
      });
    } catch (_) {
      setState(() => _bootstrapping = false);
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    final businessId = ref.read(selectedBusinessIdProvider);
    if (businessId == null) return;

    setState(() {
      _loading = true;
      _error = null;
    });

    final payload = <String, dynamic>{
      'name': _name.text.trim(),
      'code': _code.text.trim(),
      'price': int.parse(_price.text.trim()),
      'resource_type': _type,
      'status': _status,
      'description': _description.text.trim().isEmpty
          ? null
          : _description.text.trim(),
      if (_categoryId != null) 'resource_category_id': _categoryId,
    };

    try {
      final repo = ref.read(ownerRepositoryProvider);
      if (widget.isEdit) {
        await repo.updateResource(
          businessId: businessId,
          resourceId: widget.existing!.id,
          payload: payload,
        );
      } else {
        await repo.createResource(businessId: businessId, payload: payload);
      }
      if (mounted) context.pop(true);
    } on AppFailure catch (e) {
      setState(() => _error = e.message.tr());
    } catch (_) {
      setState(() => _error = 'error_unknown'.tr());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _delete() async {
    final businessId = ref.read(selectedBusinessIdProvider);
    if (businessId == null || widget.existing == null) return;
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('owner_resource_delete_title'.tr()),
        content: Text('owner_resource_delete_body'.tr()),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text('bookings_cancel_no'.tr()),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text('owner_resource_delete'.tr()),
          ),
        ],
      ),
    );
    if (ok != true) return;

    try {
      await ref.read(ownerRepositoryProvider).deleteResource(
            businessId: businessId,
            resourceId: widget.existing!.id,
          );
      if (mounted) context.pop(true);
    } on AppFailure catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.message.tr())),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(
          widget.isEdit
              ? 'owner_resource_edit'.tr()
              : 'owner_resource_add'.tr(),
        ),
        leading: rezeraBackButton(context, fallback: '/owner/resources'),
        actions: [
          if (widget.isEdit)
            IconButton(
              onPressed: _loading ? null : _delete,
              icon: const Icon(Icons.delete_outline_rounded),
            ),
        ],
      ),
      body: _bootstrapping
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(20),
              children: [
                Form(
                  key: _formKey,
                  child: Column(
                    children: [
                      TextFormField(
                        controller: _name,
                        decoration: InputDecoration(
                          labelText: 'owner_resource_name'.tr(),
                        ),
                        validator: (v) =>
                            (v == null || v.trim().isEmpty) ? '…' : null,
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _code,
                        decoration: InputDecoration(
                          labelText: 'owner_resource_code'.tr(),
                        ),
                        validator: (v) =>
                            (v == null || v.trim().isEmpty) ? '…' : null,
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _price,
                        keyboardType: TextInputType.number,
                        decoration: InputDecoration(
                          labelText: 'owner_resource_price'.tr(),
                        ),
                        validator: (v) {
                          final n = int.tryParse(v ?? '');
                          if (n == null || n < 0) return '…';
                          return null;
                        },
                      ),
                      const SizedBox(height: 12),
                      DropdownButtonFormField<String>(
                        initialValue: _type,
                        decoration: InputDecoration(
                          labelText: 'owner_resource_type'.tr(),
                        ),
                        items: const [
                          DropdownMenuItem(value: 'pc', child: Text('PC')),
                          DropdownMenuItem(
                            value: 'console',
                            child: Text('Console'),
                          ),
                          DropdownMenuItem(value: 'room', child: Text('Room')),
                          DropdownMenuItem(value: 'table', child: Text('Table')),
                          DropdownMenuItem(value: 'desk', child: Text('Desk')),
                          DropdownMenuItem(value: 'court', child: Text('Court')),
                          DropdownMenuItem(value: 'other', child: Text('Other')),
                        ],
                        onChanged: (v) => setState(() => _type = v ?? 'pc'),
                      ),
                      const SizedBox(height: 12),
                      DropdownButtonFormField<String>(
                        initialValue: _status,
                        decoration: InputDecoration(
                          labelText: 'owner_resource_status'.tr(),
                        ),
                        items: [
                          DropdownMenuItem(
                            value: 'active',
                            child: Text('owner_status_active'.tr()),
                          ),
                          DropdownMenuItem(
                            value: 'inactive',
                            child: Text('owner_status_inactive'.tr()),
                          ),
                          DropdownMenuItem(
                            value: 'maintenance',
                            child: Text('owner_status_maintenance'.tr()),
                          ),
                        ],
                        onChanged: (v) =>
                            setState(() => _status = v ?? 'active'),
                      ),
                      if (_categories.isNotEmpty) ...[
                        const SizedBox(height: 12),
                        DropdownButtonFormField<String?>(
                          initialValue: _categoryId,
                          decoration: InputDecoration(
                            labelText: 'owner_resource_category'.tr(),
                          ),
                          items: [
                            DropdownMenuItem<String?>(
                              value: null,
                              child: Text('owner_resource_category_none'.tr()),
                            ),
                            ..._categories.map(
                              (c) => DropdownMenuItem<String?>(
                                value: c.id,
                                child: Text(c.name),
                              ),
                            ),
                          ],
                          onChanged: (v) => setState(() => _categoryId = v),
                        ),
                      ],
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _description,
                        maxLines: 3,
                        decoration: InputDecoration(
                          labelText: 'owner_resource_description'.tr(),
                        ),
                      ),
                    ],
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
                const SizedBox(height: 24),
                FilledButton(
                  onPressed: _loading ? null : _submit,
                  child: _loading
                      ? const SizedBox(
                          width: 22,
                          height: 22,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : Text('owner_resource_save'.tr()),
                ),
              ],
            ),
    );
  }
}
