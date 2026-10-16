const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const {spawn}=require('node:child_process');
const fs=require('node:fs'),path=require('node:path');
const tmp=fs.mkdtempSync('/tmp/paperdesk-other-'),root=path.resolve(__dirname,'..'),base='http://127.0.0.1:8019/';
const password='local-test-password-only';let processHandle,browser,passed=0;const errors=[];
async function start(mock){processHandle=spawn('python',['tests/live_fixture.py','--port','8019','--db',path.join(tmp,'jobs.db'),...(mock?['--mock-classifier']:[])],{cwd:root,env:{...process.env,APP_ACCESS_PASSWORD:password},stdio:'ignore'});for(let i=0;i<100;i++){try{if((await fetch(base+'health')).ok)return;}catch{}await new Promise(r=>setTimeout(r,50))}throw Error('startup failed')}
async function stop(){if(processHandle){processHandle.kill();await new Promise(r=>processHandle.once('exit',r));processHandle=null}}
async function test(name,f){await f();passed++;console.log('PASS '+name)}
(async()=>{try{await start(false);browser=await chromium.launch({executablePath:'/usr/bin/chromium',headless:true,args:['--no-sandbox']});const context=await browser.newContext({httpCredentials:{username:'user',password}});const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
await test('公開時は認証なしで製品・ジョブにアクセスできない',async()=>{assert.equal((await fetch(base+'api/config')).status,401);assert.equal((await fetch(base+'api/research/jobs')).status,401)});
await test('他成分の入力画面と不足設定を正しく表示',async()=>{await page.goto(base);await page.locator('#product-form').waitFor();assert.match(await page.locator('.topbar').innerText(),/サーバー処理|PubMed/);assert.match(await page.locator('.error-banner').innerText(),/AI接続の設定待ち/);assert.doesNotMatch(await page.locator('#main').innerText(),/7工程のデモ処理/)});
await test('未知の日本語をゼロ件と偽らず入力エラー',async()=>{await page.locator('#ingredient-0').fill('未知の成分名');await page.locator('#run-demo').click();await page.locator('#form-error').filter({hasText:'英語'}).waitFor();assert.doesNotMatch(page.url(),/#\/live\//)});
await test('日本語と英語の同一成分を二重計上しない',async()=>{await page.locator('#ingredient-0').fill('エピカテキン');await page.locator('#add-ingredient').click();await page.locator('#ingredient-1').fill('epicatechin');await page.locator('#run-demo').click();await page.locator('#form-error').filter({hasText:'重複'}).waitFor()});
await test('エピカテキンとクルクミンのジョブを受け付ける（取得は模擬）',async()=>{await page.locator('#product-name').fill('他成分の動作テスト');await page.locator('#ingredient-1').fill('クルクミン');await page.locator('#run-demo').click();await page.waitForURL(/#\/live\//);await page.locator('#live-retry').waitFor({timeout:15000});assert.match(await page.locator('.chips').innerText(),/エピカテキン/);assert.match(await page.locator('.chips').innerText(),/クルクミン/)});
const job=page.url().split('/').pop();
await test('再起動後も他成分の進捗を保持しZIPを出力（分類は模擬）',async()=>{await stop();await start(true);await page.reload();await page.locator('#live-retry').click();await page.getByRole('link',{name:'結果ZIPをダウンロード'}).waitFor({timeout:15000});const r=await context.request.get(base+'api/research/jobs/'+job+'/archive');assert.equal(r.status(),200);const data=await r.body();assert.equal(data.subarray(0,2).toString(),'PK');assert.equal(await page.locator('#live-results .paper-card').count(),4)});
for(const width of [1440,768,390,320])await test('他成分結果の画面幅 '+width,async()=>{await page.setViewportSize({width,height:900});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true)});
await test('画面で履歴と入力を行き来できる',async()=>{await page.goto(base+'#/history');await page.getByRole('link',{name:'他成分の動作テスト'}).click();await page.locator('#live-results').waitFor();await page.getByRole('link',{name:'← 成分入力へ戻る'}).click();await page.locator('#product-form').waitFor();assert.match(await page.locator('.topbar').innerText(),/サーバー処理|PubMed/)});
await test('JavaScript例外なし',async()=>assert.deepEqual(errors,[]));
console.log(JSON.stringify({passed,failed:0}));
}finally{await browser?.close();await stop();fs.rmSync(tmp,{recursive:true,force:true})}})().catch(e=>{console.error(e);process.exitCode=1});
