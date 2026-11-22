import 'package:flutter/material.dart';

import '../theme.dart';
import '../utils/fa.dart';

/// آواتار: عکس، یا حرف اول نام در دایره‌ی رنگی
class HsAvatar extends StatelessWidget {
  const HsAvatar({super.key, required this.name, this.url = '', this.size = 48});

  final String name;
  final String url;
  final double size;

  static Color colorFor(String name) {
    var h = 0;
    for (final r in name.runes) {
      h = (h * 31 + r) & 0x7fffffff;
    }
    return HsColors.avatarPalette[h % HsColors.avatarPalette.length];
  }

  @override
  Widget build(BuildContext context) {
    final letter = Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(color: colorFor(name), shape: BoxShape.circle),
      child: Text(
        Fa.initial(name),
        style: TextStyle(color: Colors.white, fontSize: size * 0.45, fontWeight: FontWeight.w700, height: 1.1),
      ),
    );
    if (url.isEmpty) return letter;
    return ClipOval(
      child: Image.network(
        url,
        width: size,
        height: size,
        fit: BoxFit.cover,
        errorBuilder: (_, _, _) => letter,
        loadingBuilder: (c, child, p) => p == null ? child : letter,
      ),
    );
  }
}
