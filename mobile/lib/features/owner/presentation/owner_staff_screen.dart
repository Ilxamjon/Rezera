import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/app_failure.dart';
import '../../../core/navigation/rezera_nav.dart';
import '../../../core/theme/rezera_theme.dart';
import '../../../core/widgets/rezera_error_view.dart';
import '../data/owner_models.dart';
import 'owner_providers.dart';

class OwnerStaffBundle {
  const OwnerStaffBundle({
    required this.members,
    required this.invitations,
  });

  final List<StaffMember> members;
  final List<StaffInvitation> invitations;
}

final ownerStaffProvider =
    FutureProvider.autoDispose<OwnerStaffBundle>((ref) async {
  final businessId = ref.watch(selectedBusinessIdProvider);
  if (businessId == null) {
    throw const UnknownFailure('error_unknown');
  }
  final repo = ref.watch(ownerRepositoryProvider);
  final members = await repo.members(businessId);
  List<StaffInvitation> invitations = const [];
  try {
    invitations = await repo.invitations(businessId);
  } catch (_) {
    // Pending invites may be restricted for some roles.
  }
  return OwnerStaffBundle(members: members, invitations: invitations);
});

class OwnerStaffScreen extends ConsumerStatefulWidget {
  const OwnerStaffScreen({super.key});

  @override
  ConsumerState<OwnerStaffScreen> createState() => _OwnerStaffScreenState();
}

class _OwnerStaffScreenState extends ConsumerState<OwnerStaffScreen> {
  Future<void> _invite() async {
    final phone = TextEditingController();
    var role = 'staff';
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setLocal) {
            return AlertDialog(
              title: Text('owner_staff_invite'.tr()),
              content: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  TextField(
                    controller: phone,
                    keyboardType: TextInputType.phone,
                    decoration: InputDecoration(
                      labelText: 'auth_phone'.tr(),
                      hintText: 'auth_phone_hint'.tr(),
                    ),
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    initialValue: role,
                    decoration:
                        InputDecoration(labelText: 'owner_staff_role'.tr()),
                    items: [
                      DropdownMenuItem(
                        value: 'staff',
                        child: Text('owner_role_staff'.tr()),
                      ),
                      DropdownMenuItem(
                        value: 'manager',
                        child: Text('owner_role_manager'.tr()),
                      ),
                    ],
                    onChanged: (v) => setLocal(() => role = v ?? 'staff'),
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
                  child: Text('owner_staff_send_invite'.tr()),
                ),
              ],
            );
          },
        );
      },
    );
    final businessId = ref.read(selectedBusinessIdProvider);
    if (ok != true || businessId == null) {
      phone.dispose();
      return;
    }
    try {
      await ref.read(ownerRepositoryProvider).inviteMember(
            businessId: businessId,
            phone: phone.text.trim(),
            memberRole: role,
          );
      ref.invalidate(ownerStaffProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('owner_staff_invited'.tr())),
        );
      }
    } on AppFailure catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.message.tr())),
        );
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('error_unknown'.tr())),
        );
      }
    } finally {
      phone.dispose();
    }
  }

  Future<void> _changeRole(StaffMember member) async {
    if (member.isOwner) return;
    var role = member.memberRole == 'manager' ? 'manager' : 'staff';
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setLocal) {
            return AlertDialog(
              title: Text('owner_staff_change_role'.tr()),
              content: DropdownButtonFormField<String>(
                initialValue: role,
                items: [
                  DropdownMenuItem(
                    value: 'staff',
                    child: Text('owner_role_staff'.tr()),
                  ),
                  DropdownMenuItem(
                    value: 'manager',
                    child: Text('owner_role_manager'.tr()),
                  ),
                ],
                onChanged: (v) => setLocal(() => role = v ?? 'staff'),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(context, false),
                  child: Text('common_cancel'.tr()),
                ),
                FilledButton(
                  onPressed: () => Navigator.pop(context, true),
                  child: Text('owner_staff_save_role'.tr()),
                ),
              ],
            );
          },
        );
      },
    );
    final businessId = ref.read(selectedBusinessIdProvider);
    if (ok != true || businessId == null) return;
    try {
      await ref.read(ownerRepositoryProvider).updateMember(
            businessId: businessId,
            memberId: member.id,
            memberRole: role,
          );
      ref.invalidate(ownerStaffProvider);
    } on AppFailure catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.message.tr())),
        );
      }
    }
  }

  Future<void> _remove(StaffMember member) async {
    if (member.isOwner) return;
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) {
        return AlertDialog(
          title: Text('owner_staff_remove_title'.tr()),
          content: Text('owner_staff_remove_body'.tr()),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: Text('common_cancel'.tr()),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: Text('owner_staff_remove'.tr()),
            ),
          ],
        );
      },
    );
    final businessId = ref.read(selectedBusinessIdProvider);
    if (ok != true || businessId == null) return;
    try {
      await ref.read(ownerRepositoryProvider).removeMember(
            businessId: businessId,
            memberId: member.id,
          );
      ref.invalidate(ownerStaffProvider);
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
    final async = ref.watch(ownerStaffProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        leading: rezeraBackButton(context, fallback: '/owner/more'),
        title: Text('owner_staff_title'.tr()),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _invite,
        icon: const Icon(Icons.person_add_alt_1_rounded),
        label: Text('owner_staff_invite'.tr()),
      ),
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => RezeraErrorView(
          error: e,
          onRetry: () => ref.invalidate(ownerStaffProvider),
        ),
        data: (bundle) {
          final pending = bundle.invitations
              .where((i) => i.status == 'pending')
              .toList();
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(ownerStaffProvider),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
              children: [
                Text(
                  'owner_staff_members'.tr(),
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 8),
                if (bundle.members.isEmpty)
                  Text('owner_staff_empty'.tr())
                else
                  ...bundle.members.map(
                    (m) => Container(
                      margin: const EdgeInsets.only(bottom: 8),
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.9),
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  m.userName ?? m.userPhone ?? '—',
                                  style: theme.textTheme.titleMedium
                                      ?.copyWith(fontWeight: FontWeight.w700),
                                ),
                                if (m.userPhone != null) Text(m.userPhone!),
                                Text(
                                  'owner_role_${m.memberRole}'.tr(),
                                  style: theme.textTheme.labelLarge?.copyWith(
                                    color: RezeraColors.seafoam,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          if (!m.isOwner) ...[
                            IconButton(
                              tooltip: 'owner_staff_change_role'.tr(),
                              onPressed: () => _changeRole(m),
                              icon: const Icon(Icons.manage_accounts_outlined),
                            ),
                            IconButton(
                              tooltip: 'owner_staff_remove'.tr(),
                              onPressed: () => _remove(m),
                              icon: const Icon(Icons.person_remove_outlined),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ),
                if (pending.isNotEmpty) ...[
                  const SizedBox(height: 20),
                  Text(
                    'owner_staff_pending'.tr(),
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 8),
                  ...pending.map(
                    (i) => ListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text(i.phone),
                      subtitle: Text(
                        '${'owner_role_${i.memberRole}'.tr()} · ${i.status}',
                      ),
                    ),
                  ),
                ],
              ],
            ),
          );
        },
      ),
    );
  }
}
