/* پنل مدرسه — فرم‌ها، مودال‌ها و نظارت */
(function ($) {
  'use strict';
  if (typeof hsSo === 'undefined') return;

  function esc(v) {
    return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function msg($f, text, type) {
    $f.find('.hs-form-message').first().removeClass('success error').addClass(type).text(text);
  }
  function post(action, data) {
    data = data || {};
    data.action = 'hs_so_' + action;
    data.nonce = hsSo.nonce;
    data.school_id = hsSo.schoolId || 0;
    return $.post(hsSo.ajaxUrl, data);
  }
  function open(id) { $(id).prop('hidden', false); }
  function close() { $('.hs-modal').prop('hidden', true); }

  $(document).on('click', '.hs-so-close', close);
  $(document).on('keydown', function (e) { if (e.key === 'Escape') close(); });
  $(document).on('click', '.hs-modal', function (e) { if (e.target === this) close(); });

  // همهٔ فرم‌ها
  $(document).on('submit', '.hs-so-form', function (e) {
    e.preventDefault();
    const $f = $(this);
    const action = $f.data('so');
    const data = {};
    $f.serializeArray().forEach(function (x) { data[x.name] = x.value; });
    $f.find('input[type=checkbox]').each(function () { if (!this.checked) delete data[this.name]; });
    const $b = $f.find('button[type=submit]').prop('disabled', true);
    post(action, data).done(function (res) {
      if (!res.success) { msg($f, (res.data && res.data.message) || 'خطا', 'error'); return; }
      msg($f, res.data.message, 'success');
      if (res.data.creds) {
        const c = res.data.creds;
        $f.find('.hs-form-message').first().html(
          esc(res.data.message) + '<br><div class="hs-so-creds">نام کاربری: <b dir="ltr">' + esc(c.username) +
          '</b> · رمز موقت: <b dir="ltr">' + esc(c.password) + '</b><br><small>این رمز فقط همین یک بار نمایش داده می‌شود؛ آن را به فرد بدهید.</small>' +
          '<br><button type="button" class="hs-btn hs-btn-sm hs-btn-primary hs-so-reload">ادامه</button></div>'
        );
        return;
      }
      if (res.data.new && res.data.school_id) hsSo.schoolId = res.data.school_id;
      setTimeout(function () { location.reload(); }, 700);
    }).fail(function () { msg($f, 'خطا در ارتباط با سرور', 'error'); })
      .always(function () { $b.prop('disabled', false); });
  });
  $(document).on('click', '.hs-so-reload', function () { location.reload(); });

  // حالت افزودن کادر: جدید / موجود
  $(document).on('change', 'input[name=so_mode]', function () {
    const m = $(this).val();
    const $f = $(this).closest('.hs-card').find('form[data-so=staff_add]');
    $f.find('input[name=mode]').val(m);
    $f.find('.hs-so-mode-new').prop('hidden', m !== 'new');
    $f.find('.hs-so-mode-existing').prop('hidden', m !== 'existing');
  });

  // ویرایش کادر
  $(document).on('click', '.hs-so-staff-edit', function () {
    const d = $(this).data('staff');
    const $f = $('#hs-so-staff-modal form');
    $('#hs-so-sm-name').text(d.name);
    $f.find('[name=id]').val(d.id);
    $f.find('[name=position]').val(d.position);
    $f.find('[name=title]').val(d.title);
    $f.find('[name=job_desc]').val(d.job_desc || '').attr('placeholder', d.job_final || '');
    $f.find('[name=can_view]').prop('checked', !!d.can_view);
    $f.find('.hs-form-message').text('');
    open('#hs-so-staff-modal');
  });
  $(document).on('click', '#hs-so-staff-remove', function () {
    if (!confirm('این فرد از کادر مدرسه حذف شود؟ حساب کاربری و کلاس‌های او پاک نمی‌شود، ولی وظایف واگذارشده به او حذف می‌شود.')) return;
    post('staff_remove', { id: $('#hs-so-staff-modal [name=id]').val() }).done(function (res) {
      if (res.success) location.reload(); else alert((res.data && res.data.message) || 'خطا');
    });
  });

  // وظایف
  $(document).on('click', '.hs-so-task-edit', function () {
    const d = $(this).data('task');
    const $f = $('form[data-so=task_save]');
    ['id', 'title', 'description', 'assignee_id', 'due_date', 'domain', 'priority'].forEach(function (k) {
      $f.find('[name=' + k + ']').val(d[k] == null ? '' : d[k]);
    });
    $f.find('button[type=submit]').text('ذخیرهٔ تغییرات');
    $('html,body').animate({ scrollTop: $f.offset().top - 90 }, 250);
    $f.find('[name=title]').trigger('focus');
  });
  $(document).on('click', '.hs-so-task-del', function () {
    if (!confirm('این وظیفه حذف شود؟')) return;
    post('task_delete', { id: $(this).data('id') }).done(function (res) {
      if (res.success) location.reload(); else alert((res.data && res.data.message) || 'خطا');
    });
  });
  $(document).on('click', '.hs-so-task-status', function () {
    const d = $(this).data('task');
    const $f = $('#hs-so-status-modal form');
    $('#hs-so-stm-title').text(d.title);
    $f.find('[name=id]').val(d.id);
    $f.find('[name=status]').val(d.status);
    $f.find('[name=report]').val(d.report || '');
    $f.find('.hs-form-message').text('');
    open('#hs-so-status-modal');
  });

  // نظارت: جزئیات کلاس (فقط‌خواندنی)
  $(document).on('click', '.hs-so-class-open', function () {
    $('#hs-so-cm-title').text('در حال بارگذاری…');
    $('#hs-so-cm-body').empty();
    open('#hs-so-class-modal');
    post('class_detail', { class_id: $(this).data('class') }).done(function (res) {
      if (res.success) {
        $('#hs-so-cm-title').text(res.data.title);
        $('#hs-so-cm-body').html(res.data.html);
      } else {
        $('#hs-so-cm-title').text('خطا');
        $('#hs-so-cm-body').text((res.data && res.data.message) || '');
      }
    });
  });
})(jQuery);
