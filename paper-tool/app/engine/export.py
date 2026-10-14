import io
import json
import re
import zipfile
from ..taxonomy import NAMES


def export_results(product, results, ingredients=None):
    """Actual pipeline outputs, never substitute demo judgments."""
    safe=lambda value:re.sub(r'[\\/:*?"<>|\x00-\x1f]', '_', value)[:120].strip(' .') or '製品名未設定'
    def block(result):
        paper=result['paper'];published=paper.get('publication_date',{})
        date='/'.join(published.get(k,'') for k in ('Year','Month')).strip('/') or published.get('MedlineDate','年月未確認')
        return '\n'.join([date,paper['pubmed_url'],*paper.get('figure_urls',[]),paper.get('title_ja') or '日本語タイトル未取得',paper['title_en'],'PMID: '+paper['pmid'],''])+'\n'
    buf=io.BytesIO()
    with zipfile.ZipFile(buf,'w',zipfile.ZIP_DEFLATED) as z:
        root=safe(product)+'/'
        for ingredient in sorted(set(ingredients or []) | {r['ingredient'] for r in results}):
            rows=[r for r in results if r['ingredient']==ingredient]
            def date_key(r):
                d=r['paper'].get('publication_date',{});month=d.get('Month','01')
                months={name:str(i+1).zfill(2) for i,name in enumerate(('Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'))}
                return (d.get('Year','0000'),months.get(month,month.zfill(2)),int(r['paper']['pmid']))
            rows.sort(key=date_key,reverse=True)
            directory=root+safe(ingredient)+'/'
            for part in NAMES:
                chosen=[r for r in rows if r['final_decision']['status']=='adopted' and part in r['final_decision']['categories']]
                z.writestr(directory+f'論文一覧_{part}.txt',''.join(block(r) for r in chosen))
                effective=[r for r in chosen if r['final_decision']['human_trial']=='effective']
                z.writestr(directory+f'ヒト試験効果あり/論文一覧_{part}_ヒト試験効果あり_{len(effective)}件.txt',''.join(block(r) for r in effective))
            for status,name in (('review','要確認論文'),('excluded','除外論文')):
                z.writestr(directory+name+'.txt',''.join(block(r) for r in rows if r['final_decision']['status']==status))
            z.writestr(directory+'再チェック修正履歴.json',json.dumps(rows,ensure_ascii=False,indent=2))
        z.writestr(root+'README.txt','AIの文献判定であり、製品自体の有効性や医学的結論を証明するものではありません。各判定と再チェックの原記録を保持しています。\n')
    return buf.getvalue()
