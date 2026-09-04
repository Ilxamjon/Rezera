import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/app_failure.dart';
import '../../../core/formatters/formatters.dart';
import '../../../core/theme/rezera_theme.dart';
import 'session_controller.dart';

class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _name = TextEditingController();
  final _phone = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  final _formKey = GlobalKey<FormState>();
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    _password.dispose();
    _confirm.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final locale = context.locale.languageCode;
      final payload = await ref.read(authRepositoryProvider).register(
            name: _name.text.trim(),
            phone: PhoneFormat.toE164(_phone.text),
            password: _password.text,
            passwordConfirmation: _confirm.text,
            locale: locale,
          );
      await ref.read(sessionControllerProvider.notifier).applyAuth(payload);
      if (mounted) context.go('/home');
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
            begin: Alignment.topRight,
            end: Alignment.bottomLeft,
            colors: [
              Color(0xFFE8EDE8),
              RezeraColors.mist,
              Color(0xFFF6EEDF),
            ],
          ),
        ),
        child: SafeArea(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(24, 32, 24, 24),
            children: [
              Text(
                'app_name'.tr(),
                style: theme.textTheme.headlineLarge?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 24),
              Text(
                'auth_register_title'.tr(),
                style: theme.textTheme.headlineMedium,
              ),
              const SizedBox(height: 20),
              Form(
                key: _formKey,
                child: Column(
                  children: [
                    TextFormField(
                      controller: _name,
                      textCapitalization: TextCapitalization.words,
                      decoration: InputDecoration(labelText: 'auth_name'.tr()),
                      validator: (v) =>
                          (v == null || v.trim().isEmpty) ? '…' : null,
                    ),
                    const SizedBox(height: 12),
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
                      decoration:
                          InputDecoration(labelText: 'auth_password'.tr()),
                      validator: (v) =>
                          (v == null || v.length < 8) ? '…' : null,
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _confirm,
                      obscureText: true,
                      decoration: InputDecoration(
                        labelText: 'auth_password_confirm'.tr(),
                      ),
                      validator: (v) =>
                          v != _password.text ? '…' : null,
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
                    : Text('auth_register_action'.tr()),
              ),
              TextButton(
                onPressed: () => context.go('/login'),
                child: Text('auth_have_account'.tr()),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
