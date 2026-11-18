import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../core/utils/fa.dart';
import '../../core/widgets/hs_avatar.dart';
import '../../core/widgets/states.dart';
import '../../core/widgets/ui.dart';

/// تکالیف کلاس (معلم: ساخت + علامت‌زدن وضعیت هر دانش‌آموز)
class HomeworkTab extends ConsumerWidget {
  const HomeworkTab({super.key, required this.classId, required this.canWrite});
  final int classId;
  final bool canWrite;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final path = '/classes/$classId/homework';
    final res = ref.watch(getProvider(path));
    return Scaffold(
      backgroundColor: Colors.transparent,
      floatingActionButton: canWrite
          ? FloatingActionButton.extended(
              heroTag: 'add_hw',
              backgroundColor: HsColors.orange,
              foregroundColor: Colors.white,
              onPressed: () async {
                final ok = await showModalBottomSheet<bool>(context: context, isScrollControlled: true, showDragHandle: true, builder: (_) => _NewHomeworkSheet(classId: classId));
                if (ok == true) ref.invalidate(getProvider(path));
              },
              icon: const Icon(Icons.add_rounded),
              label: const Text('تکلیف'),
            )
          : null,
      body: RefreshIndicator(
        color: HsColors.orange,
        onRefresh: () async => ref.invalidate(getProvider(path)),
        child: res.when(
          loading: () => const LoadingView(),
          error: (e, _) => ListView(children: [const SizedBox(height: 80), ErrorView(error: e, onRetry: () => ref.invalidate(getProvider(path)))]),
          data: (r) {
            final list = ((r.data as List?) ?? const []).cast<Map>();
            return ListView(
              padding: const EdgeInsets.fromLTRB(14, 12, 14, 96),
              children: [
                if (r.fromCache) ...[OfflineBanner(savedAt: r.savedAt), const SizedBox(height: 8)],
                if (list.isEmpty) const EmptyView(icon: Icons.menu_book_rounded, title: 'هنوز تکلیفی نیست'),
                for (final h in list)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: InkWell(
                      borderRadius: BorderRadius.circular(20),
                      onTap: canWrite
                          ? () => Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => HomeworkStatusScreen(homeworkId: (h['id'] as num).toInt(), title: '${h['title']}', classId: classId)))
                          : null,
                      child: HomeworkCard(h: h),
                    ),
                  ),
              ],
            );
          },
        ),
      ),
    );
  }
}

class HomeworkCard extends StatelessWidget {
  const HomeworkCard({super.key, required this.h, this.myStatus});
  final Map h;
  final String? myStatus;

  @override
  Widget build(BuildContext context) {
    final sum = h['summary'] is Map ? h['summary'] as Map : null;
    final due = '${h['due_date'] ?? ''}';
    return SectionCard(
      title: '${h['title']}',
      icon: Icons.menu_book_rounded,
      trailing: due.isNotEmpty && due != 'null' ? Pill('تا ${Fa.date(due, withYear: false)}', color: HsColors.blue) : null,
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        if ('${h['subject'] ?? ''}'.isNotEmpty) Text('${h['subject']}', style: const TextStyle(color: HsColors.orangeDark, fontWeight: FontWeight.w700)),
        if ('${h['description'] ?? ''}'.isNotEmpty) Text('${h['description']}', style: const TextStyle(height: 1.8)),
        if (sum != null) ...[
          const SizedBox(height: 8),
          Wrap(spacing: 6, runSpacing: 6, children: [
            for (final s in HwStatus.all)
              if ((sum[s.key] ?? 0) != 0) Pill('${s.label} ${Fa.faDigits(sum[s.key])}', color: s.color),
            const Text('برای علامت‌زدن بزنید', style: TextStyle(color: Colors.black38, fontSize: 12)),
          ]),
        ],
        if (myStatus != null) ...[const SizedBox(height: 8), Pill(HwStatus.of(myStatus).label, color: HwStatus.of(myStatus).color)],
      ]),
    );
  }
}

class _NewHomeworkSheet extends ConsumerStatefulWidget {
  const _NewHomeworkSheet({required this.classId});
  final int classId;
  @override
  ConsumerState<_NewHomeworkSheet> createState() => _NewHomeworkSheetState();
}

class _NewHomeworkSheetState extends ConsumerState<_NewHomeworkSheet> {
  final _t = TextEditingController();
  final _d = TextEditingController();
  final _s = TextEditingController();
  int? _days = 1;
  bool _busy = false;

  @override
  void dispose() {
    _t.dispose();
    _d.dispose();
    _s.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_t.text.trim().isEmpty) {
      showMessage(context, 'عنوان تکلیف را بنویسید.', error: true);
      return;
    }
    setState(() => _busy = true);
    try {
      final due = _days == null ? null : Fa.ymd(_dueDate(_days!));
      await ref.read(apiProvider).post<Object?>('/classes/${widget.classId}/homework', data: {'title': _t.text.trim(), 'description': _d.text.trim(), 'subject': _s.text.trim(), 'due_date': due});
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  /// «یک هفته» = ۷ روز تقویمی؛ بقیه = روز مدرسه‌ی بعدی (پنجشنبه و جمعه رد می‌شود)
  DateTime _dueDate(int days) {
    if (days >= 7) return Week.lastSchoolDay(DateTime.now().add(Duration(days: days)));
    return Week.addSchoolDays(DateTime.now(), days);
  }

  @override
  Widget build(BuildContext context) {
    const choices = <int?, String>{1: 'جلسه‌ی بعد', 2: 'دو روز بعد', 7: 'یک هفته', null: 'بدون موعد'};
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 0, 20, 20 + MediaQuery.of(context).viewInsets.bottom),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Text('تکلیف تازه', style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: 12),
        TextField(controller: _t, autofocus: true, decoration: const InputDecoration(labelText: 'عنوان *', hintText: 'مثلاً تمرین صفحه‌ی ۲۴')),
        const SizedBox(height: 10),
        TextField(controller: _s, decoration: const InputDecoration(labelText: 'درس')),
        const SizedBox(height: 10),
        TextField(controller: _d, minLines: 2, maxLines: 5, decoration: const InputDecoration(labelText: 'توضیح')),
        const SizedBox(height: 10),
        const Text('مهلت', style: TextStyle(fontWeight: FontWeight.w700)),
        Wrap(spacing: 8, children: [
          for (final e in choices.entries)
            ChoiceChip(
              label: Text(e.key == null ? e.value : '${e.value} (${Fa.weekday(_dueDate(e.key!))} ${Fa.date(Fa.ymd(_dueDate(e.key!)), withYear: false)})'),
              selected: _days == e.key,
              onSelected: (_) => setState(() => _days = e.key),
            ),
        ]),
        const SizedBox(height: 14),
        FilledButton(onPressed: _busy ? null : _save, child: busyLabel(_busy, 'ثبت تکلیف')),
      ]),
    );
  }
}

/// علامت‌زدن وضعیت تکلیف هر دانش‌آموز
class HomeworkStatusScreen extends ConsumerStatefulWidget {
  const HomeworkStatusScreen({super.key, required this.homeworkId, required this.title, required this.classId});
  final int homeworkId;
  final String title;
  final int classId;

  @override
  ConsumerState<HomeworkStatusScreen> createState() => _HomeworkStatusScreenState();
}

class _HomeworkStatusScreenState extends ConsumerState<HomeworkStatusScreen> {
  final Map<int, String> _st = {};
  bool _loaded = false;
  bool _busy = false;

  String get _path => '/homework/${widget.homeworkId}/statuses';

  Future<void> _save() async {
    setState(() => _busy = true);
    try {
      await ref.read(apiProvider).post<Object?>(_path, data: {'records': [for (final e in _st.entries) {'student_id': e.key, 'status': e.value}]});
      ref.invalidate(getProvider('/classes/${widget.classId}/homework'));
      if (mounted) {
        showMessage(context, 'ذخیره شد ✅');
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
    final res = ref.watch(getProvider(_path));
    return Scaffold(
      appBar: AppBar(title: Text(widget.title)),
      body: res.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(getProvider(_path))),
        data: (r) {
          final rows = ((r.data as List?) ?? const []).cast<Map>();
          if (!_loaded) {
            for (final x in rows) {
              _st[(x['student_id'] as num).toInt()] = '${x['status'] ?? 'pending'}';
            }
            _loaded = true;
          }
          return Column(children: [
            const Padding(
              padding: EdgeInsets.fromLTRB(16, 10, 16, 4),
              child: Text('روی وضعیت هر دانش‌آموز بزنید تا عوض شود.', style: TextStyle(color: Colors.black54)),
            ),
            Expanded(
              child: ListView(padding: const EdgeInsets.fromLTRB(10, 4, 10, 20), children: [
                for (final x in rows)
                  Card(
                    margin: const EdgeInsets.only(bottom: 6),
                    child: ListTile(
                      leading: HsAvatar(name: '${x['name']}', url: '${x['avatar'] ?? ''}', size: 40),
                      title: Text('${x['name']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                      trailing: Builder(builder: (_) {
                        final sid = (x['student_id'] as num).toInt();
                        final s = HwStatus.of(_st[sid]);
                        return ActionChip(
                          label: Text(s.label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
                          backgroundColor: s.color,
                          side: BorderSide.none,
                          onPressed: () => setState(() => _st[sid] = s.next.key),
                        );
                      }),
                    ),
                  ),
              ]),
            ),
            SafeArea(
              top: false,
              child: Padding(padding: const EdgeInsets.fromLTRB(16, 4, 16, 10), child: FilledButton(onPressed: _busy ? null : _save, child: busyLabel(_busy, 'ذخیره'))),
            ),
          ]);
        },
      ),
    );
  }
}
