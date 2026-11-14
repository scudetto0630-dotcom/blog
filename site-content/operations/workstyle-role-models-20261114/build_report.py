#!/usr/bin/env python3
"""Build the offline research report from reviewed observations; no network access."""
import csv
import html
import json
import re
from pathlib import Path

BASE = Path(__file__).resolve().parent
DATA = json.loads((BASE / 'research.json').read_text())
SOURCES = {s['id']: s for s in DATA['sources']}


def references(ids):
    return ' / '.join(f"[{i}：{SOURCES[i]['title']}]({SOURCES[i]['url']})" for i in ids)


def person_markdown(p):
    lines = [f"# {p['name']}の詳細分析", '', f"参照先：[{p['site']}]({p['url']})", '',
             f"主に参考にする部分：{p['reference_role']}", '',
             '## 更新状況と確認範囲', '', p['activity_status'], '', p['latest_observed']['note'], '',
             '根拠：' + references(p['latest_observed']['basis']), '']
    for index, section in enumerate(p['sections'], 1):
        label = {'observation': '観察', 'analysis': '分析', 'application': 'こちらへの応用'}[section['kind']]
        lines.extend([f"## {index}. {section['title']}（{label}）", '', section['text'], '',
                      '根拠：' + references(section['source_ids']), ''])
    lines.extend(['## 記事ごとの組み立てを分解する', '', '完成記事の分析。著者が実際に使った制作工程を断定するものではない。', ''])
    for c in p.get('article_cases', []):
        source = SOURCES[c['source_id']]
        lines.extend([f"### {c['focus']}", '', f"対象：[{source['title']}]({source['url']})", '',
                      '入口：' + c['opening'], '', '展開：' + ' → '.join(c['progression']), '',
                      '読む理由：' + c['why_it_works'], '', '結びと案内：' + c['ending_and_links'], '',
                      'こちらで使うなら：' + c['application'], ''])
    lines.extend(['## 収益の窓口と、実際に分かる範囲', ''])
    for r in p['revenue']:
        lines.extend([f"### {r['type']}", '', f"確認レベル：{r['evidence_level']}", '', r['evidence'], '',
                      '自分で同じ形を採用する場合の作業：' + r['owner_workload'], '',
                      '根拠：' + references(r['source_ids']), ''])
    for heading, key in [('取り入れること', 'adopt'), ('そのまま取り入れないこと', 'avoid'), ('未確認のこと', 'unknown')]:
        lines.extend(['## ' + heading, ''] + ['- ' + x for x in p[key]] + [''])
    lines.extend(['## 今回見たページ', '', '|ID|種類|ページと観察|確認した範囲|', '|---|---|---|---|'])
    for s in DATA['sources']:
        if s['person'] == p['id']:
            lines.append(f"|{s['id']}|{s['kind']}|[{s['title']}]({s['url']})：{s['observation']}|{s['scope']}|")
    lines.extend(['', '[全体資料へ戻る](README.md)', ''])
    return '\n'.join(lines)


def inline(text):
    text = html.escape(text)
    text = re.sub(r'\[([^\]]+)\]\(([^)\s]+)\)', lambda m: '<a href="' + html.escape(html.unescape(m[2]), quote=True) + '">' + m[1] + '</a>', text)
    text = re.sub(r'`([^`]+)`', r'<code>\1</code>', text)
    return text


def markdown(text):
    """Small renderer for this dossier's headings, paragraphs, lists, tables, code."""
    out, para, table, listing = [], [], [], []

    def flush():
        if para:
            out.append('<p>' + inline(' '.join(para)) + '</p>'); para.clear()
        if table:
            rows = [x.strip().strip('|').split('|') for x in table]
            if len(rows) > 1 and all(re.fullmatch(r'\s*:?-+:?\s*', c) for c in rows[1]):
                rows.pop(1)
            head = '<thead><tr>' + ''.join('<th scope="col">' + inline(c.strip()) + '</th>' for c in rows[0]) + '</tr></thead>'
            body = '<tbody>' + ''.join('<tr>' + ''.join('<td>' + inline(c.strip()) + '</td>' for c in row) + '</tr>' for row in rows[1:]) + '</tbody>'
            out.append('<div class="table-wrap" tabindex="0">' + '<table>' + head + body + '</table></div>'); table.clear()
        if listing:
            out.append('<ul>' + ''.join('<li>' + inline(x) + '</li>' for x in listing) + '</ul>'); listing.clear()

    code, in_code = [], False
    for line in text.splitlines():
        if line.startswith('```'):
            if in_code:
                out.append('<pre>' + html.escape('\n'.join(code)) + '</pre>'); code.clear(); in_code = False
            else:
                flush(); in_code = True
            continue
        if in_code:
            code.append(line); continue
        if not line.strip():
            flush(); continue
        if line.startswith('|'):
            if para or listing: flush()
            table.append(line); continue
        if table: flush()
        heading = re.match(r'^(#{1,6})\s+(.*)', line)
        if heading:
            flush(); n = min(len(heading[1]) + 1, 6)
            out.append(f'<h{n}>' + inline(heading[2]) + f'</h{n}>'); continue
        if line.startswith('> '):
            flush(); out.append('<blockquote>' + inline(line[2:]) + '</blockquote>'); continue
        item = re.match(r'^(?:- |\d+\. )(.*)', line)
        if item:
            if para: flush()
            listing.append(item[1]); continue
        if listing: flush()
        para.append(line)
    flush()
    return '\n'.join(out)


def validate():
    assert len(SOURCES) == len(DATA['sources'])
    people = {p['id'] for p in DATA['profiles']}
    assert len(people) == 4
    for source in SOURCES.values():
        assert source['person'] in people
        assert source['url'].startswith('https://')
        assert source['scope'] and source['observation']
    for p in DATA['profiles']:
        for case in p.get('article_cases', []):
            assert case['source_id'] in SOURCES
        for section in p['sections']:
            assert section['source_ids'] and all(x in SOURCES for x in section['source_ids'])
        for r in p['revenue']:
            assert r['actual_revenue_amount'] is None
            assert r['source_ids'] and all(x in SOURCES for x in r['source_ids'])
    assert DATA['axis'] == '正社員が一番だと思っていた自分が、今は個人事業主を選んでいる'


def main():
    validate()
    for p in DATA['profiles']:
        (BASE / (p['id'] + '.md')).write_text(person_markdown(p))
    keys = ['id', 'person', 'kind', 'title', 'url', 'checked_on', 'access', 'scope', 'published', 'updated', 'displayed_date', 'date_semantics', 'latest_listed_publication', 'latest_listed_modification', 'observation']
    with (BASE / 'sources.csv').open('w', encoding='utf-8-sig', newline='') as f:
        writer = csv.DictWriter(f, fieldnames=keys, lineterminator='\n'); writer.writeheader(); writer.writerows(DATA['sources'])
    with (BASE / 'revenue.csv').open('w', encoding='utf-8-sig', newline='') as f:
        keys = ['person', 'type', 'evidence_level', 'evidence', 'source_ids', 'owner_workload', 'actual_revenue_amount']
        writer = csv.DictWriter(f, fieldnames=keys, lineterminator='\n'); writer.writeheader()
        for p in DATA['profiles']:
            for r in p['revenue']:
                row = dict(r, person=p['name'], source_ids=';'.join(r['source_ids'])); writer.writerow(row)
    names = {p['id']: p['name'] for p in DATA['profiles']}
    cards = []
    for p in DATA['profiles']:
        body = markdown((BASE / (p['id'] + '.md')).read_text())
        cards.append(f'<section id="{p["id"]}" class="profile panel" data-person="{p["id"]}">{body}</section>')
    rows = []
    for s in DATA['sources']:
        label = f"{s['id']} {names[s['person']]} {s['title']} {s['kind']} {s['observation']}"
        rows.append(f'<tr data-person="{s["person"]}" data-search="{html.escape(label, quote=True)}"><td>{s["id"]}</td><td>{names[s["person"]]}</td><td><a href="{html.escape(s["url"], quote=True)}">{html.escape(s["title"])}</a><small>{html.escape(s["scope"])}</small></td><td>{html.escape(s["observation"])}</td></tr>')
    options = ''.join(f'<option value="{p["id"]}">{p["name"]}</option>' for p in DATA['profiles'])
    nav = ''.join(f'<a href="#{p["id"]}">{p["name"]}</a>' for p in DATA['profiles'])
    count = len(DATA['sources'])
    sections = sum(len(p['sections']) for p in DATA['profiles'])
    report = '''<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>働き方ブログのロールモデル分析</title><style>
    :root{color-scheme:light;--ink:#1a3539;--muted:#53696c;--paper:#f4f6f3;--line:#d4dfd9;--accent:#1d6359}*{box-sizing:border-box}html{scroll-behavior:smooth;scroll-padding-top:30px}body{margin:0;background:var(--paper);color:var(--ink);font-family:system-ui,-apple-system,"Noto Sans CJK JP",sans-serif;line-height:1.9}a{color:var(--accent);text-decoration-thickness:1px;text-underline-offset:3px}a:hover{color:#123e38}a:focus-visible,select:focus-visible,input:focus-visible,.table-wrap:focus-visible{outline:3px solid #bc640f;outline-offset:3px}.wrap{max-width:1200px;margin:auto;padding:32px 24px 80px}.hero{padding:36px 0 26px;border-bottom:1px solid var(--line)}.eyebrow{font-size:13px;letter-spacing:.13em;color:var(--muted)}h1{font-size:clamp(27px,4vw,47px);line-height:1.4;margin:15px 0 24px}.axis{font-size:clamp(19px,2.5vw,27px);font-weight:650;max-width:900px}.counts{display:flex;gap:16px;flex-wrap:wrap}.counts span{padding:7px 14px;border:1px solid var(--line);border-radius:8px;background:white}nav{display:flex;flex-wrap:wrap;gap:10px 22px;padding:23px 0}nav a{font-weight:650}.panel{background:#fff;border:1px solid var(--line);border-radius:16px;padding:clamp(20px,4vw,48px);margin:25px 0;min-width:0}.panel>h2:first-child{margin-top:0}h2{font-size:26px;line-height:1.5;margin:32px 0 18px}h3{font-size:21px;line-height:1.6;margin:38px 0 15px;padding-top:18px;border-top:1px solid var(--line)}h4{font-size:18px;margin:25px 0 10px}p{max-width:85ch;margin:15px 0 24px;overflow-wrap:anywhere}small{display:block;color:var(--muted);font-size:12px;line-height:1.7;margin-top:8px}.table-wrap{overflow-x:auto;margin:25px 0;max-width:100%}table{border-collapse:collapse;min-width:680px;width:100%;font-size:14px;line-height:1.8}th,td{border-bottom:1px solid var(--line);padding:15px 14px;vertical-align:top;text-align:left}th{background:#eaf1ed;font-weight:650}blockquote{border-left:4px solid var(--accent);background:#edf4ef;margin:25px 0;padding:20px 24px;font-size:19px}li{margin:9px 0}code{background:#eff3f0;padding:2px 5px;border-radius:4px;font-size:.9em;overflow-wrap:anywhere}pre{white-space:pre-wrap;overflow-wrap:anywhere;background:#edf3ef;border:1px solid var(--line);border-radius:10px;padding:22px;font-size:14px;line-height:2}.controls{display:flex;gap:18px;flex-wrap:wrap;padding:18px 0}.controls label{display:flex;flex-direction:column;font-weight:650;gap:7px}select,input{font:inherit;line-height:1.5;padding:10px 12px;border:1px solid #879e97;border-radius:7px;background:white;color:var(--ink);max-width:100%}#query{width:330px}#result-count{color:var(--muted);font-size:14px}[hidden]{display:none!important}.note{padding:20px;background:#fff9e9;border:1px solid #ead9aa;border-radius:10px;color:#664e22}.source-table td:first-child{white-space:nowrap}.source-table td:nth-child(3){width:34%}footer{font-size:13px;color:var(--muted);margin-top:35px}@media(max-width:600px){.wrap{padding:20px 14px 60px}.hero{padding-top:15px}.panel{border-radius:12px;padding:23px 18px}h2{font-size:23px}h3{font-size:19px}.controls label{width:100%}#query{width:100%}blockquote{padding:16px}nav{gap:8px 17px}table{min-width:640px}}@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}}@media print{body{background:white}.wrap{max-width:none;padding:0}.panel{border:0;padding:0;break-before:page}.controls,nav{display:none}.table-wrap{overflow:visible}table{min-width:0;font-size:9pt}a{color:inherit}.hero{break-after:page}}
    </style></head><body><main class="wrap"><header class="hero"><div class="eyebrow">EDITORIAL RESEARCH · 2026.11.14</div><h1>働き方ブログを育てるための<br>4人のロールモデル分析</h1><p class="axis">正社員が一番だと思っていた自分が、今は個人事業主を選んでいる</p><p>この軸を守り、文章・企画・見せ方・収益へのつなぎ方を学ぶための調査資料。</p><div class="counts"><span>4人</span><span>__COUNT__ページの出典</span><span>__SECTIONS__項目の人物別分析</span></div></header><nav aria-label="資料の目次"><a href="#overview">全体の比較</a>__NAV__<a href="#playbook">記事の作り方</a><a href="#sources">出典を探す</a></nav><div class="note">公開ページからの標本調査です。全記事の分析や、現在の収益の監査ではありません。実画面を確認できたのはhitodeblogのPC・スマホ。ほかの4ブログの配色・寸法は接続制限により未確認です。</div><section class="panel" id="overview">__README__</section>__CARDS__<section class="panel" id="playbook">__PLAYBOOK__</section><section class="panel" id="sources"><h2>出典を探す</h2><p>観察の根拠へ戻るための台帳。タイトルは一覧用に短縮したものを含みます。記事本文、一覧、販売ページ、無料公開部分を区別しています。</p><div class="controls"><label>人物<select id="person"><option value="all">4人すべて</option>__OPTIONS__</select></label><label>タイトル・観察内容を検索<input id="query" type="search" placeholder="例：転職、料金、有料、更新"></label></div><p id="result-count" role="status" aria-live="polite">__COUNT__件の出典</p><div class="table-wrap" tabindex="0"><table class="source-table"><thead><tr><th scope="col">ID</th><th scope="col">人物</th><th scope="col">ページ・確認範囲</th><th scope="col">観察したこと</th></tr></thead><tbody>__ROWS__</tbody></table></div><p><a href="research.json">構造化データ</a> · <a href="sources.csv">出典CSV</a> · <a href="revenue.csv">収益源CSV</a> · <a href="../../WORKSTYLE-EDITORIAL.md">今後も守る編集方針</a></p></section><footer>研究資料。記事・写真・外部サイトの見た目の転載は含みません。公開サイトの変更は今回行っていません。</footer></main><script>
    const person=document.getElementById('person'),query=document.getElementById('query'),rows=[...document.querySelectorAll('[data-search]')];
    function filter(){const p=person.value,q=query.value.trim().toLocaleLowerCase('ja');let count=0;rows.forEach(r=>{const show=(p==='all'||r.dataset.person===p)&&r.dataset.search.toLocaleLowerCase('ja').includes(q);r.hidden=!show;if(show)count++});document.getElementById('result-count').textContent=count+'件の出典';}
    person.addEventListener('change',filter);query.addEventListener('input',filter);
    </script></body></html>'''
    replacements = {'COUNT': str(count), 'SECTIONS': str(sections), 'NAV': nav, 'README': markdown((BASE/'README.md').read_text()),
                    'CARDS': '\n'.join(cards), 'PLAYBOOK': markdown((BASE/'PLAYBOOK.md').read_text()), 'ROWS': '\n'.join(rows), 'OPTIONS': options}
    for key, value in replacements.items(): report = report.replace('__' + key + '__', value)
    (BASE / 'report.html').write_text(report)
    print(json.dumps({'profiles': len(DATA['profiles']), 'sources': count, 'analysis_sections': sections,
                      'revenue_entries': sum(len(p['revenue']) for p in DATA['profiles']), 'output': str(BASE/'report.html')}, ensure_ascii=False))


if __name__ == '__main__':
    main()
