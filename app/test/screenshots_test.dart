// تصویر صفحه‌ها در اندازه‌ی Galaxy A15 (فقط با HS_SHOTS=1):
//   HS_SHOTS=1 flutter test test/screenshots_test.dart --update-goldens
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hamshagerdi/core/api/api_client.dart';
import 'package:hamshagerdi/core/providers.dart';
import 'package:hamshagerdi/core/storage/cache_store.dart';
import 'package:hamshagerdi/core/storage/token_store.dart';
import 'package:hamshagerdi/core/theme.dart';
import 'package:hamshagerdi/features/auth/auth_controller.dart';
import 'package:hamshagerdi/features/auth/intro_screen.dart';
import 'package:hamshagerdi/features/auth/me.dart';
import 'package:hamshagerdi/features/auth/otp_screen.dart';
import 'package:hamshagerdi/features/auth/phone_screen.dart';
import 'package:hamshagerdi/features/home/home_screen.dart';
import 'package:hamshagerdi/features/onboarding/onboarding_screen.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

final on = Platform.environment['HS_SHOTS'] == '1';

class FakeApi extends ApiClient {
  FakeApi() : super(tokens: TokenStore(), cache: CacheStore());
  @override
  Future<ApiResult<T>> get<T>(String path, {Map<String, dynamic>? query, bool useCache = true}) async {
    final Object data = switch (path) {
      '/children' => [
          {'id': 5, 'name': 'سارا رضایی', 'avatar': '', 'classes': [{'title': 'کلاس سوم الف', 'grade': 'سوم', 'teacher': 'خانم احمدی'}]},
          {'id': 6, 'name': 'علی رضایی', 'avatar': '', 'classes': [{'title': 'کلاس اول ب', 'grade': 'اول', 'teacher': 'آقای کریمی'}]},
        ],
      '/onboarding' => {'roles': [
          {'id': 'parent', 'title': 'والدین', 'hint': 'فرزندم در کلاس است'},
          {'id': 'teacher', 'title': 'معلم', 'hint': 'کلاس دارم یا می‌سازم'},
          {'id': 'school_admin', 'title': 'مدیر مدرسه', 'hint': 'مدرسه را مدیریت می‌کنم (نیاز به تأیید)'},
        ], 'provinces': ['تهران', 'اصفهان']},
      _ => <Object>[],
    };
    return ApiResult<T>(data as T);
  }
}

class FakeAuth extends AuthController {
  @override
  AuthState build() => AuthState(AuthStatus.loggedIn, me: Me({'id': 3, 'name': 'مریم رضایی', 'view': 'parent', 'has_children': true, 'mobile': '09121234567'}));
}

Future<void> loadFonts() async {
  final v = FontLoader('Vazirmatn');
  for (final w in ['Regular', 'Medium', 'Bold']) {
    v.addFont(rootBundle.load('assets/fonts/Vazirmatn-UI-FD-$w.ttf'));
  }
  await v.load();
  await (FontLoader('Lalezar')..addFont(rootBundle.load('assets/fonts/Lalezar-Regular.ttf'))).load();
  await (FontLoader('MaterialIcons')..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'))).load();
}

void main() {
  Future<void> shot(WidgetTester t, String name, Widget screen, {bool onboarding = false}) async {
    t.view.physicalSize = const Size(1080, 2340);
    t.view.devicePixelRatio = 2.625;
    addTearDown(t.view.reset);
    await t.runAsync(loadFonts);
    await t.pumpWidget(ProviderScope(
      overrides: [apiProvider.overrideWith((_) => FakeApi()), authProvider.overrideWith(FakeAuth.new)],
      child: MaterialApp(
        debugShowCheckedModeBanner: false,
        theme: HsTheme.light(),
        locale: const Locale('fa', 'IR'),
        supportedLocales: const [Locale('fa', 'IR')],
        localizationsDelegates: const [GlobalMaterialLocalizations.delegate, GlobalWidgetsLocalizations.delegate, GlobalCupertinoLocalizations.delegate],
        home: Directionality(textDirection: TextDirection.rtl, child: screen),
      ),
    ));
    await t.runAsync(() async {
      for (final e in find.byType(Image).evaluate()) {
        await precacheImage((e.widget as Image).image, e);
      }
    });
    await t.pump(const Duration(milliseconds: 300));
    if (onboarding) {
      await t.tap(find.byKey(const Key('role_parent')));
      await t.pump();
    }
    await expectLater(find.byType(MaterialApp), matchesGoldenFile('../build/shots/$name.png'));
  }

  testWidgets('intro', (t) => shot(t, '1-intro', const IntroScreen()), skip: !on);
  testWidgets('phone', (t) => shot(t, '2-phone', const PhoneScreen()), skip: !on);
  testWidgets('otp', (t) => shot(t, '3-otp', const OtpScreen(mobile: '09121234567', wait: 42)), skip: !on);
  testWidgets('onboarding', (t) => shot(t, '4-onboarding', const OnboardingScreen(), onboarding: true), skip: !on);
  testWidgets('home', (t) => shot(t, '5-home-parent', const HomeScreen()), skip: !on);
}
