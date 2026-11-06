const {chromium}=require('/opt/codex/runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs=require('fs'),path=require('path'),assert=require('node:assert/strict'),{execFile}=require('node:child_process'),{promisify}=require('node:util');
(async()=>{
 const preview=process.env.HSH_DESIGN_PREVIEW==='1',base='https://hirogarushumi.com/',root=path.join(__dirname,'..');
 const browser=await chromium.launch({executablePath:'/usr/bin/chromium',headless:true,args:['--no-sandbox']});const p=await browser.newPage(),cache=new Map(),errors=[];let count=0;
 p.on('pageerror',e=>errors.push(e.message));
 await p.route('**/*',async route=>{
  const u=new URL(route.request().url());if(u.origin!==new URL(base).origin)return route.fulfill({status:204,body:''});
  if(preview&&u.pathname.endsWith('/assets/palette.css'))return route.fulfill({status:200,contentType:'text/css',body:fs.readFileSync(path.join(root,'hirogaru-home/assets/palette.css'))});
  if(!cache.has(u.href))cache.set(u.href,promisify(execFile)('python',[path.join(root,'tools/public-page-fetch.py'),u.href],{maxBuffer:24000000}).then(r=>JSON.parse(r.stdout)));
  const result=await cache.get(u.href);let body=Buffer.from(result.body,'base64');
  if(preview&&result.type?.includes('text/html'))body=Buffer.from(body.toString().replace('</head>','<link rel="stylesheet" href="/wp-content/plugins/hirogaru-home/assets/palette.css"></head>'));
  return route.fulfill({status:result.status,contentType:result.type||'text/plain',body});
 });
 const pages=['','category/cats','category/whisky','category/it-freelance','mognyan-cat-food-guide','essential-jaguar-cat-food','mcadams-cat-chicken-turkey','csv-import-seven-checks'];
 try{
  for(const width of [1440,1024,768,390,320]){
   await p.setViewportSize({width,height:1000});
   for(const slug of pages){
    const response=await p.goto(base+(slug?slug+'/':''),{waitUntil:'domcontentloaded',timeout:90000});assert.equal(response.status(),200);
    assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),slug+' overflow '+width);
    assert.equal(await p.locator('body').evaluate(e=>getComputedStyle(e).backgroundColor),'rgb(242, 245, 241)');
    assert.equal(await p.locator('.hsh-reader-header').evaluate(e=>getComputedStyle(e).backgroundColor),'rgb(255, 255, 255)');
    const banner=p.locator(slug.startsWith('category/')?'.hsh-category-intro':'.hsh-article-heading').first();
    if(await banner.count())assert.equal(await banner.evaluate(e=>getComputedStyle(e).backgroundColor),'rgb(24, 60, 52)');
    const cards=p.locator('.hsh-post-grid>article>a');if(await cards.count()){
     assert.equal(await cards.first().evaluate(e=>getComputedStyle(e).backgroundColor),'rgb(255, 255, 255)');
     const contrast=await cards.first().evaluate(e=>{
      const text=e.querySelector('h2,h3');const rgb=s=>s.match(/[\d.]+/g).slice(0,3).map(Number).map(v=>{v/=255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4});
      const lum=s=>rgb(s).reduce((n,v,i)=>n+v*[.2126,.7152,.0722][i],0),a=lum(getComputedStyle(text).color),b=lum(getComputedStyle(e).backgroundColor);return(Math.max(a,b)+.05)/(Math.min(a,b)+.05);
     });assert.ok(contrast>=4.5,'card heading contrast '+contrast);
    }
    if(!preview&&slug.includes('cat-food'))assert.ok((await p.locator('h1').innerText()).startsWith('【ご紹介】'));
    if([1440,390].includes(width)&&['','category/cats','mognyan-cat-food-guide'].includes(slug))await p.screenshot({path:'/tmp/hsh-design-20261106/'+(preview?'preview-':'live-')+(slug.replaceAll('/','-')||'home')+'-'+width+'.png',fullPage:false});
    count++;console.log('CHECK',width,slug||'home');
   }
  }
  assert.deepEqual(errors,[]);console.log(JSON.stringify({passed:count,failed:0,preview,viewports:[1440,1024,768,390,320],advertiser_clicks:0}));
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
