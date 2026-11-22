import '../../core/config.dart';

/// کاربر وارد شده (پاسخ /me)
class Me {
  Me(this.raw);
  final Map<String, dynamic> raw;

  int get id => (raw['id'] as num?)?.toInt() ?? 0;
  String get name => (raw['name'] ?? '') as String;
  String get mobile => (raw['mobile'] ?? '') as String;
  String get avatar => (raw['avatar'] ?? '') as String;

  /// teacher | manager | parent | student | other
  String get view => (raw['view'] ?? 'other') as String;

  /// active | pending | rejected | suspended
  String get status => (raw['status'] ?? 'active') as String;
  bool get needsOnboarding => raw['needs_onboarding'] == true;
  bool get hasChildren => raw['has_children'] == true;
  bool get canSupervise => raw['can_supervise'] == true;
  String get username => '${raw['username'] ?? ''}';
  String get email => '${raw['email'] ?? ''}';
  bool get hasPassword => raw['has_password'] == true;
  int get unread => (raw['unread_messages'] as num?)?.toInt() ?? 0;
  List<String> get roles => ((raw['roles'] as List?) ?? const []).map((e) => '$e').toList();
  String? get schoolName => (raw['school'] is Map) ? (raw['school']['name'] as String?) : null;

  /// خانه‌ی کدام نقش نشان داده شود. هر اپ فقط نقش‌های خودش را دارد:
  /// همشاگردی ← والد و دانش‌آموز؛ همشاگردی معلم ← معلم و مدیر. نقش دیگر ← wrong_app
  String get homeKind {
    if (AppConfig.isTeacherApp) {
      if (view == 'teacher') return 'teacher';
      if (view == 'manager') return 'manager';
      if (view == 'parent' || view == 'student' || hasChildren) return 'wrong_app';
      return 'other';
    }
    if (view == 'parent' || hasChildren) return 'parent';
    if (view == 'student') return 'student';
    if (view == 'teacher' || view == 'manager') return 'wrong_app';
    return 'other';
  }
}
