'use strict';
const menu=document.querySelector('.menu-button'), nav=document.querySelector('#site-nav');
menu?.addEventListener('click',()=>{const open=menu.getAttribute('aria-expanded')!=='true';menu.setAttribute('aria-expanded',String(open));nav.classList.toggle('open',open)});
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&menu?.getAttribute('aria-expanded')==='true'){menu.setAttribute('aria-expanded','false');nav.classList.remove('open');menu.focus()}});
const form=document.querySelector('.guide-search');
if(form){
 const query=document.querySelector('#guide-query'),filters=[...document.querySelectorAll('[data-filter]')],stages=[...document.querySelectorAll('[data-stage]')],cards=[...document.querySelectorAll('[data-search]')];let category='すべて',stage='all';
 const norm=s=>s.normalize('NFKC').toLocaleLowerCase('ja').trim();
 const apply=(update=true)=>{const q=norm(query.value);let count=0;for(const card of cards){const show=(category==='すべて'||card.dataset.category===category)&&(stage==='all'||(card.dataset.stages||'').split(' ').includes(stage))&&(!q||norm(card.dataset.search).includes(q));card.hidden=!show;if(show)count++}document.querySelector('#search-count').textContent=`${count}件の記事`;document.querySelector('.empty').hidden=count!==0;for(const f of filters)f.setAttribute('aria-pressed',String(f.dataset.filter===category));for(const f of stages)f.setAttribute('aria-pressed',String(f.dataset.stage===stage));if(update){const u=new URL(location.href);q?u.searchParams.set('q',query.value):u.searchParams.delete('q');category==='すべて'?u.searchParams.delete('category'):u.searchParams.set('category',category);stage==='all'?u.searchParams.delete('stage'):u.searchParams.set('stage',stage);history.replaceState(null,'',u)}};
 const restore=()=>{const p=new URLSearchParams(location.search);query.value=(p.get('q')||'').slice(0,100);category=filters.some(x=>x.dataset.filter===p.get('category'))?p.get('category'):'すべて';stage=stages.some(x=>x.dataset.stage===p.get('stage'))?p.get('stage'):'all';apply(false)};
 form.addEventListener('submit',e=>{e.preventDefault();apply()});query.addEventListener('input',()=>apply());filters.forEach(f=>f.addEventListener('click',()=>{category=f.dataset.filter;apply()}));stages.forEach(f=>f.addEventListener('click',()=>{stage=f.dataset.stage;apply()}));window.addEventListener('popstate',restore);restore();
}
const checks=[...document.querySelectorAll('[data-check]')];
if(checks.length){
 const key='neko-guide-preparation-v1',error=document.querySelector('.storage-error');
 const update=()=>{document.querySelector('.check-progress').textContent=`${checks.filter(x=>x.checked).length} / ${checks.length} 項目を確認`};
 try{const raw=localStorage.getItem(key);if(raw){const v=JSON.parse(raw);if(Array.isArray(v))checks.forEach(x=>x.checked=v.includes(x.dataset.check))}}catch{error.hidden=false}update();
 const save=()=>{update();try{localStorage.setItem(key,JSON.stringify(checks.filter(x=>x.checked).map(x=>x.dataset.check)));error.hidden=true}catch{error.hidden=false}};
 checks.forEach(x=>x.addEventListener('change',save));document.querySelector('.reset-checks').addEventListener('click',()=>{checks.forEach(x=>x.checked=false);update();try{localStorage.removeItem(key);error.hidden=true}catch{error.hidden=false}});
}

const report=document.querySelector('#correction-form');
if(report){
 const page=document.querySelector('#report-page'),message=document.querySelector('#report-message'),status=document.querySelector('#report-status'),button=report.querySelector('button[type=submit]');
 try{const relative=new URLSearchParams(location.search).get('page');if(relative){const root=new URL(page.defaultValue),u=new URL(relative,root);if(u.origin===root.origin&&u.pathname.startsWith(root.pathname)){u.search='';u.hash='';page.value=u.href}}}catch{}
 message.addEventListener('input',()=>message.setCustomValidity(''));
 report.addEventListener('submit',async event=>{
  event.preventDefault();if(button.disabled)return;
  if(!message.value.trim()){message.setCustomValidity('本文を入力してください。');message.reportValidity();return}
  const data={page:page.value,kind:report.elements.kind.value,message:message.value,website:report.elements.website.value};
  button.disabled=true;button.textContent='送信しています…';status.textContent='';
  try{
   const token=await fetch(new URL('api/report-token/',document.baseURI),{credentials:'same-origin',cache:'no-store',signal:AbortSignal.timeout(15000)});if(!token.ok)throw new Error('送信の準備ができませんでした。時間をおいてお試しください。');const {nonce}=await token.json();
   const response=await fetch(new URL('api/report/',document.baseURI),{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({...data,nonce}),signal:AbortSignal.timeout(15000)});const result=await response.json();
   if(!response.ok||result.received!==true)throw new Error(result.message||'送信できませんでした。時間をおいてお試しください。');
   report.reset();status.textContent='受け付けました。運営者が内容を確認します。このフォームからの返信はありません。';status.className='report-success';
  }catch(error){status.textContent=error.name==='TypeError'||error.name==='TimeoutError'?'通信できませんでした。入力内容は残っています。接続を確認してから再度送信してください。':error.message;status.className='report-error'}
  finally{button.disabled=false;button.textContent='訂正連絡を送る →'}
 });
}
