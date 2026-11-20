import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:url_launcher/url_launcher.dart';

import '../api/api_exception.dart';
import '../providers.dart';
import '../theme.dart';
import 'states.dart';

/// باز کردن لینک یا فایل در مرورگر/برنامه‌ی مناسب گوشی
Future<void> openUrl(BuildContext context, String url) async {
  final u = Uri.tryParse(url);
  if (u == null || !(u.scheme == 'https' || u.scheme == 'http')) {
    showMessage(context, 'لینک معتبر نیست.', error: true);
    return;
  }
  final ok = await launchUrl(u, mode: LaunchMode.externalApplication);
  if (!ok && context.mounted) showMessage(context, 'باز کردن فایل ممکن نشد.', error: true);
}

IconData attachmentIcon(String type) => switch (type) {
      'image' => Icons.image_rounded,
      'pdf' => Icons.picture_as_pdf_rounded,
      'audio' => Icons.audiotrack_rounded,
      'video' => Icons.movie_rounded,
      'link' => Icons.link_rounded,
      _ => Icons.attach_file_rounded,
    };

/// فهرست پیوست‌ها به‌شکل دکمه (با حذف اختیاری)
class AttachmentChips extends StatelessWidget {
  const AttachmentChips({super.key, required this.items, this.onRemove});
  final List<Map> items;
  final void Function(int index)? onRemove;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) return const SizedBox.shrink();
    return Wrap(spacing: 6, runSpacing: 6, children: [
      for (final (i, a) in items.indexed)
        InputChip(
          avatar: Icon(attachmentIcon('${a['type']}'), size: 18, color: HsColors.blue),
          label: Text('${a['name'] ?? 'پیوست'}', overflow: TextOverflow.ellipsis),
          onPressed: () => openUrl(context, '${a['url']}'),
          onDeleted: onRemove == null ? null : () => onRemove!(i),
        ),
    ]);
  }
}

/// دکمه‌های افزودن پیوست: عکس (دوربین/گالری)، فایل، لینک ← بارگذاری روی سایت و برگرداندن {url,name,type}
class AttachmentAdder extends ConsumerStatefulWidget {
  const AttachmentAdder({super.key, required this.onAdded, this.max = 5, this.count = 0});
  final ValueChanged<Map<String, dynamic>> onAdded;
  final int max;
  final int count;
  @override
  ConsumerState<AttachmentAdder> createState() => _AttachmentAdderState();
}

class _AttachmentAdderState extends ConsumerState<AttachmentAdder> {
  bool _busy = false;

  Future<void> _upload(MultipartFile f) async {
    setState(() => _busy = true);
    try {
      final r = await ref.read(apiProvider).post<Map<String, dynamic>>('/uploads', data: FormData.fromMap({'file': f}));
      widget.onAdded(r);
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _photo() async {
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
    if (src == null) return;
    final img = await ImagePicker().pickImage(source: src, maxWidth: 2000, imageQuality: 85);
    if (img == null) return;
    await _upload(await MultipartFile.fromFile(img.path, filename: img.name));
  }

  Future<void> _file() async {
    final res = await FilePicker.pickFiles(type: FileType.custom, allowedExtensions: const ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'mp3', 'm4a', 'mp4']);
    final f = res.firstOrNull;
    if (f == null) return;
    await _upload(MultipartFile.fromBytes(await f.readAsBytes(), filename: f.name));
  }

  Future<void> _link() async {
    final c = TextEditingController();
    final t = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (d) => AlertDialog(
        title: const Text('افزودن لینک'),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          TextField(controller: c, keyboardType: TextInputType.url, textDirection: TextDirection.ltr, decoration: const InputDecoration(labelText: 'آدرس (https://…)')),
          const SizedBox(height: 8),
          TextField(controller: t, decoration: const InputDecoration(labelText: 'عنوان (اختیاری)')),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(d, false), child: const Text('انصراف')),
          FilledButton(style: FilledButton.styleFrom(minimumSize: const Size(90, 44)), onPressed: () => Navigator.pop(d, true), child: const Text('افزودن')),
        ],
      ),
    );
    final url = c.text.trim();
    if (ok != true || url.isEmpty) return;
    final u = Uri.tryParse(url.startsWith('http') ? url : 'https://$url');
    if (u == null || u.host.isEmpty) {
      if (mounted) showMessage(context, 'آدرس معتبر نیست.', error: true);
      return;
    }
    widget.onAdded({'url': u.toString(), 'name': t.text.trim().isEmpty ? u.host : t.text.trim(), 'type': 'link'});
  }

  @override
  Widget build(BuildContext context) {
    final full = widget.count >= widget.max;
    if (_busy) return const Padding(padding: EdgeInsets.all(8), child: Row(children: [SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)), SizedBox(width: 10), Text('در حال بارگذاری…')]));
    return Wrap(spacing: 6, children: [
      ActionChip(key: const Key('att_photo'), avatar: const Icon(Icons.photo_camera_rounded, size: 18), label: const Text('عکس'), onPressed: full ? null : _photo),
      ActionChip(key: const Key('att_file'), avatar: const Icon(Icons.attach_file_rounded, size: 18), label: const Text('فایل'), onPressed: full ? null : _file),
      ActionChip(key: const Key('att_link'), avatar: const Icon(Icons.link_rounded, size: 18), label: const Text('لینک'), onPressed: full ? null : _link),
    ]);
  }
}
