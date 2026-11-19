import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../core/utils/fa.dart';
import '../../core/widgets/states.dart';

/// زنگوله‌ی اعلان با نشان عددی (در نوار بالای خانه)
class NotificationBell extends ConsumerWidget {
  const NotificationBell({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final n = ref.watch(getProvider('/notifications'));
    final unread = (((n.valueOrNull?.data as Map?)?['unread']) as num?)?.toInt() ?? 0;
    return IconButton(
      key: const Key('bell'),
      tooltip: 'اعلان‌ها',
      onPressed: () async {
        await context.push('/notifications');
        ref.invalidate(getProvider('/notifications'));
      },
      icon: Badge(
        isLabelVisible: unread > 0,
        backgroundColor: HsColors.red,
        label: Text(Fa.faDigits(unread > 99 ? '99+' : unread)),
        child: const Icon(Icons.notifications_rounded),
      ),
    );
  }
}

class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});

  static final _icons = {
    'attendance': (Icons.fact_check_rounded, HsColors.red),
    'homework': (Icons.menu_book_rounded, HsColors.orange),
    'announce': (Icons.campaign_rounded, HsColors.blue),
    'message': (Icons.forum_rounded, HsColors.navy),
    'points': (Icons.stars_rounded, HsColors.amber),
    'portfolio': (Icons.photo_album_rounded, HsColors.green),
    'request': (Icons.person_add_rounded, HsColors.blue),
    'claim': (Icons.account_balance_rounded, HsColors.blue),
  };

  void _open(BuildContext context, Map n) {
    final d = (n['data'] is Map) ? n['data'] as Map : const {};
    switch ('${n['type']}') {
      case 'message':
        if (d['user'] != null) context.push('/messages/${d['user']}');
      case 'request':
        context.push('/requests');
      case 'attendance' || 'points':
        if (d['child'] != null) context.push('/children/${d['child']}');
      case 'portfolio':
        if (d['class'] != null) context.push('/class/${d['class']}');
      default:
        break;
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final res = ref.watch(getProvider('/notifications'));
    return Scaffold(
      appBar: AppBar(
        title: const Text('اعلان‌ها'),
        actions: [
          TextButton(
            onPressed: () async {
              await ref.read(apiProvider).post<Object?>('/notifications/read');
              ref.invalidate(getProvider('/notifications'));
            },
            child: const Text('همه خوانده شد'),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async => ref.invalidate(getProvider('/notifications')),
        child: res.when(
          loading: () => const LoadingView(),
          error: (e, _) => ListView(children: [const SizedBox(height: 80), ErrorView(error: e, onRetry: () => ref.invalidate(getProvider('/notifications')))]),
          data: (r) {
            final items = ((((r.data as Map?)?['items']) as List?) ?? const []).cast<Map>();
            if (items.isEmpty) return ListView(children: const [SizedBox(height: 60), EmptyView(icon: Icons.notifications_none_rounded, title: 'اعلانی ندارید')]);
            return ListView.builder(
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 24),
              itemCount: items.length,
              itemBuilder: (_, i) {
                final n = items[i];
                final ic = _icons['${n['type']}'] ?? (Icons.notifications_rounded, HsColors.navy);
                final unread = n['is_read'] != true;
                return Card(
                  color: unread ? const Color(0xFFFFF1E3) : HsColors.card,
                  margin: const EdgeInsets.only(bottom: 8),
                  child: ListTile(
                    onTap: () async {
                      if (unread) {
                        await ref.read(apiProvider).post<Object?>('/notifications/read', data: {'ids': [n['id']]});
                        ref.invalidate(getProvider('/notifications'));
                      }
                      if (context.mounted) _open(context, n);
                    },
                    leading: CircleAvatar(backgroundColor: ic.$2.withValues(alpha: 0.12), child: Icon(ic.$1, color: ic.$2)),
                    title: Text('${n['title']}', style: TextStyle(fontWeight: unread ? FontWeight.w700 : FontWeight.w500)),
                    subtitle: Text('${n['body']}', style: const TextStyle(height: 1.6)),
                    trailing: Text(Fa.date('${n['created_at'] ?? ''}', withYear: false), style: TextStyle(fontSize: 11, color: HsColors.faint)),
                  ),
                );
              },
            );
          },
        ),
      ),
    );
  }
}
