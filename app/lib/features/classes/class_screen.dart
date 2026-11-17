import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../core/api/api_exception.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../core/utils/fa.dart';
import '../../core/widgets/hs_avatar.dart';
import '../../core/widgets/states.dart';
import 'class_providers.dart';

/// صفحه‌ی یک کلاس: دانش‌آموزان، افزودن دانش‌آموز، دعوت والدین
class ClassScreen extends ConsumerWidget {
  const ClassScreen({super.key, required this.id});
  final int id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cls = ref.watch(classProvider(id));
    final info = (cls.valueOrNull?.data as Map?) ?? const {};
    final canWrite = info['access'] == 'write';
    final students = ref.watch(classStudentsProvider(id));
    return Scaffold(
      appBar: AppBar(
        title: Text('${info['title'] ?? 'کلاس'}'),
        actions: [
          if (canWrite)
            IconButton(
              key: const Key('class_invite'),
              tooltip: 'دعوت والدین (QR)',
              icon: const Icon(Icons.qr_code_2_rounded),
              onPressed: () => _showInvite(context, ref),
            ),
        ],
      ),
      floatingActionButton: canWrite
          ? FloatingActionButton.extended(
              key: const Key('add_student'),
              backgroundColor: HsColors.orange,
              foregroundColor: Colors.white,
              onPressed: () => _addStudent(context, ref),
              icon: const Icon(Icons.person_add_alt_1_rounded),
              label: const Text('دانش‌آموز'),
            )
          : null,
      body: RefreshIndicator(
        color: HsColors.orange,
        onRefresh: () async {
          ref.invalidate(classProvider(id));
          ref.invalidate(classStudentsProvider(id));
          await ref.read(classStudentsProvider(id).future);
        },
        child: students.when(
          loading: () => const LoadingView(),
          error: (e, _) => ListView(children: [const SizedBox(height: 80), ErrorView(error: e, onRetry: () => ref.invalidate(classStudentsProvider(id)))]),
          data: (r) {
            final list = ((r.data as List?) ?? const []).cast<Map>();
            return ListView(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 96),
              children: [
                if (r.fromCache) ...[OfflineBanner(savedAt: r.savedAt), const SizedBox(height: 8)],
                _Header(info: info, count: list.length),
                const SizedBox(height: 12),
                if (list.isEmpty)
                  EmptyView(
                    icon: Icons.groups_rounded,
                    title: 'هنوز دانش‌آموزی ندارید',
                    text: canWrite ? 'با دکمه‌ی «دانش‌آموز» پایین صفحه، دانش‌آموزان را اضافه کنید.' : '',
                  ),
                if (list.isNotEmpty)
                  Card(
                    child: Column(children: [
                      for (var i = 0; i < list.length; i++) ...[
                        if (i > 0) const Divider(height: 1, indent: 72),
                        ListTile(
                          leading: HsAvatar(name: '${list[i]['name']}', url: '${list[i]['avatar'] ?? ''}', size: 44),
                          title: Text('${list[i]['name']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                          trailing: Text(Fa.faDigits(i + 1), style: const TextStyle(color: Colors.black38)),
                        ),
                      ],
                    ]),
                  ),
                const SizedBox(height: 12),
                const Text('حضور و غیاب، امتیاز و تکلیف در نسخه‌ی بعدی اپ اضافه می‌شود.', textAlign: TextAlign.center, style: TextStyle(color: Colors.black45, fontSize: 13)),
              ],
            );
          },
        ),
      ),
    );
  }

  Future<void> _addStudent(BuildContext context, WidgetRef ref) async {
    final added = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => _AddStudentSheet(classId: id),
    );
    if (added == null || !context.mounted) return;
    ref.invalidate(classStudentsProvider(id));
    ref.invalidate(classProvider(id));
    ref.invalidate(myClassesProvider);
    await showDialog<void>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text('${added['name']} اضافه شد'),
        content: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('این اطلاعات را برای خانواده بفرستید (فقط همین یک بار نشان داده می‌شود):', style: TextStyle(height: 1.7)),
          const SizedBox(height: 10),
          _kv('نام کاربری', '${added['username']}'),
          _kv('رمز', '${added['password']}'),
          if ('${added['parent_code'] ?? ''}'.isNotEmpty) _kv('کد اتصال والد', '${added['parent_code']}'),
        ]),
        actions: [
          TextButton(
            onPressed: () {
              Clipboard.setData(ClipboardData(text: 'نام کاربری: ${added['username']}\nرمز: ${added['password']}\nکد اتصال والد: ${added['parent_code'] ?? ''}'));
              showMessage(c, 'کپی شد.');
            },
            child: const Text('کپی'),
          ),
          FilledButton(style: FilledButton.styleFrom(minimumSize: const Size(90, 44)), onPressed: () => Navigator.pop(c), child: const Text('باشه')),
        ],
      ),
    );
  }

  static Widget _kv(String k, String v) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 3),
        child: Row(children: [
          Text('$k: ', style: const TextStyle(color: Colors.black54)),
          Expanded(child: SelectableText(v, textDirection: TextDirection.ltr, style: const TextStyle(fontWeight: FontWeight.w700))),
        ]),
      );

  Future<void> _showInvite(BuildContext context, WidgetRef ref) {
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => _InviteSheet(classId: id),
    );
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.info, required this.count});
  final Map info;
  final int count;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(children: [
          Container(
            width: 52,
            height: 52,
            decoration: BoxDecoration(color: const Color(0xFFFFE3CC), borderRadius: BorderRadius.circular(16)),
            child: const Icon(Icons.class_rounded, color: HsColors.orange, size: 30),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('${info['title'] ?? ''}', style: Theme.of(context).textTheme.titleLarge),
              Text(
                [if ('${info['grade'] ?? ''}'.isNotEmpty) 'پایه‌ی ${info['grade']}', '${Fa.faDigits(count)} دانش‌آموز'].join('  •  '),
                style: const TextStyle(color: Colors.black54),
              ),
            ]),
          ),
        ]),
      ),
    );
  }
}

class _AddStudentSheet extends ConsumerStatefulWidget {
  const _AddStudentSheet({required this.classId});
  final int classId;

  @override
  ConsumerState<_AddStudentSheet> createState() => _AddStudentSheetState();
}

class _AddStudentSheetState extends ConsumerState<_AddStudentSheet> {
  final _form = GlobalKey<FormState>();
  final _first = TextEditingController();
  final _last = TextEditingController();
  final _phone = TextEditingController();
  bool _busy = false;

  @override
  void dispose() {
    _first.dispose();
    _last.dispose();
    _phone.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!(_form.currentState?.validate() ?? false)) return;
    setState(() => _busy = true);
    try {
      final r = await ref.read(apiProvider).post<Map<String, dynamic>>('/classes/${widget.classId}/students', data: {
        'first_name': _first.text.trim(),
        'last_name': _last.text.trim(),
        'parent_phone': Fa.mobile(_phone.text) ?? '',
      });
      if (mounted) Navigator.pop(context, r);
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 0, 20, 20 + MediaQuery.of(context).viewInsets.bottom),
      child: Form(
        key: _form,
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Text('دانش‌آموز تازه', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 12),
          TextFormField(key: const Key('st_first'), controller: _first, autofocus: true, decoration: const InputDecoration(labelText: 'نام *'), validator: (s) => (s ?? '').trim().isEmpty ? 'نام لازم است' : null),
          const SizedBox(height: 10),
          TextFormField(key: const Key('st_last'), controller: _last, decoration: const InputDecoration(labelText: 'نام خانوادگی *'), validator: (s) => (s ?? '').trim().isEmpty ? 'نام خانوادگی لازم است' : null),
          const SizedBox(height: 10),
          TextFormField(
            controller: _phone,
            keyboardType: TextInputType.phone,
            decoration: const InputDecoration(labelText: 'موبایل والد (اختیاری)', helperText: 'والد با همین شماره وارد شود، خودکار به فرزند وصل می‌شود.'),
            validator: (s) => (s ?? '').trim().isEmpty || Fa.mobile(s!) != null ? null : 'شماره موبایل درست نیست',
          ),
          const SizedBox(height: 16),
          FilledButton(
            key: const Key('st_save'),
            onPressed: _busy ? null : _save,
            child: _busy ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 3, color: Colors.white)) : const Text('افزودن'),
          ),
        ]),
      ),
    );
  }
}

class _InviteSheet extends ConsumerWidget {
  const _InviteSheet({required this.classId});
  final int classId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final inv = ref.watch(classInviteProvider(classId));
    return SizedBox(
      height: MediaQuery.of(context).size.height * 0.75,
      child: inv.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(classInviteProvider(classId))),
        data: (d) {
          final p = (d['parent'] as Map?) ?? const {};
          final qr = '${p['qr'] ?? p['code'] ?? ''}';
          final code = '${p['code'] ?? ''}';
          return ListView(
            padding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
            children: [
              Text('دعوت والدین', textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 6),
              const Text('والدین این QR را با اپ همشاگردی اسکن کنند یا کد را وارد کنند.', textAlign: TextAlign.center, style: TextStyle(color: Colors.black54, height: 1.7)),
              const SizedBox(height: 16),
              if (qr.isNotEmpty)
                Center(
                  child: Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(20), border: Border.all(color: const Color(0x14000000))),
                    child: QrImageView(data: qr, size: 220, eyeStyle: const QrEyeStyle(eyeShape: QrEyeShape.square, color: HsColors.navy), dataModuleStyle: const QrDataModuleStyle(dataModuleShape: QrDataModuleShape.square, color: HsColors.navy)),
                  ),
                ),
              const SizedBox(height: 16),
              if (code.isNotEmpty)
                Center(
                  child: SelectableText(code, textDirection: TextDirection.ltr, style: const TextStyle(fontSize: 28, fontWeight: FontWeight.w700, letterSpacing: 3, color: HsColors.orangeDark)),
                ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: () {
                  Clipboard.setData(ClipboardData(text: 'کد کلاس در اپ همشاگردی: $code\n${p['url'] ?? ''}'));
                  showMessage(context, 'کد و لینک کپی شد؛ در پیام‌رسان برای والدین بفرستید.');
                },
                icon: const Icon(Icons.copy_rounded),
                label: const Text('کپی کد و لینک'),
              ),
            ],
          );
        },
      ),
    );
  }
}
