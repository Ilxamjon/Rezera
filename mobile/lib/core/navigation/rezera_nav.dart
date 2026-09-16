import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

/// Safe one-step back for go_router + shell screens.
void rezeraPop(BuildContext context, {String fallback = '/home'}) {
  final router = GoRouter.of(context);
  if (router.canPop()) {
    router.pop();
    return;
  }
  context.go(fallback);
}

/// AppBar leading that always offers a way back.
Widget rezeraBackButton(BuildContext context, {String fallback = '/home'}) {
  return IconButton(
    icon: const Icon(Icons.arrow_back_rounded),
    tooltip: MaterialLocalizations.of(context).backButtonTooltip,
    onPressed: () => rezeraPop(context, fallback: fallback),
  );
}
