import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_widget_from_html_core/flutter_widget_from_html_core.dart';
import 'package:go_router/go_router.dart';

import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../core/utils/fa.dart';
import '../../core/widgets/states.dart';
import '../../core/widgets/ui.dart';

/// مجله: فهرست مطالب با برچسب پایه/مخاطب و قفل
class MagazineView extends ConsumerStatefulWidget {
  const MagazineView({super.key, this.showMineFilter = false});
  final bool showMineFilter;
  @override
  ConsumerState<MagazineView> createState() => _MagazineViewState();
}

class _MagazineViewState extends ConsumerState<MagazineView> {
  bool _mine = false;

  @override
  Widget build(BuildContext context) {
    final path = '/articles?per_page=20${_mine ? '&mine=1' : ''}';
    final res = ref.watch(getProvider(path));
    return RefreshIndicator(
      color: HsColors.orange,
      onRefresh: () async => ref.invalidate(getProvider(path)),
      child: res.when(
        loading: () => const LoadingView(),
        error: (e, _) => ListView(children: [const SizedBox(height: 80), ErrorView(error: e, onRetry: () => ref.invalidate(getProvider(path)))]),
        data: (r) {
          final items = ((((r.data as Map?)?['items']) as List?) ?? const []).cast<Map>();
          return ListView(
            padding: const EdgeInsets.fromLTRB(14, 4, 14, 24),
            children: [
              if (r.fromCache) ...[OfflineBanner(savedAt: r.savedAt), const SizedBox(height: 8)],
              if (widget.showMineFilter)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Wrap(spacing: 8, children: [
                    ChoiceChip(label: const Text('همه'), selected: !_mine, onSelected: (_) => setState(() => _mine = false)),
                    ChoiceChip(label: const Text('مطالب پایه‌ی من'), selected: _mine, onSelected: (_) => setState(() => _mine = true)),
                  ]),
                ),
              if (items.isEmpty) const EmptyView(icon: Icons.auto_stories_rounded, title: 'مطلبی پیدا نشد'),
              for (final a in items)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Material(
                    color: HsColors.card,
                    borderRadius: BorderRadius.circular(20),
                    clipBehavior: Clip.antiAlias,
                    child: InkWell(
                      onTap: () => context.push('/articles/${a['id']}'),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                        if ('${a['image'] ?? ''}'.isNotEmpty)
                          AspectRatio(aspectRatio: 16 / 8, child: Image.network('${a['image']}', fit: BoxFit.cover, errorBuilder: (_, _, _) => const SizedBox())),
                        Padding(
                          padding: const EdgeInsets.all(14),
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Row(children: [
                              Expanded(child: Text('${a['title']}', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: HsColors.navy, height: 1.6))),
                              if (a['locked'] == true) Icon(Icons.lock_rounded, color: HsColors.faint2, size: 20),
                            ]),
                            const SizedBox(height: 4),
                            Text('${a['excerpt'] ?? ''}', maxLines: 3, overflow: TextOverflow.ellipsis, style: TextStyle(color: HsColors.muted, height: 1.7)),
                            const SizedBox(height: 8),
                            Wrap(spacing: 6, runSpacing: 6, children: [
                              for (final g in ((a['grades'] as List?) ?? const [])) Pill('پایه‌ی $g', color: HsColors.blue),
                              for (final au in ((a['audiences'] as List?) ?? const []).cast<Map>()) Pill('${au['title']}', color: HsColors.orangeDark),
                              Text(Fa.date('${a['date'] ?? ''}'), style: TextStyle(color: HsColors.faint2, fontSize: 12)),
                            ]),
                          ]),
                        ),
                      ]),
                    ),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}

class ArticleScreen extends ConsumerWidget {
  const ArticleScreen({super.key, required this.id});
  final int id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final res = ref.watch(getProvider('/articles/$id'));
    return Scaffold(
      appBar: AppBar(title: const Text('مجله')),
      body: res.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(getProvider('/articles/$id'))),
        data: (r) {
          final a = (r.data as Map?) ?? const {};
          final locked = a['locked'] == true;
          return ListView(
            padding: const EdgeInsets.fromLTRB(18, 4, 18, 32),
            children: [
              if ('${a['image'] ?? ''}'.isNotEmpty)
                ClipRRect(borderRadius: BorderRadius.circular(18), child: Image.network('${a['image']}', fit: BoxFit.cover, errorBuilder: (_, _, _) => const SizedBox())),
              const SizedBox(height: 12),
              Text('${a['title'] ?? ''}', style: Theme.of(context).textTheme.headlineSmall),
              Text(Fa.date('${a['date'] ?? ''}'), style: TextStyle(color: HsColors.faint)),
              const SizedBox(height: 12),
              if (!locked)
                HtmlWidget('${a['content'] ?? ''}', textStyle: const TextStyle(fontSize: 16, height: 1.9, fontFamily: 'Vazirmatn')),
              if (locked) ...[
                Text('${a['teaser'] ?? ''}', style: const TextStyle(fontSize: 16, height: 1.9)),
                const SizedBox(height: 16),
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(color: const Color(0xFFFFEAD6), borderRadius: BorderRadius.circular(16)),
                  child: Row(children: [
                    const Icon(Icons.lock_rounded, color: HsColors.orangeDark),
                    const SizedBox(width: 10),
                    Expanded(child: Text('${(a['lock'] as Map?)?['message'] ?? 'این مطلب برای شما قفل است.'}', style: const TextStyle(height: 1.7, color: HsColors.orangeDark))),
                  ]),
                ),
              ],
            ],
          );
        },
      ),
    );
  }
}
