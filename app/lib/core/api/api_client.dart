import 'dart:async';

import 'package:dio/dio.dart';

import '../config.dart';
import '../storage/cache_store.dart';
import '../storage/token_store.dart';
import 'api_exception.dart';

/// نتیجه‌ی خواندن: داده + آیا از کش آفلاین آمده
class ApiResult<T> {
  ApiResult(this.data, {this.fromCache = false, this.savedAt});
  final T data;
  final bool fromCache;
  final DateTime? savedAt;
}

/// اتصال به API سایت: توکن، رفرش خودکار، کش آفلاین، خطای فارسی
class ApiClient {
  ApiClient({required this.tokens, required this.cache, Dio? dio})
      : _dio = dio ??
            Dio(BaseOptions(
              baseUrl: AppConfig.apiBase,
              connectTimeout: AppConfig.connectTimeout,
              receiveTimeout: AppConfig.receiveTimeout,
              contentType: Headers.jsonContentType,
              responseType: ResponseType.json,
              headers: {'Accept': 'application/json'},
            )) {
    _dio.interceptors.add(InterceptorsWrapper(onRequest: _onRequest, onError: _onError));
  }

  final Dio _dio;
  final TokenStore tokens;
  final CacheStore cache;

  /// وقتی نشست قابل تمدید نیست (رفرش باطل) صدا زده می‌شود
  void Function()? onSessionExpired;

  /// شناسه‌ی کاربر برای کلید کش
  int? userId;

  Completer<bool>? _refreshing;

  static const _noAuth = 'hs_no_auth';
  static const _retried = 'hs_retried';

  Future<void> _onRequest(RequestOptions o, RequestInterceptorHandler h) async {
    if (o.extra[_noAuth] == true) return h.next(o);
    await tokens.load();
    if (tokens.hasSession && tokens.accessExpiringSoon) {
      await _refresh();
    }
    final a = tokens.access;
    if (a != null && a.isNotEmpty) {
      o.headers['Authorization'] = 'Bearer $a';
    }
    h.next(o);
  }

  Future<void> _onError(DioException e, ErrorInterceptorHandler h) async {
    final o = e.requestOptions;
    final is401 = e.response?.statusCode == 401;
    if (is401 && o.extra[_noAuth] != true && o.extra[_retried] != true && tokens.hasSession) {
      if (await _refresh()) {
        o.extra[_retried] = true;
        o.headers['Authorization'] = 'Bearer ${tokens.access}';
        try {
          return h.resolve(await _dio.fetch(o));
        } on DioException catch (e2) {
          return h.next(e2);
        }
      }
    }
    h.next(e);
  }

  /// یک رفرش هم‌زمان برای همه‌ی درخواست‌ها
  Future<bool> _refresh() async {
    if (_refreshing != null) return _refreshing!.future;
    final c = _refreshing = Completer<bool>();
    try {
      final rt = tokens.refresh;
      if (rt == null || rt.isEmpty) {
        c.complete(false);
        return false;
      }
      final res = await _dio.post<Map<String, dynamic>>(
        '/auth/refresh',
        data: {'refresh_token': rt, 'device': AppConfig.deviceName},
        options: Options(extra: {_noAuth: true}),
      );
      await saveTokens(res.data!);
      c.complete(true);
    } on DioException catch (e) {
      final status = e.response?.statusCode;
      if (status == 401 || status == 403) {
        await tokens.clear();
        onSessionExpired?.call();
      }
      c.complete(false); // خطای شبکه: نشست حفظ می‌شود تا بعداً دوباره تلاش شود
    } catch (_) {
      c.complete(false);
    } finally {
      _refreshing = null;
    }
    return c.future;
  }

  Future<void> saveTokens(Map<String, dynamic> d) => tokens.save(
        access: d['access_token'] as String,
        refresh: d['refresh_token'] as String,
        expiresIn: (d['expires_in'] as num?)?.toInt() ?? 3600,
      );

  String _ck(String path, Map<String, dynamic>? q) => '${userId ?? 0}:$path?${q ?? ''}';

  /// خواندن؛ اگر اینترنت قطع بود و کش داشت، از کش برمی‌گرداند
  Future<ApiResult<T>> get<T>(String path, {Map<String, dynamic>? query, bool useCache = true}) async {
    try {
      final res = await _dio.get<Object?>(path, queryParameters: query);
      final data = res.data;
      if (useCache) unawaited(cache.put(_ck(path, query), data));
      return ApiResult<T>(data as T);
    } on DioException catch (e) {
      final ex = ApiException.fromDio(e);
      if (ex.offline && useCache) {
        final c = await cache.get(_ck(path, query));
        if (c != null) return ApiResult<T>(c.data as T, fromCache: true, savedAt: c.savedAt);
      }
      throw ex;
    }
  }

  Future<T> post<T>(String path, {Object? data, bool auth = true}) => _send<T>('POST', path, data, auth);

  Future<T> delete<T>(String path, {Object? data}) => _send<T>('DELETE', path, data, true);

  Future<T> _send<T>(String method, String path, Object? data, bool auth) async {
    try {
      final res = await _dio.request<Object?>(
        path,
        data: data,
        options: Options(
          method: method,
          extra: auth ? null : {_noAuth: true},
          contentType: data is FormData ? Headers.multipartFormDataContentType : null,
        ),
      );
      return res.data as T;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}
