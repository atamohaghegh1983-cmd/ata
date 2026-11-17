(function ($) {
  'use strict';

  // سایدبار موبایل
  var $shell = $('#hs-shell');
  $('#hs-burger').on('click', function () { $shell.toggleClass('nav-open'); });
  $('#hs-overlay').on('click', function () { $shell.removeClass('nav-open'); });

  // جستجو در منو
  $('#hs-menu-search').on('input', function () {
    var q = $.trim($(this).val());
    $('#hs-nav .hs-nav-item').each(function () {
      var label = $(this).data('label') || '';
      $(this).toggle(!q || label.indexOf(q) !== -1);
    });
    $('#hs-nav .hs-nav-title').toggle(!q);
  }).on('keydown', function (e) {
    if (e.key === 'Enter') {
      var $first = $('#hs-nav .hs-nav-item:visible').first();
      if ($first.length) { window.location.href = $first.attr('href'); }
    }
  });

  // منوی کاربر
  $('#hs-user-btn').on('click', function (e) { e.stopPropagation(); $('#hs-user').toggleClass('open'); });
  $(document).on('click', function () { $('#hs-user').removeClass('open'); });

  // تغییر رمز
  var $modal = $('#hs-pass-modal');
  $('#hs-open-pass, #hs-open-pass-2').on('click', function () {
    $('#hs-user').removeClass('open');
    $('#hs-pass-msg').text('').removeClass('success error');
    $modal.prop('hidden', false);
    $('#hs-pass-new').trigger('focus');
  });
  $('#hs-pass-cancel').on('click', function () { $modal.prop('hidden', true); });
  $modal.on('click', function (e) { if (e.target === this) { $modal.prop('hidden', true); } });

  $('#hs-pass-save').on('click', function () {
    var $msg = $('#hs-pass-msg');
    var $btn = $(this).prop('disabled', true);
    $.post(hsShell.ajaxUrl, {
      action: 'hs_change_password',
      nonce: hsShell.nonce,
      pass: $('#hs-pass-new').val(),
      confirm: $('#hs-pass-confirm').val()
    }).done(function (res) {
      if (res && res.success) {
        $msg.removeClass('error').addClass('success').text(res.data.message);
        setTimeout(function () { location.reload(); }, 900);
      } else {
        $msg.removeClass('success').addClass('error').text((res && res.data && res.data.message) || 'خطا');
      }
    }).fail(function () {
      $msg.removeClass('success').addClass('error').text('خطای ارتباط');
    }).always(function () { $btn.prop('disabled', false); });
  });

  // تب‌ها (نمودار حضور) — فقط روی پنل‌های مستقیم همان گروه اثر می‌گذارد
  $(document).on('click', '[data-hs-tabs] > .hs-tab-list > [data-tab]', function () {
    var $root = $(this).closest('[data-hs-tabs]'), k = $(this).data('tab');
    $root.children('.hs-tab-list').children('[data-tab]').removeClass('is-active').attr('aria-selected', 'false');
    $(this).addClass('is-active').attr('aria-selected', 'true');
    $root.children('.hs-tab-panel').removeClass('is-active').filter('[data-panel="' + k + '"]').addClass('is-active');
  });

  // تب کلاس‌ها (داشبورد معلم با چند کلاس)
  $(document).on('click', '[data-hs-ctabs] > .hs-class-tabs > [data-ctab]', function () {
    var $root = $(this).closest('[data-hs-ctabs]'), k = $(this).data('ctab');
    $root.children('.hs-class-tabs').children('[data-ctab]').removeClass('is-active').attr('aria-selected', 'false');
    $(this).addClass('is-active').attr('aria-selected', 'true');
    $root.children('.hs-ctab-panel').removeClass('is-active').filter('[data-cpanel="' + k + '"]').addClass('is-active');
  });

  // عکس پروفایل
  var $am = $('#hs-avatar-modal'), $amsg = $('#hs-avatar-msg');
  function amsg(ok, t) { $amsg.removeClass('success error').addClass(ok ? 'success' : 'error').text(t); }
  $('#hs-open-avatar').on('click', function () { $('#hs-user').removeClass('open'); $amsg.text(''); $am.prop('hidden', false); });
  $('#hs-avatar-cancel').on('click', function () { $am.prop('hidden', true); });
  $am.on('click', function (e) { if (e.target === this) { $am.prop('hidden', true); } });
  $('#hs-avatar-file').on('change', function () {
    var f = this.files && this.files[0];
    if (f && /^image\//.test(f.type)) { $('#hs-avatar-img').attr('src', URL.createObjectURL(f)).removeAttr('srcset'); }
  });
  $('#hs-avatar-save').on('click', function () {
    var f = $('#hs-avatar-file')[0].files[0];
    if (!f) { amsg(false, 'ابتدا یک عکس انتخاب کنید.'); return; }
    var fd = new FormData();
    fd.append('action', 'hs_avatar_upload'); fd.append('nonce', hsShell.nonce); fd.append('avatar', f);
    var $b = $(this).prop('disabled', true);
    $.ajax({ url: hsShell.ajaxUrl, method: 'POST', data: fd, processData: false, contentType: false })
      .done(function (res) {
        if (res && res.success) { amsg(true, res.data.message); setTimeout(function () { location.reload(); }, 700); }
        else { amsg(false, (res && res.data && res.data.message) || 'خطا'); }
      })
      .fail(function (x) { amsg(false, (x.responseJSON && x.responseJSON.data && x.responseJSON.data.message) || 'خطای ارتباط'); })
      .always(function () { $b.prop('disabled', false); });
  });
  $('#hs-avatar-remove').on('click', function () {
    $.post(hsShell.ajaxUrl, { action: 'hs_avatar_remove', nonce: hsShell.nonce }).done(function (res) {
      if (res && res.success) { amsg(true, res.data.message); setTimeout(function () { location.reload(); }, 600); }
    });
  });
})(jQuery);
