(function(){
  var f=document.querySelector('.hsar-form');if(!f)return;
  function src(){var v=(f.querySelector('[name=source]:checked')||{}).value||'paste';f.querySelectorAll('[data-src]').forEach(function(e){e.classList.toggle('on',e.dataset.src===v)})}
  f.querySelectorAll('[name=source]').forEach(function(r){r.addEventListener('change',src)});src();
  var st=f.querySelector('[data-status]'),fu=f.querySelector('[data-future]');function fut(){fu.style.display=st.value==='future'?'':'none'}st.addEventListener('change',fut);fut();
  f.querySelectorAll('[data-count]').forEach(function(i){var c=i.nextElementSibling,max=+i.dataset.count;function u(){var n=i.value.length;c.textContent=n+' / '+max;c.classList.toggle('over',n>max)}i.addEventListener('input',u);u()});
  var s={title:f.querySelector('[data-p=title]'),desc:f.querySelector('[data-p=desc]'),slug:f.querySelector('[data-p=slug]')};
  function serp(){s.title.textContent=f.seo_title.value||f.title.value||'عنوان مقاله';s.desc.textContent=f.description.value||'توضیح متا اینجا نمایش داده می‌شود.';s.slug.textContent=f.slug.value?f.slug.value+'/':''}
  ['seo_title','title','description','slug'].forEach(function(n){f[n].addEventListener('input',serp)});serp();
})();
