/* پنل دانش‌آموز — بازی‌ها، ارسال عکس تکلیف، صفحه‌ی رمز، کارت QR */
(function ($) {
  'use strict';
  var cfg = window.hsStudent || window.hsClass || {};

  function ajax(action, data) {
    data = data || {};
    data.action = action;
    data.nonce = cfg.nonce;
    return $.post(cfg.ajaxUrl, data);
  }
  function fa(n) {
    return String(n).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; });
  }
  function rnd(a, b) { return a + Math.floor(Math.random() * (b - a + 1)); }
  function shuffle(arr) {
    var a = arr.slice();
    for (var i = a.length - 1; i > 0; i--) { var j = Math.floor(Math.random() * (i + 1)); var t = a[i]; a[i] = a[j]; a[j] = t; }
    return a;
  }
  function esc(v) {
    return String(v).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
  }

  /* ---------- ارسال عکس تکلیف (یک بار) ---------- */
  $(document).on('click', '.hs-sw-submit-photo', function () {
    var $btn = $(this), $card = $btn.closest('.hs-kid-hw');
    var url = ($card.find('.hs-sw-photo-input').val() || '').trim();
    if (!url) { alert('ابتدا عکس را انتخاب کن'); return; }
    $btn.prop('disabled', true).text('...');
    ajax('hs_student_submit_hw_photo', { homework_id: $btn.data('hw'), image_url: url })
      .done(function (r) {
        alert((r && r.data && r.data.message) || 'خطا');
        if (r && r.success) location.reload(); else $btn.prop('disabled', false).text('📷 ثبت عکس');
      })
      .fail(function () { alert('ارتباط برقرار نشد.'); $btn.prop('disabled', false).text('📷 ثبت عکس'); });
  });

  /* ---------- سازنده‌ی سؤال‌ها (سطح بر اساس پایه) ---------- */
  function numOpts(c, spread, min) {
    var o = [c];
    var guard = 0;
    while (o.length < 4 && guard++ < 100) {
      var x = c + rnd(-spread, spread);
      if (x >= (min || 0) && o.indexOf(x) === -1) o.push(x);
    }
    while (o.length < 4) o.push(c + o.length * 7);
    return shuffle(o).map(String);
  }
  var LETTERS = [['ب', ['ب', 'پ', 'ت', 'ث']], ['س', ['ش', 'س', 'ص', 'ض']], ['م', ['ن', 'م', 'و', 'ه']], ['آ', ['ا', 'آ', 'ع', 'غ']], ['د', ['ذ', 'ر', 'د', 'ز']], ['ج', ['ج', 'چ', 'ح', 'خ']], ['ک', ['ک', 'گ', 'ق', 'ف']], ['ل', ['ل', 'ن', 'ی', 'م']], ['ر', ['ر', 'ز', 'ژ', 'د']], ['ط', ['ط', 'ظ', 'ص', 'ض']]];
  var WORDS = [['بابا', 1], ['انار', 2], ['آسمان', 3], ['کتاب', 2], ['مدرسه', 3], ['درخت', 2], ['خورشید', 3], ['پرنده', 1], ['دوست', 2], ['باران', 3], ['گل', 1], ['ماه', 2], ['زنبور', 4], ['کلاس', 2], ['نان', 1], ['سیب', 1]];

  var Make = {
    letters: function () { var p = LETTERS[rnd(0, LETTERS.length - 1)]; return { q: 'کدام یکی «' + p[0] + '» است؟', correct: p[0], opts: shuffle(p[1]) }; },
    count: function (g) {
      var n = rnd(1, g >= 1 ? 12 : 8), sh = ['⭐', '🍎', '🔵', '🌼', '🐸', '🎈'][rnd(0, 5)];
      return { q: 'چند تا می‌بینی؟', visual: new Array(n + 1).join(sh + ' '), correct: String(n), opts: numOpts(n, 3, 1) };
    },
    words: function () {
      var w = WORDS[rnd(0, WORDS.length - 1)], word = w[0], i = Math.min(w[1], word.length - 1), ch = word[i];
      var pool = shuffle(['ا', 'ب', 'ت', 'د', 'ر', 'س', 'ن', 'م', 'و', 'ی', 'ک', 'ه', 'ل', 'ز'].filter(function (c) { return c !== ch; })).slice(0, 3);
      return { q: 'کدام نشانه جا افتاده؟', visual: word.slice(0, i) + '<span class="hssp-gap">؟</span>' + word.slice(i + 1), correct: ch, opts: shuffle(pool.concat(ch)), html: true };
    },
    add: function (g) {
      var max = [10, 10, 20, 100, 1000][Math.min(g, 4)] || 10, a = rnd(1, Math.floor(max / 2)), b = rnd(1, max - a);
      return { q: fa(a) + ' + ' + fa(b) + ' = ؟', correct: String(a + b), opts: numOpts(a + b, max > 100 ? 30 : 4, 0) };
    },
    sub: function (g) {
      var max = [10, 10, 20, 100, 1000, 1000][Math.min(g, 5)] || 10, a = rnd(2, max), b = rnd(1, a - 1);
      return { q: fa(a) + ' − ' + fa(b) + ' = ؟', correct: String(a - b), opts: numOpts(a - b, max > 100 ? 30 : 4, 0) };
    },
    compare: function (g) {
      var max = [10, 10, 20, 100, 1000][Math.min(g, 4)] || 10, a = rnd(0, max), b = Math.random() < 0.2 ? a : rnd(0, max);
      return { q: 'کدام علامت درست است؟', visual: fa(a) + ' <span class="hssp-gap">؟</span> ' + fa(b), correct: a > b ? '>' : (a < b ? '<' : '='), opts: ['<', '=', '>'], html: true, ltr: true };
    },
    mul: function (g) {
      var top = g <= 2 ? 5 : (g <= 3 ? 10 : 12), a = rnd(2, top), b = rnd(2, top);
      return { q: fa(a) + ' × ' + fa(b) + ' = ؟', correct: String(a * b), opts: numOpts(a * b, Math.max(4, a), 1) };
    },
    div: function (g) {
      var top = g <= 4 ? 10 : 12, b = rnd(2, top), c = rnd(2, top);
      return { q: fa(b * c) + ' ÷ ' + fa(b) + ' = ؟', correct: String(c), opts: numOpts(c, 3, 1) };
    },
    frac: function (g) {
      var d1 = rnd(2, g >= 6 ? 12 : 8), d2 = rnd(2, g >= 6 ? 12 : 8), n1 = rnd(1, d1 - 1), n2 = rnd(1, d2 - 1);
      if (n1 * d2 === n2 * d1) n2 = n2 === d2 - 1 ? n2 - 1 : n2 + 1;
      if (n2 < 1) n2 = 1;
      var A = fa(n1) + '/' + fa(d1), B = fa(n2) + '/' + fa(d2);
      return { q: 'کدام کسر بزرگ‌تر است؟', correct: n1 * d2 > n2 * d1 ? A : B, opts: [A, B], ltr: true };
    },
    mental: function (g) {
      var t = rnd(0, g >= 9 ? 3 : 2), a, b, c, q, ans;
      if (t === 0) { a = rnd(-20, 20); b = rnd(-20, 20); q = '(' + a + ') + (' + b + ')'; ans = a + b; }
      else if (t === 1) { a = rnd(2, 12); b = rnd(2, 12); c = rnd(1, 30); q = a + ' × ' + b + ' − ' + c; ans = a * b - c; }
      else if (t === 2) { a = rnd(2, 9) * 10; b = rnd(2, 9); q = a + ' ÷ ' + (a / 10) + ' × ' + b; ans = 10 * b; }
      else { a = [10, 20, 25, 50][rnd(0, 3)]; b = rnd(1, 20) * 20; q = a + '٪ از ' + b; ans = a * b / 100; }
      return { q: fa(q).replace(/-/g, '−') + ' = ؟', correct: String(ans), opts: numOpts(ans, 10, -999).map(function (x) { return x; }), ltrOpts: true };
    }
  };

  function quiz($root, game, grade, done) {
    var total = grade >= 4 ? 10 : 5, idx = 0, score = 0, qs = [], seen = {};
    for (var i = 0, guard = 0; qs.length < total && guard < 200; guard++) {
      var q = Make[game](grade), key = q.q + '|' + (q.visual || '');
      if (seen[key] && guard < 150) continue;
      seen[key] = 1; qs.push(q);
    }
    function render() {
      if (idx >= qs.length) { done(score, total); return; }
      var q = qs[idx], dots = '';
      for (var i = 0; i < total; i++) dots += '<span class="' + (i < idx ? 'ok' : (i === idx ? 'on' : '')) + '"></span>';
      var opts = q.opts.map(function (o) {
        return '<button type="button" class="hs-kid-opt" data-val="' + esc(o) + '"' + (q.ltr || q.ltrOpts ? ' dir="ltr"' : '') + '>' + esc(/^-?\d+$/.test(o) ? fa(o).replace('-', '−') : o) + '</button>';
      }).join('');
      $root.html('<div class="hs-kid-progress">' + dots + '</div><div class="hs-kid-q"><div class="hs-kid-q-prompt">' + esc(q.q) + '</div>' +
        (q.visual ? '<div class="hs-kid-q-visual"' + (q.ltr ? ' dir="ltr"' : '') + '>' + (q.html ? q.visual : esc(q.visual)) + '</div>' : '') +
        '<div class="hs-kid-opts">' + opts + '</div></div>');
    }
    $root.off('click.q').on('click.q', '.hs-kid-opt', function () {
      var $b = $(this), q = qs[idx], ok = String($b.data('val')) === q.correct;
      $root.find('.hs-kid-opt').prop('disabled', true);
      $b.addClass(ok ? 'correct' : 'wrong');
      if (ok) score++; else $root.find('.hs-kid-opt').filter(function () { return String($(this).data('val')) === q.correct; }).addClass('correct');
      setTimeout(function () { idx++; render(); }, ok ? 600 : 1200);
    });
    render();
  }

  function memory($root, grade, done) {
    var pairs = grade >= 4 ? 8 : 6, em = shuffle(['🍎', '🐸', '⭐', '🌼', '🚗', '🎈', '🐝', '🍉', '🦊', '🌙']).slice(0, pairs);
    var cards = shuffle(em.concat(em)), open = [], found = 0, moves = 0, lock = false;
    var html = '<div class="hs-kid-q"><div class="hs-kid-q-prompt">جفت‌ها را پیدا کن!</div><div class="hs-kid-memory-grid' + (pairs > 6 ? ' g8' : '') + '">';
    cards.forEach(function (c, i) { html += '<button type="button" class="hs-kid-mem-card" data-i="' + i + '" data-v="' + c + '"><span>' + c + '</span></button>'; });
    $root.html(html + '</div></div>');
    $root.off('click.mem').on('click.mem', '.hs-kid-mem-card', function () {
      var $c = $(this);
      if (lock || $c.hasClass('flipped') || $c.hasClass('matched')) return;
      $c.addClass('flipped'); open.push($c);
      if (open.length === 2) {
        moves++; lock = true;
        if (open[0].data('v') === open[1].data('v')) {
          open.forEach(function (x) { x.addClass('matched'); }); open = []; lock = false; found++;
          if (found === pairs) setTimeout(function () { done(moves <= pairs * 2 ? pairs : Math.max(1, pairs - (moves - pairs * 2)), pairs); }, 500);
        } else {
          setTimeout(function () { open.forEach(function (x) { x.removeClass('flipped'); }); open = []; lock = false; }, 800);
        }
      }
    });
  }

  function boot() {
    var $root = $('#hs-kid-game-root');
    if (!$root.length) return;
    var game = String($root.data('game')), grade = parseInt($root.data('grade'), 10) || 0;
    $('#hs-kid-scoreboard').hide(); $root.show();
    function done(score, total) {
      $root.hide();
      var pct = score / total;
      $('#hs-kid-result-emoji').text(pct === 1 ? '🏆' : (pct >= 0.6 ? '🌟' : '💪'));
      $('#hs-kid-result-text').text(game === 'memory' ? 'همه‌ی جفت‌ها را پیدا کردی! آفرین!' : ('از ' + fa(total) + ' سؤال، ' + fa(score) + ' تا را درست جواب دادی!'));
      $('#hs-kid-result-msg').text('');
      $('#hs-kid-scoreboard').show();
      if (String($root.data('self')) === '1') {
        ajax('hs_student_game_score', { class_id: $root.data('class'), game: game, score: score, total: total })
          .done(function (r) { if (r && r.data && r.data.message) $('#hs-kid-result-msg').text(r.data.message); });
      }
    }
    if (game === 'memory') memory($root, grade, done);
    else if (Make[game]) quiz($root, game, grade, done);
    else $root.html('<p>این بازی پیدا نشد.</p>');
  }
  $(document).on('click', '#hs-kid-play-again', boot);

  /* ---------- صفحه‌ی رمز ۴ رقمی ---------- */
  $(document).on('click', '.hssp-pad button[data-n]', function () {
    var $in = $(this).closest('form').find('.hssp-pin-in'), v = $in.val(), n = String($(this).data('n'));
    $in.val(n === 'del' ? v.slice(0, -1) : (v + n).slice(0, 4));
    if ($in.val().length === 4) $(this).closest('form').trigger('submit');
  });
  $(document).on('input', '.hssp-code-in', function () { this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, ''); });

  /* ---------- QR روی کارت‌ها ---------- */
  function qrs() {
    if (typeof window.qrcode !== 'function') return;
    $('.hssp-qr[data-qr]').each(function () {
      try {
        var q = window.qrcode(0, 'M'); q.addData($(this).data('qr')); q.make();
        $(this).html(q.createSvgTag({ cellSize: 3, margin: 0, scalable: true }));
      } catch (e) { /* ignore */ }
    });
  }

  $(function () { boot(); qrs(); });
})(jQuery);
