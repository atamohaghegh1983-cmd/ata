/* همشاگردی: کادر مدرسه (ردیف‌ها، چسباندن فهرست، بررسی شماره، QR و پیامک) + بارگذاری عکس QR برای کد دعوت */
(function () {
	'use strict';
	var C = window.hsumStaff || {};
	var faNum = function (s) { return String(s).replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); }).replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); }); };
	var normMob = function (s) { s = faNum(s).replace(/\D/g, ''); if (/^989\d{9}$/.test(s)) s = '0' + s.slice(2); if (/^9\d{9}$/.test(s)) s = '0' + s; return s; };

	/* ---------------- کادر ---------------- */
	document.querySelectorAll('.hsum-staff').forEach(function (box) {
		var list = box.querySelector('.hsum-st-list'), tpl = box.querySelector('.hsum-st-tpl');
		var next = parseInt(box.dataset.next, 10) || 1;
		function sync(row) {
			row.dataset.pos = row.querySelector('.hsum-st-pos').value;
		}
		/* مقطع مدرسه در فرم ثبت‌نام مدیر: ابتدایی ← بدون «درس» */
		var lvlSrc = document.querySelector('.hsum-level-src');
		function lvl() { if (lvlSrc) box.dataset.level = lvlSrc.value || 'primary'; }
		if (lvlSrc) { lvlSrc.addEventListener('change', lvl); lvl(); }
		function check() {
			var seen = {};
			list.querySelectorAll('.hsum-st-mob').forEach(function (i) {
				var v = normMob(i.value), bad = '';
				if (v && !/^09\d{9}$/.test(v)) bad = 'شماره باید ۱۱ رقم و با ۰۹ شروع شود';
				else if (v && seen[v]) bad = 'این شماره تکراری است';
				if (v) seen[v] = 1;
				i.classList.toggle('bad', !!bad);
				i.title = bad;
			});
		}
		function add(vals) {
			var n = next++;
			list.insertAdjacentHTML('beforeend', tpl.innerHTML.replace(/__N__/g, n));
			var row = list.lastElementChild;
			if (vals) {
				Object.keys(vals).forEach(function (k) {
					var el = row.querySelector('[name="staff[' + n + '][' + k + ']"]');
					if (!el || vals[k] === '') return;
					if (el.tagName === 'SELECT' && ![].some.call(el.options, function (o) { return (o.value || o.text) === vals[k]; })) return;
					el.value = vals[k];
				});
			}
			sync(row);
			return row;
		}
		list.querySelectorAll('.hsum-st-row').forEach(sync);
		box.querySelector('.hsum-st-add').addEventListener('click', function () { add().querySelector('[name$="[first]"]').focus(); });
		list.addEventListener('change', function (e) { var r = e.target.closest('.hsum-st-row'); if (r) sync(r); check(); });
		list.addEventListener('input', function (e) { if (e.target.classList.contains('hsum-st-mob')) check(); });
		list.addEventListener('click', function (e) {
			var b = e.target.closest('button'); if (!b) return;
			var row = b.closest('.hsum-st-row');
			if (b.classList.contains('hsum-st-del')) {
				if (row.querySelector('.hsum-st-state.ok') && !confirm('این نفر عضو کادر است. با ذخیره، از کادر مدرسه خارج می‌شود. حذف شود؟')) return;
				row.remove(); check();
				if (!list.children.length) add();
			} else if (b.classList.contains('hsum-st-qr')) {
				showQR(b.dataset.url, b.dataset.code, b.dataset.name);
			} else if (b.classList.contains('hsum-st-sms')) {
				b.disabled = true;
				var fd = new FormData(); fd.append('action', 'hsum_staff_sms'); fd.append('nonce', C.nonce); fd.append('id', b.dataset.id);
				fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
					b.disabled = false; b.textContent = j.success ? '📱 ارسال دوباره' : b.textContent;
					toast((j.data && j.data.message) || 'خطا');
				}).catch(function () { b.disabled = false; toast('ارتباط برقرار نشد.'); });
			}
		});
		/* چسباندن فهرست */
		var posMap = { 'معلم': 'teacher', 'معاون اجرایی': 'deputy_exec', 'معاون آموزشی': 'deputy_edu', 'معاون پرورشی': 'deputy_cult', 'اجرایی': 'deputy_exec', 'آموزشی': 'deputy_edu', 'پرورشی': 'deputy_cult' };
		var GR = ['پیش‌دبستانی','اول','دوم','سوم','چهارم','پنجم','ششم','هفتم','هشتم','نهم','دهم','یازدهم','دوازدهم'];
		function normGrade(g) { g = String(g || '').trim().replace(/^پایه\s*/, '').replace(/ي/g, 'ی'); var n = parseInt(faNum(g), 10); if (n >= 1 && n <= 12) return GR[n]; if (/پیش/.test(g)) return GR[0]; return g; }
		function clearEmpty() {
			list.querySelectorAll('.hsum-st-row').forEach(function (r) {
				if (!(+r.querySelector('[name$="[id]"]').value) && !r.querySelector('[name$="[first]"]').value && !r.querySelector('[name$="[mobile]"]').value) r.remove();
			});
		}
		/* ترتیب ستون‌ها مثل فایل نمونه: سمت، موبایل، نام خانوادگی، نام، درس، کلاس، پایه */
		function addRows(rows) {
			clearEmpty();
			var cnt = 0;
			rows.forEach(function (c) {
				c = c.map(function (x) { return String(x == null ? '' : x).trim(); });
				if (!c.join('') || /نام خانوادگی/.test(c.join(' '))) return;
				var pos = posMap[c[0]] || (/^0?9|^\+?98/.test(faNum(c[0])) ? 'teacher' : 'teacher');
				add({ position: pos, mobile: normMob(c[1] || ''), last: c[2] || '', first: c[3] || '', subject: c[4] || '', 'class': c[5] || '', grade: normGrade(c[6]) });
				cnt++;
			});
			if (!list.children.length) add();
			check();
			toast(cnt ? cnt + ' نفر به فهرست اضافه شد. برای ساخت کد دعوت، «ذخیره» را بزنید.' : 'سطری پیدا نشد.');
		}
		/* دانلود نمونه (CSV با BOM؛ در اکسل درست باز می‌شود) */
		box.querySelector('.hsum-st-sample').addEventListener('click', function () {
			var rows = [['سمت', 'موبایل', 'نام خانوادگی', 'نام', 'درس (فقط متوسطه)', 'کلاس', 'پایه'],
				['معلم', '09120000001', 'احمدی', 'مریم', '', 'الف', 'اول'],
				['معلم', '09120000002', 'رضایی', 'علی', 'ریاضی', '۱۰۲', 'دهم'],
				['معاون آموزشی', '09120000003', 'کریمی', 'زهرا', '', '', ''],
				['معاون اجرایی', '09120000004', 'موسوی', 'حسین', '', '', ''],
				['معاون پرورشی', '09120000005', 'نوری', 'سارا', '', '', '']];
			var csv = '﻿' + rows.map(function (r) { return r.map(function (x) { return '"' + String(x).replace(/"/g, '""') + '"'; }).join(','); }).join('\r\n');
			var a = document.createElement('a');
			a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
			a.download = 'nemone-kadr-madrese.csv';
			document.body.appendChild(a); a.click(); a.remove();
			toast('فایل نمونه دانلود شد. ستون‌ها را پر کنید و همین‌جا بارگذاری کنید.');
		});
		/* بارگذاری فایل اکسل/CSV */
		var up = box.querySelector('.hsum-st-upload input');
		up.addEventListener('change', function () {
			var f = this.files && this.files[0]; this.value = '';
			if (!f) return;
			var fd = new FormData(); fd.append('action', 'hsum_staff_import'); fd.append('nonce', C.nonce); fd.append('file', f);
			up.parentNode.classList.add('busy');
			fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
				if (j.success) addRows(j.data.rows || []); else toast((j.data && j.data.message) || 'فایل خوانده نشد.');
			}).catch(function () { toast('ارتباط برقرار نشد.'); }).then(function () { up.parentNode.classList.remove('busy'); });
		});
		check();
	});

	/* ---------------- نمایش QR ---------------- */
	function showQR(url, code, name) {
		var m = document.getElementById('hsum-qr-modal');
		if (!m) {
			document.body.insertAdjacentHTML('beforeend', '<div class="hsum-qr-modal" id="hsum-qr-modal" role="dialog" aria-modal="true"><div class="hsum-qr-box"><button type="button" class="hsum-qr-x" aria-label="بستن">×</button><h3></h3><div class="hsum-qr-img"></div><code dir="ltr"></code><div class="hsum-qr-btns"><button type="button" data-a="dl">⬇️ دانلود تصویر</button><button type="button" data-a="share">📤 اشتراک‌گذاری</button><button type="button" data-a="copy">🔗 کپی لینک</button></div></div></div>');
			m = document.getElementById('hsum-qr-modal');
			m.addEventListener('click', function (e) {
				if (e.target === m || e.target.closest('.hsum-qr-x')) { m.hidden = true; return; }
				var b = e.target.closest('[data-a]'); if (!b) return;
				var img = m.querySelector('img'), u = m.dataset.url, t = 'دعوت به همشاگردی آنلاین — کد: ' + m.dataset.code + '\n' + u;
				if (b.dataset.a === 'dl') { var a = document.createElement('a'); a.href = img.src; a.download = 'davat-' + m.dataset.code + '.png'; a.click(); }
				if (b.dataset.a === 'copy') { (navigator.clipboard ? navigator.clipboard.writeText(u) : Promise.reject()).then(function () { toast('لینک کپی شد.'); }, function () { prompt('لینک دعوت:', u); }); }
				if (b.dataset.a === 'share') {
					fetch(img.src).then(function (r) { return r.blob(); }).then(function (bl) {
						var f = new File([bl], 'davat-' + m.dataset.code + '.png', { type: 'image/png' });
						if (navigator.canShare && navigator.canShare({ files: [f] })) return navigator.share({ files: [f], text: t });
						if (navigator.share) return navigator.share({ text: t });
						window.open('https://wa.me/?text=' + encodeURIComponent(t), '_blank');
					}).catch(function () {});
				}
			});
		}
		m.dataset.url = url; m.dataset.code = code;
		m.querySelector('h3').textContent = 'دعوت‌نامه‌ی ' + (name || '');
		m.querySelector('code').textContent = code;
		var q = window.qrcode(0, 'M'); q.addData(url); q.make();
		var cv = document.createElement('canvas'), n = q.getModuleCount(), s = 8, pad = 4 * s;
		cv.width = cv.height = n * s + pad * 2;
		var g = cv.getContext('2d'); g.fillStyle = '#fff'; g.fillRect(0, 0, cv.width, cv.height); g.fillStyle = '#16213E';
		for (var r = 0; r < n; r++) for (var c = 0; c < n; c++) if (q.isDark(r, c)) g.fillRect(pad + c * s, pad + r * s, s, s);
		m.querySelector('.hsum-qr-img').innerHTML = '<img alt="QR" src="' + cv.toDataURL('image/png') + '">';
		m.hidden = false;
	}

	function toast(t) {
		var d = document.createElement('div'); d.className = 'hsum-toast'; d.textContent = t; document.body.appendChild(d);
		setTimeout(function () { d.classList.add('out'); }, 2600); setTimeout(function () { d.remove(); }, 3100);
	}

	/* ---------------- بارگذاری عکس QR (والدین) ---------------- */
	var jsqrP = null;
	function loadJsQR() {
		if (window.jsQR) return Promise.resolve(window.jsQR);
		if (!jsqrP) jsqrP = new Promise(function (ok, no) { var s = document.createElement('script'); s.src = C.jsqr; s.onload = function () { ok(window.jsQR); }; s.onerror = no; document.head.appendChild(s); });
		return jsqrP;
	}
	function decodeFile(file) {
		return new Promise(function (ok, no) {
			var img = new Image(), url = URL.createObjectURL(file);
			img.onload = function () {
				var tries = [1, 0.5, 2], i = 0;
				function run() {
					var k = Math.min(tries[i], 1600 / Math.max(img.width, img.height) * tries[i]);
					var w = Math.max(1, Math.round(img.width * k)), h = Math.max(1, Math.round(img.height * k));
					var cv = document.createElement('canvas'); cv.width = w; cv.height = h;
					var g = cv.getContext('2d'); g.drawImage(img, 0, 0, w, h);
					var d = g.getImageData(0, 0, w, h);
					var r = window.jsQR(d.data, w, h, { inversionAttempts: 'attemptBoth' });
					if (r && r.data) { URL.revokeObjectURL(url); ok(r.data); return; }
					if (++i < tries.length) run(); else { URL.revokeObjectURL(url); no(); }
				}
				run();
			};
			img.onerror = function () { URL.revokeObjectURL(url); no(); };
			img.src = url;
		});
	}
	function codeOf(txt) { var m = String(txt).match(/\/join\/([A-Za-z0-9-]{4,32})/); return (m ? m[1] : String(txt)).toUpperCase().trim(); }

	function enhance(inp) {
		if (inp.dataset.qrUp) return;
		inp.dataset.qrUp = 1;
		var lab = document.createElement('label');
		lab.className = 'hsum-qr-up';
		lab.innerHTML = '🖼 بارگذاری عکس QR<input type="file" accept="image/*" hidden>';
		var row = inp.closest('.hsum-code-row');
		(row || inp).insertAdjacentElement(row ? 'beforeend' : 'afterend', lab);
		lab.querySelector('input').addEventListener('change', function () {
			var f = this.files && this.files[0]; this.value = '';
			if (!f) return;
			lab.classList.add('busy');
			loadJsQR().then(function () { return decodeFile(f); }).then(function (txt) {
				inp.value = codeOf(txt); inp.style.borderColor = '#2EA043';
				inp.dispatchEvent(new Event('change', { bubbles: true }));
				toast('کد کلاس از روی عکس خوانده شد: ' + inp.value);
				// در صفحه‌ی اتصال (فرم کوتاه)، اگر نام فرزند پر است، همان‌جا ارسال شود
				var form = inp.form, child = form && form.querySelector('[name=child]');
				if (form && child && child.value.trim() && !form.classList.contains('hsum-onb-form')) form.requestSubmit ? form.requestSubmit() : form.submit();
			}).catch(function () { toast('در این عکس کیوآر کدی پیدا نشد. عکس واضح‌تر یا اسکرین‌شات کامل کارت را انتخاب کنید.'); })
				.then(function () { lab.classList.remove('busy'); });
		});
	}
	function scan() { document.querySelectorAll('.hsum input[name="code"], .hsum input[name$="[code]"], .hsum-onb input[name="code"], .hsum-onb input[name$="[code]"]').forEach(enhance); }
	scan();
	document.addEventListener('click', function (e) { if (e.target.closest('.hsum-kid-add')) setTimeout(scan, 0); });
})();
