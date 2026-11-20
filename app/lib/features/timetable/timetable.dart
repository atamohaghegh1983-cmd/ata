import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../core/utils/fa.dart';
import '../../core/widgets/states.dart';
import '../../core/widgets/ui.dart';

/// آدرس برنامه: کلاس مشخص، فرزند، یا «برنامه‌ی من» (معلم/دانش‌آموز)
String timetablePath({int? classId, int? childId}) => classId != null ? '/classes/$classId/timetable' : (childId != null ? '/me/timetable?child=$childId' : '/me/timetable');

/// رنگ و آیکون هر درس (ثابت برای یک نام)
class SubjectStyle {
  static const _cols = [Color(0xFFE0ECFF), Color(0xFFE3F6EC), Color(0xFFFFF1D6), Color(0xFFFDE2E7), Color(0xFFEDE7FF), Color(0xFFDDF5F7), Color(0xFFFFE7D6)];
  static const _fg = [Color(0xFF2C5DA8), Color(0xFF1E8E5A), Color(0xFFB7791F), Color(0xFFC2185B), Color(0xFF5B3CC4), Color(0xFF00838F), Color(0xFFC24E00)];

  static int _h(String s) => s.runes.fold(7, (a, b) => (a * 31 + b) & 0x7fffffff);
  static Color bg(String s) => HsColors.dark ? _fg[_h(s) % _fg.length].withValues(alpha: 0.28) : _cols[_h(s) % _cols.length];
  static Color fg(String s) => HsColors.dark ? Color.lerp(_fg[_h(s) % _fg.length], Colors.white, 0.55)! : _fg[_h(s) % _fg.length];

  static IconData icon(String s) {
    bool has(List<String> k) => k.any(s.contains);
    if (has(['ریاضی', 'حسابان', 'هندسه', 'آمار', 'گسسته'])) return Icons.calculate_rounded;
    if (has(['علوم', 'فیزیک', 'شیمی', 'زیست', 'زمین'])) return Icons.science_rounded;
    if (has(['انگلیسی', 'زبان'])) return Icons.translate_rounded;
    if (has(['عربی'])) return Icons.language_rounded;
    if (has(['هنر', 'نقاشی'])) return Icons.palette_rounded;
    if (has(['تربیت', 'ورزش', 'بازی'])) return Icons.sports_soccer_rounded;
    if (has(['قرآن', 'هدیه', 'دین', 'پیام'])) return Icons.auto_stories_rounded;
    if (has(['اجتماعی', 'تاریخ', 'جغرافیا', 'جامعه'])) return Icons.public_rounded;
    if (has(['فناوری', 'کار'])) return Icons.build_rounded;
    if (has(['تفکر', 'فلسفه', 'منطق', 'روان'])) return Icons.psychology_rounded;
    return Icons.menu_book_rounded;
  }
}

/// شماره‌ی روز مدرسه‌ای امروز (شنبه = ۰)
int todayIndex() => (DateTime.now().weekday + 1) % 7; // DateTime: دوشنبه=۱ … یکشنبه=۷ ← شنبه(۶)=۰

class TimetableScreen extends ConsumerStatefulWidget {
  const TimetableScreen({super.key, this.classId, this.childId, this.title = ''});
  final int? classId;
  final int? childId;
  final String title;
  @override
  ConsumerState<TimetableScreen> createState() => _TimetableScreenState();
}

class _TimetableScreenState extends ConsumerState<TimetableScreen> {
  bool _week = false;
  bool _edit = false;
  bool _busy = false;
  Map<String, Map<String, dynamic>> _draft = {};
  Set<String> _bad = {};

  String get _path => timetablePath(classId: widget.classId, childId: widget.childId);
  static String _k(int d, int p) => '${d}_$p';

  void _startEdit(Map d) {
    _draft = {
      for (final c in ((d['cells'] as List?) ?? const []).cast<Map>())
        _k((c['day'] as num).toInt(), (c['period'] as num).toInt()): {'subject': '${c['subject']}', 'teacher_id': c['teacher_id'], 'teacher': '${c['teacher']}', 'span': ((c['span'] as num?) ?? 1).toInt()},
    };
    _bad = {};
    setState(() {
      _edit = true;
      _week = true;
    });
  }

  Future<void> _save() async {
    setState(() => _busy = true);
    try {
      await ref.read(apiProvider).post<Object?>(_path, data: {
        'cells': [
          for (final e in _draft.entries)
            {'day': int.parse(e.key.split('_')[0]), 'period': int.parse(e.key.split('_')[1]), 'subject': e.value['subject'], 'teacher_id': e.value['teacher_id'], 'span': e.value['span'] ?? 1},
        ],
      });
      ref.invalidate(getProvider(_path));
      ref.invalidate(getProvider('/me/timetable'));
      if (mounted) {
        setState(() => _edit = false);
        showMessage(context, 'برنامه ذخیره شد ✅');
      }
    } on ApiException catch (e) {
      if (!mounted) return;
      final conf = ((e.data?['conflicts'] as List?) ?? const []).cast<Map>();
      setState(() => _bad = {for (final c in conf) _k((c['day'] as num).toInt(), (c['period'] as num).toInt())});
      await showDialog<void>(
        context: context,
        builder: (c) => AlertDialog(
          icon: const Icon(Icons.warning_amber_rounded, color: HsColors.red, size: 40),
          title: const Text('تداخل در برنامه'),
          content: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
            if (conf.isEmpty) Text(e.message, style: const TextStyle(height: 1.8)),
            for (final x in conf) Padding(padding: const EdgeInsets.only(bottom: 6), child: Text('• ${x['message']}', style: const TextStyle(height: 1.8))),
            const SizedBox(height: 4),
            Text('خانه‌های قرمز را عوض کنید (معلم دیگر یا زنگ دیگر) و دوباره ذخیره کنید.', style: TextStyle(color: HsColors.muted, fontSize: 13)),
          ]),
          actions: [FilledButton(style: FilledButton.styleFrom(minimumSize: const Size(90, 44)), onPressed: () => Navigator.pop(c), child: const Text('باشه'))],
        ),
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _editCell(Map d, int day, int period) async {
    final teachers = ((d['teachers'] as List?) ?? const []).cast<Map>();
    final cur = _draft[_k(day, period)];
    final sub = TextEditingController(text: '${cur?['subject'] ?? ''}');
    int? tid = (cur?['teacher_id'] as num?)?.toInt() ?? (teachers.isNotEmpty ? (teachers.first['id'] as num).toInt() : null);
    var span = ((cur?['span'] as num?) ?? 1).toInt();
    // حداکثر زنگ پشت سر هم بدون تفریح از این زنگ
    final slots = (((d['bells'] as Map)['slots'] as List?) ?? const []).cast<Map>();
    var maxSpan = 0;
    var started = false;
    for (final sl in slots) {
      if (!started && sl['type'] == 'class' && (sl['period'] as num).toInt() == period) started = true;
      if (!started) continue;
      if (sl['type'] != 'class') break;
      maxSpan++;
    }
    maxSpan = maxSpan.clamp(1, 3);
    final used = {for (final v in _draft.values) '${v['subject']}'}..removeWhere((x) => x.isEmpty);
    final days = ((d['bells'] as Map)['day_names'] as List).cast<String>();
    final res = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (c) => StatefulBuilder(
        builder: (c, set) => Padding(
          padding: EdgeInsets.fromLTRB(20, 0, 20, 20 + MediaQuery.of(c).viewInsets.bottom),
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Text('${days[day]} — زنگ ${Fa.faDigits(period)}', style: Theme.of(c).textTheme.titleLarge),
            const SizedBox(height: 10),
            TextField(key: const Key('tt_subject'), controller: sub, autofocus: cur == null, decoration: const InputDecoration(labelText: 'درس')),
            if (used.isNotEmpty) ...[
              const SizedBox(height: 6),
              Wrap(spacing: 6, runSpacing: 4, children: [for (final u in used) ActionChip(label: Text(u), onPressed: () => sub.text = u)]),
            ],
            if (maxSpan > 1) ...[
              const SizedBox(height: 10),
              const Text('مدت درس', style: TextStyle(fontWeight: FontWeight.w700)),
              const SizedBox(height: 4),
              Wrap(spacing: 6, children: [
                for (var k = 1; k <= maxSpan; k++)
                  ChoiceChip(key: Key('span_$k'), label: Text(k == 1 ? 'یک زنگ' : (k == 2 ? 'دو زنگ (دوزنگه)' : 'سه زنگ')), selected: span == k, onSelected: (_) => set(() => span = k)),
              ]),
            ],
            if (teachers.length > 1) ...[
              const SizedBox(height: 10),
              DropdownButtonFormField<int>(
                initialValue: tid,
                isExpanded: true,
                decoration: const InputDecoration(labelText: 'معلم این زنگ'),
                items: [for (final t in teachers) DropdownMenuItem(value: (t['id'] as num).toInt(), child: Text('${t['name']}'))],
                onChanged: (v) => set(() => tid = v),
              ),
            ],
            const SizedBox(height: 14),
            Row(children: [
              if (cur != null) TextButton(onPressed: () => Navigator.pop(c, 'clear'), child: const Text('خالی کردن زنگ', style: TextStyle(color: HsColors.red))),
              const Spacer(),
              FilledButton(style: FilledButton.styleFrom(minimumSize: const Size(110, 46)), onPressed: () => Navigator.pop(c, 'ok'), child: const Text('تأیید')),
            ]),
          ]),
        ),
      ),
    );
    if (res == null) return;
    setState(() {
      _bad.remove(_k(day, period));
      if (res == 'clear' || sub.text.trim().isEmpty) {
        _draft.remove(_k(day, period));
      } else {
        final t = teachers.where((x) => x['id'] == tid).firstOrNull;
        _draft[_k(day, period)] = {'subject': sub.text.trim(), 'teacher_id': tid, 'teacher': '${t?['name'] ?? ''}', 'span': span};
        // خانه‌هایی که زیر این درس چندزنگه رفته‌اند پاک می‌شوند
        for (var k = 1; k < span; k++) {
          _draft.remove(_k(day, period + k));
        }
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final res = ref.watch(getProvider(_path));
    final d = (res.valueOrNull?.data as Map?) ?? const {};
    final canEdit = d['can_edit'] == true;
    final title = widget.title.isNotEmpty ? widget.title : ('${d['class'] ?? ''}'.isNotEmpty ? '${d['class']}' : 'برنامه‌ی هفتگی من');
    return Scaffold(
      appBar: AppBar(
        title: Text(_edit ? 'ویرایش برنامه' : 'برنامه هفتگی — $title'),
        actions: [
          if (!_edit)
            IconButton(
              tooltip: _week ? 'نمای روزانه' : 'جدول هفته',
              onPressed: () => setState(() => _week = !_week),
              icon: Icon(_week ? Icons.view_day_rounded : Icons.grid_on_rounded),
            ),
          if (canEdit && !_edit)
            PopupMenuButton<String>(
              onSelected: (v) {
                if (v == 'edit') _startEdit(d);
                if (v == 'bells') context.push('/bells', extra: {'class_id': widget.classId, 'title': title});
              },
              itemBuilder: (_) => const [
                PopupMenuItem(value: 'edit', child: Text('ویرایش برنامه')),
                PopupMenuItem(value: 'bells', child: Text('تنظیم زنگ‌ها و ساعت‌ها')),
              ],
            ),
        ],
      ),
      bottomNavigationBar: _edit
          ? SafeArea(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 4, 16, 10),
                child: Row(children: [
                  TextButton(onPressed: _busy ? null : () => setState(() => _edit = false), child: const Text('انصراف')),
                  const SizedBox(width: 8),
                  Expanded(child: FilledButton(key: const Key('tt_save'), onPressed: _busy ? null : _save, child: busyLabel(_busy, 'ذخیره‌ی برنامه'))),
                ]),
              ),
            )
          : null,
      floatingActionButton: canEdit && !_edit && ((d['cells'] as List?) ?? const []).isEmpty
          ? FloatingActionButton.extended(backgroundColor: HsColors.orange, foregroundColor: Colors.white, onPressed: () => _startEdit(d), icon: const Icon(Icons.edit_calendar_rounded), label: const Text('چیدن برنامه'))
          : null,
      body: res.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(getProvider(_path))),
        data: (_) {
          if (d['mode'] == 'none') return const EmptyView(icon: Icons.calendar_view_week_rounded, title: 'هنوز در کلاسی نیست');
          final cells = _edit
              ? _draft.map((k, v) => MapEntry(k, {...v, 'day': int.parse(k.split('_')[0]), 'period': int.parse(k.split('_')[1])}))
              : {for (final c in ((d['cells'] as List?) ?? const []).cast<Map>()) _k((c['day'] as num).toInt(), (c['period'] as num).toInt()): c};
          final bells = (d['bells'] as Map?) ?? const {};
          final teacherMode = d['mode'] == 'teacher';
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(getProvider(_path)),
            child: ListView(padding: const EdgeInsets.fromLTRB(10, 8, 10, 24), children: [
              if (_edit)
                Padding(
                  padding: EdgeInsets.only(bottom: 8),
                  child: Text('روی هر خانه بزنید تا درس و معلم آن زنگ را تعیین کنید. هر معلم در هر زنگ فقط می‌تواند در یک کلاس باشد.', style: TextStyle(color: HsColors.muted, height: 1.7, fontSize: 13)),
                ),
              if (cells.isEmpty && !_edit)
                EmptyView(icon: Icons.calendar_view_week_rounded, title: 'برنامه‌ی هفتگی هنوز وارد نشده', text: canEdit ? 'با دکمه‌ی «چیدن برنامه» درس هر زنگ را وارد کنید.' : (teacherMode ? 'برنامه‌ی کلاس‌هایتان را از صفحه‌ی هر کلاس وارد کنید.' : 'وقتی معلم یا مدیر برنامه را وارد کند، اینجا نمایش داده می‌شود.')),
              if (cells.isNotEmpty || _edit)
                (_week || _edit)
                    ? WeekGrid(bells: bells, cells: cells, showClass: teacherMode, editing: _edit, bad: _bad, onTap: _edit ? (dd, pp) => _editCell(d, dd, pp) : null)
                    : DayList(bells: bells, cells: cells, showClass: teacherMode),
              if (d['score'] is Map && !_edit) ...[const SizedBox(height: 12), ScoreCard(score: d['score'] as Map)],
            ]),
          );
        },
      ),
    );
  }
}

/// خانه‌هایی که زیر درس چندزنگه افتاده‌اند: {"day_period"}
Set<String> coveredCells(Map<String, Map> cells) {
  final out = <String>{};
  for (final c in cells.values) {
    final span = ((c['span'] as num?) ?? 1).toInt();
    for (var k = 1; k < span; k++) {
      out.add('${c['day']}_${((c['period'] as num).toInt()) + k}');
    }
  }
  return out;
}

/// جدول هفته (افقی قابل پیمایش روی گوشی)؛ هر روز یک ستون، درس چندزنگه یک خانه‌ی بلند
class WeekGrid extends StatelessWidget {
  const WeekGrid({super.key, required this.bells, required this.cells, this.showClass = false, this.editing = false, this.bad = const {}, this.onTap});
  final Map bells;
  final Map<String, Map> cells;
  final bool showClass, editing;
  final Set<String> bad;
  final void Function(int day, int period)? onTap;

  static const rowH = 64.0, gap = 6.0, breakH = 34.0;

  @override
  Widget build(BuildContext context) {
    final days = ((bells['day_names'] as List?) ?? const []).cast<String>();
    final slots = ((bells['slots'] as List?) ?? const []).cast<Map>();
    final today = todayIndex();
    final covered = coveredCells(cells);
    const w = 92.0;
    Widget timeCol() => SizedBox(
          width: 62,
          child: Column(children: [
            const SizedBox(height: 38),
            for (final s in slots)
              s['type'] == 'break'
                  ? const SizedBox(height: breakH + gap, child: Center(child: Text('☕')))
                  : SizedBox(
                      height: rowH + gap,
                      child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                        Text(Fa.faDigits(s['period']), style: const TextStyle(fontWeight: FontWeight.w700)),
                        Text('${Fa.faDigits(s['start'])}\n${Fa.faDigits(s['end'])}', textAlign: TextAlign.center, style: TextStyle(fontSize: 10.5, color: HsColors.faint, height: 1.3)),
                      ]),
                    ),
          ]),
        );
    Widget dayCol(int d) {
      final children = <Widget>[
        SizedBox(height: 38, child: Center(child: Text(days[d], style: TextStyle(fontWeight: FontWeight.w700, color: d == today ? HsColors.orangeDark : HsColors.navy)))),
      ];
      for (var i = 0; i < slots.length; i++) {
        final s = slots[i];
        if (s['type'] == 'break') {
          children.add(Container(
            height: breakH,
            margin: const EdgeInsets.only(bottom: gap),
            decoration: BoxDecoration(color: HsColors.blue.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(10)),
            alignment: Alignment.center,
            child: Text('تفریح', style: TextStyle(fontSize: 11, color: HsColors.blue)),
          ));
          continue;
        }
        final key = '${d}_${s['period']}';
        if (covered.contains(key)) continue;
        final c = cells[key];
        final span = ((c?['span'] as num?) ?? 1).toInt();
        children.add(Padding(
          padding: const EdgeInsets.only(bottom: gap),
          child: _Cell(
            width: w - 6,
            height: rowH * span + gap * (span - 1),
            c: c,
            today: d == today,
            showClass: showClass,
            editing: editing,
            bad: bad.contains(key),
            onTap: onTap == null ? null : () => onTap!(d, (s['period'] as num).toInt()),
          ),
        ));
      }
      return SizedBox(width: w, child: Padding(padding: const EdgeInsets.symmetric(horizontal: 3), child: Column(children: children)));
    }

    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [timeCol(), for (var d = 0; d < days.length; d++) dayCol(d)]),
    );
  }
}

class _Cell extends StatelessWidget {
  const _Cell({required this.width, this.height = 64, required this.c, required this.today, required this.showClass, required this.editing, required this.bad, this.onTap});
  final double width, height;
  final Map? c;
  final bool today, showClass, editing, bad;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final sub = '${c?['subject'] ?? ''}';
    final empty = sub.isEmpty;
    return Material(
      color: empty ? (editing ? HsColors.card : const Color(0x08000000)) : SubjectStyle.bg(sub),
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Container(
          width: width,
          height: height,
          padding: const EdgeInsets.all(6),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(12),
            border: bad ? Border.all(color: HsColors.red, width: 2) : (today && !empty ? Border.all(color: HsColors.orange, width: 2) : (editing && empty ? Border.all(color: const Color(0x22000000)) : null)),
          ),
          child: empty
              ? (editing ? Icon(Icons.add_rounded, color: HsColors.line) : null)
              : Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Row(children: [
                    Icon(SubjectStyle.icon(sub), size: 14, color: SubjectStyle.fg(sub)),
                    const SizedBox(width: 3),
                    Expanded(child: Text(sub, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12.5, color: SubjectStyle.fg(sub)))),
                  ]),
                  Text(showClass ? '${c?['class'] ?? ''}' : '${c?['teacher'] ?? ''}', maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 10.5, color: HsColors.muted)),
                  if (((c?['span'] as num?) ?? 1) > 1) Text('${Fa.faDigits(c?['span'])} زنگ', style: TextStyle(fontSize: 10, color: SubjectStyle.fg(sub))),
                ]),
        ),
      ),
    );
  }
}

/// نمای روزانه: هر روز یک زبانه، امروز پیش‌فرض
class DayList extends StatelessWidget {
  const DayList({super.key, required this.bells, required this.cells, this.showClass = false});
  final Map bells;
  final Map<String, Map> cells;
  final bool showClass;

  @override
  Widget build(BuildContext context) {
    final days = ((bells['day_names'] as List?) ?? const []).cast<String>();
    final t = todayIndex();
    return DefaultTabController(
      length: days.length,
      initialIndex: t < days.length ? t : 0,
      child: Column(children: [
        TabBar(
          isScrollable: true,
          tabAlignment: TabAlignment.start,
          labelColor: HsColors.orangeDark,
          indicatorColor: HsColors.orange,
          tabs: [for (final (i, n) in days.indexed) Tab(text: i == t ? '$n (امروز)' : n)],
        ),
        SizedBox(
          height: 90.0 * ((bells['slots'] as List?) ?? const []).length + 20,
          child: TabBarView(children: [
            for (var d = 0; d < days.length; d++) Padding(padding: const EdgeInsets.only(top: 8), child: DaySlots(bells: bells, cells: cells, day: d, showClass: showClass)),
          ]),
        ),
      ]),
    );
  }
}

/// زنگ‌های یک روز (برای نمای روزانه و کارت داشبورد)
class DaySlots extends StatelessWidget {
  const DaySlots({super.key, required this.bells, required this.cells, required this.day, this.showClass = false, this.compact = false});
  final Map bells;
  final Map<String, Map> cells;
  final int day;
  final bool showClass, compact;

  static bool _now(Map s) {
    final n = TimeOfDay.now();
    int m(String hm) => int.parse(hm.split(':')[0]) * 60 + int.parse(hm.split(':')[1]);
    final x = n.hour * 60 + n.minute;
    return x >= m('${s['start']}') && x < m('${s['end']}');
  }

  @override
  Widget build(BuildContext context) {
    final slots = ((bells['slots'] as List?) ?? const []).cast<Map>();
    final isToday = day == todayIndex();
    final covered = coveredCells(cells);
    String endOf(int period, int span) {
      final last = slots.where((x) => x['type'] == 'class' && (x['period'] as num).toInt() == period + span - 1).firstOrNull;
      return '${last?['end'] ?? ''}';
    }

    return Column(children: [
      for (final s in slots)
        if (s['type'] == 'break')
          compact
              ? const SizedBox.shrink()
              : Padding(
                  padding: const EdgeInsets.symmetric(vertical: 4),
                  child: Text('☕ زنگ تفریح ${Fa.faDigits(s['start'])} تا ${Fa.faDigits(s['end'])}', style: const TextStyle(color: HsColors.blue, fontSize: 12.5)),
                )
        else
          Builder(builder: (_) {
            if (covered.contains('${day}_${s['period']}')) return const SizedBox.shrink();
            final c = cells['${day}_${s['period']}'];
            final sub = '${c?['subject'] ?? ''}';
            final span = ((c?['span'] as num?) ?? 1).toInt();
            if (compact && sub.isEmpty) return const SizedBox.shrink();
            final now = isToday && _now(s);
            return Container(
              margin: const EdgeInsets.only(bottom: 6),
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: sub.isEmpty ? const Color(0x08000000) : SubjectStyle.bg(sub),
                borderRadius: BorderRadius.circular(14),
                border: now ? Border.all(color: HsColors.orange, width: 2) : null,
              ),
              child: Row(children: [
                SizedBox(
                  width: 56,
                  child: Column(children: [
                    Text(span > 1 ? 'زنگ ${Fa.faDigits(s['period'])}-${Fa.faDigits((s['period'] as num) + span - 1)}' : 'زنگ ${Fa.faDigits(s['period'])}', style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700)),
                    Text(span > 1 ? '${Fa.faDigits(s['start'])}\n${Fa.faDigits(endOf((s['period'] as num).toInt(), span))}' : Fa.faDigits(s['start']), textAlign: TextAlign.center, style: TextStyle(fontSize: 11, color: HsColors.faint)),
                  ]),
                ),
                if (sub.isNotEmpty) Icon(SubjectStyle.icon(sub), color: SubjectStyle.fg(sub)),
                const SizedBox(width: 8),
                Expanded(
                  child: sub.isEmpty
                      ? Text('—', style: TextStyle(color: HsColors.faint2))
                      : Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text(sub, style: TextStyle(fontWeight: FontWeight.w700, color: SubjectStyle.fg(sub))),
                          Text(showClass ? '${c?['class'] ?? ''}' : '${c?['teacher'] ?? ''}', style: TextStyle(fontSize: 12, color: HsColors.muted)),
                        ]),
                ),
                if (now) const Pill('الان', color: HsColors.orange),
              ]),
            );
          }),
    ]);
  }
}

/// کارت «بهینه‌سازی برنامه»
class ScoreCard extends StatelessWidget {
  const ScoreCard({super.key, required this.score});
  final Map score;

  @override
  Widget build(BuildContext context) {
    final v = (score['score'] as num?)?.toInt() ?? 0;
    final checks = ((score['checks'] as List?) ?? const []).cast<Map>();
    final col = v >= 85 ? HsColors.green : (v >= 60 ? HsColors.orange : HsColors.red);
    return SectionCard(
      title: 'بهینه‌سازی برنامه',
      icon: Icons.auto_awesome_rounded,
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        SizedBox(
          width: 86,
          height: 86,
          child: Stack(alignment: Alignment.center, children: [
            SizedBox(width: 86, height: 86, child: CircularProgressIndicator(value: v / 100, strokeWidth: 8, color: col, backgroundColor: const Color(0x14000000))),
            Text('${Fa.faDigits(v)}٪', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w700, color: col)),
          ]),
        ),
        const SizedBox(width: 14),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            for (final c in checks)
              Padding(
                padding: const EdgeInsets.only(bottom: 4),
                child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Icon(c['ok'] == true ? Icons.check_circle_rounded : Icons.error_rounded, size: 18, color: c['ok'] == true ? HsColors.green : HsColors.orangeDark),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text.rich(TextSpan(children: [
                      TextSpan(text: '${c['label']}', style: const TextStyle(fontSize: 13)),
                      if ('${c['detail'] ?? ''}'.isNotEmpty) TextSpan(text: '\n${c['detail']}', style: TextStyle(fontSize: 11.5, color: HsColors.muted)),
                    ])),
                  ),
                ]),
              ),
          ]),
        ),
      ]),
    );
  }
}

/// کارت داشبورد: برنامه‌ی امروز
class TodayTimetableCard extends ConsumerWidget {
  const TodayTimetableCard({super.key, this.childId});
  final int? childId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final path = timetablePath(childId: childId);
    final d = (ref.watch(getProvider(path)).valueOrNull?.data as Map?) ?? const {};
    final cells = {for (final c in ((d['cells'] as List?) ?? const []).cast<Map>()) '${c['day']}_${c['period']}': c};
    final bells = (d['bells'] as Map?) ?? const {};
    final t = todayIndex();
    final days = ((bells['day_names'] as List?) ?? const []).cast<String>();
    final holiday = t >= days.length;
    final hasToday = cells.keys.any((k) => k.startsWith('${t}_'));
    return SectionCard(
      title: 'برنامه‌ی امروز',
      icon: Icons.calendar_view_week_rounded,
      trailing: TextButton(
        key: const Key('open_timetable'),
        onPressed: () => context.push(childId != null ? '/timetable?child=$childId' : '/timetable'),
        child: const Text('برنامه هفتگی ←'),
      ),
      child: d.isEmpty
          ? Text('…', style: TextStyle(color: HsColors.faint2))
          : cells.isEmpty
              ? Text('برنامه‌ی هفتگی هنوز وارد نشده است. از «برنامه هفتگی» هر کلاس آن را بچینید.', style: TextStyle(color: HsColors.faint, height: 1.7))
              : holiday || !hasToday
                  ? Text(holiday ? 'امروز تعطیل است 🌤️' : 'امروز زنگ درسی ندارید.', style: TextStyle(color: HsColors.muted))
                  : DaySlots(bells: bells, cells: cells, day: t, showClass: d['mode'] == 'teacher', compact: true),
    );
  }
}

/* ============================== زنگ‌بندی ============================== */

/// تنظیم زنگ‌ها: برای یک کلاس (classId) یا کل مدرسه (school=true، فقط مدیر)
class BellsScreen extends ConsumerStatefulWidget {
  const BellsScreen({super.key, this.classId, this.school = false, this.title = ''});
  final int? classId;
  final bool school;
  final String title;
  @override
  ConsumerState<BellsScreen> createState() => _BellsScreenState();
}

class _BellsScreenState extends ConsumerState<BellsScreen> {
  int _days = 5;
  List<Map<String, String>> _slots = [];
  String _source = '';
  bool _loaded = false;
  bool _busy = false;

  String get _getPath => widget.school ? '/school/bells' : '/classes/${widget.classId}/timetable';
  String get _savePath => widget.school ? '/school/bells' : '/classes/${widget.classId}/bells';

  void _load(Map d) {
    final b = widget.school ? d : (d['bells'] as Map? ?? const {});
    _days = (b['days'] as num?)?.toInt() ?? 5;
    _source = '${b['source'] ?? ''}';
    _slots = [for (final s in ((b['slots'] as List?) ?? const []).cast<Map>()) {'type': '${s['type']}', 'start': '${s['start']}', 'end': '${s['end']}'}];
    _loaded = true;
  }

  Future<void> _pick(int i, String key) async {
    final p = _slots[i][key]!.split(':');
    final t = await showTimePicker(
      context: context,
      initialTime: TimeOfDay(hour: int.tryParse(p[0]) ?? 8, minute: int.tryParse(p.length > 1 ? p[1] : '0') ?? 0),
      builder: (c, child) => MediaQuery(data: MediaQuery.of(c).copyWith(alwaysUse24HourFormat: true), child: child!),
    );
    if (t == null) return;
    setState(() => _slots[i][key] = '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}');
  }

  String _plus(String hm, int min) {
    final p = hm.split(':');
    final x = (int.parse(p[0]) * 60 + int.parse(p[1]) + min).clamp(0, 23 * 60 + 59);
    return '${(x ~/ 60).toString().padLeft(2, '0')}:${(x % 60).toString().padLeft(2, '0')}';
  }

  Future<void> _save({bool reset = false}) async {
    setState(() => _busy = true);
    try {
      await ref.read(apiProvider).post<Object?>(_savePath, data: reset ? {'reset': true} : {'days': _days, 'slots': _slots});
      ref.invalidate(getProvider(_getPath));
      if (widget.classId != null) ref.invalidate(getProvider('/classes/${widget.classId}/timetable'));
      ref.invalidate(getProvider('/me/timetable'));
      if (mounted) {
        showMessage(context, reset ? 'زنگ‌بندی مدرسه برای این کلاس برگشت.' : 'زنگ‌ها ذخیره شد ✅');
        Navigator.pop(context);
      }
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final res = ref.watch(getProvider(_getPath));
    if (res.hasValue && !_loaded) _load((res.value!.data as Map?) ?? const {});
    var n = 0;
    return Scaffold(
      appBar: AppBar(title: Text(widget.school ? 'زنگ‌بندی مدرسه' : 'زنگ‌های ${widget.title}')),
      bottomNavigationBar: SafeArea(child: Padding(padding: const EdgeInsets.fromLTRB(16, 4, 16, 10), child: FilledButton(key: const Key('bells_save'), onPressed: _busy || !_loaded ? null : _save, child: busyLabel(_busy, 'ذخیره')))),
      body: res.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(getProvider(_getPath))),
        data: (_) => ListView(padding: const EdgeInsets.fromLTRB(14, 8, 14, 24), children: [
          Text(
            switch (_source) {
              'class' => 'این کلاس زنگ‌بندی جدا دارد.',
              'school' => 'زنگ‌بندی مدرسه استفاده می‌شود؛ تغییر اینجا فقط برای همین کلاس است.',
              _ => 'زنگ‌بندی پیش‌فرض همشاگردی؛ هر طور می‌خواهید تغییر دهید.',
            },
            style: TextStyle(color: HsColors.muted, height: 1.7),
          ),
          if (_source == 'class' && !widget.school) TextButton(onPressed: _busy ? null : () => _save(reset: true), child: const Text('برگشت به زنگ‌بندی مدرسه/پیش‌فرض')),
          const SizedBox(height: 8),
          const Text('روزهای درسی', style: TextStyle(fontWeight: FontWeight.w700)),
          const SizedBox(height: 6),
          SegmentedButton<int>(
            segments: const [ButtonSegment(value: 5, label: Text('شنبه تا چهارشنبه')), ButtonSegment(value: 6, label: Text('تا پنجشنبه'))],
            selected: {_days},
            onSelectionChanged: (s) => setState(() => _days = s.first),
          ),
          const SizedBox(height: 14),
          for (final (i, s) in _slots.indexed)
            Card(
              margin: const EdgeInsets.only(bottom: 6),
              color: s['type'] == 'break' ? const Color(0xFFE8F0FB) : HsColors.card,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(10, 6, 4, 6),
                child: Row(children: [
                  SizedBox(
                    width: 70,
                    child: Text(s['type'] == 'break' ? '☕ تفریح' : 'زنگ ${Fa.faDigits(++n)}', style: const TextStyle(fontWeight: FontWeight.w700)),
                  ),
                  TextButton(onPressed: () => _pick(i, 'start'), child: Text(Fa.faDigits(s['start']!), style: const TextStyle(fontSize: 16))),
                  const Text('تا'),
                  TextButton(onPressed: () => _pick(i, 'end'), child: Text(Fa.faDigits(s['end']!), style: const TextStyle(fontSize: 16))),
                  const Spacer(),
                  IconButton(icon: const Icon(Icons.close_rounded, size: 20), onPressed: _slots.length <= 1 ? null : () => setState(() => _slots.removeAt(i))),
                ]),
              ),
            ),
          const SizedBox(height: 6),
          Row(children: [
            Expanded(
              child: OutlinedButton.icon(
                onPressed: _slots.length >= 14
                    ? null
                    : () => setState(() {
                          final st = _slots.isEmpty ? '07:45' : _plus(_slots.last['end']!, 5);
                          _slots.add({'type': 'class', 'start': st, 'end': _plus(st, 45)});
                        }),
                icon: const Icon(Icons.add_rounded),
                label: const Text('زنگ درسی'),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: OutlinedButton.icon(
                onPressed: _slots.length >= 14 || _slots.isEmpty
                    ? null
                    : () => setState(() {
                          final st = _slots.last['end']!;
                          _slots.add({'type': 'break', 'start': st, 'end': _plus(st, 15)});
                        }),
                icon: const Icon(Icons.coffee_rounded),
                label: const Text('زنگ تفریح'),
              ),
            ),
          ]),
        ]),
      ),
    );
  }
}
