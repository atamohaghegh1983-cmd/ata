/* پنل والدین + گروه والدین کلاس */
(function ($) {
  'use strict';
  const cfg = window.hsParent || window.hsClass;
  if (!cfg) return;
  const i18n = (window.hsClass && window.hsClass.i18n) || { error: 'خطا؛ دوباره امتحان کنید.' };
  const hsClass = { nonce: cfg.nonce, ajaxUrl: cfg.ajaxUrl };

  function showMessage($el, text, type) {
    $el.removeClass('success error').addClass(type).text(text);
    if (type === 'success') setTimeout(function () { $el.text(''); }, 4000);
  }
  function ajax(action, data) {
    data = data || {};
    data.action = action;
    data.nonce = cfg.nonce;
    return $.post(cfg.ajaxUrl, data);
  }

  // =====================================================
  // پنل والدین
  // =====================================================

  $(document).on('submit', '#hs-pp-link-form', function (e) {
    e.preventDefault();
    const login = $('#hs-pp-child-login').val().trim();
    const pcode = ($('#hs-pp-child-code').val() || '').trim();
    const $msg = $('#hs-pp-link-msg');
    if (!login || !pcode) return;
    ajax('hs_parent_link_child', { student_login: login, parent_code: pcode })
      .done(function (res) {
        if (res.success) {
          showMessage($msg, res.data.message, 'success');
          setTimeout(function () { location.reload(); }, 800);
        } else {
          showMessage($msg, res.data.message || i18n.error, 'error');
        }
      });
  });

  $(document).on('click', '.hs-pp-unlink', function () {
    if (!confirm('اتصال این فرزند قطع شود؟')) return;
    const id = $(this).data('id');
    ajax('hs_parent_unlink_child', { student_id: id })
      .done(function (res) {
        if (res.success) location.reload();
        else alert(res.data.message || i18n.error);
      });
  });

  $(document).on('submit', '#hs-pp-msg-form', function (e) {
    e.preventDefault();
    const $msg = $('#hs-pp-msg-status');
    const body = $('#hs-pp-msg-body').val().trim();
    const media = $('#hs-pp-msg-image').val() || '';
    if (!body && !media) {
      showMessage($msg, 'متن پیام را بنویسید یا عکس انتخاب کنید', 'error');
      return;
    }
    ajax('hs_parent_send_msg', {
      teacher_id: $('#hs-pp-msg-teacher').val(),
      class_id: $('#hs-pp-msg-class').val(),
      child_id: $('#hs-pp-msg-child').val(),
      body: body,
      media_url: media
    })
      .done(function (res) {
        if (res.success) {
          showMessage($msg, res.data.message, 'success');
          $('#hs-pp-msg-body').val('');
          $('#hs-pp-msg-image').val('').trigger('change');
        } else {
          showMessage($msg, res.data.message || i18n.error, 'error');
        }
      });
  });


  // =====================================================
  // چت والدین — پیام‌رسان
  // =====================================================

  const PChat = {
    classId: 0,
    lastId: 0,
    replyTo: 0,
    pollTimer: null,
    mediaRecorder: null,
    recordChunks: [],
    recordStart: 0,
    recordTimer: null,

    boot: function () {
      const $root = $('#hs-pchat');
      if (!$root.length) return;
      this.classId = parseInt($root.data('class'), 10) || 0;
      this.fetch(true);
      this.pollTimer = setInterval(function () {
        PChat.fetch(false);
      }, 4000);

      // auto-resize textarea
      $('#hs-pchat-input').on('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 100) + 'px';
      });
    },

    fetch: function (scrollBottom) {
      ajax('hs_pchat_fetch', {
        class_id: this.classId,
        after_id: this.lastId
      }).done(function (res) {
        if (!res.success) return;
        (res.data.deleted || []).forEach(function (id) { $('#hs-pchat-msg-' + id).remove(); });
        if (res.data.state) HSPP.applyState(res.data.state);
        const msgs = res.data.messages || [];
        if (msgs.length) {
          const $box = $('#hs-pchat-messages');
          if (PChat.lastId === 0) {
            $box.empty();
          }
          msgs.forEach(function (m) {
            PChat.appendMsg(m);
            if (m.id > PChat.lastId) PChat.lastId = m.id;
          });
          if (scrollBottom || PChat.isNearBottom()) {
            PChat.scrollBottom();
          }
        } else if (PChat.lastId === 0) {
          $('#hs-pchat-messages').html(
            '<div class="hs-pchat-loading">هنوز پیامی نیست. اولین نفر باشید! 👋</div>'
          );
        }
      });
    },

    isNearBottom: function () {
      const el = document.getElementById('hs-pchat-messages');
      if (!el) return true;
      return el.scrollHeight - el.scrollTop - el.clientHeight < 80;
    },

    scrollBottom: function () {
      const el = document.getElementById('hs-pchat-messages');
      if (el) el.scrollTop = el.scrollHeight;
    },

    appendMsg: function (m) {
      // جلوگیری از تکراری
      if ($('#hs-pchat-msg-' + m.id).length) return;

      const mine = m.is_mine ? 'mine' : 'theirs';
      let replyHtml = '';
      if (m.reply) {
        replyHtml =
          '<div class="hs-pchat-reply-quote" data-goto="' + m.reply.id + '">' +
            '<strong>' + escHtml(m.reply.name) + '</strong>' +
            '<span>' + escHtml(m.reply.body || (m.reply.type === 'voice' ? '🎤 پیام صوتی' : 'پیام')) + '</span>' +
          '</div>';
      }

      let bodyHtml = '';
      if (m.msg_type === 'voice' && m.media_url) {
        bodyHtml =
          '<div class="hs-pchat-voice-player">' +
            '<audio controls preload="metadata" src="' + escAttr(m.media_url) + '"></audio>' +
          '</div>';
        if (m.body) bodyHtml += '<p class="hs-pchat-text">' + escHtml(m.body) + '</p>';
      } else if (m.msg_type === 'image' && m.media_url) {
        bodyHtml =
          '<a href="' + escAttr(m.media_url) + '" target="_blank" rel="noopener">' +
            '<img class="hs-pchat-img-msg" src="' + escAttr(m.media_url) + '" alt="">' +
          '</a>';
        if (m.body) bodyHtml += '<p class="hs-pchat-text">' + escHtml(m.body) + '</p>';
      } else {
        bodyHtml = '<p class="hs-pchat-text">' + escHtml(m.body) + '</p>';
      }

      const html =
        '<div class="hs-pchat-row ' + mine + '" id="hs-pchat-msg-' + m.id + '" data-id="' + m.id + '">' +
          '<div class="hs-pchat-av">' + escHtml(m.avatar) + '</div>' +
          '<div class="hs-pchat-bubble">' +
            (m.is_mine ? '' : '<div class="hs-pchat-sender">' + escHtml(m.name) + '</div>') +
            replyHtml +
            bodyHtml +
            '<div class="hs-pchat-meta">' +
              '<span class="hs-pchat-time">' + escHtml(m.time) + '</span>' +
              '<button type="button" class="hs-pchat-reply-btn" data-id="' + m.id + '" data-name="' + escAttr(m.name) + '" data-preview="' + escAttr((m.body || '').substring(0, 60) || (m.msg_type === 'voice' ? '🎤 صوت' : 'پیام')) + '">پاسخ</button>' +
              (m.is_mine ? '' : '<button type="button" class="hs-pchat-act" data-act="report" data-id="' + m.id + '" title="گزارش پیام نامناسب">⚑</button>') +
              (m.can_mod ? '<button type="button" class="hs-pchat-act" data-act="pin" data-id="' + m.id + '" title="سنجاق">📌</button>' : '') +
              (m.can_mod && !m.is_mine ? '<button type="button" class="hs-pchat-act" data-act="mute" data-id="' + m.sender_id + '" data-name="' + escAttr(m.name) + '" title="بی‌صدا">🔇</button>' : '') +
              (m.can_delete ? '<button type="button" class="hs-pchat-act" data-act="delete" data-id="' + m.id + '" title="حذف">🗑</button>' : '') +
            '</div>' +
          '</div>' +
        '</div>';

      const $box = $('#hs-pchat-messages');
      $box.find('.hs-pchat-loading').remove();
      $box.append(html);
    },

    sendText: function () {
      const body = $('#hs-pchat-input').val().trim();
      if (!body) return;
      const $btn = $('#hs-pchat-send').prop('disabled', true);
      ajax('hs_pchat_send', {
        class_id: this.classId,
        body: body,
        reply_to: this.replyTo,
        msg_type: 'text'
      }).done(function (res) {
        if (res.success) {
          $('#hs-pchat-input').val('').css('height', 'auto');
          PChat.clearReply();
          PChat.appendMsg(res.data.msg);
          if (res.data.msg.id > PChat.lastId) PChat.lastId = res.data.msg.id;
          PChat.scrollBottom();
        } else {
          alert(res.data.message || i18n.error);
        }
      }).always(function () {
        $btn.prop('disabled', false);
      });
    },

    setReply: function (id, name, preview) {
      this.replyTo = id;
      $('#hs-pchat-reply-name').text(name);
      $('#hs-pchat-reply-preview').text(preview);
      $('#hs-pchat-reply-bar').show();
      $('#hs-pchat-input').focus();
    },

    clearReply: function () {
      this.replyTo = 0;
      $('#hs-pchat-reply-bar').hide();
    },

    sendMedia: function (type, url, attachId, caption) {
      ajax('hs_pchat_send', {
        class_id: this.classId,
        body: caption || '',
        reply_to: this.replyTo,
        msg_type: type,
        media_url: url,
        media_id: attachId || 0
      }).done(function (res) {
        if (res.success) {
          PChat.clearReply();
          PChat.appendMsg(res.data.msg);
          if (res.data.msg.id > PChat.lastId) PChat.lastId = res.data.msg.id;
          PChat.scrollBottom();
        } else {
          alert(res.data.message || i18n.error);
        }
      });
    },

    uploadFile: function (file, action, kind) {
      const fd = new FormData();
      fd.append('action', action);
      fd.append('nonce', hsClass.nonce);
      fd.append('class_id', this.classId);
      fd.append('file', file);

      return $.ajax({
        url: hsClass.ajaxUrl,
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false
      });
    },

    startVoice: function () {
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        alert('مرورگر شما از ضبط صدا پشتیبانی نمی‌کند.');
        return;
      }
      navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
        PChat.recordChunks = [];
        const mime = MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm' : 'audio/ogg';
        PChat.mediaRecorder = new MediaRecorder(stream);
        PChat.mediaRecorder.ondataavailable = function (e) {
          if (e.data.size > 0) PChat.recordChunks.push(e.data);
        };
        PChat.mediaRecorder.onstop = function () {
          stream.getTracks().forEach(function (t) { t.stop(); });
        };
        PChat.mediaRecorder.start();
        PChat.recordStart = Date.now();
        $('#hs-pchat-voice-bar').show();
        $('#hs-pchat-voice-btn').addClass('recording');
        $('.hs-pchat-composer').hide();
        PChat.recordTimer = setInterval(function () {
          const sec = Math.floor((Date.now() - PChat.recordStart) / 1000);
          const m = Math.floor(sec / 60);
          const s = sec % 60;
          $('#hs-pchat-rec-time').text(m + ':' + (s < 10 ? '0' : '') + s);
        }, 250);
      }).catch(function () {
        alert('دسترسی به میکروفون داده نشد.');
      });
    },

    stopVoice: function (send) {
      clearInterval(PChat.recordTimer);
      $('#hs-pchat-voice-bar').hide();
      $('#hs-pchat-voice-btn').removeClass('recording');
      $('.hs-pchat-composer').show();
      $('#hs-pchat-rec-time').text('0:00');

      if (!PChat.mediaRecorder) return;

      const rec = PChat.mediaRecorder;
      rec.onstop = function () {
        if (rec.stream) {
          rec.stream.getTracks().forEach(function (t) { t.stop(); });
        }
        if (!send || !PChat.recordChunks.length) return;
        const blob = new Blob(PChat.recordChunks, { type: rec.mimeType || 'audio/webm' });
        const file = new File([blob], 'voice-' + Date.now() + '.webm', { type: blob.type });
        PChat.uploadFile(file, 'hs_pchat_upload_voice', 'voice')
          .done(function (res) {
            if (res.success) {
              PChat.sendMedia('voice', res.data.url, res.data.attach_id, '');
            } else {
              alert(res.data.message || i18n.error);
            }
          })
          .fail(function () {
            alert(i18n.error);
          });
      };
      if (rec.state !== 'inactive') {
        rec.stop();
      }
      PChat.mediaRecorder = null;
    }
  };

  function escHtml(s) {
    return $('<div>').text(s || '').html();
  }
  function escAttr(s) {
    return String(s || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  $(document).on('click', '#hs-pchat-send', function () {
    PChat.sendText();
  });
  $(document).on('keydown', '#hs-pchat-input', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      PChat.sendText();
    }
  });
  $(document).on('click', '.hs-pchat-reply-btn', function () {
    PChat.setReply(
      $(this).data('id'),
      $(this).data('name'),
      $(this).data('preview')
    );
  });
  $(document).on('click', '#hs-pchat-reply-cancel', function () {
    PChat.clearReply();
  });
  $(document).on('click', '#hs-pchat-img-btn', function () {
    $('#hs-pchat-img-input').trigger('click');
  });
  $(document).on('change', '#hs-pchat-img-input', function () {
    if (!this.files || !this.files[0]) return;
    const file = this.files[0];
    PChat.uploadFile(file, 'hs_pchat_upload_image', 'image')
      .done(function (res) {
        if (res.success) {
          PChat.sendMedia('image', res.data.url, res.data.attach_id, '');
        } else {
          alert(res.data.message || i18n.error);
        }
      });
    this.value = '';
  });
  $(document).on('click', '#hs-pchat-voice-btn', function () {
    PChat.startVoice();
  });
  $(document).on('click', '#hs-pchat-voice-cancel', function () {
    PChat.stopVoice(false);
  });
  $(document).on('click', '#hs-pchat-voice-send', function () {
    PChat.stopVoice(true);
  });

  // شروع گفتگو در انتهای فایل (بعد از تعریف HSPP)





  // =====================================================
  // ناظر گروه، نظرسنجی، سنجاق، نام نمایشی
  // =====================================================
  const HSPP = {
    state: null,
    cid: function () { return PChat.classId; },
    applyState: function (s) {
      const first = !this.state;
      this.state = s;
      $('#hspp-myname span').text(s.my_name || '');
      $('#hspp-mod-btn').prop('hidden', !s.is_mod);
      $('#hspp-rep-count').prop('hidden', !s.reports).text(s.reports || '');
      $('#hs-pchat-img-btn').toggle(!!s.images || s.is_mod);
      const $why = $('#hspp-why');
      if (s.can_post) { $why.prop('hidden', true); $('.hs-pchat-composer').removeClass('is-locked'); }
      else { $why.prop('hidden', false).text('🔒 ' + s.why); $('.hs-pchat-composer').addClass('is-locked'); }
      this.renderTop(s);
      if (first && s.is_mod && $('#hspp-modpanel').is(':visible')) this.loadMod();
    },
    renderTop: function (s) {
      let h = '';
      if (s.pinned) {
        h += '<div class="hspp-pin"><span>📌</span><div><b>' + escHtml(s.pinned.name) + '</b><p>' + escHtml(s.pinned.body || (s.pinned.msg_type === 'image' ? '🖼 تصویر' : 'پیام')) + '</p></div>' +
          (s.is_mod ? '<button type="button" class="hs-pchat-act" data-act="unpin">×</button>' : '') + '</div>';
      }
      if (s.poll) {
        const p = s.poll;
        h += '<div class="hspp-poll" data-poll="' + p.id + '"><b>📊 ' + escHtml(p.q) + '</b>';
        p.opts.forEach(function (o, i) {
          const pc = p.total ? Math.round(p.counts[i] * 100 / p.total) : 0;
          h += '<button type="button" class="hspp-opt' + (p.mine === i ? ' is-mine' : '') + '" data-choice="' + i + '"><span style="width:' + pc + '%"></span><em>' + escHtml(o) + '</em><small>' + (p.mine >= 0 ? pc + '٪' : '') + '</small></button>';
        });
        h += '<small class="hspp-muted">' + p.total + ' رأی' + (p.mine < 0 ? ' — برای دیدن نتیجه رأی دهید' : '') + '</small>' +
          (s.is_mod ? ' <button type="button" class="hs-pchat-act" data-act="poll_close">بستن نظرسنجی</button>' : '') + '</div>';
      }
      const $t = $('#hspp-top');
      if ($t.data('h') !== h) { $t.html(h).data('h', h); }
    },
    act: function (action, data, done) {
      data = $.extend({ class_id: this.cid() }, data || {});
      ajax('hspp_g_' + action, data).done(function (res) {
        if (!res.success) { alert((res.data && res.data.message) || i18n.error); return; }
        if (done) done(res.data); else if (res.data && res.data.message) HSPP.toast(res.data.message);
        PChat.fetch(false);
      }).fail(function () { alert(i18n.error); });
    },
    toast: function (t) {
      const $t = $('<div class="hspp-toast">').text(t).appendTo('#hs-pchat');
      setTimeout(function () { $t.fadeOut(300, function () { $t.remove(); }); }, 2500);
    },
    loadMod: function () {
      const $p = $('#hspp-modpanel').html('<p class="hspp-muted">در حال بارگذاری…</p>');
      ajax('hspp_g_members', { class_id: this.cid() }).done(function (res) {
        if (!res.success) { $p.html('<p>' + escHtml(res.data.message) + '</p>'); return; }
        const d = res.data, s = d.settings, owner = HSPP.state && HSPP.state.is_owner;
        let h = '<div class="hspp-mod-grid"><section><h4>تنظیمات گروه</h4>' +
          (owner ? '<label><input type="checkbox" name="enabled" ' + (+s.enabled ? 'checked' : '') + '> گروه روشن است</label>' : '') +
          '<label><input type="checkbox" name="read_only" ' + (+s.read_only ? 'checked' : '') + '> فقط‌خواندنی (فقط معلم و دستیاران پیام می‌فرستند)</label>' +
          '<label><input type="checkbox" name="allow_images" ' + (+s.allow_images ? 'checked' : '') + '> ارسال عکس توسط والدین</label>' +
          '<label><input type="checkbox" name="quiet_on" ' + (+s.quiet_on ? 'checked' : '') + '> ساعت سکوت از <input type="number" name="quiet_from" min="0" max="23" value="' + s.quiet_from + '"> تا <input type="number" name="quiet_to" min="0" max="23" value="' + s.quiet_to + '"></label>' +
          '<label>قوانین گروه<textarea name="rules" rows="3" placeholder="مثلاً: فقط موضوعات کلاس؛ احترام متقابل؛ تبلیغات ممنوع">' + escHtml(s.rules || '') + '</textarea></label>' +
          '<button type="button" class="hs-pp-btn hs-pp-btn-primary" id="hspp-save-set">ذخیره تنظیمات</button></section>';
        h += '<section><h4>اعضا' + (owner ? ' و دستیاران (حداکثر ' + d.max_assistants + ')' : '') + '</h4><ul class="hspp-members">';
        d.members.forEach(function (m) {
          h += '<li><span>' + escHtml(m.name) + (m.muted ? ' 🔇' : '') + '</span>' +
            (owner ? '<button type="button" class="hs-pchat-act" data-act="assistant" data-id="' + m.id + '" data-add="' + (m.assistant ? 0 : 1) + '">' + (m.assistant ? 'برداشتن دستیار' : 'دستیار شود') + '</button>' : '') +
            (m.muted ? '<button type="button" class="hs-pchat-act" data-act="unmute" data-id="' + m.id + '">برداشتن بی‌صدا</button>' : '') + '</li>';
        });
        if (!d.members.length) h += '<li class="hspp-muted">هنوز والدی به این کلاس متصل نیست.</li>';
        h += '</ul></section>';
        h += '<section><h4>نظرسنجی جدید</h4><input id="hspp-poll-q" placeholder="سؤال"><textarea id="hspp-poll-o" rows="3" placeholder="هر خط یک گزینه (۲ تا ۶)"></textarea><button type="button" class="hs-pp-btn" id="hspp-poll-new">ساخت نظرسنجی</button></section>';
        h += '<section><h4>گزارش‌ها (' + d.reports.length + ')</h4>';
        if (!d.reports.length) h += '<p class="hspp-muted">گزارشی نیست.</p>';
        d.reports.forEach(function (r) {
          h += '<div class="hspp-report"><b>' + escHtml(r.msg.name) + ':</b> ' + escHtml(r.msg.body || '[رسانه]') + '<br><small>' + r.n + ' گزارش — ' + escHtml(r.reasons || '') + '</small><br>' +
            '<button type="button" class="hs-pchat-act" data-act="delete" data-id="' + r.msg.id + '">حذف پیام</button> <button type="button" class="hs-pchat-act" data-act="mute" data-id="' + r.msg.sender_id + '" data-name="' + escAttr(r.msg.name) + '">بی‌صدا</button> <button type="button" class="hs-pchat-act" data-act="report_close" data-id="' + r.msg.id + '">مشکلی نیست</button></div>';
        });
        h += '</section></div>';
        $p.html(h);
      });
    }
  };

  $(document).on('click', '#hspp-mod-btn', function () {
    const $p = $('#hspp-modpanel');
    $p.prop('hidden', !$p.prop('hidden'));
    if (!$p.prop('hidden')) HSPP.loadMod();
  });
  $(document).on('click', '#hspp-save-set', function () {
    const $p = $('#hspp-modpanel'), d = {};
    ['read_only', 'allow_images', 'quiet_on'].forEach(function (k) { d[k] = $p.find('[name=' + k + ']').is(':checked') ? 1 : 0; });
    if ($p.find('[name=enabled]').length) d.enabled = $p.find('[name=enabled]').is(':checked') ? 1 : 0;
    d.quiet_from = $p.find('[name=quiet_from]').val();
    d.quiet_to = $p.find('[name=quiet_to]').val();
    d.rules = $p.find('[name=rules]').val();
    HSPP.act('settings', d);
  });
  $(document).on('click', '#hspp-poll-new', function () {
    HSPP.act('poll_create', { question: $('#hspp-poll-q').val(), options: $('#hspp-poll-o').val() }, function (r) { HSPP.toast(r.message); $('#hspp-poll-q,#hspp-poll-o').val(''); });
  });
  $(document).on('click', '.hspp-opt', function () {
    HSPP.act('poll_vote', { poll_id: $(this).closest('.hspp-poll').data('poll'), choice: $(this).data('choice') }, function () {});
  });
  $(document).on('click', '#hspp-myname', function () {
    const cur = HSPP.state ? HSPP.state.my_name : '';
    const n = prompt('نام شما در این گروه (خالی = پیش‌فرض، مثل «مادرِ سارا»):', cur.replace(/\s*\((دستیار|معلم)\)$/, ''));
    if (n === null) return;
    HSPP.act('name', { name: n }, function (r) { HSPP.toast(r.message); $('#hspp-myname span').text(r.name); });
  });
  $(document).on('click', '.hs-pchat-act', function () {
    const a = $(this).data('act'), id = $(this).data('id');
    if (a === 'delete') { if (confirm('این پیام حذف شود؟')) HSPP.act('delete', { msg_id: id }, function () { $('#hs-pchat-msg-' + id).remove(); if (!$('#hspp-modpanel').prop('hidden')) HSPP.loadMod(); }); }
    else if (a === 'report') { const r = prompt('چرا این پیام نامناسب است؟ (اختیاری)', ''); if (r !== null) HSPP.act('report', { msg_id: id, reason: r }); }
    else if (a === 'pin') HSPP.act('pin', { msg_id: id });
    else if (a === 'unpin') HSPP.act('pin', { msg_id: 0 });
    else if (a === 'poll_close') { if (confirm('نظرسنجی بسته شود؟')) HSPP.act('poll_close', {}); }
    else if (a === 'mute') { const h = prompt('«' + $(this).data('name') + '» چند ساعت نتواند پیام بفرستد؟', '24'); if (h) HSPP.act('mute', { user_id: id, hours: h }, function (r) { HSPP.toast(r.message); if (!$('#hspp-modpanel').prop('hidden')) HSPP.loadMod(); }); }
    else if (a === 'unmute') HSPP.act('mute', { user_id: id, hours: 0 }, function () { HSPP.loadMod(); });
    else if (a === 'assistant') HSPP.act('assistant', { user_id: id, add: $(this).data('add') ? 1 : '' }, function (r) { HSPP.toast(r.message); HSPP.loadMod(); });
    else if (a === 'report_close') HSPP.act('report_close', { msg_id: id }, function () { HSPP.loadMod(); });
  });

  PChat.boot();
})(jQuery);
