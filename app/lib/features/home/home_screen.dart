import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_client.dart';
import '../../core/api/api_exception.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../core/utils/fa.dart';
import '../../core/widgets/hs_avatar.dart';
import '../../core/widgets/states.dart';
import '../auth/auth_controller.dart';
import '../auth/me.dart';
import '../classes/class_providers.dart';

/// خواندن یک مسیر با کش آفلاین
final _getProvider = FutureProvider.autoDispose.family<ApiResult<Object?>, String>((ref, path) {
  return ref.read(apiProvider).get<Object?>(path);
});

/// خانه‌ی مخصوص هر نقش
class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authProvider);
    final me = auth.me;
    if (me == null) {
      // نشست هست ولی اینترنت قطع بود و کشی نداریم
      return Scaffold(
        body: ErrorView(
          error: ApiException(ApiException.offlineMessage, offline: true),
          onRetry: () => ref.read(authProvider.notifier).restore(),
        ),
      );
    }
    final (title, body) = switch (me.homeKind) {
      'teacher' => ('کلاس‌های من', const _ListBody(path: '/classes', kind: _Kind.teacherClasses)),
      'parent' => ('فرزندان من', const _ListBody(path: '/children', kind: _Kind.children)),
      'manager' => ('مدرسه‌ی من', const _SchoolBody()),
      'student' => ('کلاس من', const _ListBody(path: '/classes', kind: _Kind.studentClasses)),
      _ => ('همشاگردی', _StatusBody(me: me)),
    };
    return Scaffold(
      floatingActionButton: me.homeKind == 'teacher'
          ? FloatingActionButton.extended(
              key: const Key('new_class'),
              backgroundColor: HsColors.orange,
              foregroundColor: Colors.white,
              onPressed: () => context.push('/class/new'),
              icon: const Icon(Icons.add_rounded),
              label: const Text('کلاس جدید'),
            )
          : null,
      appBar: AppBar(
        title: Text(title),
        leading: Padding(
          padding: const EdgeInsetsDirectional.only(start: 12),
          child: Center(child: HsAvatar(name: me.name, url: me.avatar, size: 36)),
        ),
        actions: [_Menu(me: me)],
      ),
      body: Column(children: [
        if (auth.offline) const OfflineBanner(),
        _Hello(me: me),
        Expanded(child: body),
      ]),
    );
  }
}

class _Hello extends StatelessWidget {
  const _Hello({required this.me});
  final Me me;

  @override
  Widget build(BuildContext context) {
    final today = Fa.date(Fa.ymd(DateTime.now()));
    final wd = Fa.weekday(DateTime.now());
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 4, 20, 8),
      child: Row(children: [
        Expanded(
          child: Text('سلام ${me.name.split(' ').first} 👋', style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700, color: HsColors.navy)),
        ),
        Text('$wd، $today', style: const TextStyle(color: Colors.black54, fontSize: 13)),
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
        if (v == 'logout' || v == 'logout_all') {
          final ok = await showDialog<bool>(
            context: context,
            builder: (c) => AlertDialog(
              title: const Text('خروج از حساب'),
              content: Text(v == 'logout_all' ? 'از همه‌ی گوشی‌ها و دستگاه‌ها خارج می‌شوید.' : 'از حساب خود خارج می‌شوید.'),
              actions: [
                TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('انصراف')),
                FilledButton(style: FilledButton.styleFrom(minimumSize: const Size(90, 44)), onPressed: () => Navigator.pop(c, true), child: const Text('خروج')),
              ],
            ),
          );
          if (ok == true) await ref.read(authProvider.notifier).logout(everywhere: v == 'logout_all');
        }
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

enum _Kind { teacherClasses, studentClasses, children }

class _ListBody extends ConsumerWidget {
  const _ListBody({required this.path, required this.kind});
  final String path;
  final _Kind kind;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final prov = kind == _Kind.children ? _getProvider(path) : myClassesProvider;
    final res = ref.watch(prov);
    return RefreshIndicator(
      color: HsColors.orange,
      onRefresh: () async => ref.invalidate(prov),
      child: res.when(
        loading: () => const LoadingView(),
        error: (e, _) => ListView(children: [const SizedBox(height: 80), ErrorView(error: e, onRetry: () => ref.invalidate(prov))]),
        data: (r) {
          final items = ((r.data as List?) ?? const []).cast<Map>();
          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 96),
            children: [
              if (r.fromCache) ...[OfflineBanner(savedAt: r.savedAt), const SizedBox(height: 8)],
              if (items.isEmpty) _empty(context),
              for (final it in items)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: kind == _Kind.children
                      ? _card(it)
                      : InkWell(
                          borderRadius: BorderRadius.circular(20),
                          onTap: () => context.push('/class/${it['id']}'),
                          child: _card(it),
                        ),
                ),
            ],
          );
        },
      ),
    );
  }

  Widget _empty(BuildContext context) => switch (kind) {
        _Kind.children => const EmptyView(
            icon: Icons.child_care_rounded,
            title: 'هنوز فرزندی متصل نشده',
            text: 'اگر معلم شماره‌ی شما را در پرونده‌ی فرزندتان ثبت کرده باشد، خودکار وصل می‌شوید.\nافزودن فرزند با اسکن QR کلاس در نسخه‌ی بعد.',
          ),
        _Kind.teacherClasses => Column(children: [
            const EmptyView(icon: Icons.class_rounded, title: 'هنوز کلاسی ندارید', text: 'اولین کلاس خود را بسازید؛ بعد دانش‌آموزان را اضافه کنید و QR دعوت را برای والدین بفرستید.'),
            FilledButton.icon(
              key: const Key('empty_new_class'),
              onPressed: () => context.push('/class/new'),
              icon: const Icon(Icons.add_rounded),
              label: const Text('ساخت اولین کلاس'),
            ),
          ]),
        _Kind.studentClasses => const EmptyView(icon: Icons.class_rounded, title: 'هنوز در کلاسی نیستید'),
      };

  Widget _card(Map it) {
    if (kind == _Kind.children) {
      final classes = ((it['classes'] as List?) ?? const []).cast<Map>();
      final first = classes.isEmpty ? null : classes.first;
      return _HsCard(
        leading: HsAvatar(name: '${it['name']}', url: '${it['avatar'] ?? ''}', size: 56),
        title: '${it['name']}',
        lines: [
          if (first != null) '${first['title']}${(first['grade'] ?? '') != '' ? ' — پایه‌ی ${first['grade']}' : ''}',
          if (first != null && '${first['teacher']}'.isNotEmpty) 'معلم: ${first['teacher']}',
          if (classes.isEmpty) 'هنوز در کلاسی ثبت نشده',
        ],
      );
    }
    return _HsCard(
      leading: Container(
        width: 56,
        height: 56,
        decoration: BoxDecoration(color: const Color(0xFFFFE3CC), borderRadius: BorderRadius.circular(16)),
        child: const Icon(Icons.class_rounded, color: HsColors.orange, size: 30),
      ),
      title: '${it['title']}',
      lines: [
        if ('${it['grade'] ?? ''}'.isNotEmpty) 'پایه‌ی ${it['grade']}',
        if (kind == _Kind.teacherClasses) '${Fa.faDigits(it['students'] ?? 0)} دانش‌آموز' else 'معلم: ${it['teacher']}',
      ],
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
    final res = ref.watch(_getProvider('/school'));
    return RefreshIndicator(
      color: HsColors.orange,
      onRefresh: () => ref.refresh(_getProvider('/school').future),
      child: res.when(
        loading: () => const LoadingView(),
        error: (e, _) => ListView(children: [const SizedBox(height: 80), ErrorView(error: e, onRetry: () => ref.invalidate(_getProvider('/school')))]),
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
