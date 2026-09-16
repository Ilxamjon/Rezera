import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/app_failure.dart';
import '../../../core/formatters/formatters.dart';
import '../../../core/theme/rezera_theme.dart';
import 'session_controller.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _phone = TextEditingController();
  final _password = TextEditingController();
  final _formKey = GlobalKey<FormState>();
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _phone.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final payload = await ref.read(authRepositoryProvider).login(
            phone: PhoneFormat.toE164(_phone.text),
            password: _password.text,
          );
      await ref.read(sessionControllerProvider.notifier).applyAuth(payload);
      if (!mounted) return;
      // Let GoRouter refresh from the new session, then leave login.
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted) return;
        context.go('/home');
      });
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
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              Color(0xFFF7F2E8),
              RezeraColors.mist,
              Color(0xFFE4EDE9),
            ],
          ),
        ),
        child: SafeArea(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(24, 48, 24, 24),
            children: [
              Center(
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(22),
                  child: Image.asset(
                    'assets/branding/logo.png',
                    width: 88,
                    height: 88,
                    fit: BoxFit.cover,
                  ),
                ),
              ),
              const SizedBox(height: 20),
              Text(
                'app_name'.tr(),
                textAlign: TextAlign.center,
                style: theme.textTheme.displayMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                  letterSpacing: -1.4,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                'tagline'.tr(),
                textAlign: TextAlign.center,
                style: theme.textTheme.titleMedium?.copyWith(
                  color: RezeraColors.slate.withValues(alpha: 0.75),
                ),
              ),
              const SizedBox(height: 40),
              Text(
                'auth_login_title'.tr(),
                style: theme.textTheme.headlineMedium,
              ),
              const SizedBox(height: 20),
              Form(
                key: _formKey,
                child: Column(
                  children: [
                    TextFormField(
                      controller: _phone,
                      keyboardType: TextInputType.phone,
                      decoration: InputDecoration(
                        labelText: 'auth_phone'.tr(),
                        hintText: 'auth_phone_hint'.tr(),
                      ),
                      validator: (v) =>
                          (v == null || v.trim().length < 9) ? '…' : null,
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _password,
                      obscureText: true,
                      decoration: InputDecoration(
                        labelText: 'auth_password'.tr(),
                      ),
                      validator: (v) =>
                          (v == null || v.length < 6) ? '…' : null,
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
                        height: 22,
                        width: 22,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : Text('auth_login_action'.tr()),
              ),
              const SizedBox(height: 12),
              TextButton(
                onPressed: () => context.push('/login/otp'),
                child: Text('auth_otp_title'.tr()),
              ),
              TextButton(
                onPressed: () => context.go('/register'),
                child: Text('auth_no_account'.tr()),
              ),
              TextButton(
                onPressed: () => context.go('/home'),
                child: Text('profile_guest'.tr()),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
