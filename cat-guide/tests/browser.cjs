const {chromium}=require('/opt/codex/runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const assert=require('node:assert/strict');
const {promisify}=require('node:util');
const {execFile}=require('node:child_process');
const path=require('node:path');
const base=process.env.CAT_GUIDE_URL||'http://127.0.0.1:8023/';
const routes=['','guides/','welcome/','local/','about/','privacy/','articles/first-days/','articles/food-and-water/','articles/litter-box/','articles/vet-visit/','articles/safe-home/','articles/adoption-steps/'];
(async()=>{const browser=await chromium.launch({executablePath:'/usr/bin/chromium',headless:true,args:['--no-sandbox']});let passed=0;const cache=new Map();
 async function newContext(options){const context=await browser.newContext(options);if(base.startsWith('https://hirogarushumi.com/')){await context.route('**/*',async route=>{const u=route.request().url();if(!cache.has(u))cache.set(u,promisify(execFile)('python',[path.join(__dirname,'../../tools/public-page-fetch.py'),u],{maxBuffer:20000000}).then(r=>JSON.parse(r.stdout)));const r=await cache.get(u);await route.fulfill({status:r.status,contentType:r.type,body:Buffer.from(r.body,'base64')})})}return context}
 try{
 for(const width of [1440,768,390,320]){
 const context=await newContext({viewport:{width,height:1000},deviceScaleFactor:1});const page=await context.newPage();
 for(const route of routes){const errors=[];const handler=e=>errors.push(e.message);page.on('pageerror',handler);const r=await page.goto(base+route);assert.equal(r.status(),200);await page.locator('h1').waitFor();assert.equal(await page.locator('h1').count(),1);assert.equal(await page.locator('.preview-bar').count(),0);assert.equal(await page.locator('link[rel=canonical]').count(),1);assert.match(await page.title(),/ねこより西東京/);assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true,`overflow ${width} ${route}`);assert.equal(await page.locator('meta[name=robots]').getAttribute('content'),'index,follow,max-image-preview:large');assert.equal(await page.evaluate(()=>[...document.images].every(i=>i.complete&&i.naturalWidth>0)),true);assert.deepEqual(errors,[]);page.off('pageerror',handler);passed++;}
 await page.goto(base);await page.screenshot({path:`/tmp/cat-guide-home-${width}.png`,fullPage:true});
 if(width===390){await page.getByRole('button',{name:'メニュー'}).click();assert.equal(await page.getByRole('button',{name:'メニュー'}).getAttribute('aria-expanded'),'true');await page.keyboard.press('Escape');assert.equal(await page.getByRole('button',{name:'メニュー'}).getAttribute('aria-expanded'),'false');passed++;}
 await context.close();}
 const context=await newContext();const page=await context.newPage();await page.goto(base+'guides/');assert.equal(await page.locator('.article-card:visible').count(),6);await page.locator('#guide-query').fill('トイレ');assert.equal(await page.locator('.article-card:visible').count(),1);passed++;
 await page.reload();assert.equal(await page.locator('#guide-query').inputValue(),'トイレ');assert.equal(await page.locator('.article-card:visible').count(),1);passed++;
 await page.locator('#guide-query').fill('存在しない記事');await page.getByRole('button',{name:'検索',exact:true}).click();assert.equal(await page.locator('.empty').isVisible(),true);passed++;
 await page.locator('#guide-query').fill('');await page.locator('[data-filter="迎える準備"]').click();assert.equal(await page.locator('.article-card:visible').count(),2);passed++;
 await page.goto(base+'guides/?category=毎日の世話');assert.equal(await page.locator('.article-card:visible').count(),2);passed++;
 await page.goto(base+'guides/');await page.locator('#guide-query').fill('<script>alert(1)</script>');assert.equal(await page.locator('.empty').isVisible(),true);assert.equal(await page.locator('script:not([src])').count(),0);passed++;
 await page.goto(base+'welcome/');await page.locator('[data-check="0"]').check();await page.locator('[data-check="2"]').check();assert.match(await page.locator('.check-progress').innerText(),/^2 \/ 6/);await page.reload();assert.equal(await page.locator('[data-check="0"]').isChecked(),true);assert.equal(await page.locator('[data-check="2"]').isChecked(),true);passed++;
 await page.locator('.reset-checks').click();await page.reload();assert.equal(await page.locator('input:checked').count(),0);assert.equal(await page.evaluate(()=>localStorage.getItem('neko-guide-preparation-v1')),null);passed++;
 await page.evaluate(()=>localStorage.setItem('neko-guide-preparation-v1','broken json'));await page.reload();assert.equal(await page.locator('.storage-error').isVisible(),true);await page.locator('[data-check="1"]').check();assert.equal(await page.locator('.storage-error').isVisible(),false);passed++;
 await page.goto(base+'articles/vet-visit/');assert.equal(await page.locator('.medical-note').isVisible(),true);await page.locator('.toc a').first().click();assert.match(page.url(),/#section-0$/);await page.getByRole('link',{name:'← 記事一覧へ戻る'}).click();assert.match(page.url(),/guides\/$/);passed++;
 const blocked=await newContext();await blocked.addInitScript(()=>{Storage.prototype.setItem=function(){throw new DOMException('Storage blocked','QuotaExceededError')}});const bp=await blocked.newPage();await bp.goto(base+'welcome/');await bp.locator('[data-check="0"]').check();assert.equal(await bp.locator('.storage-error').isVisible(),true);assert.match(await bp.locator('.check-progress').innerText(),/^1 \/ 6/);passed++;await blocked.close();
 await context.close();console.log(JSON.stringify({passed,failed:0}));
 }finally{await browser.close()}})().catch(e=>{console.error(e);process.exit(1)});
