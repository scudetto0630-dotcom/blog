#!/usr/bin/env python3
"""Summarize local audit outputs without exposing queries, credentials or source full text."""
import argparse,json,datetime
from pathlib import Path
def summarize(folder):
    def load(name):
        p=folder/name
        try:return json.loads(p.read_text())
        except (OSError,json.JSONDecodeError):return None
    public=load('public-audit.json');reader=load('reader-audit.json');products=load('product-source-audit.json');ads=load('revenue-readiness.json');plan=load('growth-planning.json')
    lines=['# ブログ運用点検','',str(plan.get('checked_on'))+' (JST)' if plan else datetime.datetime.now(datetime.timezone(datetime.timedelta(hours=9))).isoformat(), '']
    for label,d,key in [('公開HTTP',public,'failures'),('読者導線',reader,'problems'),('広告接続',ads,'technical_failures')]:
        if d is None:lines.append('- '+label+'：結果未取得')
        else:
            v=d.get(key);n=len(v) if isinstance(v,list) else v
            lines.append('- '+label+'：検出事項 '+str(n))
    if products is None:lines.append('- 商品公式ページ：結果未取得')
    else:
        changed=[r['slug'] for r in products.get('results',[]) if r.get('sample_changed') or r.get('redirect_changed')]
        lines+=['- 商品公式ページ：'+str(len(products.get('results',[])))+'件を取得。リンク不達 '+str(len(products.get('missing',[])))+'件。',
                '- 最終確認した控えとの差分候補：'+str(len(changed))+'件（販売終了の判定ではない）']
        lines.extend('  - '+s for s in changed)
    if plan:lines.append('- 内容点検：'+str(plan['article_count'])+'記事。確認期限到来 '+str(sum(r['review_due'] for r in plan['articles']))+'件。')
    lines+=['','検索順位・アクセス数・売上は、このHTTP点検だけでは測定していない。','原文の差分や取得失敗は人が確認し、本文の自動改変には直結させない。']
    return '\n'.join(lines)+'\n'
if __name__=='__main__':
    p=argparse.ArgumentParser();p.add_argument('--folder',type=Path,required=True);p.add_argument('--output',type=Path,required=True);a=p.parse_args()
    a.output.write_text(summarize(a.folder));print('Operational summary saved; no private search data included.')
