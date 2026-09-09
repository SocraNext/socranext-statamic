<script>
(function(){
const origin={!! json_encode($origin, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
document.body.dataset.contentType={!! json_encode($kind, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
const selectors={
 'faq.toggle':'.socranext-toggle','faq.question':'.socranext-q','faq.answer':'.socranext-a','faq.item':'.socranext-qa','faq.disclaimer':'.socranext-disclaimer','faq.title':'.socranext-qalist > h2','faq.container':'.socranext-frontend-block',
 'article.cta':'.sn-cta','article.author':'.sn-author','article.layout':'.sn-toc, .sn-breadcrumbs, .sn-takeaways','article.title':'#socranext-artikel .entry-title','article.heading':'#socranext-artikel .entry-content h2, #socranext-artikel .entry-content h3','article.link':'#socranext-artikel .entry-content a','article.content':'#socranext-artikel .entry-content','article.container':'#socranext-artikel',
 'archive.title':'.socranext-archive-title','archive.button':'.socranext-read-more','archive.cardTitle':'.socranext-articles h2','archive.excerpt':'.socranext-articles .excerpt','archive.card':'.socranext-articles article','archive.container':'#socranext-archive'};
function send(data){window.parent.postMessage(data,origin);}
function clear(){document.querySelectorAll('.socranext-preview-highlight').forEach(el=>el.classList.remove('socranext-preview-highlight'));}
function text(selector,value){if(typeof value==='string')document.querySelectorAll(selector).forEach(el=>{el.textContent=value;el.style.display=value.trim()===''?'none':'';});}
window.addEventListener('message',function(event){
 if(event.source!==window.parent||event.origin!==origin||!event.data||typeof event.data!=='object')return;
 const data=event.data;
 if(data.type==='socranext:css'&&typeof data.css==='string'&&data.css.length<=500000)document.getElementById('socranext-preview-css').textContent=data.css;
 else if(data.type==='socranext:texts'){
  text('.socranext-qalist > h2, .socranext-archive-title',data.title);text('.socranext-read-more',data.buttonText);
  if(typeof data.disclaimer==='string'){
   const fragment=document.createElement('template');fragment.innerHTML=data.disclaimer;
   fragment.content.querySelectorAll('script,style,iframe,object,embed,svg,math,link,meta,img,form').forEach(el=>el.remove());
   fragment.content.querySelectorAll('*').forEach(el=>{
    if(!['A','P','BR','STRONG','EM','SPAN','DIV','B','I'].includes(el.tagName)){el.replaceWith(document.createTextNode(el.textContent));return;}
    Array.from(el.attributes).forEach(attr=>{if(!(el.tagName==='A'&&attr.name==='href'))el.removeAttribute(attr.name);});
    if(el.tagName==='A'){try{if(!['http:','https:','mailto:','tel:'].includes(new URL(el.getAttribute('href')||'',location.href).protocol))el.removeAttribute('href');}catch(e){el.removeAttribute('href');}el.rel='noopener noreferrer';}
   });
   document.querySelectorAll('.socranext-disclaimer').forEach(el=>{el.replaceChildren(fragment.content.cloneNode(true));el.style.display=data.disclaimer.trim()===''?'none':'';});
  }
  if(data.article&&typeof data.article==='object'){
   const map={tocTitle:'.sn-toc-title',takeawaysTitle:'.sn-takeaways-title',relatedTitle:'.sn-related-title',homeLabel:'.sn-breadcrumbs li:first-child a',ctaTitle:'.sn-cta-title',ctaText:'.sn-cta-text',ctaButtonText:'.sn-cta-button',authorName:'.sn-author-name',authorRole:'.sn-author-role',authorBio:'.sn-author-bio'};
   Object.entries(map).forEach(([key,selector])=>text(selector,data.article[key]));
  }
 }else if(data.type==='socranext:clear-highlight')clear();
 else if(data.type==='socranext:highlight'&&selectors[data.part]){clear();document.querySelectorAll(selectors[data.part]).forEach(el=>el.classList.add('socranext-preview-highlight'));}
});
function part(target){if(!target.closest)return null;return Object.keys(selectors).find(key=>target.closest(selectors[key]))||null;}
document.addEventListener('click',function(event){if(event.target.closest('a'))event.preventDefault();const selected=part(event.target);if(selected)send({type:'socranext:element-click',part:selected,contentType:document.body.dataset.contentType});},true);
let last=null;document.addEventListener('mouseover',function(event){const selected=part(event.target);if(selected!==last){last=selected;send({type:'socranext:element-hover',part:selected,contentType:document.body.dataset.contentType});}},true);
send({type:'socranext:ready',replica:false,contentType:document.body.dataset.contentType});
})();
</script>
