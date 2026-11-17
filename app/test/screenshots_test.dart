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
import 'package:hamshagerdi/features/classes/class_screen.dart';
import 'package:hamshagerdi/features/home/home_screen.dart';
import 'package:hamshagerdi/features/magazine/magazine.dart';
import 'package:hamshagerdi/features/parent/add_child_screen.dart';
import 'package:hamshagerdi/features/parent/child_screen.dart';
import 'package:hamshagerdi/features/onboarding/onboarding_screen.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

final on = Platform.environment['HS_SHOTS'] == '1';

class FakeApi extends ApiClient {
  FakeApi() : super(tokens: TokenStore(), cache: CacheStore());
  @override
  Future<ApiResult<T>> get<T>(String path, {Map<String, dynamic>? query, bool useCache = true}) async {
    final Object data = switch (path) {
      '/children' => [
          {'id': 5, 'name': 'سارا رضایی', 'avatar': '', 'classes': [{'id': 9, 'title': 'کلاس سوم الف', 'grade': 'سوم', 'teacher': 'خانم احمدی'}]},
          {'id': 6, 'name': 'علی رضایی', 'avatar': '', 'classes': [{'id': 10, 'title': 'کلاس اول ب', 'grade': 'اول', 'teacher': 'آقای کریمی'}]},
        ],
      '/children/proposals' => [
          {'id': 1, 'student': 'علی رضایی', 'class': 'کلاس اول ب', 'teacher': 'آقای کریمی', 'relation': 'parent'},
        ],
      '/children/5/today' || '/children/6/today' => {'id': 5, 'name': 'سارا رضایی', 'classes': [
          {'id': 9, 'title': 'کلاس سوم الف', 'teacher': 'خانم احمدی', 'teacher_id': 2, 'can_message': true, 'locked': [],
           'attendance': {'status': 'present', 'note': ''}, 'points_today': 3, 'points_total': 41,
           'points_items': [{'points': 2, 'behavior': 'همکاری', 'icon': '🤝', 'note': ''}, {'points': 1, 'behavior': 'دقت', 'icon': '🎯', 'note': ''}],
           'homework_open': [{'id': 1, 'title': 'تمرین ریاضی صفحه‌ی ۲۴', 'subject': 'ریاضی', 'due_date': '2026-11-19', 'status': 'pending'}],
           'announcements': [{'id': 1, 'title': 'جلسه‌ی اولیا', 'pinned': true, 'created_at': '2026-11-16'}]},
        ]},
      '/children/5/week' => {'classes': [{'id': 9, 'days': [
          for (var i = 0; i < 6; i++) {'date': '2026-11-${15 + i}', 'attendance': ['present', 'present', 'late', 'present', 'absent', null][i], 'points': [2, 1, 0, 3, -1, 0][i]}
        ]}]},
      '/children/5/pin' => {'enabled': false, 'has_pin': false},
      '/classes' => [
          {'id': 9, 'title': 'کلاس سوم الف', 'grade': 'سوم', 'teacher': 'خانم احمدی', 'students': 24, 'access': 'write'},
        ],
      '/classes/9' => {'id': 9, 'title': 'کلاس سوم الف', 'grade': 'سوم', 'access': 'write'},
      '/classes/9/students' => [
          for (final n in ['سارا رضایی', 'علی کریمی', 'نازنین محمدی', 'پارسا احمدی', 'هستی نوری', 'آرمان یزدانی', 'یاسمن صادقی', 'امیرعلی شریفی', 'ترانه موسوی'])
            {'id': n.length, 'name': n, 'avatar': '', 'points': n.length * 3 - 20, 'parents': n.length % 3 == 0 ? 0 : 1},
        ],
      '/onboarding' => {'roles': [
          {'id': 'parent', 'title': 'والدین', 'hint': 'فرزندم در کلاس است'},
          {'id': 'teacher', 'title': 'معلم', 'hint': 'کلاس دارم یا می‌سازم'},
          {'id': 'school_admin', 'title': 'مدیر مدرسه', 'hint': 'مدرسه را مدیریت می‌کنم (نیاز به تأیید)'},
        ], 'provinces': ['تهران', 'اصفهان']},
      _ when path.startsWith('/classes/9/attendance?') => {'date': '2026-11-17', 'recorded': false, 'records': [
          for (final e in {'سارا': 'رضایی', 'علی': 'کریمی', 'نازنین': 'محمدی', 'پارسا': 'احمدی', 'هستی': 'نوری'}.entries)
            {'student_id': e.key.length + e.value.length, 'name': '${e.key} ${e.value}', 'first_name': e.key, 'last_name': e.value, 'avatar': '', 'status': e.key == 'علی' ? 'absent' : (e.key == 'هستی' ? 'late' : null)},
        ]},
      _ when path.startsWith('/classes/9/attendance/week') => {'days': [{'date': '2026-11-15', 'recorded': true}, {'date': '2026-11-16', 'recorded': true}]},
      _ when path.startsWith('/articles') => {'items': [
          {'id': 1, 'title': 'چطور با کودک درباره‌ی مدرسه حرف بزنیم؟', 'excerpt': 'چند پرسش ساده که گفتگوی عصرگاهی را شیرین می‌کند…', 'date': '2026-11-10', 'grades': ['سوم'], 'audiences': [{'id': 'parents', 'title': 'والدین'}], 'locked': false},
          {'id': 2, 'title': 'برنامه‌ی تمرین ریاضی پایه‌ی سوم', 'excerpt': 'ویژه‌ی معلمان پایه‌ی سوم.', 'date': '2026-11-08', 'grades': ['سوم'], 'audiences': [{'id': 'teachers', 'title': 'معلمان'}], 'locked': true},
        ]},
      _ when path.startsWith('/messages') => <Object>[],
      _ => <String, Object>{},
    };
    return ApiResult<T>(data as T);
  }
}

class FakeAuth extends AuthController {
  @override
  AuthState build() => AuthState(AuthStatus.loggedIn, me: Me({'id': 3, 'name': 'مریم رضایی', 'view': 'parent', 'has_children': true, 'mobile': '09121234567'}));
}

class FakeTeacher extends AuthController {
  @override
  AuthState build() => AuthState(AuthStatus.loggedIn, me: Me({'id': 2, 'name': 'زهرا احمدی', 'view': 'teacher', 'mobile': '09121234567'}));
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
  Future<void> shot(WidgetTester t, String name, Widget screen, {bool onboarding = false, bool teacher = false, int tab = -1}) async {
    t.view.physicalSize = const Size(1080, 2340);
    t.view.devicePixelRatio = 2.625;
    addTearDown(t.view.reset);
    await t.runAsync(loadFonts);
    await t.pumpWidget(ProviderScope(
      overrides: [apiProvider.overrideWith((_) => FakeApi()), authProvider.overrideWith(teacher ? FakeTeacher.new : FakeAuth.new)],
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
    if (tab >= 0) {
      await t.tap(find.byType(Tab).at(tab));
      await t.pumpAndSettle();
    }
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
  testWidgets('child', (t) => shot(t, '6-child', const ChildScreen(id: 5, name: 'سارا رضایی')), skip: !on);
  testWidgets('teacher', (t) => shot(t, '7-teacher-class', const ClassScreen(id: 9), teacher: true), skip: !on);
  testWidgets('attendance', (t) => shot(t, '8-attendance', const ClassScreen(id: 9), teacher: true, tab: 1), skip: !on);
  testWidgets('add child', (t) => shot(t, '9-add-child', const AddChildScreen()), skip: !on);
  testWidgets('magazine', (t) => shot(t, '10-magazine', const Scaffold(body: MagazineView(showMineFilter: true))), skip: !on);
}
