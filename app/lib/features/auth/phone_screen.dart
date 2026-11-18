import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/utils/fa.dart';
import '../../core/widgets/states.dart';
import 'auth_controller.dart';

/// ورود با شماره موبایل
class PhoneScreen extends ConsumerStatefulWidget {
  const PhoneScreen({super.key});

  @override
  ConsumerState<PhoneScreen> createState() => _PhoneScreenState();
}

class _PhoneScreenState extends ConsumerState<PhoneScreen> {
  final _c = TextEditingController();
  String? _error;
  bool _busy = false;

  @override
  void dispose() {
    _c.dispose();
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(24, 32, 24, 24),
          children: [
            Center(child: Image.asset('assets/images/logo_mark.png', height: 110)),
            const SizedBox(height: 20),
            Text('ورود یا ثبت‌نام', textAlign: TextAlign.center, style: Theme.of(context).textTheme.headlineMedium),
            const SizedBox(height: 8),
            const Text('شماره موبایل خود را وارد کنید تا کد ورود برایتان پیامک شود.', textAlign: TextAlign.center, style: TextStyle(height: 1.7, color: Colors.black54)),
            const SizedBox(height: 28),
            Directionality(
              textDirection: TextDirection.ltr,
              child: TextField(
                key: const Key('phone_field'),
                controller: _c,
                keyboardType: TextInputType.phone,
                textAlign: TextAlign.center,
                autofocus: true,
                maxLength: 14,
                style: const TextStyle(fontSize: 22, letterSpacing: 2, fontWeight: FontWeight.w700),
                inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9۰-۹٠-٩+ ]'))],
                decoration: InputDecoration(
                  hintText: '۰۹۱۲ ۱۲۳ ۴۵۶۷',
                  counterText: '',
                  errorText: _error,
                  prefixIcon: const Icon(Icons.phone_iphone_rounded),
                ),
                onSubmitted: (_) => _submit(),
              ),
            ),
            const SizedBox(height: 20),
            FilledButton(
              key: const Key('phone_submit'),
              onPressed: _busy ? null : _submit,
              child: _busy ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 3, color: Colors.white)) : const Text('دریافت کد'),
            ),
            const SizedBox(height: 28),
            TextButton(
              key: const Key('kid_login'),
              style: TextButton.styleFrom(foregroundColor: Colors.black38),
              onPressed: () => context.push('/kid'),
              child: const Text('ورود دانش‌آموز با کارت کلاس', style: TextStyle(fontSize: 12)),
            ),
          ],
        ),
      ),
    );
  }
}
