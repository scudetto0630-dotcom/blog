#!/usr/bin/env python3
"""Offline editorial checks. Review flags are not automated factual verdicts."""
import argparse, hashlib, html, json, re
from difflib import SequenceMatcher
from collections import Counter, defaultdict
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
POLICY=ROOT/'site-content/operations/quality-100-20261107'
def plain(body):
    body=re.sub(r'</(?:p|li|h[1-6]|tr|th|td|div|section|ul|ol|table|thead|tbody|aside)>','\n',body,flags=re.I)
    return html.unescape(re.sub(r'<[^>]+>','',body))
def facts(body):
    text=plain(body)
    return {'numbers':sorted(re.findall(r'\d+(?:\.\d+)?\s*(?:kg|ml|g|％|%|年|月|日|袋|粒|円|歳)',text)), 'sources':sorted(set(html.unescape(x) for x in re.findall(r'href="(https?://[^"]+)"',body)))}
def merge_paragraph_corrections(source_before,source_after,published):
    """Apply reviewed contiguous paragraph blocks without crossing headings or media."""
    pattern=r'<p\b[^>]*>.*?</p>(?:[ \t\r\n]*<p\b[^>]*>.*?</p>)*'
    before=re.findall(pattern,source_before,re.S);after=re.findall(pattern,source_after,re.S)
    if len(before)!=len(after) or re.sub(pattern,'<PARAGRAPH>',source_before,flags=re.S)!=re.sub(pattern,'<PARAGRAPH>',source_after,flags=re.S):raise ValueError('Only paragraph corrections can use this merge')
    result=published
    for old,new in zip(before,after):
        if old==new:continue
        if result.count(old)!=1:raise ValueError('Published paragraph is missing or ambiguous; stop for review')
        result=result.replace(old,new,1)
    if re.findall(r'<img\b[^>]*>',published,re.I)!=re.findall(r'<img\b[^>]*>',result,re.I):raise ValueError('Images or tracking pixels changed during copy correction')
    if facts(published)!=facts(result):raise ValueError('Numerical or link facts changed during copy correction')
    return result

def inspect(article, affiliate=False):
    out=[];slug=article['slug'];title=article['title'];body=article.get('content','');text=plain(body)
    def flag(kind,rule,detail):out.append({'slug':slug,'kind':kind,'rule':rule,'detail':detail})
    if affiliate and not title.startswith('【ご紹介】'):flag('error','affiliate-title','案件記事の接頭辞がありません。')
    if affiliate and ('px.a8.net/' not in body or '広告' not in text):flag('error','ad-integrity','広告表示または元のA8リンクを確認してください。')
    if affiliate and any(x in title for x in ['犬用','ジュエリー']):flag('error','scope','今回の対象外です。')
    for m in re.finditer(r'(?:必ず|絶対に|安全性が高い|副作用がない|治る|効果がある)',text):flag('fact','unsupported-claim',text[max(0,m.start()-25):m.end()+30])
    if affiliate and re.search(r'(?:実際に使って|うちの猫に与え|愛用して|使ってみたところ)',text):flag('fact','experience-provenance','本人の確認済み使用記録と照合が必要です。')
    if slug=='withpety-nekobihada' and 'PS-B1' not in text:flag('fact','bihada-model','PS-B1の資料と商品画像を照合してください。')
    for sentence in re.split('[。！？\n]',text):
        if len(sentence)>125:flag('style','long-sentence',sentence[:160])
        if sentence.count('、')>=5:flag('style','compound-sentence',sentence[:160])
        if re.match(r'^(?:これ|それ|こちら|その点)[はがを]',sentence.strip()):flag('style','unclear-reference',sentence[:120])
    if re.search(r'詳しい香りの名前を足す|記事として(?:整理|まとめ)|読者に伝わる(?:文章|構成)',text):flag('style','editorial-meta','本文に執筆上の都合が出ていないか確認してください。')
    leads=[plain(p).strip() for attrs,p in re.findall(r'<p\b([^>]*)>(.*?)</p>',body,re.S) if not re.search(r'hsh-ad-notice|hsh-product-scope',attrs)][:3]
    if len(leads)>1 and min(len(leads[0]),len(leads[1]))>=30 and SequenceMatcher(None,leads[0],leads[1],autojunk=False).ratio()>=.62:flag('style','repeated-opening','導入と直後の段落が近い表現です。次の段落で話が進むか確認してください。')
    weak=sum(text.count(x) for x in ['確認したい','見ておこう','知っておきたい'])
    if weak>=4:flag('style','weak-repetition',str(weak)+'箇所。書き手の口調を残しながら個別に検討します。')
    for term in re.findall('[ァ-ヶー]{4,}',title):
        if text.count(term)>max(15,len(text)//100):flag('style','keyword-repeat',term)
    paragraphs=[re.sub(r'\s+','',plain(p)) for p in re.findall(r'<p\b[^>]*>(.*?)</p>',body,re.S)]
    for p,n in Counter(x for x in paragraphs if len(x)>45).items():
        if n>1:flag('style','duplicate-paragraph',p[:130])
    headings=[plain(x) for x in re.findall(r'<h[23]\b[^>]*>(.*?)</h[23]>',body,re.S)]
    for heading in headings:
        if heading in ['まとめ','ポイント','確認すること']:flag('style','vague-heading',heading)
    return out

def run():
    intents=json.loads((POLICY/'article-intents.json').read_text());articles=[];flags=[];inventory=[];photos=json.loads((ROOT/'hirogaru-home/photo-assets.json').read_text())
    for name in ['starter','expanded','affiliate']:
        for a in json.loads((ROOT/f'hirogaru-home/{name}-articles.json').read_text()):
            articles.append(a);flags+=inspect(a,name=='affiliate');sources=facts(a.get('content',''))['sources']
            official=[u for u in sources if not any(s in u for s in ['px.a8.net','hirogarushumi.com'])]
            notes=json.loads((ROOT/'hirogaru-home/product-reader-notes.json').read_text()).get(a['slug'],{})
            if notes.get('source'):official=sorted(set(official+[notes['source']]))
            inventory.append({'slug':a['slug'],'title':a['title'],'topic':a.get('topic',a.get('category')),'role':intents[a['slug']]['role'],'audience':intents[a['slug']]['reader'],'audience_question':intents[a['slug']]['question'],'question_status':'editorial_intent_defined_from_existing_article','sources':official,'reviewed_at':None,'automated_checked_at':'2026-11-07','content_sha256':hashlib.sha256(a.get('content','').encode()).hexdigest(),'fact_signature':facts(a.get('content',''))})
    # A near-duplicate warning is a lead, never a destructive merge decision.
    repeats=defaultdict(list)
    for a in articles:
        for p in re.findall(r'<p\b[^>]*>(.*?)</p>',a.get('content',''),re.S):
            t=re.sub(r'\s+','',plain(p))
            if len(t)>120:repeats[t].append(a['slug'])
    for t,slugs in repeats.items():
        if len(set(slugs))>1:flags.append({'slug':','.join(sorted(set(slugs))),'kind':'style','rule':'shared-paragraph','detail':t[:130]})
    media=[]
    for key,a in photos['assets'].items():media.append({'id':key,'file':a['file'],'kind':a.get('kind'),'subject':a.get('subject'),'sha256':a.get('sha256'),'source':a.get('source_url',a.get('source','')),'derived_from':a.get('derived_from',a.get('source_assets',[])),'mapping_status':'existing_manifest; incomplete derivative ancestry requires review'})
    return {'articles':len(articles),'counts':dict(Counter(x['kind'] for x in flags)),'flags':flags,'inventory':inventory,'media':media,'limits':['型番・写真・公式ページの意味の一致は、機械的な文字検査だけでは保証できません。','数値は署名として差分を検出。本文と表の意味の一致は人の確認対象です。','検索需要・順位・体験の事実を推定しません。']}
if __name__=='__main__':
    p=argparse.ArgumentParser();p.add_argument('--output',type=Path,required=True);p.add_argument('--compare',type=Path);a=p.parse_args();result=run()
    if a.compare:
        old=json.loads(a.compare.read_text());before={x['slug']:x['fact_signature'] for x in old['inventory']};result['changed_fact_signatures']=[x['slug'] for x in result['inventory'] if x['slug'] in before and x['fact_signature']!=before[x['slug']]]
    a.output.parent.mkdir(parents=True,exist_ok=True);a.output.write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n');print(json.dumps({'articles':result['articles'],'counts':result['counts'],'output':str(a.output)},ensure_ascii=False))

    raise SystemExit(any(f["kind"]=="error" for f in result["flags"]))
