import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/config.dart';
import '../../core/providers.dart';
import '../../core/push.dart';
import 'me.dart';

enum AuthStatus { unknown, loggedOut, loggedIn }

class AuthState {
  const AuthState(this.status, {this.me, this.offline = false});
  final AuthStatus status;
  final Me? me;

  /// اطلاعات کاربر از کش آمده (اینترنت قطع بود)
  final bool offline;
}

final authProvider = NotifierProvider<AuthController, AuthState>(AuthController.new);

class AuthController extends Notifier<AuthState> {
  @override
  AuthState build() {
    final api = ref.read(apiProvider);
    api.onSessionExpired = () {
      ref.read(cacheStoreProvider).clearAll();
      state = const AuthState(AuthStatus.loggedOut);
    };
    Future.microtask(restore);
    return const AuthState(AuthStatus.unknown);
  }

  /// شروع اپ: اگر نشست ذخیره‌شده داریم، اطلاعات کاربر را بگیر
  Future<void> restore() async {
    final tokens = ref.read(tokenStoreProvider);
    await tokens.load();
    if (!tokens.hasSession) {
      state = const AuthState(AuthStatus.loggedOut);
      return;
    }
    try {
      await reloadMe();
      Push.register(ref.read(apiProvider));
    } on ApiException catch (e) {
      if (e.isUnauthorized || !tokens.hasSession) {
        await tokens.clear();
        state = const AuthState(AuthStatus.loggedOut);
      } else {
        // اینترنت قطع و کش هم نیست: بعداً دوباره تلاش می‌کنیم
        state = const AuthState(AuthStatus.loggedIn, offline: true);
      }
    }
  }

  Future<Me> reloadMe() async {
    final api = ref.read(apiProvider);
    final res = await api.get<Map<String, dynamic>>('/me');
    final me = Me(res.data);
    api.userId = me.id;
    state = AuthState(AuthStatus.loggedIn, me: me, offline: res.fromCache);
    return me;
  }

  /// درخواست کد؛ ثانیه‌های لازم تا ارسال دوباره را برمی‌گرداند
  Future<int> requestOtp(String mobile) async {
    final d = await ref.read(apiProvider).post<Map<String, dynamic>>('/auth/otp/request', data: {'mobile': mobile}, auth: false);
    return (d['wait'] as num?)?.toInt() ?? 60;
  }

  Future<Me> verifyOtp(String mobile, String code) async {
    final api = ref.read(apiProvider);
    final d = await api.post<Map<String, dynamic>>(
      '/auth/otp/verify',
      data: {'mobile': mobile, 'code': code, 'device': AppConfig.deviceName},
      auth: false,
    );
    await api.saveTokens(d);
    final me = await reloadMe();
    Push.register(api);
    return me;
  }

  /// ورود با رمز: شماره موبایل، ایمیل یا نام کاربری
  Future<Me> loginPassword(String login, String password) async {
    final api = ref.read(apiProvider);
    final d = await api.post<Map<String, dynamic>>(
      '/auth/login',
      data: {'username': login, 'password': password, 'device': AppConfig.deviceName},
      auth: false,
    );
    await api.saveTokens(d);
    final me = await reloadMe();
    Push.register(api);
    return me;
  }

  /// ساخت یا تغییر رمز (و ایمیل)؛ توکن تازه‌ی این دستگاه ذخیره می‌شود
  Future<String> setPassword({required String password, String? current, String? email}) async {
    final api = ref.read(apiProvider);
    final d = await api.post<Map<String, dynamic>>('/me/password', data: {
      'password': password,
      if (current != null && current.isNotEmpty) 'current': current,
      'email': ?email,
      'device': AppConfig.deviceName,
    });
    await api.saveTokens(d);
    await reloadMe();
    return '${d['message'] ?? 'رمز ذخیره شد.'}';
  }

  Future<void> logout({bool everywhere = false}) async {
    final api = ref.read(apiProvider);
    final tokens = ref.read(tokenStoreProvider);
    try {
      final t = await Push.token();
      if (t != null) await api.delete<Object?>('/devices', data: {'token': t});
    } catch (_) {}
    try {
      await api.post<Object?>('/auth/logout', data: {'refresh_token': tokens.refresh, 'all': everywhere});
    } catch (_) {
      // حتی اگر اینترنت قطع باشد، روی گوشی خارج می‌شویم
    }
    await tokens.clear();
    await ref.read(cacheStoreProvider).clearAll();
    api.userId = null;
    state = const AuthState(AuthStatus.loggedOut);
  }
}
