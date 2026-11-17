import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hamshagerdi/core/api/api_exception.dart';

void main() {
  final req = RequestOptions(path: '/me');

  test('اینترنت قطع ← پیام فارسی و حالت آفلاین', () {
    final e = ApiException.fromDio(DioException(requestOptions: req, type: DioExceptionType.connectionError));
    expect(e.offline, isTrue);
    expect(e.message, contains('اینترنت'));
  });

  test('پیام فارسی سرور نمایش داده می‌شود', () {
    final e = ApiException.fromDio(DioException(
      requestOptions: req,
      type: DioExceptionType.badResponse,
      response: Response(requestOptions: req, statusCode: 403, data: {
        'code': 'hs_feature_locked',
        'message': 'مدرسه دیدن تکالیف را بسته است.',
        'data': {'status': 403, 'feature': 'homework'},
      }),
    ));
    expect(e.status, 403);
    expect(e.code, 'hs_feature_locked');
    expect(e.data?['feature'], 'homework');
    expect(e.message, 'مدرسه دیدن تکالیف را بسته است.');
  });

  test('خطای ۵۰۰ بدون پیام', () {
    final e = ApiException.fromDio(DioException(
      requestOptions: req,
      type: DioExceptionType.badResponse,
      response: Response(requestOptions: req, statusCode: 502, data: '<html>'),
    ));
    expect(e.message, contains('موقتاً'));
  });
}
