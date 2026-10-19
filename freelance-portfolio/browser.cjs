const {chromium}=require('/opt/codex/runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const assert=require('node:assert/strict');
const fs=require('node:fs');
(async()=>{const b=await chromium.launch({headless:true,executablePath:'/usr/bin/chromium',args:['--no-sandbox']});const p=await b.newPage({viewport:{width:1440,height:1000}});const errors=[];p.on('pageerror',e=>errors.push(e.message));let passed=0;try{
const r=await p.goto('http://127.0.0.1:8766/');assert.equal(r.status(),200);passed++;
assert.match(await p.title(),/WORK & CODE/);assert.equal(await p.locator('h1').count(),1);passed++;
assert.equal(await p.locator('.card').count(),3);passed++;
for(const width of [1440,1280]){await p.setViewportSize({width,height:1000});assert.equal(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);passed++;}
for(const id of ['services','about','process','contact']){await p.locator('.nav a[href="#'+id+'"]').click();assert.equal(new URL(p.url()).hash,'#'+id);passed++;}
await p.locator('#open-contact').click();assert.equal(await p.locator('dialog').evaluate(e=>e.open),true);passed++;
await p.locator('#contact-note').fill('CSVの取り込みについて相談');assert.match(await p.locator('#contact-note').inputValue(),/CSV/);passed++;
await p.locator('#copy-note').click();await p.waitForFunction(()=>document.querySelector('#copy-status').textContent.length>0);passed++;
await p.keyboard.press('Escape');assert.equal(await p.locator('dialog').evaluate(e=>e.open),false);passed++;
await p.locator('#open-contact').click();await p.locator('#close-contact').click();assert.equal(await p.locator('dialog').evaluate(e=>e.open),false);passed++;
await p.setViewportSize({width:1440,height:1000});await p.goto('http://127.0.0.1:8766/');await p.screenshot({path:'/workspace/artifacts/freelance-portfolio/preview.png',fullPage:true});await p.screenshot({path:'/workspace/artifacts/freelance-portfolio/first-view.png'});
assert.deepEqual(errors,[]);passed++;
const report={passed,failed:0,browser:'Chromium',viewports:[1440,1280],published:false};fs.writeFileSync('/workspace/blog/freelance-portfolio/test-results.json',JSON.stringify(report,null,2));console.log(report);
}finally{await b.close()}})().catch(e=>{console.error(e);process.exitCode=1});
