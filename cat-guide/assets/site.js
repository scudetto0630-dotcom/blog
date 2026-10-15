'use strict';
const menu=document.querySelector('.menu-button'), nav=document.querySelector('#site-nav');
menu?.addEventListener('click',()=>{const open=menu.getAttribute('aria-expanded')!=='true';menu.setAttribute('aria-expanded',String(open));nav.classList.toggle('open',open)});
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&menu?.getAttribute('aria-expanded')==='true'){menu.setAttribute('aria-expanded','false');nav.classList.remove('open');menu.focus()}});
const form=document.querySelector('.guide-search');
if(form){
 const query=document.querySelector('#guide-query'),filters=[...document.querySelectorAll('[data-filter]')],cards=[...document.querySelectorAll('[data-search]')];let category='すべて';
 const norm=s=>s.normalize('NFKC').toLocaleLowerCase('ja').trim();
 const apply=(update=true)=>{const q=norm(query.value);let count=0;for(const card of cards){const show=(category==='すべて'||card.dataset.category===category)&&(!q||norm(card.dataset.search).includes(q));card.hidden=!show;if(show)count++}document.querySelector('#search-count').textContent=`${count}件の記事`;document.querySelector('.empty').hidden=count!==0;for(const f of filters)f.setAttribute('aria-pressed',String(f.dataset.filter===category));if(update){const u=new URL(location.href);q?u.searchParams.set('q',query.value):u.searchParams.delete('q');category==='すべて'?u.searchParams.delete('category'):u.searchParams.set('category',category);history.replaceState(null,'',u)}};
 const restore=()=>{const p=new URLSearchParams(location.search);query.value=(p.get('q')||'').slice(0,100);category=filters.some(x=>x.dataset.filter===p.get('category'))?p.get('category'):'すべて';apply(false)};
 form.addEventListener('submit',e=>{e.preventDefault();apply()});query.addEventListener('input',()=>apply());filters.forEach(f=>f.addEventListener('click',()=>{category=f.dataset.filter;apply()}));window.addEventListener('popstate',restore);restore();
}
const checks=[...document.querySelectorAll('[data-check]')];
if(checks.length){
 const key='neko-guide-preparation-v1',error=document.querySelector('.storage-error');
 const update=()=>{document.querySelector('.check-progress').textContent=`${checks.filter(x=>x.checked).length} / ${checks.length} 項目を確認`};
 try{const raw=localStorage.getItem(key);if(raw){const v=JSON.parse(raw);if(Array.isArray(v))checks.forEach(x=>x.checked=v.includes(x.dataset.check))}}catch{error.hidden=false}update();
 const save=()=>{update();try{localStorage.setItem(key,JSON.stringify(checks.filter(x=>x.checked).map(x=>x.dataset.check)));error.hidden=true}catch{error.hidden=false}};
 checks.forEach(x=>x.addEventListener('change',save));document.querySelector('.reset-checks').addEventListener('click',()=>{checks.forEach(x=>x.checked=false);update();try{localStorage.removeItem(key);error.hidden=true}catch{error.hidden=false}});
}
