import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../core/utils/fa.dart';
import '../../core/widgets/hs_avatar.dart';
import '../../core/widgets/states.dart';
import '../../core/widgets/ui.dart';

/// خانه‌ی والد: پیشنهادهای اتصال + کارت هر فرزند با خلاصه‌ی امروز
class ParentHome extends ConsumerWidget {
  const ParentHome({super.key});

  static void refreshAll(WidgetRef ref) {
    ref.invalidate(getProvider('/children'));
    ref.invalidate(getProvider('/children/proposals'));
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final kids = ref.watch(getProvider('/children'));
    final props = ref.watch(getProvider('/children/proposals'));
    final proposals = ((props.valueOrNull?.data as List?) ?? const []).cast<Map>();
    return Scaffold(
      backgroundColor: Colors.transparent,
      floatingActionButton: FloatingActionButton.extended(
        key: const Key('add_child'),
        heroTag: 'add_child',
        backgroundColor: HsColors.orange,
        foregroundColor: Colors.white,
        onPressed: () async {
          await context.push('/children/add');
          refreshAll(ref);
        },
        icon: const Icon(Icons.qr_code_scanner_rounded),
        label: const Text('افزودن فرزند'),
      ),
      body: RefreshIndicator(
        color: HsColors.orange,
        onRefresh: () async => refreshAll(ref),
        child: kids.when(
          loading: () => const LoadingView(),
          error: (e, _) => ListView(children: [const SizedBox(height: 80), ErrorView(error: e, onRetry: () => refreshAll(ref))]),
          data: (r) {
            final list = ((r.data as List?) ?? const []).cast<Map>();
            return ListView(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 96),
              children: [
                if (r.fromCache) ...[OfflineBanner(savedAt: r.savedAt), const SizedBox(height: 8)],
                for (final p in proposals) Padding(padding: const EdgeInsets.only(bottom: 12), child: _ProposalCard(p: p)),
                if (list.isEmpty && proposals.isEmpty)
                  const EmptyView(
                    icon: Icons.child_care_rounded,
                    title: 'هنوز فرزندی متصل نشده',
                    text: 'دکمه‌ی «افزودن فرزند» را بزنید و کد اتصالی را که معلم داده وارد کنید، یا QR کلاس را اسکن کنید.',
                  ),
                for (final k in list) Padding(padding: const EdgeInsets.only(bottom: 14), child: _ChildCard(k: k)),
              ],
            );
          },
        ),
      ),
    );
  }
}

class _ProposalCard extends ConsumerStatefulWidget {
  const _ProposalCard({required this.p});
  final Map p;
  @override
  ConsumerState<_ProposalCard> createState() => _ProposalCardState();
}

class _ProposalCardState extends ConsumerState<_ProposalCard> {
  bool _busy = false;

  Future<void> _answer(bool accept) async {
    setState(() => _busy = true);
    try {
      await ref.read(apiProvider).post<Object?>('/children/proposals/${widget.p['id']}', data: {'accept': accept});
      if (mounted) showMessage(context, accept ? 'به ${widget.p['student']} وصل شدید ✅' : 'رد شد.');
      ParentHome.refreshAll(ref);
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = widget.p;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: const Color(0xFFE8F0FB), borderRadius: BorderRadius.circular(20)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Row(children: [
          HsAvatar(name: '${p['student']}', size: 44),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              '${p['teacher'] != '' ? '${p['teacher']}' : 'معلم'}، شماره‌ی شما را به‌عنوان والد «${p['student']}»${p['class'] != '' ? ' در ${p['class']}' : ''} ثبت کرده است.',
              style: TextStyle(height: 1.7, color: HsColors.navy),
            ),
          ),
        ]),
        const SizedBox(height: 12),
        Row(children: [
          Expanded(child: FilledButton(key: Key('accept_${p['id']}'), onPressed: _busy ? null : () => _answer(true), child: const Text('بله، فرزند من است'))),
          const SizedBox(width: 8),
          TextButton(onPressed: _busy ? null : () => _answer(false), child: const Text('نه')),
        ]),
      ]),
    );
  }
}

class _ChildCard extends ConsumerWidget {
  const _ChildCard({required this.k});
  final Map k;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final id = (k['id'] as num).toInt();
    final today = ref.watch(getProvider('/children/$id/today'));
    final t = (today.valueOrNull?.data as Map?) ?? const {};
    final classes = ((t['classes'] as List?) ?? (k['classes'] as List?) ?? const []).cast<Map>();
    final first = classes.isEmpty ? null : classes.first;
    return Material(
      color: HsColors.card,
      borderRadius: BorderRadius.circular(22),
      child: InkWell(
        borderRadius: BorderRadius.circular(22),
        onTap: () => context.push('/children/$id', extra: '${k['name']}'),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Row(children: [
              HsAvatar(name: '${k['name']}', url: '${k['avatar'] ?? ''}', size: 60),
              const SizedBox(width: 14),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('${k['name']}', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: HsColors.navy)),
                  if (first != null) Text('${first['title']}  •  ${first['teacher']}', style: TextStyle(color: HsColors.muted, fontSize: 13)),
                  if (first == null) Text('هنوز در کلاسی ثبت نشده', style: TextStyle(color: HsColors.muted, fontSize: 13)),
                ]),
              ),
              Icon(Icons.chevron_left_rounded, color: HsColors.line),
            ]),
            if (first != null) ...[
              const SizedBox(height: 14),
              if (!today.hasValue && today.isLoading) const LinearProgressIndicator(minHeight: 2, color: HsColors.orange),
              if (today.hasValue) _TodayRow(c: first),
            ],
          ]),
        ),
      ),
    );
  }
}

/// سه خانه‌ی امروز: حضور، امتیاز، تکلیف باز
class _TodayRow extends StatelessWidget {
  const _TodayRow({required this.c});
  final Map c;

  @override
  Widget build(BuildContext context) {
    final locked = ((c['locked'] as List?) ?? const []).map((e) => '$e').toSet();
    final att = c['attendance'] is Map ? AttStatus.of('${(c['attendance'] as Map)['status']}') : null;
    final pts = (c['points_today'] as num?)?.toInt();
    final hw = (c['homework_open'] as List?)?.length;
    Widget box(String title, String value, Color color, IconData icon) => Expanded(
          child: Container(
            padding: const EdgeInsets.symmetric(vertical: 10),
            margin: const EdgeInsets.symmetric(horizontal: 4),
            decoration: BoxDecoration(color: color.withValues(alpha: 0.10), borderRadius: BorderRadius.circular(16)),
            child: Column(children: [
              Icon(icon, color: color, size: 22),
              const SizedBox(height: 4),
              Text(value, style: TextStyle(fontWeight: FontWeight.w700, color: color)),
              Text(title, style: TextStyle(fontSize: 11, color: HsColors.muted)),
            ]),
          ),
        );
    return Row(children: [
      if (locked.contains('attendance'))
        box('حضور', '—', Colors.grey, Icons.lock_outline_rounded)
      else
        box('حضور امروز', att?.label ?? 'ثبت‌نشده', att?.color ?? Colors.grey, att?.icon ?? Icons.help_outline_rounded),
      if (locked.contains('points'))
        box('امتیاز', '—', Colors.grey, Icons.lock_outline_rounded)
      else
        box('امتیاز امروز', '${(pts ?? 0) > 0 ? '+' : ''}${Fa.faDigits(pts ?? 0)}', (pts ?? 0) < 0 ? HsColors.red : HsColors.green, Icons.stars_rounded),
      if (locked.contains('homework'))
        box('تکلیف', '—', Colors.grey, Icons.lock_outline_rounded)
      else
        box('تکلیف باز', Fa.faDigits(hw ?? 0), (hw ?? 0) > 0 ? HsColors.orange : HsColors.green, Icons.menu_book_rounded),
    ]);
  }
}
