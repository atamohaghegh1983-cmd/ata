import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'core/providers.dart';
import 'core/theme.dart';
import 'features/auth/auth_controller.dart';
import 'features/auth/intro_screen.dart';
import 'features/auth/otp_screen.dart';
import 'features/auth/phone_screen.dart';
import 'features/classes/class_screen.dart';
import 'features/classes/create_class_screen.dart';
import 'features/classes/import_screen.dart';
import 'features/notifications/notifications.dart';
import 'features/portfolio/portfolio.dart';
import 'features/school/school_screens.dart';
import 'features/teach/teach_screens.dart';
import 'features/timetable/timetable.dart';
import 'features/parent/progress_screen.dart';
import 'features/home/home_screen.dart';
import 'features/magazine/magazine.dart';
import 'features/messages/messages_screens.dart';
import 'features/parent/add_child_screen.dart';
import 'features/parent/child_screen.dart';
import 'features/student/student_screens.dart';
import 'features/onboarding/onboarding_screen.dart';

/// اطلاع‌دادن تغییر وضعیت ورود به go_router
class _AuthListenable extends ChangeNotifier {
  _AuthListenable(Ref ref) {
    ref.listen(authProvider, (_, _) => notifyListeners());
    ref.listen(introSeenProvider, (_, _) => notifyListeners());
  }
}

final routerProvider = Provider<GoRouter>((ref) {
  final refresh = _AuthListenable(ref);
  ref.onDispose(refresh.dispose);
  return GoRouter(
    initialLocation: '/splash',
    refreshListenable: refresh,
    redirect: (context, state) {
      final auth = ref.read(authProvider);
      final intro = ref.read(introSeenProvider);
      final loc = state.matchedLocation;
      if (auth.status == AuthStatus.unknown || intro.isLoading) {
        return loc == '/splash' ? null : '/splash';
      }
      if (auth.status == AuthStatus.loggedOut) {
        if (loc.startsWith('/login') || loc == '/kid') return null;
        if (intro.valueOrNull != true) return loc == '/intro' ? null : '/intro';
        return loc == '/intro' ? null : '/login';
      }
      // وارد شده
      if (auth.me?.needsOnboarding == true) {
        return loc == '/onboarding' ? null : '/onboarding';
      }
      const outside = ['/splash', '/intro', '/login', '/onboarding', '/kid'];
      if (outside.any((x) => loc == x || loc.startsWith('$x/'))) return '/';
      return null;
    },
    routes: [
      GoRoute(path: '/splash', builder: (_, _) => const _Splash()),
      GoRoute(path: '/intro', builder: (_, _) => const IntroScreen()),
      GoRoute(
        path: '/login',
        builder: (_, _) => const PhoneScreen(),
        routes: [
          GoRoute(
            path: 'code',
            builder: (_, s) {
              final x = (s.extra as Map?) ?? const {};
              return OtpScreen(mobile: '${x['mobile'] ?? ''}', wait: (x['wait'] as int?) ?? 60);
            },
          ),
        ],
      ),
      GoRoute(path: '/kid', builder: (_, _) => const KidLoginScreen()),
      GoRoute(path: '/onboarding', builder: (_, _) => const OnboardingScreen()),
      GoRoute(path: '/', builder: (_, _) => const HomeScreen()),
      GoRoute(path: '/class/new', builder: (_, _) => const CreateClassScreen()),
      GoRoute(path: '/children/add', builder: (_, _) => const AddChildScreen()),
      GoRoute(path: '/children/:id', builder: (_, s) => ChildScreen(id: int.tryParse(s.pathParameters['id'] ?? '') ?? 0, name: '${s.extra ?? ''}')),
      GoRoute(path: '/messages/:id', builder: (_, s) => ThreadScreen(userId: int.tryParse(s.pathParameters['id'] ?? '') ?? 0, name: '${s.extra ?? ''}')),
      GoRoute(path: '/articles/:id', builder: (_, s) => ArticleScreen(id: int.tryParse(s.pathParameters['id'] ?? '') ?? 0)),
      GoRoute(path: '/class/:id/import', builder: (_, s) => ImportStudentsScreen(classId: int.tryParse(s.pathParameters['id'] ?? '') ?? 0)),
      GoRoute(path: '/notifications', builder: (_, _) => const NotificationsScreen()),
      GoRoute(path: '/requests', builder: (_, _) => const RequestsScreen()),
      GoRoute(path: '/school/form', builder: (_, _) => const SchoolFormScreen()),
      GoRoute(path: '/role', builder: (_, _) => const OnboardingScreen(change: true)),
      GoRoute(
        path: '/portfolio/:id',
        builder: (_, s) => Scaffold(
          appBar: AppBar(title: Text('دیوار همشاگردی ${s.extra ?? ''}'.trim())),
          body: PortfolioView(studentId: int.tryParse(s.pathParameters['id'] ?? '') ?? 0),
        ),
      ),
      GoRoute(
        path: '/wall/:id',
        builder: (_, s) => Scaffold(
          appBar: AppBar(title: Text(wallTitle('${s.extra ?? ''}'))),
          body: WallView(classId: int.tryParse(s.pathParameters['id'] ?? '') ?? 0),
        ),
      ),
      GoRoute(path: '/class/:id', builder: (_, s) => ClassScreen(id: int.tryParse(s.pathParameters['id'] ?? '') ?? 0, initialTab: int.tryParse(s.uri.queryParameters['tab'] ?? '') ?? 0)),
      GoRoute(path: '/assessments/:id', builder: (_, s) => AssessmentsScreen(classId: int.tryParse(s.pathParameters['id'] ?? '') ?? 0, title: '${s.extra ?? ''}')),
      GoRoute(path: '/assessment/:id', builder: (_, s) => AssessmentScreen(id: int.tryParse(s.pathParameters['id'] ?? '') ?? 0)),
      GoRoute(path: '/budget/:id', builder: (_, s) => BudgetScreen(classId: int.tryParse(s.pathParameters['id'] ?? '') ?? 0, title: '${s.extra ?? ''}')),
      GoRoute(path: '/calendar', builder: (_, s) => CalendarScreen(childId: int.tryParse(s.uri.queryParameters['child'] ?? ''))),
      GoRoute(
        path: '/timetable',
        builder: (_, s) => TimetableScreen(
          classId: int.tryParse(s.uri.queryParameters['class'] ?? ''),
          childId: int.tryParse(s.uri.queryParameters['child'] ?? ''),
          title: '${s.extra ?? ''}',
        ),
      ),
      GoRoute(
        path: '/bells',
        builder: (_, s) {
          final x = (s.extra is Map) ? s.extra as Map : const {};
          return BellsScreen(classId: x['class_id'] as int?, school: s.uri.queryParameters['school'] == '1', title: '${x['title'] ?? ''}');
        },
      ),
      GoRoute(path: '/school/subjects', builder: (_, _) => const SchoolSubjectsScreen()),
      GoRoute(path: '/worksheets', builder: (_, _) => const WorksheetsScreen()),
      GoRoute(path: '/progress/:id', builder: (_, s) => ChildProgressScreen(childId: int.tryParse(s.pathParameters['id'] ?? '') ?? 0, name: '${s.extra ?? ''}')),
    ],
  );
});

class _Splash extends StatelessWidget {
  const _Splash();
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: HsColors.cream,
      body: Center(child: Image.asset('assets/images/logo_mark.png', width: 180)),
    );
  }
}
