import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/app_failure.dart';
import '../../../core/navigation/rezera_nav.dart';
import '../../../core/network/api_client.dart';
import '../../../core/widgets/rezera_error_view.dart';
import '../data/saved_search_repository.dart';

final savedSearchRepositoryProvider = Provider<SavedSearchRepository>((ref) {
  return SavedSearchRepository(ref.watch(apiClientProvider));
});

final savedSearchesProvider =
    FutureProvider.autoDispose<List<SavedSearchItem>>((ref) async {
  return ref.watch(savedSearchRepositoryProvider).list();
});

class SavedSearchesScreen extends ConsumerStatefulWidget {
  const SavedSearchesScreen({super.key});

  @override
  ConsumerState<SavedSearchesScreen> createState() =>
      _SavedSearchesScreenState();
}

class _SavedSearchesScreenState extends ConsumerState<SavedSearchesScreen> {
  Future<void> _create() async {
    final name = TextEditingController();
    final city = TextEditingController();
    final query = TextEditingController();
    var alert = true;
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setLocal) {
            return AlertDialog(
              title: Text('saved_search_create'.tr()),
              content: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  TextField(
                    controller: name,
                    decoration:
                        InputDecoration(labelText: 'saved_search_name'.tr()),
                  ),
                  TextField(
                    controller: city,
                    decoration:
                        InputDecoration(labelText: 'saved_search_city'.tr()),
                  ),
                  TextField(
                    controller: query,
                    decoration:
                        InputDecoration(labelText: 'saved_search_query'.tr()),
                  ),
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    title: Text('saved_search_alert'.tr()),
                    value: alert,
                    onChanged: (v) => setLocal(() => alert = v),
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
                  child: Text('saved_search_save'.tr()),
                ),
              ],
            );
          },
        );
      },
    );
    if (ok != true) {
      name.dispose();
      city.dispose();
      query.dispose();
      return;
    }
    try {
      await ref.read(savedSearchRepositoryProvider).create(
            name: name.text.trim().isEmpty
                ? 'saved_search_default_name'.tr()
                : name.text.trim(),
            city: city.text.trim(),
            searchQuery: query.text.trim(),
            alertEnabled: alert,
          );
      ref.invalidate(savedSearchesProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('saved_search_created'.tr())),
        );
      }
    } on AppFailure catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.message.tr())),
        );
      }
    } finally {
      name.dispose();
      city.dispose();
      query.dispose();
    }
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(savedSearchesProvider);
    return Scaffold(
      appBar: AppBar(
        leading: rezeraBackButton(context, fallback: '/profile'),
        title: Text('saved_search_title'.tr()),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _create,
        icon: const Icon(Icons.notifications_active_outlined),
        label: Text('saved_search_create'.tr()),
      ),
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => RezeraErrorView(
          error: e,
          onRetry: () => ref.invalidate(savedSearchesProvider),
        ),
        data: (items) {
          if (items.isEmpty) {
            return Center(child: Text('saved_search_empty'.tr()));
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
                title: Text(item.name),
                subtitle: Text(
                  [
                    if (item.city != null && item.city!.isNotEmpty) item.city!,
                    if (item.searchQuery != null && item.searchQuery!.isNotEmpty)
                      item.searchQuery!,
                    item.alertEnabled
                        ? 'saved_search_alert_on'.tr()
                        : 'saved_search_alert_off'.tr(),
                  ].join(' · '),
                ),
                trailing: IconButton(
                  icon: const Icon(Icons.delete_outline),
                  onPressed: () async {
                    await ref.read(savedSearchRepositoryProvider).delete(item.id);
                    ref.invalidate(savedSearchesProvider);
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
