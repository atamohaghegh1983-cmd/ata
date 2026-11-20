(function ($) {
  'use strict';

  if (typeof hsWsDash === 'undefined') return;

  function msg(text, ok) {
    var $m = $('#hs-ws-msg');
    $m.removeClass('ok err').addClass(ok ? 'ok' : 'err').text(text || '');
  }

  function ajax(action, data) {
    data = data || {};
    data.action = action;
    data.nonce = hsWsDash.nonce;
    return $.post(hsWsDash.ajaxUrl, data);
  }

  function resetForm() {
    $('#hs-ws-id').val(0);
    $('#hs-ws-title, #hs-ws-slug, #hs-ws-topic, #hs-ws-content').val('');
    $('#hs-ws-grade, #hs-ws-subject').val('');
    $('#hs-ws-type').val('mixed');
    $('#hs-ws-pdf-url, #hs-ws-image-url, #hs-ws-video-url, #hs-ws-file-url').val('');
    $('#hs-ws-pdf-id, #hs-ws-image-id, #hs-ws-video-id, #hs-ws-file-id').val(0);
    $('#hs-ws-prev-pdf, #hs-ws-prev-image, #hs-ws-prev-video, #hs-ws-prev-file').empty();
    $('#hs-ws-form-title').text('کاربرگ جدید');
    msg('', true);
  }

  function collect(status) {
    return {
      id: $('#hs-ws-id').val(),
      title: $('#hs-ws-title').val(),
      slug: $('#hs-ws-slug').val(),
      content: $('#hs-ws-content').val(),
      grade: $('#hs-ws-grade').val(),
      subject: $('#hs-ws-subject').val(),
      topic: $('#hs-ws-topic').val(),
      type: $('#hs-ws-type').val(),
      status: status || 'publish',
      pdf_url: $('#hs-ws-pdf-url').val(),
      pdf_id: $('#hs-ws-pdf-id').val(),
      image_url: $('#hs-ws-image-url').val(),
      image_id: $('#hs-ws-image-id').val(),
      video_url: $('#hs-ws-video-url').val(),
      video_id: $('#hs-ws-video-id').val(),
      file_url: $('#hs-ws-file-url').val(),
      file_id: $('#hs-ws-file-id').val()
    };
  }

  function save(status) {
    var data = collect(status);
    if (!$.trim(data.title)) {
      msg(hsWsDash.i18n.needTitle, false);
      return;
    }
    msg(hsWsDash.i18n.saving, true);
    ajax('hs_ws_dash_save', data).done(function (res) {
      if (res.success) {
        msg(res.data.message + (res.data.url ? ' — ' + res.data.url : ''), true);
        setTimeout(function () { location.reload(); }, 900);
      } else {
        msg((res.data && res.data.message) || hsWsDash.i18n.error, false);
      }
    }).fail(function () {
      msg(hsWsDash.i18n.error, false);
    });
  }

  function upload(file, kind) {
    var fd = new FormData();
    fd.append('action', 'hs_ws_dash_upload');
    fd.append('nonce', hsWsDash.nonce);
    fd.append('kind', kind);
    fd.append('file', file);

    msg('در حال آپلود...', true);
    return $.ajax({
      url: hsWsDash.ajaxUrl,
      method: 'POST',
      data: fd,
      processData: false,
      contentType: false
    });
  }

  function setPreview(kind, data) {
    var $prev = $('#hs-ws-prev-' + kind);
    if (kind === 'image' && data.url) {
      $prev.html('<img src="' + data.url + '" alt=""><div>' + (data.name || '') + '</div>');
    } else {
      $prev.html('<a href="' + data.url + '" target="_blank" rel="noopener">' + (data.name || data.url) + '</a>');
    }
    $('#hs-ws-' + kind + '-url').val(data.url || '');
    $('#hs-ws-' + kind + '-id').val(data.attach_id || 0);
  }

  // UI binds
  $(document).on('click', '.hs-ws-up-btn', function () {
    var id = $(this).data('target');
    $('#' + id).trigger('click');
  });

  $(document).on('change', '#hs-ws-file-pdf', function () {
    if (!this.files[0]) return;
    upload(this.files[0], 'pdf').done(function (res) {
      if (res.success) { setPreview('pdf', res.data); msg('PDF آپلود شد', true); }
      else msg((res.data && res.data.message) || hsWsDash.i18n.error, false);
    }).fail(function () { msg(hsWsDash.i18n.error, false); });
    this.value = '';
  });

  $(document).on('change', '#hs-ws-file-image', function () {
    if (!this.files[0]) return;
    upload(this.files[0], 'image').done(function (res) {
      if (res.success) { setPreview('image', res.data); msg('تصویر آپلود شد', true); }
      else msg((res.data && res.data.message) || hsWsDash.i18n.error, false);
    }).fail(function () { msg(hsWsDash.i18n.error, false); });
    this.value = '';
  });

  $(document).on('change', '#hs-ws-file-video', function () {
    if (!this.files[0]) return;
    upload(this.files[0], 'video').done(function (res) {
      if (res.success) { setPreview('video', res.data); msg('فیلم آپلود شد', true); }
      else msg((res.data && res.data.message) || hsWsDash.i18n.error, false);
    }).fail(function () { msg(hsWsDash.i18n.error, false); });
    this.value = '';
  });

  $(document).on('change', '#hs-ws-file-other', function () {
    if (!this.files[0]) return;
    upload(this.files[0], 'file').done(function (res) {
      if (res.success) { setPreview('file', res.data); msg('فایل آپلود شد', true); }
      else msg((res.data && res.data.message) || hsWsDash.i18n.error, false);
    }).fail(function () { msg(hsWsDash.i18n.error, false); });
    this.value = '';
  });

  $(document).on('click', '#hs-ws-publish', function () { save('publish'); });
  $(document).on('click', '#hs-ws-draft', function () { save('draft'); });
  $(document).on('click', '#hs-ws-reset, #hs-ws-new-btn', function () { resetForm(); });

  $(document).on('click', '.hs-ws-edit', function () {
    var id = $(this).data('id');
    ajax('hs_ws_dash_get', { id: id }).done(function (res) {
      if (!res.success) {
        msg((res.data && res.data.message) || hsWsDash.i18n.error, false);
        return;
      }
      var d = res.data;
      $('#hs-ws-id').val(d.id);
      $('#hs-ws-title').val(d.title);
      $('#hs-ws-slug').val(d.slug);
      $('#hs-ws-content').val(d.content);
      $('#hs-ws-grade').val(d.grade);
      $('#hs-ws-subject').val(d.subject);
      $('#hs-ws-topic').val(d.topic);
      $('#hs-ws-type').val(d.type || 'mixed');
      $('#hs-ws-pdf-url').val(d.pdf_url || '');
      $('#hs-ws-pdf-id').val(d.pdf_id || 0);
      $('#hs-ws-image-url').val(d.image_url || '');
      $('#hs-ws-image-id').val(d.image_id || 0);
      $('#hs-ws-video-url').val(d.video_url || '');
      $('#hs-ws-video-id').val(d.video_id || 0);
      $('#hs-ws-file-url').val(d.file_url || '');
      $('#hs-ws-file-id').val(d.file_id || 0);

      $('#hs-ws-prev-pdf').html(d.pdf_url ? '<a href="' + d.pdf_url + '" target="_blank">PDF</a>' : '');
      $('#hs-ws-prev-image').html(d.image_url ? '<img src="' + d.image_url + '" alt="">' : '');
      $('#hs-ws-prev-video').html(d.video_url ? '<a href="' + d.video_url + '" target="_blank">ویدیو</a>' : '');
      $('#hs-ws-prev-file').html(d.file_url ? '<a href="' + d.file_url + '" target="_blank">فایل</a>' : '');

      $('#hs-ws-form-title').text('ویرایش کاربرگ');
      $('html, body').animate({ scrollTop: $('#hs-ws-form-card').offset().top - 40 }, 300);
    });
  });

  $(document).on('click', '.hs-ws-del', function () {
    if (!confirm(hsWsDash.i18n.confirm)) return;
    var id = $(this).data('id');
    ajax('hs_ws_dash_delete', { id: id }).done(function (res) {
      if (res.success) location.reload();
      else msg((res.data && res.data.message) || hsWsDash.i18n.error, false);
    });
  });

})(jQuery);
