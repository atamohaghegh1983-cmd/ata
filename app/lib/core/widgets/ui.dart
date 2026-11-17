import 'package:flutter/material.dart';

import '../theme.dart';

/// کارت با عنوان کوچک
class SectionCard extends StatelessWidget {
  const SectionCard({super.key, this.title, this.icon, this.trailing, required this.child, this.padding = const EdgeInsets.all(16)});
  final String? title;
  final IconData? icon;
  final Widget? trailing;
  final Widget child;
  final EdgeInsets padding;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: padding,
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          if (title != null) ...[
            Row(children: [
              if (icon != null) ...[Icon(icon, size: 20, color: HsColors.orange), const SizedBox(width: 8)],
              Expanded(child: Text(title!, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15, color: HsColors.navy))),
              ?trailing,
            ]),
            const SizedBox(height: 10),
          ],
          child,
        ]),
      ),
    );
  }
}

/// برچسب کوچک رنگی
class Pill extends StatelessWidget {
  const Pill(this.text, {super.key, this.color = HsColors.blue, this.icon});
  final String text;
  final Color color;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        if (icon != null) ...[Icon(icon, size: 15, color: color), const SizedBox(width: 4)],
        Text(text, style: TextStyle(color: color, fontWeight: FontWeight.w700, fontSize: 13)),
      ]),
    );
  }
}

/// وضعیت حضور: رنگ، آیکون، نام
class AttStatus {
  const AttStatus(this.key, this.label, this.color, this.icon);
  final String key;
  final String label;
  final Color color;
  final IconData icon;

  static const present = AttStatus('present', 'حاضر', HsColors.green, Icons.check_rounded);
  static const absent = AttStatus('absent', 'غایب', HsColors.red, Icons.close_rounded);
  static const late = AttStatus('late', 'تأخیر', HsColors.amber, Icons.schedule_rounded);
  static const excused = AttStatus('excused', 'موجه', HsColors.blue, Icons.event_available_rounded);
  static const all = [present, absent, late, excused];

  static AttStatus of(String? k) => all.firstWhere((s) => s.key == k, orElse: () => present);
  AttStatus get next => all[(all.indexOf(this) + 1) % all.length];
}

/// وضعیت تکلیف
class HwStatus {
  const HwStatus(this.key, this.label, this.color);
  final String key;
  final String label;
  final Color color;
  static const pending = HwStatus('pending', 'در انتظار', Color(0xFF94A3B8));
  static const done = HwStatus('done', 'انجام‌شده', HsColors.green);
  static const partial = HwStatus('partial', 'ناقص', HsColors.amber);
  static const missing = HwStatus('missing', 'انجام‌نشده', HsColors.red);
  static const all = [pending, done, partial, missing];
  static HwStatus of(String? k) => all.firstWhere((s) => s.key == k, orElse: () => pending);
  HwStatus get next => all[(all.indexOf(this) + 1) % all.length];
}

/// دکمه‌ی پهن با بار
Widget busyLabel(bool busy, String text) => busy
    ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 3, color: Colors.white))
    : Text(text);
