import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/formatters/formatters.dart';
import '../../../core/theme/rezera_theme.dart';
import '../../../core/widgets/rezera_error_view.dart';
import 'owner_providers.dart';

class OwnerResourcesScreen extends ConsumerWidget {
  const OwnerResourcesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(ownerResourcesProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: Text('owner_resources_title'.tr())),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () async {
          final businessId = ref.read(selectedBusinessIdProvider);
          if (businessId == null) return;
          await context.push('/owner/resources/new');
          ref.invalidate(ownerResourcesProvider);
        },
        icon: const Icon(Icons.add_rounded),
        label: Text('owner_resource_add'.tr()),
      ),
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => RezeraErrorView(
          error: e,
          onRetry: () => ref.invalidate(ownerResourcesProvider),
        ),
        data: (items) {
          if (items.isEmpty) {
            return Center(child: Text('owner_resources_empty'.tr()));
          }
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(ownerResourcesProvider),
            child: ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 88),
              itemCount: items.length,
              separatorBuilder: (context, index) => const SizedBox(height: 10),
              itemBuilder: (context, index) {
                final item = items[index];
                return Material(
                  color: Colors.white.withValues(alpha: 0.88),
                  borderRadius: BorderRadius.circular(14),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(14),
                    onTap: () async {
                      await context.push('/owner/resources/${item.id}/edit', extra: item);
                      ref.invalidate(ownerResourcesProvider);
                    },
                    child: Padding(
                      padding: const EdgeInsets.all(14),
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  item.name,
                                  style: theme.textTheme.titleMedium?.copyWith(
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                                if (item.code != null) Text(item.code!),
                                if (item.categoryName != null)
                                  Text(
                                    item.categoryName!,
                                    style: theme.textTheme.bodySmall,
                                  ),
                                if (item.status != null) ...[
                                  const SizedBox(height: 6),
                                  _ResourceStatusBadge(status: item.status!),
                                ],
                              ],
                            ),
                          ),
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.end,
                            children: [
                              Text(
                                MoneyFormat.uzs(item.price),
                                style: theme.textTheme.titleMedium?.copyWith(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              IconButton(
                                tooltip: 'owner_resource_qr'.tr(),
                                onPressed: () => context.push(
                                  '/owner/resources/${item.id}/qr',
                                  extra: item,
                                ),
                                icon: const Icon(Icons.qr_code_2_rounded),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}

class _ResourceStatusBadge extends StatelessWidget {
  const _ResourceStatusBadge({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final key = 'owner_status_$status';
    final label = key.tr();
    final color = switch (status) {
      'active' => RezeraColors.seafoam,
      'maintenance' => RezeraColors.amber,
      'inactive' => RezeraColors.slate,
      _ => RezeraColors.slate,
    };
    return Align(
      alignment: Alignment.centerLeft,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.15),
          borderRadius: BorderRadius.circular(8),
        ),
        child: Text(
          label == key ? status : label,
          style: Theme.of(context).textTheme.labelMedium?.copyWith(
                color: color,
                fontWeight: FontWeight.w700,
              ),
        ),
      ),
    );
  }
}
