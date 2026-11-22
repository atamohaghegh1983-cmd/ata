(function(){
  var d=document.documentElement;
  var t=document.querySelector('[data-hst-theme]');
  if(t)t.addEventListener('click',function(){var n=d.dataset.theme==='dark'?'light':'dark';d.dataset.theme=n;try{localStorage.setItem('hst-theme',n)}catch(e){}});
  var m=document.querySelector('[data-hst-menu]'),nav=document.querySelector('.hst-nav');
  if(m&&nav)m.addEventListener('click',function(){var o=nav.classList.toggle('is-open');m.setAttribute('aria-expanded',o)});
  var els=document.querySelectorAll('.hst-reveal');
  if(!('IntersectionObserver' in window)){els.forEach(function(e){e.classList.add('is-in')});return}
  var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){e.target.classList.add('is-in');io.unobserve(e.target)}})},{rootMargin:'0px 0px -8% 0px'});
  els.forEach(function(e){io.observe(e)});
})();
(function(){
  var reduce=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches;
  // تور تب‌دار: خودکار، توقف با هاور/فوکوس، کیبورد
  document.querySelectorAll('[data-hst-tour]').forEach(function(t){
    var tabs=[].slice.call(t.querySelectorAll('.hst-tab')),panes=t.querySelectorAll('.hst-pane'),i=0,timer;
    tabs.forEach(function(b,k){b.id=b.id||('hst-tab-'+k);panes[k].id=panes[k].id||('hst-pane-'+k);b.setAttribute('aria-controls',panes[k].id);panes[k].setAttribute('aria-labelledby',b.id);b.setAttribute('tabindex',k?'-1':'0')});
    function go(n,focus){i=(n+tabs.length)%tabs.length;tabs.forEach(function(b,k){var on=k===i;b.classList.toggle('is-on',on);b.setAttribute('aria-selected',on);b.setAttribute('tabindex',on?'0':'-1');panes[k].classList.toggle('is-on',on);panes[k].hidden=!on});if(focus)tabs[i].focus();arm()}
    function arm(){clearTimeout(timer);if(!reduce&&!t.classList.contains('is-paused'))timer=setTimeout(function(){go(i+1)},7000)}
    tabs.forEach(function(b,k){b.addEventListener('click',function(){t.classList.add('is-paused');go(k)});
      b.addEventListener('keydown',function(e){if(e.key==='ArrowLeft'){go(i+1,1);e.preventDefault()}if(e.key==='ArrowRight'){go(i-1,1);e.preventDefault()}})});
    t.addEventListener('mouseenter',function(){t.classList.add('is-paused');clearTimeout(timer)});
    t.addEventListener('mouseleave',function(){t.classList.remove('is-paused');go(i)});
    go(0);
  });
  // امتیاز دادن به بچه‌ها
  document.querySelectorAll('[data-hst-point]').forEach(function(c){c.addEventListener('click',function(){
    var p=c.querySelector('.hst-pts');p.textContent=(+p.dataset.n||+p.textContent.replace(/[^\d]/g,'')||0)+1;p.dataset.n=p.textContent;
    p.textContent=(+p.dataset.n).toLocaleString('fa-IR');c.classList.remove('is-bump');void c.offsetWidth;c.classList.add('is-bump');
    var f=document.createElement('span');f.className='hst-fly';f.textContent='+۱';c.appendChild(f);setTimeout(function(){f.remove()},900)})});
  document.querySelectorAll('.hst-pts').forEach(function(p){p.dataset.n=p.textContent.trim();p.textContent=(+p.dataset.n).toLocaleString('fa-IR')});
  if(reduce)return;
  // پارالاکس هیرو با موس
  var st=document.querySelector('[data-hst-parallax]');
  if(st&&matchMedia('(hover:hover)').matches){var items=st.querySelectorAll('[data-depth]');
    window.addEventListener('mousemove',function(e){var x=e.clientX/innerWidth-.5,y=e.clientY/innerHeight-.5;
      items.forEach(function(el){var d=+el.dataset.depth;el.style.transform='translate('+(x*d)+'px,'+(y*d)+'px)'})},{passive:true})}
  // کج شدن سه‌بعدی کارت‌ها
  if(matchMedia('(hover:hover)').matches)document.querySelectorAll('[data-hst-tilt]').forEach(function(c){
    c.addEventListener('mousemove',function(e){var r=c.getBoundingClientRect(),x=(e.clientX-r.left)/r.width-.5,y=(e.clientY-r.top)/r.height-.5;
      c.style.transform='perspective(800px) rotateY('+(x*10)+'deg) rotateX('+(-y*10)+'deg) translateY(-6px)'});
    c.addEventListener('mouseleave',function(){c.style.transform=''})});
})();
