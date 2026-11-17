(function () {
  var toc = document.getElementById('hs-toc');
  if (!toc) return;
  var wrap = toc.closest('.hs-article-wrap');
  var det = toc.querySelector('details');
  var MIN = 190, MAX = 280, GAP = 32, EDGE = 16;
  var side = false;

  /* اگر سمت راست ستون محتوا جای کافی هست، فهرست آنجا می‌نشیند؛ وگرنه بالای مقاله و جمع‌شونده */
  function layout() {
    var r = wrap.getBoundingClientRect();
    var free = document.documentElement.clientWidth - r.right; // فضای خالی سمت راست
    var w = Math.min(MAX, free - GAP - EDGE);
    var ok = w >= MIN;
    if (ok) {
      wrap.style.setProperty('--hs-toc-w', w + 'px');
      wrap.style.setProperty('--hs-toc-gap', Math.min(GAP, Math.max(16, (free - w) / 2)) + 'px');
    }
    if (ok !== side) {
      side = ok;
      wrap.classList.toggle('hs-toc-side', ok);
      if (det) { if (ok) det.setAttribute('open', ''); else det.removeAttribute('open'); }
    }
  }
  layout();
  if (!side && det) det.removeAttribute('open');
  window.addEventListener('resize', layout);
  window.addEventListener('load', layout);
  if (det) toc.querySelector('summary').addEventListener('click', function (e) { if (side) e.preventDefault(); });

  var links = [].slice.call(toc.querySelectorAll('a[data-toc-target]'));
  var items = links.map(function (a) { return { a: a, el: document.getElementById(a.getAttribute('data-toc-target')) }; }).filter(function (x) { return x.el; });
  if (!items.length) return;
  function setActive(a) {
    links.forEach(function (l) { l.classList.remove('active'); l.removeAttribute('aria-current'); });
    if (!a) return;
    a.classList.add('active'); a.setAttribute('aria-current', 'true');
    if (side && toc.scrollHeight > toc.clientHeight) {
      var t = a.offsetTop - toc.clientHeight / 2;
      toc.scrollTo({ top: t < 0 ? 0 : t });
    }
  }
  var lock = 0;
  function onScroll() {
    if (Date.now() < lock) return;
    var cur = null;
    items.forEach(function (x) { if (x.el.getBoundingClientRect().top <= 130) cur = x; });
    setActive((cur || items[0]).a);
  }
  var ticking = false;
  window.addEventListener('scroll', function () {
    if (!ticking) { ticking = true; requestAnimationFrame(function () { ticking = false; onScroll(); }); }
  }, { passive: true });
  links.forEach(function (a) {
    a.addEventListener('click', function () {
      setActive(a); lock = Date.now() + 800;
      if (!side && det) det.removeAttribute('open');
    });
  });
  onScroll();
})();
