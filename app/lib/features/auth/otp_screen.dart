import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:smart_auth/smart_auth.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme.dart';
import '../../core/utils/fa.dart';
import '../../core/widgets/states.dart';
import 'auth_controller.dart';

/// کد ۵ رقمی با تایمر ارسال دوباره
class OtpScreen extends ConsumerStatefulWidget {
  const OtpScreen({super.key, required this.mobile, this.wait = 60});
  final String mobile;
  final int wait;

  @override
  ConsumerState<OtpScreen> createState() => _OtpScreenState();
}

class _OtpScreenState extends ConsumerState<OtpScreen> {
  static const _len = 5;
  final _c = TextEditingController();
  final _focus = FocusNode();
  Timer? _timer;
  int _left = 0;
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _start(widget.wait);
    _listenSms();
  }

  /// خواندن خودکار کد از پیامک (اندروید: پنجره‌ی «اجازه‌ی خواندن این پیامک» نشان داده می‌شود)
  Future<void> _listenSms() async {
    try {
      final r = await SmartAuth.instance.getSmsWithUserConsentApi(matcher: r'[0-9۰-۹]{5}');
      final code = r.data?.code;
      if (!mounted || code == null) return;
      _c.text = Fa.enDigits(code);
      _verify();
    } catch (_) {
      // روی دستگاه بدون سرویس گوگل: کاربر دستی وارد می‌کند
    }
  }

  void _start(int s) {
    _timer?.cancel();
    setState(() => _left = s);
    _timer = Timer.periodic(const Duration(seconds: 1), (t) {
      if (!mounted) return;
      setState(() => _left = _left > 0 ? _left - 1 : 0);
      if (_left == 0) t.cancel();
    });
  }

  @override
  void dispose() {
    SmartAuth.instance.removeUserConsentApiListener().ignore();
    _timer?.cancel();
    _c.dispose();
    _focus.dispose();
    super.dispose();
  }

  Future<void> _verify() async {
    final code = Fa.enDigits(_c.text);
    if (code.length != _len) {
      setState(() => _error = 'کد ۵ رقمی را کامل وارد کنید.');
      return;
    }
    if (_busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref.read(authProvider.notifier).verifyOtp(widget.mobile, code);
      // مسیریاب خودش به خانه یا تکمیل ثبت‌نام می‌برد
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
      _c.clear();
      _focus.requestFocus();
      SystemChannels.textInput.invokeMethod<void>('TextInput.show');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _resend() async {
    try {
      final wait = await ref.read(authProvider.notifier).requestOtp(widget.mobile);
      _start(wait);
      _listenSms();
      if (mounted) showMessage(context, 'کد تازه فرستاده شد.');
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final mm = (_left ~/ 60).toString();
    final ss = (_left % 60).toString().padLeft(2, '0');
    return Scaffold(
      appBar: AppBar(),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(24, 8, 24, 24),
          children: [
            Text('کد تأیید', textAlign: TextAlign.center, style: Theme.of(context).textTheme.headlineMedium),
            const SizedBox(height: 8),
            Text('کد ۵ رقمی پیامک‌شده به ${Fa.faDigits(widget.mobile)} را وارد کنید.', textAlign: TextAlign.center, style: TextStyle(height: 1.7, color: HsColors.muted)),
            TextButton(onPressed: () => context.pop(), child: const Text('ویرایش شماره')),
            const SizedBox(height: 16),
            // خانه‌ی معمولی و قابل‌دیدن: با هر لمس صفحه‌کلید باز می‌شود و «چسباندن» هم کار می‌کند
            Directionality(
              textDirection: TextDirection.ltr,
              child: TextField(
                key: const Key('otp_field'),
                controller: _c,
                focusNode: _focus,
                autofocus: true,
                enabled: !_busy,
                keyboardType: TextInputType.number,
                textInputAction: TextInputAction.done,
                textAlign: TextAlign.center,
                maxLength: _len,
                autofillHints: const [AutofillHints.oneTimeCode],
                style: const TextStyle(fontSize: 32, fontWeight: FontWeight.w700, letterSpacing: 18),
                inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9۰-۹٠-٩]'))],
                decoration: InputDecoration(
                  counterText: '',
                  hintText: '-----',
                  hintStyle: TextStyle(fontSize: 32, letterSpacing: 18, color: HsColors.line),
                  errorText: _error,
                  errorMaxLines: 3,
                  contentPadding: const EdgeInsets.symmetric(vertical: 18),
                ),
                onTap: () => SystemChannels.textInput.invokeMethod<void>('TextInput.show'),
                onChanged: (v) {
                  if (_error != null) setState(() => _error = null);
                  if (Fa.enDigits(v).length == _len) _verify();
                },
                onSubmitted: (_) => _verify(),
              ),
            ),
            const SizedBox(height: 14),
            FilledButton(
              key: const Key('otp_submit'),
              onPressed: _busy ? null : _verify,
              child: const Text('تأیید و ورود'),
            ),
            const SizedBox(height: 24),
            if (_busy) const Center(child: CircularProgressIndicator(color: HsColors.orange)),
            if (!_busy)
              Center(
                child: _left > 0
                    ? Text('ارسال دوباره تا ${Fa.faDigits('$mm:$ss')}', style: TextStyle(color: HsColors.muted))
                    : TextButton.icon(onPressed: _resend, icon: const Icon(Icons.refresh_rounded), label: const Text('ارسال دوباره‌ی کد')),
              ),
          ],
        ),
      ),
    );
  }
}
