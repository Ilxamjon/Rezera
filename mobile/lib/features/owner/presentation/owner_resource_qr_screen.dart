import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../../core/errors/app_failure.dart';
import '../../../core/navigation/rezera_nav.dart';
import '../../../core/theme/rezera_theme.dart';
import '../data/owner_models.dart';
import 'owner_providers.dart';

class OwnerResourceQrScreen extends ConsumerStatefulWidget {
  const OwnerResourceQrScreen({super.key, required this.resource});

  final OwnerResource resource;

  @override
  ConsumerState<OwnerResourceQrScreen> createState() =>
      _OwnerResourceQrScreenState();
}

class _OwnerResourceQrScreenState extends ConsumerState<OwnerResourceQrScreen> {
  ResourceQrPayload? _payload;
  bool _loading = false;
  String? _error;

  Future<void> _generate() async {
    final businessId = ref.read(selectedBusinessIdProvider);
    if (businessId == null) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final payload = await ref.read(ownerRepositoryProvider).generateResourceQr(
            businessId: businessId,
            resourceId: widget.resource.id,
          );
      setState(() => _payload = payload);
    } on AppFailure catch (e) {
      setState(() => _error = e.message.tr());
    } catch (_) {
      setState(() => _error = 'error_unknown'.tr());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final payload = _payload;

    return Scaffold(
      appBar: AppBar(
        title: Text('owner_resource_qr'.tr()),
        leading: rezeraBackButton(context, fallback: '/owner/resources'),
      ),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Text(
            widget.resource.name,
            style: theme.textTheme.headlineSmall?.copyWith(
              fontWeight: FontWeight.w800,
            ),
          ),
          if (widget.resource.code != null) Text(widget.resource.code!),
          const SizedBox(height: 20),
          Text(
            'owner_resource_qr_hint'.tr(),
            style: theme.textTheme.bodyLarge,
          ),
          const SizedBox(height: 20),
          if (payload != null) ...[
            Center(
              child: Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                ),
                child: QrImageView(
                  data: payload.qrPayload,
                  size: 220,
                  backgroundColor: Colors.white,
                ),
              ),
            ),
            const SizedBox(height: 12),
            SelectableText(
              payload.qrPayload,
              style: theme.textTheme.bodySmall,
            ),
            const SizedBox(height: 8),
            TextButton.icon(
              onPressed: () async {
                await Clipboard.setData(ClipboardData(text: payload.qrPayload));
                if (context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text('owner_resource_qr_copied'.tr())),
                  );
                }
              },
              icon: const Icon(Icons.copy_rounded),
              label: Text('owner_resource_qr_copy'.tr()),
            ),
          ] else
            Container(
              height: 220,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: RezeraColors.sand.withValues(alpha: 0.5),
                borderRadius: BorderRadius.circular(16),
              ),
              child: Text('owner_resource_qr_empty'.tr()),
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
            onPressed: _loading ? null : _generate,
            child: _loading
                ? const SizedBox(
                    width: 22,
                    height: 22,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : Text(
                    payload == null
                        ? 'owner_resource_qr_generate'.tr()
                        : 'owner_resource_qr_regenerate'.tr(),
                  ),
          ),
        ],
      ),
    );
  }
}
