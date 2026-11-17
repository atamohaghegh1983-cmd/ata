import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../core/utils/fa.dart';
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
                    title: Text('${m['name']}', style: TextStyle(fontWeight: m['unread'] == true ? FontWeight.w700 : FontWeight.w500)),
                    subtitle: Text('${m['preview'] ?? ''}', maxLines: 1, overflow: TextOverflow.ellipsis),
                    trailing: Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.end, children: [
                      Text(Fa.date('${m['at'] ?? ''}', withYear: false), style: const TextStyle(fontSize: 11, color: Colors.black45)),
                      if (m['unread'] == true) const Icon(Icons.circle, size: 10, color: HsColors.orange),
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

  @override
  Widget build(BuildContext context) {
    final res = ref.watch(getProvider(_path));
    final d = (res.valueOrNull?.data as Map?) ?? const {};
    final canReply = d['can_reply'] != false;
    return Scaffold(
      appBar: AppBar(title: Text(widget.name.isEmpty ? 'گفتگو' : widget.name)),
      body: Column(children: [
        Expanded(
          child: res.when(
            loading: () => const LoadingView(),
            error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(getProvider(_path))),
            data: (r) {
              final msgs = (((r.data as Map?)?['messages'] as List?) ?? const []).cast<Map>().reversed.toList();
              if (msgs.isEmpty) return const EmptyView(icon: Icons.waving_hand_rounded, title: 'شروع گفتگو', text: 'اولین پیام را بنویسید.');
              return ListView.builder(
                reverse: true,
                padding: const EdgeInsets.fromLTRB(12, 12, 12, 12),
                itemCount: msgs.length,
                itemBuilder: (_, i) {
                  final m = msgs[i];
                  final mine = m['mine'] == true;
                  return Align(
                    alignment: mine ? AlignmentDirectional.centerStart : AlignmentDirectional.centerEnd,
                    child: Container(
                      constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.78),
                      margin: const EdgeInsets.symmetric(vertical: 4),
                      padding: const EdgeInsets.fromLTRB(14, 10, 14, 6),
                      decoration: BoxDecoration(
                        color: mine ? HsColors.orange : Colors.white,
                        borderRadius: BorderRadius.circular(18),
                      ),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text('${m['body']}', style: TextStyle(color: mine ? Colors.white : HsColors.navy, height: 1.7)),
                        Text(Fa.date('${m['at'] ?? ''}', withYear: false), style: TextStyle(fontSize: 10, color: mine ? Colors.white70 : Colors.black38)),
                      ]),
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
            color: Colors.white,
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
                : const Padding(
                    padding: EdgeInsets.all(10),
                    child: Text('فرستادن پیام در این گفتگو بسته است.', textAlign: TextAlign.center, style: TextStyle(color: Colors.black54)),
                  ),
          ),
        ),
      ]),
    );
  }
}
