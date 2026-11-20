import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../core/api/api_exception.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../core/utils/fa.dart';
import '../../core/widgets/hs_avatar.dart';
import '../../core/widgets/states.dart';
import '../../core/widgets/ui.dart';

/// افزودن فرزند: کد اتصال والد (از معلم)، اسکن QR کلاس، یا عکس QR از گالری
class AddChildScreen extends ConsumerStatefulWidget {
  const AddChildScreen({super.key});
  @override
  ConsumerState<AddChildScreen> createState() => _AddChildScreenState();
}

class _AddChildScreenState extends ConsumerState<AddChildScreen> {
  final _code = TextEditingController();
  final _name = TextEditingController();
  bool _needName = false;
  bool _busy = false;

  @override
  void dispose() {
    _code.dispose();
    _name.dispose();
    super.dispose();
  }

  bool _isClassCode(String c) => c.contains('/join/') || c.toUpperCase().startsWith('K-') || c.contains('://');

  Future<void> _done(Map<String, dynamic> r) async {
    ref.invalidate(getProvider('/children'));
    ref.invalidate(getProvider('/dashboard'));
    if (!mounted) return;
    final linked = r['status'] == 'linked';
    await showDialog<void>(
      context: context,
      builder: (c) => AlertDialog(
        icon: Icon(linked ? Icons.check_circle_rounded : Icons.hourglass_top_rounded, color: linked ? HsColors.green : HsColors.orange, size: 48),
        content: Text('${r['message']}', textAlign: TextAlign.center, style: const TextStyle(height: 1.8)),
        actions: [FilledButton(style: FilledButton.styleFrom(minimumSize: const Size(90, 44)), onPressed: () => Navigator.pop(c), child: const Text('باشه'))],
      ),
    );
    if (mounted) Navigator.pop(context);
  }

  Future<void> _submit() async {
    final code = Fa.enDigits(_code.text.trim());
    if (code.length < 4) {
      showMessage(context, 'کد را وارد کنید یا QR را اسکن کنید.', error: true);
      return;
    }
    if (_needName && _name.text.trim().length < 3) {
      showMessage(context, 'نام و نام خانوادگی فرزند را بنویسید.', error: true);
      return;
    }
    setState(() => _busy = true);
    final api = ref.read(apiProvider);
    try {
      if (_isClassCode(code) && !_needName) {
        // کد کلاس: فهرست بچه‌های کلاس ← انتخاب فرزند
        final l = await api.post<Map<String, dynamic>>('/join/students', data: {'code': code});
        if (!mounted) return;
        final kids = ((l['students'] as List?) ?? const []).cast<Map>();
        final cls = (l['class'] as Map?) ?? const {};
        final pick = await showModalBottomSheet<Object>(
          context: context,
          isScrollControlled: true,
          showDragHandle: true,
          builder: (c) => SizedBox(
            height: MediaQuery.of(c).size.height * 0.75,
            child: ListView(padding: const EdgeInsets.fromLTRB(16, 0, 16, 24), children: [
              Text('${cls['title'] ?? 'کلاس'}', textAlign: TextAlign.center, style: Theme.of(c).textTheme.titleLarge),
              if ('${cls['teacher'] ?? ''}'.isNotEmpty) Text('معلم: ${cls['teacher']}', textAlign: TextAlign.center, style: TextStyle(color: HsColors.muted)),
              const SizedBox(height: 8),
              const Text('فرزندتان کدام است؟', textAlign: TextAlign.center, style: TextStyle(fontWeight: FontWeight.w700)),
              const SizedBox(height: 8),
              for (final k in kids)
                Card(
                  child: ListTile(
                    key: Key('pick_${k['id']}'),
                    leading: HsAvatar(name: '${k['name']}', url: '${k['avatar'] ?? ''}', size: 40),
                    title: Text('${k['name']}'),
                    trailing: k['mine'] == true ? const Icon(Icons.check_circle_rounded, color: HsColors.green) : null,
                    onTap: () => Navigator.pop(c, k),
                  ),
                ),
              const SizedBox(height: 8),
              TextButton(onPressed: () => Navigator.pop(c, 'other'), child: const Text('نام فرزندم در فهرست نیست')),
            ]),
          ),
        );
        if (pick == null || !mounted) return;
        if (pick == 'other') {
          setState(() => _needName = true);
          showMessage(context, 'نام و نام خانوادگی فرزند را بنویسید و «اتصال» را بزنید؛ درخواست برای معلم می‌رود.');
          return;
        }
        final r = await api.post<Map<String, dynamic>>('/children/join-class', data: {'code': code, 'student_id': (pick as Map)['id']});
        await _done(r);
        return;
      }
      final r = await api.post<Map<String, dynamic>>('/children/join', data: {'code': code, 'child_name': _name.text.trim()});
      await _done(r);
    } on ApiException catch (e) {
      if (e.code == 'hs_need_name') {
        setState(() => _needName = true);
      }
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _gotCode(String raw) {
    _code.text = raw.trim();
    setState(() => _needName = false);
    _submit();
  }

  Future<void> _scan() async {
    final raw = await Navigator.of(context).push<String>(MaterialPageRoute(builder: (_) => const ScannerPage()));
    if (raw != null && raw.isNotEmpty) _gotCode(raw);
  }

  Future<void> _fromGallery() async {
    final img = await ImagePicker().pickImage(source: ImageSource.gallery);
    if (img == null) return;
    final ctrl = MobileScannerController();
    try {
      final cap = await ctrl.analyzeImage(img.path);
      final raw = cap?.barcodes.firstOrNull?.rawValue;
      if (raw == null || raw.isEmpty) {
        if (mounted) showMessage(context, 'در این عکس QR پیدا نشد. عکس واضح‌تری انتخاب کنید.', error: true);
        return;
      }
      _gotCode(raw);
    } catch (_) {
      if (mounted) showMessage(context, 'خواندن عکس ممکن نشد.', error: true);
    } finally {
      await ctrl.dispose();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('افزودن فرزند')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
        children: [
          SectionCard(
            title: 'کد کلاس یا کد اتصال',
            icon: Icons.key_rounded,
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Text('کد کلاس (مثل K-AB12CD) یا کد اتصالی را که معلم فرستاده وارد کنید. اگر معلم شماره‌ی شما را ثبت کرده باشد، فوراً وصل می‌شوید.', style: TextStyle(color: HsColors.muted, height: 1.7)),
              const SizedBox(height: 10),
              Directionality(
                textDirection: TextDirection.ltr,
                child: TextField(
                  key: const Key('child_code'),
                  controller: _code,
                  textAlign: TextAlign.center,
                  textCapitalization: TextCapitalization.characters,
                  style: const TextStyle(fontSize: 22, letterSpacing: 3, fontWeight: FontWeight.w700),
                  decoration: const InputDecoration(hintText: 'K-AB12CD'),
                ),
              ),
              if (_needName) ...[
                const SizedBox(height: 10),
                TextField(key: const Key('child_name'), controller: _name, decoration: const InputDecoration(labelText: 'نام و نام خانوادگی فرزند')),
              ],
              const SizedBox(height: 12),
              FilledButton(key: const Key('child_join'), onPressed: _busy ? null : _submit, child: busyLabel(_busy, 'اتصال')),
            ]),
          ),
          const SizedBox(height: 14),
          SectionCard(
            title: 'QR کلاس',
            icon: Icons.qr_code_2_rounded,
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              OutlinedButton.icon(onPressed: _busy ? null : _scan, icon: const Icon(Icons.qr_code_scanner_rounded), label: const Text('اسکن با دوربین')),
              const SizedBox(height: 8),
              OutlinedButton.icon(onPressed: _busy ? null : _fromGallery, icon: const Icon(Icons.photo_library_rounded), label: const Text('انتخاب عکس QR از گالری')),
            ]),
          ),
        ],
      ),
    );
  }
}

class ScannerPage extends StatefulWidget {
  const ScannerPage({super.key});
  @override
  State<ScannerPage> createState() => _ScannerPageState();
}

class _ScannerPageState extends State<ScannerPage> {
  final _ctrl = MobileScannerController(formats: const [BarcodeFormat.qrCode]);
  bool _done = false;

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(backgroundColor: Colors.black, foregroundColor: Colors.white, title: const Text('اسکن QR', style: TextStyle(color: Colors.white))),
      body: Stack(children: [
        MobileScanner(
          controller: _ctrl,
          onDetect: (cap) {
            final v = cap.barcodes.firstOrNull?.rawValue;
            if (_done || v == null || v.isEmpty) return;
            _done = true;
            Navigator.pop(context, v);
          },
          errorBuilder: (c, e) => const Center(
            child: Padding(
              padding: EdgeInsets.all(24),
              child: Text('دوربین باز نشد. اجازه‌ی دوربین را در تنظیمات گوشی برای همشاگردی روشن کنید.', textAlign: TextAlign.center, style: TextStyle(color: Colors.white, height: 1.8)),
            ),
          ),
        ),
        Center(
          child: Container(
            width: 240,
            height: 240,
            decoration: BoxDecoration(border: Border.all(color: HsColors.orange, width: 4), borderRadius: BorderRadius.circular(24)),
          ),
        ),
      ]),
    );
  }
}

/// اسکن QR کلاس با دوربین یا از عکس گالری؛ متن QR یا null
Future<String?> scanQrCode(BuildContext context) async {
  final src = await showModalBottomSheet<String>(
    context: context,
    showDragHandle: true,
    builder: (c) => SafeArea(
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        ListTile(key: const Key('scan_camera'), leading: const Icon(Icons.qr_code_scanner_rounded), title: const Text('اسکن با دوربین'), onTap: () => Navigator.pop(c, 'camera')),
        ListTile(leading: const Icon(Icons.photo_library_rounded), title: const Text('انتخاب عکس QR از گالری'), onTap: () => Navigator.pop(c, 'gallery')),
      ]),
    ),
  );
  if (src == null || !context.mounted) return null;
  if (src == 'camera') {
    return Navigator.of(context).push<String>(MaterialPageRoute(builder: (_) => const ScannerPage()));
  }
  final img = await ImagePicker().pickImage(source: ImageSource.gallery);
  if (img == null) return null;
  final ctrl = MobileScannerController();
  try {
    final cap = await ctrl.analyzeImage(img.path);
    final raw = cap?.barcodes.firstOrNull?.rawValue;
    if ((raw == null || raw.isEmpty) && context.mounted) showMessage(context, 'در این عکس QR پیدا نشد. عکس واضح‌تری انتخاب کنید.', error: true);
    return raw;
  } catch (_) {
    if (context.mounted) showMessage(context, 'خواندن عکس ممکن نشد.', error: true);
    return null;
  } finally {
    await ctrl.dispose();
  }
}

/// «K-AB12CD» از متن QR (لینک /join/K-AB12CD/ یا خود کد)
String classCodeFrom(String raw) {
  final m = RegExp(r'K-?[A-Za-z0-9]{6}', caseSensitive: false).firstMatch(raw);
  if (m == null) return raw.trim();
  final c = m[0]!.toUpperCase().replaceAll('-', '');
  return 'K-${c.substring(1)}';
}
