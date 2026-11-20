// آزمون سرتاسری روی یک سایت واقعی/آزمایشی (در CI اجرا نمی‌شود).
//   HS_E2E_SITE=http://127.0.0.1:8899 HS_E2E_OTP_CMD="..." flutter test test/e2e
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hamshagerdi/core/api/api_client.dart';
import 'package:hamshagerdi/core/api/api_exception.dart';
import 'package:hamshagerdi/core/config.dart';
import 'package:hamshagerdi/core/providers.dart';
import 'package:hamshagerdi/core/storage/cache_store.dart';
import 'package:hamshagerdi/core/storage/token_store.dart';
import 'package:hamshagerdi/features/auth/auth_controller.dart';
import 'package:shared_preferences/shared_preferences.dart';

final site = Platform.environment['HS_E2E_SITE'] ?? '';
final otpCmd = Platform.environment['HS_E2E_OTP_CMD'] ?? '';

Future<String> readOtp(String mobile) async {
  final r = await Process.run('bash', ['-c', otpCmd.replaceAll('{m}', mobile)]);
  return '${r.stdout}'.trim();
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  HttpOverrides.global = null; // شبکه‌ی واقعی

  late ProviderContainer box;
  late Dio dio;

  setUp(() {
    SharedPreferences.setMockInitialValues({});
    FlutterSecureStorage.setMockInitialValues({});
    dio = Dio(BaseOptions(baseUrl: '$site${AppConfig.apiPath}', contentType: Headers.jsonContentType));
    box = ProviderContainer(overrides: [
      apiProvider.overrideWith((ref) => ApiClient(tokens: ref.watch(tokenStoreProvider), cache: ref.watch(cacheStoreProvider), dio: dio)),
    ]);
  });

  tearDown(() => box.dispose());

  test('ورود پیامکی، رفرش خودکار، کش آفلاین و خروج', () async {
    final mobile = '0935${DateTime.now().millisecondsSinceEpoch % 10000000}'.padRight(11, '0').substring(0, 11);
    final auth = box.read(authProvider.notifier);
    await Future<void>.delayed(const Duration(milliseconds: 50));
    expect(box.read(authProvider).status, AuthStatus.loggedOut);

    await expectLater(auth.requestOtp('0912'), throwsA(isA<ApiException>().having((e) => e.status, 'status', 400)));
    final wait = await auth.requestOtp(mobile);
    expect(wait, greaterThan(0));
    await expectLater(auth.verifyOtp(mobile, '00000'), throwsA(isA<ApiException>()));
    final me = await auth.verifyOtp(mobile, await readOtp(mobile));
    expect(me.mobile, mobile);
    expect(me.needsOnboarding, isTrue);
    expect(box.read(authProvider).status, AuthStatus.loggedIn);

    // تکمیل ثبت‌نام به‌عنوان والد
    final api = box.read(apiProvider);
    final r = await api.post<Map<String, dynamic>>('/onboarding', data: {
      'role': 'parent', 'relation': 'mother', 'mother_first': 'مریم', 'mother_last': 'آزمون',
      'child_first': 'سارا', 'child_last': 'آزمون',
    });
    expect(r['ok'], isTrue);
    final me2 = await auth.reloadMe();
    expect(me2.needsOnboarding, isFalse);
    expect(me2.homeKind, 'parent');

    // توکن دسترسی خراب ← رفرش خودکار و تکرار درخواست
    final tokens = box.read(tokenStoreProvider);
    await tokens.save(access: 'eyJhbGciOiJIUzI1NiJ9.e30.invalid-signature-xx', refresh: tokens.refresh!, expiresIn: 3600);
    final children = await api.get<List<dynamic>>('/children');
    expect(children.fromCache, isFalse);
    expect(tokens.access, isNot(contains('invalid')));

    // اینترنت قطع ← آخرین داده از کش
    dio.options.baseUrl = 'http://127.0.0.1:1${AppConfig.apiPath}';
    final cached = await api.get<List<dynamic>>('/children');
    expect(cached.fromCache, isTrue);
    await expectLater(api.get<Object?>('/classes'), throwsA(isA<ApiException>().having((e) => e.offline, 'offline', isTrue)));
    dio.options.baseUrl = '$site${AppConfig.apiPath}';

    // خروج: توکن و کش پاک می‌شود و رفرش قبلی دیگر کار نمی‌کند
    final oldRefresh = tokens.refresh!;
    await auth.logout();
    expect(box.read(authProvider).status, AuthStatus.loggedOut);
    expect(TokenStore().hasSession, isFalse);
    expect(await CacheStore().get('${me.id}:/children?null'), isNull);
    await expectLater(
      dio.post<Object?>('/auth/refresh', data: {'refresh_token': oldRefresh}),
      throwsA(isA<DioException>().having((e) => e.response?.statusCode, 'status', 401)),
    );
  }, skip: site.isEmpty ? 'HS_E2E_SITE تعریف نشده' : false);

  test('معلم: ثبت‌نام، ساخت کلاس، افزودن دانش‌آموز، QR دعوت', () async {
    final mobile = '0936${(DateTime.now().millisecondsSinceEpoch ~/ 7) % 10000000}'.padRight(11, '1').substring(0, 11);
    final auth = box.read(authProvider.notifier);
    await Future<void>.delayed(const Duration(milliseconds: 50));
    await auth.requestOtp(mobile);
    await auth.verifyOtp(mobile, await readOtp(mobile));
    final api = box.read(apiProvider);
    await api.post<Map<String, dynamic>>('/onboarding', data: {'role': 'teacher', 'first_name': 'زهرا', 'last_name': 'معلمی', 'city': 'تهران', 'school_name_t': 'دبستان امید'});
    final me = await auth.reloadMe();
    AppConfig.app = 'family';
    expect(me.homeKind, 'wrong_app', reason: 'معلم در اپ والدین ← پیام «اپ معلم را نصب کنید»');
    AppConfig.app = 'teacher';
    addTearDown(() => AppConfig.app = 'family');
    expect(me.homeKind, 'teacher');
    // تغییر نقش با فرم ثبت‌نام ممنوع است و نام عوض نمی‌شود
    await expectLater(
      api.post<Map<String, dynamic>>('/onboarding', data: {'role': 'parent', 'first_name': 'علی', 'last_name': 'والد', 'child_first_name': 'سارا', 'child_last_name': 'والد'}),
      throwsA(isA<ApiException>()),
    );
    expect((await auth.reloadMe()).name, contains('زهرا'));
    final opt = (await api.get<Map<String, dynamic>>('/classes/options', useCache: false)).data;
    expect(opt['can_create'], isTrue);
    expect((opt['defaults'] as Map)['school'], 'دبستان امید');
    final made = await api.post<Map<String, dynamic>>('/classes', data: {
      'title': 'سوم الف', 'grade': (opt['grades'] as List)[3], 'school': 'دبستان امید', 'province': (opt['provinces'] as List).first, 'city': 'تهران',
    });
    final id = (made['class']['id'] as num).toInt();
    final st = await api.post<Map<String, dynamic>>('/classes/$id/students', data: {'first_name': 'سارا', 'last_name': 'رضایی', 'parent_phone': '09121112233'});
    expect(st['username'], isNotEmpty);
    final list = (await api.get<List<dynamic>>('/classes/$id/students')).data;
    expect(list.length, 1);
    final inv = (await api.get<Map<String, dynamic>>('/classes/$id/invite', useCache: false)).data;
    expect((inv['parent'] as Map)['code'], isNotEmpty);
    // «تکمیل کلاس» و باز کردن دوباره
    await api.post<Object?>('/classes/$id/join', data: {'open': false});
    expect((await api.get<Map<String, dynamic>>('/classes/$id/invite', useCache: false)).data['join_open'], isFalse);
    await api.post<Object?>('/classes/$id/join', data: {'open': true});
    expect((await api.get<Map<String, dynamic>>('/classes/$id/invite', useCache: false)).data['join_open'], isTrue);
    final classes = (await api.get<List<dynamic>>('/classes')).data;
    expect(classes.single['students'], 1);
    final code = '${st['parent_code']}';
    expect(code.length, 8);
    // معلم حضور، امتیاز و تکلیف ثبت می‌کند
    final sid = (st['id'] as num).toInt();
    final today = DateTime.now().toIso8601String().substring(0, 10);
    await api.post<Object?>('/classes/$id/attendance', data: {'date': today, 'records': [{'student_id': sid, 'status': 'late'}]});
    final beh = ((await api.get<Map<String, dynamic>>('/classes/$id/points', useCache: false)).data['behaviors'] as List).first;
    await api.post<Object?>('/classes/$id/points', data: {'behavior_id': beh['id'], 'student_ids': [sid]});
    await api.post<Object?>('/classes/$id/homework', data: {'title': 'تمرین ریاضی', 'due_date': today});
    await auth.logout();

    // والد تازه: ورود ← تکمیل ثبت‌نام ← فقط کد اتصال
    final pm = '0937${(DateTime.now().millisecondsSinceEpoch ~/ 11) % 10000000}'.padRight(11, '2').substring(0, 11);
    await auth.requestOtp(pm);
    await auth.verifyOtp(pm, await readOtp(pm));
    await api.post<Object?>('/onboarding', data: {'role': 'parent', 'relation': 'father', 'father_first': 'رضا', 'father_last': 'رضایی', 'child_first': 'سارا', 'child_last': 'رضایی'});
    final j = await api.post<Map<String, dynamic>>('/children/join', data: {'code': code});
    expect(j['status'], 'linked');
    final t = (await api.get<Map<String, dynamic>>('/children/$sid/today', useCache: false)).data;
    final c0 = (t['classes'] as List).first as Map;
    expect((c0['attendance'] as Map)['status'], 'late');
    expect(c0['points_today'], isNot(0));
    expect((c0['homework_open'] as List).length, 1);
    expect(c0['can_message'], isTrue);
    await api.post<Object?>('/messages', data: {'recipient_id': c0['teacher_id'], 'body': 'سلام، ممنون'});
    await auth.logout();
  }, skip: site.isEmpty ? 'HS_E2E_SITE تعریف نشده' : false);
}
