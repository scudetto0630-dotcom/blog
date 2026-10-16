#!/usr/bin/env python3
"""Read-only editorial and link audit. It never edits articles or verification dates."""
import argparse,json,datetime,urllib.request,urllib.error,urllib.parse,concurrent.futures,xml.etree.ElementTree as ET
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def check_url(url):
 try:
  r=urllib.request.urlopen(urllib.request.Request(url,headers={'User-Agent':'NekoNoTonari LinkAudit/1.0 bot'}),timeout=15)
  with r:return {'url':url,'status':r.status,'ok':200<=r.status<400}
 except urllib.error.HTTPError as e:return {'url':url,'status':e.code,'ok':False}
 except (urllib.error.URLError,TimeoutError):return {'url':url,'status':None,'ok':False}
def editorial_report(day):
 articles=json.loads((ROOT/'content/articles.json').read_text());refs=json.loads((ROOT/'content/references.json').read_text());local=json.loads((ROOT/'content/local.json').read_text())
 return {'checked_at':day.isoformat(),'articles':len(articles),'overdue_articles':[a['slug'] for a in articles if datetime.date.fromisoformat(a['review_due_at'])<day],'unverified_references':[k for k,r in refs.items() if not r.get('verified_at')],'expired_events':[x['title'] for x in local['events'] if x['event_end']<day.isoformat()],'expired_local_records':[x['title'] for x in local['events']+local['organizations'] if x.get('expires_at','')<day.isoformat()],'verification_warning':'Link availability does not verify article accuracy.'}
def main():
 p=argparse.ArgumentParser();p.add_argument('--output',type=Path,required=True);p.add_argument('--check-public',action='store_true');p.add_argument('--check-external',action='store_true');p.add_argument('--as-of',default=datetime.date.today().isoformat());a=p.parse_args();day=datetime.date.fromisoformat(a.as_of);report=editorial_report(day);urls=[]
 if a.check_public:
  base='https://hirogarushumi.com/neko-guide/'
  try:
   with urllib.request.urlopen(base+'sitemap.xml',timeout=20) as r:urls=[n.text for n in ET.fromstring(r.read()).iter('{http://www.sitemaps.org/schemas/sitemap/0.9}loc')]
   if not urls or any(not u.startswith(base) for u in urls):raise ValueError('Invalid sitemap URLs')
  except Exception:report['public_sitemap_error']=True
 if a.check_external:urls+=list({r['url'] for r in json.loads((ROOT/'content/references.json').read_text()).values()})
 with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:report['links']=list(pool.map(check_url,urls))
 report['failed_links']=sum(not r['ok'] for r in report['links']);report['needs_editorial_review']=bool(report['overdue_articles'] or report['unverified_references'] or report['expired_events'] or report['expired_local_records']);a.output.parent.mkdir(parents=True,exist_ok=True);a.output.write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n');print(json.dumps({k:report[k] for k in ['articles','failed_links','needs_editorial_review']},ensure_ascii=False))
 return 1 if report['failed_links'] or report.get('public_sitemap_error') else 0
if __name__=='__main__':raise SystemExit(main())
