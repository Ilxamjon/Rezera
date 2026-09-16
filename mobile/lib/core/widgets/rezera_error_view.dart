import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';

import '../errors/app_failure.dart';
import '../theme/rezera_theme.dart';

/// Shared async-error pane with retry for list/detail screens.
class RezeraErrorView extends StatelessWidget {
  const RezeraErrorView({
    super.key,
    required this.error,
    this.onRetry,
    this.retryLabelKey = 'home_retry',
  });

  final Object error;
  final VoidCallback? onRetry;
  final String retryLabelKey;

  static String messageOf(Object error) {
    if (error is AppFailure) {
      return error.message.tr();
    }
    return 'error_unknown'.tr();
  }

  bool get _isNetwork => error is NetworkFailure;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              _isNetwork
                  ? Icons.wifi_off_rounded
                  : Icons.error_outline_rounded,
              size: 40,
              color: _isNetwork
                  ? RezeraColors.slate.withValues(alpha: 0.7)
                  : RezeraColors.danger.withValues(alpha: 0.85),
            ),
            const SizedBox(height: 12),
            Text(
              messageOf(error),
              textAlign: TextAlign.center,
              style: theme.textTheme.titleMedium,
            ),
            if (onRetry != null) ...[
              const SizedBox(height: 16),
              FilledButton(
                onPressed: onRetry,
                child: Text(retryLabelKey.tr()),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
