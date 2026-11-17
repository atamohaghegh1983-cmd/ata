import 'package:flutter_test/flutter_test.dart';
import 'package:hamshagerdi/core/utils/fa.dart';

void main() {
  test('ارقام فارسی و عربی به انگلیسی', () {
    expect(Fa.enDigits('۰۹۱۲٣٤٥'), '0912345');
  });

  test('شماره موبایل ایران', () {
    expect(Fa.mobile('۰۹۱۲ ۱۲۳ ۴۵۶۷'), '09121234567');
    expect(Fa.mobile('+989121234567'), '09121234567');
    expect(Fa.mobile('00989121234567'), '09121234567');
    expect(Fa.mobile('9121234567'), '09121234567');
    expect(Fa.mobile('0912123456'), isNull);
    expect(Fa.mobile('02112345678'), isNull);
  });

  test('تاریخ شمسی', () {
    expect(Fa.date('2026-03-21'), '1 فروردین 1405');
    expect(Fa.date('2026-11-17', withYear: false), '26 آبان');
    expect(Fa.date(null), '');
  });

  test('حرف اول نام', () {
    expect(Fa.initial(' سارا'), 'س');
    expect(Fa.initial(''), '؟');
  });
}
