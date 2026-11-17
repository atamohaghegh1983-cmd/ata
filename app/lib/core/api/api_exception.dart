import 'package:dio/dio.dart';

/// خطای قابل نمایش به کاربر (همیشه فارسی)
class ApiException implements Exception {
  ApiException(this.message, {this.status, this.code, this.data, this.offline = false});

  final String message;
  final int? status;
  final String? code;
  final Map<String, dynamic>? data;

  /// اینترنت قطع است / سایت در دسترس نیست
  final bool offline;

  bool get isUnauthorized => status == 401;

  @override
  String toString() => message;

  static const offlineMessage = 'اتصال به اینترنت برقرار نیست. اینترنت گوشی را بررسی کنید و دوباره امتحان کنید.';

  factory ApiException.fromDio(DioException e) {
    switch (e.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
      case DioExceptionType.transformTimeout:
        return ApiException('پاسخ سایت خیلی طول کشید. اینترنت ضعیف است؛ کمی بعد دوباره امتحان کنید.', offline: true);
      case DioExceptionType.connectionError:
        return ApiException(offlineMessage, offline: true);
      case DioExceptionType.badCertificate:
        return ApiException('اتصال امن به سایت برقرار نشد. ساعت و تاریخ گوشی را بررسی کنید.');
      case DioExceptionType.cancel:
        return ApiException('درخواست لغو شد.');
      case DioExceptionType.badResponse:
      case DioExceptionType.unknown:
        break;
    }
    final res = e.response;
    if (res == null) {
      return ApiException(offlineMessage, offline: true);
    }
    final status = res.statusCode ?? 0;
    final body = res.data;
    if (body is Map && body['message'] is String && (body['message'] as String).isNotEmpty) {
      final data = body['data'];
      return ApiException(
        body['message'] as String,
        status: status,
        code: body['code']?.toString(),
        data: data is Map ? Map<String, dynamic>.from(data) : null,
      );
    }
    return ApiException(_byStatus(status), status: status);
  }

  static String _byStatus(int s) {
    if (s == 401) return 'نشست شما تمام شده است. دوباره وارد شوید.';
    if (s == 403) return 'به این بخش دسترسی ندارید.';
    if (s == 404) return 'پیدا نشد. ممکن است افزونه‌ی API روی سایت نصب نباشد.';
    if (s == 429) return 'تعداد درخواست‌ها زیاد بود. چند دقیقه بعد دوباره امتحان کنید.';
    if (s >= 500) return 'سایت موقتاً در دسترس نیست. کمی بعد دوباره امتحان کنید.';
    return 'خطایی رخ داد (کد $s). دوباره امتحان کنید.';
  }
}
