(()=>{'use strict';
const raw=document.querySelector('#hsh-reader-data');let data;try{data=JSON.parse(raw?.textContent||'{}');}catch{data={};}
const topics={cats:'猫との暮らし',whisky:'ウイスキー','it-freelance':'ITの仕事と働き方'};
// Label ordinary two-dimensional tables for a mobile stacked presentation.
document.querySelectorAll('.entry-content table').forEach(table=>{if(table.querySelector('[colspan],[rowspan]'))return;const headers=[...table.querySelectorAll('thead th')].map(th=>th.textContent.trim());if(headers.length<2||headers.length>5)return;const rows=[...table.querySelectorAll('tbody tr')];if(!rows.length||rows.some(tr=>tr.children.length!==headers.length))return;table.classList.add('hsh-stack-table');rows.forEach(tr=>[...tr.children].forEach((td,i)=>td.dataset.label=headers[i]));});
// Best-effort first-party aggregate. Never delay, cancel or redirect a purchase link.
let config;try{config=JSON.parse(document.querySelector('#hsh-engagement-config')?.textContent||'{}');}catch{config={};}
const seen=new WeakSet();document.addEventListener('click',e=>{if(e.button!==0||!e.isTrusted||navigator.doNotTrack==='1'||navigator.globalPrivacyControl===true)return;const a=e.target.closest('.hsh-affiliate-cta a,.hsh-affiliate-context a');if(!a||seen.has(a)||!config.current||!config.endpoint)return;let endpoint;try{endpoint=new URL(config.endpoint,location.origin);if(endpoint.origin!==location.origin)return;}catch{return;}seen.add(a);setTimeout(()=>seen.delete(a),1500);fetch(endpoint.href,{method:'POST',credentials:'omit',keepalive:true,headers:{'Content-Type':'application/json'},body:JSON.stringify({slug:config.current})}).catch(()=>{});},{passive:true});
})();
