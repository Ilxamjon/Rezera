import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../../core/errors/app_failure.dart';
import '../../../core/navigation/rezera_nav.dart';
import '../../../core/theme/rezera_theme.dart';
import 'owner_providers.dart';

class OwnerQrCheckInScreen extends ConsumerStatefulWidget {
  const OwnerQrCheckInScreen({
    super.key,
    required this.reservationId,
    required this.customerName,
  });

  final String reservationId;
  final String customerName;

  @override
  ConsumerState<OwnerQrCheckInScreen> createState() =>
      _OwnerQrCheckInScreenState();
}

class _OwnerQrCheckInScreenState extends ConsumerState<OwnerQrCheckInScreen> {
  final _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
  );
  bool _handling = false;
  String? _error;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  String? _extractToken(String raw) {
    final value = raw.trim();
    const prefix = 'rezera://check-in/';
    if (value.startsWith(prefix)) {
      return value.substring(prefix.length);
    }
    if (value.length >= 32 && value.length <= 64 && !value.contains(' ')) {
      return value;
    }
    return null;
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (_handling) return;
    final rawValues = capture.barcodes
        .map((b) => b.rawValue)
        .whereType<String>();
    final raw = rawValues.isEmpty ? null : rawValues.first;
    if (raw == null) return;

    final token = _extractToken(raw);
    if (token == null) {
      setState(() => _error = 'owner_qr_invalid'.tr());
      return;
    }

    final businessId = ref.read(selectedBusinessIdProvider);
    if (businessId == null) return;

    setState(() {
      _handling = true;
      _error = null;
    });
    await _controller.stop();

    try {
      await ref.read(ownerRepositoryProvider).checkInQr(
            businessId: businessId,
            reservationId: widget.reservationId,
            qrToken: token,
          );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('owner_checked_in'.tr())),
      );
      context.pop(true);
    } on AppFailure catch (e) {
      setState(() {
        _error = e.message.tr();
        _handling = false;
      });
      await _controller.start();
    } catch (_) {
      setState(() {
        _error = 'error_unknown'.tr();
        _handling = false;
      });
      await _controller.start();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: RezeraColors.ink,
      appBar: AppBar(
        backgroundColor: RezeraColors.ink,
        foregroundColor: RezeraColors.onInk,
        title: Text('owner_qr_scan_title'.tr()),
        leading: rezeraBackButton(context, fallback: '/owner/today'),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 12),
            child: Text(
              'owner_qr_scan_hint'.tr(args: [widget.customerName]),
              style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                    color: RezeraColors.onInk.withValues(alpha: 0.85),
                  ),
            ),
          ),
          Expanded(
            child: ClipRRect(
              borderRadius: BorderRadius.circular(16),
              child: MobileScanner(
                controller: _controller,
                onDetect: _onDetect,
              ),
            ),
          ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.all(16),
              child: Text(
                _error!,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: RezeraColors.amber,
                    ),
                textAlign: TextAlign.center,
              ),
            ),
          if (_handling)
            const Padding(
              padding: EdgeInsets.all(16),
              child: CircularProgressIndicator(color: RezeraColors.amber),
            ),
          const SizedBox(height: 16),
        ],
      ),
    );
  }
}
