import 'package:shamsi_date/shamsi_date.dart';

/// ابزارهای فارسی: ارقام، شماره موبایل، تاریخ شمسی
class Fa {
  Fa._();

  static const _fa = '۰۱۲۳۴۵۶۷۸۹';
  static const _ar = '٠١٢٣٤٥٦٧٨٩';

  /// ارقام فارسی/عربی ← انگلیسی
  static String enDigits(String s) {
    final b = StringBuffer();
    for (final ch in s.runes) {
      final c = String.fromCharCode(ch);
      final i = _fa.indexOf(c);
      final j = _ar.indexOf(c);
      b.write(i >= 0 ? '$i' : (j >= 0 ? '$j' : c));
    }
    return b.toString();
  }

  /// ارقام انگلیسی ← فارسی (برای متن‌هایی که با فونت دیگر نمایش داده می‌شود)
  static String faDigits(Object s) {
    return s.toString().replaceAllMapped(RegExp(r'\d'), (m) => _fa[int.parse(m[0]!)]);
  }

  /// شماره‌ی موبایل ایران به شکل 09xxxxxxxxx یا null
  static String? mobile(String raw) {
    var d = enDigits(raw).replaceAll(RegExp(r'\D'), '');
    if (d.startsWith('0098')) {
      d = d.substring(4);
    } else if (d.startsWith('98') && d.length == 12) {
      d = d.substring(2);
    }
    if (d.length == 10 && d.startsWith('9')) d = '0$d';
    return RegExp(r'^09\d{9}$').hasMatch(d) ? d : null;
  }

  /// '2026-11-17' ← «۲۶ آبان ۱۴۰۵»
  static String date(String? ymd, {bool withYear = true}) {
    if (ymd == null || ymd.length < 10) return '';
    final dt = DateTime.tryParse(ymd.substring(0, 10));
    if (dt == null) return '';
    final j = Jalali.fromDateTime(dt);
    final f = j.formatter;
    return withYear ? '${f.d} ${f.mN} ${f.yyyy}' : '${f.d} ${f.mN}';
  }

  /// نام روز هفته‌ی شمسی
  static String weekday(DateTime dt) => Jalali.fromDateTime(dt).formatter.wN;

  static String ymd(DateTime dt) =>
      '${dt.year.toString().padLeft(4, '0')}-${dt.month.toString().padLeft(2, '0')}-${dt.day.toString().padLeft(2, '0')}';

  /// حرف اول نام برای آواتار
  static String initial(String name) {
    final t = name.trim();
    if (t.isEmpty) return '؟';
    return String.fromCharCode(t.runes.first);
  }
}

/// هفته‌ی ایرانی: شنبه تا جمعه؛ مدرسه‌ها شنبه تا چهارشنبه باز، پنجشنبه و جمعه تعطیل
class Week {
  Week._();

  /// شنبه‌ی هفته‌ی این روز
  static DateTime saturdayOf(DateTime d) {
    final day = DateTime(d.year, d.month, d.day);
    final back = (day.weekday - DateTime.saturday + 7) % 7;
    return day.subtract(Duration(days: back));
  }

  /// هر هفت روز (شنبه تا جمعه)
  static List<DateTime> allDays(DateTime saturday) => List.generate(7, (i) => saturday.add(Duration(days: i)));

  /// روزهای باز مدرسه (شنبه تا چهارشنبه)
  static List<DateTime> schoolDays(DateTime saturday) => List.generate(5, (i) => saturday.add(Duration(days: i)));

  /// پنجشنبه و جمعه
  static bool isHoliday(DateTime d) => d.weekday == DateTime.thursday || d.weekday == DateTime.friday;

  /// نزدیک‌ترین روز مدرسه تا امروز (پنجشنبه و جمعه ← چهارشنبه)
  static DateTime lastSchoolDay(DateTime d) {
    var x = DateTime(d.year, d.month, d.day);
    while (isHoliday(x)) {
      x = x.subtract(const Duration(days: 1));
    }
    return x;
  }

  /// n روز مدرسه بعد از امروز (پنجشنبه و جمعه شمرده نمی‌شوند)
  static DateTime addSchoolDays(DateTime from, int n) {
    var x = DateTime(from.year, from.month, from.day);
    var left = n;
    while (left > 0) {
      x = x.add(const Duration(days: 1));
      if (!isHoliday(x)) left--;
    }
    return x;
  }

  /// نام کوتاه روز، به ترتیب از شنبه
  static const shortNames = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];

  static String shortName(DateTime d) => shortNames[(d.weekday - DateTime.saturday + 7) % 7];
}
