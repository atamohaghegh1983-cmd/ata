import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/providers.dart';
import 'core/theme.dart';
import 'router.dart';

class HamshagerdiApp extends ConsumerWidget {
  const HamshagerdiApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final mode = ref.watch(themeModeProvider);
    final platformDark = MediaQuery.platformBrightnessOf(context) == Brightness.dark;
    final dark = mode == 'dark' || (mode == 'system' && platformDark);
    HsColors.dark = dark;
    return MaterialApp.router(
      // با عوض شدن تم، همه‌ی صفحه‌ها دوباره ساخته می‌شوند (رنگ‌های ثابت هم عوض شوند)
      key: ValueKey(dark),
      title: 'همشاگردی',
      debugShowCheckedModeBanner: false,
      theme: HsTheme.light(),
      locale: const Locale('fa', 'IR'),
      supportedLocales: const [Locale('fa', 'IR')],
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      routerConfig: ref.watch(routerProvider),
      builder: (context, child) => Directionality(textDirection: TextDirection.rtl, child: child!),
    );
  }
}
