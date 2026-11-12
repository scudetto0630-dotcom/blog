#!/usr/bin/env python3
"""Offline editorial inventory and review candidates; no invented search demand."""
import argparse,json,re,html,datetime
from collections import Counter
from itertools import combinations
from pathlib import Path
from urllib.parse import urlsplit
ROOT=Path(__file__).resolve().parents[1]
def plain(s):return re.sub(r'\s+',' ',html.unescape(re.sub('<[^>]+>',' ',s))).strip()
def grams(s):return {s[i:i+3] for i in range(max(0,len(s)-2))}
def report(today=None):
    today=today or datetime.datetime.now(__import__('zoneinfo').ZoneInfo('Asia/Tokyo')).date()
    def read(n):return json.loads((ROOT/'hirogaru-home'/n).read_text())
    starters={r['slug']:r for r in read('starter-articles.json')}
    items=[dict(starters.get(r['slug'],{}),**r) for r in read('article-map.json')]+read('expanded-articles.json')+read('affiliate-articles.json')
    intents=json.loads((ROOT/'site-content/operations/quality-100-20261107/article-intents.json').read_text())
    rows=[];texts={}
    paragraphs=[[plain(p) for p in re.findall(r'<p\b[^>]*>(.*?)</p>',r.get('content',''),re.S)] for r in items]
    common=Counter(p for ps in paragraphs for p in set(ps))
    for r,ps in zip(items,paragraphs):
        core=' '.join(p for p in ps if common[p]<5)
        texts[r['slug']]=grams(core)
        heads=re.findall(r'<h[23]\b[^>]*>(.*?)</h[23]>',r.get('content',''),re.S)
        urls=set(html.unescape(u) for u in re.findall(r'href="(https?://[^"]+)"',r.get('content','')))
        intent=intents.get(r['slug'],{})
        rows.append({'slug':r['slug'],'title':r['title'],'topic':r['topic'],'reader':intent.get('reader','商品仕様や申し込み条件を比較する人'),
                     'question':intent.get('question',r['title'].replace('【ご紹介】','')+'の特徴と注文条件は？'),
                     'question_basis':'editorial_definition_not_search_volume','role':intent.get('role',r.get('kind','guide')),
                     'headings':[plain(x) for x in heads],'opening':ps[0] if ps else '',
                     'ending':ps[-1] if ps else '', 'external_sources':sorted(u for u in urls if urlsplit(u).hostname not in ('hirogarushumi.com','px.a8.net','www.a8.net')),
                     'review_due_at':r.get('review_due_at'),'review_due':bool(r.get('review_due_at') and r['review_due_at']<=today.isoformat()),
                     'decision':'maintain_distinct_question; verify_before_merging'})
    pairs=[]
    for a,b in combinations(rows,2):
        if a['topic']!=b['topic']:continue
        x,y=texts[a['slug']],texts[b['slug']];score=len(x&y)/len(x|y) if x|y else 0
        if score>=.15:pairs.append({'a':a['slug'],'b':b['slug'],'shared_text_jaccard':round(score,3),'decision':'review_only; distinct_products_and_questions_are_not_duplicates'})
    pairs.sort(key=lambda r:-r['shared_text_jaccard'])
    manifest=read('photo-assets.json');photos=[]
    source_text='\n'.join(p.read_text() for p in (ROOT/'hirogaru-home').glob('*.php'))
    for key,a in manifest['assets'].items():
        if a.get('kind')!='provided':continue
        photos.append({'key':key,'file':a['file'],'alt':a['alt'],'subject':a['subject'],'referenced_in_php':key in source_text,
                       'cat_gallery_eligible':a['subject']=='manmos','decision':'existing_owner_photo; preserve_context_and_do_not_imply_a_bib_or_new_purchase'})
    return {'checked_on':today.isoformat(),'article_count':len(rows),'articles':rows,'overlap_review_candidates':pairs[:20],'owner_photos':photos,
            'seasonal_review':[{'when':'2026-12-01','topic':'年末の移動前にキャリーと交通機関の規定を点検','url':'/cat-carrier-checklist/','status':'planned; no travel experience invented'},
                               {'when':'2027-04-01','topic':'冷感用品を使わなかった本人の話と室内環境の記事候補を点検','url':'/manmos-cat-life/#manmos-unused-cooling','status':'planned; no medical recommendations inferred'}],
            'limits':['Similarity is a review cue, not a merge decision.','Questions are editorial definitions, not measured demand.','A PHP reference scan is not proof an owner photo is unused; the live gallery is also considered.']}
def markdown(data):
    out=['# 記事の運用点検', '', '検索需要を推測せず、記事の役割と確認期限を整理した記録。', '', '|記事|答える疑問|次回確認|','|---|---|---|']
    for r in data['articles']:out.append('|'+r['title'].replace('|','｜')+'|'+r['question'].replace('|','｜')+'|'+str(r['review_due_at'] or '時期未指定')+'|')
    out+=['','## 重複の確認候補','', '文章の類似だけで統合しない。違う商品・違う疑問は別の記事として維持する。']
    for p in data['overlap_review_candidates']:out.append('- '+p['a']+' / '+p['b']+'：'+str(p['shared_text_jaccard']))
    return '\n'.join(out)+'\n'
if __name__=='__main__':
    p=argparse.ArgumentParser();p.add_argument('--output',type=Path,required=True);p.add_argument('--markdown',type=Path);p.add_argument('--today',type=datetime.date.fromisoformat);a=p.parse_args()
    d=report(a.today);a.output.parent.mkdir(parents=True,exist_ok=True);a.output.write_text(json.dumps(d,ensure_ascii=False,indent=2)+'\n')
    if a.markdown:a.markdown.write_text(markdown(d))
    print(json.dumps({'articles':d['article_count'],'owner_photos':len(d['owner_photos']),'overlap_candidates':len(d['overlap_review_candidates'])}))
