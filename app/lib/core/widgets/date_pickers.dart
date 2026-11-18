import 'package:flutter/material.dart';
import 'package:shamsi_date/shamsi_date.dart';

import '../theme.dart';
import '../utils/fa.dart';

/// انتخاب روز به‌شکل تقویم هفتگی مدرسه (شنبه تا جمعه؛ پنجشنبه و جمعه قرمز).
/// [allowHolidays] برای رویدادهای تقویم که در تعطیلی هم ممکن است باشند.
class WeekDayPicker extends StatelessWidget {
  const WeekDayPicker({super.key, required this.selected, required this.onPick, this.weeks = 4, this.allowHolidays = false, this.start});
  final DateTime? selected;
  final ValueChanged<DateTime> onPick;
  final int weeks;
  final bool allowHolidays;
  final DateTime? start;

  static bool same(DateTime? a, DateTime? b) => a != null && b != null && a.year == b.year && a.month == b.month && a.day == b.day;

  @override
  Widget build(BuildContext context) {
    final today = DateUtils.dateOnly(start ?? DateTime.now());
    final sat0 = Week.saturdayOf(today);
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      Row(children: [
        const SizedBox(width: 64),
        for (final n in Week.shortNames)
          Expanded(child: Center(child: Text(n, style: TextStyle(fontSize: 12, color: n == 'پ' || n == 'ج' ? HsColors.red : Colors.black54, fontWeight: FontWeight.w700)))),
      ]),
      const SizedBox(height: 4),
      for (var w = 0; w < weeks; w++)
        Builder(builder: (_) {
          final sat = sat0.add(Duration(days: 7 * w));
          final label = w == 0 ? 'این هفته' : (w == 1 ? 'هفته‌ی بعد' : '${Fa.faDigits(w)} هفته بعد');
          return Padding(
            padding: const EdgeInsets.only(bottom: 4),
            child: Row(children: [
              SizedBox(width: 64, child: Text(label, style: const TextStyle(fontSize: 11, color: Colors.black45))),
              for (final d in Week.allDays(sat))
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.all(2),
                    child: _Day(
                      d: d,
                      past: d.isBefore(today),
                      holiday: Week.isHoliday(d),
                      selected: same(d, selected),
                      enabled: !d.isBefore(today) && (allowHolidays || !Week.isHoliday(d)),
                      onTap: () => onPick(d),
                    ),
                  ),
                ),
            ]),
          );
        }),
    ]);
  }
}

class _Day extends StatelessWidget {
  const _Day({required this.d, required this.past, required this.holiday, required this.selected, required this.enabled, required this.onTap});
  final DateTime d;
  final bool past, holiday, selected, enabled;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final j = Jalali.fromDateTime(d);
    final fg = selected ? Colors.white : (holiday ? HsColors.red : (past ? Colors.black26 : HsColors.navy));
    return Material(
      color: selected ? HsColors.orange : (holiday ? const Color(0x14E53935) : Colors.white),
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        key: Key('day_${Fa.ymd(d)}'),
        borderRadius: BorderRadius.circular(10),
        onTap: enabled ? onTap : null,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 6),
          child: Column(children: [
            Text(Fa.faDigits(j.day), style: TextStyle(color: fg, fontWeight: FontWeight.w700)),
            if (j.day == 1 || selected) Text(j.formatter.mN, style: TextStyle(fontSize: 9, color: fg)),
          ]),
        ),
      ),
    );
  }
}

/// پنجره‌ی انتخاب تاریخ دلخواه (چند هفته‌ی پیش رو) — برمی‌گرداند تاریخ یا null
Future<DateTime?> pickWeekDay(BuildContext context, {DateTime? initial, DateTime? start, bool allowHolidays = false, int weeks = 10, String title = 'انتخاب روز'}) {
  DateTime? sel = initial;
  return showModalBottomSheet<DateTime>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (c) => StatefulBuilder(
      builder: (c, set) => SizedBox(
        height: MediaQuery.of(c).size.height * 0.75,
        child: Column(children: [
          Text(title, style: Theme.of(c).textTheme.titleLarge),
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
              child: WeekDayPicker(selected: sel, weeks: weeks, start: start, allowHolidays: allowHolidays, onPick: (d) => set(() => sel = d)),
            ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 12),
              child: FilledButton(
                onPressed: sel == null ? null : () => Navigator.pop(c, sel),
                child: Text(sel == null ? 'یک روز را بزنید' : 'تأیید ${Fa.weekday(sel!)} ${Fa.date(Fa.ymd(sel!), withYear: false)}'),
              ),
            ),
          ),
        ]),
      ),
    ),
  );
}

/// «دوشنبه‌ی بعد» و… : نزدیک‌ترین همان روز هفته بعد از امروز
DateTime nextWeekday(int weekday, {DateTime? from}) {
  final t = DateUtils.dateOnly(from ?? DateTime.now());
  var add = (weekday - t.weekday + 7) % 7;
  if (add == 0) add = 7;
  return t.add(Duration(days: add));
}
