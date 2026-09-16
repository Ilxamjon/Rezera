import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/app_failure.dart';
import '../../../core/navigation/rezera_nav.dart';
import '../../../core/widgets/rezera_error_view.dart';
import '../../promos/data/promo_repository.dart';
import '../../promos/presentation/promo_providers.dart';
import 'owner_providers.dart';

final ownerPromosProvider =
    FutureProvider.autoDispose<List<PromoCodeItem>>((ref) async {
  final businessId = ref.watch(selectedBusinessIdProvider);
  if (businessId == null) throw const UnknownFailure('error_unknown');
  return ref.watch(promoRepositoryProvider).list(businessId);
});

class OwnerPromosScreen extends ConsumerStatefulWidget {
  const OwnerPromosScreen({super.key});

  @override
  ConsumerState<OwnerPromosScreen> createState() => _OwnerPromosScreenState();
}

class _OwnerPromosScreenState extends ConsumerState<OwnerPromosScreen> {
  Future<void> _create() async {
    final code = TextEditingController();
    final name = TextEditingController();
    final value = TextEditingController(text: '10');
    var type = 'percentage';
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setLocal) {
            return AlertDialog(
              title: Text('owner_promo_create'.tr()),
              content: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  TextField(
                    controller: code,
                    decoration:
                        InputDecoration(labelText: 'owner_promo_code'.tr()),
                  ),
                  TextField(
                    controller: name,
                    decoration:
                        InputDecoration(labelText: 'owner_promo_name'.tr()),
                  ),
                  DropdownButtonFormField<String>(
                    value: type,
                    items: [
                      DropdownMenuItem(
                        value: 'percentage',
                        child: Text('owner_promo_percent'.tr()),
                      ),
                      DropdownMenuItem(
                        value: 'fixed',
                        child: Text('owner_promo_fixed'.tr()),
                      ),
                    ],
                    onChanged: (v) => setLocal(() => type = v ?? 'percentage'),
                  ),
                  TextField(
                    controller: value,
                    keyboardType: TextInputType.number,
                    decoration:
                        InputDecoration(labelText: 'owner_promo_value'.tr()),
                  ),
                ],
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(context, false),
                  child: Text('common_cancel'.tr()),
                ),
                FilledButton(
                  onPressed: () => Navigator.pop(context, true),
                  child: Text('owner_promo_save'.tr()),
                ),
              ],
            );
          },
        );
      },
    );
    final businessId = ref.read(selectedBusinessIdProvider);
    if (ok != true || businessId == null) {
      code.dispose();
      name.dispose();
      value.dispose();
      return;
    }
    try {
      await ref.read(promoRepositoryProvider).create(
            businessId: businessId,
            code: code.text.trim(),
            name: name.text.trim(),
            discountType: type,
            discountValue: int.tryParse(value.text.trim()) ?? 10,
          );
      ref.invalidate(ownerPromosProvider);
    } on AppFailure catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.message.tr())),
        );
      }
    } finally {
      code.dispose();
      name.dispose();
      value.dispose();
    }
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(ownerPromosProvider);
    return Scaffold(
      appBar: AppBar(
        leading: rezeraBackButton(context, fallback: '/owner/more'),
        title: Text('owner_promo_title'.tr()),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _create,
        icon: const Icon(Icons.add),
        label: Text('owner_promo_create'.tr()),
      ),
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => RezeraErrorView(
          error: e,
          onRetry: () => ref.invalidate(ownerPromosProvider),
        ),
        data: (items) {
          if (items.isEmpty) {
            return Center(child: Text('owner_promo_empty'.tr()));
          }
          return ListView.separated(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
            itemCount: items.length,
            separatorBuilder: (_, _) => const SizedBox(height: 8),
            itemBuilder: (context, index) {
              final item = items[index];
              return ListTile(
                tileColor: Colors.white.withValues(alpha: 0.9),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
                title: Text(item.code),
                subtitle: Text(
                  '${item.name} · ${item.discountType == 'percentage' ? '${item.discountValue}%' : item.discountValue}',
                ),
                trailing: IconButton(
                  icon: const Icon(Icons.delete_outline),
                  onPressed: () async {
                    final businessId = ref.read(selectedBusinessIdProvider);
                    if (businessId == null) return;
                    await ref.read(promoRepositoryProvider).delete(
                          businessId: businessId,
                          promoId: item.id,
                        );
                    ref.invalidate(ownerPromosProvider);
                  },
                ),
              );
            },
          );
        },
      ),
    );
  }
}
