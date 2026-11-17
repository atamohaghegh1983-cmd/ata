import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/providers.dart';
import '../../core/widgets/states.dart';
import 'class_providers.dart';

final _optionsProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) async {
  final r = await ref.read(apiProvider).get<Map<String, dynamic>>('/classes/options', useCache: false);
  return r.data;
});

/// ساخت کلاس تازه
class CreateClassScreen extends ConsumerStatefulWidget {
  const CreateClassScreen({super.key});

  @override
  ConsumerState<CreateClassScreen> createState() => _CreateClassScreenState();
}

class _CreateClassScreenState extends ConsumerState<CreateClassScreen> {
  final _form = GlobalKey<FormState>();
  final _title = TextEditingController();
  final _school = TextEditingController();
  final _city = TextEditingController();
  String? _grade;
  String? _province;
  bool _filled = false;
  bool _busy = false;

  @override
  void dispose() {
    _title.dispose();
    _school.dispose();
    _city.dispose();
    super.dispose();
  }

  void _prefill(Map<String, dynamic> o) {
    if (_filled) return;
    _filled = true;
    final d = (o['defaults'] as Map?) ?? const {};
    _school.text = '${d['school'] ?? ''}';
    _city.text = '${d['city'] ?? ''}';
    final p = '${d['province'] ?? ''}';
    if (((o['provinces'] as List?) ?? const []).contains(p)) _province = p;
  }

  Future<void> _submit() async {
    if (!(_form.currentState?.validate() ?? false)) return;
    setState(() => _busy = true);
    try {
      final r = await ref.read(apiProvider).post<Map<String, dynamic>>('/classes', data: {
        'title': _title.text.trim(),
        'grade': _grade ?? '',
        'school': _school.text.trim(),
        'province': _province ?? '',
        'city': _city.text.trim(),
      });
      ref.invalidate(myClassesProvider);
      final id = (r['class']?['id'] as num?)?.toInt();
      if (!mounted) return;
      showMessage(context, '${r['message'] ?? 'کلاس ساخته شد.'}');
      if (id != null) {
        context.pushReplacement('/class/$id');
      } else {
        context.pop();
      }
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  String? _req(String? s) => (s == null || s.trim().isEmpty) ? 'این مورد لازم است' : null;

  @override
  Widget build(BuildContext context) {
    final opts = ref.watch(_optionsProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('کلاس جدید')),
      body: opts.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(_optionsProvider)),
        data: (o) {
          _prefill(o);
          if (o['can_create'] != true) {
            return const EmptyView(
              icon: Icons.lock_outline_rounded,
              title: 'فقط معلم می‌تواند کلاس بسازد',
              text: 'اگر معلم هستید، از منوی بالا خارج شوید و هنگام تکمیل ثبت‌نام نقش «معلم» را انتخاب کنید.',
            );
          }
          final grades = ((o['grades'] as List?) ?? const []).map((e) => '$e').toList();
          final provinces = ((o['provinces'] as List?) ?? const []).map((e) => '$e').toList();
          return Form(
            key: _form,
            child: ListView(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
              children: [
                TextFormField(
                  key: const Key('class_title'),
                  controller: _title,
                  decoration: const InputDecoration(labelText: 'نام کلاس *', hintText: 'مثلاً سوم الف'),
                  validator: _req,
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  key: const Key('class_grade'),
                  initialValue: _grade,
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'پایه *'),
                  items: [for (final g in grades) DropdownMenuItem(value: g, child: Text(g))],
                  onChanged: (v) => setState(() => _grade = v),
                  validator: (v) => v == null ? 'پایه را انتخاب کنید' : null,
                ),
                const SizedBox(height: 12),
                TextFormField(controller: _school, decoration: const InputDecoration(labelText: 'نام مدرسه *'), validator: _req),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: _province,
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'استان *'),
                  items: [for (final p in provinces) DropdownMenuItem(value: p, child: Text(p))],
                  onChanged: (v) => setState(() => _province = v),
                  validator: (v) => v == null ? 'استان را انتخاب کنید' : null,
                ),
                const SizedBox(height: 12),
                TextFormField(controller: _city, decoration: const InputDecoration(labelText: 'شهر *'), validator: _req),
                const SizedBox(height: 24),
                FilledButton(
                  key: const Key('class_submit'),
                  onPressed: _busy ? null : _submit,
                  child: _busy ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 3, color: Colors.white)) : const Text('ساخت کلاس'),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}
