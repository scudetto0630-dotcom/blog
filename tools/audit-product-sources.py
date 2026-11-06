#!/usr/bin/env python3
"""Read official source URLs only. Never follow A8 tracking links or submit forms."""
import argparse,concurrent.futures,datetime,json,urllib.request,urllib.error,urllib.parse
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def check(item):
    url=item['source_url'];u=urllib.parse.urlsplit(url)
    if u.scheme!='https' or u.hostname in ('px.a8.net','www.a8.net','a8.net'): raise ValueError('Not an official HTTPS source')
    result={'slug':item['slug'],'url':url}
    try:
        with urllib.request.urlopen(urllib.request.Request(url,headers={'User-Agent':'HirogaruSourceCheck/1.0','Accept':'text/html'}),timeout=25) as r:
            result.update(status=r.status,final_url=r.url,sampled_bytes=len(r.read(262144)))
    except urllib.error.HTTPError as e: result['status']=e.code
    except (urllib.error.URLError,TimeoutError): result['status']=None
    result['result']='unavailable' if result['status'] in (404,410) else 'retrieved' if result['status']==200 and result.get('sampled_bytes',0)>1000 else 'needs_review'
    return result

def main():
    p=argparse.ArgumentParser();p.add_argument('--output',default='product-source-audit.json');a=p.parse_args()
    items=json.loads((ROOT/'hirogaru-home/affiliate-articles.json').read_text())
    with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool: rows=list(pool.map(check,items))
    report={'checked_at':datetime.datetime.now(datetime.timezone.utc).isoformat(),'scope':'Official source reachability only; HTTP 200 does not establish availability, current pricing, or sale conditions. No affiliate clicks.','results':rows,'missing':[r['slug'] for r in rows if r['result']=='unavailable'],'needs_review':[r['slug'] for r in rows if r['result']=='needs_review']}
    Path(a.output).write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n');print(json.dumps({'checked':len(rows),'missing':len(report['missing']),'needs_review':len(report['needs_review'])}));return bool(report['missing'])
if __name__=='__main__':raise SystemExit(main())
