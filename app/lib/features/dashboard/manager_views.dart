import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../core/utils/fa.dart';
import '../../core/widgets/hs_avatar.dart';
import '../../core/widgets/confirm.dart';
import '../../core/widgets/states.dart';
import '../../core/widgets/ui.dart';

/// مدیر: فهرست کلاس‌های مدرسه (فقط‌خواندنی)
class SchoolClassesView extends ConsumerWidget {
  const SchoolClassesView({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final res = ref.watch(getProvider('/school'));
    return RefreshIndicator(
      color: HsColors.orange,
      onRefresh: () async => ref.invalidate(getProvider('/school')),
      child: res.when(
        loading: () => const LoadingView(),
        error: (e, _) => ListView(children: [const SizedBox(height: 80), ErrorView(error: e, onRetry: () => ref.invalidate(getProvider('/school')))]),
        data: (r) {
          final classes = ((((r.data as Map?)?['classes']) as List?) ?? const []).cast<Map>();
          return ListView(padding: const EdgeInsets.fromLTRB(14, 4, 14, 24), children: [
            if (r.fromCache) ...[OfflineBanner(savedAt: r.savedAt), const SizedBox(height: 8)],
            if (classes.isEmpty) const EmptyView(icon: Icons.class_rounded, title: 'هنوز کلاسی به مدرسه وصل نیست', text: 'کلاس‌های آموزگارانی که دعوت مدرسه را بپذیرند اینجا می‌آید.'),
            for (final c in classes)
              Card(
                margin: const EdgeInsets.only(bottom: 8),
                child: ListTile(
                  onTap: () => context.push('/class/${c['id']}'),
                  leading: const Icon(Icons.class_rounded, color: HsColors.blue, size: 30),
                  title: Text('${c['title']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                  subtitle: Text('${c['teacher']}  •  ${Fa.faDigits(c['students'] ?? 0)} دانش‌آموز'),
                  trailing: const Icon(Icons.chevron_left_rounded),
                ),
              ),
          ]);
        },
      ),
    );
  }
}

/// مدیر: کادر مدرسه و دعوت‌ها
class StaffView extends ConsumerWidget {
  const StaffView({super.key});

  static const _status = {'active': ('منتظر پذیرش', HsColors.orange), 'accepted': ('پذیرفته', HsColors.green), 'revoked': ('لغو', Colors.grey), 'declined': ('رد شده', HsColors.red)};

  Future<void> _remove(BuildContext context, WidgetRef ref, Map m) async {
    final ok = await confirmDanger(context, title: 'حذف ${m['name']} از کادر', text: 'از کادر مدرسه حذف می‌شود و کلاس‌هایش دیگر زیر نظر مدرسه نیست. حساب کاربری و کلاس‌های خودش پاک نمی‌شود.');
    if (!ok) return;
    try {
      await ref.read(apiProvider).delete<Object?>('/school/staff/${m['id']}');
      ref.invalidate(getProvider('/school'));
      ref.invalidate(getProvider('/dashboard'));
      if (context.mounted) showMessage(context, 'از کادر حذف شد.');
    } on ApiException catch (e) {
      if (context.mounted) showMessage(context, e.message, error: true);
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final res = ref.watch(getProvider('/school'));
    return RefreshIndicator(
      color: HsColors.orange,
      onRefresh: () async => ref.invalidate(getProvider('/school')),
      child: res.when(
        loading: () => const LoadingView(),
        error: (e, _) => ListView(children: [const SizedBox(height: 80), ErrorView(error: e, onRetry: () => ref.invalidate(getProvider('/school')))]),
        data: (r) {
          final s = (r.data as Map?) ?? const {};
          final staff = ((s['staff'] as List?) ?? const []).cast<Map>();
          final invites = ((s['invites'] as List?) ?? const []).cast<Map>();
          return ListView(padding: const EdgeInsets.fromLTRB(14, 4, 14, 24), children: [
            if (r.fromCache) ...[OfflineBanner(savedAt: r.savedAt), const SizedBox(height: 8)],
            if (s['is_manager'] != true)
              const EmptyView(icon: Icons.lock_outline_rounded, title: 'فقط مدیر مدرسه کادر را می‌بیند'),
            if (s['is_manager'] == true) ...[
              SectionCard(
                title: 'کادر (${Fa.faDigits(staff.length)})',
                icon: Icons.badge_rounded,
                child: Column(children: [
                  if (staff.isEmpty) const Text('هنوز کسی در کادر نیست.', style: TextStyle(color: Colors.black54)),
                  for (final m in staff)
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: HsAvatar(name: '${m['name']}', size: 40),
                      title: Text('${m['name']}'),
                      subtitle: Text('${m['label'] ?? m['position'] ?? ''}'),
                      trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                        if (m['can_view'] == true) const Pill('ناظر', color: HsColors.blue, icon: Icons.visibility_rounded),
                        IconButton(
                          tooltip: 'حذف از کادر',
                          icon: const Icon(Icons.person_remove_rounded, color: HsColors.red),
                          onPressed: () => _remove(context, ref, m),
                        ),
                      ]),
                    ),
                ]),
              ),
              const SizedBox(height: 12),
              SectionCard(
                title: 'دعوت‌ها',
                icon: Icons.mail_outline_rounded,
                child: Column(children: [
                  if (invites.isEmpty) const Text('دعوتی ثبت نشده.', style: TextStyle(color: Colors.black54)),
                  for (final i in invites)
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(Icons.person_add_alt_rounded, color: HsColors.blue),
                      title: Text('${i['name']}'.isEmpty ? 'بدون نام' : '${i['name']}'),
                      subtitle: Text(Fa.date('${i['created_at'] ?? ''}')),
                      trailing: Pill(_status['${i['status']}']?.$1 ?? '${i['status']}', color: _status['${i['status']}']?.$2 ?? Colors.grey),
                    ),
                ]),
              ),
              const SizedBox(height: 10),
              const Text('افزودن کادر و دعوت تازه فعلاً از پنل سایت انجام می‌شود.', textAlign: TextAlign.center, style: TextStyle(color: Colors.black45, fontSize: 12)),
            ],
          ]);
        },
      ),
    );
  }
}
