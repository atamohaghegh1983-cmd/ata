import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

/// کش ساده‌ی آخرین پاسخ هر صفحه، برای حالت آفلاین.
/// کلیدها به شناسه‌ی کاربر وابسته‌اند و با خروج از حساب پاک می‌شوند.
class CacheStore {
  static const _prefix = 'hs_cache:';
  SharedPreferences? _p;

  Future<SharedPreferences> get _prefs async => _p ??= await SharedPreferences.getInstance();

  Future<void> put(String key, Object? data) async {
    final p = await _prefs;
    await p.setString(_prefix + key, jsonEncode({'t': DateTime.now().toIso8601String(), 'd': data}));
  }

  Future<CachedValue?> get(String key) async {
    final p = await _prefs;
    final raw = p.getString(_prefix + key);
    if (raw == null) return null;
    try {
      final m = jsonDecode(raw) as Map<String, dynamic>;
      return CachedValue(m['d'], DateTime.tryParse(m['t'] as String? ?? '') ?? DateTime.now());
    } catch (_) {
      return null;
    }
  }

  Future<void> clearAll() async {
    final p = await _prefs;
    for (final k in p.getKeys().where((k) => k.startsWith(_prefix)).toList()) {
      await p.remove(k);
    }
  }
}

class CachedValue {
  CachedValue(this.data, this.savedAt);
  final Object? data;
  final DateTime savedAt;
}
