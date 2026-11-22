import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../core/utils/fa.dart';
import '../../core/widgets/hs_avatar.dart';
import '../../core/widgets/states.dart';
import '../../core/widgets/ui.dart';

/// ثبت یا ویرایش اطلاعات مدرسه (مدیر مدرسه، حتی پیش از تأیید)
class SchoolFormScreen extends ConsumerStatefulWidget {
  const SchoolFormScreen({super.key});
  @override
  ConsumerState<SchoolFormScreen> createState() => _SchoolFormScreenState();
}

class _SchoolFormScreenState extends ConsumerState<SchoolFormScreen> {
  final _form = GlobalKey<FormState>();
  final _c = <String, TextEditingController>{};
  String? _province;
  String? _level;
  bool _filled = false;
  bool _busy = false;

  TextEditingController c(String k) => _c.putIfAbsent(k, TextEditingController.new);

  @override
  void dispose() {
    for (final x in _c.values) {
      x.dispose();
    }
    super.dispose();
  }

  void _fill(Map d) {
    if (_filled) return;
    _filled = true;
    final s = d['school'] as Map?;
    if (s == null) return;
    for (final k in ['name', 'city', 'address', 'postal', 'phone']) {
      c(k).text = '${s[k] ?? ''}';
    }
    c('code').text = '${s['code'] ?? ''}';
    _province = '${s['province'] ?? ''}'.isEmpty ? null : '${s['province']}';
    _level = '${s['level'] ?? ''}'.isEmpty ? null : '${s['level']}';
  }

  Future<void> _save() async {
    if (!(_form.currentState?.validate() ?? false)) return;
    setState(() => _busy = true);
    try {
      final r = await ref.read(apiProvider).post<Map<String, dynamic>>('/school/profile', data: {
        for (final k in ['name', 'city', 'address', 'postal', 'phone', 'code']) k: Fa.enDigits(c(k).text.trim()),
        'province': _province ?? '',
        'level': _level ?? '',
      });
      ref.invalidate(getProvider('/dashboard'));
      ref.invalidate(getProvider('/school'));
      if (!mounted) return;
      showMessage(context, r['approved'] == true ? 'اطلاعات مدرسه ذخیره شد ✅' : 'ذخیره شد. نقش مدیر مدرسه پس از تأیید مدیر سامانه فعال می‌شود.');
      Navigator.pop(context);
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  String? _req(String? s) => (s ?? '').trim().isEmpty ? 'این مورد لازم است' : null;

  @override
  Widget build(BuildContext context) {
    final res = ref.watch(getProvider('/school/profile'));
    return Scaffold(
      appBar: AppBar(title: const Text('اطلاعات مدرسه')),
      body: res.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(getProvider('/school/profile'))),
        data: (r) {
          final d = (r.data as Map?) ?? const {};
          _fill(d);
          if (d['can_edit'] != true) {
            return const EmptyView(icon: Icons.lock_outline_rounded, title: 'فقط مدیر مدرسه', text: 'اگر مدیر مدرسه هستید، از منو «تغییر نقش» را بزنید و «مدیر مدرسه» را انتخاب کنید.');
          }
          final provinces = ((d['provinces'] as List?) ?? const []).map((e) => '$e').toList();
          final levels = ((d['levels'] as Map?) ?? const {}).map((k, v) => MapEntry('$k', '$v'));
          return Form(
            key: _form,
            child: ListView(padding: const EdgeInsets.fromLTRB(20, 8, 20, 32), children: [
              if (d['approved'] != true)
                Container(
                  padding: const EdgeInsets.all(12),
                  margin: const EdgeInsets.only(bottom: 12),
                  decoration: BoxDecoration(color: const Color(0xFFE8F0FB), borderRadius: BorderRadius.circular(14)),
                  child: const Text('بعد از ثبت، نقش مدیر مدرسه با تأیید مدیر سامانه فعال می‌شود.', style: TextStyle(color: HsColors.blue, height: 1.7)),
                ),
              TextFormField(controller: c('name'), decoration: const InputDecoration(labelText: 'نام مدرسه *'), validator: _req),
              const SizedBox(height: 12),
              TextFormField(
                key: const Key('school_code'),
                controller: c('code'),
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'کد مدرسه *', helperText: 'شناسه‌ی رسمی مدرسه'),
                validator: _req,
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: provinces.contains(_province) ? _province : null,
                isExpanded: true,
                decoration: const InputDecoration(labelText: 'استان *'),
                items: [for (final p in provinces) DropdownMenuItem(value: p, child: Text(p))],
                onChanged: (v) => setState(() => _province = v),
                validator: (v) => v == null ? 'استان را انتخاب کنید' : null,
              ),
              const SizedBox(height: 12),
              TextFormField(controller: c('city'), decoration: const InputDecoration(labelText: 'شهر *'), validator: _req),
              const SizedBox(height: 12),
              TextFormField(controller: c('address'), maxLines: 2, decoration: const InputDecoration(labelText: 'آدرس *'), validator: (s) => (s ?? '').trim().length < 8 ? 'آدرس کامل را بنویسید' : null),
              const SizedBox(height: 12),
              TextFormField(controller: c('postal'), keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'کد پستی *'), validator: (s) => Fa.enDigits(s ?? '').replaceAll(RegExp(r'\D'), '').length != 10 ? 'کد پستی معتبر نیست' : null),
              const SizedBox(height: 12),
              TextFormField(controller: c('phone'), keyboardType: TextInputType.phone, decoration: const InputDecoration(labelText: 'تلفن مدرسه')),
              const SizedBox(height: 12),
              if (levels.isNotEmpty)
                DropdownButtonFormField<String>(
                  initialValue: levels.containsKey(_level) ? _level : null,
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'مقطع'),
                  items: [for (final e in levels.entries.where((e) => e.key.isNotEmpty)) DropdownMenuItem(value: e.key, child: Text(e.value))],
                  onChanged: (v) => setState(() => _level = v),
                ),
              const SizedBox(height: 20),
              FilledButton(onPressed: _busy ? null : _save, child: busyLabel(_busy, 'ذخیره')),
            ]),
          );
        },
      ),
    );
  }
}

/// افزودن معاون یا معلم به کادر
Future<void> addStaff(BuildContext context, WidgetRef ref) async {
  final prof = await ref.read(apiProvider).get<Map<String, dynamic>>('/school/profile', useCache: false);
  final positions = ((prof.data['positions'] as Map?) ?? const {}).map((k, v) => MapEntry('$k', v is Map ? '${v['title'] ?? k}' : '$v'));
  if (!context.mounted) return;
  final first = TextEditingController();
  final last = TextEditingController();
  final mobile = TextEditingController();
  var pos = positions.containsKey('teacher') ? 'teacher' : (positions.keys.isEmpty ? 'teacher' : positions.keys.first);
  final ok = await showModalBottomSheet<bool>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (c) => StatefulBuilder(
      builder: (c, set) => Padding(
        padding: EdgeInsets.fromLTRB(20, 0, 20, 20 + MediaQuery.of(c).viewInsets.bottom),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Text('افزودن به کادر', style: Theme.of(c).textTheme.titleLarge),
          const SizedBox(height: 10),
          if (positions.isNotEmpty)
            DropdownButtonFormField<String>(
              initialValue: pos,
              isExpanded: true,
              decoration: const InputDecoration(labelText: 'سمت'),
              items: [for (final e in positions.entries) DropdownMenuItem(value: e.key, child: Text(e.value))],
              onChanged: (v) => set(() => pos = v ?? pos),
            ),
          const SizedBox(height: 10),
          TextField(controller: first, decoration: const InputDecoration(labelText: 'نام')),
          const SizedBox(height: 10),
          TextField(controller: last, decoration: const InputDecoration(labelText: 'نام خانوادگی')),
          const SizedBox(height: 10),
          TextField(controller: mobile, keyboardType: TextInputType.phone, decoration: const InputDecoration(labelText: 'موبایل', helperText: 'اگر با این شماره عضو باشد، مستقیم به کادر اضافه می‌شود؛ وگرنه دعوت پیامکی می‌گیرد.', helperMaxLines: 2)),
          const SizedBox(height: 14),
          FilledButton(onPressed: () => Navigator.pop(c, true), child: const Text('افزودن')),
        ]),
      ),
    ),
  );
  if (ok != true) return;
  try {
    final r = await ref.read(apiProvider).post<Map<String, dynamic>>('/school/staff', data: {'first': first.text.trim(), 'last': last.text.trim(), 'mobile': Fa.enDigits(mobile.text.trim()), 'position': pos});
    ref.invalidate(getProvider('/school'));
    ref.invalidate(getProvider('/dashboard'));
    if (context.mounted) showMessage(context, '${r['message'] ?? 'ثبت شد.'}');
  } on ApiException catch (e) {
    if (context.mounted) showMessage(context, e.message, error: true);
  }
}

/// کلاس‌هایی که معلم‌ها برای اتصال به مدرسه فرستاده‌اند (برای مدیر)
class ClaimsCard extends ConsumerWidget {
  const ClaimsCard({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final res = ref.watch(getProvider('/school/claims'));
    final list = ((res.valueOrNull?.data as List?) ?? const []).cast<Map>();
    if (list.isEmpty) return const SizedBox.shrink();
    Future<void> answer(Map c, bool ok) async {
      try {
        await ref.read(apiProvider).post<Object?>('/school/claims/${c['id']}', data: ok ? {} : {'reject': true});
        ref.invalidate(getProvider('/school/claims'));
        ref.invalidate(getProvider('/school'));
        ref.invalidate(getProvider('/dashboard'));
      } on ApiException catch (e) {
        if (context.mounted) showMessage(context, e.message, error: true);
      }
    }

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: SectionCard(
        title: 'درخواست اتصال کلاس به مدرسه',
        icon: Icons.link_rounded,
        child: Column(children: [
          for (final c in list)
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text('${c['title']}'),
              subtitle: Text('${c['teacher']}  •  ${Fa.faDigits(c['students'] ?? 0)} دانش‌آموز'),
              trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                IconButton(tooltip: 'رد', onPressed: () => answer(c, false), icon: const Icon(Icons.close_rounded, color: HsColors.red)),
                IconButton(tooltip: 'تأیید', onPressed: () => answer(c, true), icon: const Icon(Icons.check_circle_rounded, color: HsColors.green)),
              ]),
            ),
        ]),
      ),
    );
  }
}

/// معلم: اتصال به مدرسه با کد مدرسه یا موبایل مدیر
Future<void> linkTeacherToSchool(BuildContext context, WidgetRef ref) async {
  final code = TextEditingController();
  final mm = TextEditingController();
  final ok = await showDialog<bool>(
    context: context,
    builder: (d) => AlertDialog(
      title: const Text('اتصال به مدرسه'),
      content: Column(mainAxisSize: MainAxisSize.min, children: [
        const Text('کد مدرسه را از مدیر بگیرید، یا موبایل مدیر را بنویسید. بعد از تأیید مدیر، کلاس‌هایتان به مدرسه وصل می‌شود.', style: TextStyle(height: 1.7, fontSize: 13)),
        const SizedBox(height: 10),
        TextField(controller: code, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'کد مدرسه')),
        const SizedBox(height: 8),
        Text('یا', style: TextStyle(color: HsColors.faint)),
        const SizedBox(height: 8),
        TextField(controller: mm, keyboardType: TextInputType.phone, decoration: const InputDecoration(labelText: 'موبایل مدیر مدرسه')),
      ]),
      actions: [
        TextButton(onPressed: () => Navigator.pop(d, false), child: const Text('انصراف')),
        FilledButton(style: FilledButton.styleFrom(minimumSize: const Size(90, 44)), onPressed: () => Navigator.pop(d, true), child: const Text('ارسال')),
      ],
    ),
  );
  if (ok != true) return;
  try {
    final r = await ref.read(apiProvider).post<Map<String, dynamic>>('/me/school', data: {'school_code': Fa.enDigits(code.text.trim()), 'manager_mobile': Fa.enDigits(mm.text.trim())});
    if (context.mounted) showMessage(context, '${r['message']}');
  } on ApiException catch (e) {
    if (context.mounted) showMessage(context, e.message, error: true);
  }
}

/// معلم: درخواست‌های اتصال والدینی که شماره‌شان در پرونده نبود
class RequestsScreen extends ConsumerWidget {
  const RequestsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final res = ref.watch(getProvider('/requests'));
    return Scaffold(
      appBar: AppBar(title: const Text('درخواست‌های اتصال والدین')),
      body: res.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(getProvider('/requests'))),
        data: (r) {
          final list = ((r.data as List?) ?? const []).cast<Map>();
          if (list.isEmpty) return const EmptyView(icon: Icons.inbox_rounded, title: 'درخواستی نیست');
          return ListView(padding: const EdgeInsets.all(14), children: [
            for (final q in list) Padding(padding: const EdgeInsets.only(bottom: 12), child: _RequestCard(q: q)),
          ]);
        },
      ),
    );
  }
}

class _RequestCard extends ConsumerStatefulWidget {
  const _RequestCard({required this.q});
  final Map q;
  @override
  ConsumerState<_RequestCard> createState() => _RequestCardState();
}

class _RequestCardState extends ConsumerState<_RequestCard> {
  int? _sid;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    final cands = ((widget.q['candidates'] as List?) ?? const []).cast<Map>();
    final name = '${widget.q['child_name']}'.replaceAll(' ', '');
    final hit = cands.where((c) => '${c['name']}'.replaceAll(' ', '') == name);
    _sid = hit.isEmpty ? null : (hit.first['id'] as num).toInt();
  }

  Future<void> _answer(bool ok) async {
    if (ok && _sid == null) {
      showMessage(context, 'دانش‌آموز را انتخاب کنید.', error: true);
      return;
    }
    setState(() => _busy = true);
    try {
      await ref.read(apiProvider).post<Object?>('/requests/${widget.q['id']}', data: {'approve': ok, 'student_id': _sid});
      ref.invalidate(getProvider('/requests'));
      ref.invalidate(getProvider('/dashboard'));
      if (mounted) showMessage(context, ok ? 'تأیید شد؛ والد وصل شد ✅' : 'رد شد.');
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final q = widget.q;
    final cands = ((q['candidates'] as List?) ?? const []).cast<Map>();
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Row(children: [
            HsAvatar(name: '${q['parent']}', size: 40),
            const SizedBox(width: 10),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('${q['parent']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                Text(Fa.faDigits('${q['parent_mobile']}'), style: TextStyle(color: HsColors.muted, fontSize: 12)),
              ]),
            ),
          ]),
          const SizedBox(height: 8),
          Text('والد «${q['child_name']}»${'${q['class']}'.isNotEmpty ? ' در ${q['class']}' : ''}', style: const TextStyle(height: 1.7)),
          const SizedBox(height: 8),
          DropdownButtonFormField<int>(
            initialValue: _sid,
            isExpanded: true,
            decoration: const InputDecoration(labelText: 'کدام دانش‌آموز؟'),
            items: [for (final c in cands) DropdownMenuItem(value: (c['id'] as num).toInt(), child: Text('${c['name']}  (${c['class']})'))],
            onChanged: (v) => setState(() => _sid = v),
          ),
          const SizedBox(height: 10),
          Row(children: [
            Expanded(child: FilledButton(onPressed: _busy ? null : () => _answer(true), child: const Text('تأیید'))),
            const SizedBox(width: 8),
            TextButton(onPressed: _busy ? null : () => _answer(false), child: const Text('رد', style: TextStyle(color: HsColors.red))),
          ]),
        ]),
      ),
    );
  }
}
