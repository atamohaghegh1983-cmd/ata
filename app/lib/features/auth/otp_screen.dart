import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

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
    _timer?.cancel();
    _c.dispose();
    _focus.dispose();
    super.dispose();
  }

  Future<void> _verify() async {
    final code = Fa.enDigits(_c.text);
    if (code.length != _len) return;
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
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _resend() async {
    try {
      final wait = await ref.read(authProvider.notifier).requestOtp(widget.mobile);
      _start(wait);
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
            Text('کد ۵ رقمی پیامک‌شده به ${Fa.faDigits(widget.mobile)} را وارد کنید.', textAlign: TextAlign.center, style: const TextStyle(height: 1.7, color: Colors.black54)),
            TextButton(onPressed: () => context.pop(), child: const Text('ویرایش شماره')),
            const SizedBox(height: 16),
            GestureDetector(
              onTap: () => _focus.requestFocus(),
              child: Stack(
                alignment: Alignment.center,
                children: [
                  // فیلد واقعی (نامرئی) برای صفحه‌کلید و پر شدن خودکار
                  Opacity(
                    opacity: 0,
                    child: TextField(
                      key: const Key('otp_field'),
                      controller: _c,
                      focusNode: _focus,
                      autofocus: true,
                      keyboardType: TextInputType.number,
                      maxLength: _len,
                      autofillHints: const [AutofillHints.oneTimeCode],
                      inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9۰-۹٠-٩]'))],
                      onChanged: (v) {
                        setState(() {});
                        if (Fa.enDigits(v).length == _len) _verify();
                      },
                    ),
                  ),
                  Directionality(
                    textDirection: TextDirection.ltr,
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: List.generate(_len, (i) {
                        final t = Fa.enDigits(_c.text);
                        final filled = i < t.length;
                        final active = i == t.length && _focus.hasFocus;
                        return Container(
                          width: 54,
                          height: 62,
                          margin: const EdgeInsets.symmetric(horizontal: 5),
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(14),
                            border: Border.all(color: _error != null ? HsColors.red : (active ? HsColors.orange : const Color(0x22000000)), width: active ? 2 : 1),
                          ),
                          child: Text(filled ? Fa.faDigits(t[i]) : '', style: const TextStyle(fontSize: 26, fontWeight: FontWeight.w700)),
                        );
                      }),
                    ),
                  ),
                ],
              ),
            ),
            if (_error != null) ...[
              const SizedBox(height: 12),
              Text(_error!, textAlign: TextAlign.center, style: const TextStyle(color: HsColors.red, height: 1.6)),
            ],
            const SizedBox(height: 24),
            if (_busy) const Center(child: CircularProgressIndicator(color: HsColors.orange)),
            if (!_busy)
              Center(
                child: _left > 0
                    ? Text('ارسال دوباره تا ${Fa.faDigits('$mm:$ss')}', style: const TextStyle(color: Colors.black54))
                    : TextButton.icon(onPressed: _resend, icon: const Icon(Icons.refresh_rounded), label: const Text('ارسال دوباره‌ی کد')),
              ),
          ],
        ),
      ),
    );
  }
}
