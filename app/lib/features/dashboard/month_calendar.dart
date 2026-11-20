import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:shamsi_date/shamsi_date.dart';

import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../core/utils/fa.dart';
import '../../core/widgets/ui.dart';

/// تقویم ماهانه‌ی شمسی برای داشبورد: علامت رنگی روی روزهای دارای رویداد، مهلت تکلیف یا ارزیابی؛
/// پنجشنبه و جمعه قرمز؛ زدن روی هر روز ← کارهای همان روز.
class MonthCalendarCard extends ConsumerStatefulWidget {
  const MonthCalendarCard({super.key, this.childId});
  final int? childId;
  @override
  ConsumerState<MonthCalendarCard> createState() => _MonthCalendarCardState();
}

class _MonthCalendarCardState extends ConsumerState<MonthCalendarCard> {
  late Jalali _month = Jalali.now().withDay(1);

  static const kinds = {
    'event': ('رویداد', HsColors.blue),
    'holiday': ('تعطیل', HsColors.red),
    'hw': ('مهلت تکلیف', HsColors.orange),
    'assess': ('ارزیابی', Color(0xFFD9468F)),
    'meet': ('جلسه', HsColors.green),
  };

  String get _path {
    final from = Fa.ymd(_month.toDateTime());
    final to = Fa.ymd(_month.withDay(_month.monthLength).toDateTime());
    return widget.childId != null ? '/children/${widget.childId}/calendar?from=$from&to=$to' : '/calendar?from=$from&to=$to&tasks=1';
  }

  /// همه‌ی موارد ماه: [{date, kind, title, sub}]
  List<Map<String, String>> _items(Map d) {
    final out = <Map<String, String>>[];
    for (final e in ((d['events'] as List?) ?? const []).cast<Map>()) {
      final kind = widget.childId != null ? '${e['kind'] ?? 'event'}' : (e['holiday'] == true ? 'holiday' : 'event');
      out.add({'date': '${e['date']}', 'kind': kind, 'title': '${e['title']}', 'sub': '${e['sub'] ?? e['type_label'] ?? ''}', 'icon': '${e['icon'] ?? ''}'});
    }
    for (final t in ((d['tasks'] as List?) ?? const []).cast<Map>()) {
      out.add({'date': '${t['date']}', 'kind': '${t['kind']}', 'title': '${t['title']}', 'sub': '${t['class'] ?? ''}', 'class_id': '${t['class_id'] ?? ''}'});
    }
    return out;
  }

  Future<void> _showDay(DateTime day, List<Map<String, String>> list) async {
    await showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (c) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Text('${Fa.weekday(day)} ${Fa.date(Fa.ymd(day))}', style: Theme.of(c).textTheme.titleMedium),
            const SizedBox(height: 8),
            if (list.isEmpty) Text('برای این روز چیزی ثبت نشده است.', style: TextStyle(color: HsColors.faint)),
            for (final it in list)
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: CircleAvatar(radius: 6, backgroundColor: (kinds[it['kind']] ?? kinds['event']!).$2),
                title: Text('${it['title']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                subtitle: Text([(kinds[it['kind']] ?? kinds['event']!).$1, if ('${it['sub']}'.isNotEmpty) '${it['sub']}'].join(' • ')),
                onTap: (it['class_id'] ?? '').isEmpty
                    ? null
                    : () {
                        Navigator.pop(c);
                        context.push(it['kind'] == 'assess' ? '/assessments/${it['class_id']}' : '/class/${it['class_id']}?tab=2');
                      },
              ),
            const SizedBox(height: 4),
            OutlinedButton(
              onPressed: () {
                Navigator.pop(c);
                context.push(widget.childId != null ? '/calendar?child=${widget.childId}' : '/calendar');
              },
              child: const Text('تقویم کامل'),
            ),
          ]),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final res = ref.watch(getProvider(_path));
    final items = _items((res.valueOrNull?.data as Map?) ?? const {});
    final byDay = <String, List<Map<String, String>>>{};
    for (final it in items) {
      byDay.putIfAbsent(it['date']!, () => []).add(it);
    }
    final first = _month.toDateTime();
    final lead = (first.weekday + 1) % 7; // شنبه = ۰
    final len = _month.monthLength;
    final today = DateUtils.dateOnly(DateTime.now());
    return SectionCard(
      title: 'تقویم',
      icon: Icons.calendar_month_rounded,
      trailing: Row(mainAxisSize: MainAxisSize.min, children: [
        IconButton(visualDensity: VisualDensity.compact, icon: const Icon(Icons.chevron_right_rounded), onPressed: () => setState(() => _month = _month.addMonths(-1))),
        Text('${_month.formatter.mN} ${Fa.faDigits(_month.year)}', style: const TextStyle(fontWeight: FontWeight.w700)),
        IconButton(visualDensity: VisualDensity.compact, icon: const Icon(Icons.chevron_left_rounded), onPressed: () => setState(() => _month = _month.addMonths(1))),
      ]),
      child: Column(children: [
        Row(children: [
          for (final (i, n) in const ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'].indexed)
            Expanded(child: Center(child: Text(n, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: i >= 5 ? HsColors.red : HsColors.muted)))),
        ]),
        const SizedBox(height: 4),
        GridView.count(
          crossAxisCount: 7,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          childAspectRatio: 1,
          children: [
            for (var i = 0; i < lead; i++) const SizedBox.shrink(),
            for (var day = 1; day <= len; day++)
              Builder(builder: (_) {
                final g = _month.withDay(day).toDateTime();
                final ymd = Fa.ymd(g);
                final list = byDay[ymd] ?? const [];
                final holiday = Week.isHoliday(g) || list.any((x) => x['kind'] == 'holiday');
                final isToday = DateUtils.isSameDay(g, today);
                final dots = list.map((x) => x['kind']).toSet().take(3).toList();
                return InkWell(
                  key: Key('cal_$ymd'),
                  customBorder: const CircleBorder(),
                  onTap: () => _showDay(g, list),
                  child: Container(
                    margin: const EdgeInsets.all(2),
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: isToday ? HsColors.orange : (list.isNotEmpty ? const Color(0xFFFFF1E3) : null),
                    ),
                    child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                      Text(Fa.faDigits(day), style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: isToday ? Colors.white : (holiday ? HsColors.red : HsColors.navy))),
                      if (dots.isNotEmpty)
                        Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                          for (final k in dots) Container(width: 5, height: 5, margin: const EdgeInsets.symmetric(horizontal: 1), decoration: BoxDecoration(shape: BoxShape.circle, color: isToday ? Colors.white : (kinds[k] ?? kinds['event']!).$2)),
                        ]),
                    ]),
                  ),
                );
              }),
          ],
        ),
        const SizedBox(height: 6),
        Wrap(spacing: 10, runSpacing: 2, children: [
          for (final k in ['event', 'hw', 'assess', 'holiday'])
            Row(mainAxisSize: MainAxisSize.min, children: [
              CircleAvatar(radius: 4, backgroundColor: kinds[k]!.$2),
              const SizedBox(width: 4),
              Text(kinds[k]!.$1, style: TextStyle(fontSize: 11, color: HsColors.muted)),
            ]),
        ]),
      ]),
    );
  }
}
