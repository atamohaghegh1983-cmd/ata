import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/config.dart';
import '../../core/theme.dart';
import '../../core/utils/fa.dart';
import '../../core/widgets/states.dart';
import 'auth_controller.dart';

/// ورود: با کد پیامکی (موبایل) یا با رمز (موبایل، ایمیل یا نام کاربری)
class PhoneScreen extends ConsumerStatefulWidget {
  const PhoneScreen({super.key});

  @override
  ConsumerState<PhoneScreen> createState() => _PhoneScreenState();
}

class _PhoneScreenState extends ConsumerState<PhoneScreen> {
  final _c = TextEditingController();
  final _login = TextEditingController();
  final _pass = TextEditingController();
  bool _password = false;
  bool _show = false;
  String? _error;
  bool _busy = false;

  @override
  void dispose() {
    _c.dispose();
    _login.dispose();
    _pass.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final m = Fa.mobile(_c.text);
    if (m == null) {
      setState(() => _error = 'شماره موبایل درست نیست. مثال: ۰۹۱۲۱۲۳۴۵۶۷');
      return;
    }
    setState(() {
      _error = null;
      _busy = true;
    });
    try {
      final wait = await ref.read(authProvider.notifier).requestOtp(m);
      if (mounted) context.push('/login/code', extra: {'mobile': m, 'wait': wait});
    } on ApiException catch (e) {
      // کد قبلاً فرستاده شده: به صفحه‌ی کد برو
      if (e.code == 'hs_otp_cooldown' && mounted) {
        context.push('/login/code', extra: {'mobile': m, 'wait': 60});
      } else if (mounted) {
        showMessage(context, e.message, error: true);
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _submitPassword() async {
    final login = Fa.enDigits(_login.text.trim());
    if (login.isEmpty || _pass.text.isEmpty) {
      setState(() => _error = 'شماره موبایل، ایمیل یا نام کاربری و رمز را وارد کنید.');
      return;
    }
    setState(() {
      _error = null;
      _busy = true;
    });
    try {
      await ref.read(authProvider.notifier).loginPassword(login, _pass.text);
      TextInput.finishAutofillContext();
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final dark = HsColors.dark;
    return Scaffold(
      body: SafeArea(
        child: ListView(
          padding: EdgeInsets.zero,
          children: [
            // سربرگ رنگی با لوگو؛ متن روی زمینه‌ی ساده قرار می‌گیرد، نه روی تصویر
            Container(
              padding: const EdgeInsets.fromLTRB(24, 28, 24, 28),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topRight,
                  end: Alignment.bottomLeft,
                  colors: AppConfig.isTeacherApp ? const [Color(0xFF16213E), Color(0xFF2C3E73)] : const [HsColors.orange, Color(0xFFFFA94D)],
                ),
                borderRadius: const BorderRadius.vertical(bottom: Radius.circular(32)),
              ),
              child: Column(children: [
                Container(
                  width: 120,
                  height: 120,
                  padding: const EdgeInsets.all(8),
                  decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle, boxShadow: [BoxShadow(color: Color(0x33000000), blurRadius: 16, offset: Offset(0, 6))]),
                  child: Image.asset('assets/images/logo_mark.png', fit: BoxFit.contain),
                ),
                const SizedBox(height: 14),
                Text(AppConfig.appName, style: const TextStyle(fontFamily: 'Lalezar', fontSize: 30, color: Colors.white)),
                Text(AppConfig.isTeacherApp ? 'ویژه‌ی معلم، مدیر و کادر مدرسه' : 'ویژه‌ی والدین و دانش‌آموزان', style: const TextStyle(color: Colors.white, fontSize: 13)),
              ]),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 22, 20, 24),
              child: AutofillGroup(
                child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  Text('ورود یا ثبت‌نام', textAlign: TextAlign.center, style: Theme.of(context).textTheme.headlineSmall),
                  const SizedBox(height: 14),
                  SegmentedButton<bool>(
                    showSelectedIcon: false,
                    segments: const [
                      ButtonSegment(value: false, icon: Icon(Icons.sms_rounded), label: Text('کد پیامکی')),
                      ButtonSegment(value: true, icon: Icon(Icons.password_rounded), label: Text('رمز عبور', key: Key('mode_password'))),
                    ],
                    selected: {_password},
                    onSelectionChanged: (v) => setState(() {
                      _password = v.first;
                      _error = null;
                    }),
                  ),
                  const SizedBox(height: 18),
                  if (!_password) ...[
                    Text('شماره موبایل خود را وارد کنید تا کد ورود برایتان پیامک شود.', style: TextStyle(height: 1.7, color: HsColors.muted)),
                    const SizedBox(height: 10),
                    TextField(
                      key: const Key('phone_field'),
                      controller: _c,
                      keyboardType: TextInputType.phone,
                      textDirection: TextDirection.ltr,
                      textAlign: TextAlign.left,
                      autofocus: true,
                      maxLength: 14,
                      autofillHints: const [AutofillHints.telephoneNumber],
                      style: TextStyle(fontSize: 20, letterSpacing: 1.5, fontWeight: FontWeight.w700, color: HsColors.text87),
                      inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9۰-۹٠-٩+ ]'))],
                      decoration: InputDecoration(
                        labelText: 'شماره موبایل',
                        helperText: 'مثال: ۰۹۱۲۱۲۳۴۵۶۷',
                        counterText: '',
                        errorText: _error,
                        prefixIcon: const Icon(Icons.phone_iphone_rounded),
                      ),
                      onSubmitted: (_) => _submit(),
                    ),
                    const SizedBox(height: 16),
                    FilledButton(
                      key: const Key('phone_submit'),
                      onPressed: _busy ? null : _submit,
                      child: _busy ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 3, color: Colors.white)) : const Text('دریافت کد'),
                    ),
                  ] else ...[
                    Text('اگر قبلاً از منوی اپ «رمز عبور و ایمیل» را ساخته‌اید، با آن وارد شوید.', style: TextStyle(height: 1.7, color: HsColors.muted)),
                    const SizedBox(height: 10),
                    TextField(
                      key: const Key('login_field'),
                      controller: _login,
                      textDirection: TextDirection.ltr,
                      textAlign: TextAlign.left,
                      keyboardType: TextInputType.emailAddress,
                      autofillHints: const [AutofillHints.username, AutofillHints.email],
                      textInputAction: TextInputAction.next,
                      decoration: const InputDecoration(labelText: 'شماره موبایل، ایمیل یا نام کاربری', prefixIcon: Icon(Icons.person_rounded)),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      key: const Key('password_field'),
                      controller: _pass,
                      obscureText: !_show,
                      textDirection: TextDirection.ltr,
                      textAlign: TextAlign.left,
                      autofillHints: const [AutofillHints.password],
                      decoration: InputDecoration(
                        labelText: 'رمز عبور',
                        errorText: _error,
                        errorMaxLines: 4,
                        prefixIcon: const Icon(Icons.lock_rounded),
                        suffixIcon: IconButton(icon: Icon(_show ? Icons.visibility_off_rounded : Icons.visibility_rounded), onPressed: () => setState(() => _show = !_show)),
                      ),
                      onSubmitted: (_) => _submitPassword(),
                    ),
                    const SizedBox(height: 16),
                    FilledButton(
                      key: const Key('password_submit'),
                      onPressed: _busy ? null : _submitPassword,
                      child: _busy ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 3, color: Colors.white)) : const Text('ورود'),
                    ),
                    TextButton(
                      onPressed: () => setState(() {
                        _password = false;
                        _error = null;
                      }),
                      child: const Text('رمز را فراموش کرده‌ام؛ با کد پیامکی وارد می‌شوم'),
                    ),
                  ],
                  if (!AppConfig.isTeacherApp) ...[
                    const SizedBox(height: 18),
                    Divider(color: dark ? Colors.white24 : null),
                    TextButton.icon(
                      key: const Key('kid_login'),
                      onPressed: () => context.push('/kid'),
                      icon: const Icon(Icons.qr_code_2_rounded),
                      label: const Text('ورود دانش‌آموز با کارت کلاس'),
                    ),
                  ],
                ]),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
