const {chromium}=require('/opt/codex/runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
(async()=>{const browser=await chromium.launch({executablePath:'/usr/bin/chromium',headless:true,args:['--no-sandbox']});const page=await browser.newPage({viewport:{width:1440,height:1000}});let checks=0;const errors=[];page.on('pageerror',e=>errors.push(e.message));
try{
const fixture=fs.readFileSync('/tmp/planner-admin-fixture.html','utf8');
await page.route('**/*',r=>r.fulfill({status:200,contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><rect width="100" height="100" fill="#eedfca"/></svg>'}));
await page.setContent('<html lang="ja"><head><meta charset="utf-8"></head><body>'+fixture+'</body></html>');
await page.addStyleTag({path:path.join(__dirname,'../hirogaru-home/assets/editorial-planner.css')});
await page.evaluate(()=>{
 window.wp={media:()=>{
  let select;
  const item={toJSON:()=>({id:702,title:'追加したまんもす',filename:'manmos-new.jpg'})};
  return {on:(event,f)=>{select=f;},open:()=>select(),state:()=>({get:()=>({first:()=>item})})};
 }};
});
await page.addScriptTag({path:path.join(__dirname,'../hirogaru-home/assets/editorial-planner.js')});
assert.equal(await page.locator('[data-hsh-type=article]').count(),14);checks++;
assert.equal(await page.locator('[data-hsh-type=photo]').count(),46);checks++;
await page.locator('#hsh-plan-type').selectOption('article');assert.equal(await page.locator('[data-hsh-type=photo]:visible').count(),0);checks++;
await page.locator('#hsh-plan-search').fill('private planning note');assert.equal(await page.locator('[data-hsh-type=article]:visible').count(),1);checks++;
await page.locator('#hsh-plan-type').selectOption('photo');await page.locator('#hsh-plan-search').fill('実写写真');const real=await page.locator('[data-hsh-type=photo]:visible').count();assert.equal(real,9);checks++;
await page.locator('#hsh-plan-search').fill('生成画像');assert.equal(await page.locator('[data-hsh-type=photo]:visible').count(),37);checks++;
await page.locator('#hsh-plan-search').fill('存在しない検索文字列');assert.equal(await page.locator('[data-hsh-type=photo]:visible').count(),0);checks++;
await page.locator('#hsh-plan-search').fill('');await page.locator('#hsh-plan-media').click();const picked=page.locator('#hsh-plan-new-photo');assert.ok(await picked.isVisible());assert.equal(await picked.locator('[name=id]').inputValue(),'media:702');assert.equal(await picked.locator('[name="fields[purpose]"]').inputValue(),'hold');assert.equal(await picked.locator('[name="fields[rights]"]').inputValue(),'unknown');checks++;
const download=page.waitForEvent('download');await page.locator('#hsh-plan-export').click();assert.match((await download).suggestedFilename(),/管理表/);checks++;
await page.screenshot({path:'/tmp/planner-admin-1440.png',fullPage:false});
await page.setViewportSize({width:390,height:900});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true);checks++;
await page.screenshot({path:'/tmp/planner-admin-390.png',fullPage:false});
assert.deepEqual(errors,[]);checks++;console.log(JSON.stringify({passed:checks,failed:0,context:'PHP-rendered admin fixture; standard WordPress media selection mocked; no live upload performed'}));
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
