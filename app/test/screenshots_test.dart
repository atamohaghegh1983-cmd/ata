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
import 'package:hamshagerdi/features/dashboard/dashboard.dart';
import 'package:hamshagerdi/features/home/home_screen.dart';
import 'package:hamshagerdi/features/student/student_screens.dart';
import 'package:hamshagerdi/features/magazine/magazine.dart';
import 'package:hamshagerdi/features/parent/add_child_screen.dart';
import 'package:hamshagerdi/features/parent/child_screen.dart';
import 'package:hamshagerdi/features/onboarding/onboarding_screen.dart';
import 'package:hamshagerdi/features/notifications/notifications.dart';
import 'package:hamshagerdi/features/portfolio/portfolio.dart';
import 'package:hamshagerdi/features/school/school_screens.dart';
import 'package:hamshagerdi/features/classes/import_screen.dart';
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
      '/dashboard' => {'role': 'teacher', 'unread_messages': 2,
          'teacher': {'totals': {'classes': 2, 'students': 48, 'absent_today': 3, 'late_today': 1, 'not_recorded': 1, 'unlinked_parents': 5, 'open_homework': 4, 'points_today': 37}, 'parent_requests': 1,
            'classes': [
              {'id': 9, 'title': 'کلاس سوم الف', 'students': 24, 'recorded_today': true, 'absent_today': 3, 'late_today': 1, 'points_today': 30, 'open_homework': 2, 'unlinked_parents': 2},
              {'id': 10, 'title': 'کلاس سوم ب', 'students': 24, 'recorded_today': false, 'absent_today': 0, 'late_today': 0, 'points_today': 7, 'open_homework': 2, 'unlinked_parents': 3},
            ]},
          'manager': {'school': {'id': 1, 'name': 'دبستان امید'}, 'is_manager': true, 'pending_invites': 2,
            'totals': {'classes': 12, 'teachers': 10, 'students': 286, 'recorded_today': 9, 'absent_today': 11, 'att_rate_30': 94, 'parents_linked_pct': 71},
            'classes': [{'id': 9, 'title': 'سوم الف', 'teacher': 'خانم احمدی', 'students': 24, 'homework': 8, 'att_rate': 96, 'recorded_today': true}, {'id': 10, 'title': 'سوم ب', 'teacher': 'آقای کریمی', 'students': 25, 'homework': 3, 'att_rate': 81, 'recorded_today': false}],
            'teachers': [{'id': 2, 'name': 'خانم احمدی', 'classes': 1, 'students': 24, 'homework': 8, 'att_rate': 96}]},
          'parent': {'proposals': 1, 'children': [{'id': 5, 'name': 'سارا رضایی', 'avatar': '', 'attendance_today': 'present', 'absent_week': 0, 'late_week': 1, 'points_week': 12, 'open_homework': 1, 'locked': []}],
            'announcements': [{'id': 1, 'class': 'کلاس سوم الف', 'title': 'جلسه‌ی اولیا', 'body': 'جلسه‌ی اولیا پنجشنبه ساعت ۱۰ در نمازخانه برگزار می‌شود.', 'created_at': '2026-11-16'}]}},
      '/student/summary' => {'classes': [{'id': 9, 'title': 'کلاس سوم الف', 'teacher': 'خانم احمدی', 'points_total': 41,
          'points_recent': [{'points': 2, 'behavior': 'همکاری', 'icon': '🤝', 'at': '2026-11-17'}],
          'homework': [{'id': 1, 'title': 'تمرین ریاضی', 'subject': 'ریاضی', 'due_date': '2026-11-21', 'status': 'pending', 'feedback': ''}, {'id': 2, 'title': 'املا', 'subject': 'فارسی', 'due_date': '2026-11-15', 'status': 'done', 'feedback': 'آفرین'}]}]},
      '/classes?mine=1' => [
          {'id': 9, 'title': 'کلاس سوم الف', 'grade': 'سوم', 'teacher': 'زهرا احمدی', 'students': 24, 'access': 'write'},
        ],
      '/notifications' => {'unread': 2, 'items': [
          {'id': 1, 'type': 'attendance', 'title': 'غیبت', 'body': 'سارا امروز غایب ثبت شد.', 'is_read': false, 'created_at': '2026-11-17', 'data': {'child': 5}},
          {'id': 2, 'type': 'portfolio', 'title': 'دیوار کلاس', 'body': 'نقاشی سارا روی دیوار کلاس رفت 🎉', 'is_read': false, 'created_at': '2026-11-16', 'data': {'class': 9}},
          {'id': 3, 'type': 'homework', 'title': 'تکلیف تازه', 'body': 'تمرین ریاضی صفحه‌ی ۲۴', 'is_read': true, 'created_at': '2026-11-15', 'data': {}},
        ]},
      '/requests' => [
          {'id': 4, 'parent': 'رضا کریمی', 'parent_mobile': '09351234567', 'child_name': 'علی کریمی', 'class': 'کلاس سوم الف',
            'candidates': [{'id': 21, 'name': 'علی کریمی', 'class': 'کلاس سوم الف'}, {'id': 22, 'name': 'علی کاظمی', 'class': 'کلاس سوم الف'}]},
        ],
      '/school/profile' => {'can_edit': true, 'approved': false, 'school': null, 'levels': {'': '— انتخاب —', 'primary': 'ابتدایی'}, 'provinces': ['تهران', 'اصفهان'], 'positions': {'teacher': 'معلم'}},
      '/portfolio/student/5' => {'student': {'id': 5, 'name': 'سارا رضایی', 'avatar': '', 'about': 'عاشق نقاشی و کتاب‌های داستان'}, 'can_post': true, 'today': 0, 'limit': 2, 'posts': [
          {'id': 1, 'student_id': 5, 'student': 'سارا ر.', 'avatar': '', 'class_id': 9, 'title': 'نقاشی پاییز', 'text': 'درخت‌های حیاط مدرسه', 'type': 'image', 'image': '', 'on_wall': true, 'cheers': 7, 'cheered': false, 'at': '2026-11-16 10:00:00'},
          {'id': 2, 'student_id': 5, 'student': 'سارا ر.', 'avatar': '', 'class_id': 9, 'title': 'کتابی که خواندم', 'text': 'شازده کوچولو را تمام کردم', 'type': 'text', 'image': '', 'on_wall': false, 'cheers': 2, 'cheered': true, 'at': '2026-11-17 18:00:00'},
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
  testWidgets('dash-teacher', (t) => shot(t, '11-dash-teacher', const Scaffold(body: DashboardView(kind: 'teacher'))), skip: !on);
  testWidgets('dash-parent', (t) => shot(t, '12-dash-parent', const Scaffold(body: DashboardView(kind: 'parent'))), skip: !on);
  testWidgets('dash-manager', (t) => shot(t, '13-dash-manager', const Scaffold(body: DashboardView(kind: 'manager'))), skip: !on);
  testWidgets('student', (t) => shot(t, '14-student', const Scaffold(body: StudentHome())), skip: !on);
  testWidgets('notifications', (t) => shot(t, '15-notifications', const NotificationsScreen()), skip: !on);
  testWidgets('requests', (t) => shot(t, '16-requests', const RequestsScreen(), teacher: true), skip: !on);
  testWidgets('school', (t) => shot(t, '17-school-form', const SchoolFormScreen()), skip: !on);
  testWidgets('portfolio', (t) => shot(t, '18-portfolio', const Scaffold(body: PortfolioView(studentId: 5))), skip: !on);
  testWidgets('import', (t) => shot(t, '19-import', const ImportStudentsScreen(classId: 9), teacher: true), skip: !on);
  testWidgets('teacher-home', (t) => shot(t, '20-teacher-home', const HomeScreen(), teacher: true), skip: !on);
  testWidgets('magazine', (t) => shot(t, '10-magazine', const Scaffold(body: MagazineView(showMineFilter: true))), skip: !on);
}
