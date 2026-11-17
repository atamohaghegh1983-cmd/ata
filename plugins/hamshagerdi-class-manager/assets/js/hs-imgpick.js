/* انتخاب عکس از کامپیوتر/موبایل (گالری، دوربین یا فایل‌ها) برای فیلدهای «لینک عکس» */
(function ($) {
  'use strict';
  if (typeof hsImg === 'undefined') return;

  function sync($box) {
    var url = ($box.find('.hs-imgpick-val').val() || '').trim();
    var $prev = $box.find('.hs-imgpick-prev');
    if (url && /^https?:\/\//i.test(url)) {
      $prev.addClass('has').empty().append($('<img>', { src: url, alt: '' }));
      $box.find('.hs-imgpick-state').text('عکس انتخاب شد');
      $box.find('.hs-imgpick-clear').prop('hidden', false);
    } else {
      $prev.removeClass('has').text('🖼️');
      $box.find('.hs-imgpick-state').text('عکسی انتخاب نشده');
      $box.find('.hs-imgpick-clear').prop('hidden', true);
    }
  }

  $(document).on('click', '.hs-imgpick-btn', function () {
    $(this).closest('.hs-imgpick').find('.hs-imgpick-file').trigger('click');
  });
  $(document).on('click', '.hs-imgpick-linkbtn', function () {
    var $v = $(this).closest('.hs-imgpick').find('.hs-imgpick-val');
    $v.prop('hidden', !$v.prop('hidden'));
    if (!$v.prop('hidden')) $v.trigger('focus');
  });
  $(document).on('click', '.hs-imgpick-clear', function () {
    var $box = $(this).closest('.hs-imgpick');
    $box.find('.hs-imgpick-val').val('');
    $box.find('.hs-imgpick-file').val('');
    sync($box);
  });
  $(document).on('input change', '.hs-imgpick-val', function () {
    sync($(this).closest('.hs-imgpick'));
  });

  $(document).on('change', '.hs-imgpick-file', function () {
    var $box = $(this).closest('.hs-imgpick');
    var file = this.files && this.files[0];
    if (!file) return;
    if (!/^image\//.test(file.type)) {
      $box.find('.hs-imgpick-state').text('فقط فایل عکس مجاز است');
      return;
    }
    var fd = new FormData();
    fd.append('action', 'hs_upload_image');
    fd.append('nonce', hsImg.nonce);
    fd.append('purpose', $box.data('purpose') || 'general');
    fd.append('file', file);
    var $btn = $box.find('.hs-imgpick-btn').prop('disabled', true);
    $box.find('.hs-imgpick-state').text('در حال آپلود…');
    $.ajax({ url: hsImg.ajaxUrl, type: 'POST', data: fd, processData: false, contentType: false })
      .done(function (res) {
        if (res && res.success) {
          $box.find('.hs-imgpick-val').val(res.data.url);
          sync($box);
        } else {
          $box.find('.hs-imgpick-state').text((res && res.data && res.data.message) || 'آپلود ناموفق بود');
        }
      })
      .fail(function () { $box.find('.hs-imgpick-state').text('آپلود ناموفق بود'); })
      .always(function () { $btn.prop('disabled', false); $box.find('.hs-imgpick-file').val(''); });
  });

  window.hsImgPickSync = function (scope) {
    $(scope || document).find('.hs-imgpick').each(function () { sync($(this)); });
  };
})(jQuery);
