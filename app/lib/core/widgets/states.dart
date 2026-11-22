import 'package:flutter/material.dart';

import '../api/api_exception.dart';
import '../theme.dart';
import '../utils/fa.dart';

/// پیام خطا با دکمه‌ی «دوباره»
class ErrorView extends StatelessWidget {
  const ErrorView({super.key, required this.error, this.onRetry});
  final Object error;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    final msg = error is ApiException ? (error as ApiException).message : 'خطای غیرمنتظره‌ای رخ داد. دوباره امتحان کنید.';
    final offline = error is ApiException && (error as ApiException).offline;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(offline ? Icons.wifi_off_rounded : Icons.error_outline_rounded, size: 56, color: HsColors.orange),
            const SizedBox(height: 12),
            Text(msg, textAlign: TextAlign.center, style: const TextStyle(fontSize: 16, height: 1.7)),
            if (onRetry != null) ...[
              const SizedBox(height: 16),
              OutlinedButton.icon(onPressed: onRetry, icon: const Icon(Icons.refresh_rounded), label: const Text('دوباره امتحان کن')),
            ],
          ],
        ),
      ),
    );
  }
}

class LoadingView extends StatelessWidget {
  const LoadingView({super.key});
  @override
  Widget build(BuildContext context) => const Center(child: CircularProgressIndicator(color: HsColors.orange));
}

/// نوار «آفلاین؛ آخرین اطلاعات ذخیره‌شده»
class OfflineBanner extends StatelessWidget {
  const OfflineBanner({super.key, this.savedAt});
  final DateTime? savedAt;

  @override
  Widget build(BuildContext context) {
    final when = savedAt == null ? '' : ' (${Fa.date(Fa.ymd(savedAt!), withYear: false)}، ساعت ${Fa.faDigits('${savedAt!.hour}:${savedAt!.minute.toString().padLeft(2, '0')}')})';
    return Container(
      width: double.infinity,
      color: const Color(0xFFFFE8CC),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Row(children: [
        const Icon(Icons.cloud_off_rounded, size: 18, color: HsColors.orangeDark),
        const SizedBox(width: 8),
        Expanded(child: Text('اینترنت قطع است؛ آخرین اطلاعات ذخیره‌شده$when', style: const TextStyle(fontSize: 13, color: HsColors.orangeDark))),
      ]),
    );
  }
}

/// پیام خالی بودن فهرست
class EmptyView extends StatelessWidget {
  const EmptyView({super.key, required this.icon, required this.title, this.text = ''});
  final IconData icon;
  final String title;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(28),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(
          width: 88,
          height: 88,
          decoration: const BoxDecoration(color: Color(0xFFFFE3CC), shape: BoxShape.circle),
          child: Icon(icon, size: 44, color: HsColors.orange),
        ),
        const SizedBox(height: 14),
        Text(title, textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleLarge),
        if (text.isNotEmpty) ...[
          const SizedBox(height: 6),
          Text(text, textAlign: TextAlign.center, style: TextStyle(height: 1.7, color: HsColors.muted)),
        ],
      ]),
    );
  }
}

void showMessage(BuildContext context, String msg, {bool error = false}) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(
      content: Text(msg),
      backgroundColor: error ? HsColors.red : HsColors.navy,
    ));
}
