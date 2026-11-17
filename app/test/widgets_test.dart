import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hamshagerdi/core/theme.dart';
import 'package:hamshagerdi/core/widgets/hs_avatar.dart';
import 'package:hamshagerdi/features/auth/phone_screen.dart';

Widget wrap(Widget w) => ProviderScope(
      child: MaterialApp(
        theme: HsTheme.light(),
        home: Directionality(textDirection: TextDirection.rtl, child: w),
      ),
    );

void main() {
  testWidgets('آواتار بدون عکس: حرف اول نام در دایره‌ی رنگی', (t) async {
    await t.pumpWidget(wrap(const Scaffold(body: HsAvatar(name: 'سارا رضایی'))));
    expect(find.text('س'), findsOneWidget);
    expect(HsAvatar.colorFor('سارا'), HsAvatar.colorFor('سارا'));
  });

  testWidgets('شماره‌ی نادرست پیام خطای فارسی می‌دهد و درخواست نمی‌فرستد', (t) async {
    await t.pumpWidget(wrap(const PhoneScreen()));
    await t.enterText(find.byKey(const Key('phone_field')), '0912');
    await t.tap(find.byKey(const Key('phone_submit')));
    await t.pump();
    expect(find.textContaining('شماره موبایل درست نیست'), findsOneWidget);
  });
}
