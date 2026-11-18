import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../core/utils/fa.dart';
import '../../core/widgets/states.dart';
import '../auth/auth_controller.dart';

final onboardingInfoProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) async {
  final r = await ref.read(apiProvider).get<Map<String, dynamic>>('/onboarding', useCache: false);
  return r.data;
});

/// تکمیل ثبت‌نام: انتخاب نقش و چند سؤال کوتاه (همان فرم سایت)
class OnboardingScreen extends ConsumerStatefulWidget {
  const OnboardingScreen({super.key, this.change = false});

  /// از منو برای تغییر/تکمیل نقش باز شده (نه اجباری)
  final bool change;

  @override
  ConsumerState<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends ConsumerState<OnboardingScreen> {
  final _form = GlobalKey<FormState>();
  final _f = <String, TextEditingController>{};
  String? _role;
  String _relation = 'father';
  String? _province;
  bool _busy = false;

  TextEditingController c(String k) => _f.putIfAbsent(k, TextEditingController.new);

  @override
  void dispose() {
    for (final x in _f.values) {
      x.dispose();
    }
    super.dispose();
  }

  String v(String k) => Fa.enDigits(c(k).text.trim());

  Map<String, dynamic> _payload() {
    final p = <String, dynamic>{'role': _role};
    switch (_role) {
      case 'parent':
        final who = _relation == 'mother' ? 'mother' : 'father';
        p.addAll({
          'relation': _relation,
          '${who}_first': v('first'),
          '${who}_last': v('last'),
          'child_first': v('child_first'),
          'child_last': v('child_last'),
          'child_city': v('city'),
          'code': v('code'),
        });
      case 'teacher':
        p.addAll({'first_name': v('first'), 'last_name': v('last'), 'city': v('city'), 'school_name_t': v('school'), 'subject': v('subject')});
      case 'school_admin':
        p.addAll({
          'first_name': v('first'),
          'last_name': v('last'),
          'personnel_code': v('personnel'),
          'school_name_m': v('school'),
          'province_m': _province ?? '',
          'city_m': v('city'),
          'school_address_m': v('address'),
          'postal_code_m': v('postal'),
          'school_code': v('school_code'),
          'school_phone': v('school_phone'),
        });
      case 'student':
        p.addAll({'first_name': v('first'), 'last_name': v('last'), 'code': v('code').toUpperCase()});
    }
    return p;
  }

  Future<void> _submit() async {
    if (!(_form.currentState?.validate() ?? false)) return;
    setState(() => _busy = true);
    try {
      final r = await ref.read(apiProvider).post<Map<String, dynamic>>('/onboarding', data: _payload());
      await ref.read(authProvider.notifier).reloadMe();
      if (!mounted) return;
      showMessage(context, (r['message'] ?? 'ثبت شد.') as String);
      if (widget.change && Navigator.of(context).canPop()) Navigator.of(context).pop();
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  String? _req(String? s) => (s == null || s.trim().isEmpty) ? 'این مورد لازم است' : null;

  Widget _field(String k, String label, {bool required = true, TextInputType? type, String? Function(String?)? validator, String? hint}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextFormField(
        key: Key('onb_$k'),
        controller: c(k),
        keyboardType: type,
        decoration: InputDecoration(labelText: required ? '$label *' : label, hintText: hint),
        validator: validator ?? (required ? _req : null),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final info = ref.watch(onboardingInfoProvider);
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.change ? 'انتخاب نقش' : 'تکمیل ثبت‌نام'),
        actions: [
          if (!widget.change)
            IconButton(tooltip: 'خروج', onPressed: () => ref.read(authProvider.notifier).logout(), icon: const Icon(Icons.logout_rounded)),
        ],
      ),
      body: info.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(onboardingInfoProvider)),
        data: (d) => _body(d),
      ),
    );
  }

  Widget _body(Map<String, dynamic> d) {
    final roles = ((d['roles'] as List?) ?? const []).cast<Map>();
    final provinces = ((d['provinces'] as List?) ?? const []).map((e) => '$e').toList();
    return Form(
      key: _form,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 4, 20, 32),
        children: [
          const Text('شما کدام هستید؟', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
          const SizedBox(height: 10),
          for (final r in roles)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: _RoleCard(
                id: '${r['id']}',
                title: '${r['title']}',
                hint: '${r['hint']}',
                selected: _role == r['id'],
                onTap: () => setState(() => _role = '${r['id']}'),
              ),
            ),
          if (_role != null) ...[
            const Divider(height: 28),
            ..._roleFields(provinces),
            const SizedBox(height: 8),
            FilledButton(
              key: const Key('onb_submit'),
              onPressed: _busy ? null : _submit,
              child: _busy ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 3, color: Colors.white)) : const Text('ثبت و ادامه'),
            ),
          ],
        ],
      ),
    );
  }

  List<Widget> _roleFields(List<String> provinces) {
    switch (_role) {
      case 'parent':
        return [
          SegmentedButton<String>(
            segments: const [
              ButtonSegment(value: 'father', label: Text('پدر')),
              ButtonSegment(value: 'mother', label: Text('مادر')),
              ButtonSegment(value: 'parent', label: Text('سرپرست')),
            ],
            selected: {_relation},
            onSelectionChanged: (s) => setState(() => _relation = s.first),
          ),
          const SizedBox(height: 14),
          _field('first', 'نام شما'),
          _field('last', 'نام خانوادگی شما'),
          _field('child_first', 'نام فرزند'),
          _field('child_last', 'نام خانوادگی فرزند'),
          _field('city', 'شهر', required: false),
          _field('code', 'کد کلاس (اگر معلم داده)', required: false, hint: 'مثلاً K-AB12CD'),
          const Text('بدون کد هم می‌توانید ادامه دهید و بعداً فرزند را با اسکن QR کلاس اضافه کنید.', style: TextStyle(fontSize: 13, color: Colors.black54, height: 1.6)),
          const SizedBox(height: 12),
        ];
      case 'teacher':
        return [
          _field('first', 'نام'),
          _field('last', 'نام خانوادگی'),
          _field('city', 'شهر'),
          _field('school', 'نام مدرسه', required: false),
          _field('subject', 'درس/پایه', required: false),
        ];
      case 'school_admin':
        return [
          Container(
            padding: const EdgeInsets.all(12),
            margin: const EdgeInsets.only(bottom: 14),
            decoration: BoxDecoration(color: const Color(0xFFE8F0FB), borderRadius: BorderRadius.circular(14)),
            child: const Text('نقش مدیر مدرسه بعد از بررسی و تأیید مدیر سامانه فعال می‌شود. فهرست کادر را بعداً از سایت وارد کنید.', style: TextStyle(height: 1.7, color: HsColors.blue)),
          ),
          _field('first', 'نام'),
          _field('last', 'نام خانوادگی'),
          _field('personnel', 'کد پرسنلی', type: TextInputType.number, validator: (s) => Fa.enDigits(s ?? '').replaceAll(RegExp(r'\D'), '').length < 6 ? 'کد پرسنلی معتبر وارد کنید' : null),
          _field('school', 'نام مدرسه'),
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: DropdownButtonFormField<String>(
              key: const Key('onb_province'),
              initialValue: _province,
              isExpanded: true,
              decoration: const InputDecoration(labelText: 'استان *'),
              items: [for (final p in provinces) DropdownMenuItem(value: p, child: Text(p))],
              onChanged: (s) => setState(() => _province = s),
              validator: (s) => s == null ? 'استان را انتخاب کنید' : null,
            ),
          ),
          _field('city', 'شهر'),
          _field('address', 'آدرس مدرسه', validator: (s) => (s ?? '').trim().length < 8 ? 'آدرس کامل مدرسه را بنویسید' : null),
          _field('postal', 'کد پستی مدرسه', type: TextInputType.number, validator: (s) => Fa.enDigits(s ?? '').replaceAll(RegExp(r'\D'), '').length != 10 ? 'کد پستی باید ۱۰ رقم باشد' : null),
          _field('school_code', 'کد مدرسه', type: TextInputType.number, validator: (s) => RegExp(r'^\d{7}$').hasMatch(Fa.enDigits(s ?? '').trim()) ? null : 'کد مدرسه معتبر نیست'),
          _field('school_phone', 'تلفن مدرسه', required: false, type: TextInputType.phone),
        ];
      case 'student':
        return [
          _field('first', 'نام'),
          _field('last', 'نام خانوادگی'),
          _field('code', 'کد کلاس', hint: 'از معلمت بگیر'),
        ];
    }
    return const [];
  }
}

class _RoleCard extends StatelessWidget {
  const _RoleCard({required this.id, required this.title, required this.hint, required this.selected, required this.onTap});
  final String id;
  final String title;
  final String hint;
  final bool selected;
  final VoidCallback onTap;

  IconData get _icon => switch (id) {
        'parent' => Icons.family_restroom_rounded,
        'teacher' => Icons.co_present_rounded,
        'student' => Icons.backpack_rounded,
        _ => Icons.account_balance_rounded,
      };

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? const Color(0xFFFFEAD6) : Colors.white,
      borderRadius: BorderRadius.circular(18),
      child: InkWell(
        key: Key('role_$id'),
        borderRadius: BorderRadius.circular(18),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: selected ? HsColors.orange : const Color(0x14000000), width: selected ? 2 : 1),
          ),
          child: Row(children: [
            Icon(_icon, size: 34, color: selected ? HsColors.orange : HsColors.blue),
            const SizedBox(width: 14),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                Text(hint, style: const TextStyle(color: Colors.black54, fontSize: 13)),
              ]),
            ),
            if (selected) const Icon(Icons.check_circle_rounded, color: HsColors.orange),
          ]),
        ),
      ),
    );
  }
}
