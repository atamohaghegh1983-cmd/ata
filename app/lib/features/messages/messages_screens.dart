import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../core/utils/fa.dart';
import '../../core/widgets/confirm.dart';
import '../../core/widgets/hs_avatar.dart';
import '../../core/widgets/states.dart';

/// صندوق پیام‌ها
class InboxView extends ConsumerWidget {
  const InboxView({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final res = ref.watch(getProvider('/messages'));
    return RefreshIndicator(
      color: HsColors.orange,
      onRefresh: () async => ref.invalidate(getProvider('/messages')),
      child: res.when(
        loading: () => const LoadingView(),
        error: (e, _) => ListView(children: [const SizedBox(height: 80), ErrorView(error: e, onRetry: () => ref.invalidate(getProvider('/messages')))]),
        data: (r) {
          final list = ((r.data as List?) ?? const []).cast<Map>();
          return ListView(
            padding: const EdgeInsets.fromLTRB(12, 4, 12, 24),
            children: [
              if (r.fromCache) ...[OfflineBanner(savedAt: r.savedAt), const SizedBox(height: 8)],
              if (list.isEmpty)
                const EmptyView(icon: Icons.forum_rounded, title: 'پیامی ندارید', text: 'گفتگو با معلم یا والدین از صفحه‌ی فرزند یا دانش‌آموز شروع می‌شود.'),
              for (final m in list)
                Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: ListTile(
                    onTap: () async {
                      await context.push('/messages/${m['user_id']}', extra: '${m['name']}');
                      ref.invalidate(getProvider('/messages'));
                    },
                    leading: HsAvatar(name: '${m['name']}', url: '${m['avatar'] ?? ''}', size: 46),
                    title: Text.rich(
                      TextSpan(children: [
                        TextSpan(text: '${m['name']}', style: TextStyle(fontWeight: m['unread'] == true ? FontWeight.w700 : FontWeight.w500)),
                        if ('${m['label'] ?? ''}'.isNotEmpty) TextSpan(text: '  ${m['label']}', style: const TextStyle(fontSize: 12, color: HsColors.blue)),
                      ]),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    subtitle: Text(
                      ((m['unread_count'] as num?) ?? 0) > 1 ? '${Fa.faDigits(m['unread_count'])} پیام جدید' : '${m['preview'] ?? ''}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(color: ((m['unread_count'] as num?) ?? 0) > 1 ? HsColors.orangeDark : null, fontWeight: ((m['unread_count'] as num?) ?? 0) > 1 ? FontWeight.w700 : null),
                    ),
                    trailing: Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.end, children: [
                      Text(Fa.date('${m['at'] ?? ''}', withYear: false), style: TextStyle(fontSize: 11, color: HsColors.faint)),
                      const SizedBox(height: 4),
                      if (((m['unread_count'] as num?) ?? 0) > 0)
                        Badge(key: Key('unread_${m['user_id']}'), backgroundColor: HsColors.orange, label: Text(Fa.faDigits(m['unread_count']))),
                    ]),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}

/// گفتگو با یک نفر
class ThreadScreen extends ConsumerStatefulWidget {
  const ThreadScreen({super.key, required this.userId, this.name = ''});
  final int userId;
  final String name;
  @override
  ConsumerState<ThreadScreen> createState() => _ThreadScreenState();
}

class _ThreadScreenState extends ConsumerState<ThreadScreen> {
  final _c = TextEditingController();
  bool _busy = false;
  String get _path => '/messages/${widget.userId}';

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    final t = _c.text.trim();
    if (t.isEmpty) return;
    setState(() => _busy = true);
    try {
      await ref.read(apiProvider).post<Object?>('/messages', data: {'recipient_id': widget.userId, 'body': t});
      _c.clear();
      ref.invalidate(getProvider(_path));
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  /// نگه‌داشتن انگشت روی پیام: ویرایش (فرستنده تا ۲۴ ساعت)، اطلاعات پیام، حذف
  Future<void> _actions(Map m) async {
    final mine = m['mine'] == true;
    final act = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (c) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          if (m['can_edit'] == true) ListTile(leading: const Icon(Icons.edit_rounded), title: const Text('ویرایش'), onTap: () => Navigator.pop(c, 'edit')),
          if (mine) ListTile(leading: const Icon(Icons.info_outline_rounded), title: const Text('اطلاعات پیام'), onTap: () => Navigator.pop(c, 'info')),
          ListTile(leading: const Icon(Icons.copy_rounded), title: const Text('کپی متن'), onTap: () => Navigator.pop(c, 'copy')),
          if (m['can_delete'] == true) ListTile(leading: const Icon(Icons.delete_outline_rounded, color: HsColors.red), title: const Text('حذف', style: TextStyle(color: HsColors.red)), onTap: () => Navigator.pop(c, 'delete')),
        ]),
      ),
    );
    if (!mounted || act == null) return;
    switch (act) {
      case 'edit':
        await _edit(m);
      case 'info':
        await showModalBottomSheet<void>(
          context: context,
          showDragHandle: true,
          builder: (c) => SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
              child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                Text('اطلاعات پیام', style: Theme.of(c).textTheme.titleMedium),
                const SizedBox(height: 10),
                _infoRow(const Ticks(receipt: 'sent'), 'ارسال شد', '${m['at'] ?? ''}'),
                _infoRow(const Ticks(receipt: 'delivered'), 'رسید', '${m['delivered_at'] ?? ''}'),
                _infoRow(const Ticks(receipt: 'read'), 'خوانده شد', '${m['read_at'] ?? ''}'),
              ]),
            ),
          ),
        );
      case 'copy':
        await Clipboard.setData(ClipboardData(text: '${m['body']}'));
        if (mounted) showMessage(context, 'متن کپی شد.');
      case 'delete':
        await _delete(m);
    }
  }

  Widget _infoRow(Widget icon, String label, String at) => ListTile(
        contentPadding: EdgeInsets.zero,
        leading: icon,
        title: Text(label),
        trailing: Text(at.length >= 16 && at != 'null' ? '${Fa.weekday(DateTime.parse(at.substring(0, 10)))} ${Fa.date(at, withYear: false)} — ${msgTime(at)}' : '—', style: TextStyle(color: HsColors.muted)),
      );

  Future<void> _edit(Map m) async {
    final c = TextEditingController(text: '${m['body']}');
    final ok = await showDialog<bool>(
      context: context,
      builder: (d) => AlertDialog(
        title: const Text('ویرایش پیام'),
        content: TextField(key: const Key('msg_edit_field'), controller: c, autofocus: true, minLines: 1, maxLines: 6),
        actions: [
          TextButton(onPressed: () => Navigator.pop(d, false), child: const Text('انصراف')),
          FilledButton(style: FilledButton.styleFrom(minimumSize: const Size(90, 44)), onPressed: () => Navigator.pop(d, true), child: const Text('ذخیره')),
        ],
      ),
    );
    if (ok != true || c.text.trim().isEmpty || c.text.trim() == '${m['body']}') return;
    try {
      await ref.read(apiProvider).post<Object?>('/messages/item/${m['id']}', data: {'body': c.text.trim()});
      ref.invalidate(getProvider(_path));
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    }
  }

  /// حذف پیام: فرستنده، معلم کلاس، مدیر مدرسه، نماینده‌ی والدین
  Future<void> _delete(Map m) async {
    if (!await confirmDanger(context, title: 'حذف پیام', text: 'این پیام برای هر دو طرف حذف می‌شود.', ok: 'حذف')) return;
    try {
      await ref.read(apiProvider).delete<Object?>('/messages/item/${m['id']}');
      ref.invalidate(getProvider(_path));
      ref.invalidate(getProvider('/messages'));
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final res = ref.watch(getProvider(_path));
    final d = (res.valueOrNull?.data as Map?) ?? const {};
    final canReply = d['can_reply'] != false;
    final partner = (d['partner'] as Map?) ?? const {};
    final name = '${partner['name'] ?? ''}'.isNotEmpty ? '${partner['name']}' : widget.name;
    final label = '${partner['label'] ?? ''}';
    return Scaffold(
      appBar: AppBar(
        titleSpacing: 0,
        title: Row(children: [
          HsAvatar(name: name.isEmpty ? '؟' : name, url: '${partner['avatar'] ?? ''}', size: 38),
          const SizedBox(width: 10),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(name.isEmpty ? 'گفتگو' : 'گفتگو با $name', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 16)),
              if (label.isNotEmpty) Text(label, style: const TextStyle(fontSize: 12, color: HsColors.blue, fontFamily: 'Vazirmatn')),
            ]),
          ),
        ]),
      ),
      body: Column(children: [
        Expanded(
          child: res.when(
            loading: () => const LoadingView(),
            error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(getProvider(_path))),
            data: (r) {
              final msgs = (((r.data as Map?)?['messages'] as List?) ?? const []).cast<Map>().toList();
              if (msgs.isEmpty) return const EmptyView(icon: Icons.waving_hand_rounded, title: 'شروع گفتگو', text: 'اولین پیام را بنویسید.');
              // ردیف‌ها: جداکننده‌ی تاریخ + پیام‌ها (مثل واتساپ)؛ لیست برعکس است تا آخرین پیام پایین باشد
              final rows = <Object>[];
              String? last;
              for (final m in msgs) {
                final day = '${m['at'] ?? ''}'.length >= 10 ? '${m['at']}'.substring(0, 10) : '';
                if (day != last) {
                  rows.add(day);
                  last = day;
                }
                rows.add(m);
              }
              final items = rows.reversed.toList();
              return ListView.builder(
                reverse: true,
                padding: const EdgeInsets.fromLTRB(12, 12, 12, 12),
                itemCount: items.length,
                itemBuilder: (_, i) {
                  final it = items[i];
                  if (it is String) return _DateChip(ymd: it);
                  final m = it as Map;
                  final mine = m['mine'] == true;
                  return Align(
                    alignment: mine ? AlignmentDirectional.centerStart : AlignmentDirectional.centerEnd,
                    child: GestureDetector(
                      onLongPress: () => _actions(m),
                      child: Container(
                        constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.78),
                        margin: const EdgeInsets.symmetric(vertical: 3),
                        padding: const EdgeInsets.fromLTRB(12, 8, 12, 5),
                        decoration: BoxDecoration(
                          color: mine ? HsColors.bubbleMine : HsColors.card,
                          borderRadius: BorderRadius.circular(16),
                        ),
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
                          Text('${m['body']}', style: TextStyle(color: HsColors.navy, height: 1.7)),
                          Row(mainAxisSize: MainAxisSize.min, children: [
                            if (m['edited'] == true) Text('ویرایش‌شده  ', style: TextStyle(fontSize: 10, color: HsColors.faint2)),
                            Text(msgTime('${m['at'] ?? ''}'), style: TextStyle(fontSize: 10.5, color: HsColors.faint)),
                            if (mine) ...[const SizedBox(width: 4), Ticks(receipt: '${m['receipt'] ?? 'sent'}')],
                          ]),
                        ]),
                      ),
                    ),
                  );
                },
              );
            },
          ),
        ),
        SafeArea(
          top: false,
          child: Container(
            color: HsColors.card,
            padding: const EdgeInsets.fromLTRB(8, 6, 8, 6),
            child: canReply
                ? Row(children: [
                    Expanded(
                      child: TextField(
                        key: const Key('msg_field'),
                        controller: _c,
                        minLines: 1,
                        maxLines: 4,
                        decoration: const InputDecoration(hintText: 'پیام…', contentPadding: EdgeInsets.symmetric(horizontal: 14, vertical: 10)),
                      ),
                    ),
                    const SizedBox(width: 6),
                    IconButton.filled(
                      key: const Key('msg_send'),
                      style: IconButton.styleFrom(backgroundColor: HsColors.orange),
                      onPressed: _busy ? null : _send,
                      icon: const Icon(Icons.send_rounded, color: Colors.white),
                    ),
                  ])
                : Padding(
                    padding: EdgeInsets.all(10),
                    child: Text('فرستادن پیام در این گفتگو بسته است.', textAlign: TextAlign.center, style: TextStyle(color: HsColors.muted)),
                  ),
          ),
        ),
      ]),
    );
  }
}

/// ساعت پیام «۱۴:۰۵»
String msgTime(String at) => at.length >= 16 ? Fa.faDigits(at.substring(11, 16)) : '';

/// تیک‌های وضعیت پیام: ✓ ارسال، ✓✓ رسید، ✓✓ سبز خوانده شد
class Ticks extends StatelessWidget {
  const Ticks({super.key, required this.receipt});
  final String receipt;

  @override
  Widget build(BuildContext context) {
    final read = receipt == 'read';
    final two = receipt != 'sent';
    final color = read ? const Color(0xFF16A34A) : HsColors.faint2;
    return Icon(two ? Icons.done_all_rounded : Icons.done_rounded, size: 16, color: color, semanticLabel: read ? 'خوانده شد' : (two ? 'رسید' : 'ارسال شد'));
  }
}

/// جداکننده‌ی تاریخ در گفتگو: امروز، دیروز، «سه‌شنبه ۲۶ آبان»
class _DateChip extends StatelessWidget {
  const _DateChip({required this.ymd});
  final String ymd;

  @override
  Widget build(BuildContext context) {
    final d = DateTime.tryParse(ymd);
    var t = '';
    if (d != null) {
      final today = DateUtils.dateOnly(DateTime.now());
      final diff = today.difference(DateUtils.dateOnly(d)).inDays;
      t = diff == 0 ? 'امروز' : (diff == 1 ? 'دیروز' : '${Fa.weekday(d)} ${Fa.date(ymd, withYear: d.year != today.year)}');
    }
    return Center(
      child: Container(
        margin: const EdgeInsets.symmetric(vertical: 8),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
        decoration: BoxDecoration(color: HsColors.dark ? HsColors.card : const Color(0xFFE8EEF8), borderRadius: BorderRadius.circular(12)),
        child: Text(t, style: TextStyle(fontSize: 12, color: HsColors.navy)),
      ),
    );
  }
}
