'use strict';
const browserMode = document.documentElement.dataset.storage === 'browser';
const main = document.querySelector('#main');
const state = {config: null, products: [], jobs: [], timer: null, version: 0, saving: false, dirty: false, draftRoute: '', draft: {name: '', ingredients: ['']}, editId: null};
const labels = {adopted: '採用', excluded: '除外', review: '要確認', completed: '完了', running: '処理中', queued: '待機中', failed: 'エラー'};
const humanLabels = {effective: 'ヒト試験・効果あり（デモ判定）', human_no_effect: 'ヒト試験・効果なし（デモ判定）', non_human: 'ヒト試験ではない（デモ判定）', unclear: 'ヒト試験・要確認（デモ判定）'};
const escapeHTML = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const date = value => new Date(value).toLocaleString('ja-JP', {month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'});
const productName = name => name || '製品名未設定';
const chips = ingredients => `<div class="chips">${ingredients.map(i => `<span class="chip">${escapeHTML(i)}</span>`).join('')}</div>`;
const badge = status => `<span class="pill ${status}">${labels[status] || escapeHTML(status)}</span>`;
const notice = (extra = '') => `<div class="demo-notice"><span class="notice-icon" aria-hidden="true">ⓘ</span><p><strong>デモモード</strong> — 実際の論文検索・分析は行いません。表示される項目や数値は架空のサンプルです。${extra}</p></div>`;
const heading = (eyebrow, title, subtitle, action = '') => `<div class="page-heading"><div><p class="eyebrow">${eyebrow}</p><h1>${escapeHTML(title)}</h1><p class="subtitle">${subtitle}</p></div>${action}</div>`;
let toastTimeout;
function toast(message) {
    const node = document.querySelector('#toast'); node.textContent = message; node.hidden = false;
    clearTimeout(toastTimeout); toastTimeout = setTimeout(() => node.hidden = true, 3500);
}
async function api(path, method = 'GET', body) {
    if (browserMode) return window.BrowserStore.request(path, method, body);
    const controller = new AbortController(); const timeout = setTimeout(() => controller.abort(), 10000);
    try {
        const response = await fetch(`/api${path}`, {method, headers: body ? {'Content-Type':'application/json'} : {}, body: body ? JSON.stringify(body) : undefined, signal: controller.signal});
        let data; try { data = await response.json(); } catch { throw new Error('サーバーの応答を読み取れません。再試行してください。'); }
        if (!response.ok) throw new Error(data.error || '操作を完了できませんでした。');
        return data;
    } catch (error) {
        if (error.name === 'AbortError') throw new Error('通信がタイムアウトしました。保存・開始が反映されたか、製品一覧や履歴で確認してください。');
        if (error instanceof TypeError) throw new Error('サーバーに接続できません。入力はこの画面に残っています。接続を確認して再試行してください。');
        throw error;
    } finally { clearTimeout(timeout); }
}
function errorBanner(message) { return `<div class="error-banner" role="alert">${escapeHTML(message)}</div>`; }
function showError(message, target = '#form-error') {
    const node = document.querySelector(target); if (node) node.innerHTML = errorBanner(message); else toast(message);
}
function navigate(hash) { if (location.hash === hash) route(); else location.hash = hash; }
function updateCount() { document.querySelector('#product-count').textContent = state.products.length; }
async function refreshProducts() { const data = await api('/products'); state.products = data.products; updateCount(); }
function bindNavigation(section) { document.querySelector('.topbar .demo-pill').textContent = section==='research' ? '提供データ' : state.config?.research_engine_available ? 'PubMed検索・AI仕分け' : state.config?.provided_data_enabled ? '提供資料を利用' : 'デモモード'; document.querySelectorAll('[data-nav]').forEach(a => a.classList.toggle('active', a.dataset.nav === section)); }
function normalize(value) { return value.normalize('NFKC').trim().replace(/\s+/gu, ' ').toLocaleLowerCase(); }
function inputError() {
    if (state.draft.name.length > 120) return '製品名は120文字以内で入力してください。';
    const values = state.draft.ingredients.map(s => s.trim());
    if (!values.length || !values[0]) return '必須の成分名を入力してください。';
    if (state.draft.ingredients.some((v,i) => i > 0 && v.length > 0 && !v.trim())) return '成分名に空白だけを入力することはできません。';
    if (values.some(v => v.length > 120)) return '成分名は120文字以内で入力してください。';
    if (values.some(v => /[\x00-\x1f]/u.test(v))) return '成分名に改行や制御文字は使用できません。';
    if (new Set(values.filter(Boolean).map(normalize)).size !== values.filter(Boolean).length) return '同じ成分が重複しています。入力を見直してください。';
    return '';
}
function inputSnapshot() { return {name: state.draft.name.trim(), ingredients: state.draft.ingredients.map(i => i.trim()).filter(Boolean)}; }
function persistDraft() {
    try { if (state.dirty) state.draftRoute = location.hash || '#/new'; sessionStorage.setItem('paperdesk-draft-v1', JSON.stringify({draft: state.draft, editId: state.editId, dirty: state.dirty, draftRoute: state.draftRoute})); }
    catch { /* Server product saving remains available when browser storage is disabled. */ }
}
function loadDraft() {
    try {
        const value = JSON.parse(sessionStorage.getItem('paperdesk-draft-v1') || 'null');
        if (value && value.draft && typeof value.draft.name === 'string' && value.draft.name.length <= 120 && Array.isArray(value.draft.ingredients) && value.draft.ingredients.length >= 1 && value.draft.ingredients.length <= 5 && value.draft.ingredients.every(v => typeof v === 'string' && v.length <= 120)) {
            state.draft = value.draft; state.editId = typeof value.editId === 'string' ? value.editId : null; state.dirty = value.dirty === true; state.draftRoute = typeof value.draftRoute === 'string' ? value.draftRoute : '';
        }
    } catch { try { sessionStorage.removeItem('paperdesk-draft-v1'); } catch {} }
}
function validationFeedback() {
    const error = inputError();
    const names = state.draft.ingredients.map(normalize);
    const duplicates = names.filter((name, index) => name && names.indexOf(name) !== index);
    document.querySelectorAll('.ingredient-field').forEach((field, index) => {
        const duplicate = duplicates.includes(names[index]); field.querySelector('input').classList.toggle('invalid', duplicate);
        field.querySelector('.field-error').textContent = duplicate ? '同じ成分が入力されています。' : '';
    });
    document.querySelector('#save-product').disabled = !!error || state.saving;
    document.querySelector('#run-demo').disabled = !!error || state.saving;
    document.querySelector('#duplicate-warning').innerHTML = duplicates.length ? errorBanner('同じ成分が重複しています。入力を見直してください。') : '';
    document.querySelector('#ingredient-count').textContent = `${state.draft.ingredients.length} / 5`;
}
function renderIngredients(focusIndex) {
    const container = document.querySelector('#ingredient-fields');
    container.innerHTML = state.draft.ingredients.map((value, index) => `<div class="ingredient-field"><label for="ingredient-${index}">成分名 ${index + 1}${index === 0 ? '<span class="required">必須</span>' : '<span class="optional">任意</span>'}</label><div class="ingredient-row"><input type="text" id="ingredient-${index}" data-index="${index}" value="${escapeHTML(value)}" maxlength="120" placeholder="例：EGCG、ビタミンC" autocomplete="off" aria-describedby="ingredient-error-${index}" ${index === 0 ? 'required' : ''}><button type="button" class="icon-button remove-ingredient" data-index="${index}" aria-label="成分${index + 1}を削除">×</button></div><p class="field-error" id="ingredient-error-${index}"></p></div>`).join('');
    const add = document.querySelector('#add-ingredient'); add.disabled = state.draft.ingredients.length >= 5;
    add.textContent = state.draft.ingredients.length >= 5 ? '最大5種類まで追加できます' : '＋ 成分を追加';
    container.querySelectorAll('input').forEach(input => input.addEventListener('input', () => {
        state.draft.ingredients[Number(input.dataset.index)] = input.value; state.dirty = true; persistDraft(); validationFeedback();
    }));
    container.querySelectorAll('.remove-ingredient').forEach(button => button.addEventListener('click', () => {
        const index = Number(button.dataset.index);
        if (state.draft.ingredients.length === 1) state.draft.ingredients[0] = '';
        else state.draft.ingredients.splice(index, 1);
        state.dirty = true; persistDraft(); renderIngredients(Math.min(index, state.draft.ingredients.length - 1));
    }));
    validationFeedback(); if (focusIndex !== undefined) document.querySelector(`#ingredient-${focusIndex}`).focus();
}
function newForm(title = '成分から、研究を整理する。') {
    bindNavigation('new');
    main.innerHTML = heading('NEW RESEARCH', title, '製品名と成分を入力して、論文仕分けの流れを体験しましょう。') + notice() + `<div class="input-layout"><section class="card"><div class="card-header"><h2><span class="step-number">01</span> 製品・成分を入力</h2><span class="pill demo-pill">最大5成分</span></div><form id="product-form" novalidate><div id="form-error"></div><div class="field"><label for="product-name">製品名<span class="optional">任意</span></label><input id="product-name" type="text" value="${escapeHTML(state.draft.name)}" maxlength="120" placeholder="例：製品A" autocomplete="off"><p class="field-help">未入力でも実行できます。後から名前を付けられます。</p></div><div class="card-header"><h3>対象の成分</h3><small id="ingredient-count">1 / 5</small></div><p class="field-help">日本語・英語どちらでも入力できます（各120文字以内）。</p><div id="ingredient-fields"></div><div id="duplicate-warning"></div><button type="button" id="add-ingredient" class="button secondary add-button">＋ 成分を追加</button><div class="form-actions"><button type="button" id="save-product" class="button secondary">${state.editId ? '変更を保存' : '入力内容を保存'}</button><button type="submit" id="run-demo" class="button primary">論文検索・仕分け開始 <span aria-hidden="true">→</span></button></div><p class="small-note">開始すると、7工程のデモ処理に進みます。</p></form></section><aside class="card guide"><h2>かんたん、3ステップ</h2><ol><li><strong>成分を入力</strong>調べたい成分を最大5種類登録します。</li><li><strong>デモ処理を開始</strong>7つの工程と進捗を確認します。</li><li><strong>結果を確認</strong>成分別・51分類別に表示できます。</li></ol><div class="guide-separator"></div><p>今回は画面と操作のデモ版です。PubMed検索、AI判定、ZIP出力は次の開発段階で追加します。</p><div class="guide-mark">✓ 保存した製品は後から編集できます</div></aside></div>`;
    if(browserMode && state.config.provided_data_enabled){document.querySelector('#run-demo').textContent='論文を確認・ZIP出力 →';document.querySelector('.subtitle').textContent='製品名と成分を入力して、登録済みの実際の論文を確認できます。';document.querySelector('.demo-notice p').innerHTML='<strong>提供資料を利用</strong> — EGCG・カテキンの論文を登録済みです。ファイルの選択は不要です。新規検索・AI判定・独立再チェックは未稼働です。';document.querySelector('.topbar .demo-pill').textContent='提供資料を利用';document.querySelector('.small-note').textContent='入力した成分の論文一覧と結果ZIPへ進みます。';document.querySelector('.guide').innerHTML='<h2>かんたん、3ステップ</h2><ol><li><strong>成分を入力</strong>EGCG・カテキンの実データに対応しています。</li><li><strong>開始</strong>登録済み資料から成分に対応する論文を表示します。</li><li><strong>結果を確認</strong>人間版の分類・要確認論文を確認し、ZIPを保存できます。</li></ol>'; }
    if(!browserMode && state.config.research_engine_available){document.querySelector('.sidebar-note').innerHTML='<span class="status-dot"></span> PubMed検索・AI仕分け<p>論文取得から結果ZIPまで。</p>';document.querySelector('.subtitle').textContent='製品名と成分を入力すると、PubMedから論文を取得します。';document.querySelector('.demo-notice p').innerHTML='<strong>サーバー処理</strong> — AIの費用・承認設定がない場合は、無料の論文取得後に停止します。';document.querySelector('.topbar .demo-pill').textContent='サーバー処理';document.querySelector('.small-note').textContent='取得後に100論文あたりの見積りを表示します。費用上限を承認してからAI仕分けを開始します。';document.querySelector('.field-help').textContent='日本語・英語に対応。日本語名を確認できない成分は英語名で入力してください（各120文字以内）。';document.querySelector('.guide').innerHTML='<h2>使い方</h2><ol><li>製品名と成分を入力</li><li>論文取得後に料金を確認して承認</li><li>初回判定と2回の再チェック後にZIPを保存</li></ol><p>処理中に画面を閉じても、稼働中のサーバーで処理を続けます。</p><p>PubMedのタイトル・抄録を使って判定します。全文が必要な判断は要確認として残します。</p>';if(!state.config.runtime?.ai_ready){const warning=document.createElement('div');warning.className='error-banner';warning.setAttribute('role','alert');warning.textContent='AI接続の設定待ち：'+(state.config.runtime?.missing_settings||[]).join('・')+'。論文取得はできますが、AI仕分けは設定が済むまで停止します。';document.querySelector('#product-form').before(warning);} }
    renderIngredients();
    document.querySelector('#product-name').addEventListener('input', event => { state.draft.name = event.target.value; state.dirty = true; persistDraft(); validationFeedback(); });
    document.querySelector('#add-ingredient').addEventListener('click', () => { if (state.draft.ingredients.length < 5) { state.draft.ingredients.push(''); state.dirty = true; persistDraft(); renderIngredients(state.draft.ingredients.length - 1); } });
    document.querySelector('#save-product').addEventListener('click', saveProduct);
    document.querySelector('#product-form').addEventListener('submit', runDemo);
}
async function saveProduct() {
    if (state.saving) return;
    const error = inputError(); if (error) return showError(error);
    state.saving = true; validationFeedback();
    const button = document.querySelector('#save-product'); button.textContent = '保存しています…';
    try {
        const product = await api(state.editId ? `/products/${state.editId}` : '/products', state.editId ? 'PUT' : 'POST', inputSnapshot());
        state.editId = product.id; state.dirty = false; persistDraft();
        await refreshProducts(); toast('製品を保存しました。');
    } catch (error) { showError(error.message); }
    finally { state.saving = false; if (document.querySelector('#save-product')) { button.textContent = state.editId ? '変更を保存' : '入力内容を保存'; validationFeedback(); } }
}
async function runDemo(event) {
    event.preventDefault(); if (state.saving) return;
    const error = inputError(); if (error) return showError(error);
    state.saving = true; validationFeedback(); document.querySelector('#run-demo').textContent = '準備しています…';
    try {
        if (!browserMode && state.config.research_engine_available) { const job=await api('/research/jobs','POST',inputSnapshot());navigate('#/live/'+job.id);return; }
        if (browserMode && state.config.provided_data_enabled) { await window.ResearchUI.run(inputSnapshot(),state.config); return; }
        const job = await api('/jobs', 'POST', inputSnapshot());
        state.dirty = false; navigate(`#/processing/${job.id}`);
    } catch (error) { showError(error.message); }
    finally { state.saving = false; if (document.querySelector('#run-demo')) { document.querySelector('#run-demo').textContent = state.config.provided_data_enabled ? '論文を確認・ZIP出力 →' : '論文検索・仕分け開始 →'; validationFeedback(); } }
}
function confirmDelete() {
    return new Promise(resolve => {
        const dialog = document.querySelector('#confirm-dialog'); dialog.returnValue = ''; dialog.showModal();
        dialog.addEventListener('close', () => resolve(dialog.returnValue === 'delete'), {once:true});
    });
}
function productsView(query = '') {
    bindNavigation('products');
    main.innerHTML = heading('PRODUCT LIBRARY', '保存した製品', state.config.provided_data_enabled ? '登録した成分を編集したり、同じ条件で論文を確認できます。' : '登録した成分を編集したり、同じ条件でデモを実行できます。', '<a href="#/new" id="fresh-product" class="button">＋ 新しく入力</a>') + `<div id="list-error"></div>${browserMode ? '<div class="backup-actions"><button id="backup-export" class="button secondary small">バックアップを保存</button><button id="backup-import" class="button secondary small">バックアップから復元</button><input id="backup-file" type="file" accept=".json,application/json" hidden></div>' : ''}<div class="search-bar"><input type="search" id="product-search" placeholder="製品名で検索" aria-label="製品名で検索" value="${escapeHTML(query)}"><small id="search-count"></small></div><div id="products-container"></div>`;
    document.querySelector('#fresh-product').addEventListener('click', () => { state.draft = {name:'', ingredients:['']}; state.editId = null; state.dirty = false; persistDraft(); });
    document.querySelector('#product-search').addEventListener('input', event => productCards(event.target.value));
    productCards(query);
    if (browserMode) bindBackup();
}
function productCards(query) {
    const list = state.products.filter(p => productName(p.name).toLocaleLowerCase().includes(query.toLocaleLowerCase()));
    document.querySelector('#search-count').textContent = `${list.length}件`;
    document.querySelector('#products-container').innerHTML = list.length ? `<div class="product-list">${list.map(p => `<article class="card product-card"><h2>${escapeHTML(productName(p.name))}</h2>${chips(p.ingredients)}<p class="product-meta">更新：${date(p.updated_at)}</p><div class="product-actions"><a class="button secondary small" href="#/edit/${p.id}">編集する</a><button class="button small product-run" data-id="${p.id}">${state.config.provided_data_enabled || state.config.research_engine_available ? '仕分け開始' : 'デモを実行'}</button><button class="button text-button small product-delete" data-id="${p.id}" aria-label="${escapeHTML(productName(p.name))}を削除">削除</button></div></article>`).join('')}</div>` : `<section class="card empty-state"><div class="empty-icon" aria-hidden="true">▤</div><h2>${query ? '一致する製品がありません' : 'まだ製品が保存されていません'}</h2><p>${query ? '別の製品名で検索してください。' : '製品と成分を入力して保存すると、ここに表示されます。'}</p>${query ? '' : '<a href="#/new" class="button">最初の製品を入力</a>'}</section>`;
    document.querySelectorAll('.product-delete').forEach(button => button.addEventListener('click', async () => {
        if (!await confirmDelete()) return;
        button.disabled = true;
        try {
            await api(`/products/${button.dataset.id}`, 'DELETE');
            if (state.editId === button.dataset.id) { state.editId = null; persistDraft(); }
            await refreshProducts(); productCards(document.querySelector('#product-search').value); toast('製品を削除しました。処理履歴は残っています。');
        } catch (error) { button.disabled = false; showError(error.message, '#list-error'); }
    }));
    document.querySelectorAll('.product-run').forEach(button => button.addEventListener('click', async () => {
        button.disabled = true;
        const product = state.products.find(p => p.id === button.dataset.id);
        try { if (!browserMode && state.config.research_engine_available) {const job=await api('/research/jobs','POST',{name:product.name,ingredients:product.ingredients});navigate('#/live/'+job.id);return;} if (browserMode && state.config.provided_data_enabled) {await window.ResearchUI.run({name:product.name,ingredients:product.ingredients},state.config);return;} const job = await api('/jobs', 'POST', {name:product.name, ingredients:product.ingredients}); navigate(`#/processing/${job.id}`); }
        catch (error) { button.disabled = false; showError(error.message, '#list-error'); }
    }));
}
function historyView() {
    bindNavigation('history');
    main.innerHTML = heading('RESEARCH HISTORY', '処理履歴', '過去のデモ結果と、処理中の進捗を確認できます。') + notice() + (state.jobs.length ? `<div class="history-list">${state.jobs.map(job => `<article class="card history-card"><div class="history-main"><h2>${escapeHTML(productName(job.snapshot.name))}</h2>${chips(job.snapshot.ingredients)}<small>${date(job.created_at)} · デモ処理 · ${job.completed_steps} / 7工程</small></div>${badge(job.status)}<div class="history-actions"><a class="button secondary small" href="#/${job.status === 'completed' ? 'results' : 'processing'}/${job.id}">${job.status === 'completed' ? '結果を見る' : '進捗を見る'}</a><a class="button text-button small" href="#/reuse/${job.id}">入力を再利用</a></div></article>`).join('')}</div>` : `<section class="card empty-state"><div class="empty-icon" aria-hidden="true">◷</div><h2>まだ処理履歴がありません</h2><p>デモ処理を実行すると、ここに記録されます。</p><a href="#/new" class="button">デモを試す</a></section>`);
}
function progressView(job, connectionError = '') {
    bindNavigation('history');
    const completed = job.completed_steps; const percent = Math.round(completed / 7 * 100);
    const current = job.status === 'completed' ? 'デモ処理が完了しました' : job.status === 'failed' ? '処理を中断しました' : job.status === 'queued' ? 'デモ処理の開始を待っています' : state.config.stages[Math.min(completed,6)];
    main.innerHTML = `<a href="#/history" class="back-link">← 処理履歴に戻る</a>` + heading('DEMO PROCESSING', '研究整理の流れを確認', browserMode ? 'デモはこの画面で進みます。閉じた場合は、履歴から続きを再開できます。' : 'ブラウザを閉じてもデモ処理はサーバー側で続きます。') + notice(' 2回の再チェックも固定のデモ演出です。') + (connectionError ? errorBanner(connectionError) + '<button id="retry-progress" class="button secondary small">接続を再確認</button>' : '') + (job.error ? errorBanner(job.error) : '') + `<div class="progress-layout"><section class="card"><div class="progress-header"><h2 id="current-stage">${escapeHTML(current)}</h2><span class="percentage">${percent}<small>%</small></span></div><div class="progress-track" role="progressbar" aria-label="全体の進捗" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${percent}"><div class="progress-fill" style="width:${percent}%"></div></div><p class="progress-caption" aria-live="polite">${completed} / 7工程完了 · ${labels[job.status]}</p><ol class="stage-list">${state.config.stages.map((stage,index) => `<li class="stage ${index < completed ? 'done' : index === completed && job.status === 'running' ? 'active' : ''}"><span class="stage-icon" aria-hidden="true">${index < completed ? '✓' : index === completed && job.status === 'running' ? '' : index+1}</span><span>${stage}</span><span class="stage-tag">${index < completed ? '完了' : index === completed && job.status === 'running' ? 'デモ処理中' : '未処理'}</span></li>`).join('')}</ol></section><aside class="progress-aside"><section class="card count-card"><dl><div><dt>対象成分数</dt><dd>${job.snapshot.ingredients.length}<small>種類</small></dd></div><div><dt>処理済みサンプル数</dt><dd>${job.processed_count}<small>件</small></dd></div></dl></section><section class="card"><h3>${escapeHTML(productName(job.snapshot.name))}</h3>${chips(job.snapshot.ingredients)}<p class="offline-note">実際の論文は取得していません。</p>${badge(job.status)}</section></aside></div>` + (job.status === 'failed' ? `<div class="form-actions"><a class="button" href="#/reuse/${job.id}">入力を再利用してやり直す</a></div>` : '');
    document.querySelector('#retry-progress')?.addEventListener('click', () => route());
}
function beginPolling(jobId, generation, initial) {
    let previous = initial;
    const poll = async () => {
        if (generation !== state.version) return;
        try {
            const job = await api(`/jobs/${jobId}`);
            if (generation !== state.version) return;
            if (job.status === 'completed') { navigate(`#/results/${jobId}`); return; }
            progressView(job); previous = job;
            if (job.status === 'failed') return;
        } catch (error) { if (generation === state.version) progressView(previous, error.message); }
        if (generation === state.version) state.timer = setTimeout(poll, 650);
    };
    state.timer = setTimeout(poll, 650);
}
function bodyNames(ids) { return ids.map(id => state.config.taxonomy.find(p => p.id === id)?.name || id); }
function paperCard(paper) {
    const final = paper.final_decision;
    return `<article class="card paper-card"><div class="paper-top">${badge(final.status)}<span class="pill demo-pill">架空のサンプル</span></div><h3>${escapeHTML(paper.title)}</h3><p>${escapeHTML(humanLabels[final.human_trial])}</p>${chips(bodyNames(final.body_ids))}<p>${escapeHTML(final.reason)}</p><a class="pubmed-link" href="${escapeHTML(paper.pubmed_url)}" target="_blank" rel="noopener noreferrer">PubMedで成分を検索 ↗</a><p class="small-note">この項目の実在論文リンクではありません。PMIDはありません。</p><details><summary>初回判定と2回の再チェックを見る</summary><div class="decision-grid"><div><h4>初回判定（デモ）</h4>${badge(paper.initial_decision.status)}<p>${escapeHTML(bodyNames(paper.initial_decision.body_ids).join('・') || '分類なし')}</p><p>${escapeHTML(paper.initial_decision.reason)}</p></div>${paper.checks.map(check => `<div><h4>第${check.round}回再チェック（デモ）</h4>${badge(check.decision.status)}<p>${check.changed ? '変更あり' : '変更なし'} · ${escapeHTML(bodyNames(check.decision.body_ids).join('・') || '分類なし')}</p><p>${escapeHTML(check.decision.reason)}</p></div>`).join('')}</div></details></article>`;
}
function resultsView(job) {
    bindNavigation('history');
    const summary = job.result.summary;
    main.innerHTML = `<a href="#/history" class="back-link">← 処理履歴に戻る</a>` + heading('DEMO RESULTS', '仕分け結果', '成分別・51分類別に、デモ結果を確認できます。', '<div class="result-heading-actions"><button class="button secondary" disabled id="download-zip">ZIP一括ダウンロード · 準備中</button><p class="download-note">ファイル生成・ダウンロードは未実装です</p></div>') + notice(' 以下の件数は実際の取得論文数や研究結果ではありません。') + `<section class="result-intro"><h2>${escapeHTML(productName(job.snapshot.name))}</h2>${chips(job.snapshot.ingredients)}</section><div class="summary-grid">${[['取得論文数（デモ）','retrieved',''],['採用論文数（デモ）','adopted','green'],['除外論文数（デモ）','excluded',''],['ヒト試験効果あり（デモ）','human_effective','green'],['要確認論文数（デモ）','review','amber'],['再チェック修正数（デモ）','corrected','']].map(([label,key,color]) => `<div class="stat ${color}"><span>${label}</span><strong>${summary[key]}</strong></div>`).join('')}</div><div class="success-panel">✓ 7工程のデモ処理が完了しました。実際のZIPファイルは生成していません。</div><div class="result-toolbar"><div class="segmented" role="group" aria-label="表示方法"><button id="by-ingredient" class="selected" aria-pressed="true">成分別に見る</button><button id="by-part" aria-pressed="false">51分類別に見る</button></div><div class="filters"><select id="status-filter" aria-label="判定で絞り込み"><option value="all">すべての判定</option><option value="adopted">採用</option><option value="excluded">除外</option><option value="review">要確認</option></select><select id="part-filter" aria-label="分類で絞り込み" hidden><option value="all">すべての51分類</option>${state.config.taxonomy.map(p => `<option value="${p.id}">${escapeHTML(p.name)}</option>`).join('')}</select></div></div><p class="field-help" id="taxonomy-note" hidden>ご指定の51分類です。複数分類の項目は、それぞれの分類に重複表示されます。</p><div id="result-list"></div><div class="form-actions"><a href="#/reuse/${job.id}" class="button secondary">この入力を再利用</a><a href="#/history" class="button secondary">処理履歴を見る</a></div>`;
    let grouping = 'ingredient';
    const renderList = () => {
        const status = document.querySelector('#status-filter').value;
        const selectedPart = document.querySelector('#part-filter').value;
        const papers = job.result.papers.filter(p => status === 'all' || p.final_decision.status === status);
        if (grouping === 'ingredient') {
            document.querySelector('#result-list').innerHTML = job.snapshot.ingredients.map(i => { const list = papers.filter(p => p.ingredient === i); return `<h2 class="group-heading">${escapeHTML(i)}<span>${list.length}件</span></h2><div class="result-list">${list.length ? list.map(paperCard).join('') : '<p class="result-empty">この条件に一致するサンプルはありません。</p>'}</div>`; }).join('');
        } else {
            const groups = state.config.taxonomy.filter(p => selectedPart === 'all' || p.id === selectedPart);
            document.querySelector('#result-list').innerHTML = groups.map(part => { const list = papers.filter(p => p.final_decision.body_ids.includes(part.id)); return `<section class="part-group"><h2 class="group-heading">${Number(part.id.split('-')[1])}. ${escapeHTML(part.name)}<span>${list.length}件</span></h2><div class="result-list">${list.length ? list.map(paperCard).join('') : '<p class="result-empty">この分類のサンプルはありません。</p>'}</div></section>`; }).join('');
            if (selectedPart === 'all') {
                const unclassified = papers.filter(p => !p.final_decision.body_ids.length);
                document.querySelector('#result-list').insertAdjacentHTML('beforeend', `<h2 class="group-heading">分類なし・除外<span>${unclassified.length}件</span></h2><div class="result-list">${unclassified.map(paperCard).join('')}</div>`);
            }
        }
    };
    document.querySelector('#by-ingredient').addEventListener('click', () => { grouping='ingredient'; toggleGrouping(); });
    document.querySelector('#by-part').addEventListener('click', () => { grouping='part'; toggleGrouping(); });
    function toggleGrouping() {
        for (const [id, active] of [['by-ingredient',grouping==='ingredient'],['by-part',grouping==='part']]) { const button=document.getElementById(id); button.classList.toggle('selected',active); button.setAttribute('aria-pressed',String(active)); }
        document.querySelector('#part-filter').hidden=grouping!=='part'; document.querySelector('#taxonomy-note').hidden=grouping!=='part'; renderList();
    }
    document.querySelector('#status-filter').addEventListener('change',renderList); document.querySelector('#part-filter').addEventListener('change',renderList); renderList();
}
async function route() {
    clearTimeout(state.timer); const generation = ++state.version;
    const [, section='new', id] = (location.hash || '#/new').split('/');
    main.setAttribute('aria-busy','true');
    try {
        if (!state.config) state.config = await api('/config');
        if (generation !== state.version) return;
        if (section === 'live') { await window.ResearchUI.renderLive(main,state.config,id); }
        else if (section === 'research-result') { await window.ResearchUI.renderRun(main,state.config,id); }
        else if (section === 'research') { await window.ResearchUI.render(main,state.config); }
        else if (section === 'new') { newForm(); }
        else if (section === 'products') { await refreshProducts(); if(generation===state.version) productsView(); }
        else if (section === 'edit') {
            const product = await api(`/products/${id}`); if(generation!==state.version) return;
            if (!(state.dirty && state.draftRoute === location.hash && state.editId === product.id)) { state.editId=product.id; state.draft={name:product.name,ingredients:product.ingredients}; state.dirty=false; persistDraft(); } newForm('保存した製品を編集');
        } else if (section === 'reuse') {
            const job = await api(`/jobs/${id}`); if(generation!==state.version) return;
            if (!(state.dirty && state.draftRoute === location.hash)) { state.editId=null; state.draft={name:job.snapshot.name,ingredients:[...job.snapshot.ingredients]}; state.dirty=false; persistDraft(); } newForm('履歴の入力を再利用');
        } else if (section === 'history') {
            const data=await api('/jobs'); if(generation!==state.version)return; state.jobs=data.jobs; historyView();if((state.config.provided_data_enabled || state.config.research_engine_available) && !state.jobs.length)main.innerHTML=heading('RESEARCH HISTORY','処理履歴',state.config.research_engine_available?'論文取得・AI仕分けの進捗と結果を確認できます。':'提供資料を利用した結果を再度確認できます。');
            if(!browserMode && state.config.research_engine_available){const live=await api('/research/jobs');if(generation!==state.version)return;const list=document.createElement('section');list.className='card';list.innerHTML='<h2>サーバー処理の履歴</h2>'+live.jobs.map(j=>`<p><a href="#/live/${j.id}">${escapeHTML(productName(j.snapshot.name))}</a> · ${escapeHTML(j.snapshot.ingredients.join('・'))} · ${escapeHTML(({queued:'待機中',running:'処理中',completed:'完了',failed:'中断',awaiting_approval:'費用の確認・承認待ち'})[j.status]||j.status)}</p>`).join('');main.append(list);}
            if(browserMode && state.config.provided_data_enabled){const runs=await window.ResearchUI.history();if(generation!==state.version)return;const section=document.createElement('section');section.className='card';section.innerHTML='<h2>提供資料の処理履歴</h2>'+runs.map(j=>`<p><a href="#/research-result/${j.id}">${escapeHTML(productName(j.snapshot.name))}</a> · ${escapeHTML(j.snapshot.ingredients.join('・'))}</p>`).join('');main.append(section);if(!runs.length && !state.jobs.length)section.innerHTML='<p>まだ処理履歴がありません。</p><a href="#/new" class="button">成分を入力する</a>';}
        } else if (section === 'processing' || section === 'results') {
            const job=await api(`/jobs/${id}`); if(generation!==state.version)return;
            if(job.status==='completed') { if(section==='processing') {navigate(`#/results/${id}`); return;} resultsView(job); }
            else { progressView(job); if(job.status!=='failed') beginPolling(id,generation,job); }
        } else {
            main.innerHTML=heading('PAGE NOT FOUND','ページが見つかりません','メニューから画面を選び直してください。')+'<a href="#/new" class="button">新しい仕分けへ</a>';
        }
        window.scrollTo({top:0,behavior:'instant'});
    } catch(error) {
        if(generation!==state.version)return;
        main.innerHTML=heading('CONNECTION','画面を読み込めませんでした',browserMode ? '保存済みの製品や履歴は、このブラウザに保存されています。' : '保存済みの製品や履歴はサーバーに保持されています。')+errorBanner(error.message)+'<button id="retry-page" class="button">再試行</button>';
        document.querySelector('#retry-page').addEventListener('click',route);
    } finally { if(generation===state.version)main.setAttribute('aria-busy','false'); }
}
window.addEventListener('hashchange', route);
if (browserMode) {
    const footer=document.querySelector('.site-footer');
    footer.insertAdjacentHTML('beforebegin','<div class="storage-notice"><strong>無料版 · このブラウザに保存</strong><p>端末間の共有はできません。ブラウザのデータ削除・別のURLへの移動では引き継がれないため、「保存した製品」からバックアップを保存してください。</p></div>');
}
loadDraft(); route(); refreshProducts().catch(() => {});

function bindBackup() {
    document.querySelector('#backup-export').addEventListener('click',async()=>{
        try {
            const data=await window.BrowserStore.exportData();
            const url=URL.createObjectURL(new Blob([JSON.stringify(data,null,2)],{type:'application/json'}));
            const a=document.createElement('a');a.href=url;a.download='PaperDesk-backup-'+new Date().toISOString().slice(0,10)+'.json';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);toast('バックアップファイルを保存しました。');
        } catch(error){showError(error.message,'#list-error');}
    });
    document.querySelector('#backup-import').addEventListener('click',()=>document.querySelector('#backup-file').click());
    document.querySelector('#backup-file').addEventListener('change',async event=>{
        const file=event.target.files[0];if(!file)return;
        try {
            if(file.size>10*1024*1024)throw new Error('バックアップは10MB以内のファイルを指定してください。');
            let data;try{data=JSON.parse(await file.text());}catch{throw new Error('バックアップファイルを読み取れません。現在のデータは変更していません。');}
            const dialog=document.querySelector('#confirm-dialog');
            const previousTitle=document.querySelector('#dialog-title').textContent;const previousDescription=document.querySelector('#dialog-description').textContent;
            document.querySelector('#dialog-title').textContent='バックアップから復元しますか？';document.querySelector('#dialog-description').textContent='現在の製品と履歴を、バックアップの内容に置き換えます。必要なら先にバックアップを保存してください。';
            const confirmButton=dialog.querySelector('[value="delete"]');confirmButton.textContent='復元する';
            const confirmed=await confirmDelete();document.querySelector('#dialog-title').textContent=previousTitle;document.querySelector('#dialog-description').textContent=previousDescription;confirmButton.textContent='削除する';
            if(!confirmed)return;
            await window.BrowserStore.importData(data);state.editId=null;state.draft={name:'',ingredients:['']};state.dirty=false;persistDraft();await refreshProducts();productsView();toast('バックアップから復元しました。');
        }catch(error){showError(error.message,'#list-error');}
        finally{if(document.querySelector('#backup-file'))document.querySelector('#backup-file').value='';}
    });
}
