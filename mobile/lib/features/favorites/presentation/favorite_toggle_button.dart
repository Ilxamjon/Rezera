import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/app_failure.dart';
import '../../../core/theme/rezera_theme.dart';
import '../../auth/presentation/session_controller.dart';
import 'favorite_providers.dart';

class FavoriteToggleButton extends ConsumerStatefulWidget {
  const FavoriteToggleButton({
    super.key,
    required this.businessId,
    this.isFavorite = false,
    this.onChanged,
    this.color,
  });

  final String businessId;
  final bool isFavorite;
  final ValueChanged<bool>? onChanged;
  final Color? color;

  @override
  ConsumerState<FavoriteToggleButton> createState() =>
      _FavoriteToggleButtonState();
}

class _FavoriteToggleButtonState extends ConsumerState<FavoriteToggleButton> {
  late bool _favorite = widget.isFavorite;
  bool _busy = false;

  @override
  void didUpdateWidget(covariant FavoriteToggleButton oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.isFavorite != widget.isFavorite ||
        oldWidget.businessId != widget.businessId) {
      _favorite = widget.isFavorite;
    }
  }

  Future<void> _toggle() async {
    final session = ref.read(sessionControllerProvider);
    if (!session.isAuthenticated) {
      if (mounted) context.push('/login');
      return;
    }
    if (_busy) return;
    setState(() => _busy = true);
    final previous = _favorite;
    setState(() => _favorite = !previous);
    try {
      final next = await ref.read(favoriteRepositoryProvider).toggle(
            widget.businessId,
            currentlyFavorite: previous,
          );
      if (!mounted) return;
      setState(() => _favorite = next);
      widget.onChanged?.call(next);
      ref.invalidate(favoriteBusinessesProvider);
    } on AppFailure catch (e) {
      if (!mounted) return;
      setState(() => _favorite = previous);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message.tr())),
      );
    } catch (_) {
      if (!mounted) return;
      setState(() => _favorite = previous);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('error_unknown'.tr())),
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final color = widget.color ?? RezeraColors.ink;
    return IconButton(
      tooltip: _favorite ? 'favorites_remove'.tr() : 'favorites_add'.tr(),
      onPressed: _busy ? null : _toggle,
      icon: Icon(
        _favorite ? Icons.favorite_rounded : Icons.favorite_border_rounded,
        color: _favorite ? RezeraColors.danger : color,
      ),
    );
  }
}
