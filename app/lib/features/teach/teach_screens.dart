import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/config.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../core/utils/fa.dart';
import '../../core/widgets/attachments.dart';
import '../../core/widgets/confirm.dart';
import '../../core/widgets/date_pickers.dart';
import '../../core/widgets/hs_avatar.dart';
import '../../core/widgets/states.dart';
import '../../core/widgets/ui.dart';
import '../classes/class_providers.dart';

/// انتخاب کلاس (اگر معلم یک کلاس دارد، همان)
Future<Map?> pickClass(BuildContext context, WidgetRef ref, {String title = 'کدام کلاس؟'}) async {
  final r = await ref.read(myClassesProvider.future);
  final list = ((r.data as List?) ?? const []).cast<Map>();
  if (!context.mounted) return null;
  if (list.isEmpty) {
    showMessage(context, 'اول یک کلاس بسازید.', error: true);
    return null;
  }
  if (list.length == 1) return list.first;
  return showModalBottomSheet<Map>(
    context: context,
    showDragHandle: true,
    builder: (c) => SafeArea(
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Text(title, style: Theme.of(c).textTheme.titleMedium),
        for (final x in list) ListTile(leading: const Icon(Icons.class_rounded, color: HsColors.orange), title: Text('${x['title']}'), onTap: () => Navigator.pop(c, x)),
      ]),
    ),
  );
}

/// ابزارهای معلم (مثل منوی پنل سایت)
class TeacherTools extends ConsumerWidget {
  const TeacherTools({super.key, this.onOpenTab});
  final void Function(int)? onOpenTab;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    Future<void> withClass(String Function(Map c) path) async {
      final c = await pickClass(context, ref);
      if (c != null && context.mounted) context.push(path(c), extra: '${c['title']}');
    }

    final items = <(String, IconData, Color, VoidCallback)>[
      ('کلاس‌ها', Icons.class_rounded, HsColors.blue, () => onOpenTab?.call(1)),
      ('افزودن دانش‌آموز', Icons.person_add_alt_1_rounded, const Color(0xFF0F9D8F), () => withClass((c) => '/class/${c['id']}')),
      ('حضور و غیاب', Icons.fact_check_rounded, HsColors.green, () => withClass((c) => '/class/${c['id']}?tab=1')),
      ('تشویق و امتیاز', Icons.stars_rounded, HsColors.orange, () => withClass((c) => '/class/${c['id']}')),
      ('ارزیابی و نمرات', Icons.grading_rounded, const Color(0xFFD9468F), () => withClass((c) => '/assessments/${c['id']}')),
      ('تکالیف', Icons.menu_book_rounded, HsColors.blue, () => withClass((c) => '/class/${c['id']}?tab=2')),
      ('کتابخانه کاربرگ', Icons.collections_bookmark_rounded, HsColors.green, () => context.push('/worksheets')),
      ('بودجه‌بندی', Icons.straighten_rounded, const Color(0xFF7C4DFF), () => withClass((c) => '/budget/${c['id']}')),
      ('تقویم', Icons.calendar_month_rounded, HsColors.orangeDark, () => context.push('/calendar')),
      ('ارتباط با والدین', Icons.forum_rounded, const Color(0xFFD9468F), () => onOpenTab?.call(2)),
    ];
    final site = <(String, String)>[
      ('تکلیف‌ساز آنلاین', 'online-builder'),
      ('دفتر تکالیف', 'notebook'),
      ('استودیو محتوا', 'content-studio'),
      ('گزارش پیشرفت', 'reports'),
      ('ابزارهای کلاس', 'tools'),
    ];
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      GridView.count(
        crossAxisCount: 4,
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        mainAxisSpacing: 8,
        crossAxisSpacing: 8,
        childAspectRatio: 0.82,
        children: [
          for (final it in items)
            Material(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              child: InkWell(
                key: Key('tool_${it.$1}'),
                borderRadius: BorderRadius.circular(16),
                onTap: it.$4,
                child: Padding(
                  padding: const EdgeInsets.all(4),
                  child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                    CircleAvatar(radius: 20, backgroundColor: it.$3.withValues(alpha: 0.12), child: Icon(it.$2, color: it.$3, size: 22)),
                    const SizedBox(height: 6),
                    Text(it.$1, textAlign: TextAlign.center, maxLines: 2, style: const TextStyle(fontSize: 11.5, height: 1.3, fontWeight: FontWeight.w500)),
                  ]),
                ),
              ),
            ),
        ],
      ),
      const SizedBox(height: 6),
      Wrap(spacing: 6, runSpacing: 4, children: [
        for (final s in site)
          ActionChip(
            avatar: const Icon(Icons.open_in_new_rounded, size: 16),
            label: Text('${s.$1} (سایت)', style: const TextStyle(fontSize: 12)),
            onPressed: () => openUrl(context, '${AppConfig.site}/panel/${s.$2}/'),
          ),
      ]),
    ]);
  }
}

/* ============================== ارزیابی و نمرات ============================== */

class AssessmentsScreen extends ConsumerWidget {
  const AssessmentsScreen({super.key, required this.classId, this.title = ''});
  final int classId;
  final String title;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final path = '/classes/$classId/assessments';
    final res = ref.watch(getProvider(path));
    final canEdit = (res.valueOrNull?.data as Map?)?['can_edit'] == true;
    return Scaffold(
      appBar: AppBar(title: Text(title.isEmpty ? 'ارزیابی و نمرات' : 'ارزیابی — $title')),
      floatingActionButton: canEdit
          ? FloatingActionButton.extended(
              key: const Key('new_assessment'),
              backgroundColor: HsColors.orange,
              foregroundColor: Colors.white,
              onPressed: () async {
                final id = await showModalBottomSheet<int>(context: context, isScrollControlled: true, showDragHandle: true, builder: (_) => _NewAssessmentSheet(classId: classId));
                ref.invalidate(getProvider(path));
                if (id != null && context.mounted) context.push('/assessment/$id');
              },
              icon: const Icon(Icons.add_rounded),
              label: const Text('ارزیابی تازه'),
            )
          : null,
      body: RefreshIndicator(
        onRefresh: () async => ref.invalidate(getProvider(path)),
        child: res.when(
          loading: () => const LoadingView(),
          error: (e, _) => ListView(children: [const SizedBox(height: 80), ErrorView(error: e, onRetry: () => ref.invalidate(getProvider(path)))]),
          data: (r) {
            final items = ((((r.data as Map?)?['items']) as List?) ?? const []).cast<Map>();
            if (items.isEmpty) {
              return ListView(children: const [SizedBox(height: 60), EmptyView(icon: Icons.grading_rounded, title: 'هنوز ارزیابی‌ای ثبت نشده', text: 'مثلاً «املای هفته‌ی دوم» یا «آزمون ریاضی فصل ۳». ارزیابی توصیفی (خیلی خوب، خوب…) یا نمره‌ای.')]);
            }
            return ListView(padding: const EdgeInsets.fromLTRB(14, 10, 14, 96), children: [
              for (final a in items)
                Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: ListTile(
                    onTap: () async {
                      await context.push('/assessment/${a['id']}');
                      ref.invalidate(getProvider(path));
                    },
                    leading: CircleAvatar(
                      backgroundColor: const Color(0x1FD9468F),
                      child: Icon(a['scale'] == 'numeric' ? Icons.pin_rounded : Icons.grading_rounded, color: const Color(0xFFD9468F)),
                    ),
                    title: Text('${a['title']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                    subtitle: Text([if ('${a['subject']}'.isNotEmpty) '${a['subject']}', Fa.date('${a['date']}', withYear: false)].join(' • ')),
                    trailing: Pill('${Fa.faDigits(a['recorded'] ?? 0)}/${Fa.faDigits(a['students'] ?? 0)}', color: (a['recorded'] ?? 0) >= (a['students'] ?? 0) ? HsColors.green : HsColors.orangeDark),
                  ),
                ),
            ]);
          },
        ),
      ),
    );
  }
}

class _NewAssessmentSheet extends ConsumerStatefulWidget {
  const _NewAssessmentSheet({required this.classId});
  final int classId;
  @override
  ConsumerState<_NewAssessmentSheet> createState() => _NewAssessmentSheetState();
}

class _NewAssessmentSheetState extends ConsumerState<_NewAssessmentSheet> {
  final _t = TextEditingController();
  final _s = TextEditingController();
  String _scale = 'descriptive';
  DateTime _date = DateUtils.dateOnly(DateTime.now());
  bool _busy = false;

  Future<void> _save() async {
    if (_t.text.trim().isEmpty) {
      showMessage(context, 'عنوان را بنویسید.', error: true);
      return;
    }
    setState(() => _busy = true);
    try {
      final r = await ref.read(apiProvider).post<Map<String, dynamic>>('/classes/${widget.classId}/assessments', data: {'title': _t.text.trim(), 'subject': _s.text.trim(), 'scale': _scale, 'date': Fa.ymd(_date)});
      if (mounted) Navigator.pop(context, (r['id'] as num).toInt());
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 0, 20, 20 + MediaQuery.of(context).viewInsets.bottom),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Text('ارزیابی تازه', style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: 12),
        TextField(key: const Key('as_title'), controller: _t, autofocus: true, decoration: const InputDecoration(labelText: 'عنوان *', hintText: 'مثلاً املای هفته‌ی دوم')),
        const SizedBox(height: 10),
        TextField(controller: _s, decoration: const InputDecoration(labelText: 'درس')),
        const SizedBox(height: 10),
        SegmentedButton<String>(
          segments: const [ButtonSegment(value: 'descriptive', label: Text('توصیفی')), ButtonSegment(value: 'numeric', label: Text('نمره‌ای'))],
          selected: {_scale},
          onSelectionChanged: (s) => setState(() => _scale = s.first),
        ),
        const SizedBox(height: 8),
        ListTile(
          contentPadding: EdgeInsets.zero,
          leading: const Icon(Icons.event_rounded),
          title: Text('تاریخ: ${Fa.weekday(_date)} ${Fa.date(Fa.ymd(_date))}'),
          onTap: () async {
            final d = await pickWeekDay(context, initial: _date, title: 'تاریخ ارزیابی');
            if (d != null) setState(() => _date = d);
          },
        ),
        FilledButton(key: const Key('as_save'), onPressed: _busy ? null : _save, child: busyLabel(_busy, 'ساخت و ثبت نتایج')),
      ]),
    );
  }
}

class AssessmentScreen extends ConsumerStatefulWidget {
  const AssessmentScreen({super.key, required this.id});
  final int id;
  @override
  ConsumerState<AssessmentScreen> createState() => _AssessmentScreenState();
}

class _AssessmentScreenState extends ConsumerState<AssessmentScreen> {
  final Map<int, Map<String, dynamic>> _r = {};
  final Map<int, TextEditingController> _score = {};
  bool _loaded = false;
  bool _busy = false;
  String get _path => '/assessments/${widget.id}';

  @override
  void dispose() {
    for (final c in _score.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    setState(() => _busy = true);
    try {
      final data = <String, dynamic>{};
      for (final e in _r.entries) {
        data['${e.key}'] = {...e.value, if (_score[e.key] != null) 'score': Fa.enDigits(_score[e.key]!.text.trim())};
      }
      await ref.read(apiProvider).post<Object?>('$_path/results', data: {'results': data});
      ref.invalidate(getProvider(_path));
      if (mounted) {
        showMessage(context, 'نتایج ذخیره شد ✅');
        Navigator.pop(context);
      }
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _delete() async {
    if (!await confirmDanger(context, title: 'حذف ارزیابی', text: 'این ارزیابی و همه‌ی نتایجش حذف می‌شود.')) return;
    try {
      await ref.read(apiProvider).delete<Object?>(_path);
      if (mounted) Navigator.pop(context);
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final res = ref.watch(getProvider(_path));
    final d = (res.valueOrNull?.data as Map?) ?? const {};
    final canEdit = d['can_edit'] == true;
    return Scaffold(
      appBar: AppBar(
        title: Text('${d['title'] ?? 'ارزیابی'}'),
        actions: [if (canEdit) IconButton(tooltip: 'حذف', onPressed: _delete, icon: const Icon(Icons.delete_outline_rounded))],
      ),
      bottomNavigationBar: canEdit
          ? SafeArea(child: Padding(padding: const EdgeInsets.fromLTRB(16, 4, 16, 10), child: FilledButton(key: const Key('as_results_save'), onPressed: _busy ? null : _save, child: busyLabel(_busy, 'ذخیره‌ی نتایج'))))
          : null,
      body: res.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(getProvider(_path))),
        data: (r) {
          final students = ((d['students'] as List?) ?? const []).cast<Map>();
          final levels = ((d['levels'] as List?) ?? const []).cast<Map>().where((l) => l['key'] != 'absent').toList();
          final numeric = d['scale'] == 'numeric';
          if (!_loaded) {
            for (final s in students) {
              final id = (s['id'] as num).toInt();
              _r[id] = {'level': '${s['level'] ?? ''}', 'comment': '${s['comment'] ?? ''}'};
              if (numeric) _score[id] = TextEditingController(text: s['score'] == null ? '' : Fa.faDigits('${s['score']}'.replaceAll(RegExp(r'\.0+$'), '')));
            }
            _loaded = true;
          }
          if (students.isEmpty) return const EmptyView(icon: Icons.groups_rounded, title: 'کلاس دانش‌آموز ندارد');
          Color col(String hex) => Color(int.parse('FF${hex.replaceAll('#', '')}', radix: 16));
          return ListView(padding: const EdgeInsets.fromLTRB(10, 8, 10, 20), children: [
            if ('${d['subject'] ?? ''}'.isNotEmpty || '${d['date'] ?? ''}'.isNotEmpty)
              Padding(
                padding: const EdgeInsets.fromLTRB(6, 0, 6, 8),
                child: Text([if ('${d['subject']}'.isNotEmpty) '${d['subject']}', if ('${d['date']}'.length >= 10) Fa.date('${d['date']}')].join(' • '), style: const TextStyle(color: Colors.black54)),
              ),
            for (final s in students)
              Builder(builder: (_) {
                final id = (s['id'] as num).toInt();
                return Card(
                  margin: const EdgeInsets.only(bottom: 6),
                  child: Padding(
                    padding: const EdgeInsets.all(10),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Row(children: [
                        HsAvatar(name: '${s['name']}', url: '${s['avatar'] ?? ''}', size: 36),
                        const SizedBox(width: 10),
                        Expanded(child: Text('${s['name']}', style: const TextStyle(fontWeight: FontWeight.w700))),
                        if (numeric)
                          SizedBox(
                            width: 84,
                            child: TextField(
                              controller: _score[id],
                              enabled: canEdit,
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                              textAlign: TextAlign.center,
                              decoration: const InputDecoration(hintText: 'نمره', isDense: true),
                            ),
                          ),
                      ]),
                      if (!numeric) ...[
                        const SizedBox(height: 6),
                        Wrap(spacing: 6, runSpacing: 6, children: [
                          for (final l in levels)
                            ChoiceChip(
                              label: Text('${l['label']}', style: TextStyle(color: _r[id]!['level'] == l['key'] ? Colors.white : null, fontSize: 12)),
                              selectedColor: col('${l['color']}'),
                              selected: _r[id]!['level'] == l['key'],
                              onSelected: canEdit ? (_) => setState(() => _r[id]!['level'] = _r[id]!['level'] == l['key'] ? '' : l['key']) : null,
                            ),
                        ]),
                      ],
                    ]),
                  ),
                );
              }),
          ]);
        },
      ),
    );
  }
}

/* ============================== بودجه‌بندی ============================== */

class BudgetScreen extends ConsumerStatefulWidget {
  const BudgetScreen({super.key, required this.classId, this.title = ''});
  final int classId;
  final String title;
  @override
  ConsumerState<BudgetScreen> createState() => _BudgetScreenState();
}

class _BudgetScreenState extends ConsumerState<BudgetScreen> {
  List<Map<String, dynamic>> _rows = [];
  bool _loaded = false;
  bool _busy = false;
  String get _path => '/classes/${widget.classId}/budget';

  Future<void> _save() async {
    setState(() => _busy = true);
    try {
      await ref.read(apiProvider).post<Object?>(_path, data: {'rows': _rows});
      ref.invalidate(getProvider(_path));
      ref.invalidate(getProvider('/dashboard'));
      _loaded = false;
      if (mounted) showMessage(context, 'بودجه‌بندی ذخیره شد ✅');
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _editRow([int? i]) async {
    final row = i == null ? <String, dynamic>{'subject': '', 'planned': 20, 'done': 0} : Map<String, dynamic>.from(_rows[i]);
    final sub = TextEditingController(text: '${row['subject']}');
    final pl = TextEditingController(text: Fa.faDigits(row['planned']));
    final dn = TextEditingController(text: Fa.faDigits(row['done']));
    final subjects = (((ref.read(getProvider(_path)).valueOrNull?.data as Map?)?['subjects'] as List?) ?? const []).map((e) => '$e').toList();
    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (c) => Padding(
        padding: EdgeInsets.fromLTRB(20, 0, 20, 20 + MediaQuery.of(c).viewInsets.bottom),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Text(i == null ? 'درس تازه' : 'ویرایش ${row['subject']}', style: Theme.of(c).textTheme.titleLarge),
          const SizedBox(height: 10),
          TextField(controller: sub, decoration: const InputDecoration(labelText: 'درس')),
          if (i == null)
            Wrap(spacing: 6, children: [for (final s in subjects) ActionChip(label: Text(s), onPressed: () => sub.text = s)]),
          const SizedBox(height: 10),
          Row(children: [
            Expanded(child: TextField(controller: pl, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'کل جلسات/درس‌ها'))),
            const SizedBox(width: 10),
            Expanded(child: TextField(controller: dn, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'تدریس‌شده'))),
          ]),
          const SizedBox(height: 14),
          Row(children: [
            if (i != null) TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('حذف این درس', style: TextStyle(color: HsColors.red))),
            const Spacer(),
            FilledButton(style: FilledButton.styleFrom(minimumSize: const Size(110, 46)), onPressed: () => Navigator.pop(c, true), child: const Text('تأیید')),
          ]),
        ]),
      ),
    );
    if (ok == null) return;
    setState(() {
      if (ok == false && i != null) {
        _rows.removeAt(i);
      } else if (ok == true && sub.text.trim().isNotEmpty) {
        final n = {'subject': sub.text.trim(), 'planned': int.tryParse(Fa.enDigits(pl.text.trim())) ?? 0, 'done': int.tryParse(Fa.enDigits(dn.text.trim())) ?? 0};
        if (i == null) {
          _rows.add(n);
        } else {
          _rows[i] = n;
        }
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final res = ref.watch(getProvider(_path));
    final d = (res.valueOrNull?.data as Map?) ?? const {};
    final canEdit = d['can_edit'] == true;
    final expected = (d['expected'] as num?)?.toInt() ?? 0;
    if (res.hasValue && !_loaded) {
      _rows = ((d['rows'] as List?) ?? const []).cast<Map>().map((m) => {'subject': m['subject'], 'planned': m['planned'], 'done': m['done']}).toList();
      _loaded = true;
    }
    return Scaffold(
      appBar: AppBar(title: Text(widget.title.isEmpty ? 'بودجه‌بندی' : 'بودجه‌بندی — ${widget.title}')),
      floatingActionButton: canEdit ? FloatingActionButton(backgroundColor: HsColors.orange, foregroundColor: Colors.white, onPressed: () => _editRow(), child: const Icon(Icons.add_rounded)) : null,
      bottomNavigationBar: canEdit && _rows.isNotEmpty
          ? SafeArea(child: Padding(padding: const EdgeInsets.fromLTRB(16, 4, 16, 10), child: FilledButton(onPressed: _busy ? null : _save, child: busyLabel(_busy, 'ذخیره'))))
          : null,
      body: res.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(getProvider(_path))),
        data: (_) => ListView(padding: const EdgeInsets.fromLTRB(14, 10, 14, 96), children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(color: const Color(0xFFEDE7FF), borderRadius: BorderRadius.circular(14)),
            child: Text('تا امروز حدود ${Fa.faDigits(expected)}٪ سال تحصیلی گذشته است. پیشرفت هر درس با همین مقایسه می‌شود.', style: const TextStyle(color: Color(0xFF5B3CC4), height: 1.7)),
          ),
          const SizedBox(height: 10),
          if (_rows.isEmpty) const EmptyView(icon: Icons.straighten_rounded, title: 'درسی ثبت نشده', text: 'با دکمه‌ی + درس‌ها و تعداد جلسات هر کدام را وارد کنید.'),
          for (final (i, x) in _rows.indexed) BudgetRow(row: x, expected: expected, onTap: canEdit ? () => _editRow(i) : null),
        ]),
      ),
    );
  }
}

class BudgetRow extends StatelessWidget {
  const BudgetRow({super.key, required this.row, required this.expected, this.onTap});
  final Map row;
  final int expected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final planned = (row['planned'] as num?)?.toInt() ?? 0;
    final done = (row['done'] as num?)?.toInt() ?? 0;
    final pct = planned == 0 ? 0 : (done * 100 / planned).round();
    final diff = pct - expected;
    final (label, color) = diff >= 5 ? ('جلوتر از برنامه', HsColors.green) : (diff <= -10 ? ('عقب‌تر از برنامه', HsColors.red) : ('مطابق برنامه', HsColors.blue));
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Row(children: [
              Expanded(child: Text('${row['subject']}', style: const TextStyle(fontWeight: FontWeight.w700))),
              Pill(label, color: color),
            ]),
            const SizedBox(height: 8),
            Stack(children: [
              ClipRRect(borderRadius: BorderRadius.circular(8), child: LinearProgressIndicator(value: pct / 100, minHeight: 10, color: color, backgroundColor: const Color(0x14000000))),
              Positioned.fill(
                child: Align(
                  alignment: AlignmentDirectional(-1 + 2 * (expected.clamp(0, 100) / 100), 0),
                  child: Container(width: 2, height: 14, color: HsColors.navy),
                ),
              ),
            ]),
            const SizedBox(height: 4),
            Text('${Fa.faDigits(done)} از ${Fa.faDigits(planned)} (${Fa.faDigits(pct)}٪)', style: const TextStyle(fontSize: 12, color: Colors.black54)),
          ]),
        ),
      ),
    );
  }
}

/* ============================== تقویم ============================== */

/// تقویم آموزشی و مناسبتی. [childId] برای والد (تقویم فرزند)
class CalendarScreen extends ConsumerWidget {
  const CalendarScreen({super.key, this.childId});
  final int? childId;

  String get _path => childId == null ? '/calendar' : '/children/$childId/calendar';

  static const _typeIcons = {
    'academic': ('🎓', HsColors.blue),
    'cultural': ('🌸', Color(0xFFD9468F)),
    'holiday': ('🔴', HsColors.red),
    'exam': ('📝', HsColors.orangeDark),
    'meeting': ('🤝', HsColors.navy),
    'ceremony': ('🎉', HsColors.green),
    'deadline': ('⏰', HsColors.orange),
  };

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final res = ref.watch(getProvider(_path));
    final d = (res.valueOrNull?.data as Map?) ?? const {};
    final canAdd = d['can_add_school'] == true || d['can_add_class'] == true;
    return Scaffold(
      appBar: AppBar(title: const Text('تقویم آموزشی و مناسبتی')),
      floatingActionButton: canAdd
          ? FloatingActionButton.extended(
              key: const Key('cal_add'),
              backgroundColor: HsColors.orange,
              foregroundColor: Colors.white,
              onPressed: () async {
                final ok = await showModalBottomSheet<bool>(context: context, isScrollControlled: true, showDragHandle: true, builder: (_) => _NewEventSheet(info: d));
                if (ok == true) {
                  ref.invalidate(getProvider(_path));
                  ref.invalidate(getProvider('/dashboard'));
                }
              },
              icon: const Icon(Icons.add_rounded),
              label: const Text('رویداد'),
            )
          : null,
      body: RefreshIndicator(
        onRefresh: () async => ref.invalidate(getProvider(_path)),
        child: res.when(
          loading: () => const LoadingView(),
          error: (e, _) => ListView(children: [const SizedBox(height: 80), ErrorView(error: e, onRetry: () => ref.invalidate(getProvider(_path)))]),
          data: (r) {
            final events = ((d['events'] as List?) ?? const []).cast<Map>();
            if (events.isEmpty) {
              return ListView(children: const [SizedBox(height: 60), EmptyView(icon: Icons.calendar_month_rounded, title: 'رویدادی در دو ماه آینده نیست')]);
            }
            final byDay = <String, List<Map>>{};
            for (final e in events) {
              byDay.putIfAbsent('${e['date']}', () => []).add(e);
            }
            return ListView(padding: const EdgeInsets.fromLTRB(12, 8, 12, 96), children: [
              for (final day in byDay.keys) ...[
                Padding(
                  padding: const EdgeInsets.fromLTRB(4, 10, 4, 4),
                  child: Text(
                    '${Fa.weekday(DateTime.parse(day))} ${Fa.date(day)}',
                    style: TextStyle(fontWeight: FontWeight.w700, color: Week.isHoliday(DateTime.parse(day)) ? HsColors.red : HsColors.navy),
                  ),
                ),
                for (final e in byDay[day]!) EventTile(e: e, onDeleted: () => ref.invalidate(getProvider(_path))),
              ],
            ]);
          },
        ),
      ),
    );
  }
}

class EventTile extends ConsumerWidget {
  const EventTile({super.key, required this.e, this.onDeleted});
  final Map e;
  final VoidCallback? onDeleted;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = CalendarScreen._typeIcons['${e['type']}'];
    final icon = '${e['icon'] ?? ''}'.isNotEmpty ? '${e['icon']}' : (t?.$1 ?? '📌');
    final sub = '${e['sub'] ?? ''}'.isNotEmpty
        ? '${e['sub']}'
        : [
            '${e['type_label'] ?? ''}',
            if ('${e['level_label'] ?? ''}'.isNotEmpty) '${e['level_label']}',
            if ('${e['class'] ?? ''}'.isNotEmpty) '${e['class']}',
            if ('${e['end_date'] ?? ''}'.length >= 10) 'تا ${Fa.date('${e['end_date']}', withYear: false)}',
          ].where((x) => x.isNotEmpty).join(' • ');
    return Card(
      margin: const EdgeInsets.only(bottom: 6),
      child: ListTile(
        leading: Text(icon, style: const TextStyle(fontSize: 24)),
        title: Text('${e['title']}', style: const TextStyle(fontWeight: FontWeight.w700)),
        subtitle: sub.isEmpty ? null : Text(sub),
        trailing: e['can_delete'] == true
            ? IconButton(
                icon: const Icon(Icons.delete_outline_rounded),
                onPressed: () async {
                  if (!await confirmDanger(context, title: 'حذف رویداد', text: '«${e['title']}» از تقویم حذف می‌شود.')) return;
                  try {
                    await ref.read(apiProvider).delete<Object?>('/calendar/${e['id']}');
                    onDeleted?.call();
                  } on ApiException catch (x) {
                    if (context.mounted) showMessage(context, x.message, error: true);
                  }
                },
              )
            : null,
      ),
    );
  }
}

class _NewEventSheet extends ConsumerStatefulWidget {
  const _NewEventSheet({required this.info});
  final Map info;
  @override
  ConsumerState<_NewEventSheet> createState() => _NewEventSheetState();
}

class _NewEventSheetState extends ConsumerState<_NewEventSheet> {
  final _t = TextEditingController();
  final _d = TextEditingController();
  DateTime? _date;
  DateTime? _end;
  late String _scope = widget.info['can_add_school'] == true ? 'school' : 'class';
  int? _class;
  String _type = 'occasion';
  String _level = '';
  String _aud = 'all';
  bool _busy = false;

  Future<void> _save() async {
    if (_t.text.trim().isEmpty || _date == null) {
      showMessage(context, 'عنوان و تاریخ را وارد کنید.', error: true);
      return;
    }
    if (_scope == 'class' && _class == null) {
      showMessage(context, 'کلاس را انتخاب کنید.', error: true);
      return;
    }
    setState(() => _busy = true);
    try {
      await ref.read(apiProvider).post<Object?>('/calendar', data: {
        'title': _t.text.trim(),
        'description': _d.text.trim(),
        'date': Fa.ymd(_date!),
        'end_date': _end == null ? '' : Fa.ymd(_end!),
        'type': _type,
        if (_scope == 'class') 'class_id': _class,
        if (_scope == 'school') ...{'level': _level, 'audience': _aud},
      });
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final types = ((widget.info['types'] as Map?) ?? const {}).map((k, v) => MapEntry('$k', '$v'));
    final levels = ((widget.info['levels'] as Map?) ?? const {}).map((k, v) => MapEntry('$k', '$v'));
    final classes = ((widget.info['classes'] as List?) ?? const []).cast<Map>();
    _class ??= classes.length == 1 ? (classes.first['id'] as num).toInt() : null;
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 0, 20, 20 + MediaQuery.of(context).viewInsets.bottom),
      child: SingleChildScrollView(
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Text('رویداد تازه', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 10),
          if (widget.info['can_add_school'] == true && classes.isNotEmpty)
            SegmentedButton<String>(
              segments: const [ButtonSegment(value: 'school', label: Text('کل مدرسه')), ButtonSegment(value: 'class', label: Text('یک کلاس'))],
              selected: {_scope},
              onSelectionChanged: (s) => setState(() => _scope = s.first),
            ),
          const SizedBox(height: 10),
          TextField(key: const Key('ev_title'), controller: _t, decoration: const InputDecoration(labelText: 'عنوان *', hintText: 'مثلاً امتحانات نوبت اول')),
          const SizedBox(height: 10),
          DropdownButtonFormField<String>(
            initialValue: types.containsKey(_type) ? _type : null,
            isExpanded: true,
            decoration: const InputDecoration(labelText: 'نوع'),
            items: [for (final e in types.entries) DropdownMenuItem(value: e.key, child: Text(e.value))],
            onChanged: (v) => setState(() => _type = v ?? _type),
          ),
          const SizedBox(height: 10),
          if (_scope == 'class')
            DropdownButtonFormField<int>(
              initialValue: _class,
              isExpanded: true,
              decoration: const InputDecoration(labelText: 'کلاس'),
              items: [for (final c in classes) DropdownMenuItem(value: (c['id'] as num).toInt(), child: Text('${c['title']}'))],
              onChanged: (v) => setState(() => _class = v),
            )
          else ...[
            DropdownButtonFormField<String>(
              initialValue: _level,
              isExpanded: true,
              decoration: const InputDecoration(labelText: 'مقطع'),
              items: [const DropdownMenuItem(value: '', child: Text('همه‌ی مقطع‌ها')), for (final e in levels.entries) DropdownMenuItem(value: e.key, child: Text(e.value))],
              onChanged: (v) => setState(() => _level = v ?? ''),
            ),
            const SizedBox(height: 10),
            DropdownButtonFormField<String>(
              initialValue: _aud,
              isExpanded: true,
              decoration: const InputDecoration(labelText: 'چه کسانی ببینند'),
              items: const [
                DropdownMenuItem(value: 'all', child: Text('همه (کادر، والدین، دانش‌آموزان)')),
                DropdownMenuItem(value: 'parents', child: Text('والدین')),
                DropdownMenuItem(value: 'teachers', child: Text('فقط کادر و معلمان')),
              ],
              onChanged: (v) => setState(() => _aud = v ?? 'all'),
            ),
          ],
          const SizedBox(height: 6),
          Row(children: [
            Expanded(
              child: OutlinedButton.icon(
                key: const Key('ev_date'),
                icon: const Icon(Icons.event_rounded),
                label: Text(_date == null ? 'تاریخ *' : '${Fa.weekday(_date!)} ${Fa.date(Fa.ymd(_date!), withYear: false)}'),
                onPressed: () async {
                  final d = await pickWeekDay(context, initial: _date, allowHolidays: true, weeks: 16, title: 'تاریخ رویداد');
                  if (d != null) setState(() => _date = d);
                },
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: OutlinedButton.icon(
                icon: const Icon(Icons.event_available_rounded),
                label: Text(_end == null ? 'تا (اختیاری)' : 'تا ${Fa.date(Fa.ymd(_end!), withYear: false)}'),
                onPressed: () async {
                  final d = await pickWeekDay(context, initial: _end ?? _date, start: _date, allowHolidays: true, weeks: 16, title: 'روز پایان');
                  setState(() => _end = d);
                },
              ),
            ),
          ]),
          const SizedBox(height: 10),
          TextField(controller: _d, minLines: 2, maxLines: 4, decoration: const InputDecoration(labelText: 'توضیح')),
          const SizedBox(height: 14),
          FilledButton(key: const Key('ev_save'), onPressed: _busy ? null : _save, child: busyLabel(_busy, 'ثبت در تقویم')),
        ]),
      ),
    );
  }
}

/* ============================== کتابخانه‌ی کاربرگ ============================== */

class WorksheetsScreen extends ConsumerStatefulWidget {
  const WorksheetsScreen({super.key});
  @override
  ConsumerState<WorksheetsScreen> createState() => _WorksheetsScreenState();
}

class _WorksheetsScreenState extends ConsumerState<WorksheetsScreen> {
  String _grade = '';
  String _subject = '';
  String _q = '';
  final _s = TextEditingController();

  String get _path {
    final p = <String>[if (_grade.isNotEmpty) 'grade=$_grade', if (_subject.isNotEmpty) 'subject=$_subject', if (_q.isNotEmpty) 's=${Uri.encodeQueryComponent(_q)}'];
    return p.isEmpty ? '/worksheets' : '/worksheets?${p.join('&')}';
  }

  Future<void> _assign(Map w, List<Map> classes) async {
    int? cid = classes.length == 1 ? (classes.first['id'] as num).toInt() : null;
    DateTime? due = Week.addSchoolDays(DateTime.now(), 1);
    final note = TextEditingController();
    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (c) => StatefulBuilder(
        builder: (c, set) => Padding(
          padding: EdgeInsets.fromLTRB(20, 0, 20, 20 + MediaQuery.of(c).viewInsets.bottom),
          child: SingleChildScrollView(
            child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Text('«${w['title']}» به‌عنوان تکلیف', style: Theme.of(c).textTheme.titleMedium),
              const SizedBox(height: 10),
              DropdownButtonFormField<int>(
                initialValue: cid,
                isExpanded: true,
                decoration: const InputDecoration(labelText: 'کلاس'),
                items: [for (final x in classes) DropdownMenuItem(value: (x['id'] as num).toInt(), child: Text('${x['title']}'))],
                onChanged: (v) => set(() => cid = v),
              ),
              const SizedBox(height: 10),
              TextField(controller: note, decoration: const InputDecoration(labelText: 'یادداشت برای بچه‌ها (اختیاری)')),
              const SizedBox(height: 10),
              Text('مهلت: ${due == null ? 'بدون مهلت' : '${Fa.weekday(due!)} ${Fa.date(Fa.ymd(due!), withYear: false)}'}', style: const TextStyle(fontWeight: FontWeight.w700)),
              const SizedBox(height: 6),
              WeekDayPicker(selected: due, weeks: 2, onPick: (d) => set(() => due = d)),
              const SizedBox(height: 12),
              FilledButton(onPressed: cid == null ? null : () => Navigator.pop(c, true), child: const Text('ثبت تکلیف')),
            ]),
          ),
        ),
      ),
    );
    if (ok != true || cid == null) return;
    try {
      final r = await ref.read(apiProvider).post<Map<String, dynamic>>('/worksheets/${w['id']}/assign', data: {'class_id': cid, 'note': note.text.trim(), 'due_date': due == null ? '' : Fa.ymd(due!)});
      ref.invalidate(getProvider('/classes/$cid/homework'));
      if (mounted) showMessage(context, '${r['message']}');
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final res = ref.watch(getProvider(_path));
    final d = (res.valueOrNull?.data as Map?) ?? const {};
    final grades = ((d['grades'] as Map?) ?? const {}).map((k, v) => MapEntry('$k', '$v'));
    final subjects = ((d['subjects'] as Map?) ?? const {}).map((k, v) => MapEntry('$k', '$v'));
    final classes = ((d['classes'] as List?) ?? const []).cast<Map>();
    return Scaffold(
      appBar: AppBar(title: const Text('کتابخانه کاربرگ')),
      body: Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 6, 12, 0),
          child: TextField(
            controller: _s,
            textInputAction: TextInputAction.search,
            decoration: InputDecoration(hintText: 'جستجو…', prefixIcon: const Icon(Icons.search_rounded), isDense: true, suffixIcon: _q.isEmpty ? null : IconButton(icon: const Icon(Icons.close_rounded), onPressed: () => setState(() => _q = _s.text = ''))),
            onSubmitted: (v) => setState(() => _q = v.trim()),
          ),
        ),
        SizedBox(
          height: 48,
          child: ListView(scrollDirection: Axis.horizontal, padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6), children: [
            ChoiceChip(label: const Text('همه‌ی پایه‌ها'), selected: _grade.isEmpty, onSelected: (_) => setState(() => _grade = '')),
            for (final e in grades.entries) Padding(padding: const EdgeInsetsDirectional.only(start: 6), child: ChoiceChip(label: Text(e.value), selected: _grade == e.key, onSelected: (_) => setState(() => _grade = e.key))),
          ]),
        ),
        SizedBox(
          height: 48,
          child: ListView(scrollDirection: Axis.horizontal, padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6), children: [
            ChoiceChip(label: const Text('همه‌ی درس‌ها'), selected: _subject.isEmpty, onSelected: (_) => setState(() => _subject = '')),
            for (final e in subjects.entries) Padding(padding: const EdgeInsetsDirectional.only(start: 6), child: ChoiceChip(label: Text(e.value), selected: _subject == e.key, onSelected: (_) => setState(() => _subject = e.key))),
          ]),
        ),
        Expanded(
          child: res.when(
            loading: () => const LoadingView(),
            error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(getProvider(_path))),
            data: (r) {
              final items = ((d['items'] as List?) ?? const []).cast<Map>();
              if (items.isEmpty) return const EmptyView(icon: Icons.collections_bookmark_rounded, title: 'کاربرگی پیدا نشد');
              return ListView(padding: const EdgeInsets.fromLTRB(12, 6, 12, 24), children: [
                for (final w in items)
                  Card(
                    margin: const EdgeInsets.only(bottom: 8),
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                        Text('${w['title']}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                        const SizedBox(height: 4),
                        Wrap(spacing: 6, runSpacing: 4, children: [
                          if ('${w['grade']}'.isNotEmpty) Pill('${w['grade']}', color: HsColors.blue),
                          if ('${w['subject']}'.isNotEmpty) Pill('${w['subject']}', color: HsColors.orangeDark),
                          if ('${w['difficulty']}'.isNotEmpty) Pill('${w['difficulty']}', color: HsColors.green),
                        ]),
                        if ('${w['topic']}'.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 4), child: Text('${w['topic']}', style: const TextStyle(color: Colors.black54))),
                        const SizedBox(height: 6),
                        Row(children: [
                          TextButton.icon(
                            onPressed: () => openUrl(context, '${w['pdf']}'.isNotEmpty ? '${w['pdf']}' : '${w['url']}'),
                            icon: const Icon(Icons.visibility_rounded, size: 18),
                            label: const Text('دیدن'),
                          ),
                          const Spacer(),
                          FilledButton.tonalIcon(
                            style: FilledButton.styleFrom(minimumSize: const Size(0, 40)),
                            onPressed: classes.isEmpty ? null : () => _assign(w, classes),
                            icon: const Icon(Icons.assignment_add, size: 18),
                            label: const Text('تکلیف کلاس'),
                          ),
                        ]),
                      ]),
                    ),
                  ),
              ]);
            },
          ),
        ),
      ]),
    );
  }
}
