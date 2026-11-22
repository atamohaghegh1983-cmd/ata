import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';

import 'api/api_client.dart';
import 'config.dart';
import 'utils/fa.dart';
import 'widgets/attachments.dart';

/// بررسی نسخه‌ی تازه‌ی اپ روی سایت (یک بار در هر بار باز شدن اپ)
class UpdateCheck {
  UpdateCheck._();
  static bool _done = false;

  static Future<void> run(BuildContext context, ApiClient api) async {
    if (_done) return;
    _done = true;
    try {
      final info = await PackageInfo.fromPlatform();
      final build = int.tryParse(info.buildNumber) ?? 0;
      final r = await api.get<Map<String, dynamic>>('/app/latest?app=${AppConfig.app}', useCache: false);
      final d = r.data;
      if (d['available'] != true || build == 0) return;
      final latest = (d['build'] as num?)?.toInt() ?? 0;
      if (latest <= build) return;
      final force = build < ((d['min_build'] as num?)?.toInt() ?? 0);
      if (!context.mounted) return;
      await showDialog<void>(
        context: context,
        barrierDismissible: !force,
        builder: (c) => PopScope(
          canPop: !force,
          child: AlertDialog(
            icon: const Icon(Icons.system_update_rounded, size: 40),
            title: Text('نسخه‌ی تازه‌ی ${AppConfig.appName} (${Fa.faDigits(d['version'] ?? '')})'),
            content: Text(
              [if ('${d['notes'] ?? ''}'.isNotEmpty) '${d['notes']}', if (force) 'برای ادامه، نسخه‌ی تازه را نصب کنید.'].join('\n\n'),
              style: const TextStyle(height: 1.8),
            ),
            actions: [
              if (!force) TextButton(onPressed: () => Navigator.pop(c), child: const Text('بعداً')),
              FilledButton(
                style: FilledButton.styleFrom(minimumSize: const Size(120, 44)),
                onPressed: () => openUrl(c, '${d['url']}'),
                child: const Text('دانلود و نصب'),
              ),
            ],
          ),
        ),
      );
    } catch (_) {
      // بی‌صدا: بررسی نسخه نباید کار اپ را مختل کند
    }
  }
}
