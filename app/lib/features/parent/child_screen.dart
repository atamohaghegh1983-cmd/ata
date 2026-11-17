import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../core/utils/fa.dart';
import '../../core/widgets/hs_avatar.dart';
import '../../core/widgets/states.dart';
import '../../core/widgets/ui.dart';
import '../classes/class_screen.dart' show AnnouncementCard;
import '../classes/homework_tab.dart' show HomeworkCard;

/// جزئیات فرزند برای والد: امروز، این هفته، تکالیف، اطلاعیه‌ها، پیام، رمز ورود
class ChildScreen extends ConsumerWidget {
  const ChildScreen({super.key, required this.id, this.name = ''});
  final int id;
  final String name;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final today = ref.watch(getProvider('/children/$id/today'));
    final week = ref.watch(getProvider('/children/$id/week'));
    return Scaffold(
      appBar: AppBar(title: Text(name.isEmpty ? 'فرزند من' : name)),
      body: RefreshIndicator(
        color: HsColors.orange,
        onRefresh: () async {
          ref.invalidate(getProvider('/children/$id/today'));
          ref.invalidate(getProvider('/children/$id/week'));
        },
        child: today.when(
          loading: () => const LoadingView(),
          error: (e, _) => ListView(children: [const SizedBox(height: 80), ErrorView(error: e, onRetry: () => ref.invalidate(getProvider('/children/$id/today')))]),
          data: (r) {
            final d = (r.data as Map?) ?? const {};
            final classes = ((d['classes'] as List?) ?? const []).cast<Map>();
            final weekClasses = (((week.valueOrNull?.data as Map?)?['classes'] as List?) ?? const []).cast<Map>();
            return ListView(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 32),
              children: [
                if (r.fromCache) ...[OfflineBanner(savedAt: r.savedAt), const SizedBox(height: 8)],
                Row(children: [
                  HsAvatar(name: '${d['name'] ?? name}', url: '${d['avatar'] ?? ''}', size: 64),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text('${d['name'] ?? name}', style: Theme.of(context).textTheme.titleLarge),
                      Text('${Fa.weekday(DateTime.now())} ${Fa.date(Fa.ymd(DateTime.now()))}', style: const TextStyle(color: Colors.black54)),
                    ]),
                  ),
                ]),
                const SizedBox(height: 14),
                if (classes.isEmpty) const EmptyView(icon: Icons.class_rounded, title: 'هنوز در کلاسی ثبت نشده'),
                for (final c in classes) ...[
                  _ClassBlock(childId: id, c: c, week: weekClasses.where((w) => w['id'] == c['id']).firstOrNull),
                  const SizedBox(height: 16),
                ],
                _PinCard(childId: id),
              ],
            );
          },
        ),
      ),
    );
  }
}

class _ClassBlock extends ConsumerWidget {
  const _ClassBlock({required this.childId, required this.c, required this.week});
  final int childId;
  final Map c;
  final Map? week;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final locked = ((c['locked'] as List?) ?? const []).map((e) => '$e').toSet();
    final att = c['attendance'] is Map ? AttStatus.of('${(c['attendance'] as Map)['status']}') : null;
    final items = ((c['points_items'] as List?) ?? const []).cast<Map>();
    final hw = ((c['homework_open'] as List?) ?? const []).cast<Map>();
    final anns = ((c['announcements'] as List?) ?? const []).cast<Map>();
    final days = ((week?['days'] as List?) ?? const []).cast<Map>();
    final cid = (c['id'] as num).toInt();
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      Row(children: [
        Expanded(child: Text('${c['title']}', style: Theme.of(context).textTheme.titleLarge)),
        if (c['can_message'] == true)
          FilledButton.tonalIcon(
            key: const Key('msg_teacher'),
            style: FilledButton.styleFrom(minimumSize: const Size(0, 40)),
            onPressed: () => context.push('/messages/${c['teacher_id']}', extra: '${c['teacher']}'),
            icon: const Icon(Icons.chat_bubble_rounded, size: 18),
            label: const Text('پیام به معلم'),
          ),
      ]),
      Text('معلم: ${c['teacher']}', style: const TextStyle(color: Colors.black54)),
      const SizedBox(height: 10),
      SectionCard(
        title: 'امروز',
        icon: Icons.today_rounded,
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          if (!locked.contains('attendance'))
            Row(children: [
              const Text('حضور: '),
              Pill(att?.label ?? 'هنوز ثبت نشده', color: att?.color ?? Colors.grey, icon: att?.icon),
              if ('${(c['attendance'] as Map?)?['note'] ?? ''}'.isNotEmpty) ...[const SizedBox(width: 6), Expanded(child: Text('${(c['attendance'] as Map)['note']}', style: const TextStyle(color: Colors.black54)))],
            ]),
          if (!locked.contains('points')) ...[
            const SizedBox(height: 10),
            Row(children: [
              const Text('امتیاز امروز: '),
              Text('${((c['points_today'] ?? 0) as num) > 0 ? '+' : ''}${Fa.faDigits(c['points_today'] ?? 0)}', style: TextStyle(fontWeight: FontWeight.w700, color: ((c['points_today'] ?? 0) as num) < 0 ? HsColors.red : HsColors.green)),
              const Spacer(),
              Text('کل: ${Fa.faDigits(c['points_total'] ?? 0)}', style: const TextStyle(color: Colors.black54)),
            ]),
            for (final p in items)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Text('${p['icon'] ?? '⭐'} ${p['behavior']}  ${(p['points'] as num) > 0 ? '+' : ''}${Fa.faDigits(p['points'])}${'${p['note'] ?? ''}'.isNotEmpty ? ' — ${p['note']}' : ''}', style: const TextStyle(height: 1.6)),
              ),
          ],
          if (locked.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text('بخش‌هایی که مدرسه بسته است نمایش داده نمی‌شود.', style: TextStyle(color: Colors.grey.shade600, fontSize: 12)),
          ],
        ]),
      ),
      if (days.isNotEmpty) ...[
        const SizedBox(height: 10),
        SectionCard(
          title: 'این هفته',
          icon: Icons.date_range_rounded,
          child: Row(children: [
            for (final dd in days)
              Expanded(
                child: Column(children: [
                  Text(Week.shortNames[(DateTime.parse('${dd['date']}').weekday - DateTime.saturday + 7) % 7], style: const TextStyle(fontSize: 12, color: Colors.black54)),
                  const SizedBox(height: 4),
                  if (dd.containsKey('attendance'))
                    Container(
                      width: 26,
                      height: 26,
                      decoration: BoxDecoration(color: dd['attendance'] == null ? const Color(0xFFEDEDED) : AttStatus.of('${dd['attendance']}').color, shape: BoxShape.circle),
                      child: dd['attendance'] == null ? null : Icon(AttStatus.of('${dd['attendance']}').icon, size: 16, color: Colors.white),
                    ),
                  if (dd.containsKey('points')) ...[
                    const SizedBox(height: 4),
                    Text(Fa.faDigits(dd['points'] ?? 0), style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: ((dd['points'] ?? 0) as num) < 0 ? HsColors.red : HsColors.green)),
                  ],
                ]),
              ),
          ]),
        ),
      ],
      if (!locked.contains('homework')) ...[
        const SizedBox(height: 10),
        if (hw.isEmpty)
          const SectionCard(title: 'تکالیف', icon: Icons.menu_book_rounded, child: Text('تکلیف باز ندارد 🎉', style: TextStyle(color: Colors.black54)))
        else
          for (final h in hw) Padding(padding: const EdgeInsets.only(bottom: 8), child: HomeworkCard(h: h, myStatus: '${h['status'] ?? 'pending'}')),
      ],
      if (!locked.contains('announce') && anns.isNotEmpty) ...[
        const SizedBox(height: 10),
        _AnnouncementsList(classId: cid),
      ],
    ]);
  }
}

class _AnnouncementsList extends ConsumerWidget {
  const _AnnouncementsList({required this.classId});
  final int classId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final res = ref.watch(getProvider('/classes/$classId/announcements'));
    final list = ((res.valueOrNull?.data as List?) ?? const []).cast<Map>();
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      const Padding(padding: EdgeInsets.only(bottom: 6), child: Text('اطلاعیه‌های کلاس', style: TextStyle(fontWeight: FontWeight.w700, color: HsColors.navy))),
      for (final a in list.take(5)) Padding(padding: const EdgeInsets.only(bottom: 8), child: AnnouncementCard(a: a)),
    ]);
  }
}

/// رمز ۴ رقمی ورود فرزند با کارت کلاس
class _PinCard extends ConsumerStatefulWidget {
  const _PinCard({required this.childId});
  final int childId;
  @override
  ConsumerState<_PinCard> createState() => _PinCardState();
}

class _PinCardState extends ConsumerState<_PinCard> {
  bool _busy = false;
  String get _path => '/children/${widget.childId}/pin';

  Future<void> _set() async {
    final c = TextEditingController();
    final pin = await showDialog<String>(
      context: context,
      builder: (d) => AlertDialog(
        title: const Text('رمز ۴ رقمی فرزند'),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          const Text('فرزندتان با اسکن کارت کلاس و همین رمز وارد بخش دانش‌آموز می‌شود. رمز ساده (مثل ۱۲۳۴) پذیرفته نمی‌شود.', style: TextStyle(height: 1.7)),
          const SizedBox(height: 12),
          Directionality(
            textDirection: TextDirection.ltr,
            child: TextField(
              controller: c,
              autofocus: true,
              keyboardType: TextInputType.number,
              maxLength: 4,
              textAlign: TextAlign.center,
              obscureText: true,
              style: const TextStyle(fontSize: 28, letterSpacing: 14),
              inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9۰-۹]'))],
              decoration: const InputDecoration(counterText: ''),
            ),
          ),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(d), child: const Text('انصراف')),
          FilledButton(style: FilledButton.styleFrom(minimumSize: const Size(90, 44)), onPressed: () => Navigator.pop(d, Fa.enDigits(c.text)), child: const Text('ذخیره')),
        ],
      ),
    );
    if (pin == null || pin.isEmpty) return;
    await _call(() => ref.read(apiProvider).post<Object?>(_path, data: {'pin': pin}), 'رمز ذخیره شد ✅');
  }

  Future<void> _call(Future<Object?> Function() f, String ok) async {
    setState(() => _busy = true);
    try {
      await f();
      ref.invalidate(getProvider(_path));
      if (mounted) showMessage(context, ok);
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final res = ref.watch(getProvider(_path));
    if (res.hasError) return const SizedBox.shrink(); // سایت این امکان را ندارد
    final d = (res.valueOrNull?.data as Map?) ?? const {};
    final on = d['enabled'] == true;
    return SectionCard(
      title: 'ورود فرزند به اپ',
      icon: Icons.lock_person_rounded,
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Text(on ? 'فعال است: فرزندتان با کارت کلاس و رمز ۴ رقمی وارد می‌شود.' : 'با گذاشتن رمز ۴ رقمی، فرزندتان می‌تواند با کارت کلاس وارد شود و تکالیف و امتیازهایش را ببیند.',
            style: const TextStyle(height: 1.7, color: Colors.black87)),
        const SizedBox(height: 10),
        Row(children: [
          Expanded(child: FilledButton(onPressed: _busy || res.isLoading ? null : _set, child: Text(on ? 'تغییر رمز' : 'گذاشتن رمز'))),
          if (on) ...[
            const SizedBox(width: 8),
            TextButton(onPressed: _busy ? null : () => _call(() => ref.read(apiProvider).delete<Object?>(_path), 'ورود فرزند بسته شد.'), child: const Text('بستن')),
          ],
        ]),
      ]),
    );
  }
}
