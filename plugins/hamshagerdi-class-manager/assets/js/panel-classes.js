/* helper امن‌سازی خروجی HTML */
window.hsEsc = function (v) {
  return String(v === null || v === undefined ? '' : v).replace(/[&<>"']/g, function (c) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
  });
};
(function ($) {
  'use strict';

  if (typeof hsClass === 'undefined') return;

  const i18n = hsClass.i18n || {};

  function showMessage($el, text, type) {
    $el.removeClass('success error').addClass(type).text(text);
    if (type === 'success') {
      setTimeout(function () {
        $el.text('');
      }, 4000);
    }
  }

  function ajax(action, data) {
    data = data || {};
    data.action = action;
    data.nonce = hsClass.nonce;
    return $.post(hsClass.ajaxUrl, data);
  }

  // ---------- کپی کد دعوت ----------
  $(document).on('click', '.hs-btn-copy', function (e) {
    e.preventDefault();
    const code = $(this).data('code') || $(this).siblings('.hs-invite-code').data('code') || $(this).siblings('code').text();
    if (!code) return;

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(code).then(function () {
        alert(i18n.copySuccess || 'کد دعوت کپی شد');
      });
    } else {
      const $temp = $('<input>');
      $('body').append($temp);
      $temp.val(code).select();
      document.execCommand('copy');
      $temp.remove();
      alert(i18n.copySuccess || 'کد دعوت کپی شد');
    }
  });

  // ---------- نمایش/مخفی فرم ساخت کلاس ----------
  $(document).on('click', '#hs-btn-new-class, #hs-btn-new-class-empty', function () {
    $('#hs-create-class-form').slideDown(200);
    $('#hs-class-title').focus();
  });

  $(document).on('click', '#hs-btn-cancel-create', function () {
    $('#hs-create-class-form').slideUp(200);
    $('#hs-form-create-class')[0].reset();
    $('#hs-create-message').text('');
  });

  // ---------- ساخت کلاس ----------
  $(document).on('submit', '#hs-form-create-class', function (e) {
    e.preventDefault();
    const $form = $(this);
    const $btn = $form.find('button[type="submit"]');
    const $msg = $('#hs-create-message');

    $btn.prop('disabled', true).text(i18n.saving || 'در حال ذخیره...');

    ajax('hs_create_class', {
      title: $('#hs-class-title').val(),
      grade: $('#hs-class-grade').val(),
      year: $('#hs-class-year').val(),
      school: $('#hs-class-school').val(),
      province: $('#hs-class-province').val(),
      city: $('#hs-class-city').val(),
      description: $('#hs-class-desc').val()
    })
      .done(function (res) {
        if (res.success) {
          showMessage($msg, res.data.message, 'success');
          // ریدایرکت به صفحه جزئیات کلاس
          if (res.data.redirect) {
            setTimeout(function () {
              window.location.href = res.data.redirect;
            }, 800);
          } else {
            location.reload();
          }
        } else {
          showMessage($msg, res.data.message || i18n.error, 'error');
          $btn.prop('disabled', false).text('ساخت کلاس');
        }
      })
      .fail(function () {
        showMessage($msg, i18n.error, 'error');
        $btn.prop('disabled', false).text('ساخت کلاس');
      });
  });

  // آمدن از داشبورد با «افزودن دانش‌آموز»
  if (location.hash === '#hs-form-add-student') {
    setTimeout(function () {
      var f = document.getElementById('hs-form-add-student');
      if (f) { f.scrollIntoView({ behavior: 'smooth', block: 'center' }); var i = document.getElementById('hs-st-first'); if (i) { i.focus(); } }
    }, 300);
  }

  // ---------- ثبت دانش‌آموز کامل ----------
  // رفتار یکدست: ثبت با AJAX ← نمایش اطلاعات ورود ← با «ادامه» صفحه یک‌بار بارگذاری می‌شود.
  // دکمه از اولین کلیک تا پایان کار غیرفعال است تا درخواست دوم ارسال نشود.
  $(document).on('submit', '#hs-form-add-student', function (e) {
    e.preventDefault();
    const $form = $(this);
    if ($form.data('hsBusy')) return;          // درخواست قبلی هنوز تمام نشده
    const $btn = $form.find('button[type="submit"]');
    const $msg = $('#hs-add-student-message');
    const classId = $('.hs-class-detail').data('class-id');
    const first = $('#hs-st-first').val().trim();
    const last = $('#hs-st-last').val().trim();

    if (!first && !last) {
      showMessage($msg, 'نام و نام خانوادگی را وارد کنید', 'error');
      return;
    }

    $form.data('hsBusy', true);
    const btnText = $btn.text();
    $btn.prop('disabled', true).text('در حال ثبت…');
    ajax('hs_add_student', {
      class_id: classId,
      first_name: first,
      last_name: last,
      parent_name: $('#hs-st-parent').val(),
      parent_phone: $('#hs-st-phone').val(),
      parent2_name: $('#hs-st-parent2').val(),
      parent2_phone: $('#hs-st-phone2').val(),
      class_number: $('#hs-st-number').val(),
      birth_date: $('#hs-st-birth').val(),
      photo_url: $('#hs-st-photo').val(),
      status: 'active'
    })
      .done(function (res) {
        if (res.success) {
          const st = res.data.student;
          $('#hs-student-count').text(res.data.count);
          $msg.removeClass('error').addClass('success').html(
            '<strong>ثبت شد: ' + hsEsc(st.name) + '</strong><br>' +
            'نام کاربری: <code>' + hsEsc(st.username) + '</code> | رمز موقت: <code>' + hsEsc(st.password) + '</code>' +
            (st.parent_code ? ' | کد والد: <code>' + hsEsc(st.parent_code) + '</code>' : '') +
            '<br><small>این رمز فقط همین یک‌بار نمایش داده می‌شود؛ یادداشت کنید.</small><br>' +
            '<button type="button" class="hs-btn hs-btn-primary hs-st-continue" style="margin-top:8px">یادداشت کردم، ادامه</button>'
          ).show();
          $btn.text('ثبت شد ✓');               // تا بارگذاری صفحه غیرفعال می‌ماند
        } else {
          showMessage($msg, (res.data && res.data.message) || i18n.error, 'error');
          $form.data('hsBusy', false);
          $btn.prop('disabled', false).text(btnText);
        }
      })
      .fail(function () {
        showMessage($msg, i18n.error, 'error');
        $form.data('hsBusy', false);
        $btn.prop('disabled', false).text(btnText);
      });
  });
  $(document).on('click', '.hs-st-continue', function () {
    $(this).prop('disabled', true);
    location.reload();
  });

  // حذف
  $(document).on('click', '.hs-st-remove', function () {
    const $btn = $(this);
    if ($btn.prop('disabled')) return;
    const name = $btn.data('name') || 'این دانش‌آموز';
    if (!confirm('«' + name + '» از این کلاس حذف شود؟\nحساب و سوابقش (حضور، نمره، تکلیف) حفظ می‌شود.')) return;
    const userId = $btn.data('user-id');
    const classId = $('.hs-class-detail').data('class-id');
    const $row = $btn.closest('tr, li');
    $btn.prop('disabled', true);
    ajax('hs_remove_student', { class_id: classId, user_id: userId })
      .done(function (res) {
        if (!res.success) {
          $btn.prop('disabled', false);
          alert((res.data && res.data.message) || i18n.error);
          return;
        }
        $row.fadeOut(200, function () {
          $(this).remove();
          $('#hs-student-count').text(res.data.count);
        });
        // در هیچ کلاس دیگری نیست و والد متصل ندارد (مثلاً ثبت تکراری) ← پیشنهاد حذف کامل حساب
        if (res.data.orphan && confirm('«' + name + '» در هیچ کلاس دیگری نیست.\nاگر ثبت تکراری یا اشتباه بوده، حساب او کاملاً حذف شود؟\n(این کار برگشت‌پذیر نیست)')) {
          ajax('hs_delete_student_account', { user_id: userId }).done(function (r) {
            alert((r.data && r.data.message) || i18n.error);
          });
        }
      })
      .fail(function () { $btn.prop('disabled', false); alert(i18n.error); });
  });

  // باز کردن پرونده
  $(document).on('click', '.hs-st-edit', function () {
    const userId = $(this).data('user-id');
    const classId = $('.hs-class-detail').data('class-id');
    $('#hs-st-modal').show();
    $('#hs-st-profile-msg').empty();
    ajax('hs_get_student_profile', { class_id: classId, user_id: userId })
      .done(function (res) {
        if (!res.success) return;
        const p = res.data.profile;
        $('#hs-st-edit-id').val(p.user_id);
        $('#hs-ed-first').val(p.first_name);
        $('#hs-ed-last').val(p.last_name);
        $('#hs-ed-parent').val(p.parent_name);
        $('#hs-ed-phone').val(p.parent_phone);
        $('#hs-ed-number').val(p.class_number);
        $('#hs-ed-birth').val(p.birth_date);
        $('#hs-ed-photo').val(p.photo_url).trigger('change');
        $('#hs-ed-status').val(p.status || 'active');
        $('#hs-ed-notes').val(p.notes);
        $('#hs-ed-pcode').val(p.parent_code || '');
      });
  });

  $(document).on('click', '#hs-ed-pcode-regen', function () {
    if (!confirm('کد قبلی باطل و کد جدید ساخته شود؟')) return;
    const classId = $('.hs-class-detail').data('class-id');
    ajax('hs_regen_parent_code', { class_id: classId, user_id: $('#hs-st-edit-id').val() })
      .done(function (res) { if (res.success) $('#hs-ed-pcode').val(res.data.code); });
  });

  $(document).on('click', '#hs-st-modal-close, #hs-st-modal-x', function () {
    $('#hs-st-modal').hide();
  });

  $(document).on('submit', '#hs-form-st-profile', function (e) {
    e.preventDefault();
    const classId = $('.hs-class-detail').data('class-id');
    const userId = $('#hs-st-edit-id').val();
    const $msg = $('#hs-st-profile-msg');
    ajax('hs_update_student_profile', {
      class_id: classId,
      user_id: userId,
      first_name: $('#hs-ed-first').val(),
      last_name: $('#hs-ed-last').val(),
      parent_name: $('#hs-ed-parent').val(),
      parent_phone: $('#hs-ed-phone').val(),
      class_number: $('#hs-ed-number').val(),
      birth_date: $('#hs-ed-birth').val(),
      photo_url: $('#hs-ed-photo').val(),
      status: $('#hs-ed-status').val(),
      notes: $('#hs-ed-notes').val()
    })
      .done(function (res) {
        if (res.success) {
          showMessage($msg, res.data.message, 'success');
          setTimeout(function () { location.reload(); }, 800);
        } else {
          showMessage($msg, res.data.message || i18n.error, 'error');
        }
      });
  });

  // انتقال کلاس
  $(document).on('click', '#hs-st-transfer-btn', function () {
    const toClass = $('#hs-ed-transfer').val();
    if (!toClass) { alert('کلاس مقصد را انتخاب کنید'); return; }
    if (!confirm('دانش‌آموز به کلاس دیگر منتقل شود؟')) return;
    const classId = $('.hs-class-detail').data('class-id');
    const userId = $('#hs-st-edit-id').val();
    ajax('hs_transfer_student', {
      user_id: userId,
      from_class: classId,
      to_class: toClass
    })
      .done(function (res) {
        if (res.success) {
          alert(res.data.message);
          location.reload();
        } else {
          alert(res.data.message || i18n.error);
        }
      });
  });

  // خروجی CSV / اکسل
  $(document).on('click', '#hs-export-students', function () {
    const classId = $(this).data('class-id') || $('.hs-class-detail').data('class-id');
    ajax('hs_export_class_students', { class_id: classId })
      .done(function (res) {
        if (!res.success) {
          alert(res.data.message || i18n.error);
          return;
        }
        const rows = res.data.rows;
        const lines = rows.map(function (row) {
          return row.map(function (cell) {
            const c = (cell === null || cell === undefined) ? '' : String(cell);
            return '"' + c.replace(/"/g, '""') + '"';
          }).join(',');
        });
        // BOM برای اکسل فارسی
        const bom = '\uFEFF';
        const blob = new Blob([bom + lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = res.data.filename || 'students.csv';
        a.click();
        URL.revokeObjectURL(url);
      });
  });

  // ---------- ورود دانش‌آموزان از اکسل/CSV ----------
  function downloadCsv(rows, filename) {
    const lines = rows.map(function (row) {
      return row.map(function (cell) {
        const c = (cell === null || cell === undefined) ? '' : String(cell);
        return '"' + c.replace(/"/g, '""') + '"';
      }).join(',');
    });
    const blob = new Blob(['\uFEFF' + lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  $(document).on('click', '#hs-import-open', function () {
    $('#hs-import-box').prop('hidden', function (i, v) { return !v; });
  });

  $(document).on('click', '#hs-import-template', function () {
    downloadCsv([
      ['شماره', 'نام', 'نام خانوادگی', 'نام والد', 'تلفن والد', 'تاریخ تولد', 'یادداشت'],
      ['1', 'علی', 'احمدی', 'رضا احمدی', '09121234567', '1395/04/12', ''],
      ['2', 'سارا', 'محمدی', 'مریم محمدی', '09351234567', '1395/09/03', '']
    ], 'students-template.csv');
  });

  $(document).on('click', '#hs-import-go', function () {
    const file = document.getElementById('hs-import-file').files[0];
    const $msg = $('#hs-import-msg');
    const $res = $('#hs-import-result').empty();
    if (!file) {
      showMessage($msg, 'ابتدا فایل اکسل یا CSV را انتخاب کنید', 'error');
      return;
    }
    const fd = new FormData();
    fd.append('action', 'hs_import_students');
    fd.append('nonce', hsClass.nonce);
    fd.append('class_id', $('.hs-class-detail').data('class-id'));
    fd.append('file', file);
    const $btn = $(this).prop('disabled', true);
    showMessage($msg, 'در حال ورود اطلاعات…', 'success');
    $.ajax({ url: hsClass.ajaxUrl, type: 'POST', data: fd, processData: false, contentType: false })
      .done(function (res) {
        if (!res.success) {
          showMessage($msg, (res.data && res.data.message) || i18n.error, 'error');
          return;
        }
        const d = res.data;
        showMessage($msg, d.message, 'success');
        $('#hs-student-count').text(d.count);
        let html = '';
        if (d.created.length) {
          html += '<p><strong>مشخصات ورود دانش‌آموزان جدید (رمزها فقط همین یک بار نمایش داده می‌شود):</strong></p>' +
            '<div class="hs-import-table-wrap"><table class="hs-table"><thead><tr><th>نام</th><th>نام کاربری</th><th>رمز موقت</th><th>کد والد</th></tr></thead><tbody>';
          d.created.forEach(function (c) {
            html += '<tr><td>' + hsEsc(c.name) + '</td><td dir="ltr">' + hsEsc(c.username) + '</td><td dir="ltr">' + hsEsc(c.password) + '</td><td dir="ltr">' + hsEsc(c.code) + '</td></tr>';
          });
          html += '</tbody></table></div><p><button type="button" class="hs-btn hs-btn-sm hs-btn-primary" id="hs-import-creds">📥 دانلود مشخصات ورود</button> ' +
            '<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost" id="hs-import-reload">بستن و نمایش لیست</button></p>';
        }
        if (d.skipped.length) html += '<p class="hs-muted">تکراری (رد شد): ' + d.skipped.map(hsEsc).join('، ') + '</p>';
        if (d.failed.length) html += '<p class="error">خطا: ' + d.failed.map(hsEsc).join('، ') + '</p>';
        $res.html(html).data('created', d.created);
        if (!d.created.length) setTimeout(function () { location.reload(); }, 1500);
      })
      .fail(function () { showMessage($msg, i18n.error, 'error'); })
      .always(function () { $btn.prop('disabled', false); });
  });

  $(document).on('click', '#hs-import-creds', function () {
    const created = $('#hs-import-result').data('created') || [];
    const rows = [['نام', 'نام کاربری', 'رمز موقت', 'کد والد']];
    created.forEach(function (c) { rows.push([c.name, c.username, c.password, c.code]); });
    downloadCsv(rows, 'students-logins.csv');
  });
  $(document).on('click', '#hs-import-reload', function () { location.reload(); });

  // ---------- ذخیره تنظیمات کلاس ----------
  $(document).on('submit', '#hs-form-update-class', function (e) {
    e.preventDefault();
    const $form = $(this);
    const $btn = $form.find('button[type="submit"]');
    const $msg = $('#hs-update-message');
    const classId = $('.hs-class-detail').data('class-id');

    $btn.prop('disabled', true).text(i18n.saving || 'در حال ذخیره...');

    ajax('hs_update_class', {
      class_id: classId,
      title: $form.find('[name="title"]').val(),
      grade: $form.find('[name="grade"]').val(),
      year: $form.find('[name="year"]').val(),
      school: $form.find('[name="school"]').val(),
      province: $form.find('[name="province"]').val(),
      city: $form.find('[name="city"]').val(),
      description: $form.find('[name="description"]').val()
    })
      .done(function (res) {
        if (res.success) {
          showMessage($msg, res.data.message, 'success');
          // به‌روزرسانی عنوان صفحه
          $('.hs-panel-header h2').first().text($form.find('[name="title"]').val());
        } else {
          showMessage($msg, res.data.message || i18n.error, 'error');
        }
      })
      .fail(function () {
        showMessage($msg, i18n.error, 'error');
      })
      .always(function () {
        $btn.prop('disabled', false).text('ذخیره تغییرات');
      });
  });

  // ---------- تولید مجدد کد دعوت ----------
  $(document).on('click', '#hs-btn-regen-code', function () {
    if (!confirm('کد دعوت قبلی باطل می‌شود. ادامه می‌دهید؟')) return;

    const classId = $('.hs-class-detail').data('class-id');
    const $btn = $(this);

    $btn.prop('disabled', true);

    ajax('hs_regenerate_code', { class_id: classId })
      .done(function (res) {
        if (res.success) {
          $('#hs-invite-display').text(res.data.code);
          $('.hs-btn-copy').data('code', res.data.code);
          alert(res.data.message);
        } else {
          alert(res.data.message || i18n.error);
        }
      })
      .always(function () {
        $btn.prop('disabled', false);
      });
  });

  // ---------- حذف کلاس ----------
  $(document).on('click', '#hs-btn-delete-class', function () {
    if (!confirm(i18n.confirmDelete || 'حذف شود؟')) return;
    if (!confirm('واقعاً مطمئن هستید؟ تمام دانش‌آموزان از این کلاس جدا می‌شوند.')) return;

    const classId = $('.hs-class-detail').data('class-id');

    ajax('hs_delete_class', { class_id: classId })
      .done(function (res) {
        if (res.success) {
          // بازگشت به لیست
          const url = new URL(window.location.href);
          url.searchParams.delete('class_id');
          window.location.href = url.toString();
        } else {
          alert(res.data.message || i18n.error);
        }
      });
  });

  // ---------- پیوستن با کد دعوت ----------
  $(document).on('submit', '#hs-form-join-class', function (e) {
    e.preventDefault();
    const $form = $(this);
    const $btn = $form.find('button[type="submit"]');
    const $msg = $('#hs-join-message');
    const code = $('#hs-join-code').val().trim();

    if (!code) {
      showMessage($msg, 'کد دعوت را وارد کنید', 'error');
      return;
    }

    $btn.prop('disabled', true);

    ajax('hs_join_class', { code: code })
      .done(function (res) {
        if (res.success) {
          showMessage($msg, res.data.message, 'success');
        } else {
          showMessage($msg, res.data.message || i18n.error, 'error');
        }
      })
      .fail(function () {
        showMessage($msg, i18n.error, 'error');
      })
      .always(function () {
        $btn.prop('disabled', false);
      });
  });



  // =====================================================
  // حضور و غیاب
  // =====================================================

  function updateAttSummary() {
    const counts = { present: 0, absent: 0, late: 0, excused: 0 };
    $('#hs-att-form .hs-att-row').each(function () {
      const status = $(this).find('input[type="radio"]:checked').val() || 'present';
      if (counts[status] !== undefined) counts[status]++;
    });
    $('#hs-att-summary .sum-present strong').text(counts.present);
    $('#hs-att-summary .sum-absent strong').text(counts.absent);
    $('#hs-att-summary .sum-late strong').text(counts.late);
    $('#hs-att-summary .sum-excused strong').text(counts.excused);
  }

  // تغییر وضعیت دکمه‌ها
  $(document).on('change', '.hs-att-status-btn input', function () {
    const $group = $(this).closest('.hs-att-status-group');
    $group.find('.hs-att-status-btn').removeClass('active');
    $(this).closest('.hs-att-status-btn').addClass('active');
    updateAttSummary();
  });

  // عملیات سریع
  $(document).on('click', '.hs-att-bulk [data-bulk], .hs-att-quick [data-bulk]', function () {
    const action = $(this).data('bulk');
    $('#hs-att-form .hs-att-row').each(function () {
      const $row = $(this);
      if (action === 'clear') {
        $row.find('input[type="radio"]').prop('checked', false);
        $row.find('.hs-att-status-btn').removeClass('active');
        $row.find('.hs-att-note-input').val('');
      } else {
        $row.find('input[value="' + action + '"]').prop('checked', true).trigger('change');
      }
    });
    updateAttSummary();
  });

  // بارگذاری کلاس/تاریخ جدید
  $(document).on('click', '#hs-att-load', function () {
    const classId = $('#hs-att-class').val();
    const date = $('#hs-att-date').val();
    if (!classId || !date) return;
    const url = new URL(window.location.href);
    url.searchParams.set('class_id', classId);
    url.searchParams.set('date', date);
    window.location.href = url.toString();
  });

  // ذخیره حضور
  $(document).on('submit', '#hs-att-form', function (e) {
    e.preventDefault();
    const $form = $(this);
    const $btn = $('#hs-att-save');
    const $msg = $('#hs-att-message');
    const classId = $('#hs-attendance-panel').data('class-id') || $('#hs-att-class').val();
    const date = $('#hs-attendance-panel').data('date') || $('#hs-att-date').val();

    const records = {};
    $form.find('.hs-att-row').each(function () {
      const sid = $(this).data('student-id');
      const status = $(this).find('input[type="radio"]:checked').val() || 'present';
      const note = $(this).find('.hs-att-note-input').val() || '';
      records[sid] = { status: status, note: note };
    });

    if (Object.keys(records).length === 0) {
      showMessage($msg, 'هیچ دانش‌آموزی برای ثبت نیست', 'error');
      return;
    }

    $btn.prop('disabled', true).text('در حال ذخیره...');

    ajax('hs_save_attendance', {
      class_id: classId,
      date: date,
      records: records
    })
      .done(function (res) {
        if (res.success) {
          showMessage($msg, res.data.message, 'success');
          // به‌روزرسانی خلاصه از سرور
          if (res.data.summary) {
            $('#hs-att-summary .sum-present strong').text(res.data.summary.present);
            $('#hs-att-summary .sum-absent strong').text(res.data.summary.absent);
            $('#hs-att-summary .sum-late strong').text(res.data.summary.late);
            $('#hs-att-summary .sum-excused strong').text(res.data.summary.excused);
          }
        } else {
          showMessage($msg, res.data.message || i18n.error, 'error');
        }
      })
      .fail(function () {
        showMessage($msg, i18n.error, 'error');
      })
      .always(function () {
        $btn.prop('disabled', false).text('💾 ذخیره حضور و غیاب');
      });
  });

  // گزارش ماهانه
  $(document).on('click', '#hs-att-toggle-report', function () {
    const $box = $('#hs-att-monthly-report');
    if ($box.is(':visible')) {
      $box.slideUp(200);
      return;
    }
    $box.slideDown(200);
    const $loading = $box.find('.hs-att-report-loading').show();
    const $content = $box.find('.hs-att-report-content').hide();
    const classId = $('#hs-attendance-panel').data('class-id') || $('#hs-att-class').val();
    const now = new Date();

    ajax('hs_get_monthly_report', {
      class_id: classId,
      year: now.getFullYear(),
      month: now.getMonth() + 1
    })
      .done(function (res) {
        $loading.hide();
        if (!res.success) {
          $content.html('<p class="error">' + hsEsc(res.data.message || i18n.error) + '</p>').show();
          return;
        }
        const rows = res.data.rows || [];
        let html = '<p class="hs-muted" style="margin-bottom:0.75rem;">تعداد روزهای ثبت‌شده این ماه: <strong>' + res.data.recorded_days + '</strong></p>';
        if (rows.length === 0) {
          html += '<p>داده‌ای برای نمایش نیست.</p>';
        } else {
          html += '<div style="overflow-x:auto;"><table class="hs-att-report-table"><thead><tr>';
          html += '<th>نام</th><th>حاضر</th><th>غایب</th><th>تأخیر</th><th>موجه</th></tr></thead><tbody>';
          rows.forEach(function (r) {
            html += '<tr><td>' + hsEsc(r.name) + '</td>';
            html += '<td class="num-present">' + r.present + '</td>';
            html += '<td class="num-absent">' + r.absent + '</td>';
            html += '<td class="num-late">' + r.late + '</td>';
            html += '<td class="num-excused">' + r.excused + '</td></tr>';
          });
          html += '</tbody></table></div>';
        }
        $content.html(html).show();
      })
      .fail(function () {
        $loading.hide();
        $content.html('<p class="error">' + i18n.error + '</p>').show();
      });
  });

  // مقداردهی اولیه خلاصه
  if ($('#hs-att-form').length) {
    updateAttSummary();
  }


  // =====================================================
  // سیستم امتیاز و تشویق (مرحله ۲)
  // =====================================================

  let selectedBehaviorId = null;

  function updateAwardButton() {
    const hasBeh = !!selectedBehaviorId;
    const hasStudents = $('#hs-pts-students input:checked').length > 0;
    $('#hs-pts-award').prop('disabled', !(hasBeh && hasStudents));
  }

  // انتخاب رفتار
  $(document).on('click', '.hs-pts-beh-btn', function () {
    $('.hs-pts-beh-btn').removeClass('active');
    $(this).addClass('active');
    selectedBehaviorId = $(this).data('id');
    const name = $(this).data('name');
    const icon = $(this).data('icon');
    const pts = $(this).data('points');
    const sign = pts >= 0 ? '+' : '';
    $('#hs-pts-selected-label')
      .addClass('has-selection')
      .text(icon + ' ' + name + ' (' + sign + pts + ')');
    updateAwardButton();
  });

  // انتخاب دانش‌آموز
  $(document).on('change', '#hs-pts-students input', function () {
    updateAwardButton();
  });

  $(document).on('click', '#hs-pts-select-all', function () {
    $('#hs-pts-students input').prop('checked', true);
    updateAwardButton();
  });
  $(document).on('click', '#hs-pts-select-none', function () {
    $('#hs-pts-students input').prop('checked', false);
    updateAwardButton();
  });

  // تغییر کلاس
  $(document).on('click', '#hs-pts-load', function () {
    const classId = $('#hs-pts-class').val();
    if (!classId) return;
    const url = new URL(window.location.href);
    url.searchParams.set('class_id', classId);
    window.location.href = url.toString();
  });

  // ثبت امتیاز
  $(document).on('click', '#hs-pts-award', function () {
    if (!selectedBehaviorId) return;

    const classId = $('#hs-points-panel').data('class-id') || $('#hs-pts-class').val();
    const studentIds = [];
    $('#hs-pts-students input:checked').each(function () {
      studentIds.push($(this).val());
    });
    if (studentIds.length === 0) return;

    const note = $('#hs-pts-note').val() || '';
    const $btn = $(this);
    const $msg = $('#hs-pts-message');

    $btn.prop('disabled', true).text('در حال ثبت...');

    ajax('hs_award_points', {
      class_id: classId,
      behavior_id: selectedBehaviorId,
      student_ids: studentIds,
      note: note
    })
      .done(function (res) {
        if (res.success) {
          showMessage($msg, res.data.message, 'success');

          // به‌روزرسانی امتیازها در UI
          if (res.data.totals) {
            $.each(res.data.totals, function (sid, total) {
              $('.hs-pts-st-score[data-student-id="' + sid + '"]').text(total);
              $('.lb-score[data-student-id="' + sid + '"]').text(total);
            });
            // مرتب‌سازی مجدد رتبه‌بندی
            reorderLeaderboard();
          }

          // افزودن به فعالیت اخیر
          if (res.data.behavior) {
            const b = res.data.behavior;
            const sign = b.points >= 0 ? '+' : '';
            studentIds.forEach(function (sid) {
              const name = $('#hs-pts-students input[value="' + sid + '"]')
                .closest('label').find('.hs-pts-st-name').text();
              const $li = $(
                '<li><span class="rec-icon">' + b.icon + '</span>' +
                '<span class="rec-text"><strong>' + name + '</strong> ' +
                b.name + ' <em>' + sign + b.points + '</em></span></li>'
              );
              const $list = $('#hs-pts-recent');
              $list.find('.hs-muted').remove();
              $list.prepend($li);
            });
          }

          // پاک کردن انتخاب دانش‌آموزان و یادداشت
          $('#hs-pts-students input').prop('checked', false);
          $('#hs-pts-note').val('');
          updateAwardButton();
        } else {
          showMessage($msg, res.data.message || i18n.error, 'error');
          $btn.prop('disabled', false).text('ثبت امتیاز');
        }
      })
      .fail(function () {
        showMessage($msg, i18n.error, 'error');
        $btn.prop('disabled', false).text('ثبت امتیاز');
      })
      .always(function () {
        // دکمه را بر اساس وضعیت فعلی فعال/غیرفعال کن
        updateAwardButton();
        $btn.text('ثبت امتیاز');
      });
  });

  function reorderLeaderboard() {
    const $list = $('#hs-pts-leaderboard');
    if (!$list.length) return;
    const items = $list.children('li').get();
    items.sort(function (a, b) {
      const sa = parseInt($(a).find('.lb-score').text(), 10) || 0;
      const sb = parseInt($(b).find('.lb-score').text(), 10) || 0;
      return sb - sa;
    });
    $.each(items, function (idx, item) {
      const rank = idx + 1;
      const medal = rank === 1 ? '🥇' : (rank === 2 ? '🥈' : (rank === 3 ? '🥉' : rank + '.'));
      $(item).find('.lb-rank').text(medal);
      $list.append(item);
    });
  }


  // =====================================================
  // ارتباط با والدین (مرحله ۳)
  // =====================================================

  $(document).on('click', '#hs-comms-load', function () {
    const classId = $('#hs-comms-class').val();
    if (!classId) return;
    const url = new URL(window.location.href);
    url.searchParams.set('class_id', classId);
    window.location.href = url.toString();
  });

  // اطلاعیه
  $(document).on('submit', '#hs-form-announcement', function (e) {
    e.preventDefault();
    const classId = $('#hs-comms-panel').data('class-id') || $('#hs-comms-class').val();
    const title = $('#hs-ann-title').val();
    const body = $('#hs-ann-body').val();
    const pinned = $('#hs-ann-pinned').is(':checked') ? 1 : 0;
    const $msg = $('#hs-ann-message');
    const $btn = $(this).find('button[type="submit"]');

    if (!body.trim()) {
      showMessage($msg, 'متن اطلاعیه را وارد کنید', 'error');
      return;
    }

    $btn.prop('disabled', true);
    ajax('hs_add_announcement', {
      class_id: classId,
      title: title,
      body: body,
      pinned: pinned
    })
      .done(function (res) {
        if (res.success) {
          showMessage($msg, res.data.message, 'success');
          const item = res.data.item;
          const pinHtml = item.is_pinned ? '<span class="hs-pin">📌</span>' : '';
          const titleHtml = item.title ? '<strong class="hs-ann-title">' + hsEsc(item.title) + '</strong>' : '';
          const $li = $(
            '<li class="hs-ann-item ' + (item.is_pinned ? 'pinned' : '') + '" data-id="' + item.id + '">' +
            pinHtml + titleHtml +
            '<div class="hs-ann-body"><p>' + hsEsc(item.body).replace(/\n/g, '<br>') + '</p></div>' +
            '<div class="hs-ann-meta"><span>' + hsEsc(item.author) + '</span><span>همین الان</span>' +
            '<button type="button" class="hs-btn-remove hs-ann-delete" data-id="' + item.id + '">×</button></div></li>'
          );
          $('#hs-ann-list .hs-ann-empty').remove();
          if (item.is_pinned) {
            $('#hs-ann-list').prepend($li);
          } else {
            $('#hs-ann-list').append($li);
          }
          $('#hs-ann-title').val('');
          $('#hs-ann-body').val('');
          $('#hs-ann-pinned').prop('checked', false);
        } else {
          showMessage($msg, res.data.message || i18n.error, 'error');
        }
      })
      .fail(function () {
        showMessage($msg, i18n.error, 'error');
      })
      .always(function () {
        $btn.prop('disabled', false);
      });
  });

  $(document).on('click', '.hs-ann-delete', function () {
    if (!confirm('اطلاعیه حذف شود؟')) return;
    const id = $(this).data('id');
    const classId = $('#hs-comms-panel').data('class-id') || $('#hs-comms-class').val();
    const $li = $(this).closest('.hs-ann-item');
    ajax('hs_delete_announcement', { id: id, class_id: classId })
      .done(function (res) {
        if (res.success) $li.fadeOut(200, function () { $(this).remove(); });
      });
  });

  // فید
  $(document).on('submit', '#hs-form-feed', function (e) {
    e.preventDefault();
    const classId = $('#hs-comms-panel').data('class-id') || $('#hs-comms-class').val();
    const body = $('#hs-feed-body').val();
    const $msg = $('#hs-feed-message');
    const $btn = $(this).find('button[type="submit"]');

    if (!body.trim()) {
      showMessage($msg, 'متن را وارد کنید', 'error');
      return;
    }

    $btn.prop('disabled', true);
    ajax('hs_add_feed_post', { class_id: classId, body: body })
      .done(function (res) {
        if (res.success) {
          showMessage($msg, res.data.message, 'success');
          const item = res.data.item;
          const letter = item.author ? item.author.charAt(0) : 'م';
          const $card = $(
            '<article class="hs-feed-card" data-id="' + item.id + '">' +
            '<div class="hs-feed-header">' +
            '<div class="hs-student-avatar">' + letter + '</div>' +
            '<div><strong>' + hsEsc(item.author) + '</strong><span class="hs-muted">همین الان</span></div>' +
            '<button type="button" class="hs-btn-remove hs-feed-delete" data-id="' + item.id + '">×</button></div>' +
            '<div class="hs-feed-body"><p>' + hsEsc(item.body).replace(/\n/g, '<br>') + '</p></div></article>'
          );
          $('.hs-feed-empty').remove();
          $('#hs-feed-list').prepend($card);
          $('#hs-feed-body').val('');
        } else {
          showMessage($msg, res.data.message || i18n.error, 'error');
        }
      })
      .fail(function () {
        showMessage($msg, i18n.error, 'error');
      })
      .always(function () {
        $btn.prop('disabled', false);
      });
  });

  $(document).on('click', '.hs-feed-delete', function () {
    if (!confirm('پست حذف شود؟')) return;
    const id = $(this).data('id');
    const classId = $('#hs-comms-panel').data('class-id') || $('#hs-comms-class').val();
    const $card = $(this).closest('.hs-feed-card');
    ajax('hs_delete_feed_post', { id: id, class_id: classId })
      .done(function (res) {
        if (res.success) $card.fadeOut(200, function () { $(this).remove(); });
      });
  });

  // پیام — ارسال جدید
  $(document).on('submit', '#hs-form-message', function (e) {
    e.preventDefault();
    const classId = $('#hs-comms-panel').data('class-id') || $('#hs-comms-class').val();
    const recipientId = $('#hs-msg-recipient').val();
    const studentId = $('#hs-msg-student').val() || 0;
    const body = $('#hs-msg-body').val();
    const media = $('#hs-msg-image').val() || '';
    const $msg = $('#hs-msg-send-message');
    const $btn = $(this).find('button[type="submit"]');

    if (!recipientId || (!body.trim() && !media)) {
      showMessage($msg, 'گیرنده و متن (یا عکس) الزامی است', 'error');
      return;
    }

    $btn.prop('disabled', true);
    ajax('hs_send_message', {
      recipient_id: recipientId,
      body: body,
      media_url: media,
      class_id: classId,
      student_id: studentId
    })
      .done(function (res) {
        if (res.success) {
          showMessage($msg, res.data.message, 'success');
          $('#hs-msg-body').val('');
          $('#hs-msg-image').val('').trigger('change');
        } else {
          showMessage($msg, res.data.message || i18n.error, 'error');
        }
      })
      .fail(function () {
        showMessage($msg, i18n.error, 'error');
      })
      .always(function () {
        $btn.prop('disabled', false);
      });
  });

  function msgBodyHtml(m) {
    let h = m.body ? '<span>' + hsEsc(m.body) + '</span>' : '';
    if (m.media_url) {
      h += '<a href="' + hsEsc(m.media_url) + '" target="_blank" rel="noopener"><img class="hs-msg-img" src="' + hsEsc(m.media_url) + '" alt="عکس پیام"></a>';
    }
    return h;
  }

  // باز کردن گفتگو
  $(document).on('click', '.hs-msg-thread', function () {
    const otherId = $(this).data('other-id');
    if (!otherId) return;
    const classId = $('#hs-comms-panel').data('class-id') || 0;
    const $chat = $('#hs-msg-chat');
    const $compose = $('#hs-msg-compose');
    const $body = $('#hs-msg-chat-body');

    $compose.hide();
    $chat.show();
    $body.html('<p class="hs-muted">در حال بارگذاری...</p>');
    $('#hs-msg-reply-to').val(otherId);

    ajax('hs_get_conversation', { other_id: otherId, class_id: classId })
      .done(function (res) {
        if (!res.success) {
          $body.html('<p class="error">' + hsEsc(res.data.message || i18n.error) + '</p>');
          return;
        }
        $('#hs-msg-chat-name').text(res.data.other_name || '');
        $body.empty();
        (res.data.messages || []).forEach(function (m) {
          const cls = m.is_mine ? 'mine' : 'theirs';
          // تیک مثل واتساپ: ✓ ارسال، ✓✓ رسید، ✓✓ سبز خوانده شد
          const tick = !m.is_mine ? '' : (m.receipt === 'read'
            ? ' <b style="color:#16a34a">✓✓</b>'
            : (m.receipt === 'delivered' ? ' <b style="color:#94a3b8">✓✓</b>' : ' <b style="color:#94a3b8">✓</b>'));
          $body.append(
            '<div class="hs-msg-bubble ' + cls + '">' + msgBodyHtml(m) +
            '<time title="' + hsEsc(m.created_at) + '">' + (m.edited ? 'ویرایش‌شده · ' : '') + hsEsc(m.time || m.created_at) + tick + '</time></div>'
          );
        });
        $body.scrollTop($body[0].scrollHeight);
        $(this).removeClass('unread');
      }.bind(this))
      .fail(function () {
        $body.html('<p class="error">' + i18n.error + '</p>');
      });
  });

  // پیوست عکس در پاسخ
  $(document).on('click', '#hs-msg-reply-clip', function () {
    $('#hs-msg-reply-file').trigger('click');
  });
  $(document).on('change', '#hs-msg-reply-file', function () {
    const file = this.files && this.files[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('action', 'hs_upload_image');
    fd.append('nonce', hsClass.nonce);
    fd.append('purpose', 'general');
    fd.append('file', file);
    const $clip = $('#hs-msg-reply-clip').text('⏳');
    const inp = this;
    $.ajax({ url: hsClass.ajaxUrl, type: 'POST', data: fd, processData: false, contentType: false })
      .done(function (res) {
        if (res && res.success) {
          $('#hs-msg-reply-media').val(res.data.url);
          $clip.text('✅ عکس پیوست شد');
        } else {
          $clip.text('📎');
          alert((res && res.data && res.data.message) || i18n.error);
        }
      })
      .fail(function () { $clip.text('📎'); alert(i18n.error); })
      .always(function () { inp.value = ''; });
  });

  $(document).on('click', '#hs-msg-back', function () {
    $('#hs-msg-chat').hide();
    $('#hs-msg-compose').show();
  });

  // پاسخ در گفتگو
  $(document).on('submit', '#hs-form-reply', function (e) {
    e.preventDefault();
    const otherId = $('#hs-msg-reply-to').val();
    const body = $('#hs-msg-reply-body').val();
    const media = $('#hs-msg-reply-media').val() || '';
    const classId = $('#hs-comms-panel').data('class-id') || 0;
    if (!otherId || (!body.trim() && !media)) return;

    const $btn = $(this).find('button[type="submit"]');
    $btn.prop('disabled', true);

    ajax('hs_send_message', {
      recipient_id: otherId,
      body: body,
      media_url: media,
      class_id: classId
    })
      .done(function (res) {
        if (res.success) {
          $('#hs-msg-reply-media').val('');
          $('#hs-msg-reply-clip').text('📎');
          $('#hs-msg-chat-body').append(
            '<div class="hs-msg-bubble mine">' + msgBodyHtml({ body: body, media_url: media }) + '<time>همین الان</time></div>'
          );
          $('#hs-msg-reply-body').val('');
          const $body = $('#hs-msg-chat-body');
          $body.scrollTop($body[0].scrollHeight);
        }
      })
      .always(function () {
        $btn.prop('disabled', false);
      });
  });


  // =====================================================
  // ارزیابی گروهی
  // =====================================================

  $(document).on('click', '#hs-assess-load', function () {
    const classId = $('#hs-assess-class').val();
    if (!classId) return;
    const url = new URL(window.location.href);
    url.searchParams.set('class_id', classId);
    url.searchParams.delete('assessment_id');
    window.location.href = url.toString();
  });

  $(document).on('submit', '#hs-form-create-assessment', function (e) {
    e.preventDefault();
    const classId = $('#hs-assess-panel').data('class-id') || $('#hs-assess-class').val();
    const title = $('#hs-assess-title').val();
    const subject = $('#hs-assess-subject').val();
    const date = $('#hs-assess-date').val();
    const scaleType = $('input[name="scale_type"]:checked').val() || 'descriptive';
    const $msg = $('#hs-assess-create-msg');
    const $btn = $(this).find('button[type="submit"]');

    if (!title.trim()) {
      showMessage($msg, 'عنوان را وارد کنید', 'error');
      return;
    }

    $btn.prop('disabled', true).text('در حال ساخت...');
    ajax('hs_create_assessment', {
      class_id: classId,
      title: title,
      subject: subject,
      date: date,
      scale_type: scaleType
    })
      .done(function (res) {
        if (res.success) {
          showMessage($msg, res.data.message, 'success');
          if (res.data.redirect) {
            setTimeout(function () {
              window.location.href = res.data.redirect;
            }, 600);
          }
        } else {
          showMessage($msg, res.data.message || i18n.error, 'error');
          $btn.prop('disabled', false).text('+ ساخت و شروع ارزیابی');
        }
      })
      .fail(function () {
        showMessage($msg, i18n.error, 'error');
        $btn.prop('disabled', false).text('+ ساخت و شروع ارزیابی');
      });
  });

  $(document).on('click', '.hs-assess-delete', function () {
    if (!confirm('این ارزیابی و همه نتایج آن حذف شود؟')) return;
    const id = $(this).data('id');
    const $li = $(this).closest('.hs-assess-list-item');
    ajax('hs_delete_assessment', { assessment_id: id })
      .done(function (res) {
        if (res.success) $li.fadeOut(200, function () { $(this).remove(); });
        else alert(res.data.message || i18n.error);
      });
  });

  // سطح توصیفی
  $(document).on('change', '.hs-assess-level-btn input', function () {
    const $group = $(this).closest('.hs-assess-levels');
    $group.find('.hs-assess-level-btn').removeClass('active').css({ borderColor: '', background: '' });
    const $btn = $(this).closest('.hs-assess-level-btn');
    $btn.addClass('active');
  });

  // اعمال برای همه
  $(document).on('click', '.hs-assess-bulk', function () {
    const level = $(this).data('level');
    $('.hs-assess-result-row').each(function () {
      const $input = $(this).find('input[value="' + level + '"]');
      if ($input.length) {
        $input.prop('checked', true).trigger('change');
      }
    });
  });

  // ذخیره نتایج
  $(document).on('submit', '#hs-form-assess-results', function (e) {
    e.preventDefault();
    const assessmentId = $('#hs-assess-panel').data('assessment-id');
    const $msg = $('#hs-assess-save-msg');
    const $btn = $('#hs-assess-save');
    const results = {};

    $('.hs-assess-result-row').each(function () {
      const sid = $(this).data('student-id');
      const level = $(this).find('input[type="radio"]:checked').val() || '';
      const score = $(this).find('.hs-assess-score').val();
      const comment = $(this).find('.hs-assess-comment').val() || '';
      results[sid] = {
        level_key: level,
        score: score !== undefined && score !== '' ? score : null,
        comment: comment
      };
    });

    $btn.prop('disabled', true).text('در حال ذخیره...');
    ajax('hs_save_assessment_results', {
      assessment_id: assessmentId,
      results: results
    })
      .done(function (res) {
        if (res.success) {
          showMessage($msg, res.data.message, 'success');
          // به‌روزرسانی خلاصه
          if (res.data.summary) {
            const s = res.data.summary;
            let html = '';
            if (s.type === 'numeric') {
              html = '<span>میانگین: <strong>' + s.avg + '</strong></span>' +
                '<span>ثبت‌شده: <strong>' + s.count + '</strong></span>';
            } else if (s.levels) {
              const labels = {
                excellent: 'خیلی خوب', good: 'خوب', acceptable: 'قابل قبول',
                needs_work: 'نیاز به تلاش', absent: 'غایب'
              };
              $.each(s.levels, function (k, cnt) {
                if (cnt > 0) {
                  html += '<span>' + (labels[k] || k) + ': <strong>' + cnt + '</strong></span> ';
                }
              });
            }
            $('#hs-assess-summary').html(html);
          }
        } else {
          showMessage($msg, res.data.message || i18n.error, 'error');
        }
      })
      .fail(function () {
        showMessage($msg, i18n.error, 'error');
      })
      .always(function () {
        $btn.prop('disabled', false).text('💾 ذخیره نتایج');
      });
  });


  // =====================================================
  // گزارش پیشرفت
  // =====================================================

  $(document).on('click', '#hs-report-load', function () {
    const classId = $('#hs-report-class').val();
    const month = $('#hs-report-month').val();
    const year = $('#hs-report-year').val();
    const url = new URL(window.location.href);
    url.searchParams.set('class_id', classId);
    url.searchParams.set('month', month);
    url.searchParams.set('year', year);
    window.location.href = url.toString();
  });

  $(document).on('click', '.hs-report-student-btn', function () {
    const studentId = $(this).data('student-id');
    const classId = $('#hs-reports-panel').data('class-id');
    const year = $('#hs-reports-panel').data('year');
    const month = $('#hs-reports-panel').data('month');
    const $modal = $('#hs-report-modal');
    const $content = $('#hs-report-modal-content');

    $content.html('<p class="hs-muted">در حال بارگذاری گزارش...</p>');
    $modal.show();

    ajax('hs_get_student_report', {
      class_id: classId,
      student_id: studentId,
      year: year,
      month: month
    })
      .done(function (res) {
        if (!res.success) {
          $content.html('<p class="error">' + hsEsc(res.data.message || i18n.error) + '</p>');
          return;
        }
        const r = res.data.report;
        let html = '<div class="hs-sr-header">';
        html += '<h3>گزارش پیشرفت — ' + hsEsc(r.student_name) + '</h3>';
        html += '<p>' + r.class_name;
        if (r.grade) html += ' · پایه ' + r.grade;
        html += ' · ' + r.year + '/' + r.month + '</p></div>';

        html += '<div class="hs-sr-grid">';
        html += '<div class="hs-sr-stat"><div class="num">' + r.points + '</div><div class="lbl">مجموع امتیاز</div></div>';
        html += '<div class="hs-sr-stat"><div class="num">' + (r.att_rate !== null ? r.att_rate + '%' : '—') + '</div><div class="lbl">نرخ حضور</div></div>';
        html += '<div class="hs-sr-stat"><div class="num">' + r.attendance.present + '</div><div class="lbl">روز حاضر</div></div>';
        html += '<div class="hs-sr-stat"><div class="num">' + r.attendance.absent + '</div><div class="lbl">روز غایب</div></div>';
        html += '</div>';

        if (r.assessments && r.assessments.length) {
          html += '<div class="hs-sr-section"><h4>ارزیابی‌ها</h4><ul>';
          r.assessments.forEach(function (a) {
            html += '<li><span>' + a.title;
            if (a.subject) html += ' <small>(' + a.subject + ')</small>';
            html += '</span><strong>' + a.value + '</strong></li>';
          });
          html += '</ul></div>';
        }

        if (r.points_history && r.points_history.length) {
          html += '<div class="hs-sr-section"><h4>آخرین امتیازها</h4><ul>';
          r.points_history.slice(0, 10).forEach(function (h) {
            const sign = h.points >= 0 ? '+' : '';
            html += '<li><span>' + h.behavior + '</span><strong>' + sign + h.points + '</strong></li>';
          });
          html += '</ul></div>';
        }

        $content.html(html);
      })
      .fail(function () {
        $content.html('<p class="error">' + i18n.error + '</p>');
      });
  });

  function closeReportModal() {
    $('#hs-report-modal').hide();
  }
  $(document).on('click', '#hs-report-modal-close, #hs-report-modal-x', closeReportModal);

  $(document).on('click', '#hs-report-print', function () {
    window.print();
  });


  // =====================================================
  // تکالیف
  // =====================================================

  $(document).on('click', '#hs-hw-load', function () {
    const classId = $('#hs-hw-class').val();
    if (!classId) return;
    const url = new URL(window.location.href);
    url.searchParams.set('class_id', classId);
    url.searchParams.delete('homework_id');
    window.location.href = url.toString();
  });

  $(document).on('submit', '#hs-form-create-hw', function (e) {
    e.preventDefault();
    const classId = $('#hs-hw-panel').data('class-id') || $('#hs-hw-class').val();
    const $msg = $('#hs-hw-create-msg');
    const $btn = $(this).find('button[type="submit"]');
    const title = $('#hs-hw-title').val();

    if (!title.trim()) {
      showMessage($msg, 'عنوان را وارد کنید', 'error');
      return;
    }

    $btn.prop('disabled', true).text('در حال ثبت...');
    ajax('hs_create_homework', {
      class_id: classId,
      title: title,
      subject: $('#hs-hw-subject').val(),
      due_date: $('#hs-hw-due').val(),
      description: $('#hs-hw-desc').val()
    })
      .done(function (res) {
        if (res.success) {
          showMessage($msg, res.data.message, 'success');
          if (res.data.redirect) {
            setTimeout(function () { window.location.href = res.data.redirect; }, 600);
          }
        } else {
          showMessage($msg, res.data.message || i18n.error, 'error');
          $btn.prop('disabled', false).text('+ ثبت تکلیف');
        }
      })
      .fail(function () {
        showMessage($msg, i18n.error, 'error');
        $btn.prop('disabled', false).text('+ ثبت تکلیف');
      });
  });

  $(document).on('click', '.hs-hw-delete', function () {
    if (!confirm('این تکلیف و وضعیت‌های آن حذف شود؟')) return;
    const id = $(this).data('id');
    const $li = $(this).closest('.hs-hw-list-item');
    ajax('hs_delete_homework', { homework_id: id })
      .done(function (res) {
        if (res.success) $li.fadeOut(200, function () { $(this).remove(); });
        else alert(res.data.message || i18n.error);
      });
  });

  $(document).on('change', '.hs-hw-status-btn input', function () {
    const $group = $(this).closest('.hs-hw-statuses');
    $group.find('.hs-hw-status-btn').removeClass('active').css({ borderColor: '', background: '' });
    const $btn = $(this).closest('.hs-hw-status-btn');
    const color = $btn.data('color');
    $btn.addClass('active');
    if (color) {
      $btn.css({ borderColor: color, background: color + '18' });
    }
  });

  $(document).on('click', '.hs-hw-bulk', function () {
    const status = $(this).data('status');
    $('.hs-hw-row').each(function () {
      const $input = $(this).find('input[value="' + status + '"]');
      if ($input.length) $input.prop('checked', true).trigger('change');
    });
  });

  $(document).on('submit', '#hs-form-hw-status', function (e) {
    e.preventDefault();
    const homeworkId = $('#hs-hw-panel').data('homework-id');
    const $msg = $('#hs-hw-save-msg');
    const $btn = $('#hs-hw-save');
    const records = {};

    $('.hs-hw-row').each(function () {
      const sid = $(this).data('student-id');
      records[sid] = {
        status: $(this).find('input[type="radio"]:checked').val() || 'pending',
        feedback: $(this).find('.hs-hw-feedback').val() || ''
      };
    });

    $btn.prop('disabled', true).text('در حال ذخیره...');
    ajax('hs_save_homework_status', {
      homework_id: homeworkId,
      records: records
    })
      .done(function (res) {
        if (res.success) {
          showMessage($msg, res.data.message, 'success');
          if (res.data.summary) {
            const labels = {
              pending: 'در انتظار', done: 'انجام‌شده',
              partial: 'ناقص', missing: 'انجام‌نشده'
            };
            let html = '';
            $.each(res.data.summary, function (k, cnt) {
              if (cnt > 0) html += '<span>' + (labels[k] || k) + ': <strong>' + cnt + '</strong></span> ';
            });
            $('#hs-hw-summary').html(html);
          }
        } else {
          showMessage($msg, res.data.message || i18n.error, 'error');
        }
      })
      .fail(function () {
        showMessage($msg, i18n.error, 'error');
      })
      .always(function () {
        $btn.prop('disabled', false).text('💾 ذخیره وضعیت');
      });
  });


  // =====================================================
  // پورتفولیو
  // =====================================================

  $(document).on('click', '#hs-pf-load', function () {
    const classId = $('#hs-pf-class').val();
    const studentId = $('#hs-pf-student-filter').val() || 0;
    const url = new URL(window.location.href);
    url.searchParams.set('class_id', classId);
    if (studentId && studentId !== '0') {
      url.searchParams.set('student_id', studentId);
    } else {
      url.searchParams.delete('student_id');
    }
    window.location.href = url.toString();
  });

  $(document).on('submit', '#hs-form-add-portfolio', function (e) {
    e.preventDefault();
    const classId = $('#hs-portfolio-panel').data('class-id') || $('#hs-pf-class').val();
    const $msg = $('#hs-pf-msg');
    const $btn = $(this).find('button[type="submit"]');

    const studentId = $('#hs-pf-student').val();
    const title = $('#hs-pf-title').val();
    if (!studentId || !title.trim()) {
      showMessage($msg, 'دانش‌آموز و عنوان الزامی است', 'error');
      return;
    }

    $btn.prop('disabled', true).text('در حال ثبت...');
    ajax('hs_add_portfolio_item', {
      class_id: classId,
      student_id: studentId,
      title: title,
      description: $('#hs-pf-desc').val(),
      item_type: $('#hs-pf-type').val(),
      media_url: $('#hs-pf-media').val(),
      status: 'approved'
    })
      .done(function (res) {
        if (res.success) {
          showMessage($msg, res.data.message, 'success');
          // افزودن کارت به گالری
          const item = res.data.item;
          const hasImg = item.media_url && /\.(jpe?g|png|gif|webp)(\?|$)/i.test(item.media_url);
          let thumb = hasImg
            ? '<div class="hs-pf-thumb"><img src="' + hsEsc(item.media_url) + '" alt=""></div>'
            : '<div class="hs-pf-thumb hs-pf-thumb-icon"><span>' + hsEsc(item.type_icon) + '</span></div>';
          const $card = $(
            '<article class="hs-pf-card" data-id="' + item.id + '" data-status="' + hsEsc(item.status) + '">' +
            thumb +
            '<div class="hs-pf-body">' +
            '<div class="hs-pf-meta"><span class="hs-pf-type">' + hsEsc(item.type_icon) + ' ' + hsEsc(item.type_label) + '</span>' +
            '<span class="hs-pf-status" style="color:#10B981">تأییدشده</span></div>' +
            '<h4>' + hsEsc(item.title) + '</h4>' +
            '<p class="hs-pf-student">' + hsEsc(item.student_name) + '</p>' +
            (item.description ? '<p class="hs-pf-desc">' + hsEsc(item.description.substring(0, 100)) + '</p>' : '') +
            '<div class="hs-pf-actions">' +
            '<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost hs-pf-delete" data-id="' + item.id + '">حذف</button>' +
            '</div></div></article>'
          );
          let $gallery = $('#hs-pf-gallery');
          if (!$gallery.length) {
            // اگر گالری خالی بود، صفحه را رفرش کن
            setTimeout(function () { location.reload(); }, 800);
          } else {
            $gallery.prepend($card);
          }
          $('#hs-pf-title').val('');
          $('#hs-pf-desc').val('');
          $('#hs-pf-media').val('').trigger('change');
        } else {
          showMessage($msg, res.data.message || i18n.error, 'error');
        }
      })
      .fail(function () {
        showMessage($msg, i18n.error, 'error');
      })
      .always(function () {
        $btn.prop('disabled', false).text('+ افزودن به پورتفولیو');
      });
  });

  $(document).on('click', '.hs-pf-approve', function () {
    const id = $(this).data('id');
    const $card = $(this).closest('.hs-pf-card');
    ajax('hs_update_portfolio_status', { id: id, status: 'approved' })
      .done(function (res) {
        if (res.success) {
          $card.attr('data-status', 'approved');
          $card.find('.hs-pf-status').text('تأییدشده').css('color', '#10B981');
          $card.find('.hs-pf-approve').remove();
        }
      });
  });

  $(document).on('click', '.hs-pf-reject', function () {
    const id = $(this).data('id');
    const $card = $(this).closest('.hs-pf-card');
    ajax('hs_update_portfolio_status', { id: id, status: 'rejected' })
      .done(function (res) {
        if (res.success) {
          $card.attr('data-status', 'rejected');
          $card.find('.hs-pf-status').text('ردشده').css('color', '#EF4444');
          $card.find('.hs-pf-reject').remove();
        }
      });
  });

  $(document).on('click', '.hs-pf-delete', function () {
    if (!confirm('این اثر حذف شود؟')) return;
    const id = $(this).data('id');
    const $card = $(this).closest('.hs-pf-card');
    ajax('hs_delete_portfolio_item', { id: id })
      .done(function (res) {
        if (res.success) $card.fadeOut(200, function () { $(this).remove(); });
      });
  });


  // =====================================================
  // ابزارهای کلاس
  // =====================================================

  $(document).on('click', '#hs-tools-load', function () {
    const classId = $('#hs-tools-class').val();
    if (!classId) return;
    const url = new URL(window.location.href);
    url.searchParams.set('class_id', classId);
    window.location.href = url.toString();
  });

  // تب‌های ابزار
  $(document).on('click', '.hs-tools-panel .hs-comms-tab', function () {
    const tool = $(this).data('tool');
    $('.hs-tools-panel .hs-comms-tab').removeClass('active');
    $(this).addClass('active');
    $('.hs-tool-section').hide();
    $('#hs-tool-' + tool).show();
  });

  // ---------- تایمر ----------
  let timerInterval = null;
  let timerRemaining = 300; // ثانیه
  let timerRunning = false;
  let stopwatchElapsed = 0;

  function formatTime(sec) {
    sec = Math.max(0, Math.floor(sec));
    const m = Math.floor(sec / 60);
    const s = sec % 60;
    return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
  }

  function updateTimerDisplay() {
    const mode = $('input[name="timer_mode"]:checked').val();
    const val = mode === 'stopwatch' ? stopwatchElapsed : timerRemaining;
    const $d = $('#hs-timer-display');
    $d.text(formatTime(val));
    $d.removeClass('warning danger done');
    if (mode === 'countdown') {
      if (timerRemaining <= 0) {
        $d.addClass('done');
      } else if (timerRemaining <= 10) {
        $d.addClass('danger');
      } else if (timerRemaining <= 30) {
        $d.addClass('warning');
      }
    }
  }

  function playBeep() {
    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.frequency.value = 880;
      gain.gain.value = 0.3;
      osc.start();
      setTimeout(function () {
        osc.stop();
        ctx.close();
      }, 400);
    } catch (e) { /* ignore */ }
  }

  function stopTimer() {
    if (timerInterval) {
      clearInterval(timerInterval);
      timerInterval = null;
    }
    timerRunning = false;
    $('#hs-timer-start').prop('disabled', false).text('▶ شروع');
    $('#hs-timer-pause').prop('disabled', true);
  }

  $(document).on('click', '.hs-timer-preset', function () {
    stopTimer();
    timerRemaining = parseInt($(this).data('sec'), 10) || 60;
    stopwatchElapsed = 0;
    const m = Math.floor(timerRemaining / 60);
    const s = timerRemaining % 60;
    $('#hs-timer-min').val(m);
    $('#hs-timer-sec').val(s);
    updateTimerDisplay();
    $('#hs-timer-status').text('');
  });

  $(document).on('click', '#hs-timer-set', function () {
    stopTimer();
    const m = parseInt($('#hs-timer-min').val(), 10) || 0;
    const s = parseInt($('#hs-timer-sec').val(), 10) || 0;
    timerRemaining = m * 60 + s;
    stopwatchElapsed = 0;
    updateTimerDisplay();
    $('#hs-timer-status').text('');
  });

  $(document).on('click', '#hs-timer-start', function () {
    if (timerRunning) return;
    const mode = $('input[name="timer_mode"]:checked').val();
    if (mode === 'countdown' && timerRemaining <= 0) {
      const m = parseInt($('#hs-timer-min').val(), 10) || 0;
      const s = parseInt($('#hs-timer-sec').val(), 10) || 0;
      timerRemaining = m * 60 + s;
      if (timerRemaining <= 0) return;
    }
    timerRunning = true;
    $('#hs-timer-start').prop('disabled', true);
    $('#hs-timer-pause').prop('disabled', false);
    $('#hs-timer-status').text(mode === 'stopwatch' ? 'کرونومتر در حال اجرا...' : 'شمارش معکوس...');

    timerInterval = setInterval(function () {
      if (mode === 'stopwatch') {
        stopwatchElapsed++;
        updateTimerDisplay();
      } else {
        timerRemaining--;
        updateTimerDisplay();
        if (timerRemaining <= 0) {
          stopTimer();
          $('#hs-timer-status').text('⏰ زمان تمام شد!');
          playBeep();
          setTimeout(playBeep, 500);
          setTimeout(playBeep, 1000);
        }
      }
    }, 1000);
  });

  $(document).on('click', '#hs-timer-pause', function () {
    stopTimer();
    $('#hs-timer-status').text('متوقف شد');
  });

  $(document).on('click', '#hs-timer-reset', function () {
    stopTimer();
    const mode = $('input[name="timer_mode"]:checked').val();
    if (mode === 'stopwatch') {
      stopwatchElapsed = 0;
    } else {
      const m = parseInt($('#hs-timer-min').val(), 10) || 0;
      const s = parseInt($('#hs-timer-sec').val(), 10) || 0;
      timerRemaining = m * 60 + s;
    }
    updateTimerDisplay();
    $('#hs-timer-status').text('');
  });

  $(document).on('change', 'input[name="timer_mode"]', function () {
    stopTimer();
    updateTimerDisplay();
    $('#hs-timer-status').text('');
  });

  // ---------- انتخاب تصادفی ----------
  let pickerPool = [];
  let pickerHistory = [];

  function initPicker() {
    try {
      const data = JSON.parse($('#hs-tools-panel').attr('data-students') || '[]');
      pickerPool = data.map(function (s) { return s; });
      pickerHistory = [];
      updatePickerUI();
    } catch (e) {
      pickerPool = [];
    }
  }

  function updatePickerUI() {
    const total = (function () {
      try {
        return JSON.parse($('#hs-tools-panel').attr('data-students') || '[]').length;
      } catch (e) { return 0; }
    })();
    $('#hs-picker-remaining').text(
      pickerPool.length
        ? (pickerPool.length + ' نفر باقی‌مانده از ' + total)
        : (total ? 'همه انتخاب شدند — لیست را ریست کنید' : '')
    );
  }

  $(document).on('click', '#hs-picker-go', function () {
    if (!pickerPool.length) {
      initPicker();
    }
    // اگر exclude خاموش است، همیشه از لیست کامل استفاده کن
    if (!$('#hs-picker-exclude').is(':checked')) {
      try {
        pickerPool = JSON.parse($('#hs-tools-panel').attr('data-students') || '[]');
      } catch (e) { /* */ }
    }
    if (!pickerPool.length) {
      $('#hs-picker-result').html('<span class="hs-picker-placeholder">دانش‌آموزی باقی نمانده</span>');
      return;
    }
    const idx = Math.floor(Math.random() * pickerPool.length);
    const chosen = pickerPool[idx];

    // انیمیشن ساده
    const $result = $('#hs-picker-result');
    $result.html('<span class="hs-picker-placeholder">...</span>');
    setTimeout(function () {
      $result.html('<span class="name">' + chosen.name + '</span>');
    }, 300);

    pickerHistory.push(chosen);
    if ($('#hs-picker-exclude').is(':checked')) {
      pickerPool.splice(idx, 1);
    }
    $('#hs-picker-history').prepend('<li>' + chosen.name + '</li>');
    updatePickerUI();
  });

  $(document).on('click', '#hs-picker-reset', function () {
    initPicker();
    $('#hs-picker-history').empty();
    $('#hs-picker-result').html('<span class="hs-picker-placeholder">برای انتخاب کلیک کنید</span>');
  });

  // ---------- گروه‌ساز ----------
  $(document).on('click', '#hs-groups-make', function () {
    let students;
    try {
      students = JSON.parse($('#hs-tools-panel').attr('data-students') || '[]');
    } catch (e) {
      students = [];
    }
    if (!students.length) return;

    const count = Math.max(2, Math.min(12, parseInt($('#hs-groups-count').val(), 10) || 4));

    // شافل
    const shuffled = students.slice();
    for (let i = shuffled.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      const t = shuffled[i];
      shuffled[i] = shuffled[j];
      shuffled[j] = t;
    }

    const groups = [];
    for (let g = 0; g < count; g++) {
      groups.push([]);
    }
    shuffled.forEach(function (s, i) {
      groups[i % count].push(s);
    });

    const $grid = $('#hs-groups-grid').empty();
    groups.forEach(function (members, i) {
      if (!members.length) return;
      let html = '<div class="hs-group-card" data-score="0"><h4>گروه ' + (i + 1) + ' (' + members.length + ' نفر)</h4><ul>';
      members.forEach(function (m) {
        html += '<li>' + hsEsc(m.name) + '</li>';
      });
      html += '</ul><div class="hs-group-score">' +
        '<button type="button" class="hs-gs-btn hs-gs-minus" aria-label="کم کردن امتیاز">−</button>' +
        '<span class="hs-gs-val">0</span>' +
        '<button type="button" class="hs-gs-btn hs-gs-plus" aria-label="اضافه کردن امتیاز">+</button></div></div>';
      $grid.append(html);
    });
  });

  // امتیاز گروه‌ها
  function faNum(n) {
    return String(n).replace(/[0-9]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.charAt(d); }).replace('-', '−');
  }
  $(document).on('click', '.hs-gs-btn', function () {
    const $card = $(this).closest('.hs-group-card');
    const v = (parseInt($card.attr('data-score'), 10) || 0) + ($(this).hasClass('hs-gs-plus') ? 1 : -1);
    $card.attr('data-score', v).find('.hs-gs-val').text(faNum(v));
    let best = null;
    $('#hs-groups-grid .hs-group-card').each(function () {
      const s = parseInt($(this).attr('data-score'), 10) || 0;
      $(this).removeClass('is-leader');
      if (best === null || s > best.s) best = { s: s, el: this };
    });
    if (best && best.s > 0) $(best.el).addClass('is-leader');
  });

  // تاس و سکه
  const DICE = ['⚀', '⚁', '⚂', '⚃', '⚄', '⚅'];
  $(document).on('click', '#hs-dice-roll', function () {
    const n = Math.max(1, Math.min(3, parseInt($('#hs-dice-count').val(), 10) || 1));
    const $out = $('#hs-dice-out');
    let frames = 0;
    const timer = setInterval(function () {
      const vals = [];
      for (let i = 0; i < n; i++) vals.push(Math.floor(Math.random() * 6));
      $out.html(vals.map(function (v) { return '<span class="hs-die">' + DICE[v] + '</span>'; }).join(''));
      if (++frames >= 8) {
        clearInterval(timer);
        $('#hs-dice-sum').text('مجموع: ' + faNum(vals.reduce(function (a, v) { return a + v + 1; }, 0)));
      }
    }, 70);
  });
  $(document).on('click', '#hs-coin-flip', function () {
    const $c = $('#hs-coin').removeClass('flip');
    void $c[0].offsetWidth;
    const heads = Math.random() < 0.5;
    $c.addClass('flip').text(heads ? '👑' : '🌿');
    $('#hs-coin-res').text(heads ? 'شیر' : 'خط');
  });

  // سروصداسنج (با میکروفون دستگاه؛ صدا ضبط یا ارسال نمی‌شود)
  let noiseCtx = null, noiseStream = null, noiseRaf = 0;
  function stopNoise() {
    if (noiseRaf) cancelAnimationFrame(noiseRaf);
    noiseRaf = 0;
    if (noiseStream) noiseStream.getTracks().forEach(function (t) { t.stop(); });
    if (noiseCtx) { try { noiseCtx.close(); } catch (e) {} }
    noiseCtx = noiseStream = null;
    $('#hs-noise-toggle').text('🎙️ شروع اندازه‌گیری');
    $('#hs-noise-bar').css('width', '0%');
    $('#hs-noise-state').text('خاموش');
  }
  $(document).on('click', '#hs-noise-toggle', function () {
    if (noiseCtx) { stopNoise(); return; }
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      $('#hs-noise-state').text('مرورگر یا اتصال (نیازمند https) از میکروفون پشتیبانی نمی‌کند');
      return;
    }
    navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
      noiseStream = stream;
      const AC = window.AudioContext || window.webkitAudioContext;
      noiseCtx = new AC();
      const src = noiseCtx.createMediaStreamSource(stream);
      const an = noiseCtx.createAnalyser();
      an.fftSize = 512;
      src.connect(an);
      const data = new Uint8Array(an.fftSize);
      $('#hs-noise-toggle').text('⏹ توقف');
      (function loop() {
        an.getByteTimeDomainData(data);
        let sum = 0;
        for (let i = 0; i < data.length; i++) { const v = (data[i] - 128) / 128; sum += v * v; }
        const level = Math.min(100, Math.round(Math.sqrt(sum / data.length) * 320));
        const limit = parseInt($('#hs-noise-limit').val(), 10) || 60;
        $('#hs-noise-bar').css('width', level + '%').toggleClass('is-loud', level >= limit);
        $('#hs-noise-state').text(level >= limit ? '🔴 سروصدا زیاد است! لطفاً آرام‌تر' : (level > limit * 0.6 ? '🟡 کمی شلوغ' : '🟢 آرام و عالی'));
        noiseRaf = requestAnimationFrame(loop);
      })();
    }).catch(function () {
      $('#hs-noise-state').text('اجازهٔ دسترسی به میکروفون داده نشد');
    });
  });
  $(document).on('click', '.hs-tools-panel .hs-comms-tab', function () {
    if ($(this).data('tool') !== 'noise') stopNoise();
  });

  // init picker on load if panel exists
  if ($('#hs-tools-panel').length) {
    initPicker();
    updateTimerDisplay();
  }


  // =====================================================
  // کتابخانه کاربرگ — ارسال به کلاس
  // =====================================================

  $(document).on('click', '.hs-ws-assign-btn', function () {
    const id = $(this).data('id');
    const title = $(this).data('title');
    $('#hs-ws-assign-id').val(id);
    $('#hs-ws-assign-title').text(title);
    $('#hs-ws-assign-msg').empty();
    $('#hs-ws-assign-modal').show();
  });

  $(document).on('click', '#hs-ws-assign-close, #hs-ws-assign-x', function () {
    $('#hs-ws-assign-modal').hide();
  });

  $(document).on('submit', '#hs-form-assign-ws', function (e) {
    e.preventDefault();
    const $msg = $('#hs-ws-assign-msg');
    const $btn = $(this).find('button[type="submit"]');
    $btn.prop('disabled', true);

    ajax('hs_assign_worksheet', {
      worksheet_id: $('#hs-ws-assign-id').val(),
      class_id: $('#hs-ws-assign-class').val(),
      due_date: $('#hs-ws-assign-due').val(),
      note: $('#hs-ws-assign-note').val()
    })
      .done(function (res) {
        if (res.success) {
          showMessage($msg, res.data.message, 'success');
          if (res.data.redirect) {
            setTimeout(function () {
              window.location.href = res.data.redirect;
            }, 900);
          }
        } else {
          showMessage($msg, res.data.message || i18n.error, 'error');
          $btn.prop('disabled', false);
        }
      })
      .fail(function () {
        showMessage($msg, i18n.error, 'error');
        $btn.prop('disabled', false);
      });
  });


  // =====================================================
  // تکلیف آنلاین — سازنده + پاسخ
  // =====================================================

  let ohQuestionIndex = 0;

  function ohAddQuestion(data) {
    data = data || {};
    const idx = ohQuestionIndex++;
    const type = data.type || 'multiple_choice';
    const $box = $(
      '<div class="hs-oq-editor" data-idx="' + idx + '">' +
        '<div class="hs-oq-editor-head">' +
          '<select class="oh-type">' +
            '<option value="multiple_choice">چندگزینه‌ای</option>' +
            '<option value="true_false">درست/نادرست</option>' +
            '<option value="fill_blank">جای خالی</option>' +
            '<option value="open">تشریحی</option>' +
          '</select>' +
          '<input type="number" class="oh-points" value="1" min="1" max="10" style="width:70px" title="نمره">' +
          '<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost oh-remove">حذف</button>' +
        '</div>' +
        '<textarea class="oh-prompt" rows="2" placeholder="متن سؤال..." required></textarea>' +
        '<div class="oh-options-wrap">' +
          '<div class="hs-oq-editor-opts">' +
            '<input type="text" class="oh-opt" placeholder="گزینه ۱">' +
            '<input type="text" class="oh-opt" placeholder="گزینه ۲">' +
            '<input type="text" class="oh-opt" placeholder="گزینه ۳">' +
            '<input type="text" class="oh-opt" placeholder="گزینه ۴ (اختیاری)">' +
          '</div>' +
          '<input type="text" class="oh-correct" placeholder="پاسخ صحیح (متن دقیق یکی از گزینه‌ها یا true/false)" style="margin-top:0.5rem;width:100%;">' +
        '</div>' +
      '</div>'
    );
    $box.find('.oh-type').val(type);
    $('#hs-oh-questions').append($box);
    ohToggleOptions($box);
  }

  function ohToggleOptions($box) {
    const type = $box.find('.oh-type').val();
    const $wrap = $box.find('.oh-options-wrap');
    if (type === 'open') {
      $wrap.hide();
    } else if (type === 'true_false') {
      $wrap.find('.hs-oq-editor-opts').hide();
      $wrap.find('.oh-correct').show().attr('placeholder', 'true یا false');
      $wrap.show();
    } else if (type === 'fill_blank') {
      $wrap.find('.hs-oq-editor-opts').hide();
      $wrap.find('.oh-correct').show().attr('placeholder', 'پاسخ صحیح جای خالی');
      $wrap.show();
    } else {
      $wrap.find('.hs-oq-editor-opts').show();
      $wrap.find('.oh-correct').show().attr('placeholder', 'متن دقیق گزینه صحیح');
      $wrap.show();
    }
  }

  $(document).on('click', '#hs-oh-add-q', function () {
    ohAddQuestion();
  });

  $(document).on('change', '.oh-type', function () {
    ohToggleOptions($(this).closest('.hs-oq-editor'));
  });

  $(document).on('click', '.oh-remove', function () {
    $(this).closest('.hs-oq-editor').remove();
  });

  // یک سؤال پیش‌فرض
  if ($('#hs-online-builder').length && $('#hs-oh-questions').children().length === 0) {
    ohAddQuestion();
  }

  $(document).on('submit', '#hs-form-online-hw', function (e) {
    e.preventDefault();
    const $msg = $('#hs-oh-msg');
    const $btn = $(this).find('button[type="submit"]');
    const title = $('#hs-oh-title').val().trim();
    if (!title) {
      showMessage($msg, 'عنوان را وارد کنید', 'error');
      return;
    }

    const questions = [];
    $('#hs-oh-questions .hs-oq-editor').each(function () {
      const type = $(this).find('.oh-type').val();
      const prompt = $(this).find('.oh-prompt').val().trim();
      if (!prompt) return;
      const opts = [];
      $(this).find('.oh-opt').each(function () {
        const v = $(this).val().trim();
        if (v) opts.push(v);
      });
      questions.push({
        type: type,
        prompt: prompt,
        options: opts,
        correct: $(this).find('.oh-correct').val().trim(),
        points: parseInt($(this).find('.oh-points').val(), 10) || 1
      });
    });

    if (!questions.length) {
      showMessage($msg, 'حداقل یک سؤال با متن وارد کنید', 'error');
      return;
    }

    $btn.prop('disabled', true).text('در حال ساخت...');
    ajax('hs_create_online_homework', {
      class_id: $('#hs-oh-class').val(),
      title: title,
      subject: $('#hs-oh-subject').val(),
      due_date: $('#hs-oh-due').val(),
      description: $('#hs-oh-desc').val(),
      questions: questions
    })
      .done(function (res) {
        if (res.success) {
          showMessage($msg, res.data.message, 'success');
          if (res.data.redirect) {
            setTimeout(function () { window.location.href = res.data.redirect; }, 800);
          }
        } else {
          showMessage($msg, res.data.message || i18n.error, 'error');
          $btn.prop('disabled', false).text('ساخت و انتشار تکلیف آنلاین');
        }
      })
      .fail(function () {
        showMessage($msg, i18n.error, 'error');
        $btn.prop('disabled', false).text('ساخت و انتشار تکلیف آنلاین');
      });
  });

  // ارسال پاسخ دانش‌آموز
  $(document).on('submit', '#hs-form-take-hw', function (e) {
    e.preventDefault();
    const $panel = $('#hs-online-take');
    const hwId = $panel.data('homework-id');
    const studentId = $panel.data('student-id');
    const $msg = $('#hs-take-msg');
    const $btn = $(this).find('button[type="submit"]');
    const answers = {};

    $('.hs-oq').each(function () {
      const qid = $(this).data('qid');
      const type = $(this).data('type');
      let val = '';
      if (type === 'fill_blank' || type === 'open') {
        val = $(this).find('.hs-oq-fill').val() || '';
      } else {
        val = $(this).find('input[type="radio"]:checked').val() || '';
      }
      answers[qid] = val;
    });

    $btn.prop('disabled', true).text('در حال ارسال...');
    ajax('hs_submit_online_hw', {
      homework_id: hwId,
      student_id: studentId,
      answers: answers,
      image_url: $('#hs-oh-image').val()
    })
      .done(function (res) {
        if (res.success) {
          showMessage($msg, res.data.message, 'success');
          setTimeout(function () { location.reload(); }, 1200);
        } else {
          showMessage($msg, res.data.message || i18n.error, 'error');
          $btn.prop('disabled', false).text('ارسال پاسخ‌ها');
        }
      })
      .fail(function () {
        showMessage($msg, i18n.error, 'error');
        $btn.prop('disabled', false).text('ارسال پاسخ‌ها');
      });
  });


  // =====================================================
  // استودیو محتوا
  // =====================================================

  const studioMedia = [];
  let studioProjectId = 0;
  let studioSourceType = '';

  function studioCollect() {
    return {
      project_id: studioProjectId,
      title: $('#hs-studio-title').val(),
      extracted_text: $('#hs-studio-text').val(),
      html_content: $('#hs-studio-html').val(),
      source_type: studioSourceType,
      meta: JSON.stringify({
        grade: $('#hs-studio-grade').val(),
        subject: $('#hs-studio-subject').val(),
        topic: $('#hs-studio-topic').val(),
        publish_as: $('#hs-studio-publish-as').val()
      }),
      media: JSON.stringify(studioMedia)
    };
  }

  function studioRenderMedia() {
    const $list = $('#hs-studio-media-list').empty();
    studioMedia.forEach(function (m, i) {
      const isImg = /\.(jpe?g|png|gif|webp)$/i.test(m.url);
      let thumb = isImg
        ? '<img src="' + m.url + '" alt="">'
        : '<span style="font-size:1.5rem">' + (m.ext === 'pdf' || (m.name || '').match(/\.pdf$/i) ? '📄' : '📎') + '</span>';
      $list.append(
        $('<li data-i="' + i + '">' + thumb +
          '<span>' + (m.name || 'فایل') + '</span>' +
          '<button type="button" class="hs-btn-remove hs-studio-rm-media" data-i="' + i + '">×</button></li>')
      );
    });
  }

  function studioUploadFile(file, isMediaOnly) {
    const $msg = isMediaOnly ? $('#hs-studio-msg') : $('#hs-studio-upload-msg');
    const fd = new FormData();
    fd.append('action', 'hs_studio_upload');
    fd.append('nonce', hsClass.nonce);
    fd.append('file', file);

    $('#hs-studio-upload-progress').show();
    $('#hs-studio-bar').css('width', '30%');

    return $.ajax({
      url: hsClass.ajaxUrl,
      method: 'POST',
      data: fd,
      processData: false,
      contentType: false
    })
      .done(function (res) {
        $('#hs-studio-bar').css('width', '100%');
        setTimeout(function () {
          $('#hs-studio-upload-progress').hide();
          $('#hs-studio-bar').css('width', '0%');
        }, 400);

        if (!res.success) {
          showMessage($msg, res.data.message || i18n.error, 'error');
          return;
        }
        const d = res.data;

        if (d.is_media || isMediaOnly) {
          studioMedia.push({
            url: d.url,
            type: d.type,
            name: d.name,
            attach_id: d.attach_id,
            ext: d.ext
          });
          studioRenderMedia();
          showMessage($msg, 'فایل رسانه اضافه شد.', 'success');
          return;
        }

        // سند منبع
        studioSourceType = d.ext;
        studioMedia.push({
          url: d.url,
          type: d.type,
          name: d.name,
          attach_id: d.attach_id,
          ext: d.ext
        });
        studioRenderMedia();

        if (d.extract_error) {
          showMessage($msg, 'آپلود شد ولی استخراج متن: ' + d.extract_error, 'error');
        } else {
          showMessage($msg, 'آپلود و استخراج انجام شد.', 'success');
        }
        if (d.text) {
          $('#hs-studio-text').val(d.text);
        }
        if (d.html) {
          $('#hs-studio-html').val(d.html);
        }
        if (!$('#hs-studio-title').val() && d.name) {
          $('#hs-studio-title').val(d.name.replace(/\.[^.]+$/, ''));
        }
      })
      .fail(function () {
        $('#hs-studio-upload-progress').hide();
        showMessage($msg, i18n.error, 'error');
      });
  }

  // دراپ‌زون
  $(document).on('click', '#hs-studio-drop', function () {
    $('#hs-studio-file').trigger('click');
  });
  $(document).on('change', '#hs-studio-file', function () {
    if (this.files && this.files[0]) {
      studioUploadFile(this.files[0], false);
      this.value = '';
    }
  });
  $(document).on('dragover', '#hs-studio-drop', function (e) {
    e.preventDefault();
    $(this).addClass('dragover');
  });
  $(document).on('dragleave drop', '#hs-studio-drop', function (e) {
    e.preventDefault();
    $(this).removeClass('dragover');
    if (e.type === 'drop' && e.originalEvent.dataTransfer.files.length) {
      studioUploadFile(e.originalEvent.dataTransfer.files[0], false);
    }
  });

  $(document).on('click', '#hs-studio-add-media', function () {
    $('#hs-studio-media-file').trigger('click');
  });
  $(document).on('change', '#hs-studio-media-file', function () {
    if (this.files && this.files[0]) {
      studioUploadFile(this.files[0], true);
      this.value = '';
    }
  });
  $(document).on('click', '.hs-studio-rm-media', function () {
    const i = parseInt($(this).data('i'), 10);
    studioMedia.splice(i, 1);
    studioRenderMedia();
  });

  $(document).on('click', '#hs-studio-to-html', function () {
    // ساده: هر پاراگراف خالی‌جدا به p
    const text = $('#hs-studio-text').val().trim();
    if (!text) return;
    const parts = text.split(/\n{2,}/);
    let html = '';
    parts.forEach(function (p) {
      p = p.trim();
      if (!p) return;
      const lines = p.split('\n').map(function (l) {
        return $('<div>').text(l).html();
      });
      html += '<p>' + lines.join('<br>') + '</p>\n';
    });
    $('#hs-studio-html').val(html);
  });

  $(document).on('click', '#hs-studio-save', function () {
    const $msg = $('#hs-studio-msg');
    const data = studioCollect();
    if (!data.title) {
      showMessage($msg, 'عنوان را وارد کنید', 'error');
      return;
    }
    ajax('hs_studio_save', data)
      .done(function (res) {
        if (res.success) {
          studioProjectId = res.data.project_id;
          showMessage($msg, res.data.message, 'success');
        } else {
          showMessage($msg, res.data.message || i18n.error, 'error');
        }
      });
  });

  $(document).on('click', '#hs-studio-publish', function () {
    const $msg = $('#hs-studio-msg');
    const $btn = $(this);
    const data = studioCollect();
    if (!data.title) {
      showMessage($msg, 'عنوان صفحه الزامی است', 'error');
      return;
    }
    $btn.prop('disabled', true).text('در حال انتشار...');
    ajax('hs_studio_publish', data)
      .done(function (res) {
        if (res.success) {
          studioProjectId = res.data.project_id;
          showMessage($msg, res.data.message, 'success');
          $('#hs-studio-result').show();
          $('#hs-studio-view-link').attr('href', res.data.url);
        } else {
          showMessage($msg, res.data.message || i18n.error, 'error');
        }
      })
      .fail(function () {
        showMessage($msg, i18n.error, 'error');
      })
      .always(function () {
        $btn.prop('disabled', false).text('✓ پایان و انتشار');
      });
  });


  // دنیای دانش‌آموز (بازی‌ها و ارسال عکس) ← افزونه‌ی «پنل دانش‌آموز» (assets/student.js)


  // پنل والدین و گروه والدین ← افزونه‌ی جداگانه‌ی «پنل والدین» (assets/parent.js)

  // =====================================================
  // تقویم مناسبتی + اتصال بله
  // =====================================================

  $(document).on('submit', '#hs-cal-form', function (e) {
    e.preventDefault();
    const $msg = $('#hs-cal-msg');
    ajax('hs_cal_save', {
      title: $('#hs-cal-title').val(),
      event_date: $('#hs-cal-date').val(),
      event_type: $('#hs-cal-type').val(),
      audience: $('#hs-cal-audience').val(),
      class_id: $('#hs-cal-class').val(),
      remind_days: $('#hs-cal-remind').val(),
      description: $('#hs-cal-desc').val()
    }).done(function (res) {
      if (res.success) {
        showMessage($msg, res.data.message, 'success');
        setTimeout(function () { location.reload(); }, 700);
      } else {
        showMessage($msg, res.data.message || i18n.error, 'error');
      }
    });
  });

  $(document).on('click', '.hs-cal-del', function () {
    if (!confirm('حذف شود؟')) return;
    const id = $(this).data('id');
    ajax('hs_cal_delete', { id: id }).done(function (res) {
      if (res.success) location.reload();
      else alert(res.data.message || i18n.error);
    });
  });

  $(document).on('click', '#hs-bale-refresh', function () {
    ajax('hs_bale_new_code', {}).done(function (res) {
      if (res.success) {
        $('.hs-bale-code').text(res.data.code);
      }
    });
  });

  $(document).on('click', '#hs-bale-unlink', function () {
    ajax('hs_bale_unlink', {}).done(function (res) {
      if (res.success) location.reload();
    });
  });

})(jQuery);

/* ترتیب نمایش دانش‌آموزان (حضور و غیاب) */
(function ($) {
  var $bar = $('.hs-att-sortbar');
  if (!$bar.length) return;
  var cid = $bar.data('class-id'), $list = $('.hs-att-list'), timer;
  function save(mode, reload) {
    var data = { action: 'hs_save_student_order', nonce: hsClass.nonce, class_id: cid, mode: mode };
    if (mode === 'custom') data.order = $list.find('.hs-att-row').map(function () { return $(this).data('student-id'); }).get();
    return $.post(hsClass.ajaxUrl || hsClass.ajax_url || ajaxurl, data).done(function () { if (reload) location.reload(); });
  }
  function saveSoon() { clearTimeout(timer); timer = setTimeout(function () { save('custom'); }, 500); }
  $bar.on('change', 'input[name=hs_sort]', function () {
    $bar.find('.hs-seg label').removeClass('active'); $(this).closest('label').addClass('active');
    var m = this.value; $list.toggleClass('is-sorting', m === 'custom'); $bar.find('.hs-att-sort-hint').prop('hidden', m !== 'custom');
    save(m, m !== 'custom'); // نام/نام خانوادگی: صفحه با ترتیب جدید بارگذاری می‌شود
  });
  $list.on('click', '[data-move]', function () {
    var $r = $(this).closest('.hs-att-row'), d = +$(this).data('move');
    if (d < 0 && $r.prev('.hs-att-row').length) $r.insertBefore($r.prev('.hs-att-row'));
    if (d > 0 && $r.next('.hs-att-row').length) $r.insertAfter($r.next('.hs-att-row'));
    $(this).focus(); saveSoon();
  });
  var drag = null;
  $list.on('dragstart', '.hs-att-handle', function (e) { drag = $(this).closest('.hs-att-row').addClass('is-dragging')[0]; e.originalEvent.dataTransfer.effectAllowed = 'move'; try { e.originalEvent.dataTransfer.setData('text/plain', ''); } catch (x) {} e.originalEvent.dataTransfer.setDragImage(drag, 20, 20); });
  $list.on('dragover', '.hs-att-row', function (e) { if (!drag) return; e.preventDefault(); $list.find('.is-over').removeClass('is-over'); if (this !== drag) $(this).addClass('is-over'); });
  $list.on('drop', '.hs-att-row', function (e) { if (!drag) return; e.preventDefault(); if (this !== drag) $(drag).insertBefore(this); saveSoon(); });
  $list.on('dragend', '.hs-att-row', function () { $list.find('.is-over').removeClass('is-over'); $(drag).removeClass('is-dragging'); drag = null; });
})(jQuery);

/* تقویم شمسی برای انتخاب تاریخ (مقدار میلادی در ورودی پنهان ذخیره می‌شود) */
(function ($) {
  function div(a, b) { return ~~(a / b); }
  function g2j(gy, gm, gd) {
    var g = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334], gy2 = gm > 2 ? gy + 1 : gy;
    var d = 355666 + 365 * gy + div(gy2 + 3, 4) - div(gy2 + 99, 100) + div(gy2 + 399, 400) + gd + g[gm - 1];
    var jy = -1595 + 33 * div(d, 12053); d %= 12053; jy += 4 * div(d, 1461); d %= 1461;
    if (d > 365) { jy += div(d - 1, 365); d = (d - 1) % 365; }
    var jm = d < 186 ? 1 + div(d, 31) : 7 + div(d - 186, 30), jd = 1 + (d < 186 ? d % 31 : (d - 186) % 30);
    return [jy, jm, jd];
  }
  function j2g(jy, jm, jd) {
    jy += 1595; var d = -355668 + 365 * jy + div(jy, 33) * 8 + div(jy % 33 + 3, 4) + jd + (jm < 7 ? (jm - 1) * 31 : (jm - 7) * 30 + 186);
    var gy = 400 * div(d, 146097); d %= 146097;
    if (d > 36524) { gy += 100 * div(--d, 36524); d %= 36524; if (d >= 365) d++; }
    gy += 4 * div(d, 1461); d %= 1461; if (d > 365) { gy += div(d - 1, 365); d = (d - 1) % 365; }
    var gd = d + 1, sal = [0, 31, (gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0 ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31], gm = 0;
    for (gm = 0; gm < 13 && gd > sal[gm]; gm++) gd -= sal[gm];
    return [gy, gm, gd];
  }
  function jlen(jy, jm) { if (jm <= 6) return 31; if (jm <= 11) return 30; var a = j2g(jy + 1, 1, 1), b = j2g(jy, 12, 30); return (a[0] === b[0] && a[1] === b[1] && a[2] === b[2]) ? 29 : 30; }
  var fa = function (n) { return String(n).replace(/\d/g, function (c) { return '۰۱۲۳۴۵۶۷۸۹'[c]; }); };
  var months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
  var pad = function (n) { return (n < 10 ? '0' : '') + n; };
  $('.hs-jdate').each(function () {
    var $w = $(this), $t = $($w.data('target')), $d = $w.find('.hs-jd-d'), $m = $w.find('.hs-jd-m'), $y = $w.find('.hs-jd-y');
    function parse() { var v = ($t.val() || '').split('-'), now = new Date(); return v.length === 3 ? [+v[0], +v[1], +v[2]] : [now.getFullYear(), now.getMonth() + 1, now.getDate()]; }
    function fill(j) {
      $y.empty(); for (var y = j[0] - 2; y <= j[0] + 1; y++) $y.append(new Option(fa(y), y));
      $m.empty(); months.forEach(function (n, i) { $m.append(new Option(n, i + 1)); });
      $d.empty(); for (var d = 1; d <= jlen(j[0], j[1]); d++) $d.append(new Option(fa(d), d));
      $y.val(j[0]); $m.val(j[1]); $d.val(Math.min(j[2], jlen(j[0], j[1])));
    }
    function setG(g, trigger) { $t.val(g[0] + '-' + pad(g[1]) + '-' + pad(g[2])); fill(g2j(g[0], g[1], g[2])); if (trigger) $('#hs-att-load').trigger('click'); }
    function fromSel() { var jy = +$y.val(), jm = +$m.val(), jd = Math.min(+$d.val(), jlen(jy, jm)); setG(j2g(jy, jm, jd), true); }
    $w.on('change', 'select', fromSel);
    $w.on('click', '.hs-jd-step', function () { var g = parse(), dt = new Date(g[0], g[1] - 1, g[2] + (+$(this).data('step'))); setG([dt.getFullYear(), dt.getMonth() + 1, dt.getDate()], true); });
    $w.on('click', '.hs-jd-today', function () { var n = new Date(); setG([n.getFullYear(), n.getMonth() + 1, n.getDate()], true); });
    var g = parse(); fill(g2j(g[0], g[1], g[2]));
  });
  window.hsJalali = { g2j: g2j, j2g: j2g };
})(jQuery);

/* پروفایل سریع دانش‌آموز: زدن روی نام → امتیاز، یادداشت، عکس، سابقه */
(function ($) {
  var $m = $('#hs-qa-modal'); if (!$m.length) return;
  var cid = $m.data('class-id'), sid = 0, last = null, url = hsClass.ajaxUrl;
  var fa = function (n) { return String(n).replace(/-/g, '−').replace(/\d/g, function (c) { return '۰۱۲۳۴۵۶۷۸۹'[c]; }); };
  function msg(t, err) { $m.find('.hs-qa-msg').text(t || '').toggleClass('err', !!err); }
  function photo(u, name) {
    var $p = $m.find('.hs-qa-photo-img').empty();
    if (u) $p.append($('<img>', { src: u, alt: '' })); else $p.text((name || '').charAt(0));
  }
  function setTotal(t) {
    $('.hs-qa-pts[data-student-id="' + sid + '"], .hs-pts-st-score[data-student-id="' + sid + '"]').text(fa(t)).filter('.hs-qa-pts').toggleClass('neg', t < 0);
    $m.find('.hs-qa-total b').text(fa(t));
  }
  function history() {
    var $ul = $m.find('.hs-qa-hist ul').html('<li class="hs-muted">در حال بارگذاری…</li>');
    $.post(url, { action: 'hs_get_student_points', nonce: hsClass.nonce, class_id: cid, student_id: sid }).done(function (r) {
      if (!r || !r.success) { $ul.html('<li class="hs-muted">بارگذاری نشد</li>'); return; }
      setTotal(r.data.total); $ul.empty();
      if (!r.data.history.length) $ul.append('<li class="hs-muted">هنوز امتیازی ثبت نشده</li>');
      r.data.history.slice(0, 15).forEach(function (h) {
        var d = (h.date || '').slice(0, 10).split('-'), jd = d.length === 3 && window.hsJalali ? window.hsJalali.g2j(+d[0], +d[1], +d[2]) : null;
        $('<li>').toggleClass('neg', h.points < 0)
          .append($('<b>').text((h.points > 0 ? '+' : '') + fa(h.points)), ' ', $('<span>').text(h.behavior), h.note ? $('<em>').text(' — ' + h.note) : '', $('<small>').text(jd ? fa(jd[2] + '/' + jd[1]) : ''))
          .appendTo($ul);
      });
    });
  }
  $(document).on('click', '.hs-qa-open', function () {
    last = this; sid = $(this).data('student-id');
    $m.find('#hs-qa-title').text($(this).data('name'));
    photo($(this).data('photo'), $(this).data('name'));
    $m.find('.hs-qa-total b').text($(this).find('.hs-qa-pts').text());
    msg(''); $m.find('textarea').val(''); $m.find('.hs-qa-hist').prop('open', false);
    $m.prop('hidden', false); document.body.style.overflow = 'hidden';
    $m.find('.hs-qa-skill').first().focus();
    history();
  });
  function close() { $m.prop('hidden', true); document.body.style.overflow = ''; if (last) last.focus(); }
  $m.on('click', '.hs-qa-close', close);
  $m.on('click', function (e) { if (e.target === this) close(); });
  $(document).on('keydown', function (e) { if (e.key === 'Escape' && !$m.prop('hidden')) close(); });
  $m.on('click', '.hs-qa-skill', function () {
    var $b = $(this), note = $m.find('textarea').val();
    $b.prop('disabled', true);
    $.post(url, { action: 'hs_award_points', nonce: hsClass.nonce, class_id: cid, behavior_id: $b.data('behavior'), student_ids: [sid], note: note })
      .done(function (r) {
        if (r && r.success) {
          $b.removeClass('is-done'); void $b[0].offsetWidth; $b.addClass('is-done');
          setTotal(r.data.totals[sid]); msg('✓ ' + r.data.behavior.icon + ' ' + r.data.behavior.name + ' ثبت شد');
          $m.find('textarea').val(''); history();
        } else msg((r && r.data && r.data.message) || 'خطا در ثبت', true);
      })
      .fail(function () { msg('ارتباط برقرار نشد', true); })
      .always(function () { $b.prop('disabled', false); });
  });
  // عکس دانش‌آموز
  $m.on('change', '.hs-qa-photo-file', function () {
    var f = this.files && this.files[0], input = this; if (!f) return;
    if (!/^image\//.test(f.type)) { msg('فقط فایل عکس مجاز است', true); return; }
    var fd = new FormData(); fd.append('action', 'hs_upload_image'); fd.append('nonce', hsClass.nonce); fd.append('purpose', 'student_photo'); fd.append('file', f);
    msg('در حال آپلود عکس…');
    $.ajax({ url: url, type: 'POST', data: fd, processData: false, contentType: false }).done(function (r) {
      if (!r || !r.success) { msg((r && r.data && r.data.message) || 'آپلود ناموفق بود', true); return; }
      var u = r.data.url;
      $.post(url, { action: 'hs_update_student_profile', nonce: hsClass.nonce, class_id: cid, user_id: sid, photo_url: u }).done(function (r2) {
        if (r2 && r2.success) {
          photo(u); msg('✓ عکس ذخیره شد');
          $('[data-avatar="' + sid + '"]').addClass('has-photo').empty().append($('<img>', { src: u, alt: '' }));
          $('.hs-qa-open[data-student-id="' + sid + '"]').data('photo', u).attr('data-photo', u);
        } else msg((r2 && r2.data && r2.data.message) || 'ذخیره‌ی عکس ناموفق بود', true);
      });
    }).fail(function () { msg('آپلود ناموفق بود', true); }).always(function () { input.value = ''; });
  });
})(jQuery);

/* نوار هفته (شنبه تا پنجشنبه) به‌جای انتخاب تاریخ کرکره‌ای */
(function ($) {
  var $w = $('.hs-week'); if (!$w.length || !window.hsJalali) return;
  var J = window.hsJalali, $t = $($w.data('target')), $days = $w.find('.hs-week-days'), $label = $('.hs-week-label');
  var names = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه'];
  var months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
  var fa = function (n) { return String(n).replace(/\d/g, function (c) { return '۰۱۲۳۴۵۶۷۸۹'[c]; }); };
  var pad = function (n) { return (n < 10 ? '0' : '') + n; };
  var iso = function (d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); };
  function parse(v) { var p = (v || '').split('-'); return p.length === 3 ? new Date(+p[0], p[1] - 1, +p[2]) : new Date(); }
  function satOf(d) { var x = new Date(d); x.setDate(x.getDate() - ((x.getDay() + 1) % 7)); return x; } // شنبه‌ی همان هفته
  var sel = parse($t.val()), start = satOf(sel), today = iso(new Date());
  function draw() {
    $days.empty();
    for (var i = 0; i < 6; i++) {
      var d = new Date(start); d.setDate(start.getDate() + i);
      var j = J.g2j(d.getFullYear(), d.getMonth() + 1, d.getDate()), v = iso(d);
      $('<button type="button" role="option" class="hs-day">')
        .attr({ 'data-date': v, 'aria-selected': v === iso(sel) ? 'true' : 'false', title: names[i] + ' ' + fa(j[2]) + ' ' + months[j[1] - 1] })
        .toggleClass('is-on', v === iso(sel)).toggleClass('is-today', v === today)
        .append($('<small>').text(names[i]), $('<b>').text(fa(j[2])))
        .appendTo($days);
    }
    var a = J.g2j(start.getFullYear(), start.getMonth() + 1, start.getDate()), e = new Date(start); e.setDate(start.getDate() + 5);
    var b = J.g2j(e.getFullYear(), e.getMonth() + 1, e.getDate());
    $label.text(a[1] === b[1] ? months[a[1] - 1] + ' ' + fa(a[0]) : months[a[1] - 1] + ' – ' + months[b[1] - 1] + ' ' + fa(b[0]));
  }
  function go(v) { $t.val(v); $('#hs-att-load').trigger('click'); }
  $days.on('click', '.hs-day', function () { sel = parse($(this).data('date')); draw(); go(iso(sel)); });
  $w.on('click', '.hs-week-nav', function () { start.setDate(start.getDate() + 7 * $(this).data('week')); draw(); });
  $w.on('click', '.hs-week-today', function () { sel = new Date(); start = satOf(sel); draw(); go(iso(sel)); });
  $('#hs-att-class').on('change', function () { $('#hs-att-load').trigger('click'); });
  draw();
})(jQuery);

/* کیوآر کد کلاس برای اشتراک‌گذاری با والدین */
(function ($) {
  if (typeof qrcode === 'undefined') return;
  function png(url, px) {
    var q = qrcode(0, 'M'); q.addData(url); q.make();
    var n = q.getModuleCount(), cell = Math.floor(px / (n + 8)), size = cell * (n + 8), c = document.createElement('canvas'), x = c.getContext('2d');
    c.width = c.height = size; x.fillStyle = '#fff'; x.fillRect(0, 0, size, size); x.fillStyle = '#0F2F6B';
    for (var r = 0; r < n; r++) for (var k = 0; k < n; k++) if (q.isDark(r, k)) x.fillRect((k + 4) * cell, (r + 4) * cell, cell, cell);
    return c;
  }
  $(document).on('click', '.hs-qr-open', function () {
    var url = $(this).data('url'), title = $(this).data('title'), code = $(this).data('code');
    var c = png(url, 520), data = c.toDataURL('image/png');
    var $m = $('<div class="hs-qr-modal" role="dialog" aria-modal="true" aria-label="کیوآر کد کلاس">').html(
      '<div class="hs-qr-box"><button type="button" class="hs-qr-x" aria-label="بستن">×</button>' +
      '<h3></h3><p class="hs-muted">والدین با دوربین گوشی این کد را اسکن کنند تا مستقیم به صفحه‌ی ورود و اتصال به همین کلاس بروند.</p>' +
      '<img alt="کیوآر کد کلاس"><div class="hs-qr-code">کد دعوت: <b dir="ltr"></b></div>' +
      '<div class="hs-qr-actions"><a class="hs-btn hs-btn-primary hs-btn-sm" download>⬇ دانلود عکس</a><button type="button" class="hs-btn hs-btn-ghost hs-btn-sm hs-qr-share">اشتراک‌گذاری</button><button type="button" class="hs-btn hs-btn-ghost hs-btn-sm hs-qr-copy">کپی لینک</button></div></div>');
    $m.find('h3').text(title); $m.find('img').attr('src', data); $m.find('.hs-qr-code b').text(code);
    $m.find('a[download]').attr({ href: data, download: 'qr-' + code + '.png' });
    $m.appendTo('body').find('.hs-qr-x').focus();
    var close = function () { $m.remove(); $(document).off('keydown.hsqr'); };
    $m.on('click', function (e) { if (e.target === this) close(); }).on('click', '.hs-qr-x', close);
    $(document).on('keydown.hsqr', function (e) { if (e.key === 'Escape') close(); });
    $m.on('click', '.hs-qr-copy', function () { var b = this; (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject()).then(function () { b.textContent = '✓ کپی شد'; }, function () { prompt('لینک کلاس:', url); }); });
    $m.on('click', '.hs-qr-share', function () {
      var txt = 'برای پیوستن به کلاس «' + title + '» در همشاگردی: ' + url;
      if (navigator.share) { c.toBlob(function (b) { var f = new File([b], 'qr-' + code + '.png', { type: 'image/png' }); var d = { title: title, text: txt, url: url }; if (navigator.canShare && navigator.canShare({ files: [f] })) d.files = [f]; navigator.share(d).catch(function () {}); }); }
      else { (navigator.clipboard ? navigator.clipboard.writeText(txt) : Promise.reject()).then(function () { alert('متن و لینک دعوت کپی شد؛ در پیام‌رسان بچسبانید.'); }, function () { prompt('متن دعوت:', txt); }); }
    });
  });
})(jQuery);
