import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../core/api/api_exception.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../core/utils/fa.dart';
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
    try {
      final r = await ref.read(apiProvider).post<Map<String, dynamic>>('/children/join', data: {'code': code, 'child_name': _name.text.trim()});
      ref.invalidate(getProvider('/children'));
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder: (c) => AlertDialog(
          icon: Icon(r['status'] == 'linked' ? Icons.check_circle_rounded : Icons.hourglass_top_rounded, color: r['status'] == 'linked' ? HsColors.green : HsColors.orange, size: 48),
          content: Text('${r['message']}', textAlign: TextAlign.center, style: const TextStyle(height: 1.8)),
          actions: [FilledButton(style: FilledButton.styleFrom(minimumSize: const Size(90, 44)), onPressed: () => Navigator.pop(c), child: const Text('باشه'))],
        ),
      );
      if (mounted) Navigator.pop(context);
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
    // QR کلاس (لینک /join/…) نام فرزند لازم دارد؛ کد اتصال والد نه
    setState(() => _needName = raw.contains('/join/') || raw.toUpperCase().startsWith('K-'));
    if (!_needName) _submit();
  }

  Future<void> _scan() async {
    final raw = await Navigator.of(context).push<String>(MaterialPageRoute(builder: (_) => const _ScannerPage()));
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
            title: 'کد اتصال از معلم',
            icon: Icons.key_rounded,
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              const Text('کدی را که معلم برایتان فرستاده وارد کنید.', style: TextStyle(color: Colors.black54, height: 1.7)),
              const SizedBox(height: 10),
              Directionality(
                textDirection: TextDirection.ltr,
                child: TextField(
                  key: const Key('child_code'),
                  controller: _code,
                  textAlign: TextAlign.center,
                  textCapitalization: TextCapitalization.characters,
                  style: const TextStyle(fontSize: 22, letterSpacing: 3, fontWeight: FontWeight.w700),
                  decoration: const InputDecoration(hintText: 'ABCD1234'),
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

class _ScannerPage extends StatefulWidget {
  const _ScannerPage();
  @override
  State<_ScannerPage> createState() => _ScannerPageState();
}

class _ScannerPageState extends State<_ScannerPage> {
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
