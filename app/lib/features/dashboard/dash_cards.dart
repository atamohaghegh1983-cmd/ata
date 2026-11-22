import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/theme.dart';
import '../../core/utils/fa.dart';
import '../../core/widgets/hs_avatar.dart';
import '../../core/widgets/ui.dart';
import '../../core/widgets/states.dart' show showMessage;
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/providers.dart';
import '../classes/class_screen.dart' show AnnouncementCard;
import '../portfolio/portfolio.dart' show wallTitle;
import '../teach/teach_screens.dart' show BudgetRow, DashTools, pickClass;
import '../timetable/timetable.dart' show TodayTimetableCard;
import 'month_calendar.dart';

/// بدنه‌ی داشبورد معلم مثل سایت: انتخاب کلاس (اگر چند کلاس)، آمار، حضور این ماه، برترین‌ها، تکالیف پیش‌رو، بودجه‌بندی، مناسبت‌ها
class _TeacherPanelState extends State<TeacherPanelBody> {
  int? _cid; // null = همه‌ی کلاس‌ها

  @override
  Widget build(BuildContext context) {
    final t = widget.t;
    final classes = ((t['classes'] as List?) ?? const []).cast<Map>();
    final c = _cid == null ? null : classes.where((x) => x['id'] == _cid).firstOrNull;
    final tot = (t['totals'] as Map?) ?? const {};
    final month = ((c ?? t)['month'] as Map?) ?? const {};
    final top = (((c ?? t)['top'] as List?) ?? const []).cast<Map>();
    final upcoming = (((c ?? t)['upcoming'] as List?) ?? const []).cast<Map>();
    final notRec = c == null ? ((tot['not_recorded'] as num?)?.toInt() ?? 0) : (c['recorded_today'] == true ? 0 : 1);
    final students = c == null ? tot['students'] : c['students'];
    final openHw = c == null ? tot['open_homework'] : c['open_homework'];
    final budgets = c != null ? [c] : classes.where((x) => x['budget'] is Map && ((x['budget']['rows'] as List?) ?? const []).isNotEmpty).toList();
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      if (classes.length > 1)
        SizedBox(
          height: 44,
          child: ListView(scrollDirection: Axis.horizontal, children: [
            ChoiceChip(label: const Text('همه‌ی کلاس‌ها'), selected: _cid == null, onSelected: (_) => setState(() => _cid = null)),
            for (final x in classes)
              Padding(
                padding: const EdgeInsetsDirectional.only(start: 6),
                child: ChoiceChip(label: Text('🏫 ${x['title']}'), selected: _cid == x['id'], onSelected: (_) => setState(() => _cid = (x['id'] as num).toInt())),
              ),
          ]),
        ),
      const SizedBox(height: 8),
      if (notRec > 0)
        DashAlert(icon: Icons.fact_check_rounded, color: HsColors.orangeDark, text: c == null ? 'حضور و غیاب امروز ${Fa.faDigits(notRec)} کلاس هنوز ثبت نشده است.' : 'حضور و غیاب امروز این کلاس ثبت نشده است.', action: 'ثبت', onTap: () => c == null ? widget.onOpenTab?.call(1) : context.push('/class/${c['id']}?tab=1')),
      if (((t['parent_requests'] as num?) ?? 0) > 0)
        DashAlert(icon: Icons.person_add_rounded, color: HsColors.blue, text: '${Fa.faDigits(t['parent_requests'])} درخواست اتصال والد منتظر تأیید شماست.', action: 'بررسی', onTap: () => context.push('/requests')),
      StatGrid(tiles: [
        StatTile('کلاس‌ها', c == null ? '${tot['classes'] ?? classes.length}' : '۱', Icons.class_rounded, const Color(0xFF7C4DFF), sub: 'فعال', onTap: () => widget.onOpenTab?.call(1)),
        StatTile('دانش‌آموزان', '${students ?? 0}', Icons.groups_rounded, HsColors.green, sub: c == null ? 'همه‌ی کلاس‌ها' : 'در این کلاس'),
        StatTile('حضور این ماه', month['rate'] == null ? '—' : '${Fa.faDigits(month['rate'])}٪', Icons.fact_check_rounded, HsColors.blue, raw: true, sub: 'حاضر + با تأخیر'),
        StatTile('پیام خوانده‌نشده', '${widget.unread}', Icons.forum_rounded, const Color(0xFFD9468F), sub: '${Fa.faDigits(openHw ?? 0)} تکلیف باز', onTap: () => widget.onOpenTab?.call(2)),
      ]),
      const SizedBox(height: 12),
      const DashTools(),
      const SizedBox(height: 12),
      const TodayTimetableCard(),
      const SizedBox(height: 12),
      const MonthCalendarCard(),
      const SizedBox(height: 12),
      TeacherFeed(classId: _cid, classes: classes),
      const SizedBox(height: 12),
      SectionCard(
        title: 'وضعیت حضور این ماه',
        icon: Icons.pie_chart_rounded,
        child: AttBreakdown(month: month),
      ),
      const SizedBox(height: 12),
      SectionCard(
        title: 'برترین‌ها (امتیاز)',
        icon: Icons.emoji_events_rounded,
        child: top.isEmpty
            ? Text('هنوز امتیازی ثبت نشده است.', style: TextStyle(color: HsColors.faint))
            : Column(children: [
                for (final (i, s) in top.indexed)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Row(children: [
                      HsAvatar(name: '${s['name']}', url: '${s['avatar'] ?? ''}', size: 34),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text('${s['name']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                          const SizedBox(height: 3),
                          ClipRRect(
                            borderRadius: BorderRadius.circular(6),
                            child: LinearProgressIndicator(value: ((s['points'] as num) / ((top.first['points'] as num).clamp(1, 1 << 30))).toDouble(), minHeight: 6, color: HsColors.amber, backgroundColor: const Color(0x11000000)),
                          ),
                        ]),
                      ),
                      const SizedBox(width: 8),
                      Pill('${Fa.faDigits(s['points'])} ⭐', color: i == 0 ? HsColors.amber : HsColors.orangeDark),
                    ]),
                  ),
              ]),
      ),
      const SizedBox(height: 12),
      SectionCard(
        title: 'تکالیف پیش‌رو',
        icon: Icons.menu_book_rounded,
        child: upcoming.isEmpty
            ? Text('تکلیف بازی وجود ندارد.', style: TextStyle(color: HsColors.faint))
            : Column(children: [
                for (final h in upcoming)
                  ListTile(
                    dense: true,
                    contentPadding: EdgeInsets.zero,
                    onTap: () => context.push('/class/${h['class_id']}?tab=2'),
                    leading: const Icon(Icons.circle, size: 10, color: HsColors.blue),
                    title: Text('${h['title']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                    subtitle: Text([if (c == null) '${h['class']}', if ('${h['subject']}'.isNotEmpty) '${h['subject']}'].join(' • ')),
                    trailing: Pill('${h['due_date'] ?? ''}'.length >= 10 ? '${Fa.weekday(DateTime.parse('${h['due_date']}'))} ${Fa.date('${h['due_date']}', withYear: false)}' : 'بدون مهلت', color: HsColors.blue),
                  ),
              ]),
      ),
      const SizedBox(height: 12),
      SectionCard(
        title: 'بودجه‌بندی',
        icon: Icons.straighten_rounded,
        trailing: TextButton(onPressed: () => context.push('/budget/${(c ?? classes.first)['id']}', extra: '${(c ?? classes.first)['title']}'), child: const Text('ویرایش ←')),
        child: budgets.isEmpty || ((((budgets.first['budget'] as Map?)?['rows'] as List?) ?? const []).isEmpty)
            ? Text('هنوز بودجه‌بندی درس‌ها وارد نشده است.', style: TextStyle(color: HsColors.faint))
            : Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                for (final b in budgets.take(2)) ...[
                  if (budgets.length > 1) Text('${b['title']}', style: TextStyle(fontWeight: FontWeight.w700, color: HsColors.navy)),
                  for (final r in (((b['budget'] as Map)['rows'] as List?) ?? const []).cast<Map>().take(4))
                    BudgetRow(row: r, expected: ((b['budget'] as Map)['expected'] as num?)?.toInt() ?? 0),
                ],
              ]),
      ),
      const SizedBox(height: 12),
      Text('کلاس‌های من', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16, color: HsColors.navy)),
      const SizedBox(height: 8),
      for (final x in (c == null ? classes : [c])) ClassTodayCard(c: x),
    ]);
  }
}

class TeacherPanelBody extends StatefulWidget {
  const TeacherPanelBody({super.key, required this.t, required this.unread, this.onOpenTab});
  final Map t;
  final int unread;
  final void Function(int)? onOpenTab;
  @override
  State<TeacherPanelBody> createState() => _TeacherPanelState();
}

/// نوار رنگی حضور: حاضر، غایب، تأخیر، موجه
class AttBreakdown extends StatelessWidget {
  const AttBreakdown({super.key, required this.month});
  final Map month;

  @override
  Widget build(BuildContext context) {
    final parts = [
      ('حاضر', (month['present'] as num?)?.toInt() ?? 0, const Color(0xFF10B981)),
      ('غایب', (month['absent'] as num?)?.toInt() ?? 0, const Color(0xFFF43F5E)),
      ('تأخیر', (month['late'] as num?)?.toInt() ?? 0, const Color(0xFFF59E0B)),
      ('موجه', (month['excused'] as num?)?.toInt() ?? 0, const Color(0xFF3B82F6)),
    ];
    final all = parts.fold<int>(0, (a, b) => a + b.$2);
    if (all == 0) return Text('این ماه هنوز حضوری ثبت نشده است.', style: TextStyle(color: HsColors.faint));
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      ClipRRect(
        borderRadius: BorderRadius.circular(10),
        child: SizedBox(
          height: 18,
          child: Row(children: [
            for (final p in parts)
              if (p.$2 > 0) Expanded(flex: p.$2, child: Container(color: p.$3)),
          ]),
        ),
      ),
      const SizedBox(height: 8),
      Wrap(spacing: 12, runSpacing: 4, children: [
        for (final p in parts)
          Row(mainAxisSize: MainAxisSize.min, children: [
            Container(width: 10, height: 10, decoration: BoxDecoration(color: p.$3, shape: BoxShape.circle)),
            const SizedBox(width: 4),
            Text('${p.$1} ${Fa.faDigits(p.$2)} (${Fa.faDigits((p.$2 * 100 / all).round())}٪)', style: const TextStyle(fontSize: 12)),
          ]),
      ]),
    ]);
  }
}

class ClassTodayCard extends StatelessWidget {
  const ClassTodayCard({super.key, required this.c});
  final Map c;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: HsColors.card,
        borderRadius: BorderRadius.circular(18),
        child: InkWell(
          borderRadius: BorderRadius.circular(18),
          onTap: () => context.push('/class/${c['id']}'),
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Row(children: [
                Expanded(child: Text('${c['title']}', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16, color: HsColors.navy))),
                Pill(c['recorded_today'] == true ? 'حضور ثبت شد' : 'حضور ثبت نشده', color: c['recorded_today'] == true ? HsColors.green : HsColors.orangeDark, icon: c['recorded_today'] == true ? Icons.check_rounded : Icons.schedule_rounded),
              ]),
              const SizedBox(height: 8),
              Wrap(spacing: 6, runSpacing: 6, children: [
                if ('${c['grade'] ?? ''}'.isNotEmpty) Pill('پایه‌ی ${c['grade']}', color: const Color(0xFF7C4DFF)),
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
    );
  }
}

class DashAlert extends StatelessWidget {
  const DashAlert({super.key, required this.icon, required this.color, required this.text, this.action, this.onTap});
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

class StatTile {
  const StatTile(this.label, this.value, this.icon, this.color, {this.sub = '', this.onTap, this.raw = false});
  final String label, value, sub;
  final IconData icon;
  final Color color;
  final VoidCallback? onTap;
  final bool raw;
}

/// کارت‌های آمار دوتایی (مثل سایت)
class StatGrid extends StatelessWidget {
  const StatGrid({super.key, required this.tiles});
  final List<StatTile> tiles;

  @override
  Widget build(BuildContext context) {
    return GridView.count(
      crossAxisCount: 2,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: 8,
      crossAxisSpacing: 8,
      childAspectRatio: 1.9,
      children: [
        for (final t in tiles)
          Material(
            color: HsColors.card,
            borderRadius: BorderRadius.circular(16),
            child: InkWell(
              borderRadius: BorderRadius.circular(16),
              onTap: t.onTap,
              child: Padding(
                padding: const EdgeInsets.all(10),
                child: Row(children: [
                  CircleAvatar(radius: 20, backgroundColor: t.color.withValues(alpha: 0.12), child: Icon(t.icon, color: t.color)),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
                      Text(t.raw ? t.value : Fa.faDigits(t.value), style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700, color: t.color)),
                      Text(t.label, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 12, color: HsColors.text87)),
                      if (t.sub.isNotEmpty) Text(t.sub, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 10.5, color: HsColors.faint)),
                    ]),
                  ),
                ]),
              ),
            ),
          ),
      ],
    );
  }
}

/// کارت هر فرزند در داشبورد والد (مثل خانه‌ی پنل والدین سایت)
class ParentChildCard extends StatelessWidget {
  const ParentChildCard({super.key, required this.k, required this.locked, this.onOpenTab});
  final Map k;
  final bool Function(String feature) locked;
  final void Function(int)? onOpenTab;

  @override
  Widget build(BuildContext context) {
    final id = k['id'];
    final name = '${k['name']}';
    final classes = ((k['classes'] as List?) ?? const []).cast<Map>();
    final c0 = classes.firstOrNull;
    final pending = ((k['pending'] as List?) ?? const []).cast<Map>();
    final events = ((k['events'] as List?) ?? const []).cast<Map>();
    final att = k['attendance_today'];
    final quick = <(String, IconData, Color, String)>[
      if (!locked('homework')) ('تکالیف', Icons.menu_book_rounded, HsColors.blue, '/children/$id'),
      ('گزارش پیشرفت', Icons.insights_rounded, const Color(0xFF0F9D8F), '/progress/$id'),
      if (!locked('attendance')) ('حضور', Icons.fact_check_rounded, HsColors.green, '/children/$id'),
      ('برنامه هفتگی', Icons.calendar_view_week_rounded, const Color(0xFF00838F), '/timetable?child=$id'),
      ('تقویم', Icons.calendar_month_rounded, HsColors.orangeDark, '/calendar?child=$id'),
      if (c0 != null && (c0['teacher_id'] ?? 0) != 0) ('پیام به معلم', Icons.forum_rounded, const Color(0xFFD9468F), '/messages/${c0['teacher_id']}'),
      ('دیوار همشاگردی', Icons.photo_album_rounded, const Color(0xFF7C4DFF), '/portfolio/$id'),
    ];
    return Material(
      color: HsColors.card,
      borderRadius: BorderRadius.circular(20),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          InkWell(
            onTap: () => context.push('/children/$id', extra: name),
            child: Row(children: [
              HsAvatar(name: name, url: '${k['avatar'] ?? ''}', size: 52),
              const SizedBox(width: 12),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(name, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 17, color: HsColors.navy)),
                  if (c0 != null) Text('${c0['title']}${'${c0['teacher']}'.isNotEmpty ? ' · معلم: ${c0['teacher']}' : ''}', style: TextStyle(color: HsColors.muted, fontSize: 12.5)),
                ]),
              ),
              Icon(Icons.chevron_left_rounded, color: HsColors.line),
            ]),
          ),
          const SizedBox(height: 12),
          Row(children: [
            if (!locked('homework')) _mini('${Fa.faDigits(k['hw_done'] ?? 0)}/${Fa.faDigits(k['hw_total'] ?? 0)}', 'تکلیف انجام‌شده', HsColors.blue),
            if (!locked('points')) _mini(Fa.faDigits(k['stars'] ?? 0), 'ستاره', HsColors.amber),
            if (!locked('attendance')) _mini(att == null ? '—' : AttStatus.of('$att').label, 'حضور امروز', att == null ? HsColors.faint2 : AttStatus.of('$att').color),
          ]),
          if (!locked('attendance') || !locked('points')) ...[
            const SizedBox(height: 6),
            Text(
              'هفت روز اخیر: ${[if (!locked('attendance')) '${Fa.faDigits(k['absent_week'] ?? 0)} غیبت، ${Fa.faDigits(k['late_week'] ?? 0)} تأخیر', if (!locked('points')) '${Fa.faDigits(k['points_week'] ?? 0)} امتیاز'].join(' • ')}',
              style: TextStyle(fontSize: 12, color: HsColors.muted),
            ),
          ],
          if (!locked('homework')) ...[
            const SizedBox(height: 10),
            Text('تکالیف باز', style: TextStyle(fontWeight: FontWeight.w700, color: HsColors.navy)),
            if (pending.isEmpty) Text('تکلیف باز و معوقی نیست. 👍', style: TextStyle(color: HsColors.faint)),
            for (final h in pending)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Row(children: [
                  const Icon(Icons.circle, size: 8, color: HsColors.orange),
                  const SizedBox(width: 8),
                  Expanded(child: Text('${h['title']}${'${h['subject']}'.isNotEmpty ? ' · ${h['subject']}' : ''}', maxLines: 1, overflow: TextOverflow.ellipsis)),
                  if ('${h['due_date'] ?? ''}'.length >= 10) Text('مهلت: ${Fa.weekday(DateTime.parse('${h['due_date']}'))} ${Fa.date('${h['due_date']}', withYear: false)}', style: const TextStyle(fontSize: 11.5, color: HsColors.blue)),
                ]),
              ),
          ],
          const SizedBox(height: 12),
          Text('دسترسی سریع', style: TextStyle(fontWeight: FontWeight.w700, color: HsColors.navy)),
          const SizedBox(height: 6),
          GridView.count(
            crossAxisCount: 3,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            mainAxisSpacing: 6,
            crossAxisSpacing: 6,
            childAspectRatio: 1.35,
            children: [
              for (final q in quick)
                Material(
                  color: q.$3.withValues(alpha: 0.08),
                  borderRadius: BorderRadius.circular(14),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(14),
                    onTap: () {
                      if (q.$1 == 'پیام به معلم' && c0?['can_message'] == false) {
                        showMessage(context, 'فعلاً امکان پیام به معلم این کلاس نیست؛ یا معلم بخش «پیام» را برای این دانش‌آموز بسته، یا خودتان معلم همین کلاس هستید.', error: true);
                        return;
                      }
                      context.push(q.$4, extra: q.$1 == 'پیام به معلم' ? '${c0?['teacher'] ?? ''}' : name);
                    },
                    child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                      Icon(q.$2, color: q.$3),
                      const SizedBox(height: 4),
                      Text(q.$1, textAlign: TextAlign.center, style: const TextStyle(fontSize: 11.5)),
                    ]),
                  ),
                ),
            ],
          ),
          if (events.isNotEmpty) ...[
            const SizedBox(height: 10),
            Text('مناسبت‌ها و رویدادهای پیش‌رو', style: TextStyle(fontWeight: FontWeight.w700, color: HsColors.navy)),
            for (final e in events)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Row(children: [
                  Text('${e['icon']}', style: const TextStyle(fontSize: 16)),
                  const SizedBox(width: 8),
                  Expanded(child: Text('${e['title']}', maxLines: 1, overflow: TextOverflow.ellipsis)),
                  Text(Fa.date('${e['date']}', withYear: false), style: TextStyle(fontSize: 11.5, color: HsColors.muted)),
                ]),
              ),
          ],
        ]),
      ),
    );
  }

  Widget _mini(String v, String l, Color c) => Expanded(
        child: Container(
          margin: const EdgeInsetsDirectional.only(end: 6),
          padding: const EdgeInsets.symmetric(vertical: 8),
          decoration: BoxDecoration(color: c.withValues(alpha: 0.08), borderRadius: BorderRadius.circular(14)),
          child: Column(children: [
            Text(v, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 17, color: c)),
            Text(l, style: TextStyle(fontSize: 11, color: HsColors.muted)),
          ]),
        ),
      );
}

/// داشبورد معلم: اطلاعیه‌ها و دیوار کلاس (همه‌ی کلاس‌ها یا کلاس انتخاب‌شده)
class TeacherFeed extends ConsumerWidget {
  const TeacherFeed({super.key, this.classId, required this.classes});
  final int? classId;
  final List<Map> classes;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final path = classId == null ? '/teacher/feed' : '/teacher/feed?class_id=$classId';
    final d = (ref.watch(getProvider(path)).valueOrNull?.data as Map?) ?? const {};
    final anns = ((d['announcements'] as List?) ?? const []).cast<Map>().where((a) => a['state'] != 'archived').toList();
    final wall = ((d['wall'] as List?) ?? const []).cast<Map>();
    Future<void> go(int tab) async {
      final c = classId != null ? classes.firstWhere((x) => x['id'] == classId) : await pickClass(context, ref);
      if (c != null && context.mounted) {
        await context.push('/class/${c['id']}?tab=$tab');
        ref.invalidate(getProvider(path));
      }
    }

    final one = classId != null ? classes.where((x) => x['id'] == classId).firstOrNull : null;
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      SectionCard(
        title: 'اطلاعیه‌ها',
        icon: Icons.campaign_rounded,
        trailing: TextButton.icon(key: const Key('dash_new_ann'), onPressed: () => go(3), icon: const Icon(Icons.add_rounded, size: 18), label: const Text('اطلاعیه‌ی تازه')),
        child: anns.isEmpty
            ? Text('اطلاعیه‌ی فعالی نیست.', style: TextStyle(color: HsColors.faint))
            : Column(children: [
                for (final a in anns.take(3))
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      if (classId == null) Text('${a['class']}', style: const TextStyle(color: HsColors.orangeDark, fontSize: 12, fontWeight: FontWeight.w700)),
                      AnnouncementCard(a: a, onChanged: () => ref.invalidate(getProvider(path))),
                    ]),
                  ),
              ]),
      ),
      const SizedBox(height: 12),
      SectionCard(
        title: one != null ? wallTitle('${one['title']}') : 'دیوار کلاس‌ها',
        icon: Icons.photo_album_rounded,
        trailing: TextButton(onPressed: () => go(4), child: const Text('همه ←')),
        child: wall.isEmpty
            ? Text('هنوز پستی روی دیوار نیست.', style: TextStyle(color: HsColors.faint))
            : SizedBox(
                height: 150,
                child: ListView(scrollDirection: Axis.horizontal, children: [
                  for (final p in wall)
                    GestureDetector(
                      onTap: () => context.push('/class/${p['class_id']}?tab=4'),
                      child: Container(
                        width: 130,
                        margin: const EdgeInsetsDirectional.only(end: 8),
                        decoration: BoxDecoration(color: const Color(0xFFFFF8F2), borderRadius: BorderRadius.circular(14), border: p['pinned'] == true ? Border.all(color: HsColors.orange, width: 2) : null),
                        clipBehavior: Clip.antiAlias,
                        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                          Expanded(
                            child: '${p['image'] ?? ''}'.isNotEmpty
                                ? Image.network('${p['image']}', fit: BoxFit.cover, errorBuilder: (_, _, _) => Icon(Icons.image_rounded, color: HsColors.line))
                                : const Icon(Icons.brush_rounded, size: 36, color: HsColors.orange),
                          ),
                          Padding(
                            padding: const EdgeInsets.all(6),
                            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              Text('${p['pinned'] == true ? '📌 ' : ''}${p['title']}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12)),
                              Text('${p['student']}', maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 11, color: HsColors.muted)),
                            ]),
                          ),
                        ]),
                      ),
                    ),
                ]),
              ),
      ),
    ]);
  }
}
