import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shamsi_date/shamsi_date.dart';

import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../core/utils/fa.dart';
import '../../core/widgets/states.dart';
import '../../core/widgets/ui.dart';

/// گزارش پیشرفت ماهانه‌ی فرزند (مثل پنل والدین سایت)
class ChildProgressScreen extends ConsumerStatefulWidget {
  const ChildProgressScreen({super.key, required this.childId, this.name = ''});
  final int childId;
  final String name;
  @override
  ConsumerState<ChildProgressScreen> createState() => _ChildProgressScreenState();
}

class _ChildProgressScreenState extends ConsumerState<ChildProgressScreen> {
  int _back = 0; // چند ماه قبل

  /// ماه میلادی‌ای که بیشترِ ماه شمسیِ انتخاب‌شده در آن است (سایت با ماه میلادی کار می‌کند)
  (int, int, String) get _month {
    final j = Jalali.now();
    var y = j.year, m = j.month - _back;
    while (m < 1) {
      m += 12;
      y--;
    }
    final mid = Jalali(y, m, 15).toDateTime();
    return (mid.year, mid.month, '${Jalali(y, m, 1).formatter.mN} ${Fa.faDigits(y)}');
  }

  @override
  Widget build(BuildContext context) {
    final (gy, gm, label) = _month;
    final path = '/children/${widget.childId}/progress?y=$gy&m=$gm';
    final res = ref.watch(getProvider(path));
    return Scaffold(
      appBar: AppBar(title: Text(widget.name.isEmpty ? 'گزارش پیشرفت' : 'گزارش پیشرفت ${widget.name}')),
      body: Column(children: [
        Row(mainAxisAlignment: MainAxisAlignment.center, children: [
          IconButton(icon: const Icon(Icons.chevron_right_rounded), onPressed: _back >= 6 ? null : () => setState(() => _back++)),
          Text(label, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
          IconButton(icon: const Icon(Icons.chevron_left_rounded), onPressed: _back == 0 ? null : () => setState(() => _back--)),
        ]),
        Expanded(
          child: res.when(
            loading: () => const LoadingView(),
            error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(getProvider(path))),
            data: (r) {
              final classes = ((((r.data as Map?)?['classes']) as List?) ?? const []).cast<Map>();
              if (classes.isEmpty) return const EmptyView(icon: Icons.insights_rounded, title: 'گزارشی نیست');
              return ListView(padding: const EdgeInsets.fromLTRB(14, 0, 14, 24), children: [
                for (final c in classes) ...[
                  Text('${c['class']}', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 6),
                  if (c['attendance'] is Map) _att(c['attendance'] as Map),
                  if (c['points_month'] != null)
                    SectionCard(
                      title: 'امتیاز رفتاری',
                      icon: Icons.stars_rounded,
                      child: Text('این ماه ${Fa.faDigits(c['points_month'])} امتیاز • مجموع ${Fa.faDigits(c['points_total'] ?? 0)}', style: const TextStyle(height: 1.8)),
                    ),
                  if (c['homework'] is Map) ...[
                    const SizedBox(height: 8),
                    SectionCard(
                      title: 'تکالیف',
                      icon: Icons.menu_book_rounded,
                      child: Text('${Fa.faDigits(c['homework']['done'])} از ${Fa.faDigits(c['homework']['total'])} تکلیف این ماه انجام شده', style: const TextStyle(height: 1.8)),
                    ),
                  ],
                  if (((c['assessments'] as List?) ?? const []).isNotEmpty) ...[
                    const SizedBox(height: 8),
                    SectionCard(
                      title: 'ارزیابی‌ها',
                      icon: Icons.grading_rounded,
                      child: Column(children: [
                        for (final a in (c['assessments'] as List).cast<Map>())
                          ListTile(
                            dense: true,
                            contentPadding: EdgeInsets.zero,
                            title: Text('${a['title']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                            subtitle: Text([if ('${a['subject']}'.isNotEmpty) '${a['subject']}', if ('${a['comment']}'.isNotEmpty) '«${a['comment']}»'].join(' • ')),
                            trailing: Pill('${a['value']}', color: _hex('${a['color']}')),
                          ),
                      ]),
                    ),
                  ],
                  if (c['practice'] != null) ...[
                    const SizedBox(height: 8),
                    SectionCard(title: 'تمرین در خانه', icon: Icons.home_work_rounded, child: Text('${Fa.faDigits(c['practice'])} تمرین در این ماه', style: const TextStyle(height: 1.8))),
                  ],
                  const SizedBox(height: 16),
                ],
              ]);
            },
          ),
        ),
      ]),
    );
  }

  static Color _hex(String h) {
    final v = int.tryParse(h.replaceAll('#', ''), radix: 16);
    return v == null ? HsColors.navy : Color(0xFF000000 | v);
  }

  Widget _att(Map a) {
    final rate = a['rate'];
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: SectionCard(
        title: 'حضور و غیاب',
        icon: Icons.fact_check_rounded,
        trailing: rate == null ? null : Pill('${Fa.faDigits(rate)}٪ حضور', color: (rate as num) >= 90 ? HsColors.green : HsColors.orangeDark),
        child: Wrap(spacing: 8, runSpacing: 6, children: [
          Pill('حاضر ${Fa.faDigits(a['present'] ?? 0)}', color: HsColors.green),
          Pill('غایب ${Fa.faDigits(a['absent'] ?? 0)}', color: HsColors.red),
          Pill('تأخیر ${Fa.faDigits(a['late'] ?? 0)}', color: HsColors.amber),
          Pill('موجه ${Fa.faDigits(a['excused'] ?? 0)}', color: HsColors.blue),
        ]),
      ),
    );
  }
}
