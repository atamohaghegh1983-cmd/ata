import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'api/api_client.dart';
import 'storage/cache_store.dart';
import 'storage/token_store.dart';

final tokenStoreProvider = Provider<TokenStore>((ref) => TokenStore());
final cacheStoreProvider = Provider<CacheStore>((ref) => CacheStore());

final apiProvider = Provider<ApiClient>((ref) {
  return ApiClient(tokens: ref.watch(tokenStoreProvider), cache: ref.watch(cacheStoreProvider));
});

/// آیا معرفی اول اپ دیده شده؟
final introSeenProvider = FutureProvider<bool>((ref) async {
  final p = await SharedPreferences.getInstance();
  return p.getBool('hs_intro_seen') ?? false;
});

Future<void> markIntroSeen() async {
  final p = await SharedPreferences.getInstance();
  await p.setBool('hs_intro_seen', true);
}

/// خواندن یک مسیر API با کش آفلاین؛ کلید = مسیر کامل (با query)
final getProvider = FutureProvider.autoDispose.family<ApiResult<Object?>, String>((ref, path) {
  return ref.read(apiProvider).get<Object?>(path);
});

/// نحوه‌ی نمایش دانش‌آموزان هر کلاس روی این گوشی: grid | list
final studentsViewProvider = NotifierProvider.family<StudentsView, String, int>(StudentsView.new);

class StudentsView extends FamilyNotifier<String, int> {
  @override
  String build(int classId) {
    SharedPreferences.getInstance().then((p) {
      final v = p.getString('hs_sv_$classId');
      if (v != null && v != state) state = v;
    }).catchError((_) {});
    return 'grid';
  }

  Future<void> set(String v) async {
    state = v;
    try {
      (await SharedPreferences.getInstance()).setString('hs_sv_$arg', v);
    } catch (_) {}
  }
}

/// ظاهر اپ: system | light | dark (روی همین گوشی)
final themeModeProvider = NotifierProvider<ThemeModeCtl, String>(ThemeModeCtl.new);

class ThemeModeCtl extends Notifier<String> {
  @override
  String build() {
    SharedPreferences.getInstance().then((p) {
      final v = p.getString('hs_theme');
      if (v != null && v != state) state = v;
    }).catchError((_) {});
    return 'system';
  }

  Future<void> set(String v) async {
    state = v;
    try {
      (await SharedPreferences.getInstance()).setString('hs_theme', v);
    } catch (_) {}
  }
}
