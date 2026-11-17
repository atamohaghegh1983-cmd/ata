import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:share_plus/share_plus.dart';

import '../../core/api/api_exception.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../core/utils/fa.dart';
import '../../core/widgets/hs_avatar.dart';
import '../../core/widgets/states.dart';
import '../../core/widgets/ui.dart';
import 'attendance_tab.dart';
import 'class_providers.dart';
import 'homework_tab.dart';
import 'student_sheet.dart';

/// صفحه‌ی کلاس: دانش‌آموزان، حضور و غیاب، تکالیف، اطلاعیه‌ها
class ClassScreen extends ConsumerWidget {
  const ClassScreen({super.key, required this.id});
  final int id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cls = ref.watch(classProvider(id));
    final info = (cls.valueOrNull?.data as Map?) ?? const {};
    final canWrite = info['access'] == 'write';
    return DefaultTabController(
      length: 4,
      child: Scaffold(
        appBar: AppBar(
          title: Text('${info['title'] ?? 'کلاس'}'),
          actions: [
            if (canWrite)
              IconButton(
                key: const Key('class_invite'),
                tooltip: 'دعوت والدین (QR)',
                icon: const Icon(Icons.qr_code_2_rounded),
                onPressed: () => showModalBottomSheet<void>(context: context, isScrollControlled: true, showDragHandle: true, builder: (_) => InviteSheet(classId: id)),
              ),
          ],
          bottom: const TabBar(
            isScrollable: false,
            labelColor: HsColors.orangeDark,
            indicatorColor: HsColors.orange,
            labelStyle: TextStyle(fontFamily: 'Vazirmatn', fontWeight: FontWeight.w700, fontSize: 13),
            tabs: [
              Tab(icon: Icon(Icons.groups_rounded), text: 'دانش‌آموزان'),
              Tab(icon: Icon(Icons.fact_check_rounded), text: 'حضور'),
              Tab(icon: Icon(Icons.menu_book_rounded), text: 'تکالیف'),
              Tab(icon: Icon(Icons.campaign_rounded), text: 'اطلاعیه'),
            ],
          ),
        ),
        body: TabBarView(children: [
          _StudentsTab(classId: id, canWrite: canWrite),
          AttendanceTab(classId: id, canWrite: canWrite),
          HomeworkTab(classId: id, canWrite: canWrite),
          AnnouncementsTab(classId: id, canWrite: canWrite),
        ]),
      ),
    );
  }
}

/* ------------------------------ دانش‌آموزان ------------------------------ */

class _StudentsTab extends ConsumerWidget {
  const _StudentsTab({required this.classId, required this.canWrite});
  final int classId;
  final bool canWrite;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final students = ref.watch(classStudentsProvider(classId));
    return Scaffold(
      backgroundColor: Colors.transparent,
      floatingActionButton: canWrite
          ? FloatingActionButton.extended(
              key: const Key('add_student'),
              heroTag: 'add_student',
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
          ref.invalidate(classStudentsProvider(classId));
          await ref.read(classStudentsProvider(classId).future);
        },
        child: students.when(
          loading: () => const LoadingView(),
          error: (e, _) => ListView(children: [const SizedBox(height: 80), ErrorView(error: e, onRetry: () => ref.invalidate(classStudentsProvider(classId)))]),
          data: (r) {
            final list = ((r.data as List?) ?? const []).cast<Map>();
            final unlinked = list.where((s) => (s['parents'] ?? 0) == 0).length;
            return ListView(
              padding: const EdgeInsets.fromLTRB(14, 12, 14, 96),
              children: [
                if (r.fromCache) ...[OfflineBanner(savedAt: r.savedAt), const SizedBox(height: 8)],
                if (list.isEmpty)
                  EmptyView(icon: Icons.groups_rounded, title: 'هنوز دانش‌آموزی ندارید', text: canWrite ? 'با دکمه‌ی «دانش‌آموز»، نام دانش‌آموز و موبایل والدش را وارد کنید.' : ''),
                if (list.isNotEmpty && canWrite && unlinked > 0)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: Text('${Fa.faDigits(unlinked)} دانش‌آموز هنوز والد متصل ندارد؛ روی هر کدام بزنید و کد اتصال را برای والدش بفرستید.',
                        style: const TextStyle(color: Colors.black54, fontSize: 13, height: 1.7)),
                  ),
                if (list.isNotEmpty)
                  GridView.count(
                    crossAxisCount: 3,
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    mainAxisSpacing: 10,
                    crossAxisSpacing: 10,
                    childAspectRatio: 0.82,
                    children: [
                      for (final s in list)
                        _StudentTile(
                          s: s,
                          onTap: () => showStudentSheet(context, classId: classId, studentId: (s['id'] as num).toInt(), canWrite: canWrite),
                        ),
                    ],
                  ),
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
      builder: (_) => _AddStudentSheet(classId: classId),
    );
    if (added == null || !context.mounted) return;
    ref.invalidate(classStudentsProvider(classId));
    ref.invalidate(classProvider(classId));
    ref.invalidate(myClassesProvider);
    await showParentCodeDialog(context, name: '${added['name']}', code: '${added['parent_code'] ?? ''}', share: '${added['share_text'] ?? ''}', justAdded: true);
  }
}

class _StudentTile extends StatelessWidget {
  const _StudentTile({required this.s, required this.onTap});
  final Map s;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final pts = (s['points'] as num?)?.toInt() ?? 0;
    final linked = (s['parents'] ?? 0) > 0;
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(18),
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(8),
          child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
            Stack(clipBehavior: Clip.none, children: [
              HsAvatar(name: '${s['name']}', url: '${s['avatar'] ?? ''}', size: 56),
              PositionedDirectional(
                end: -6,
                top: -4,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                  decoration: BoxDecoration(color: pts < 0 ? HsColors.red : HsColors.green, borderRadius: BorderRadius.circular(10)),
                  child: Text(Fa.faDigits(pts), style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700)),
                ),
              ),
            ]),
            const SizedBox(height: 6),
            Text('${s['name']}', maxLines: 2, textAlign: TextAlign.center, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, height: 1.4)),
            if (!linked) const Text('بدون والد', style: TextStyle(fontSize: 11, color: HsColors.orangeDark)),
          ]),
        ),
      ),
    );
  }
}

/// پنجره‌ی کد اتصال والد (بعد از افزودن دانش‌آموز یا از پروفایل او)
Future<void> showParentCodeDialog(BuildContext context, {required String name, required String code, required String share, bool justAdded = false}) {
  return showDialog<void>(
    context: context,
    builder: (c) => AlertDialog(
      title: Text(justAdded ? '$name اضافه شد' : 'کد اتصال والد'),
      content: Column(mainAxisSize: MainAxisSize.min, children: [
        const Text('والد، اپ همشاگردی را نصب می‌کند، با موبایل خودش وارد می‌شود و در «افزودن فرزند» این کد را می‌زند:', style: TextStyle(height: 1.8)),
        const SizedBox(height: 14),
        SelectableText(code, textDirection: TextDirection.ltr, style: const TextStyle(fontSize: 30, fontWeight: FontWeight.w700, letterSpacing: 4, color: HsColors.orangeDark)),
        const SizedBox(height: 6),
        const Text('اگر موبایل والد را وارد کرده باشید، بعد از ورود فقط یک «تأیید» لازم است.', textAlign: TextAlign.center, style: TextStyle(color: Colors.black54, fontSize: 12, height: 1.7)),
      ]),
      actions: [
        TextButton.icon(
          onPressed: () {
            Clipboard.setData(ClipboardData(text: code));
            showMessage(c, 'کد کپی شد.');
          },
          icon: const Icon(Icons.copy_rounded),
          label: const Text('کپی'),
        ),
        FilledButton.icon(
          style: FilledButton.styleFrom(minimumSize: const Size(120, 44)),
          onPressed: () => SharePlus.instance.share(ShareParams(text: share.isNotEmpty ? share : 'کد اتصال به $name در اپ همشاگردی: $code')),
          icon: const Icon(Icons.send_rounded),
          label: const Text('ارسال برای والد'),
        ),
      ],
    ),
  );
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
            key: const Key('st_phone'),
            controller: _phone,
            keyboardType: TextInputType.phone,
            decoration: const InputDecoration(labelText: 'موبایل والد', helperText: 'والد با همین شماره وارد شود، فقط یک «تأیید» می‌زند و وصل می‌شود.', helperMaxLines: 2),
            validator: (s) => (s ?? '').trim().isEmpty || Fa.mobile(s!) != null ? null : 'شماره موبایل درست نیست',
          ),
          const SizedBox(height: 16),
          FilledButton(key: const Key('st_save'), onPressed: _busy ? null : _save, child: busyLabel(_busy, 'افزودن')),
        ]),
      ),
    );
  }
}

/* ------------------------------ اطلاعیه‌ها ------------------------------ */

class AnnouncementsTab extends ConsumerWidget {
  const AnnouncementsTab({super.key, required this.classId, required this.canWrite});
  final int classId;
  final bool canWrite;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final path = '/classes/$classId/announcements';
    final res = ref.watch(getProvider(path));
    return Scaffold(
      backgroundColor: Colors.transparent,
      floatingActionButton: canWrite
          ? FloatingActionButton.extended(
              heroTag: 'add_ann',
              backgroundColor: HsColors.orange,
              foregroundColor: Colors.white,
              onPressed: () async {
                final ok = await showModalBottomSheet<bool>(context: context, isScrollControlled: true, showDragHandle: true, builder: (_) => _NewAnnouncementSheet(classId: classId));
                if (ok == true) ref.invalidate(getProvider(path));
              },
              icon: const Icon(Icons.add_rounded),
              label: const Text('اطلاعیه'),
            )
          : null,
      body: RefreshIndicator(
        color: HsColors.orange,
        onRefresh: () async => ref.invalidate(getProvider(path)),
        child: res.when(
          loading: () => const LoadingView(),
          error: (e, _) => ListView(children: [const SizedBox(height: 80), ErrorView(error: e, onRetry: () => ref.invalidate(getProvider(path)))]),
          data: (r) {
            final list = ((r.data as List?) ?? const []).cast<Map>();
            return ListView(
              padding: const EdgeInsets.fromLTRB(14, 12, 14, 96),
              children: [
                if (r.fromCache) ...[OfflineBanner(savedAt: r.savedAt), const SizedBox(height: 8)],
                if (list.isEmpty) const EmptyView(icon: Icons.campaign_rounded, title: 'هنوز اطلاعیه‌ای نیست'),
                for (final a in list) Padding(padding: const EdgeInsets.only(bottom: 10), child: AnnouncementCard(a: a)),
              ],
            );
          },
        ),
      ),
    );
  }
}

class AnnouncementCard extends StatelessWidget {
  const AnnouncementCard({super.key, required this.a});
  final Map a;

  @override
  Widget build(BuildContext context) {
    return SectionCard(
      title: '${a['title']}',
      icon: a['pinned'] == true ? Icons.push_pin_rounded : Icons.campaign_rounded,
      trailing: Text(Fa.date('${a['created_at'] ?? ''}', withYear: false), style: const TextStyle(color: Colors.black45, fontSize: 12)),
      child: Text('${a['body'] ?? ''}', style: const TextStyle(height: 1.8)),
    );
  }
}

class _NewAnnouncementSheet extends ConsumerStatefulWidget {
  const _NewAnnouncementSheet({required this.classId});
  final int classId;
  @override
  ConsumerState<_NewAnnouncementSheet> createState() => _NewAnnouncementSheetState();
}

class _NewAnnouncementSheetState extends ConsumerState<_NewAnnouncementSheet> {
  final _t = TextEditingController();
  final _b = TextEditingController();
  bool _pin = false;
  bool _busy = false;

  @override
  void dispose() {
    _t.dispose();
    _b.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_t.text.trim().isEmpty || _b.text.trim().isEmpty) {
      showMessage(context, 'عنوان و متن را بنویسید.', error: true);
      return;
    }
    setState(() => _busy = true);
    try {
      await ref.read(apiProvider).post<Object?>('/classes/${widget.classId}/announcements', data: {'title': _t.text.trim(), 'body': _b.text.trim(), 'pinned': _pin});
      if (mounted) Navigator.pop(context, true);
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
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Text('اطلاعیه برای والدین کلاس', style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: 12),
        TextField(controller: _t, autofocus: true, decoration: const InputDecoration(labelText: 'عنوان')),
        const SizedBox(height: 10),
        TextField(controller: _b, minLines: 3, maxLines: 6, decoration: const InputDecoration(labelText: 'متن')),
        SwitchListTile(contentPadding: EdgeInsets.zero, value: _pin, onChanged: (v) => setState(() => _pin = v), title: const Text('سنجاق شود (بالای فهرست بماند)')),
        FilledButton(onPressed: _busy ? null : _save, child: busyLabel(_busy, 'انتشار')),
      ]),
    );
  }
}

/* ------------------------------ QR دعوت ------------------------------ */

class InviteSheet extends ConsumerWidget {
  const InviteSheet({super.key, required this.classId});
  final int classId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final inv = ref.watch(classInviteProvider(classId));
    return SizedBox(
      height: MediaQuery.of(context).size.height * 0.78,
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
              Text('QR دعوت کلاس', textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 6),
              const Text('برای جلسه‌ی اولیا یا تابلوی کلاس: والدین با اپ همشاگردی این QR را اسکن کنند و نام فرزندشان را بنویسند. اگر شماره‌شان در پرونده‌ی دانش‌آموز باشد فوراً وصل می‌شوند؛ وگرنه درخواست برای شما می‌آید.',
                  textAlign: TextAlign.center, style: TextStyle(color: Colors.black54, height: 1.8, fontSize: 13)),
              const SizedBox(height: 16),
              if (qr.isNotEmpty)
                Center(
                  child: Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(20), border: Border.all(color: const Color(0x14000000))),
                    child: QrImageView(
                      data: qr,
                      size: 220,
                      eyeStyle: const QrEyeStyle(eyeShape: QrEyeShape.square, color: HsColors.navy),
                      dataModuleStyle: const QrDataModuleStyle(dataModuleShape: QrDataModuleShape.square, color: HsColors.navy),
                    ),
                  ),
                ),
              const SizedBox(height: 14),
              if (code.isNotEmpty) Center(child: SelectableText(code, textDirection: TextDirection.ltr, style: const TextStyle(fontSize: 26, fontWeight: FontWeight.w700, letterSpacing: 3, color: HsColors.orangeDark))),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: () => SharePlus.instance.share(ShareParams(text: 'کد کلاس در اپ همشاگردی: $code\n${p['url'] ?? ''}')),
                icon: const Icon(Icons.send_rounded),
                label: const Text('ارسال کد و لینک'),
              ),
            ],
          );
        },
      ),
    );
  }
}
