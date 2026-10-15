/* Browser rendering of live same-origin resources through TLS-verified Python fetch.
 * This avoids Chromium's missing environment CA without disabling certificate verification.
 * Third-party widgets are excluded. No WordPress credentials are used. */
const {chromium}=require('/opt/codex/runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const {promisify}=require('util');const {execFile}=require('child_process');const path=require('path');const fs=require('fs');
(async()=>{const b=await chromium.launch({executablePath:'/usr/bin/chromium',headless:true,args:['--no-sandbox']});try{
 const p=await b.newPage();const cache=new Map();const results=[];
 await p.route('**/*',async route=>{const u=route.request().url();if(!cache.has(u)){cache.set(u,promisify(execFile)('python',[path.join(__dirname,'../tools/public-page-fetch.py'),u],{maxBuffer:20000000}).then(x=>JSON.parse(x.stdout)));}const r=await cache.get(u);await route.fulfill({status:r.status,contentType:r.type,body:Buffer.from(r.body,'base64')});});
 const slugs=['','category/cats','category/whisky','category/it-freelance','cat-carrier-checklist','highball-measure-and-adjust','csv-import-seven-checks','hsh-about'];
 for(const width of [1440,768,390,320]){await p.setViewportSize({width,height:900});for(const slug of slugs){
  const r=await p.goto('https://hirogarushumi.com/'+(slug?slug+'/':'')+'?hsh_polish_check=1',{waitUntil:'load',timeout:60000});
  await p.locator('img[src*="hsh-"],img[src*="brand-avatar"]').evaluateAll(async imgs=>{for(const img of imgs){img.loading='eager';await img.decode();}});
  const data=await p.evaluate(()=>({overflow:document.documentElement.scrollWidth>innerWidth+1,h1:document.querySelectorAll('h1').length,broken:[...document.querySelectorAll('img[src*="hsh-"],img[src*="brand-avatar"]')].filter(x=>!x.naturalWidth).length,visibleShares:[...document.querySelectorAll('.sns-share,.sns-follow,.mobile-footer-menu-buttons')].filter(x=>getComputedStyle(x).display!=='none').length,profile:!!document.querySelector('.hsh-profile')}));
  if(r.status()!==200||data.overflow||data.h1!==1||data.broken||data.visibleShares)throw Error(JSON.stringify({slug,width,status:r.status(),...data}));
  results.push({slug,width,...data});if(width===390)await p.screenshot({path:'/tmp/hsh-polish-'+(slug.replaceAll('/','-')||'home')+'.png',fullPage:true});
 }}
 // Native search submits to WordPress and returns the existing CSV article.
 await p.goto('https://hirogarushumi.com/?hsh_polish_check=1',{waitUntil:'load'});
 await p.locator('#hsh-home-search').fill('CSV');await Promise.all([p.waitForURL(/\?s=CSV/),p.locator('.hsh-search button').click()]);
 if(!await p.locator('a[href*="csv-import-seven-checks"]').count())throw Error('Search result missing CSV article');
 results.push({search:'CSV',found:true});
 const summary={passed:results.length,failed:0,method:'Live HTML and same-origin resources with certificate-verified fetch; third-party widgets excluded',results};
 fs.writeFileSync('/tmp/hsh-polish-browser-results.json',JSON.stringify(summary,null,2));console.log(JSON.stringify({passed:summary.passed,failed:0}));
}finally{await b.close();}})().catch(e=>{console.error(e.message);process.exitCode=1;});
