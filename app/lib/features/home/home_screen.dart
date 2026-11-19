import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/config.dart';
import '../../core/providers.dart';
import '../../core/update_check.dart';
import '../../core/theme.dart';
import '../../core/utils/fa.dart';
import '../../core/widgets/hs_avatar.dart';
import '../../core/widgets/states.dart';
import '../auth/auth_controller.dart';
import '../auth/me.dart';
import '../classes/class_providers.dart';
import '../classes/class_screen.dart' show AnnouncementsTab;
import '../classes/homework_tab.dart' show HomeworkTab;
import '../dashboard/dashboard.dart';
import '../dashboard/manager_views.dart';
import '../kid/kid_mode.dart';
import '../kid/parent_lock.dart';
import '../notifications/notifications.dart';
import '../portfolio/portfolio.dart' show PortfolioView;
import '../school/school_screens.dart';
import '../magazine/magazine.dart';
import '../messages/messages_screens.dart';
import '../parent/parent_home.dart';
import '../student/student_screens.dart';
import '../teach/teach_screens.dart' show ClassTools;

/// خانه با نوار پایین؛ زبانه‌ها بر اساس نقش
class HomeScreen extends ConsumerStatefulWidget {
  const HomeScreen({super.key});
  @override
  ConsumerState<HomeScreen> createState() => _HomeScreenState();
}

class _Tab {
  const _Tab(this.label, this.icon, this.title, this.body);
  final String label;
  final IconData icon;
  final String title;
  final Widget body;
}

class _HomeScreenState extends ConsumerState<HomeScreen> {
  int _i = 0;
  DateTime? _lastBack;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) UpdateCheck.run(context, ref.read(apiProvider));
    });
  }

  void _go(int i) => setState(() => _i = i);

  List<_Tab> _tabs(Me me) {
    const msgs = _Tab('پیام‌ها', Icons.forum_rounded, 'پیام‌ها', InboxView());
    switch (me.homeKind) {
      case 'teacher':
        return [
          _Tab('داشبورد', Icons.dashboard_rounded, 'داشبورد', DashboardView(kind: 'teacher', onOpenTab: _go)),
          _Tab('کلاس‌ها', Icons.class_rounded, 'کلاس‌های من', _ClassList(student: false, onOpenTab: _go)),
          msgs,
          const _Tab('مجله', Icons.auto_stories_rounded, 'مجله', MagazineView(showMineFilter: true)),
        ];
      case 'parent':
        return [
          _Tab('داشبورد', Icons.dashboard_rounded, 'داشبورد', DashboardView(kind: 'parent', onOpenTab: _go)),
          const _Tab('فرزندان', Icons.family_restroom_rounded, 'فرزندان من', ParentHome()),
          msgs,
          const _Tab('مجله', Icons.auto_stories_rounded, 'مجله‌ی والدین', MagazineView()),
        ];
      case 'manager':
        return [
          _Tab('داشبورد', Icons.dashboard_rounded, 'داشبورد مدرسه', DashboardView(kind: 'manager', onOpenTab: _go)),
          const _Tab('کلاس‌ها', Icons.class_rounded, 'کلاس‌های مدرسه', SchoolClassesView()),
          const _Tab('کادر', Icons.badge_rounded, 'کادر مدرسه', StaffView()),
          const _Tab('مجله', Icons.auto_stories_rounded, 'مجله', MagazineView(showMineFilter: true)),
        ];
      case 'student':
        return [
          const _Tab('من', Icons.emoji_events_rounded, 'امتیازها و تکالیف من', StudentHome()),
          _Tab('دیوار من', Icons.photo_album_rounded, 'دیوار همشاگردی من', PortfolioView(studentId: me.id)),
          const _Tab('مجله', Icons.auto_stories_rounded, 'مجله', MagazineView()),
        ];
    }
    return [_Tab('خانه', Icons.home_rounded, 'همشاگردی', _StatusBody(me: me))];
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authProvider);
    final lock = ref.watch(parentLockProvider);
    if (lock.kidMode) return const KidModeScreen();
    final me = auth.me;
    if (me == null) {
      return Scaffold(
        body: ErrorView(error: ApiException(ApiException.offlineMessage, offline: true), onRetry: () => ref.read(authProvider.notifier).restore()),
      );
    }
    final tabs = _tabs(me);
    if (_i >= tabs.length) _i = 0;
    final t = tabs[_i];
    // دکمه‌ی برگشت گوشی: از زبانه‌های دیگر به داشبورد؛ در داشبورد دو بار برای خروج
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (didPop) return;
        if (_i != 0) {
          setState(() => _i = 0);
          return;
        }
        final now = DateTime.now();
        if (_lastBack != null && now.difference(_lastBack!) < const Duration(seconds: 2)) {
          SystemNavigator.pop();
          return;
        }
        _lastBack = now;
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('برای خروج دوباره «برگشت» را بزنید.'), duration: Duration(seconds: 2)));
      },
      child: Scaffold(
        appBar: AppBar(
          title: Text(t.title),
          leading: Padding(
            padding: const EdgeInsetsDirectional.only(start: 12),
            child: Center(
              child: HsAvatar(name: me.name, url: me.avatar, size: 36),
            ),
          ),
          actions: [
            const NotificationBell(),
            _Menu(me: me),
          ],
        ),
        floatingActionButton: me.homeKind == 'teacher' && _i == 1
            ? FloatingActionButton.extended(
                key: const Key('new_class'),
                backgroundColor: HsColors.orange,
                foregroundColor: Colors.white,
                onPressed: () => context.push('/class/new'),
                icon: const Icon(Icons.add_rounded),
                label: const Text('کلاس جدید'),
              )
            : null,
        bottomNavigationBar: tabs.length < 2
            ? null
            : NavigationBar(
                selectedIndex: _i,
                backgroundColor: HsColors.card,
                indicatorColor: HsColors.dark ? HsColors.orange.withValues(alpha: 0.35) : const Color(0xFFFFE3CC),
                onDestinationSelected: (v) => setState(() => _i = v),
                destinations: [for (final x in tabs) NavigationDestination(icon: Icon(x.icon), label: x.label)],
              ),
        body: Column(
          children: [
            if (auth.offline) const OfflineBanner(),
            if (_i == 0) _Hello(me: me),
            Expanded(
              child: IndexedStack(index: _i, children: [for (final x in tabs) x.body]),
            ),
          ],
        ),
      ),
    );
  }
}

class _Hello extends StatelessWidget {
  const _Hello({required this.me});
  final Me me;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 4, 20, 8),
      child: Row(
        children: [
          Expanded(
            child: Text(
              'سلام ${me.name.split(' ').first} 👋',
              style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700, color: HsColors.navy),
            ),
          ),
          Text('${Fa.weekday(DateTime.now())}، ${Fa.date(Fa.ymd(DateTime.now()))}', style: TextStyle(color: HsColors.muted, fontSize: 13)),
        ],
      ),
    );
  }
}

class _Menu extends ConsumerWidget {
  const _Menu({required this.me});
  final Me me;

  Future<bool> _confirm(BuildContext context, String title, String text, String ok, {bool danger = false}) async {
    final r = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text(title),
        content: Text(text, style: const TextStyle(height: 1.8)),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('انصراف')),
          FilledButton(
            style: FilledButton.styleFrom(minimumSize: const Size(90, 44), backgroundColor: danger ? HsColors.red : null),
            onPressed: () => Navigator.pop(c, true),
            child: Text(ok),
          ),
        ],
      ),
    );
    return r == true;
  }

  Future<void> _logout(BuildContext context, WidgetRef ref, bool all) async {
    if (!await _confirm(context, 'خروج از حساب', all ? 'از همه‌ی گوشی‌ها و دستگاه‌ها خارج می‌شوید.' : 'از حساب خود خارج می‌شوید.', 'خروج')) return;
    await ref.read(parentLockProvider.notifier).clearAll();
    await ref.read(authProvider.notifier).logout(everywhere: all);
  }

  Future<void> _delete(BuildContext context, WidgetRef ref) async {
    if (!await _confirm(
      context,
      'حذف حساب کاربری',
      'درخواست حذف حساب ثبت می‌شود و از همه‌ی دستگاه‌ها خارج می‌شوید. اطلاعات شما پس از بررسی پاک می‌شود و این کار برگشت ندارد.',
      'حذف حساب',
      danger: true,
    )) {
      return;
    }
    try {
      await ref.read(apiProvider).post<Object?>('/me/delete', data: {'confirm': true});
    } on ApiException catch (e) {
      if (context.mounted) showMessage(context, e.message, error: true);
      return;
    }
    await ref.read(parentLockProvider.notifier).clearAll();
    await ref.read(authProvider.notifier).logout(everywhere: true);
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final kind = me.homeKind;
    return PopupMenuButton<String>(
      key: const Key('home_menu'),
      icon: const Icon(Icons.more_vert_rounded),
      onSelected: (v) async {
        switch (v) {
          case 'logout' || 'logout_all':
            await _logout(context, ref, v == 'logout_all');
          case 'delete':
            await _delete(context, ref);
          case 'role':
            await context.push('/role');
          case 'school':
            await context.push('/school/form');
          case 'staff':
            await addStaff(context, ref);
          case 'link_school':
            await linkTeacherToSchool(context, ref);
          case 'requests':
            await context.push('/requests');
          case 'kid':
            await startKidMode(context, ref);
          case 'pin':
            await parentPinSettings(context, ref);
          case 'theme':
            final cur = ref.read(themeModeProvider);
            final v = await showModalBottomSheet<String>(
              context: context,
              showDragHandle: true,
              builder: (c) => SafeArea(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    for (final e in const {
                      'system': ('هماهنگ با گوشی', Icons.brightness_auto_rounded),
                      'light': ('روشن', Icons.light_mode_rounded),
                      'dark': ('تاریک', Icons.dark_mode_rounded),
                    }.entries)
                      ListTile(
                        key: Key('theme_${e.key}'),
                        leading: Icon(e.value.$2),
                        title: Text(e.value.$1),
                        trailing: cur == e.key ? const Icon(Icons.check_rounded, color: HsColors.orange) : null,
                        onTap: () => Navigator.pop(c, e.key),
                      ),
                  ],
                ),
              ),
            );
            if (v != null) await ref.read(themeModeProvider.notifier).set(v);
          case 'privacy':
            await showDialog<void>(
              context: context,
              builder: (c) => AlertDialog(
                title: const Text('حریم خصوصی'),
                content: SingleChildScrollView(
                  child: Text(
                    'اطلاعات شما و فرزندتان فقط برای معلم، مدیر مدرسه و والدین همان دانش‌آموز نمایش داده می‌شود و به هیچ شخص یا شرکت دیگری فروخته یا داده نمی‌شود. '
                    'شماره‌ی موبایل فقط برای ورود و اطلاع‌رسانی استفاده می‌شود. عکس‌ها و پست‌های «دیوار همشاگردی» فقط برای معلم و والدین همان دانش‌آموز دیده می‌شود، مگر معلم آن را روی «دیوار کلاس» بگذارد. '
                    'هر زمان بخواهید می‌توانید از منو «حذف حساب» را بزنید.\n\nمتن کامل: ${AppConfig.site}/privacy',
                    style: const TextStyle(height: 1.9),
                  ),
                ),
                actions: [TextButton(onPressed: () => Navigator.pop(c), child: const Text('باشه'))],
              ),
            );
        }
      },
      itemBuilder: (_) => [
        PopupMenuItem(
          enabled: false,
          child: Text('${me.name}\n${Fa.faDigits(me.mobile)}', style: TextStyle(color: HsColors.muted, height: 1.6)),
        ),
        const PopupMenuDivider(),
        if (kind == 'teacher') ...[const PopupMenuItem(value: 'requests', child: Text('درخواست‌های اتصال والدین')), const PopupMenuItem(value: 'link_school', child: Text('اتصال به مدرسه'))],
        if (kind == 'manager' || me.roles.contains('school_admin')) ...[
          const PopupMenuItem(value: 'school', child: Text('اطلاعات مدرسه')),
          if (kind == 'manager') const PopupMenuItem(value: 'staff', child: Text('افزودن معلم یا معاون')),
        ],
        if (kind == 'parent') ...[const PopupMenuItem(value: 'kid', child: Text('حالت کودک (سپردن گوشی به فرزند)')), const PopupMenuItem(value: 'pin', child: Text('رمز والدین'))],
        const PopupMenuItem(value: 'role', child: Text('انتخاب یا تغییر نقش')),
        const PopupMenuItem(value: 'theme', child: Text('ظاهر (روشن / تاریک)')),
        const PopupMenuItem(value: 'privacy', child: Text('حریم خصوصی')),
        const PopupMenuDivider(),
        const PopupMenuItem(value: 'logout', child: Text('خروج')),
        const PopupMenuItem(value: 'logout_all', child: Text('خروج از همه‌ی دستگاه‌ها')),
        const PopupMenuItem(
          value: 'delete',
          child: Text('حذف حساب کاربری', style: TextStyle(color: HsColors.red)),
        ),
      ],
    );
  }
}

/// فهرست کلاس‌ها (معلم: مدیریت کلاس؛ دانش‌آموز: تکالیف و اطلاعیه‌ها)
class _ClassList extends ConsumerWidget {
  const _ClassList({required this.student, this.onOpenTab});
  final bool student;
  final void Function(int)? onOpenTab;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final res = ref.watch(myClassesProvider);
    return RefreshIndicator(
      color: HsColors.orange,
      onRefresh: () async => ref.invalidate(myClassesProvider),
      child: res.when(
        loading: () => const LoadingView(),
        error: (e, _) => ListView(
          children: [
            const SizedBox(height: 80),
            ErrorView(error: e, onRetry: () => ref.invalidate(myClassesProvider)),
          ],
        ),
        data: (r) {
          final items = ((r.data as List?) ?? const []).cast<Map>();
          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 96),
            children: [
              if (!student && items.isNotEmpty) ...[ClassTools(onOpenTab: onOpenTab), const SizedBox(height: 14)],
              if (r.fromCache) ...[OfflineBanner(savedAt: r.savedAt), const SizedBox(height: 8)],
              if (items.isEmpty && student) const EmptyView(icon: Icons.class_rounded, title: 'هنوز در کلاسی نیستید'),
              if (items.isEmpty && !student) ...[
                const EmptyView(icon: Icons.class_rounded, title: 'هنوز کلاسی ندارید', text: 'اولین کلاس خود را بسازید؛ بعد دانش‌آموزان را با موبایل والدشان اضافه کنید.'),
                FilledButton.icon(key: const Key('empty_new_class'), onPressed: () => context.push('/class/new'), icon: const Icon(Icons.add_rounded), label: const Text('ساخت اولین کلاس')),
              ],
              for (final it in items)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(20),
                    onTap: () => student
                        ? Navigator.of(context).push(
                            MaterialPageRoute<void>(
                              builder: (_) => _StudentClassScreen(id: (it['id'] as num).toInt(), title: '${it['title']}'),
                            ),
                          )
                        : context.push('/class/${it['id']}'),
                    child: _HsCard(
                      leading: Container(
                        width: 56,
                        height: 56,
                        decoration: BoxDecoration(color: const Color(0xFFFFE3CC), borderRadius: BorderRadius.circular(16)),
                        child: const Icon(Icons.class_rounded, color: HsColors.orange, size: 30),
                      ),
                      title: '${it['title']}',
                      lines: [if ('${it['grade'] ?? ''}'.isNotEmpty) 'پایه‌ی ${it['grade']}', if (student) 'معلم: ${it['teacher']}' else '${Fa.faDigits(it['students'] ?? 0)} دانش‌آموز'],
                    ),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}

class _StudentClassScreen extends StatelessWidget {
  const _StudentClassScreen({required this.id, required this.title});
  final int id;
  final String title;

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: Text(title),
          bottom: const TabBar(
            labelColor: HsColors.orangeDark,
            indicatorColor: HsColors.orange,
            tabs: [
              Tab(text: 'تکالیف'),
              Tab(text: 'اطلاعیه‌ها'),
            ],
          ),
        ),
        body: TabBarView(
          children: [
            HomeworkTab(classId: id, canWrite: false),
            AnnouncementsTab(classId: id, canWrite: false),
          ],
        ),
      ),
    );
  }
}

class _HsCard extends StatelessWidget {
  const _HsCard({required this.leading, required this.title, required this.lines});
  final Widget leading;
  final String title;
  final List<String> lines;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          children: [
            leading,
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700, color: HsColors.navy),
                  ),
                  for (final l in lines) Text(l, style: TextStyle(color: HsColors.muted, height: 1.7)),
                ],
              ),
            ),
            Icon(Icons.chevron_left_rounded, color: HsColors.line),
          ],
        ),
      ),
    );
  }
}

/// کاربری که هنوز نقش فعال ندارد (مثلاً مدیر مدرسه‌ی در انتظار تأیید)
class _StatusBody extends ConsumerWidget {
  const _StatusBody({required this.me});
  final Me me;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final (icon, title, text) = switch (me.status) {
      'pending' => (Icons.hourglass_top_rounded, 'در انتظار تأیید', 'حساب شما پس از بررسی مدیر سامانه فعال می‌شود. نتیجه با پیامک اطلاع داده می‌شود.'),
      'rejected' => (Icons.block_rounded, 'درخواست تأیید نشد', 'برای پیگیری با پشتیبانی سایت تماس بگیرید.'),
      _ => (Icons.waving_hand_rounded, 'خوش آمدید', 'هنوز به کلاس یا مدرسه‌ای وصل نیستید.'),
    };
    final manager = me.roles.contains('school_admin');
    return RefreshIndicator(
      onRefresh: () => ref.read(authProvider.notifier).reloadMe(),
      child: ListView(
        padding: const EdgeInsets.symmetric(horizontal: 24),
        children: [
          const SizedBox(height: 40),
          EmptyView(icon: icon, title: title, text: manager ? 'اطلاعات مدرسه را کامل کنید تا بعد از تأیید، مدیریت مدرسه از همین اپ فعال شود.' : text),
          const SizedBox(height: 12),
          if (manager)
            FilledButton.icon(key: const Key('school_form_btn'), onPressed: () => context.push('/school/form'), icon: const Icon(Icons.account_balance_rounded), label: const Text('ثبت اطلاعات مدرسه'))
          else if (me.status != 'pending')
            FilledButton.icon(onPressed: () => context.push('/role'), icon: const Icon(Icons.how_to_reg_rounded), label: const Text('انتخاب نقش')),
        ],
      ),
    );
  }
}
