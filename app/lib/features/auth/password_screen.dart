import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme.dart';
import '../../core/widgets/states.dart';
import 'auth_controller.dart';

/// ساخت یا تغییر رمز عبور و ایمیل، برای ورود بدون پیامک
class PasswordScreen extends ConsumerStatefulWidget {
  const PasswordScreen({super.key});
  @override
  ConsumerState<PasswordScreen> createState() => _PasswordScreenState();
}

class _PasswordScreenState extends ConsumerState<PasswordScreen> {
  final _email = TextEditingController();
  final _cur = TextEditingController();
  final _p1 = TextEditingController();
  final _p2 = TextEditingController();
  bool _show = false;
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _email.text = ref.read(authProvider).me?.email ?? '';
  }

  @override
  void dispose() {
    for (final c in [_email, _cur, _p1, _p2]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    final p = _p1.text;
    String? err;
    if (p.length < 8 || !RegExp(r'[0-9]').hasMatch(p) || !RegExp(r'[A-Za-z]').hasMatch(p)) {
      err = 'رمز باید دست‌کم ۸ نویسه و شامل حرف انگلیسی و عدد باشد.';
    } else if (p != _p2.text) {
      err = 'تکرار رمز با خود رمز یکی نیست.';
    }
    if (err != null) {
      setState(() => _error = err);
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final msg = await ref.read(authProvider.notifier).setPassword(password: p, current: _cur.text, email: _email.text.trim());
      TextInput.finishAutofillContext();
      if (!mounted) return;
      showMessage(context, msg);
      Navigator.pop(context);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final me = ref.watch(authProvider).me;
    final has = me?.hasPassword ?? false;
    InputDecoration dec(String label, {IconData icon = Icons.lock_rounded}) => InputDecoration(
          labelText: label,
          prefixIcon: Icon(icon),
          suffixIcon: icon == Icons.lock_rounded ? IconButton(icon: Icon(_show ? Icons.visibility_off_rounded : Icons.visibility_rounded), onPressed: () => setState(() => _show = !_show)) : null,
        );
    return Scaffold(
      appBar: AppBar(title: const Text('رمز عبور و ایمیل')),
      body: AutofillGroup(
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(color: HsColors.blue.withValues(alpha: 0.08), borderRadius: BorderRadius.circular(16)),
              child: Text(
                'با ساختن رمز، دیگر لازم نیست هر بار منتظر پیامک بمانید. در صفحه‌ی ورود «رمز عبور» را بزنید و با شماره موبایل، ایمیل یا نام کاربری خود و همین رمز وارد شوید.',
                style: TextStyle(height: 1.8, color: HsColors.text87),
              ),
            ),
            const SizedBox(height: 16),
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.badge_rounded, color: HsColors.blue),
              title: const Text('نام کاربری شما'),
              subtitle: SelectableText(me?.username ?? '', textDirection: TextDirection.ltr, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
            ),
            if ((me?.mobile ?? '').isNotEmpty)
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.phone_iphone_rounded, color: HsColors.blue),
                title: const Text('شماره موبایل'),
                subtitle: Text(me!.mobile, textDirection: TextDirection.ltr, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
              ),
            const SizedBox(height: 8),
            TextField(
              key: const Key('pw_email'),
              controller: _email,
              keyboardType: TextInputType.emailAddress,
              textDirection: TextDirection.ltr,
              autofillHints: const [AutofillHints.email],
              decoration: dec('ایمیل (اختیاری)', icon: Icons.alternate_email_rounded),
            ),
            const SizedBox(height: 12),
            if (has) ...[
              TextField(key: const Key('pw_current'), controller: _cur, obscureText: !_show, textDirection: TextDirection.ltr, autofillHints: const [AutofillHints.password], decoration: dec('رمز فعلی')),
              Padding(
                padding: const EdgeInsets.only(top: 4, bottom: 8),
                child: Text('اگر رمز فعلی را فراموش کرده‌اید: خارج شوید، با کد پیامکی وارد شوید و تا ۱۵ دقیقه بدون رمز فعلی رمز تازه بسازید.', style: TextStyle(fontSize: 12, color: HsColors.muted, height: 1.6)),
              ),
            ],
            TextField(key: const Key('pw_new'), controller: _p1, obscureText: !_show, textDirection: TextDirection.ltr, autofillHints: const [AutofillHints.newPassword], decoration: dec(has ? 'رمز تازه' : 'رمز عبور')),
            const SizedBox(height: 12),
            TextField(
              key: const Key('pw_repeat'),
              controller: _p2,
              obscureText: !_show,
              textDirection: TextDirection.ltr,
              decoration: dec('تکرار رمز').copyWith(errorText: _error, errorMaxLines: 4, helperText: 'دست‌کم ۸ نویسه، با حرف انگلیسی و عدد'),
              onSubmitted: (_) => _save(),
            ),
            const SizedBox(height: 20),
            FilledButton(
              key: const Key('pw_save'),
              onPressed: _busy ? null : _save,
              child: _busy ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 3, color: Colors.white)) : const Text('ذخیره'),
            ),
          ],
        ),
      ),
    );
  }
}
