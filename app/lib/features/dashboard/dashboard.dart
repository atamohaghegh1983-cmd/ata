import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../core/utils/fa.dart';
import '../../core/widgets/hs_avatar.dart';
import '../../core/widgets/states.dart';
import '../../core/widgets/ui.dart';
import '../school/school_screens.dart' show ClaimsCard;

/// داشبورد: معلم، والد یا مدیر (از /dashboard)
class DashboardView extends ConsumerWidget {
  const DashboardView({super.key, required this.kind, this.onOpenTab});

  /// teacher | parent | manager
  final String kind;

  /// رفتن به زبانه‌ی دیگر خانه (مثلاً «کلاس‌ها»)
  final void Function(int)? onOpenTab;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final res = ref.watch(getProvider('/dashboard'));
    return RefreshIndicator(
      color: HsColors.orange,
      onRefresh: () async => ref.invalidate(getProvider('/dashboard')),
      child: res.when(
        loading: () => const LoadingView(),
        error: (e, _) => ListView(children: [const SizedBox(height: 80), ErrorView(error: e, onRetry: () => ref.invalidate(getProvider('/dashboard')))]),
        data: (r) {
          final d = (r.data as Map?) ?? const {};
          final unread = (d['unread_messages'] as num?)?.toInt() ?? 0;
          final body = switch (kind) {
            'teacher' => _teacher(context, (d['teacher'] as Map?) ?? const {}, unread),
            'manager' => _manager(context, (d['manager'] as Map?) ?? const {}),
            _ => _parent(context, (d['parent'] as Map?) ?? const {}, unread),
          };
          return ListView(
            padding: const EdgeInsets.fromLTRB(14, 4, 14, 96),
            children: [
              if (r.fromCache) ...[OfflineBanner(savedAt: r.savedAt), const SizedBox(height: 8)],
              ...body,
            ],
          );
        },
      ),
    );
  }

  /* ------------------------------ معلم ------------------------------ */

  List<Widget> _teacher(BuildContext context, Map t, int unread) {
    final tot = (t['totals'] as Map?) ?? const {};
    final classes = ((t['classes'] as List?) ?? const []).cast<Map>();
    final notRec = (tot['not_recorded'] as num?)?.toInt() ?? 0;
    if (classes.isEmpty) {
      return [
        const EmptyView(icon: Icons.dashboard_rounded, title: 'هنوز کلاسی ندارید', text: 'اولین کلاس را بسازید تا داشبورد پر شود.'),
        FilledButton.icon(onPressed: () => context.push('/class/new'), icon: const Icon(Icons.add_rounded), label: const Text('ساخت کلاس')),
      ];
    }
    return [
      if (notRec > 0)
        _Alert(
          icon: Icons.fact_check_rounded,
          color: HsColors.orangeDark,
          text: 'حضور و غیاب امروز ${Fa.faDigits(notRec)} کلاس هنوز ثبت نشده است.',
          action: 'ثبت',
          onTap: () => onOpenTab?.call(1),
        ),
      if (((t['parent_requests'] as num?) ?? 0) > 0)
        _Alert(icon: Icons.person_add_rounded, color: HsColors.blue, text: '${Fa.faDigits(t['parent_requests'])} درخواست اتصال والد منتظر تأیید شماست.', action: 'بررسی', onTap: () => context.push('/requests')),
      _Grid(tiles: [
        _Tile('دانش‌آموز', '${tot['students'] ?? 0}', Icons.groups_rounded, HsColors.blue),
        _Tile('غایب امروز', '${tot['absent_today'] ?? 0}', Icons.person_off_rounded, HsColors.red),
        _Tile('امتیاز امروز', '${tot['points_today'] ?? 0}', Icons.stars_rounded, HsColors.green),
        _Tile('تکلیف باز', '${tot['open_homework'] ?? 0}', Icons.menu_book_rounded, HsColors.orange),
        _Tile('بدون والد متصل', '${tot['unlinked_parents'] ?? 0}', Icons.link_off_rounded, HsColors.orangeDark),
        _Tile('پیام نخوانده', '$unread', Icons.forum_rounded, HsColors.navy, onTap: () => onOpenTab?.call(2)),
      ]),
      const SizedBox(height: 14),
      const Text('کلاس‌ها امروز', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16, color: HsColors.navy)),
      const SizedBox(height: 8),
      for (final c in classes)
        Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: Material(
            color: Colors.white,
            borderRadius: BorderRadius.circular(18),
            child: InkWell(
              borderRadius: BorderRadius.circular(18),
              onTap: () => context.push('/class/${c['id']}'),
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  Row(children: [
                    Expanded(child: Text('${c['title']}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16, color: HsColors.navy))),
                    Pill(c['recorded_today'] == true ? 'حضور ثبت شد' : 'حضور ثبت نشده', color: c['recorded_today'] == true ? HsColors.green : HsColors.orangeDark, icon: c['recorded_today'] == true ? Icons.check_rounded : Icons.schedule_rounded),
                  ]),
                  const SizedBox(height: 8),
                  Wrap(spacing: 6, runSpacing: 6, children: [
                    Pill('${Fa.faDigits(c['students'] ?? 0)} دانش‌آموز', color: HsColors.blue),
                    if ((c['absent_today'] ?? 0) > 0) Pill('${Fa.faDigits(c['absent_today'])} غایب', color: HsColors.red),
                    if ((c['late_today'] ?? 0) > 0) Pill('${Fa.faDigits(c['late_today'])} تأخیر', color: HsColors.amber),
                    if ((c['points_today'] ?? 0) > 0) Pill('${Fa.faDigits(c['points_today'])} امتیاز', color: HsColors.green),
                    if ((c['open_homework'] ?? 0) > 0) Pill('${Fa.faDigits(c['open_homework'])} تکلیف باز', color: HsColors.orange),
                    if ((c['unlinked_parents'] ?? 0) > 0) Pill('${Fa.faDigits(c['unlinked_parents'])} بدون والد', color: HsColors.orangeDark),
                  ]),
                ]),
              ),
            ),
          ),
        ),
    ];
  }

  /* ------------------------------ والد ------------------------------ */

  List<Widget> _parent(BuildContext context, Map p, int unread) {
    final kids = ((p['children'] as List?) ?? const []).cast<Map>();
    final anns = ((p['announcements'] as List?) ?? const []).cast<Map>();
    final props = (p['proposals'] as num?)?.toInt() ?? 0;
    return [
      if (props > 0)
        _Alert(icon: Icons.link_rounded, color: HsColors.blue, text: 'معلم شماره‌ی شما را برای ${Fa.faDigits(props)} دانش‌آموز ثبت کرده؛ در «فرزندان» تأیید کنید.', action: 'تأیید', onTap: () => onOpenTab?.call(1)),
      if (unread > 0) _Alert(icon: Icons.forum_rounded, color: HsColors.navy, text: '${Fa.faDigits(unread)} پیام نخوانده دارید.', action: 'دیدن', onTap: () => onOpenTab?.call(2)),
      if (kids.isEmpty)
        const EmptyView(icon: Icons.child_care_rounded, title: 'هنوز فرزندی متصل نشده', text: 'در زبانه‌ی «فرزندان»، دکمه‌ی «افزودن فرزند» را بزنید.'),
      for (final k in kids) ...[
        Material(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          child: InkWell(
            borderRadius: BorderRadius.circular(20),
            onTap: () => context.push('/children/${k['id']}', extra: '${k['name']}'),
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                Row(children: [
                  HsAvatar(name: '${k['name']}', url: '${k['avatar'] ?? ''}', size: 48),
                  const SizedBox(width: 12),
                  Expanded(child: Text('${k['name']}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 17, color: HsColors.navy))),
                  if (k['attendance_today'] != null) Pill('امروز: ${AttStatus.of('${k['attendance_today']}').label}', color: AttStatus.of('${k['attendance_today']}').color),
                ]),
                const SizedBox(height: 10),
                const Text('هفت روز اخیر', style: TextStyle(color: Colors.black54, fontSize: 12)),
                const SizedBox(height: 6),
                _Grid(cols: 4, compact: true, tiles: [
                  if (!_locked(k, 'points')) _Tile('امتیاز', '${k['points_week'] ?? 0}', Icons.stars_rounded, HsColors.green),
                  if (!_locked(k, 'attendance')) _Tile('غیبت', '${k['absent_week'] ?? 0}', Icons.person_off_rounded, HsColors.red),
                  if (!_locked(k, 'attendance')) _Tile('تأخیر', '${k['late_week'] ?? 0}', Icons.schedule_rounded, HsColors.amber),
                  if (!_locked(k, 'homework')) _Tile('تکلیف باز', '${k['open_homework'] ?? 0}', Icons.menu_book_rounded, HsColors.orange),
                ]),
              ]),
            ),
          ),
        ),
        const SizedBox(height: 12),
      ],
      if (anns.isNotEmpty) ...[
        const Text('آخرین اطلاعیه‌ها', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16, color: HsColors.navy)),
        const SizedBox(height: 8),
        for (final a in anns)
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: SectionCard(
              title: '${a['title']}',
              icon: Icons.campaign_rounded,
              trailing: Text(Fa.date('${a['created_at'] ?? ''}', withYear: false), style: const TextStyle(fontSize: 12, color: Colors.black45)),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('${a['class']}', style: const TextStyle(color: HsColors.orangeDark, fontSize: 12, fontWeight: FontWeight.w700)),
                Text('${a['body']}', style: const TextStyle(height: 1.7)),
              ]),
            ),
          ),
      ],
    ];
  }

  static bool _locked(Map k, String f) => ((k['locked'] as List?) ?? const []).contains(f);

  /* ------------------------------ مدیر ------------------------------ */

  List<Widget> _manager(BuildContext context, Map m) {
    if (m['school'] == null) {
      return [
        const EmptyView(icon: Icons.account_balance_rounded, title: 'مدرسه‌ی خود را ثبت کنید', text: 'اطلاعات مدرسه را وارد کنید؛ بعد از تأیید، کلاس‌ها و کادر مدرسه همین‌جا نمایش داده می‌شود.'),
        FilledButton.icon(key: const Key('dash_school_form'), onPressed: () => context.push('/school/form'), icon: const Icon(Icons.account_balance_rounded), label: const Text('ثبت اطلاعات مدرسه')),
      ];
    }
    final tot = (m['totals'] as Map?) ?? const {};
    final classes = ((m['classes'] as List?) ?? const []).cast<Map>();
    final teachers = ((m['teachers'] as List?) ?? const []).cast<Map>();
    final notRec = ((tot['classes'] as num?) ?? 0) - ((tot['recorded_today'] as num?) ?? 0);
    String pct(Object? v) => v == null ? '—' : '${Fa.faDigits(v)}٪';
    return [
      Text('${(m['school'] as Map)['name']}', style: Theme.of(context).textTheme.titleLarge),
      Row(children: [
        Expanded(child: Text('کد مدرسه: ${Fa.faDigits((m['school'] as Map)['code'] ?? '—')}', style: const TextStyle(color: HsColors.blue, fontSize: 12))),
        TextButton.icon(onPressed: () => context.push('/school/form'), icon: const Icon(Icons.edit_rounded, size: 16), label: const Text('ویرایش')),
      ]),
      const SizedBox(height: 6),
      const ClaimsCard(),
      if (notRec > 0) _Alert(icon: Icons.fact_check_rounded, color: HsColors.orangeDark, text: 'حضور و غیاب امروز ${Fa.faDigits(notRec)} کلاس هنوز ثبت نشده است.'),
      _Grid(tiles: [
        _Tile('کلاس', '${tot['classes'] ?? 0}', Icons.class_rounded, HsColors.blue, onTap: () => onOpenTab?.call(1)),
        _Tile('آموزگار', '${tot['teachers'] ?? 0}', Icons.co_present_rounded, HsColors.navy, onTap: () => onOpenTab?.call(2)),
        _Tile('دانش‌آموز', '${tot['students'] ?? 0}', Icons.groups_rounded, HsColors.blue),
        _Tile('غایب امروز', '${tot['absent_today'] ?? 0}', Icons.person_off_rounded, HsColors.red),
        _Tile('حضور ۳۰ روز', pct(tot['att_rate_30']), Icons.trending_up_rounded, HsColors.green, raw: true),
        _Tile('والدین متصل', pct(tot['parents_linked_pct']), Icons.family_restroom_rounded, HsColors.orange, raw: true),
      ]),
      if (((m['pending_invites'] as num?) ?? 0) > 0) ...[
        const SizedBox(height: 10),
        _Alert(icon: Icons.mail_outline_rounded, color: HsColors.blue, text: '${Fa.faDigits(m['pending_invites'])} دعوت کادر هنوز پذیرفته نشده است.', action: 'کادر', onTap: () => onOpenTab?.call(2)),
      ],
      const SizedBox(height: 14),
      const Text('کلاس‌ها (۳۰ روز اخیر)', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16, color: HsColors.navy)),
      const SizedBox(height: 8),
      for (final c in classes)
        Padding(
          padding: const EdgeInsets.only(bottom: 8),
          child: Material(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            child: InkWell(
              borderRadius: BorderRadius.circular(16),
              onTap: () => context.push('/class/${c['id']}'),
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Row(children: [
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text('${c['title']}', style: const TextStyle(fontWeight: FontWeight.w700, color: HsColors.navy)),
                      Text('${c['teacher']}  •  ${Fa.faDigits(c['students'] ?? 0)} دانش‌آموز  •  ${Fa.faDigits(c['homework'] ?? 0)} تکلیف', style: const TextStyle(color: Colors.black54, fontSize: 12)),
                    ]),
                  ),
                  Column(children: [
                    Text(pct(c['att_rate']), style: TextStyle(fontWeight: FontWeight.w700, color: (c['att_rate'] ?? 100) < 85 ? HsColors.red : HsColors.green)),
                    Icon(c['recorded_today'] == true ? Icons.check_circle_rounded : Icons.radio_button_unchecked_rounded, size: 18, color: c['recorded_today'] == true ? HsColors.green : Colors.black26),
                  ]),
                ]),
              ),
            ),
          ),
        ),
      if (teachers.isNotEmpty) ...[
        const SizedBox(height: 8),
        const Text('آموزگاران', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16, color: HsColors.navy)),
        const SizedBox(height: 8),
        Card(
          child: Column(children: [
            for (final t in teachers)
              ListTile(
                leading: HsAvatar(name: '${t['name']}', size: 38),
                title: Text('${t['name']}'),
                subtitle: Text('${Fa.faDigits(t['classes'] ?? 0)} کلاس  •  ${Fa.faDigits(t['students'] ?? 0)} دانش‌آموز  •  ${Fa.faDigits(t['homework'] ?? 0)} تکلیف'),
                trailing: Text(pct(t['att_rate']), style: const TextStyle(fontWeight: FontWeight.w700)),
              ),
          ]),
        ),
      ],
    ];
  }
}

class _Alert extends StatelessWidget {
  const _Alert({required this.icon, required this.color, required this.text, this.action, this.onTap});
  final IconData icon;
  final Color color;
  final String text;
  final String? action;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.fromLTRB(12, 8, 8, 8),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.10), borderRadius: BorderRadius.circular(16)),
      child: Row(children: [
        Icon(icon, color: color),
        const SizedBox(width: 10),
        Expanded(child: Text(text, style: TextStyle(color: color, height: 1.6, fontWeight: FontWeight.w500))),
        if (action != null && onTap != null) TextButton(onPressed: onTap, child: Text(action!)),
      ]),
    );
  }
}

class _Tile {
  const _Tile(this.label, this.value, this.icon, this.color, {this.onTap, this.raw = false});
  final String label;
  final String value;
  final IconData icon;
  final Color color;
  final VoidCallback? onTap;
  final bool raw;
}

class _Grid extends StatelessWidget {
  const _Grid({required this.tiles, this.cols = 3, this.compact = false});
  final List<_Tile> tiles;
  final int cols;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    return GridView.count(
      crossAxisCount: cols,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: 8,
      crossAxisSpacing: 8,
      childAspectRatio: compact ? 1.0 : 0.95,
      children: [
        for (final t in tiles)
          Material(
            color: compact ? t.color.withValues(alpha: 0.08) : Colors.white,
            borderRadius: BorderRadius.circular(16),
            child: InkWell(
              borderRadius: BorderRadius.circular(16),
              onTap: t.onTap,
              child: Padding(
                padding: const EdgeInsets.all(6),
                child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                  Icon(t.icon, color: t.color, size: compact ? 20 : 26),
                  const SizedBox(height: 4),
                  Text(t.raw ? t.value : Fa.faDigits(t.value), style: TextStyle(fontSize: compact ? 18 : 24, fontWeight: FontWeight.w700, color: t.color)),
                  Text(t.label, textAlign: TextAlign.center, maxLines: 2, style: TextStyle(fontSize: compact ? 11 : 12, color: Colors.black54, height: 1.3)),
                ]),
              ),
            ),
          ),
      ],
    );
  }
}
