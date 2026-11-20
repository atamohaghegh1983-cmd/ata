import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/api/api_exception.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../core/utils/fa.dart';
import '../../core/widgets/confirm.dart';
import '../../core/widgets/hs_avatar.dart';
import '../../core/widgets/states.dart';
import '../auth/auth_controller.dart';

/// انتخاب عکس از دوربین یا گالری و بارگذاری با فیلد photo
Future<Map<String, dynamic>?> pickAndUpload(BuildContext context, WidgetRef ref, String path, {Map<String, dynamic> fields = const {}, bool required = true}) async {
  final src = await showModalBottomSheet<ImageSource>(
    context: context,
    showDragHandle: true,
    builder: (c) => SafeArea(
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        ListTile(leading: const Icon(Icons.photo_camera_rounded), title: const Text('دوربین'), onTap: () => Navigator.pop(c, ImageSource.camera)),
        ListTile(leading: const Icon(Icons.photo_library_rounded), title: const Text('گالری'), onTap: () => Navigator.pop(c, ImageSource.gallery)),
      ]),
    ),
  );
  if (src == null) return null;
  final img = await ImagePicker().pickImage(source: src, maxWidth: 1600, imageQuality: 85);
  if (img == null) return null;
  return uploadImage(ref, path, img, fields);
}

Future<Map<String, dynamic>> uploadImage(WidgetRef ref, String path, XFile img, Map<String, dynamic> fields) async {
  final form = FormData.fromMap({...fields, 'photo': await MultipartFile.fromFile(img.path, filename: img.name)});
  return ref.read(apiProvider).post<Map<String, dynamic>>(path, data: form);
}

/// پورتفولیوی یک دانش‌آموز (خودش، والدش در حالت کودک، معلم)
class PortfolioView extends ConsumerWidget {
  const PortfolioView({super.key, required this.studentId});
  final int studentId;

  String get _path => '/portfolio/student/$studentId';

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final res = ref.watch(getProvider(_path));
    return res.when(
      loading: () => const LoadingView(),
      error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(getProvider(_path))),
      data: (r) {
        final d = (r.data as Map?) ?? const {};
        final st = (d['student'] as Map?) ?? const {};
        final posts = ((d['posts'] as List?) ?? const []).cast<Map>();
        final canPost = d['can_post'] == true;
        final left = ((d['limit'] ?? 2) as num).toInt() - ((d['today'] ?? 0) as num).toInt();
        return Scaffold(
          backgroundColor: Colors.transparent,
          floatingActionButton: canPost
              ? FloatingActionButton.extended(
                  key: const Key('new_post'),
                  heroTag: 'new_post',
                  backgroundColor: left > 0 ? HsColors.orange : Colors.grey,
                  foregroundColor: Colors.white,
                  onPressed: left > 0
                      ? () async {
                          final ok = await showModalBottomSheet<bool>(context: context, isScrollControlled: true, showDragHandle: true, builder: (_) => _NewPostSheet(studentId: studentId));
                          if (ok == true) ref.invalidate(getProvider(_path));
                        }
                      : () => showMessage(context, 'امروز ۲ پست گذاشته‌ای؛ فردا دوباره 🌟'),
                  icon: const Icon(Icons.add_a_photo_rounded),
                  label: Text(left > 0 ? 'پست تازه (${Fa.faDigits(left)} مانده)' : 'فردا دوباره'),
                )
              : null,
          body: RefreshIndicator(
            onRefresh: () async => ref.invalidate(getProvider(_path)),
            child: ListView(padding: const EdgeInsets.fromLTRB(14, 12, 14, 96), children: [
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Row(children: [
                    GestureDetector(
                      onTap: canPost
                          ? () async {
                              try {
                                final self = ref.read(authProvider).me?.id == studentId;
                                final r = await pickAndUpload(context, ref, self ? '/me/avatar' : '/children/$studentId/avatar');
                                if (r != null) ref.invalidate(getProvider(_path));
                              } on ApiException catch (e) {
                                if (context.mounted) showMessage(context, e.message, error: true);
                              }
                            }
                          : null,
                      child: Stack(children: [
                        HsAvatar(name: '${st['name'] ?? ''}', url: '${st['avatar'] ?? ''}', size: 72),
                        if (canPost) const PositionedDirectional(bottom: 0, end: 0, child: CircleAvatar(radius: 12, backgroundColor: HsColors.orange, child: Icon(Icons.camera_alt_rounded, size: 14, color: Colors.white))),
                      ]),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text('${st['name'] ?? ''}', style: Theme.of(context).textTheme.titleLarge),
                        Text('${st['about'] ?? ''}'.isEmpty ? (canPost ? 'درباره‌ی خودت بنویس…' : '') : '${st['about']}', style: TextStyle(color: HsColors.muted, height: 1.6)),
                      ]),
                    ),
                    if (canPost)
                      IconButton(
                        tooltip: 'درباره‌ی من',
                        icon: const Icon(Icons.edit_rounded),
                        onPressed: () async {
                          final c = TextEditingController(text: '${st['about'] ?? ''}');
                          final t = await showDialog<String>(
                            context: context,
                            builder: (dd) => AlertDialog(
                              title: const Text('درباره‌ی من'),
                              content: TextField(controller: c, maxLines: 4, maxLength: 400, decoration: const InputDecoration(hintText: 'چه چیزهایی دوست داری؟')),
                              actions: [FilledButton(style: FilledButton.styleFrom(minimumSize: const Size(90, 44)), onPressed: () => Navigator.pop(dd, c.text), child: const Text('ذخیره'))],
                            ),
                          );
                          if (t == null) return;
                          await ref.read(apiProvider).post<Object?>('$_path/about', data: {'about': t});
                          ref.invalidate(getProvider(_path));
                        },
                      ),
                  ]),
                ),
              ),
              const SizedBox(height: 12),
              if (posts.isEmpty) const EmptyView(icon: Icons.photo_album_rounded, title: 'هنوز پستی نیست', text: 'از کارهایی که دوست داری عکس بگیر و این‌جا بگذار؛ روزی دو پست.'),
              for (final p in posts) Padding(padding: const EdgeInsets.only(bottom: 12), child: PostCard(p: p, path: _path, showStatus: true)),
            ]),
          ),
        );
      },
    );
  }
}

class PostCard extends ConsumerWidget {
  const PostCard({super.key, required this.p, required this.path, this.showStatus = false, this.teacher = false});
  final Map p;
  final String path;
  final bool showStatus;
  final bool teacher;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final api = ref.read(apiProvider);
    return Card(
      clipBehavior: Clip.antiAlias,
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        ListTile(
          leading: HsAvatar(name: '${p['student']}', url: '${p['avatar'] ?? ''}', size: 38),
          title: Text('${p['pinned'] == true ? '📌 ' : ''}${p['title']}', style: const TextStyle(fontWeight: FontWeight.w700)),
          subtitle: Text('${p['student']}  •  ${Fa.date('${p['at'] ?? ''}', withYear: false)}'),
          trailing: PopupMenuButton<String>(
            onSelected: (v) async {
              try {
                if (v == 'wall') {
                  await api.post<Object?>('/portfolio/${p['id']}/wall', data: {'on': p['on_wall'] != true});
                } else if (v == 'pin') {
                  await api.post<Object?>('/portfolio/${p['id']}/pin', data: {'on': p['pinned'] != true});
                } else if (v == 'delete') {
                  if (!await confirmDanger(context, title: 'حذف پست', text: 'این پست پاک می‌شود.')) return;
                  await api.delete<Object?>('/portfolio/${p['id']}');
                }
                ref.invalidate(getProvider(path));
              } on ApiException catch (e) {
                if (context.mounted) showMessage(context, e.message, error: true);
              }
            },
            itemBuilder: (_) => [
              if (teacher) PopupMenuItem(value: 'pin', child: Text(p['pinned'] == true ? 'برداشتن سنجاق' : '📌 سنجاق بالای دیوار کلاس')),
              if (teacher) PopupMenuItem(value: 'wall', child: Text(p['on_wall'] == true ? 'برداشتن از دیوار کلاس' : 'گذاشتن روی دیوار کلاس')),
              const PopupMenuItem(value: 'delete', child: Text('حذف', style: TextStyle(color: HsColors.red))),
            ],
          ),
        ),
        if ('${p['image'] ?? ''}'.isNotEmpty) Image.network('${p['image']}', fit: BoxFit.cover, errorBuilder: (_, _, _) => const SizedBox(height: 0)),
        if ('${p['text'] ?? ''}'.isNotEmpty) Padding(padding: const EdgeInsets.fromLTRB(16, 10, 16, 0), child: Text('${p['text']}', style: const TextStyle(height: 1.8))),
        Row(children: [
          TextButton.icon(
            onPressed: () async {
              await api.post<Object?>('/portfolio/${p['id']}/cheer');
              ref.invalidate(getProvider(path));
            },
            icon: Icon(p['cheered'] == true ? Icons.favorite_rounded : Icons.favorite_border_rounded, color: HsColors.red),
            label: Text(Fa.faDigits(p['cheers'] ?? 0)),
          ),
          const Spacer(),
          if (showStatus || teacher)
            Padding(
              padding: const EdgeInsetsDirectional.only(end: 12),
              child: Text(p['on_wall'] == true ? '🌍 روی دیوار کلاس' : '🔒 فقط معلم و خانواده', style: TextStyle(fontSize: 12, color: HsColors.faint)),
            ),
        ]),
      ]),
    );
  }
}

class _NewPostSheet extends ConsumerStatefulWidget {
  const _NewPostSheet({required this.studentId});
  final int studentId;
  @override
  ConsumerState<_NewPostSheet> createState() => _NewPostSheetState();
}

class _NewPostSheetState extends ConsumerState<_NewPostSheet> {
  final _t = TextEditingController();
  final _x = TextEditingController();
  XFile? _img;
  bool _busy = false;

  @override
  void dispose() {
    _t.dispose();
    _x.dispose();
    super.dispose();
  }

  Future<void> _pick(ImageSource s) async {
    final i = await ImagePicker().pickImage(source: s, maxWidth: 1600, imageQuality: 85);
    if (i != null) setState(() => _img = i);
  }

  Future<void> _save() async {
    if (_img == null && _t.text.trim().isEmpty && _x.text.trim().isEmpty) {
      showMessage(context, 'عکس بگذار یا چند کلمه بنویس.', error: true);
      return;
    }
    setState(() => _busy = true);
    try {
      final path = '/portfolio/student/${widget.studentId}';
      final f = {'title': _t.text.trim(), 'text': _x.text.trim(), 'type': _img != null ? 'photo' : 'writing'};
      if (_img != null) {
        await uploadImage(ref, path, _img!, f);
      } else {
        await ref.read(apiProvider).post<Object?>(path, data: f);
      }
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 0, 20, 20 + MediaQuery.of(context).viewInsets.bottom),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Text('امروز چه کار قشنگی کردی؟', style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: 10),
        Row(children: [
          Expanded(child: OutlinedButton.icon(onPressed: () => _pick(ImageSource.camera), icon: const Icon(Icons.photo_camera_rounded), label: const Text('دوربین'))),
          const SizedBox(width: 8),
          Expanded(child: OutlinedButton.icon(onPressed: () => _pick(ImageSource.gallery), icon: const Icon(Icons.photo_library_rounded), label: const Text('گالری'))),
        ]),
        if (_img != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text('✅ عکس انتخاب شد: ${_img!.name}', style: const TextStyle(color: HsColors.green))),
        const SizedBox(height: 10),
        TextField(controller: _t, maxLength: 120, decoration: const InputDecoration(labelText: 'عنوان', hintText: 'مثلاً نقاشی باغ')),
        TextField(controller: _x, maxLines: 3, maxLength: 1000, decoration: const InputDecoration(labelText: 'توضیح')),
        Text('پست تو اول فقط برای معلم و خانواده است؛ معلم اگر بخواهد روی دیوار کلاس می‌گذارد.', style: TextStyle(fontSize: 12, color: HsColors.faint, height: 1.7)),
        const SizedBox(height: 10),
        FilledButton(onPressed: _busy ? null : _save, child: _busy ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 3, color: Colors.white)) : const Text('ثبت پست')),
      ]),
    );
  }
}

/// دیوار کلاس (پست‌های تأییدشده) یا همه‌ی پست‌ها برای معلم
class WallView extends ConsumerWidget {
  const WallView({super.key, required this.classId, this.teacher = false});
  final int classId;
  final bool teacher;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final path = teacher ? '/classes/$classId/portfolio' : '/classes/$classId/wall';
    final res = ref.watch(getProvider(path));
    return RefreshIndicator(
      onRefresh: () async => ref.invalidate(getProvider(path)),
      child: res.when(
        loading: () => const LoadingView(),
        error: (e, _) => ListView(children: [const SizedBox(height: 80), ErrorView(error: e, onRetry: () => ref.invalidate(getProvider(path)))]),
        data: (r) {
          final posts = ((r.data as List?) ?? const []).cast<Map>();
          return ListView(padding: const EdgeInsets.fromLTRB(14, 12, 14, 32), children: [
            if (teacher)
              Padding(
                padding: EdgeInsets.only(bottom: 10),
                child: Text('پست‌های بچه‌ها اول فقط برای شما و خانواده‌شان است. از منوی ⋮ هر پست را روی دیوار کلاس بگذارید تا همه‌ی کلاس ببینند.', style: TextStyle(color: HsColors.muted, height: 1.7, fontSize: 13)),
              ),
            if (posts.isEmpty) const EmptyView(icon: Icons.photo_album_rounded, title: 'هنوز پستی نیست'),
            for (final p in posts) Padding(padding: const EdgeInsets.only(bottom: 12), child: PostCard(p: p, path: path, teacher: teacher)),
          ]);
        },
      ),
    );
  }
}

/// «دیوار کلاس سوم الف» (بدون تکرار «کلاس» اگر در نام هست)
String wallTitle(String classTitle) {
  final t = classTitle.trim();
  if (t.isEmpty) return 'دیوار کلاس';
  return t.startsWith('کلاس') ? 'دیوار $t' : 'دیوار کلاس $t';
}
