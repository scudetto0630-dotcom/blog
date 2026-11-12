#!/usr/bin/env python3
"""Read official source URLs only. Never follow A8 tracking links or submit forms."""
import argparse,concurrent.futures,datetime,hashlib,html,json,re,urllib.request,urllib.error,urllib.parse
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
class OfficialRedirects(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,req,fp,code,msg,headers,newurl):
        host=(urllib.parse.urlsplit(newurl).hostname or '').lower()
        if host=='a8.net' or host.endswith('.a8.net') or urllib.parse.urlsplit(newurl).scheme!='https':
            raise urllib.error.HTTPError(req.full_url,code,'Unsafe redirect rejected',headers,fp)
        return super().redirect_request(req,fp,code,msg,headers,newurl)

def check(item):
    url=item['source_url'];u=urllib.parse.urlsplit(url)
    if u.scheme!='https' or u.hostname in ('px.a8.net','www.a8.net','a8.net'): raise ValueError('Not an official HTTPS source')
    result={'slug':item['slug'],'url':url}
    try:
        with urllib.request.build_opener(OfficialRedirects()).open(urllib.request.Request(url,headers={'User-Agent':'HirogaruSourceCheck/1.0','Accept':'text/html'}),timeout=25) as r:
            raw=r.read(1_000_000);text=raw.decode('utf-8','replace')
            text=re.sub(r'<(?:script|style)\b[^>]*>.*?</(?:script|style)>','',text,flags=re.S|re.I)
            title=re.search(r'<title\b[^>]*>(.*?)</title>',text,re.S|re.I)
            visible=html.unescape(re.sub('<[^>]*>',' ',text));visible=re.sub(r'\s+',' ',visible).strip()
            signals=[word for word in ['販売終了','取り扱い終了','販売を終了','在庫切れ','売り切れ','SOLD OUT'] if word in visible]
            result.update(status=r.status,final_url=r.url,sampled_bytes=len(raw),sample_limit=1_000_000,
              page_title=html.unescape(title[1]).strip() if title else None,
              sampled_text_sha256=hashlib.sha256(visible.encode()).hexdigest(),
              availability_review_signals=signals,last_modified=r.headers.get('Last-Modified'))
    except urllib.error.HTTPError as e: result['status']=e.code
    except (urllib.error.URLError,TimeoutError): result['status']=None
    result['result']='unavailable' if result['status'] in (404,410) else 'retrieved' if result['status']==200 and result.get('sampled_bytes',0)>1000 else 'needs_review'
    return result

def main():
    p=argparse.ArgumentParser();p.add_argument('--output',default='product-source-audit.json');p.add_argument('--previous',type=Path);a=p.parse_args()
    items=json.loads((ROOT/'hirogaru-home/affiliate-articles.json').read_text())
    with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool: rows=list(pool.map(check,items))
    previous={r['slug']:r for r in json.loads(a.previous.read_text()).get('results',[])} if a.previous else {}
    for row in rows:
        old=previous.get(row['slug'],{});row['sample_changed']=row.get('sampled_text_sha256')!=old.get('sampled_text_sha256') if old.get('sampled_text_sha256') and row.get('sampled_text_sha256') else None
        row['redirect_changed']=bool(old.get('final_url') and row.get('final_url') and old['final_url']!=row['final_url'])
        row['source_host_changed']=bool(row.get('final_url') and urllib.parse.urlsplit(row['url']).hostname.removeprefix('www.')!=urllib.parse.urlsplit(row['final_url']).hostname.removeprefix('www.'))
    report={'checked_at':datetime.datetime.now(datetime.timezone.utc).isoformat(),'scope':'Official source reachability only; HTTP 200 does not establish availability, current pricing, or sale conditions. Availability keywords and sampled text changes are review signals only, not a sale-status verdict. No affiliate clicks.','results':rows,'missing':[r['slug'] for r in rows if r['result']=='unavailable'],'needs_review':[r['slug'] for r in rows if r['result']=='needs_review']}
    Path(a.output).write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n');print(json.dumps({'checked':len(rows),'missing':len(report['missing']),'needs_review':len(report['needs_review'])}));return bool(report['missing'])
if __name__=='__main__':raise SystemExit(main())
