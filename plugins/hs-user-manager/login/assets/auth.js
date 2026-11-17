/* ثبت‌نام و ورود با موبایل */
(function () {
  'use strict';
  var root = document.getElementById('hsma');
  if (!root || typeof hsmaCfg === 'undefined') return;

  var state = { mobile: '', ticket: '', timer: null };
  var msg = root.querySelector('.hsma-msg');
  var steps = {};
  root.querySelectorAll('.hsma-step').forEach(function (f) { steps[f.dataset.step] = f; });

  function faDigitsToEn(s) {
    return String(s || '').replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); })
      .replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); });
  }

  var stepNo = { mobile: 1, code: 1, role: 2, register: 3 };
  var stepper = root.querySelector('.hsma-steps');
  function show(name, noFocus) {
    Object.keys(steps).forEach(function (k) { steps[k].hidden = (k !== name); });
    if (stepper) {
      stepper.hidden = (name === 'pw');
      stepper.querySelectorAll('li').forEach(function (li) {
        var n = +li.dataset.s, cur = stepNo[name] || 1;
        li.className = n < cur ? 'is-done' : (n === cur ? 'is-on' : '');
      });
    }
    if (noFocus) return;
    var first = steps[name].querySelector('input:not([type=radio]):not([hidden])');
    if (first) setTimeout(function () { first.focus(); }, 30);
  }

  function say(text, type) {
    msg.textContent = text || '';
    msg.className = 'hsma-msg' + (text ? ' is-' + (type || 'info') : '');
  }

  function post(action, data, btn) {
    var fd = new FormData();
    fd.append('action', 'hsma_' + action);
    fd.append('nonce', hsmaCfg.nonce);
    fd.append('redirect_to', hsmaCfg.redirect || '');
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    var label = btn ? btn.textContent : '';
    if (btn) { btn.disabled = true; btn.textContent = 'لطفاً صبر کنید…'; }
    return fetch(hsmaCfg.ajax, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .catch(function () { return { success: false, data: { message: 'ارتباط با سرور برقرار نشد. اینترنت را بررسی کنید.' } }; })
      .then(function (res) {
        if (btn) { btn.disabled = false; btn.textContent = label; }
        return res || { success: false, data: {} };
      });
  }

  function go(res) {
    if (res.data && res.data.redirect) {
      root.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
      setTimeout(function () { window.location.href = res.data.redirect; }, (res.data.notes && res.data.notes.length) ? 2200 : 600);
      return true;
    }
    return false;
  }

  function startTimer(sec) {
    var btn = root.querySelector('.hsma-resend');
    var t = root.querySelector('.hsma-timer');
    clearInterval(state.timer);
    btn.disabled = true;
    state.timer = setInterval(function () {
      sec--;
      t.textContent = sec > 0 ? '(' + sec + ')' : '';
      if (sec <= 0) { clearInterval(state.timer); btn.disabled = false; }
    }, 1000);
    t.textContent = '(' + sec + ')';
  }

  function setMobileLabels() {
    root.querySelectorAll('.hsma-mobile').forEach(function (el) { el.textContent = state.mobile; });
  }

  // تب‌ها
  root.querySelectorAll('.hsma-tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
      root.querySelectorAll('.hsma-tab').forEach(function (t) { t.classList.toggle('is-on', t === tab); });
      say('');
      show(tab.dataset.tab === 'pw' ? 'pw' : (state.ticket ? 'role' : 'mobile'));
    });
  });

  function sendCode(btn) {
    return post('send', { mobile: state.mobile }, btn).then(function (res) {
      if (res.success) {
        say(res.data.message, 'ok');
        setMobileLabels();
        show('code');
        startTimer(res.data.wait || 60);
      } else {
        say(res.data.message, 'err');
        if (res.data.code === 'cooldown') { setMobileLabels(); show('code'); }
      }
    });
  }

  // ۱) موبایل
  steps.mobile.addEventListener('submit', function (e) {
    e.preventDefault();
    var v = faDigitsToEn(this.mobile.value).replace(/\D/g, '');
    if (v.indexOf('98') === 0 && v.length === 12) v = '0' + v.slice(2);
    if (v.length === 10 && v[0] === '9') v = '0' + v;
    if (!/^09\d{9}$/.test(v)) { say('شماره موبایل درست نیست. مثال: 09121234567', 'err'); return; }
    state.mobile = v;
    state.ticket = '';
    sendCode(this.querySelector('button[type=submit]'));
  });

  // ۲) کد
  var codeInput = steps.code.querySelector('.hsma-code');
  codeInput.addEventListener('input', function () {
    this.value = faDigitsToEn(this.value).replace(/\D/g, '').slice(0, 5);
    if (this.value.length === 5) steps.code.requestSubmit ? steps.code.requestSubmit() : steps.code.dispatchEvent(new Event('submit'));
  });
  var verifying = false;
  steps.code.addEventListener('submit', function (e) {
    e.preventDefault();
    if (verifying) return;
    var code = codeInput.value;
    if (code.length !== 5) { say('کد ۵ رقمی را کامل وارد کنید.', 'err'); return; }
    verifying = true;
    post('verify', { mobile: state.mobile, code: code }, this.querySelector('button[type=submit]')).then(function (res) {
      verifying = false;
      if (!res.success) {
        say(res.data.message, 'err');
        codeInput.value = '';
        if (res.data.code === 'expired' || res.data.code === 'too_many') {
          root.querySelector('.hsma-resend').disabled = false;
        }
        return;
      }
      if (go(res)) { say(res.data.message, 'ok'); return; }
      if (res.data.register) {
        state.ticket = res.data.ticket;
        say('');
        show('role');
      }
    });
  });
  root.querySelector('.hsma-resend').addEventListener('click', function () { sendCode(this); });
  root.querySelector('.hsma-edit').addEventListener('click', function () { say(''); show('mobile'); });

  // ۳) نقش
  function applyRole(role) {
    state.role = role;
    steps.register.querySelectorAll('.hsma-extra').forEach(function (box) {
      box.hidden = box.dataset.for.split(' ').indexOf(role) === -1;
    });
    var r = steps.role.querySelector('input[name=role][value="' + role + '"]');
    root.querySelectorAll('.hsma-role-label').forEach(function (el) { el.textContent = r ? r.parentNode.querySelector('b').textContent : ''; });
  }
  steps.role.addEventListener('submit', function (e) {
    e.preventDefault();
    var r = this.querySelector('input[name=role]:checked');
    if (!r) { say('نقش خود را انتخاب کنید.', 'err'); return; }
    say('');
    applyRole(r.value);
    show('register');
  });
  steps.role.querySelectorAll('input[name=role]').forEach(function (r) {
    r.addEventListener('dblclick', function () { steps.role.requestSubmit ? steps.role.requestSubmit() : null; });
  });
  root.querySelector('.hsma-back-role').addEventListener('click', function () { say(''); show('role'); });

  // ۴) مشخصات
  var registering = false;
  steps.register.addEventListener('submit', function (e) {
    e.preventDefault();
    if (registering) return;
    var f = this;
    var role = state.role ? { value: state.role } : null;
    if (!role) { show('role'); say('نقش خود را انتخاب کنید.', 'err'); return; }
    if (!f.first_name.value.trim() || !f.last_name.value.trim()) { say('نام و نام خانوادگی را وارد کنید.', 'err'); return; }
    if (f.password.value.length < 6) { say('رمز عبور باید دست‌کم ۶ کاراکتر باشد.', 'err'); return; }
    registering = true;
    post('register', {
      ticket: state.ticket,
      role: role.value,
      first_name: f.first_name.value.trim(),
      last_name: f.last_name.value.trim(),
      password: f.password.value,
      invite_code: f.invite_code.value.trim(),
      child_username: f.child_username.value.trim(),
      child_code: f.child_code.value.trim(),
      school: f.school.value.trim()
    }, f.querySelector('button[type=submit]')).then(function (res) {
      if (!res.success) {
        registering = false;
        say(res.data.message, 'err');
        if (res.data.code === 'ticket') { state.ticket = ''; show('mobile'); }
        return;
      }
      var notes = (res.data.notes || []).join(' ');
      say(res.data.message + (notes ? ' ' + notes : ''), 'ok');
      go(res);
    });
  });

  // ورود با رمز
  steps.pw.addEventListener('submit', function (e) {
    e.preventDefault();
    var f = this;
    if (!f.mobile.value.trim() || !f.password.value) { say('شماره موبایل و رمز را وارد کنید.', 'err'); return; }
    post('login_pw', { mobile: faDigitsToEn(f.mobile.value.trim()), password: f.password.value }, f.querySelector('button[type=submit]'))
      .then(function (res) {
        if (!res.success) { say(res.data.message, 'err'); return; }
        say(res.data.message, 'ok');
        go(res);
      });
  });

  // نقشِ صفحه‌ای که کاربر از آن آمده، در ثبت‌نام از پیش انتخاب شود
  if (hsmaCfg.role) {
    var pre = steps.role.querySelector('input[name=role][value="' + hsmaCfg.role + '"]');
    if (pre) { pre.checked = true; }
  }

  if (hsmaCfg.start === 'pw') {
    root.querySelectorAll('.hsma-tab').forEach(function (t) { t.classList.toggle('is-on', t.dataset.tab === 'pw'); });
    show('pw', true);
  } else {
    show('mobile', true); // بار اول فوکوس نکن تا صفحه روی موبایل پایین نپرد
  }
})();
