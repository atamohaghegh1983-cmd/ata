import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';

import 'api/api_client.dart';

/// اعلان Firebase. اگر فایل تنظیمات Firebase در ساخت اپ نباشد، بی‌صدا غیرفعال می‌ماند
/// و فقط «مرکز اعلان» داخل اپ کار می‌کند.
class Push {
  Push._();
  static bool _ready = false;

  static Future<void> init() async {
    try {
      await Firebase.initializeApp();
      _ready = true;
    } catch (e) {
      debugPrint('Firebase غیرفعال: $e');
      _ready = false;
    }
  }

  /// پس از ورود: اجازه‌ی اعلان و ثبت توکن این گوشی برای همین حساب
  static Future<void> register(ApiClient api) async {
    if (!_ready) return;
    try {
      final m = FirebaseMessaging.instance;
      await m.requestPermission();
      // توکن ثبت FCM برای ارسال با HTTP v1 (روش جدید FID هنوز با messages:send سازگار نیست)
      // ignore: deprecated_member_use
      final token = await m.getToken();
      if (token != null) {
        await api.post<Object?>('/devices', data: {'token': token, 'platform': 'android', 'provider': 'fcm', 'app_version': '0.5.0'});
      }
      // ignore: deprecated_member_use
      m.onTokenRefresh.listen((t) => api.post<Object?>('/devices', data: {'token': t, 'platform': 'android', 'provider': 'fcm'}).catchError((_) => null));
    } catch (e) {
      debugPrint('ثبت توکن اعلان ناموفق: $e');
    }
  }

  static Future<String?> token() async {
    if (!_ready) return null;
    try {
      // ignore: deprecated_member_use
      return await FirebaseMessaging.instance.getToken();
    } catch (_) {
      return null;
    }
  }
}
