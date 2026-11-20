import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// نگهداری امن توکن‌ها (Android Keystore)
class TokenStore {
  TokenStore([FlutterSecureStorage? storage]) : _s = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _s;
  static const _kAccess = 'hs_access';
  static const _kRefresh = 'hs_refresh';
  static const _kExpiry = 'hs_access_exp';

  String? _access;
  String? _refresh;
  DateTime? _expiry;
  bool _loaded = false;

  Future<void> load() async {
    if (_loaded) return;
    _access = await _s.read(key: _kAccess);
    _refresh = await _s.read(key: _kRefresh);
    final e = await _s.read(key: _kExpiry);
    _expiry = e == null ? null : DateTime.tryParse(e);
    _loaded = true;
  }

  String? get access => _access;
  String? get refresh => _refresh;
  bool get hasSession => (_refresh ?? '').isNotEmpty;

  /// کمتر از یک دقیقه به انقضا مانده؟
  bool get accessExpiringSoon => _expiry == null || DateTime.now().isAfter(_expiry!.subtract(const Duration(minutes: 1)));

  Future<void> save({required String access, required String refresh, required int expiresIn}) async {
    _access = access;
    _refresh = refresh;
    _expiry = DateTime.now().add(Duration(seconds: expiresIn));
    _loaded = true;
    await _s.write(key: _kAccess, value: access);
    await _s.write(key: _kRefresh, value: refresh);
    await _s.write(key: _kExpiry, value: _expiry!.toIso8601String());
  }

  Future<void> clear() async {
    _access = null;
    _refresh = null;
    _expiry = null;
    await _s.delete(key: _kAccess);
    await _s.delete(key: _kRefresh);
    await _s.delete(key: _kExpiry);
  }
}
