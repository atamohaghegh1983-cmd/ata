import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/providers.dart';
import '../../core/theme.dart';

/// معرفی کوتاه (فقط بار اول)
class IntroScreen extends StatelessWidget {
  const IntroScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: HsColors.navy,
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 24, 24, 20),
          child: Column(
            children: [
              const Spacer(),
              Image.asset('assets/images/logo_full.png', height: 210, semanticLabel: 'همشاگردی آنلاین'),
              const SizedBox(height: 28),
              const Text(
                'مدرسه و کلاس، در جیب شما',
                textAlign: TextAlign.center,
                style: TextStyle(fontFamily: 'Lalezar', fontSize: 26, color: Colors.white),
              ),
              const SizedBox(height: 18),
              const _Point(icon: Icons.family_restroom_rounded, text: 'والدین: حضور، امتیاز و تکالیف فرزند، هر روز'),
              const _Point(icon: Icons.co_present_rounded, text: 'معلم: حضور و غیاب و امتیاز با یک لمس'),
              const _Point(icon: Icons.chat_bubble_rounded, text: 'پیام مستقیم بین خانه و کلاس'),
              const Spacer(flex: 2),
              FilledButton(
                key: const Key('intro_start'),
                onPressed: () async {
                  await markIntroSeen();
                  if (context.mounted) context.go('/login');
                },
                child: const Text('شروع کنیم'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Point extends StatelessWidget {
  const _Point({required this.icon, required this.text});
  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(children: [
        Container(
          width: 38,
          height: 38,
          decoration: BoxDecoration(color: HsColors.orange.withValues(alpha: 0.18), borderRadius: BorderRadius.circular(12)),
          child: Icon(icon, color: HsColors.orange, size: 22),
        ),
        const SizedBox(width: 12),
        Expanded(child: Text(text, style: const TextStyle(color: Colors.white, fontSize: 15, height: 1.6))),
      ]),
    );
  }
}
