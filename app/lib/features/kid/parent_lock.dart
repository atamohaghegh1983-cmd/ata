import 'dart:convert';

import 'package:crypto/crypto.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// رمز والدین (اختیاری) و حالت کودک — فقط روی همین گوشی.
/// رمز به‌صورت هش نمک‌دار در حافظه‌ی امن ذخیره می‌شود. فراموشی ← خروج و ورود دوباره با پیامک (رمز پاک می‌شود).
class LockState {
  const LockState({this.hasPin = false, this.kidId, this.kidName = '', this.loaded = false});
  final bool hasPin;
  final int? kidId;
  final String kidName;
  final bool loaded;
  bool get kidMode => kidId != null;
}

final parentLockProvider = NotifierProvider<ParentLock, LockState>(ParentLock.new);

class ParentLock extends Notifier<LockState> {
  static const _s = FlutterSecureStorage();
  static const _kPin = 'hs_parent_pin';
  static const _kKid = 'hs_kid_mode';

  @override
  LockState build() {
    Future.microtask(_load);
    return const LockState();
  }

  Future<void> _load() async {
    String? pin;
    String? kid;
    try {
      pin = await _s.read(key: _kPin);
      kid = await _s.read(key: _kKid);
    } catch (_) {
      // حافظه‌ی امن در دسترس نیست: بدون رمز و بدون حالت کودک
    }
    int? id;
    var name = '';
    if (kid != null) {
      try {
        final m = jsonDecode(kid) as Map<String, dynamic>;
        id = (m['id'] as num).toInt();
        name = '${m['name'] ?? ''}';
      } catch (_) {}
    }
    state = LockState(hasPin: pin != null, kidId: id, kidName: name, loaded: true);
  }

  String _hash(String pin, String salt) => sha256.convert(utf8.encode('$salt:$pin:hamshagerdi')).toString();

  Future<void> setPin(String pin) async {
    final salt = DateTime.now().microsecondsSinceEpoch.toString();
    await _s.write(key: _kPin, value: '$salt:${_hash(pin, salt)}');
    state = LockState(hasPin: true, kidId: state.kidId, kidName: state.kidName, loaded: true);
  }

  Future<bool> check(String pin) async {
    final v = await _s.read(key: _kPin);
    if (v == null) return true;
    final i = v.indexOf(':');
    return i > 0 && _hash(pin, v.substring(0, i)) == v.substring(i + 1);
  }

  Future<void> removePin() async {
    await _s.delete(key: _kPin);
    state = LockState(hasPin: false, kidId: state.kidId, kidName: state.kidName, loaded: true);
  }

  Future<void> enterKidMode(int id, String name) async {
    await _s.write(key: _kKid, value: jsonEncode({'id': id, 'name': name}));
    state = LockState(hasPin: state.hasPin, kidId: id, kidName: name, loaded: true);
  }

  Future<void> exitKidMode() async {
    await _s.delete(key: _kKid);
    state = LockState(hasPin: state.hasPin, loaded: true);
  }

  /// خروج از حساب: رمز و حالت کودک پاک می‌شود
  Future<void> clearAll() async {
    await _s.delete(key: _kPin);
    await _s.delete(key: _kKid);
    state = const LockState(loaded: true);
  }
}
