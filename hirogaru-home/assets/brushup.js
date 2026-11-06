(()=>{'use strict';
const raw=document.querySelector('#hsh-reader-data');let data;try{data=JSON.parse(raw?.textContent||'{}');}catch{data={};}
const topics={cats:'猫との暮らし',whisky:'ウイスキー','it-freelance':'ITの仕事と働き方'};
// Saved filters leave the underlying saved collection intact.
const savedRoot=document.querySelector('[data-hsh-saved-list]');
if(savedRoot){const nav=document.createElement('div');nav.className='hsh-saved-filters';nav.setAttribute('role','group');nav.setAttribute('aria-label','保存記事の分野');let choice='all';const catalog=new Map((data.articles||[]).map(a=>[new URL(a.url,location.origin).pathname,a]));
 const apply=()=>{savedRoot.querySelectorAll('li').forEach(li=>{const a=li.querySelector('a');if(!a)return;const item=catalog.get(new URL(a.href).pathname);li.hidden=choice!=='all'&&item?.topic!==choice;});};
 for(const [key,label] of Object.entries({all:'すべて',...topics})){const b=document.createElement('button');b.type='button';b.textContent=label;b.setAttribute('aria-pressed',String(key===choice));b.addEventListener('click',()=>{choice=key;nav.querySelectorAll('button').forEach(x=>x.setAttribute('aria-pressed',String(x===b)));apply();});nav.append(b);}savedRoot.before(nav);new MutationObserver(apply).observe(savedRoot,{childList:true,subtree:true});apply();}
// Label ordinary two-dimensional tables for a mobile stacked presentation.
document.querySelectorAll('.entry-content table').forEach(table=>{if(table.querySelector('[colspan],[rowspan]'))return;const headers=[...table.querySelectorAll('thead th')].map(th=>th.textContent.trim());if(headers.length<2||headers.length>5)return;const rows=[...table.querySelectorAll('tbody tr')];if(!rows.length||rows.some(tr=>tr.children.length!==headers.length))return;table.classList.add('hsh-stack-table');rows.forEach(tr=>[...tr.children].forEach((td,i)=>td.dataset.label=headers[i]));});
// Best-effort first-party aggregate. Never delay, cancel or redirect a purchase link.
let config;try{config=JSON.parse(document.querySelector('#hsh-engagement-config')?.textContent||'{}');}catch{config={};}
const seen=new WeakSet();document.addEventListener('click',e=>{if(e.button!==0||!e.isTrusted||navigator.doNotTrack==='1'||navigator.globalPrivacyControl===true)return;const a=e.target.closest('.hsh-affiliate-cta a');if(!a||seen.has(a)||!config.current||!config.endpoint)return;let endpoint;try{endpoint=new URL(config.endpoint,location.origin);if(endpoint.origin!==location.origin)return;}catch{return;}seen.add(a);setTimeout(()=>seen.delete(a),1500);fetch(endpoint.href,{method:'POST',credentials:'omit',keepalive:true,headers:{'Content-Type':'application/json'},body:JSON.stringify({slug:config.current})}).catch(()=>{});},{passive:true});
})();
