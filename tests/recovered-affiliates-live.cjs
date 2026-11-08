// Read-only live verification. Never click ads or submit forms.
const {chromium}=require('/opt/codex/runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const {execFile}=require('node:child_process'),{promisify}=require('node:util'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
(async()=>{
 const root=path.join(__dirname,'..'),catalog=JSON.parse(fs.readFileSync(path.join(root,'hirogaru-home/affiliate-articles.json'))),slugs=['aigan-dobutsu-course','acana-orijen-cat-food-guide','linx-as-store'],rows=catalog.filter(a=>slugs.includes(a.slug)),cache=new Map(),errors=[],dir='/tmp/hsh-recovered4-20261108/screenshots';fs.mkdirSync(dir,{recursive:true});
 const browser=await chromium.launch({executablePath:'/usr/bin/chromium',headless:true,args:['--no-sandbox']}),p=await browser.newPage({reducedMotion:'reduce'});let checks=0;
 p.on('pageerror',e=>errors.push(e.message));
 await p.route('**/*',async route=>{
  assert.ok(['GET','HEAD'].includes(route.request().method()));const u=new URL(route.request().url());
  if(u.origin!=='https://hirogarushumi.com')return route.fulfill({status:204,body:''});
  if(route.request().resourceType()==='document')u.searchParams.set('hsh_verify','recovered1381');
  if(!cache.has(u.href))cache.set(u.href,promisify(execFile)('python',[path.join(root,'tools/public-page-fetch.py'),u.href],{maxBuffer:24000000}).then(x=>JSON.parse(x.stdout)));
  const r=await cache.get(u.href);return route.fulfill({status:r.status,contentType:r.type||'text/plain',body:Buffer.from(r.body||'','base64')});
 });
 try{
  for(const width of [1440,768,390,320]){
   await p.setViewportSize({width,height:950});
   for(const a of rows){
    const res=await p.goto('https://hirogarushumi.com/'+a.slug+'/',{waitUntil:'domcontentloaded',timeout:90000});assert.equal(res.status(),200);assert.equal(await p.locator('h1').count(),1);assert.equal(await p.locator('h1').innerText(),a.title);
    assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),a.slug+' overflow '+width);
    assert.equal(await p.locator('.hsh-article-visual img').first().evaluate(i=>getComputedStyle(i).objectFit),'contain');
    const images=p.locator('.hsh-inline-photo img');assert.equal(await images.count(),2);
    await images.evaluateAll(async list=>{for(const i of list){i.loading='eager';await i.decode();if(!i.naturalWidth)throw Error('Broken official image');}});
    const cta=p.locator('.hsh-affiliate-cta');const href=a.content.match(/<a href="(https:\/\/px.a8.net[^\"]+)"/)[1];const pixel=a.content.match(/src="(https:\/\/www\d+.a8.net[^\"]+)"/)[1];
    assert.equal(await cta.locator('a').getAttribute('href'),href);assert.equal(await cta.locator('img').getAttribute('src'),pixel);assert.ok((await cta.locator('a').getAttribute('rel')).includes('sponsored'));assert.equal(await p.locator('.hsh-ad-notice').count(),1);
    assert.doesNotMatch(await p.locator('.entry-content').innerText(),/未使用|使ったことがない|Undefined array key|Fatal error/);
    const note=await p.locator('.hsh-buying-note').innerText();assert.ok(note.includes(a.purchase_checks));
    if(a.slug==='aigan-dobutsu-course'){assert.match(note,/認定登録料/);assert.doesNotMatch(note,/猫の体格|定期購入/);assert.match(await p.locator('.entry-content table').first().innerText(),/40,000円/);}
    if(a.slug==='linx-as-store'){assert.match(note,/20歳以上/);assert.doesNotMatch(note,/猫|サプリ|定期購入/);assert.match(await p.locator('.hsh-product-related').innerText(),/グレンリベット/);}
    if(width===390){await p.locator('.entry-content>.hsh-answer').scrollIntoViewIfNeeded();await p.screenshot({path:dir+'/'+a.slug+'-intro.png'});await p.locator('.hsh-buying-note').scrollIntoViewIfNeeded();await p.screenshot({path:dir+'/'+a.slug+'-buying.png'});}
    checks++;
   }
  }
  assert.deepEqual(errors,[]);console.log(JSON.stringify({passed:checks,failed:0,widths:[1440,768,390,320],scope:slugs,affiliate_links_clicked:0,forms_submitted:0}));
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
