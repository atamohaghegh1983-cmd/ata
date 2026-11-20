import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../core/utils/fa.dart';
import '../../core/widgets/hs_avatar.dart';
import '../../core/widgets/confirm.dart';
import '../../core/widgets/states.dart';
import '../../core/widgets/ui.dart';
import '../portfolio/portfolio.dart' show pickAndUpload;
import 'class_providers.dart';
import 'class_screen.dart';

Future<void> showStudentSheet(BuildContext context, {required int classId, required int studentId, required bool canWrite}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (_) => _StudentSheet(classId: classId, studentId: studentId, canWrite: canWrite),
  );
}

/// پروفایل سریع دانش‌آموز: امتیاز یک‌لمسی، سوابق، اتصال والد
class _StudentSheet extends ConsumerStatefulWidget {
  const _StudentSheet({required this.classId, required this.studentId, required this.canWrite});
  final int classId;
  final int studentId;
  final bool canWrite;

  @override
  ConsumerState<_StudentSheet> createState() => _StudentSheetState();
}

class _StudentSheetState extends ConsumerState<_StudentSheet> {
  bool _busy = false;

  String get _path => '/classes/${widget.classId}/students/${widget.studentId}';
  String get _pointsPath => '/classes/${widget.classId}/points';

  Future<void> _award(Map b) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await ref.read(apiProvider).post<Object?>(_pointsPath, data: {'behavior_id': b['id'], 'student_ids': [widget.studentId]});
      HapticFeedback.lightImpact();
      final p = (b['points'] as num).toInt();
      if (mounted) showMessage(context, '${b['icon'] ?? ''} ${b['name']}  ${p > 0 ? '+' : ''}${Fa.faDigits(p)}');
      ref.invalidate(getProvider(_path));
      ref.invalidate(classStudentsProvider(widget.classId));
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final prof = ref.watch(getProvider(_path));
    final pts = widget.canWrite ? ref.watch(getProvider(_pointsPath)) : null;
    return SizedBox(
      height: MediaQuery.of(context).size.height * 0.85,
      child: prof.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(getProvider(_path))),
        data: (r) {
          final s = (r.data as Map?) ?? const {};
          final total = (s['points_total'] as num?)?.toInt() ?? 0;
          final behaviors = ((pts?.valueOrNull?.data as Map?)?['behaviors'] as List? ?? const []).cast<Map>();
          final parents = ((s['parents'] as List?) ?? const []).cast<Map>();
          final att = (s['attendance_month'] is Map) ? s['attendance_month'] as Map : const {};
          return ListView(
            padding: const EdgeInsets.fromLTRB(18, 0, 18, 28),
            children: [
              Row(children: [
                GestureDetector(
                  key: const Key('student_photo'),
                  onTap: widget.canWrite ? _photo : null,
                  child: Stack(clipBehavior: Clip.none, children: [
                    HsAvatar(name: '${s['name']}', url: '${s['avatar'] ?? ''}', size: 64),
                    if (widget.canWrite)
                      const PositionedDirectional(
                        bottom: -2,
                        end: -2,
                        child: CircleAvatar(radius: 13, backgroundColor: HsColors.orange, child: Icon(Icons.photo_camera_rounded, size: 15, color: Colors.white)),
                      ),
                  ]),
                ),
                const SizedBox(width: 14),
                Expanded(child: Text('${s['name']}', style: Theme.of(context).textTheme.titleLarge)),
                Column(children: [
                  Text(Fa.faDigits(total), style: TextStyle(fontSize: 28, fontWeight: FontWeight.w700, color: total < 0 ? HsColors.red : HsColors.green)),
                  Text('امتیاز', style: TextStyle(color: HsColors.faint, fontSize: 12)),
                ]),
              ]),
              const SizedBox(height: 16),
              if (widget.canWrite && behaviors.isNotEmpty) ...[
                Text('امتیاز با یک لمس', style: TextStyle(fontWeight: FontWeight.w700, color: HsColors.navy)),
                const SizedBox(height: 8),
                Wrap(spacing: 8, runSpacing: 8, children: [
                  for (final b in behaviors)
                    ActionChip(
                      key: Key('beh_${b['id']}'),
                      onPressed: _busy ? null : () => _award(b),
                      avatar: Text('${b['icon'] ?? ''}'),
                      label: Text('${b['name']}  ${(b['points'] as num) > 0 ? '+' : ''}${Fa.faDigits(b['points'])}'),
                      backgroundColor: (b['points'] as num) >= 0 ? const Color(0xFFE6F6EA) : const Color(0xFFFDE8E8),
                      side: BorderSide.none,
                    ),
                ]),
                const SizedBox(height: 16),
              ],
              // وقتی والد وصل است، «کد اتصال» و «حذف از کلاس» کوچک و داخل همین کادر
              if (att.isNotEmpty || (widget.canWrite && parents.isNotEmpty))
                SectionCard(
                  title: 'حضور این ماه',
                  icon: Icons.fact_check_rounded,
                  child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                    Wrap(spacing: 8, runSpacing: 8, children: [
                      if (att.isEmpty) Text('این ماه هنوز حضوری ثبت نشده.', style: TextStyle(color: HsColors.faint, fontSize: 13)),
                      for (final st in AttStatus.all)
                        if ((att[st.key] ?? 0) != 0) Pill('${st.label}: ${Fa.faDigits(att[st.key])}', color: st.color, icon: st.icon),
                    ]),
                    if (widget.canWrite && parents.isNotEmpty) ...[
                      const Divider(height: 18),
                      Row(mainAxisAlignment: MainAxisAlignment.end, children: [
                        if ('${s['parent_code'] ?? ''}'.isNotEmpty)
                          TextButton.icon(
                            key: const Key('parent_code_small'),
                            style: TextButton.styleFrom(visualDensity: VisualDensity.compact, textStyle: const TextStyle(fontSize: 12)),
                            onPressed: () => showParentCodeDialog(context, name: '${s['name']}', code: '${s['parent_code']}', share: _shareText(s)),
                            icon: const Icon(Icons.key_rounded, size: 16),
                            label: const Text('کد اتصال'),
                          ),
                        TextButton.icon(
                          key: const Key('remove_student'),
                          style: TextButton.styleFrom(foregroundColor: HsColors.red, visualDensity: VisualDensity.compact, textStyle: const TextStyle(fontSize: 12)),
                          onPressed: _busy ? null : () => _remove('${s['name']}'),
                          icon: const Icon(Icons.person_remove_rounded, size: 16),
                          label: const Text('حذف از کلاس'),
                        ),
                      ]),
                    ],
                  ]),
                ),
              const SizedBox(height: 10),
              SectionCard(
                title: 'والدین',
                icon: Icons.family_restroom_rounded,
                child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  if (parents.isEmpty) Text('هنوز والدی وصل نشده است.', style: TextStyle(color: HsColors.muted)),
                  for (final p in parents)
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: HsAvatar(name: '${p['name']}', size: 36),
                      title: Text('${p['name']}'),
                      trailing: widget.canWrite
                          ? IconButton(
                              tooltip: 'پیام به والد',
                              icon: const Icon(Icons.chat_bubble_outline_rounded, color: HsColors.blue),
                              onPressed: () {
                                Navigator.pop(context);
                                context.push('/messages/${p['id']}', extra: '${p['name']}');
                              },
                            )
                          : null,
                    ),
                  if (widget.canWrite && parents.isEmpty && '${s['parent_code'] ?? ''}'.isNotEmpty) ...[
                    const SizedBox(height: 6),
                    OutlinedButton.icon(
                      key: const Key('parent_code_btn'),
                      onPressed: () => showParentCodeDialog(context, name: '${s['name']}', code: '${s['parent_code']}', share: _shareText(s)),
                      icon: const Icon(Icons.key_rounded),
                      label: const Text('کد اتصال والد'),
                    ),
                  ],
                ]),
              ),
              const SizedBox(height: 10),
              SectionCard(
                title: 'آخرین امتیازها',
                icon: Icons.stars_rounded,
                child: Column(children: [
                  if (((s['points_recent'] as List?) ?? const []).isEmpty) Text('هنوز امتیازی ثبت نشده.', style: TextStyle(color: HsColors.muted)),
                  for (final p in ((s['points_recent'] as List?) ?? const []).cast<Map>().take(10))
                    ListTile(
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      leading: Text('${p['icon'] ?? '⭐'}', style: const TextStyle(fontSize: 20)),
                      title: Text('${p['behavior']}'),
                      subtitle: Text(Fa.date('${p['at'] ?? ''}', withYear: false)),
                      trailing: Text('${(p['points'] as num) > 0 ? '+' : ''}${Fa.faDigits(p['points'])}',
                          style: TextStyle(fontWeight: FontWeight.w700, color: (p['points'] as num) < 0 ? HsColors.red : HsColors.green)),
                    ),
                ]),
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: () {
                  Navigator.pop(context);
                  context.push('/portfolio/${widget.studentId}', extra: '${s['name']}');
                },
                icon: const Icon(Icons.photo_album_rounded),
                label: const Text('دیوار همشاگردی'),
              ),
              if (widget.canWrite && parents.isEmpty) ...[
                const SizedBox(height: 10),
                OutlinedButton.icon(
                  key: const Key('remove_student'),
                  style: OutlinedButton.styleFrom(foregroundColor: HsColors.red, side: const BorderSide(color: HsColors.red)),
                  onPressed: _busy ? null : () => _remove('${s['name']}'),
                  icon: const Icon(Icons.person_remove_rounded),
                  label: const Text('حذف از کلاس'),
                ),
              ],
            ],
          );
        },
      ),
    );
  }

  Future<void> _photo() async {
    try {
      final r = await pickAndUpload(context, ref, '/classes/${widget.classId}/students/${widget.studentId}/avatar');
      if (r == null) return;
      ref.invalidate(getProvider(_path));
      ref.invalidate(classStudentsProvider(widget.classId));
      if (mounted) showMessage(context, 'عکس ذخیره شد ✅');
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    }
  }

  Future<void> _remove(String name) async {
    final ok = await confirmDanger(context, title: 'حذف $name از کلاس', text: 'دانش‌آموز از این کلاس بیرون می‌رود. حساب، سوابق و اتصال والدینش پاک نمی‌شود و بعداً می‌توانید دوباره اضافه‌اش کنید.');
    if (!ok) return;
    setState(() => _busy = true);
    try {
      await ref.read(apiProvider).delete<Object?>('/classes/${widget.classId}/students/${widget.studentId}');
      ref.invalidate(classStudentsProvider(widget.classId));
      ref.invalidate(myClassesProvider);
      if (mounted) {
        Navigator.pop(context);
        showMessage(context, '$name از کلاس حذف شد.');
      }
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  String _shareText(Map s) =>
      'والد گرامی ${s['name']}،\nبرای دیدن حضور، امتیاز و تکالیف فرزندتان، اپ «همشاگردی» را نصب کنید، با شماره موبایل خود وارد شوید و در «افزودن فرزند» این کد را بزنید:\n${s['parent_code']}';
}
