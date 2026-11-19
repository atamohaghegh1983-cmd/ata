import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:share_plus/share_plus.dart';

import '../../core/api/api_exception.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../core/utils/fa.dart';
import '../../core/utils/sheet_reader.dart';
import '../../core/widgets/ui.dart';
import '../../core/widgets/states.dart';
import 'class_providers.dart';

/// افزودن گروهی دانش‌آموزان از فایل اکسل (xlsx) یا CSV
class ImportStudentsScreen extends ConsumerStatefulWidget {
  const ImportStudentsScreen({super.key, required this.classId});
  final int classId;
  @override
  ConsumerState<ImportStudentsScreen> createState() => _ImportStudentsScreenState();
}

class _ImportStudentsScreenState extends ConsumerState<ImportStudentsScreen> {
  List<Map<String, String>> _rows = [];
  String _file = '';
  bool _busy = false;

  Future<void> _pick() async {
    try {
      final res = await FilePicker.pickFiles(type: FileType.custom, allowedExtensions: const ['xlsx', 'csv']);
      final f = res.firstOrNull;
      if (f == null) return;
      final bytes = await f.readAsBytes();
      final rows = SheetReader.students(SheetReader.read(bytes, f.name));
      setState(() {
        _rows = rows;
        _file = f.name;
      });
      if (rows.isEmpty && mounted) showMessage(context, 'در این فایل نام دانش‌آموزی پیدا نشد. از فایل نمونه استفاده کنید.', error: true);
    } on FormatException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    } catch (_) {
      if (mounted) showMessage(context, 'خواندن فایل ممکن نشد. فایل را در اکسل با قالب xlsx ذخیره کنید.', error: true);
    }
  }

  Future<void> _template() async {
    await SharePlus.instance.share(ShareParams(
      files: [XFile.fromData(SheetReader.template(), mimeType: 'text/csv', name: 'hamshagerdi-students.csv')],
      fileNameOverrides: const ['hamshagerdi-students.csv'],
      text: 'فایل نمونه‌ی فهرست دانش‌آموزان همشاگردی',
    ));
  }

  Future<void> _save() async {
    setState(() => _busy = true);
    try {
      final r = await ref.read(apiProvider).post<Map<String, dynamic>>('/classes/${widget.classId}/students/import', data: {'rows': _rows});
      ref.invalidate(classStudentsProvider(widget.classId));
      ref.invalidate(classProvider(widget.classId));
      ref.invalidate(myClassesProvider);
      if (!mounted) return;
      final errs = ((r['errors'] as List?) ?? const []).cast<Map>();
      await showDialog<void>(
        context: context,
        builder: (c) => AlertDialog(
          icon: const Icon(Icons.check_circle_rounded, color: HsColors.green, size: 48),
          title: Text('${Fa.faDigits(r['added'] ?? 0)} دانش‌آموز اضافه شد'),
          content: SingleChildScrollView(
            child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Text('والدینی که موبایلشان ثبت شده، با ورود به اپ خودکار وصل می‌شوند.', style: TextStyle(height: 1.8)),
              if (errs.isNotEmpty) ...[
                const SizedBox(height: 10),
                const Text('این ردیف‌ها ثبت نشد:', style: TextStyle(fontWeight: FontWeight.w700, color: HsColors.red)),
                for (final e in errs) Text('• ${e['name']}: ${e['message']}', style: const TextStyle(fontSize: 13, height: 1.7)),
              ],
            ]),
          ),
          actions: [FilledButton(style: FilledButton.styleFrom(minimumSize: const Size(90, 44)), onPressed: () => Navigator.pop(c), child: const Text('باشه'))],
        ),
      );
      if (mounted) Navigator.pop(context);
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final bad = _rows.where((r) => r['parent_phone']!.isNotEmpty && Fa.mobile(r['parent_phone']!) == null).length;
    return Scaffold(
      appBar: AppBar(title: const Text('افزودن از اکسل')),
      bottomNavigationBar: _rows.isEmpty
          ? null
          : SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: FilledButton(key: const Key('import_save'), onPressed: _busy ? null : _save, child: busyLabel(_busy, 'ثبت ${Fa.faDigits(_rows.length)} دانش‌آموز')),
              ),
            ),
      body: ListView(padding: const EdgeInsets.fromLTRB(16, 8, 16, 24), children: [
        SectionCard(
          title: 'راهنما',
          icon: Icons.info_outline_rounded,
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Text('ستون‌ها: نام، نام خانوادگی، موبایل والد (اختیاری: نام والد، شماره دانش‌آموز). ردیف اول عنوان ستون‌ها باشد. حداکثر ۱۵۰ نفر در هر بار.', style: TextStyle(height: 1.8, color: HsColors.text87)),
            const SizedBox(height: 10),
            OutlinedButton.icon(onPressed: _template, icon: const Icon(Icons.download_rounded), label: const Text('دریافت فایل نمونه')),
          ]),
        ),
        const SizedBox(height: 12),
        FilledButton.tonalIcon(key: const Key('import_pick'), onPressed: _busy ? null : _pick, icon: const Icon(Icons.upload_file_rounded), label: Text(_file.isEmpty ? 'انتخاب فایل اکسل' : 'فایل دیگر')),
        if (_file.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 6), child: Text(_file, textAlign: TextAlign.center, style: TextStyle(color: HsColors.faint, fontSize: 12))),
        const SizedBox(height: 12),
        if (_rows.isEmpty && _file.isNotEmpty) const EmptyView(icon: Icons.table_chart_rounded, title: 'ردیفی پیدا نشد'),
        if (bad > 0) Padding(padding: const EdgeInsets.only(bottom: 8), child: Text('${Fa.faDigits(bad)} شماره موبایل درست نیست (قرمز)؛ آن دانش‌آموزان بدون اتصال خودکار ثبت می‌شوند.', style: const TextStyle(color: HsColors.red, fontSize: 13, height: 1.7))),
        for (final (i, r) in _rows.indexed)
          Card(
            margin: const EdgeInsets.only(bottom: 6),
            child: ListTile(
              dense: true,
              leading: CircleAvatar(radius: 14, backgroundColor: const Color(0xFFFFE3CC), child: Text(Fa.faDigits(i + 1), style: const TextStyle(fontSize: 12, color: HsColors.orangeDark))),
              title: Text('${r['first_name']} ${r['last_name']}', style: const TextStyle(fontWeight: FontWeight.w700)),
              subtitle: r['parent_phone']!.isEmpty
                  ? Text('بدون موبایل والد', style: TextStyle(color: HsColors.faint2))
                  : Text(Fa.faDigits(r['parent_phone']!), style: TextStyle(color: Fa.mobile(r['parent_phone']!) == null ? HsColors.red : HsColors.muted)),
              trailing: IconButton(icon: const Icon(Icons.close_rounded, size: 18), onPressed: () => setState(() => _rows = [..._rows]..removeAt(i))),
            ),
          ),
      ]),
    );
  }
}
