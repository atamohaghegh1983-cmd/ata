import 'dart:convert';
import 'dart:typed_data';

import 'package:archive/archive.dart';
import 'package:xml/xml.dart';

import 'fa.dart';

/// خواندن فایل اکسل (xlsx) یا CSV ← ردیف‌ها (فقط برگه‌ی اول)
class SheetReader {
  SheetReader._();

  static List<List<String>> read(Uint8List bytes, String name) {
    final n = name.toLowerCase();
    if (n.endsWith('.csv') || n.endsWith('.txt')) return _csv(bytes);
    return _xlsx(bytes);
  }

  static List<List<String>> _csv(Uint8List bytes) {
    var text = utf8.decode(bytes, allowMalformed: true);
    if (text.startsWith('﻿')) text = text.substring(1);
    final sep = text.split('\n').first.contains(';') && !text.split('\n').first.contains(',') ? ';' : ',';
    return [
      for (final line in const LineSplitter().convert(text))
        if (line.trim().isNotEmpty) line.split(sep).map((c) => c.trim().replaceAll('"', '')).toList(),
    ];
  }

  static List<List<String>> _xlsx(Uint8List bytes) {
    final zip = ZipDecoder().decodeBytes(bytes);
    String? file(String path) {
      final f = zip.findFile(path);
      return f == null ? null : utf8.decode(f.content as List<int>, allowMalformed: true);
    }

    final shared = <String>[];
    final ss = file('xl/sharedStrings.xml');
    if (ss != null) {
      for (final si in XmlDocument.parse(ss).findAllElements('si')) {
        shared.add(si.findAllElements('t').map((t) => t.innerText).join());
      }
    }
    final sheet = file('xl/worksheets/sheet1.xml');
    if (sheet == null) throw const FormatException('برگه‌ای در فایل پیدا نشد.');
    final rows = <List<String>>[];
    for (final row in XmlDocument.parse(sheet).findAllElements('row')) {
      final cells = <int, String>{};
      for (final c in row.findElements('c')) {
        final ref = c.getAttribute('r') ?? '';
        final col = _col(ref);
        final type = c.getAttribute('t');
        String v;
        if (type == 'inlineStr') {
          v = c.findAllElements('t').map((t) => t.innerText).join();
        } else {
          v = c.getElement('v')?.innerText ?? '';
          if (type == 's') {
            final i = int.tryParse(v);
            v = i != null && i < shared.length ? shared[i] : '';
          }
        }
        cells[col < 0 ? cells.length : col] = v.trim();
      }
      if (cells.isEmpty) continue;
      final max = cells.keys.reduce((a, b) => a > b ? a : b);
      rows.add(List.generate(max + 1, (i) => cells[i] ?? ''));
    }
    return rows;
  }

  /// "C12" ← 2
  static int _col(String ref) {
    final letters = ref.replaceAll(RegExp(r'[0-9]'), '');
    if (letters.isEmpty) return -1;
    var n = 0;
    for (final ch in letters.codeUnits) {
      n = n * 26 + (ch - 64);
    }
    return n - 1;
  }

  /// ردیف‌ها ← دانش‌آموز (ستون‌ها از روی عنوان ردیف اول، وگرنه به ترتیب: نام، نام خانوادگی، موبایل والد، نام والد، شماره)
  static List<Map<String, String>> students(List<List<String>> rows) {
    if (rows.isEmpty) return [];
    final head = rows.first.map((h) => h.replaceAll('‌', ' ').trim()).toList();
    int find(List<String> keys) => head.indexWhere((h) => keys.any((k) => h.contains(k)));
    var iLast = find(['نام خانوادگی', 'فامیل', 'last']);
    var iFirst = head.indexWhere((h) => (h == 'نام' || h.startsWith('نام ') && !h.contains('خانوادگی') && !h.contains('والد') && !h.contains('پدر') && !h.contains('مادر')) || h.toLowerCase() == 'first name');
    var iPhone = find(['موبایل', 'تلفن', 'شماره تماس', 'mobile', 'phone']);
    var iPName = find(['نام والد', 'نام پدر', 'نام مادر', 'ولی', 'parent']);
    var iNum = find(['شماره دانش', 'ردیف کلاس', 'شماره کلاس', 'number']);
    var start = 1;
    if (iFirst < 0 && iLast < 0) {
      // بدون سطر عنوان
      iFirst = 0;
      iLast = 1;
      iPhone = 2;
      iPName = 3;
      iNum = 4;
      start = 0;
    }
    String at(List<String> r, int i) => i >= 0 && i < r.length ? r[i].trim() : '';
    final out = <Map<String, String>>[];
    for (final r in rows.skip(start)) {
      final first = at(r, iFirst);
      final last = at(r, iLast);
      if (first.isEmpty && last.isEmpty) continue;
      out.add({
        'first_name': first,
        'last_name': last,
        'parent_phone': Fa.mobile(at(r, iPhone)) ?? at(r, iPhone),
        'parent_name': at(r, iPName),
        'class_number': Fa.enDigits(at(r, iNum)),
      });
    }
    return out;
  }

  /// فایل نمونه (CSV با BOM تا اکسل فارسی را درست نشان دهد)
  static Uint8List template() {
    const csv = 'نام,نام خانوادگی,موبایل والد,نام والد,شماره دانش‌آموز\r\nسارا,رضایی,09121234567,مریم رضایی,1\r\nعلی,کریمی,09351234567,رضا کریمی,2\r\n';
    return Uint8List.fromList([0xEF, 0xBB, 0xBF, ...utf8.encode(csv)]);
  }
}
