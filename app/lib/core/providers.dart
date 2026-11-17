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
