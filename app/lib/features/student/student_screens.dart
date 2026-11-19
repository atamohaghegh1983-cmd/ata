import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../core/api/api_exception.dart';
import '../../core/config.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../core/utils/fa.dart';
import '../../core/widgets/hs_avatar.dart';
import '../../core/widgets/states.dart';
import '../../core/widgets/attachments.dart';
import '../timetable/timetable.dart' show TodayTimetableCard;
import '../../core/widgets/ui.dart';
import '../auth/auth_controller.dart';

/// خانه‌ی دانش‌آموز: امتیازها و تکالیف خودش
class StudentHome extends ConsumerWidget {
  const StudentHome({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final res = ref.watch(getProvider('/student/summary'));
    return RefreshIndicator(
      color: HsColors.orange,
      onRefresh: () async => ref.invalidate(getProvider('/student/summary')),
      child: res.when(
        loading: () => const LoadingView(),
        error: (e, _) => ListView(children: [const SizedBox(height: 80), ErrorView(error: e, onRetry: () => ref.invalidate(getProvider('/student/summary')))]),
        data: (r) {
          final classes = ((((r.data as Map?)?['classes']) as List?) ?? const []).cast<Map>();
          return ListView(
            padding: const EdgeInsets.fromLTRB(14, 4, 14, 32),
            children: [
              if (r.fromCache) ...[OfflineBanner(savedAt: r.savedAt), const SizedBox(height: 8)],
              if (classes.isEmpty) const EmptyView(icon: Icons.class_rounded, title: 'هنوز در کلاسی نیستی'),
              for (final c in classes) ...[
                Container(
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(colors: [HsColors.orange, Color(0xFFFFB347)]),
                    borderRadius: BorderRadius.circular(24),
                  ),
                  child: Row(children: [
                    const Text('⭐', style: TextStyle(fontSize: 44)),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text('${c['title']}', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 16)),
                        Text('معلم: ${c['teacher']}', style: const TextStyle(color: Colors.white70, fontSize: 12)),
                      ]),
                    ),
                    Column(children: [
                      Text(Fa.faDigits(c['points_total'] ?? 0), style: const TextStyle(color: Colors.white, fontSize: 34, fontFamily: 'Lalezar')),
                      const Text('امتیاز من', style: TextStyle(color: Colors.white, fontSize: 12)),
                    ]),
                  ]),
                ),
                const SizedBox(height: 12),
                if (c == classes.first) ...[const TodayTimetableCard(), const SizedBox(height: 12)],
                SectionCard(
                  title: 'تکالیف من',
                  icon: Icons.menu_book_rounded,
                  child: Column(children: [
                    if (((c['homework'] as List?) ?? const []).isEmpty) const Text('تکلیفی نداری 🎉', style: TextStyle(color: Colors.black54)),
                    for (final h in ((c['homework'] as List?) ?? const []).cast<Map>())
                      ListTile(
                        contentPadding: EdgeInsets.zero,
                        leading: Icon(HwStatus.of('${h['status']}') == HwStatus.done ? Icons.check_circle_rounded : Icons.radio_button_unchecked_rounded, color: HwStatus.of('${h['status']}').color),
                        title: Text('${h['title']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                        subtitle: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text([
                            if ('${h['subject'] ?? ''}'.isNotEmpty) '${h['subject']}',
                            if ('${h['due_date'] ?? ''}'.isNotEmpty && '${h['due_date']}' != 'null') 'تا ${Fa.weekday(DateTime.parse('${h['due_date']}'))} ${Fa.date('${h['due_date']}', withYear: false)}',
                            if ('${h['feedback'] ?? ''}'.isNotEmpty) 'معلم: ${h['feedback']}',
                          ].join('  •  ')),
                          if (((h['attachments'] as List?) ?? const []).isNotEmpty) AttachmentChips(items: ((h['attachments'] as List?) ?? const []).cast<Map>()),
                        ]),
                        trailing: Pill(HwStatus.of('${h['status']}').label, color: HwStatus.of('${h['status']}').color),
                      ),
                  ]),
                ),
                const SizedBox(height: 12),
                SectionCard(
                  title: 'امتیازهای اخیر',
                  icon: Icons.stars_rounded,
                  child: Column(children: [
                    if (((c['points_recent'] as List?) ?? const []).isEmpty) const Text('هنوز امتیازی نگرفته‌ای.', style: TextStyle(color: Colors.black54)),
                    for (final p in ((c['points_recent'] as List?) ?? const []).cast<Map>())
                      ListTile(
                        dense: true,
                        contentPadding: EdgeInsets.zero,
                        leading: Text('${p['icon'] ?? '⭐'}', style: const TextStyle(fontSize: 22)),
                        title: Text('${p['behavior']}'),
                        subtitle: Text(Fa.date('${p['at'] ?? ''}', withYear: false)),
                        trailing: Text('${(p['points'] as num) > 0 ? '+' : ''}${Fa.faDigits(p['points'])}',
                            style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16, color: (p['points'] as num) < 0 ? HsColors.red : HsColors.green)),
                      ),
                  ]),
                ),
                const SizedBox(height: 18),
              ],
            ],
          );
        },
      ),
    );
  }
}

/// ورود دانش‌آموز: کارت کلاس (QR یا کد) ← انتخاب نام ← رمز ۴ رقمی
class KidLoginScreen extends ConsumerStatefulWidget {
  const KidLoginScreen({super.key});
  @override
  ConsumerState<KidLoginScreen> createState() => _KidLoginScreenState();
}

class _KidLoginScreenState extends ConsumerState<KidLoginScreen> {
  final _code = TextEditingController();
  String _cardCode = '';
  Map? _class;
  List<Map> _students = const [];
  Map? _me;
  String _pin = '';
  bool _busy = false;

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _loadClass(String raw) async {
    setState(() => _busy = true);
    try {
      final r = await ref.read(apiProvider).post<Map<String, dynamic>>('/auth/kid/class', data: {'code': raw}, auth: false);
      setState(() {
        _cardCode = raw;
        _class = r['class'] as Map?;
        _students = ((r['students'] as List?) ?? const []).cast<Map>();
      });
      if (_students.isEmpty && mounted) {
        showMessage(context, 'ورود با کارت برای این کلاس هنوز باز نشده. از پدر و مادرت بخواه در اپ برایت رمز بگذارند.', error: true);
      }
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _scan() async {
    final raw = await Navigator.of(context).push<String>(MaterialPageRoute(builder: (_) => const _KidScanner()));
    if (raw != null && raw.isNotEmpty) await _loadClass(raw);
  }

  Future<void> _gallery() async {
    final img = await ImagePicker().pickImage(source: ImageSource.gallery);
    if (img == null) return;
    final ctrl = MobileScannerController();
    try {
      final raw = (await ctrl.analyzeImage(img.path))?.barcodes.firstOrNull?.rawValue;
      if (raw == null) {
        if (mounted) showMessage(context, 'در این عکس QR پیدا نشد.', error: true);
        return;
      }
      await _loadClass(raw);
    } finally {
      await ctrl.dispose();
    }
  }

  Future<void> _tapKey(String k) async {
    if (_busy) return;
    HapticFeedback.selectionClick();
    setState(() {
      if (k == 'del') {
        if (_pin.isNotEmpty) _pin = _pin.substring(0, _pin.length - 1);
      } else if (_pin.length < 4) {
        _pin += k;
      }
    });
    if (_pin.length == 4) await _login();
  }

  Future<void> _login() async {
    setState(() => _busy = true);
    try {
      final api = ref.read(apiProvider);
      final d = await api.post<Map<String, dynamic>>('/auth/kid/login', data: {'code': _cardCode, 'student_id': _me!['id'], 'pin': _pin, 'device': AppConfig.deviceName}, auth: false);
      await api.saveTokens(d);
      await ref.read(authProvider.notifier).reloadMe();
    } on ApiException catch (e) {
      setState(() => _pin = '');
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('ورود دانش‌آموز')),
      body: _me != null ? _pinView() : (_class != null ? _namesView() : _cardView()),
    );
  }

  Widget _cardView() {
    return ListView(padding: const EdgeInsets.fromLTRB(24, 16, 24, 24), children: [
      const Center(child: Text('🎒', style: TextStyle(fontSize: 64))),
      const SizedBox(height: 8),
      Text('کارت کلاست را اسکن کن', textAlign: TextAlign.center, style: Theme.of(context).textTheme.headlineSmall),
      const SizedBox(height: 20),
      FilledButton.icon(onPressed: _busy ? null : _scan, icon: const Icon(Icons.qr_code_scanner_rounded), label: const Text('اسکن QR کارت کلاس')),
      const SizedBox(height: 10),
      OutlinedButton.icon(onPressed: _busy ? null : _gallery, icon: const Icon(Icons.photo_library_rounded), label: const Text('انتخاب عکس QR')),
      const SizedBox(height: 24),
      const Text('یا کد ۶ حرفی کارت را بزن:', textAlign: TextAlign.center, style: TextStyle(color: Colors.black54)),
      const SizedBox(height: 8),
      Directionality(
        textDirection: TextDirection.ltr,
        child: TextField(
          controller: _code,
          textAlign: TextAlign.center,
          maxLength: 6,
          textCapitalization: TextCapitalization.characters,
          style: const TextStyle(fontSize: 24, letterSpacing: 6, fontWeight: FontWeight.w700),
          decoration: const InputDecoration(counterText: '', hintText: 'ABC123'),
          onSubmitted: (v) => _loadClass(v),
        ),
      ),
      const SizedBox(height: 10),
      OutlinedButton(onPressed: _busy ? null : () => _loadClass(_code.text), child: _busy ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 3)) : const Text('ادامه')),
    ]);
  }

  Widget _namesView() {
    return Column(children: [
      Padding(
        padding: const EdgeInsets.all(16),
        child: Text('🏫 ${_class!['title']}\nاسمت را پیدا کن:', textAlign: TextAlign.center, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700, height: 1.8)),
      ),
      Expanded(
        child: GridView.count(
          crossAxisCount: 3,
          padding: const EdgeInsets.fromLTRB(14, 0, 14, 20),
          mainAxisSpacing: 10,
          crossAxisSpacing: 10,
          children: [
            for (final s in _students)
              Material(
                color: Colors.white,
                borderRadius: BorderRadius.circular(18),
                child: InkWell(
                  borderRadius: BorderRadius.circular(18),
                  onTap: s['locked'] == true ? () => showMessage(context, 'فعلاً قفل است؛ کمی بعد امتحان کن.', error: true) : () => setState(() => _me = s),
                  child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                    HsAvatar(name: '${s['name']}', url: '${s['avatar'] ?? ''}', size: 54),
                    const SizedBox(height: 6),
                    Text('${s['name']}', textAlign: TextAlign.center, style: const TextStyle(fontWeight: FontWeight.w700)),
                  ]),
                ),
              ),
          ],
        ),
      ),
      TextButton(onPressed: () => setState(() => _class = null), child: const Text('کارت دیگر')),
    ]);
  }

  Widget _pinView() {
    const keys = ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'del', '0', ''];
    return ListView(padding: const EdgeInsets.fromLTRB(24, 16, 24, 24), children: [
      Center(child: HsAvatar(name: '${_me!['name']}', url: '${_me!['avatar'] ?? ''}', size: 84)),
      const SizedBox(height: 10),
      Text('سلام ${_me!['name']}!', textAlign: TextAlign.center, style: Theme.of(context).textTheme.headlineSmall),
      const Text('رمز ۴ رقمی‌ات را بزن', textAlign: TextAlign.center, style: TextStyle(color: Colors.black54)),
      const SizedBox(height: 18),
      Row(mainAxisAlignment: MainAxisAlignment.center, children: [
        for (var i = 0; i < 4; i++)
          Container(
            width: 20,
            height: 20,
            margin: const EdgeInsets.symmetric(horizontal: 8),
            decoration: BoxDecoration(shape: BoxShape.circle, color: i < _pin.length ? HsColors.orange : const Color(0xFFE5E5E5)),
          ),
      ]),
      const SizedBox(height: 22),
      Directionality(
        textDirection: TextDirection.ltr,
        child: GridView.count(
          crossAxisCount: 3,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          mainAxisSpacing: 12,
          crossAxisSpacing: 12,
          childAspectRatio: 1.5,
          children: [
            for (final k in keys)
              k.isEmpty
                  ? const SizedBox()
                  : Material(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(18),
                      child: InkWell(
                        borderRadius: BorderRadius.circular(18),
                        onTap: () => _tapKey(k),
                        child: Center(child: k == 'del' ? const Icon(Icons.backspace_outlined) : Text(Fa.faDigits(k), style: const TextStyle(fontSize: 26, fontWeight: FontWeight.w700))),
                      ),
                    ),
          ],
        ),
      ),
      const SizedBox(height: 12),
      if (_busy) const Center(child: CircularProgressIndicator(color: HsColors.orange)),
      TextButton(onPressed: () => setState(() { _me = null; _pin = ''; }), child: const Text('من نیستم')),
    ]);
  }
}

class _KidScanner extends StatefulWidget {
  const _KidScanner();
  @override
  State<_KidScanner> createState() => _KidScannerState();
}

class _KidScannerState extends State<_KidScanner> {
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
      appBar: AppBar(backgroundColor: Colors.black, foregroundColor: Colors.white, title: const Text('اسکن کارت کلاس', style: TextStyle(color: Colors.white))),
      body: Stack(children: [
        MobileScanner(
          controller: _ctrl,
          onDetect: (cap) {
            final v = cap.barcodes.firstOrNull?.rawValue;
            if (_done || v == null) return;
            _done = true;
            Navigator.pop(context, v);
          },
          errorBuilder: (c, e) => const Center(child: Text('دوربین باز نشد. اجازه‌ی دوربین را روشن کن.', style: TextStyle(color: Colors.white))),
        ),
        Center(child: Container(width: 240, height: 240, decoration: BoxDecoration(border: Border.all(color: HsColors.orange, width: 4), borderRadius: BorderRadius.circular(24)))),
      ]),
    );
  }
}
