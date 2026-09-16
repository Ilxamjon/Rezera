import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/app_failure.dart';
import '../../../core/formatters/formatters.dart';
import '../../../core/theme/rezera_theme.dart';
import 'session_controller.dart';

class OtpLoginScreen extends ConsumerStatefulWidget {
  const OtpLoginScreen({super.key});

  @override
  ConsumerState<OtpLoginScreen> createState() => _OtpLoginScreenState();
}

class _OtpLoginScreenState extends ConsumerState<OtpLoginScreen> {
  final _phone = TextEditingController();
  final _code = TextEditingController();
  final _name = TextEditingController();
  bool _codeSent = false;
  bool _loading = false;
  String? _error;
  String? _debugCode;

  @override
  void dispose() {
    _phone.dispose();
    _code.dispose();
    _name.dispose();
    super.dispose();
  }

  Future<void> _request() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final result = await ref.read(authRepositoryProvider).requestOtp(
            phone: PhoneFormat.toE164(_phone.text),
          );
      setState(() {
        _codeSent = true;
        _debugCode = result.debugCode;
      });
    } on AppFailure catch (e) {
      setState(() => _error = e.message.tr());
    } catch (_) {
      setState(() => _error = 'error_unknown'.tr());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _verify() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final payload = await ref.read(authRepositoryProvider).verifyOtp(
            phone: PhoneFormat.toE164(_phone.text),
            code: _code.text.trim(),
            name: _name.text.trim().isEmpty ? null : _name.text.trim(),
          );
      await ref.read(sessionControllerProvider.notifier).applyAuth(payload);
      if (!mounted) return;
      context.go('/home');
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
    return Scaffold(
      appBar: AppBar(title: Text('auth_otp_title'.tr())),
      body: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          Text(
            'auth_otp_hint'.tr(),
            style: theme.textTheme.bodyLarge?.copyWith(
              color: RezeraColors.slate.withValues(alpha: 0.9),
            ),
          ),
          const SizedBox(height: 20),
          TextField(
            controller: _phone,
            keyboardType: TextInputType.phone,
            enabled: !_codeSent,
            decoration: InputDecoration(
              labelText: 'auth_phone'.tr(),
              hintText: 'auth_phone_hint'.tr(),
            ),
          ),
          if (_codeSent) ...[
            const SizedBox(height: 12),
            TextField(
              controller: _code,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(labelText: 'auth_otp_code'.tr()),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _name,
              decoration: InputDecoration(
                labelText: 'auth_otp_name_optional'.tr(),
              ),
            ),
            if (_debugCode != null) ...[
              const SizedBox(height: 8),
              Text(
                'auth_otp_debug'.tr(args: [_debugCode!]),
                style: theme.textTheme.labelLarge?.copyWith(
                  color: RezeraColors.amber,
                ),
              ),
            ],
          ],
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
            onPressed: _loading
                ? null
                : (_codeSent ? _verify : _request),
            child: _loading
                ? const SizedBox(
                    width: 22,
                    height: 22,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : Text(
                    _codeSent
                        ? 'auth_otp_verify'.tr()
                        : 'auth_otp_send'.tr(),
                  ),
          ),
          TextButton(
            onPressed: () => context.go('/login'),
            child: Text('auth_otp_use_password'.tr()),
          ),
        ],
      ),
    );
  }
}
