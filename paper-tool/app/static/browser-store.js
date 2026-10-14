/* Free static demo adapter. No remote storage, PubMed, AI, or API keys. */
'use strict';
(() => {
    const instance = crypto.randomUUID();
    let database, configuration;
    const message = 'このブラウザに保存できません。プライベートモードや保存容量を確認してください。入力は画面に残っています。';
    const clone = value => JSON.parse(JSON.stringify(value));
    const now = () => new Date().toISOString();
    const normal = s => s.normalize('NFKC').trim().replace(/\s+/gu,' ').toLocaleLowerCase();
    function validInput(data) {
        if (!data || typeof data !== 'object' || Array.isArray(data) || typeof data.name !== 'string' || data.name.length > 120 || !Array.isArray(data.ingredients) || !data.ingredients.length || data.ingredients.length > 5) throw new Error('保存データの入力形式が正しくありません。');
        if (data.ingredients.some(v => typeof v !== 'string' || !v.trim() || v.length > 120 || /[\x00-\x1f]/u.test(v))) throw new Error('成分名は空白以外の120文字以内で入力してください。');
        if (new Set(data.ingredients.map(normal)).size !== data.ingredients.length) throw new Error('同じ成分が重複しています。');
        return {name:data.name.trim(),ingredients:data.ingredients.map(v=>v.trim())};
    }
    async function config() {
        if (!configuration) {
            try {
                const response = await fetch('/config.json');
                if (!response.ok) throw new Error();
                const data = await response.json();
                if (data.mode !== 'demo' || data.taxonomy.length !== 51 || data.taxonomy_version !== 'user-51-v1') throw new Error();
                configuration = data;
            } catch { throw new Error('デモ設定を読み込めません。接続を確認して再試行してください。'); }
        }
        return configuration;
    }
    function open() {
        if (!database) database = new Promise((resolve,reject) => {
            const request = indexedDB.open('paperdesk-free-v1',1);
            request.onupgradeneeded = () => {
                request.result.createObjectStore('products',{keyPath:'id'});
                request.result.createObjectStore('jobs',{keyPath:'id'});
            };
            request.onsuccess = () => {
                request.result.onversionchange = () => request.result.close();
                resolve(request.result);
            };
            request.onerror = () => reject(new Error(message));
            request.onblocked = () => reject(new Error('別のタブを閉じてから保存を再試行してください。'));
        });
        return database;
    }
    async function read(store, id) {
        const db = await open();
        return new Promise((resolve,reject) => {
            const transaction = db.transaction(store,'readonly');
            const request = id ? transaction.objectStore(store).get(id) : transaction.objectStore(store).getAll();
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(new Error(message));
        });
    }
    async function write(store, value, remove = false) {
        const db = await open();
        return new Promise((resolve,reject) => {
            const transaction = db.transaction(store,'readwrite');
            try {
                if (remove) transaction.objectStore(store).delete(value);
                else transaction.objectStore(store).put(value);
            } catch {transaction.abort();reject(new Error(message));return;}
            transaction.oncomplete = () => resolve(value);
            transaction.onabort = transaction.onerror = () => reject(new Error(message));
        });
    }
    async function buildResult(snapshot) {
        const cfg = await config();
        const papers = snapshot.ingredients.flatMap((ingredient,i) => cfg.demo_template.papers.map((template,j) => {
            const p = clone(template);
            p.id = `demo-${i+1}-${j+1}`; p.ingredient = ingredient;
            p.title = `デモ項目 ${j+1}：${ingredient}の研究サンプル（架空）`;
            p.pubmed_url = 'https://pubmed.ncbi.nlm.nih.gov/?term='+encodeURIComponent(ingredient);
            return p;
        }));
        const summary = Object.fromEntries(Object.entries(cfg.demo_template.summary).map(([key,value]) => [key,value*snapshot.ingredients.length]));
        return {is_demo:true,taxonomy_version:cfg.taxonomy_version,papers,summary,artifact:{status:'not_implemented',download_url:null}};
    }
    async function tick(job) {
        if (!job) throw new Error('処理履歴が見つかりません。');
        if (job.status==='running' || job.status==='queued') {
            const timestamp = Date.now();
            if (job.instance !== instance) {
                // Reopening starts from the persisted checkpoint, not elapsed closed-tab time.
                job.instance = instance; job.last_tick = timestamp; job.status='running';
            } else if (timestamp-job.last_tick >= 800) {
                job.completed_steps=Math.min(7,job.completed_steps+1); job.last_tick=timestamp;
                job.processed_count=job.completed_steps>=3 ? job.snapshot.ingredients.length*6 : 0;
                if (job.completed_steps===7) {job.status='completed';job.result=await buildResult(job.snapshot);}
            }
            job.updated_at=now(); await write('jobs',job);
        }
        return job;
    }
    async function request(path, method='GET', body) {
        if(path==='/config' && method==='GET') return config();
        if(path==='/products' && method==='GET') return {products:(await read('products')).sort((a,b)=>b.updated_at.localeCompare(a.updated_at))};
        if(path==='/jobs' && method==='GET') return {jobs:(await read('jobs')).sort((a,b)=>b.created_at.localeCompare(a.created_at)).map(j=>({...j,result:j.result?{summary:j.result.summary,is_demo:true}:null}))};
        if(path.startsWith('/products/')) {
            const id=path.split('/').pop(); const product=await read('products',id);
            if(!product) throw new Error('製品が見つかりません。');
            if(method==='GET') return product;
            if(method==='DELETE') {await write('products',id,true);return {deleted:true};}
            if(method==='PUT') return write('products',{...product,...validInput(body),updated_at:now()});
        }
        if(path.startsWith('/jobs/') && method==='GET') return tick(await read('jobs',path.split('/').pop()));
        if(path==='/products' && method==='POST') return write('products',{...validInput(body),id:crypto.randomUUID(),created_at:now(),updated_at:now()});
        if(path==='/jobs' && method==='POST') return write('jobs',{id:crypto.randomUUID(),snapshot:validInput(body),is_demo:true,status:'running',completed_steps:0,processed_count:0,result:null,error:null,created_at:now(),updated_at:now(),instance,last_tick:Date.now()});
        throw new Error('この操作は利用できません。');
    }
    async function exportData() {
        return {format:'paperdesk-browser-backup',version:1,taxonomy_version:'user-51-v1',exported_at:now(),products:await read('products'),jobs:await read('jobs')};
    }
    async function importData(data) {
        if(!data || data.format!=='paperdesk-browser-backup' || data.version!==1 || data.taxonomy_version!=='user-51-v1' || !Array.isArray(data.products) || !Array.isArray(data.jobs) || data.products.length>1000 || data.jobs.length>1000) throw new Error('PaperDeskのバックアップファイルではありません。現在のデータは変更していません。');
        const validID=id=>typeof id==='string' && /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(id);
        const products=[],jobs=[];
        for(const p of data.products) {
            if(!validID(p.id)) throw new Error('バックアップの製品IDが不正です。');
            products.push({...validInput(p),id:p.id,created_at:now(),updated_at:now()});
        }
        for(const j of data.jobs) {
            if(!validID(j.id) || !['running','queued','completed','failed'].includes(j.status) || !Number.isInteger(j.completed_steps) || j.completed_steps<0 || j.completed_steps>7 || (j.status==='completed' && j.completed_steps!==7)) throw new Error('バックアップの履歴形式が不正です。');
            const snapshot=validInput(j.snapshot);
            // Ignore imported URLs/HTML/results and regenerate safe synthetic fixtures.
            jobs.push({id:j.id,snapshot,is_demo:true,status:j.status,completed_steps:j.completed_steps,processed_count:j.completed_steps>=3?snapshot.ingredients.length*6:0,result:j.status==='completed'?await buildResult(snapshot):null,error:j.status==='failed'?'デモ処理が中断されました。入力を再利用してください。':null,created_at:now(),updated_at:now(),instance:'restored',last_tick:Date.now()});
        }
        if(new Set(products.map(p=>p.id)).size!==products.length || new Set(jobs.map(j=>j.id)).size!==jobs.length) throw new Error('バックアップに重複したIDがあります。');
        const db=await open();
        return new Promise((resolve,reject)=>{
            const transaction=db.transaction(['products','jobs'],'readwrite');
            try {
                for(const [name,items] of [['products',products],['jobs',jobs]]) {
                    const store=transaction.objectStore(name);store.clear();items.forEach(item=>store.put(item));
                }
            }catch{transaction.abort();reject(new Error('復元できませんでした。変更は取り消されています。'));return;}
            transaction.oncomplete=()=>resolve();
            transaction.onabort=transaction.onerror=()=>reject(new Error('復元できませんでした。変更は取り消されています。'));
        });
    }
    window.BrowserStore=Object.freeze({request,exportData,importData});
})();
