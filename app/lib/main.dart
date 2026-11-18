import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'app.dart';
import 'core/push.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Push.init();
  SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp]);
  runApp(const ProviderScope(child: HamshagerdiApp()));
}
