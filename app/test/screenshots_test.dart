// تصویر صفحه‌ها در اندازه‌ی Galaxy A15 (فقط با HS_SHOTS=1):
//   HS_SHOTS=1 flutter test test/screenshots_test.dart --update-goldens
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hamshagerdi/core/api/api_client.dart';
import 'package:hamshagerdi/core/config.dart';
import 'package:hamshagerdi/core/providers.dart';
import 'package:hamshagerdi/core/storage/cache_store.dart';
import 'package:hamshagerdi/core/storage/token_store.dart';
import 'package:hamshagerdi/core/theme.dart';
import 'package:hamshagerdi/features/auth/auth_controller.dart';
import 'package:hamshagerdi/features/auth/intro_screen.dart';
import 'package:hamshagerdi/features/auth/me.dart';
import 'package:hamshagerdi/features/auth/otp_screen.dart';
import 'package:hamshagerdi/features/auth/phone_screen.dart';
import 'package:hamshagerdi/features/classes/class_screen.dart';
import 'package:hamshagerdi/features/dashboard/dashboard.dart';
import 'package:hamshagerdi/features/home/home_screen.dart';
import 'package:hamshagerdi/features/student/student_screens.dart';
import 'package:hamshagerdi/features/magazine/magazine.dart';
import 'package:hamshagerdi/features/parent/add_child_screen.dart';
import 'package:hamshagerdi/features/parent/child_screen.dart';
import 'package:hamshagerdi/features/onboarding/onboarding_screen.dart';
import 'package:hamshagerdi/features/notifications/notifications.dart';
import 'package:hamshagerdi/features/portfolio/portfolio.dart';
import 'package:hamshagerdi/features/school/school_screens.dart';
import 'package:hamshagerdi/features/classes/import_screen.dart';
import 'package:hamshagerdi/features/messages/messages_screens.dart';
import 'package:hamshagerdi/features/parent/progress_screen.dart';
import 'package:hamshagerdi/features/teach/teach_screens.dart';
import 'package:hamshagerdi/features/timetable/timetable.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

final on = Platform.environment['HS_SHOTS'] == '1';

class FakeApi extends ApiClient {
  FakeApi() : super(tokens: TokenStore(), cache: CacheStore());
  @override
  Future<ApiResult<T>> get<T>(String path, {Map<String, dynamic>? query, bool useCache = true}) async {
    final Object data = switch (path) {
      '/children' => [
          {'id': 5, 'name': 'سارا رضایی', 'avatar': '', 'classes': [{'id': 9, 'title': 'کلاس سوم الف', 'grade': 'سوم', 'teacher': 'خانم احمدی'}]},
          {'id': 6, 'name': 'علی رضایی', 'avatar': '', 'classes': [{'id': 10, 'title': 'کلاس اول ب', 'grade': 'اول', 'teacher': 'آقای کریمی'}]},
        ],
      '/children/proposals' => [
          {'id': 1, 'student': 'علی رضایی', 'class': 'کلاس اول ب', 'teacher': 'آقای کریمی', 'relation': 'parent'},
        ],
      '/children/5/today' || '/children/6/today' => {'id': 5, 'name': 'سارا رضایی', 'classes': [
          {'id': 9, 'title': 'کلاس سوم الف', 'teacher': 'خانم احمدی', 'teacher_id': 2, 'can_message': true, 'locked': [],
           'attendance': {'status': 'present', 'note': ''}, 'points_today': 3, 'points_total': 41,
           'points_items': [{'points': 2, 'behavior': 'همکاری', 'icon': '🤝', 'note': ''}, {'points': 1, 'behavior': 'دقت', 'icon': '🎯', 'note': ''}],
           'homework_open': [{'id': 1, 'title': 'تمرین ریاضی صفحه‌ی ۲۴', 'subject': 'ریاضی', 'due_date': '2026-11-19', 'status': 'pending'}],
           'announcements': [{'id': 1, 'title': 'جلسه‌ی اولیا', 'pinned': true, 'created_at': '2026-11-16'}]},
        ]},
      '/children/5/week' => {'classes': [{'id': 9, 'days': [
          for (var i = 0; i < 6; i++) {'date': '2026-11-${15 + i}', 'attendance': ['present', 'present', 'late', 'present', 'absent', null][i], 'points': [2, 1, 0, 3, -1, 0][i]}
        ]}]},
      '/children/5/pin' => {'enabled': false, 'has_pin': false},
      '/classes' => [
          {'id': 9, 'title': 'کلاس سوم الف', 'grade': 'سوم', 'teacher': 'خانم احمدی', 'students': 24, 'access': 'write'},
        ],
      '/classes/9' => {'id': 9, 'title': 'کلاس سوم الف', 'grade': 'سوم', 'access': 'write'},
      '/classes/9/students' => [
          for (final n in ['سارا رضایی', 'علی کریمی', 'نازنین محمدی', 'پارسا احمدی', 'هستی نوری', 'آرمان یزدانی', 'یاسمن صادقی', 'امیرعلی شریفی', 'ترانه موسوی'])
            {'id': n.length, 'name': n, 'avatar': '', 'points': n.length * 3 - 20, 'parents': n.length % 3 == 0 ? 0 : 1},
        ],
      '/onboarding' => {'roles': [
          {'id': 'parent', 'title': 'والدین', 'hint': 'فرزندم در کلاس است'},
          {'id': 'teacher', 'title': 'معلم', 'hint': 'کلاس دارم یا می‌سازم'},
          {'id': 'school_admin', 'title': 'مدیر مدرسه', 'hint': 'مدرسه را مدیریت می‌کنم (نیاز به تأیید)'},
        ], 'provinces': ['تهران', 'اصفهان']},
      _ when path.startsWith('/classes/9/attendance?') => {'date': '2026-11-17', 'recorded': false, 'records': [
          for (final e in {'سارا': 'رضایی', 'علی': 'کریمی', 'نازنین': 'محمدی', 'پارسا': 'احمدی', 'هستی': 'نوری'}.entries)
            {'student_id': e.key.length + e.value.length, 'name': '${e.key} ${e.value}', 'first_name': e.key, 'last_name': e.value, 'avatar': '', 'status': e.key == 'علی' ? 'absent' : (e.key == 'هستی' ? 'late' : null)},
        ]},
      _ when path.startsWith('/classes/9/attendance/week') => {'days': [{'date': '2026-11-15', 'recorded': true}, {'date': '2026-11-16', 'recorded': true}]},
      _ when path.startsWith('/articles') => {'items': [
          {'id': 1, 'title': 'چطور با کودک درباره‌ی مدرسه حرف بزنیم؟', 'excerpt': 'چند پرسش ساده که گفتگوی عصرگاهی را شیرین می‌کند…', 'date': '2026-11-10', 'grades': ['سوم'], 'audiences': [{'id': 'parents', 'title': 'والدین'}], 'locked': false},
          {'id': 2, 'title': 'برنامه‌ی تمرین ریاضی پایه‌ی سوم', 'excerpt': 'ویژه‌ی معلمان پایه‌ی سوم.', 'date': '2026-11-08', 'grades': ['سوم'], 'audiences': [{'id': 'teachers', 'title': 'معلمان'}], 'locked': true},
        ]},
      '/dashboard' => {'role': 'teacher', 'unread_messages': 2,
          'teacher': {'totals': {'classes': 2, 'students': 48, 'absent_today': 3, 'late_today': 1, 'not_recorded': 1, 'unlinked_parents': 5, 'open_homework': 4, 'points_today': 37}, 'parent_requests': 1,
            'classes': [
              {'id': 9, 'title': 'کلاس سوم الف', 'grade': 'سوم', 'students': 24, 'recorded_today': true, 'absent_today': 3, 'late_today': 1, 'points_today': 30, 'open_homework': 2, 'unlinked_parents': 2,
                'month': {'present': 300, 'absent': 12, 'late': 9, 'excused': 4, 'rate': 95}, 'top': [], 'upcoming': [],
                'budget': {'expected': 22, 'rows': [{'subject': 'ریاضی', 'planned': 120, 'done': 30}, {'subject': 'فارسی', 'planned': 140, 'done': 20}]}},
              {'id': 10, 'title': 'کلاس سوم ب', 'grade': 'سوم', 'students': 24, 'recorded_today': false, 'absent_today': 0, 'late_today': 0, 'points_today': 7, 'open_homework': 2, 'unlinked_parents': 3,
                'month': {'present': 280, 'absent': 20, 'late': 5, 'excused': 2, 'rate': 93}, 'top': [], 'upcoming': [], 'budget': {'expected': 22, 'rows': []}},
            ],
            'month': {'present': 580, 'absent': 32, 'late': 14, 'excused': 6, 'rate': 94},
            'top': [{'id': 1, 'name': 'سارا رضایی', 'avatar': '', 'points': 41}, {'id': 2, 'name': 'علی کریمی', 'avatar': '', 'points': 35}, {'id': 3, 'name': 'پارسا احمدی', 'avatar': '', 'points': 28}],
            'upcoming': [{'id': 1, 'class_id': 9, 'class': 'کلاس سوم الف', 'title': 'تمرین ریاضی صفحه‌ی ۲۴', 'subject': 'ریاضی', 'due_date': '2026-11-23'}],
            'events': [{'id': 1, 'title': 'امتحانات نوبت اول', 'date': '2026-12-21', 'type': 'academic', 'type_label': 'تقویم آموزشی', 'level_label': 'ابتدایی', 'class': '', 'can_delete': false}]},
          'manager': {'school': {'id': 1, 'name': 'دبستان امید'}, 'is_manager': true, 'pending_invites': 2,
            'totals': {'classes': 12, 'teachers': 10, 'students': 286, 'recorded_today': 9, 'absent_today': 11, 'att_rate_30': 94, 'parents_linked_pct': 71},
            'classes': [{'id': 9, 'title': 'سوم الف', 'grade': 'سوم', 'teacher': 'خانم احمدی', 'students': 24, 'homework': 8, 'att_rate': 96, 'recorded_today': true}, {'id': 10, 'title': 'سوم ب', 'grade': 'سوم', 'teacher': 'آقای کریمی', 'students': 25, 'homework': 3, 'att_rate': 81, 'recorded_today': false}, {'id': 11, 'title': 'اول الف', 'grade': 'اول', 'teacher': 'خانم نوری', 'students': 22, 'homework': 5, 'att_rate': 97, 'recorded_today': true}],
            'events': [{'id': 3, 'title': 'امتحانات نوبت اول', 'date': '2026-12-21', 'type': 'academic', 'type_label': 'تقویم آموزشی', 'level_label': 'ابتدایی', 'class': '', 'can_delete': true}],
            'teachers': [{'id': 2, 'name': 'خانم احمدی', 'classes': 1, 'students': 24, 'homework': 8, 'att_rate': 96}]},
          'parent': {'proposals': 1, 'children': [{'id': 5, 'name': 'سارا رضایی', 'avatar': '', 'attendance_today': 'present', 'absent_week': 0, 'late_week': 1, 'points_week': 12, 'open_homework': 1, 'locked': [],
              'hw_done': 7, 'hw_total': 9, 'stars': 41, 'classes': [{'id': 9, 'title': 'کلاس سوم الف', 'teacher': 'خانم احمدی', 'teacher_id': 2}],
              'pending': [{'id': 1, 'class_id': 9, 'title': 'تمرین ریاضی', 'subject': 'ریاضی', 'due_date': '2026-11-23'}],
              'events': [{'date': '2026-11-26', 'icon': '🎓', 'title': 'جلسه‌ی اولیا و مربیان', 'sub': 'جلسه', 'kind': 'event'}]}],
            'announcements': [{'id': 1, 'class': 'کلاس سوم الف', 'title': 'جلسه‌ی اولیا', 'body': 'جلسه‌ی اولیا پنجشنبه ساعت ۱۰ در نمازخانه برگزار می‌شود.', 'created_at': '2026-11-16'}]}},
      '/student/summary' => {'classes': [{'id': 9, 'title': 'کلاس سوم الف', 'teacher': 'خانم احمدی', 'points_total': 41,
          'points_recent': [{'points': 2, 'behavior': 'همکاری', 'icon': '🤝', 'at': '2026-11-17'}],
          'homework': [{'id': 1, 'title': 'تمرین ریاضی', 'subject': 'ریاضی', 'due_date': '2026-11-21', 'status': 'pending', 'feedback': ''}, {'id': 2, 'title': 'املا', 'subject': 'فارسی', 'due_date': '2026-11-15', 'status': 'done', 'feedback': 'آفرین'}]}]},
      '/classes?mine=1' => [
          {'id': 9, 'title': 'کلاس سوم الف', 'grade': 'سوم', 'teacher': 'زهرا احمدی', 'students': 24, 'access': 'write'},
        ],
      '/notifications' => {'unread': 2, 'items': [
          {'id': 1, 'type': 'attendance', 'title': 'غیبت', 'body': 'سارا امروز غایب ثبت شد.', 'is_read': false, 'created_at': '2026-11-17', 'data': {'child': 5}},
          {'id': 2, 'type': 'portfolio', 'title': 'دیوار کلاس', 'body': 'نقاشی سارا روی دیوار کلاس رفت 🎉', 'is_read': false, 'created_at': '2026-11-16', 'data': {'class': 9}},
          {'id': 3, 'type': 'homework', 'title': 'تکلیف تازه', 'body': 'تمرین ریاضی صفحه‌ی ۲۴', 'is_read': true, 'created_at': '2026-11-15', 'data': {}},
        ]},
      '/requests' => [
          {'id': 4, 'parent': 'رضا کریمی', 'parent_mobile': '09351234567', 'child_name': 'علی کریمی', 'class': 'کلاس سوم الف',
            'candidates': [{'id': 21, 'name': 'علی کریمی', 'class': 'کلاس سوم الف'}, {'id': 22, 'name': 'علی کاظمی', 'class': 'کلاس سوم الف'}]},
        ],
      '/school/profile' => {'can_edit': true, 'approved': false, 'school': null, 'levels': {'': '— انتخاب —', 'primary': 'ابتدایی'}, 'provinces': ['تهران', 'اصفهان'], 'positions': {'teacher': 'معلم'}},
      '/portfolio/student/5' => {'student': {'id': 5, 'name': 'سارا رضایی', 'avatar': '', 'about': 'عاشق نقاشی و کتاب‌های داستان'}, 'can_post': true, 'today': 0, 'limit': 2, 'posts': [
          {'id': 1, 'student_id': 5, 'student': 'سارا ر.', 'avatar': '', 'class_id': 9, 'title': 'نقاشی پاییز', 'text': 'درخت‌های حیاط مدرسه', 'type': 'image', 'image': '', 'on_wall': true, 'cheers': 7, 'cheered': false, 'at': '2026-11-16 10:00:00'},
          {'id': 2, 'student_id': 5, 'student': 'سارا ر.', 'avatar': '', 'class_id': 9, 'title': 'کتابی که خواندم', 'text': 'شازده کوچولو را تمام کردم', 'type': 'text', 'image': '', 'on_wall': false, 'cheers': 2, 'cheered': true, 'at': '2026-11-17 18:00:00'},
        ]},
      '/messages' => [
          {'user_id': 7, 'name': 'مریم رضایی', 'label': 'والد سارا رضایی', 'avatar': '', 'preview': 'سلام خانم احمدی', 'at': '2026-11-18', 'unread': true, 'unread_count': 5},
          {'user_id': 8, 'name': 'رضا کریمی', 'label': 'والد علی کریمی', 'avatar': '', 'preview': 'ممنون', 'at': '2026-11-17', 'unread': false, 'unread_count': 0},
        ],
      '/messages/7' => {'partner': {'id': 7, 'name': 'مریم رضایی', 'label': 'والد سارا رضایی', 'avatar': ''}, 'can_reply': true, 'messages': [
          {'id': 1, 'mine': false, 'body': 'سلام خانم احمدی، سارا امروز کمی تب داشت.', 'at': '2026-11-17 07:40:00', 'can_delete': true},
          {'id': 2, 'mine': true, 'body': 'سلام، ممنون که خبر دادید. ان‌شاءالله زود خوب شود 🌷', 'at': '2026-11-17 08:05:00', 'can_delete': true, 'receipt': 'read', 'edited': true},
          {'id': 3, 'mine': true, 'body': 'تکلیف امروز را برایش می‌فرستم.', 'at': '2026-11-19 10:15:00', 'can_delete': true, 'receipt': 'delivered'},
          {'id': 4, 'mine': true, 'body': 'جلسه‌ی اولیا پنجشنبه است.', 'at': '2026-11-19 10:16:00', 'can_delete': true, 'receipt': 'sent'},
        ]},
      '/calendar' => {'events': [
          {'id': 1, 'title': 'امتحانات نوبت اول', 'date': '2026-12-21', 'end_date': '2027-01-08', 'type': 'academic', 'type_label': 'تقویم آموزشی', 'level_label': 'ابتدایی', 'class': '', 'can_delete': false},
          {'id': 2, 'title': 'اردوی علمی', 'date': '2026-11-25', 'type': 'ceremony', 'type_label': 'جشن / مراسم', 'class': 'کلاس سوم الف', 'can_delete': true},
          {'id': 0, 'title': 'شهادت حضرت فاطمه (س)', 'date': '2026-11-24', 'type': 'holiday', 'type_label': 'تعطیل', 'class': '', 'can_delete': false},
        ], 'types': {'academic': 'تقویم آموزشی', 'cultural': 'پرورشی و مناسبتی'}, 'levels': {'primary': 'ابتدایی'}, 'can_add_school': false, 'can_add_class': true, 'classes': [{'id': 9, 'title': 'کلاس سوم الف'}]},
      '/classes/9/assessments' => {'can_edit': true, 'items': [
          {'id': 1, 'title': 'املای هفته‌ی دوم', 'subject': 'فارسی', 'scale': 'descriptive', 'date': '2026-11-15', 'recorded': 24, 'students': 24},
          {'id': 2, 'title': 'آزمون ریاضی فصل ۳', 'subject': 'ریاضی', 'scale': 'numeric', 'date': '2026-11-17', 'recorded': 10, 'students': 24},
        ]},
      '/assessments/1' => {'id': 1, 'title': 'املای هفته‌ی دوم', 'subject': 'فارسی', 'scale': 'descriptive', 'date': '2026-11-15', 'can_edit': true,
          'levels': [{'key': 'excellent', 'label': 'خیلی خوب', 'color': '#10B981'}, {'key': 'good', 'label': 'خوب', 'color': '#079E91'}, {'key': 'acceptable', 'label': 'قابل قبول', 'color': '#F59E0B'}, {'key': 'needs_work', 'label': 'نیاز به تلاش', 'color': '#EF4444'}],
          'students': [{'id': 1, 'name': 'سارا رضایی', 'avatar': '', 'level': 'excellent', 'comment': ''}, {'id': 2, 'name': 'علی کریمی', 'avatar': '', 'level': 'good', 'comment': ''}, {'id': 3, 'name': 'پارسا احمدی', 'avatar': '', 'level': '', 'comment': ''}]},
      '/classes/9/budget' => {'can_edit': true, 'expected': 22, 'subjects': ['ریاضی', 'فارسی'], 'rows': [{'subject': 'ریاضی', 'planned': 120, 'done': 30}, {'subject': 'فارسی', 'planned': 140, 'done': 20}, {'subject': 'علوم', 'planned': 60, 'done': 9}]},
      '/worksheets' => {'items': [
          {'id': 1, 'title': 'کاربرگ جمع و تفریق دو رقمی', 'grade': 'سوم دبستان', 'subject': 'ریاضی', 'topic': 'جمع با انتقال', 'difficulty': 'متوسط', 'pdf': 'https://example.com/a.pdf', 'url': ''},
          {'id': 2, 'title': 'کاربرگ نشانه‌ی «ث»', 'grade': 'اول دبستان', 'subject': 'فارسی', 'topic': '', 'difficulty': 'آسان', 'pdf': '', 'url': 'https://example.com'},
        ], 'grades': {'1': 'اول دبستان', '3': 'سوم دبستان'}, 'subjects': {'math': 'ریاضی', 'farsi': 'فارسی'}, 'classes': [{'id': 9, 'title': 'کلاس سوم الف'}]},
      _ when path.startsWith('/children/5/progress') => {'classes': [{'class_id': 9, 'class': 'کلاس سوم الف', 'attendance': {'present': 18, 'absent': 1, 'late': 1, 'excused': 0, 'rate': 95}, 'points_month': 14, 'points_total': 41, 'homework': {'done': 7, 'total': 9},
          'assessments': [{'title': 'املای هفته‌ی دوم', 'subject': 'فارسی', 'value': 'خیلی خوب', 'color': '#10B981', 'comment': 'آفرین'}], 'practice': 3}]},
      '/me/timetable' || '/classes/9/timetable' => {'mode': 'teacher', 'can_edit': true, 'class': 'کلاس سوم الف',
          'bells': {'days': 5, 'periods': 4, 'source': 'default', 'day_names': ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه'], 'slots': [
            {'type': 'class', 'start': '07:45', 'end': '08:30', 'period': 1}, {'type': 'class', 'start': '08:35', 'end': '09:20', 'period': 2},
            {'type': 'break', 'start': '09:20', 'end': '09:40', 'period': 0},
            {'type': 'class', 'start': '09:40', 'end': '10:25', 'period': 3}, {'type': 'class', 'start': '10:30', 'end': '11:15', 'period': 4}]},
          'cells': [
            {'day': 0, 'period': 1, 'subject': 'ریاضی', 'teacher': 'زهرا احمدی', 'class': 'سوم الف', 'span': 2},
            {'day': 1, 'period': 1, 'subject': 'علوم تجربی', 'teacher': 'زهرا احمدی', 'class': 'سوم الف'}, {'day': 2, 'period': 3, 'subject': 'زبان انگلیسی', 'teacher': 'آقای صادقی', 'class': 'سوم ب'},
            {'day': 3, 'period': 1, 'subject': 'هدیه‌های آسمان', 'teacher': 'زهرا احمدی', 'class': 'سوم الف'}, {'day': 4, 'period': 2, 'subject': 'هنر', 'teacher': 'زهرا احمدی', 'class': 'سوم الف'},
            {'day': 3, 'period': 4, 'subject': 'تربیت‌بدنی', 'teacher': 'آقای کریمی', 'class': 'سوم الف'}],
          'teachers': [{'id': 2, 'name': 'زهرا احمدی'}],
          'score': {'score': 87, 'checks': [{'label': 'بدون تداخل زمانی معلم', 'ok': true, 'detail': ''}, {'label': 'همه‌ی زنگ‌ها درس دارند', 'ok': false, 'detail': '۱۳ زنگ خالی'}, {'label': 'تعادل دروس در هر روز', 'ok': true, 'detail': ''}, {'label': 'استراحت کافی معلم‌ها', 'ok': true, 'detail': ''}]}},
      _ when path.startsWith('/teacher/feed') => {'announcements': [{'id': 1, 'class_id': 9, 'class': 'کلاس سوم الف', 'title': 'جلسه‌ی اولیا', 'body': 'پنجشنبه ساعت ۱۰ در نمازخانه.', 'state': 'active', 'show_until': '2026-11-26', 'can_edit': true, 'created_at': '2026-11-18'}],
          'wall': [{'id': 1, 'class_id': 9, 'title': 'نقاشی پاییز', 'student': 'سارا رضایی', 'image': '', 'pinned': true}, {'id': 2, 'class_id': 9, 'title': 'کاردستی', 'student': 'علی کریمی', 'image': '', 'pinned': false}]},
      _ when path.startsWith('/calendar?') => {'events': [{'date': '2026-11-24', 'title': 'تعطیل رسمی', 'holiday': true}, {'date': '2026-11-26', 'title': 'جلسه‌ی اولیا', 'holiday': false}],
          'tasks': [{'date': '2026-11-23', 'kind': 'hw', 'title': 'تمرین ریاضی', 'class': 'سوم الف', 'class_id': 9}, {'date': '2026-11-30', 'kind': 'assess', 'title': 'املا', 'class': 'سوم الف', 'class_id': 9}]},
      _ when path.startsWith('/messages') => <Object>[],
      _ => <String, Object>{},
    };
    return ApiResult<T>(data as T);
  }
}

class FakeAuth extends AuthController {
  @override
  AuthState build() => AuthState(AuthStatus.loggedIn, me: Me({'id': 3, 'name': 'مریم رضایی', 'view': 'parent', 'has_children': true, 'mobile': '09121234567'}));
}

class FakeTeacher extends AuthController {
  @override
  AuthState build() => AuthState(AuthStatus.loggedIn, me: Me({'id': 2, 'name': 'زهرا احمدی', 'view': 'teacher', 'mobile': '09121234567'}));
}

Future<void> loadFonts() async {
  final v = FontLoader('Vazirmatn');
  for (final w in ['Regular', 'Medium', 'Bold']) {
    v.addFont(rootBundle.load('assets/fonts/Vazirmatn-UI-FD-$w.ttf'));
  }
  await v.load();
  await (FontLoader('Lalezar')..addFont(rootBundle.load('assets/fonts/Lalezar-Regular.ttf'))).load();
  await (FontLoader('MaterialIcons')..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'))).load();
}

void main() {
  Future<void> shot(WidgetTester t, String name, Widget screen, {bool onboarding = false, bool teacher = false, int tab = -1, Key? tapKey, bool dark = false, String? app}) async {
    HsColors.dark = dark;
    AppConfig.app = app ?? (teacher ? 'teacher' : 'family');
    addTearDown(() => AppConfig.app = 'family');
    addTearDown(() => HsColors.dark = false);
    t.view.physicalSize = const Size(1080, 2340);
    t.view.devicePixelRatio = 2.625;
    addTearDown(t.view.reset);
    await t.runAsync(loadFonts);
    await t.pumpWidget(ProviderScope(
      overrides: [apiProvider.overrideWith((_) => FakeApi()), authProvider.overrideWith(teacher ? FakeTeacher.new : FakeAuth.new)],
      child: MaterialApp(
        debugShowCheckedModeBanner: false,
        theme: HsTheme.light(),
        locale: const Locale('fa', 'IR'),
        supportedLocales: const [Locale('fa', 'IR')],
        localizationsDelegates: const [GlobalMaterialLocalizations.delegate, GlobalWidgetsLocalizations.delegate, GlobalCupertinoLocalizations.delegate],
        home: Directionality(textDirection: TextDirection.rtl, child: screen),
      ),
    ));
    await t.runAsync(() async {
      for (final e in find.byType(Image).evaluate()) {
        await precacheImage((e.widget as Image).image, e);
      }
    });
    await t.pump(const Duration(milliseconds: 300));
    if (tab >= 0) {
      await t.tap(find.byType(Tab).at(tab));
      await t.pumpAndSettle();
    }
    if (tapKey != null) {
      await t.tap(find.byTooltip('جدول هفته'));
      await t.pump(const Duration(milliseconds: 300));
    }
    if (onboarding && AppConfig.app == 'family') {
      await t.tap(find.byKey(const Key('role_parent')));
      await t.pump();
    }
    await expectLater(find.byType(MaterialApp), matchesGoldenFile('../build/shots/$name.png'));
  }

  testWidgets('intro', (t) => shot(t, '1-intro', const IntroScreen()), skip: !on);
  testWidgets('phone', (t) => shot(t, '2-phone', const PhoneScreen()), skip: !on);
  testWidgets('otp', (t) => shot(t, '3-otp', const OtpScreen(mobile: '09121234567', wait: 42)), skip: !on);
  testWidgets('onboarding', (t) => shot(t, '4-onboarding', const OnboardingScreen(), onboarding: true), skip: !on);
  testWidgets('home', (t) => shot(t, '5-home-parent', const HomeScreen()), skip: !on);
  testWidgets('child', (t) => shot(t, '6-child', const ChildScreen(id: 5, name: 'سارا رضایی')), skip: !on);
  testWidgets('teacher', (t) => shot(t, '7-teacher-class', const ClassScreen(id: 9), teacher: true), skip: !on);
  testWidgets('attendance', (t) => shot(t, '8-attendance', const ClassScreen(id: 9), teacher: true, tab: 1), skip: !on);
  testWidgets('add child', (t) => shot(t, '9-add-child', const AddChildScreen()), skip: !on);
  testWidgets('dash-teacher', (t) => shot(t, '11-dash-teacher', const Scaffold(body: DashboardView(kind: 'teacher'))), skip: !on);
  testWidgets('dash-parent', (t) => shot(t, '12-dash-parent', const Scaffold(body: DashboardView(kind: 'parent'))), skip: !on);
  testWidgets('dash-manager', (t) => shot(t, '13-dash-manager', const Scaffold(body: DashboardView(kind: 'manager'))), skip: !on);
  testWidgets('student', (t) => shot(t, '14-student', const Scaffold(body: StudentHome())), skip: !on);
  testWidgets('notifications', (t) => shot(t, '15-notifications', const NotificationsScreen()), skip: !on);
  testWidgets('requests', (t) => shot(t, '16-requests', const RequestsScreen(), teacher: true), skip: !on);
  testWidgets('school', (t) => shot(t, '17-school-form', const SchoolFormScreen()), skip: !on);
  testWidgets('portfolio', (t) => shot(t, '18-portfolio', const Scaffold(body: PortfolioView(studentId: 5))), skip: !on);
  testWidgets('import', (t) => shot(t, '19-import', const ImportStudentsScreen(classId: 9), teacher: true), skip: !on);
  testWidgets('teacher-home', (t) => shot(t, '20-teacher-home', const HomeScreen(), teacher: true), skip: !on);
  testWidgets('inbox', (t) => shot(t, '21-inbox', const Scaffold(body: InboxView()), teacher: true), skip: !on);
  testWidgets('thread', (t) => shot(t, '22-thread', const ThreadScreen(userId: 7)), skip: !on);
  testWidgets('calendar', (t) => shot(t, '23-calendar', const CalendarScreen(), teacher: true), skip: !on);
  testWidgets('assessments', (t) => shot(t, '24-assessments', const AssessmentsScreen(classId: 9), teacher: true), skip: !on);
  testWidgets('assessment', (t) => shot(t, '25-assessment', const AssessmentScreen(id: 1), teacher: true), skip: !on);
  testWidgets('budget', (t) => shot(t, '26-budget', const BudgetScreen(classId: 9), teacher: true), skip: !on);
  testWidgets('worksheets', (t) => shot(t, '27-worksheets', const WorksheetsScreen(), teacher: true), skip: !on);
  testWidgets('progress', (t) => shot(t, '28-progress', const ChildProgressScreen(childId: 5, name: 'سارا')), skip: !on);
  testWidgets('timetable-day', (t) => shot(t, '29-timetable-day', const TimetableScreen(), teacher: true), skip: !on);
  testWidgets('timetable-week', (t) => shot(t, '30-timetable-week', const TimetableScreen(classId: 9), teacher: true, tapKey: const Key('grid')), skip: !on);
  testWidgets('bells', (t) => shot(t, '31-bells', const BellsScreen(classId: 9, title: 'سوم الف'), teacher: true), skip: !on);
  testWidgets('dark-dash', (t) => shot(t, '32-dark-dash', const HomeScreen(), teacher: true, dark: true), skip: !on);
  testWidgets('dark-thread', (t) => shot(t, '33-dark-thread', const ThreadScreen(userId: 7), dark: true), skip: !on);
  testWidgets('dark-week', (t) => shot(t, '34-dark-week', const TimetableScreen(classId: 9), teacher: true, tapKey: const Key('grid'), dark: true), skip: !on);
  testWidgets('wrong-app', (t) => shot(t, '35-wrong-app', const HomeScreen(), teacher: true, app: 'family'), skip: !on);
  testWidgets('teacher-onboarding', (t) => shot(t, '36-teacher-onboarding', const OnboardingScreen(), onboarding: true, app: 'teacher'), skip: !on);
  testWidgets('teacher-phone', (t) => shot(t, '37-teacher-phone', const PhoneScreen(), app: 'teacher'), skip: !on);
  testWidgets('magazine', (t) => shot(t, '10-magazine', const Scaffold(body: MagazineView(showMineFilter: true))), skip: !on);
}
