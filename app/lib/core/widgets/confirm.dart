import 'package:flutter/material.dart';

import '../theme.dart';

/// پنجره‌ی تأیید کارهای برگشت‌ناپذیر
Future<bool> confirmDanger(BuildContext context, {required String title, required String text, String ok = 'حذف'}) async {
  final r = await showDialog<bool>(
    context: context,
    builder: (c) => AlertDialog(
      icon: const Icon(Icons.warning_amber_rounded, color: HsColors.red, size: 40),
      title: Text(title),
      content: Text(text, style: const TextStyle(height: 1.8)),
      actions: [
        TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('انصراف')),
        FilledButton(
          style: FilledButton.styleFrom(backgroundColor: HsColors.red, minimumSize: const Size(90, 44)),
          onPressed: () => Navigator.pop(c, true),
          child: Text(ok),
        ),
      ],
    ),
  );
  return r == true;
}
