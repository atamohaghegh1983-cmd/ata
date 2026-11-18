import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/providers.dart';

/// کلاس‌های من (خانه‌ی معلم)
final myClassesProvider = FutureProvider.autoDispose<ApiResult<Object?>>((ref) {
  return ref.read(apiProvider).get<Object?>('/classes?mine=1');
});

final classProvider = FutureProvider.autoDispose.family<ApiResult<Object?>, int>((ref, id) {
  return ref.read(apiProvider).get<Object?>('/classes/$id');
});

final classStudentsProvider = FutureProvider.autoDispose.family<ApiResult<Object?>, int>((ref, id) {
  return ref.read(apiProvider).get<Object?>('/classes/$id/students');
});

final classInviteProvider = FutureProvider.autoDispose.family<Map<String, dynamic>, int>((ref, id) async {
  final r = await ref.read(apiProvider).get<Map<String, dynamic>>('/classes/$id/invite', useCache: false);
  return r.data;
});
