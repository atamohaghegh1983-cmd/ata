import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../core/utils/fa.dart';
import '../../core/widgets/hs_avatar.dart';
import '../../core/widgets/states.dart';
import '../auth/auth_controller.dart';
import '../auth/me.dart';
import '../classes/class_providers.dart';
import '../classes/class_screen.dart' show AnnouncementsTab;
import '../classes/homework_tab.dart' show HomeworkTab;
import '../magazine/magazine.dart';
import '../messages/messages_screens.dart';
import '../parent/parent_home.dart';

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

  List<_Tab> _tabs(Me me) {
    const msgs = _Tab('پیام‌ها', Icons.forum_rounded, 'پیام‌ها', InboxView());
    switch (me.homeKind) {
      case 'teacher':
        return const [
          _Tab('کلاس‌ها', Icons.class_rounded, 'کلاس‌های من', _ClassList(student: false)),
          msgs,
          _Tab('مجله', Icons.auto_stories_rounded, 'مجله', MagazineView(showMineFilter: true)),
        ];
      case 'parent':
        return const [
          _Tab('فرزندان', Icons.family_restroom_rounded, 'فرزندان من', ParentHome()),
          msgs,
          _Tab('مجله', Icons.auto_stories_rounded, 'مجله‌ی والدین', MagazineView()),
        ];
      case 'manager':
        return const [
          _Tab('مدرسه', Icons.account_balance_rounded, 'مدرسه‌ی من', _SchoolBody()),
          _Tab('مجله', Icons.auto_stories_rounded, 'مجله', MagazineView(showMineFilter: true)),
        ];
      case 'student':
        return const [
          _Tab('کلاس', Icons.class_rounded, 'کلاس من', _ClassList(student: true)),
          _Tab('مجله', Icons.auto_stories_rounded, 'مجله', MagazineView()),
        ];
    }
    return [_Tab('خانه', Icons.home_rounded, 'همشاگردی', _StatusBody(me: me))];
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authProvider);
    final me = auth.me;
    if (me == null) {
      return Scaffold(body: ErrorView(error: ApiException(ApiException.offlineMessage, offline: true), onRetry: () => ref.read(authProvider.notifier).restore()));
    }
    final tabs = _tabs(me);
    if (_i >= tabs.length) _i = 0;
    final t = tabs[_i];
    return Scaffold(
      appBar: AppBar(
        title: Text(t.title),
        leading: Padding(padding: const EdgeInsetsDirectional.only(start: 12), child: Center(child: HsAvatar(name: me.name, url: me.avatar, size: 36))),
        actions: [_Menu(me: me)],
      ),
      floatingActionButton: me.homeKind == 'teacher' && _i == 0
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
              backgroundColor: Colors.white,
              indicatorColor: const Color(0xFFFFE3CC),
              onDestinationSelected: (v) => setState(() => _i = v),
              destinations: [for (final x in tabs) NavigationDestination(icon: Icon(x.icon), label: x.label)],
            ),
      body: Column(children: [
        if (auth.offline) const OfflineBanner(),
        if (_i == 0) _Hello(me: me),
        Expanded(child: IndexedStack(index: _i, children: [for (final x in tabs) x.body])),
      ]),
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
      child: Row(children: [
        Expanded(child: Text('سلام ${me.name.split(' ').first} 👋', style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700, color: HsColors.navy))),
        Text('${Fa.weekday(DateTime.now())}، ${Fa.date(Fa.ymd(DateTime.now()))}', style: const TextStyle(color: Colors.black54, fontSize: 13)),
      ]),
    );
  }
}

class _Menu extends ConsumerWidget {
  const _Menu({required this.me});
  final Me me;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return PopupMenuButton<String>(
      icon: const Icon(Icons.more_vert_rounded),
      onSelected: (v) async {
        final all = v == 'logout_all';
        final ok = await showDialog<bool>(
          context: context,
          builder: (c) => AlertDialog(
            title: const Text('خروج از حساب'),
            content: Text(all ? 'از همه‌ی گوشی‌ها و دستگاه‌ها خارج می‌شوید.' : 'از حساب خود خارج می‌شوید.'),
            actions: [
              TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('انصراف')),
              FilledButton(style: FilledButton.styleFrom(minimumSize: const Size(90, 44)), onPressed: () => Navigator.pop(c, true), child: const Text('خروج')),
            ],
          ),
        );
        if (ok == true) await ref.read(authProvider.notifier).logout(everywhere: all);
      },
      itemBuilder: (_) => [
        PopupMenuItem(enabled: false, child: Text('${me.name}\n${Fa.faDigits(me.mobile)}', style: const TextStyle(color: Colors.black54, height: 1.6))),
        const PopupMenuDivider(),
        const PopupMenuItem(value: 'logout', child: Text('خروج')),
        const PopupMenuItem(value: 'logout_all', child: Text('خروج از همه‌ی دستگاه‌ها')),
      ],
    );
  }
}

/// فهرست کلاس‌ها (معلم: مدیریت کلاس؛ دانش‌آموز: تکالیف و اطلاعیه‌ها)
class _ClassList extends ConsumerWidget {
  const _ClassList({required this.student});
  final bool student;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final res = ref.watch(myClassesProvider);
    return RefreshIndicator(
      color: HsColors.orange,
      onRefresh: () async => ref.invalidate(myClassesProvider),
      child: res.when(
        loading: () => const LoadingView(),
        error: (e, _) => ListView(children: [const SizedBox(height: 80), ErrorView(error: e, onRetry: () => ref.invalidate(myClassesProvider))]),
        data: (r) {
          final items = ((r.data as List?) ?? const []).cast<Map>();
          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 96),
            children: [
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
                        ? Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => _StudentClassScreen(id: (it['id'] as num).toInt(), title: '${it['title']}')))
                        : context.push('/class/${it['id']}'),
                    child: _HsCard(
                      leading: Container(
                        width: 56,
                        height: 56,
                        decoration: BoxDecoration(color: const Color(0xFFFFE3CC), borderRadius: BorderRadius.circular(16)),
                        child: const Icon(Icons.class_rounded, color: HsColors.orange, size: 30),
                      ),
                      title: '${it['title']}',
                      lines: [
                        if ('${it['grade'] ?? ''}'.isNotEmpty) 'پایه‌ی ${it['grade']}',
                        if (student) 'معلم: ${it['teacher']}' else '${Fa.faDigits(it['students'] ?? 0)} دانش‌آموز',
                      ],
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
          bottom: const TabBar(labelColor: HsColors.orangeDark, indicatorColor: HsColors.orange, tabs: [Tab(text: 'تکالیف'), Tab(text: 'اطلاعیه‌ها')]),
        ),
        body: TabBarView(children: [HomeworkTab(classId: id, canWrite: false), AnnouncementsTab(classId: id, canWrite: false)]),
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
        child: Row(children: [
          leading,
          const SizedBox(width: 14),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(title, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700, color: HsColors.navy)),
              for (final l in lines) Text(l, style: const TextStyle(color: Colors.black54, height: 1.7)),
            ]),
          ),
          const Icon(Icons.chevron_left_rounded, color: Colors.black26),
        ]),
      ),
    );
  }
}

class _SchoolBody extends ConsumerWidget {
  const _SchoolBody();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final res = ref.watch(getProvider('/school'));
    return RefreshIndicator(
      color: HsColors.orange,
      onRefresh: () => ref.refresh(getProvider('/school').future),
      child: res.when(
        loading: () => const LoadingView(),
        error: (e, _) => ListView(children: [const SizedBox(height: 80), ErrorView(error: e, onRetry: () => ref.invalidate(getProvider('/school')))]),
        data: (r) {
          final s = (r.data as Map?) ?? const {};
          final classes = ((s['classes'] as List?) ?? const []).cast<Map>();
          final staff = ((s['staff'] as List?) ?? const []);
          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
            children: [
              if (r.fromCache) ...[OfflineBanner(savedAt: r.savedAt), const SizedBox(height: 8)],
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(18),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text('${s['name'] ?? ''}', style: Theme.of(context).textTheme.titleLarge),
                    const SizedBox(height: 6),
                    Text('${Fa.faDigits(classes.length)} کلاس  •  ${Fa.faDigits(staff.length)} نفر کادر', style: const TextStyle(color: Colors.black54)),
                    const SizedBox(height: 4),
                    const Text('نمای فقط‌خواندنی', style: TextStyle(color: HsColors.blue, fontSize: 13)),
                  ]),
                ),
              ),
              const SizedBox(height: 12),
              for (final c in classes)
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: _HsCard(
                    leading: const Icon(Icons.class_rounded, color: HsColors.blue, size: 32),
                    title: '${c['title']}',
                    lines: ['معلم: ${c['teacher']}  •  ${Fa.faDigits(c['students'] ?? 0)} دانش‌آموز'],
                  ),
                ),
            ],
          );
        },
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
    return RefreshIndicator(
      onRefresh: () => ref.read(authProvider.notifier).reloadMe(),
      child: ListView(children: [const SizedBox(height: 60), EmptyView(icon: icon, title: title, text: text)]),
    );
  }
}
