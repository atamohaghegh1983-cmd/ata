(function ($) {
  'use strict';
  var $tb = $('#hs-bg-table tbody');
  if (!$tb.length) { return; }

  function recalc() {
    var P = 0, D = 0;
    $tb.find('tr').each(function () {
      var p = parseInt($(this).find('.hs-bg-planned').val(), 10) || 0;
      var d = Math.min(p, parseInt($(this).find('.hs-bg-done').val(), 10) || 0);
      P += p; D += d;
      $(this).find('.hs-bg-pct').text(p ? Math.round(d / p * 100).toLocaleString('fa-IR') + '٪' : '—');
    });
    $('#hs-bg-sum').text(P ? 'مجموع: ' + D.toLocaleString('fa-IR') + ' از ' + P.toLocaleString('fa-IR') + ' (' + Math.round(D / P * 100).toLocaleString('fa-IR') + '٪)' : '');
  }
  function row(subject) {
    return $('<tr>' +
      '<td><input type="text" class="hs-bg-subject" maxlength="40"></td>' +
      '<td><input type="number" class="hs-bg-planned" min="0" max="999" value="0"></td>' +
      '<td><input type="number" class="hs-bg-done" min="0" max="999" value="0"></td>' +
      '<td class="hs-bg-pct">—</td>' +
      '<td><button type="button" class="hs-btn hs-btn-sm hs-btn-ghost hs-bg-del" aria-label="حذف ردیف">✕</button></td></tr>')
      .find('.hs-bg-subject').val(subject || '').end();
  }
  $tb.on('input', 'input', recalc);
  $tb.on('click', '.hs-bg-del', function () { $(this).closest('tr').remove(); recalc(); });
  $('#hs-bg-add').on('click', function () { var $r = row(''); $tb.append($r); $r.find('.hs-bg-subject').trigger('focus'); });

  $('#hs-bg-save').on('click', function () {
    var rows = [];
    $tb.find('tr').each(function () {
      rows.push({
        subject: $.trim($(this).find('.hs-bg-subject').val()),
        planned: parseInt($(this).find('.hs-bg-planned').val(), 10) || 0,
        done: parseInt($(this).find('.hs-bg-done').val(), 10) || 0
      });
    });
    var $btn = $(this).prop('disabled', true), $m = $('#hs-bg-msg');
    $.post(hsBudget.ajaxUrl, { action: 'hs_budget_save', nonce: hsBudget.nonce, class_id: hsBudget.classId, rows: JSON.stringify(rows) })
      .done(function (res) {
        $m.removeClass('success error').addClass(res && res.success ? 'success' : 'error')
          .text((res && res.data && res.data.message) || 'خطا');
      })
      .fail(function () { $m.removeClass('success').addClass('error').text('خطای ارتباط'); })
      .always(function () { $btn.prop('disabled', false); });
  });
  recalc();
})(jQuery);
