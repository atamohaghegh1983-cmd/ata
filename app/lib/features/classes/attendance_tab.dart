import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../core/utils/fa.dart';
import '../../core/widgets/hs_avatar.dart';
import '../../core/widgets/states.dart';
import '../../core/widgets/ui.dart';

/// حضور و غیاب سریع: لمس دایره ← حاضر/غایب/تأخیر/موجه
class AttendanceTab extends ConsumerStatefulWidget {
  const AttendanceTab({super.key, required this.classId, required this.canWrite});
  final int classId;
  final bool canWrite;

  @override
  ConsumerState<AttendanceTab> createState() => _AttendanceTabState();
}

class _AttendanceTabState extends ConsumerState<AttendanceTab> with AutomaticKeepAliveClientMixin {
  late DateTime _day;
  late DateTime _weekStart;
  bool _byLast = false;
  bool _busy = false;
  bool _dirty = false;
  final Map<int, String> _status = {};
  String? _loadedFor;

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _day = DateTime(now.year, now.month, now.day);
    if (_day.weekday == DateTime.friday) _day = _day.subtract(const Duration(days: 1));
    _weekStart = Week.saturdayOf(_day);
  }

  String get _ymd => Fa.ymd(_day);
  String get _path => '/classes/${widget.classId}/attendance?date=$_ymd';
  String get _weekPath => '/classes/${widget.classId}/attendance/week?from=${Fa.ymd(_weekStart)}';

  void _pick(DateTime d) {
    if (_dirty) {
      showMessage(context, 'اول حضور این روز را ذخیره کنید.', error: true);
      return;
    }
    setState(() {
      _day = d;
      _loadedFor = null;
    });
  }

  void _moveWeek(int dir) {
    if (_dirty) {
      showMessage(context, 'اول حضور این روز را ذخیره کنید.', error: true);
      return;
    }
    setState(() {
      _weekStart = _weekStart.add(Duration(days: 7 * dir));
      _day = _weekStart;
      _loadedFor = null;
    });
  }

  Future<void> _save(List<Map> rows) async {
    setState(() => _busy = true);
    try {
      final records = [for (final r in rows) {'student_id': r['student_id'], 'status': _status[(r['student_id'] as num).toInt()] ?? 'present'}];
      await ref.read(apiProvider).post<Object?>('/classes/${widget.classId}/attendance', data: {'date': _ymd, 'records': records});
      HapticFeedback.mediumImpact();
      _dirty = false;
      ref.invalidate(getProvider(_path));
      ref.invalidate(getProvider(_weekPath));
      if (mounted) showMessage(context, 'حضور و غیاب ${Fa.date(_ymd, withYear: false)} ذخیره شد ✅');
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final res = ref.watch(getProvider(_path));
    final week = ref.watch(getProvider(_weekPath));
    final recordedDays = {
      for (final d in (((week.valueOrNull?.data as Map?)?['days'] as List?) ?? const []).cast<Map>())
        if (d['recorded'] == true) '${d['date']}',
    };
    final today = Fa.ymd(DateTime.now());
    return Column(children: [
      // نوار روزهای هفته
      Container(
        color: Colors.white,
        padding: const EdgeInsets.fromLTRB(4, 8, 4, 8),
        child: Row(children: [
          IconButton(onPressed: () => _moveWeek(-1), icon: const Icon(Icons.chevron_right_rounded), tooltip: 'هفته‌ی قبل'),
          for (final d in Week.schoolDays(_weekStart))
            Expanded(
              child: GestureDetector(
                onTap: () => _pick(d),
                child: Container(
                  margin: const EdgeInsets.symmetric(horizontal: 2),
                  padding: const EdgeInsets.symmetric(vertical: 6),
                  decoration: BoxDecoration(
                    color: Fa.ymd(d) == _ymd ? HsColors.orange : (Fa.ymd(d) == today ? const Color(0xFFFFE3CC) : Colors.transparent),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Column(children: [
                    Text(Week.shortNames[(d.weekday - DateTime.saturday + 7) % 7], style: TextStyle(fontSize: 12, color: Fa.ymd(d) == _ymd ? Colors.white : Colors.black54)),
                    Text(Fa.date(Fa.ymd(d), withYear: false).split(' ').first, style: TextStyle(fontWeight: FontWeight.w700, color: Fa.ymd(d) == _ymd ? Colors.white : HsColors.navy)),
                    Icon(Icons.circle, size: 6, color: recordedDays.contains(Fa.ymd(d)) ? (Fa.ymd(d) == _ymd ? Colors.white : HsColors.green) : Colors.transparent),
                  ]),
                ),
              ),
            ),
          IconButton(onPressed: () => _moveWeek(1), icon: const Icon(Icons.chevron_left_rounded), tooltip: 'هفته‌ی بعد'),
        ]),
      ),
      Expanded(
        child: res.when(
          loading: () => const LoadingView(),
          error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(getProvider(_path))),
          data: (r) {
            final d = (r.data as Map?) ?? const {};
            final rows = ((d['records'] as List?) ?? const []).cast<Map>().toList();
            if (_loadedFor != _ymd) {
              _status
                ..clear()
                ..addAll({for (final x in rows) (x['student_id'] as num).toInt(): '${x['status'] ?? 'present'}'});
              _loadedFor = _ymd;
              _dirty = false;
            }
            String key(Map x) => _byLast ? '${x['last_name'] ?? ''} ${x['first_name'] ?? ''}' : '${x['first_name'] ?? ''} ${x['last_name'] ?? ''}';
            rows.sort((a, b) => key(a).compareTo(key(b)));
            final counts = {for (final s in AttStatus.all) s.key: _status.values.where((v) => v == s.key).length};
            if (rows.isEmpty) return const EmptyView(icon: Icons.groups_rounded, title: 'این کلاس دانش‌آموز ندارد');
            return Column(children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(14, 10, 14, 4),
                child: Row(children: [
                  Expanded(
                    child: Text(
                      '${Fa.weekday(_day)} ${Fa.date(_ymd)}${d['recorded'] == true ? '' : '  •  ثبت‌نشده'}',
                      style: TextStyle(fontWeight: FontWeight.w700, color: d['recorded'] == true ? HsColors.navy : HsColors.orangeDark),
                    ),
                  ),
                  TextButton.icon(
                    onPressed: () => setState(() => _byLast = !_byLast),
                    icon: const Icon(Icons.sort_by_alpha_rounded, size: 18),
                    label: Text(_byLast ? 'نام خانوادگی' : 'نام'),
                  ),
                ]),
              ),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 14),
                child: Wrap(spacing: 6, runSpacing: 6, children: [
                  for (final s in AttStatus.all) Pill('${s.label} ${Fa.faDigits(counts[s.key] ?? 0)}', color: s.color),
                  if (widget.canWrite)
                    ActionChip(
                      label: const Text('همه حاضر'),
                      onPressed: () => setState(() {
                        for (final x in rows) {
                          _status[(x['student_id'] as num).toInt()] = 'present';
                        }
                        _dirty = true;
                      }),
                    ),
                ]),
              ),
              Expanded(
                child: ListView.builder(
                  padding: const EdgeInsets.fromLTRB(10, 6, 10, 90),
                  itemCount: rows.length,
                  itemBuilder: (_, i) {
                    final x = rows[i];
                    final sid = (x['student_id'] as num).toInt();
                    final st = AttStatus.of(_status[sid]);
                    return Card(
                      margin: const EdgeInsets.only(bottom: 6),
                      child: ListTile(
                        leading: HsAvatar(name: '${x['name']}', url: '${x['avatar'] ?? ''}', size: 40),
                        title: Text(_byLast ? '${x['last_name'] ?? ''}، ${x['first_name'] ?? ''}' : '${x['name']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                        subtitle: Text(st.label, style: TextStyle(color: st.color, fontWeight: FontWeight.w700)),
                        trailing: GestureDetector(
                          key: Key('att_$sid'),
                          onTap: widget.canWrite
                              ? () {
                                  HapticFeedback.selectionClick();
                                  setState(() {
                                    _status[sid] = st.next.key;
                                    _dirty = true;
                                  });
                                }
                              : null,
                          child: AnimatedContainer(
                            duration: const Duration(milliseconds: 150),
                            width: 46,
                            height: 46,
                            decoration: BoxDecoration(color: st.color, shape: BoxShape.circle),
                            child: Icon(st.icon, color: Colors.white, size: 26),
                          ),
                        ),
                      ),
                    );
                  },
                ),
              ),
              if (widget.canWrite)
                SafeArea(
                  top: false,
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(16, 4, 16, 10),
                    child: FilledButton.icon(
                      key: const Key('att_save'),
                      onPressed: _busy ? null : () => _save(rows),
                      icon: const Icon(Icons.save_rounded),
                      label: busyLabel(_busy, _dirty || d['recorded'] != true ? 'ذخیره‌ی حضور و غیاب' : 'ذخیره شده ✓ (ذخیره‌ی دوباره)'),
                    ),
                  ),
                ),
            ]);
          },
        ),
      ),
    ]);
  }
}
