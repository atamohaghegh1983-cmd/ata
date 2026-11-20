import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../core/utils/fa.dart';
import '../../core/widgets/hs_avatar.dart';
import '../../core/widgets/states.dart';
import '../../core/widgets/ui.dart';
import '../auth/auth_controller.dart';
import '../portfolio/portfolio.dart';
import 'parent_lock.dart';

/// پنجره‌ی رمز ۴ رقمی؛ برمی‌گرداند رمز واردشده یا null
Future<String?> askPin(BuildContext context, {required String title, String hint = ''}) {
  final c = TextEditingController();
  return showDialog<String>(
    context: context,
    builder: (d) => AlertDialog(
      title: Text(title),
      content: Column(mainAxisSize: MainAxisSize.min, children: [
        if (hint.isNotEmpty) Text(hint, style: TextStyle(height: 1.7, color: HsColors.muted)),
        const SizedBox(height: 10),
        Directionality(
          textDirection: TextDirection.ltr,
          child: TextField(
            key: const Key('pin_field'),
            controller: c,
            autofocus: true,
            obscureText: true,
            maxLength: 4,
            keyboardType: TextInputType.number,
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 28, letterSpacing: 14),
            inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9۰-۹]'))],
            decoration: const InputDecoration(counterText: ''),
            onSubmitted: (_) => Navigator.pop(d, Fa.enDigits(c.text)),
          ),
        ),
      ]),
      actions: [
        TextButton(onPressed: () => Navigator.pop(d), child: const Text('انصراف')),
        FilledButton(style: FilledButton.styleFrom(minimumSize: const Size(90, 44)), onPressed: () => Navigator.pop(d, Fa.enDigits(c.text)), child: const Text('تأیید')),
      ],
    ),
  );
}

/// تنظیم، تغییر یا حذف رمز والدین
Future<void> parentPinSettings(BuildContext context, WidgetRef ref) async {
  final lock = ref.read(parentLockProvider);
  final n = ref.read(parentLockProvider.notifier);
  if (lock.hasPin) {
    final cur = await askPin(context, title: 'رمز فعلی والدین');
    if (cur == null || !context.mounted) return;
    if (!await n.check(cur)) {
      if (context.mounted) showMessage(context, 'رمز درست نیست.', error: true);
      return;
    }
    if (!context.mounted) return;
    final act = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (c) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          ListTile(leading: const Icon(Icons.password_rounded), title: const Text('تغییر رمز'), onTap: () => Navigator.pop(c, 'change')),
          ListTile(leading: const Icon(Icons.lock_open_rounded, color: HsColors.red), title: const Text('برداشتن رمز'), onTap: () => Navigator.pop(c, 'remove')),
        ]),
      ),
    );
    if (act == 'remove') {
      await n.removePin();
      if (context.mounted) showMessage(context, 'رمز والدین برداشته شد.');
      return;
    }
    if (act != 'change' || !context.mounted) return;
  }
  final p1 = await askPin(context, title: 'رمز ۴ رقمی والدین', hint: 'با این رمز، فرزندتان در «حالت کودک» نمی‌تواند وارد پیام‌ها، گزارش‌ها و تنظیمات شما شود. اگر فراموش کنید، با خروج و ورود دوباره با پیامک پاک می‌شود.');
  if (p1 == null || !context.mounted) return;
  if (!RegExp(r'^\d{4}$').hasMatch(p1)) {
    showMessage(context, 'رمز باید ۴ رقم باشد.', error: true);
    return;
  }
  final p2 = await askPin(context, title: 'تکرار رمز');
  if (p2 == null || !context.mounted) return;
  if (p1 != p2) {
    showMessage(context, 'دو رمز یکی نیستند.', error: true);
    return;
  }
  await n.setPin(p1);
  if (context.mounted) showMessage(context, 'رمز والدین فعال شد ✅');
}

/// شروع حالت کودک: انتخاب فرزند
Future<void> startKidMode(BuildContext context, WidgetRef ref) async {
  final kids = ((ref.read(getProvider('/children')).valueOrNull?.data as List?) ?? const []).cast<Map>();
  if (kids.isEmpty) {
    showMessage(context, 'اول فرزندتان را وصل کنید.', error: true);
    return;
  }
  Map? kid = kids.length == 1 ? kids.first : null;
  kid ??= await showModalBottomSheet<Map>(
    context: context,
    showDragHandle: true,
    builder: (c) => SafeArea(
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        const Padding(padding: EdgeInsets.all(8), child: Text('گوشی را به کدام فرزند می‌سپارید؟', style: TextStyle(fontWeight: FontWeight.w700))),
        for (final k in kids)
          ListTile(leading: HsAvatar(name: '${k['name']}', url: '${k['avatar'] ?? ''}', size: 40), title: Text('${k['name']}'), onTap: () => Navigator.pop(c, k)),
      ]),
    ),
  );
  if (kid == null) return;
  if (!ref.read(parentLockProvider).hasPin && context.mounted) {
    final setNow = await showDialog<bool>(
      context: context,
      builder: (d) => AlertDialog(
        title: const Text('رمز والدین'),
        content: const Text('برای اینکه فرزندتان نتواند از حالت کودک بیرون بیاید، رمز والدین بگذارید (اختیاری).', style: TextStyle(height: 1.8)),
        actions: [
          TextButton(onPressed: () => Navigator.pop(d, false), child: const Text('بدون رمز')),
          FilledButton(style: FilledButton.styleFrom(minimumSize: const Size(110, 44)), onPressed: () => Navigator.pop(d, true), child: const Text('گذاشتن رمز')),
        ],
      ),
    );
    if (setNow == true && context.mounted) await parentPinSettings(context, ref);
  }
  await ref.read(parentLockProvider.notifier).enterKidMode((kid['id'] as num).toInt(), '${kid['name']}');
}

/// خانه‌ی حالت کودک روی گوشی والد: امتیاز، تکالیف، پورتفولیو
class KidModeScreen extends ConsumerWidget {
  const KidModeScreen({super.key});

  Future<void> _exit(BuildContext context, WidgetRef ref) async {
    final lock = ref.read(parentLockProvider);
    if (lock.hasPin) {
      final p = await askPin(context, title: 'رمز والدین');
      if (p == null || !context.mounted) return;
      if (!await ref.read(parentLockProvider.notifier).check(p)) {
        if (context.mounted) {
          final forgot = await showDialog<bool>(
            context: context,
            builder: (d) => AlertDialog(
              title: const Text('رمز درست نیست'),
              content: const Text('اگر رمز را فراموش کرده‌اید، از حساب خارج شوید و دوباره با کد پیامکی وارد شوید؛ رمز پاک می‌شود.', style: TextStyle(height: 1.8)),
              actions: [
                TextButton(onPressed: () => Navigator.pop(d, false), child: const Text('دوباره')),
                TextButton(onPressed: () => Navigator.pop(d, true), child: const Text('خروج از حساب', style: TextStyle(color: HsColors.red))),
              ],
            ),
          );
          if (forgot == true) {
            await ref.read(parentLockProvider.notifier).clearAll();
            await ref.read(authProvider.notifier).logout();
          }
        }
        return;
      }
    }
    await ref.read(parentLockProvider.notifier).exitKidMode();
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final lock = ref.watch(parentLockProvider);
    final id = lock.kidId ?? 0;
    final today = ref.watch(getProvider('/children/$id/today'));
    final d = (today.valueOrNull?.data as Map?) ?? const {};
    final classes = ((d['classes'] as List?) ?? const []).cast<Map>();
    return PopScope(
      canPop: false,
      child: DefaultTabController(
        length: 2,
        child: Scaffold(
          backgroundColor: const Color(0xFFFFF3E6),
          appBar: AppBar(
            backgroundColor: const Color(0xFFFFF3E6),
            automaticallyImplyLeading: false,
            title: Text('سلام ${lock.kidName.split(' ').first}! 🌟'),
            actions: [
              TextButton.icon(
                key: const Key('kid_exit'),
                onPressed: () => _exit(context, ref),
                icon: Icon(lock.hasPin ? Icons.lock_rounded : Icons.logout_rounded, size: 18),
                label: const Text('بخش والدین'),
              ),
            ],
            bottom: const TabBar(labelColor: HsColors.orangeDark, indicatorColor: HsColors.orange, tabs: [Tab(text: 'امروز من'), Tab(text: 'دیوار همشاگردی من')]),
          ),
          body: TabBarView(children: [
            RefreshIndicator(
              onRefresh: () async => ref.invalidate(getProvider('/children/$id/today')),
              child: ListView(padding: const EdgeInsets.all(16), children: [
                if (today.isLoading && !today.hasValue) const LoadingView(),
                for (final c in classes) ...[
                  Container(
                    padding: const EdgeInsets.all(18),
                    decoration: BoxDecoration(gradient: const LinearGradient(colors: [HsColors.orange, Color(0xFFFFB347)]), borderRadius: BorderRadius.circular(24)),
                    child: Row(children: [
                      const Text('⭐', style: TextStyle(fontSize: 42)),
                      const SizedBox(width: 12),
                      Expanded(child: Text('${c['title']}', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 16))),
                      if (c.containsKey('points_total'))
                        Column(children: [
                          Text(Fa.faDigits(c['points_total'] ?? 0), style: const TextStyle(color: Colors.white, fontSize: 32, fontFamily: 'Lalezar')),
                          Text('امروز ${Fa.faDigits(c['points_today'] ?? 0)}+', style: const TextStyle(color: Colors.white, fontSize: 12)),
                        ]),
                    ]),
                  ),
                  const SizedBox(height: 12),
                  SectionCard(
                    title: 'تکالیف',
                    icon: Icons.menu_book_rounded,
                    child: Column(children: [
                      if (((c['homework_open'] as List?) ?? const []).isEmpty) Text('تکلیفی نداری 🎉', style: TextStyle(color: HsColors.muted)),
                      for (final h in ((c['homework_open'] as List?) ?? const []).cast<Map>())
                        ListTile(contentPadding: EdgeInsets.zero, leading: const Icon(Icons.radio_button_unchecked_rounded, color: HsColors.orange), title: Text('${h['title']}'), subtitle: Text('${h['subject'] ?? ''}')),
                    ]),
                  ),
                  const SizedBox(height: 16),
                ],
              ]),
            ),
            PortfolioView(studentId: id),
          ]),
        ),
      ),
    );
  }
}
