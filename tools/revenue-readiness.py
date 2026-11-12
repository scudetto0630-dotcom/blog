#!/usr/bin/env python3
"""Read-only technical advertising checks, not an AdSense approval prediction."""
import argparse
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, timezone
from html.parser import HTMLParser
import json
from pathlib import Path
import re
from urllib.request import Request, urlopen
from urllib.error import HTTPError
from urllib.parse import quote

BASE = 'https://hirogarushumi.com'
PATHS = ['/', '/hsh-about/', '/お問い合わせ/', '/プライバシーポリシー/',
         '/robots.txt', '/wp-sitemap.xml', '/ads.txt']

class Page(HTMLParser):
    def __init__(self):
        super().__init__(); self.scripts=[]; self.robots=[]; self.forms=0
    def handle_starttag(self, tag, attrs):
        a=dict(attrs)
        if tag=='script' and a.get('src'): self.scripts.append(a['src'])
        if tag=='meta' and a.get('name')=='robots': self.robots.append(a.get('content',''))
        if tag=='form': self.forms+=1

def fetch(path):
    req=Request(BASE+quote(path,safe='/'),headers={'User-Agent':'HSH-revenue-readiness-bot/1.0'})
    try:
        with urlopen(req,timeout=30) as r: return path,r.status,r.read().decode('utf-8','replace')
    except HTTPError as e: return path,e.code,e.read().decode('utf-8','replace')
    except (OSError,TimeoutError) as e: return path,0,type(e).__name__

def audit():
    with ThreadPoolExecutor(max_workers=3) as p: responses=list(p.map(fetch,PATHS))
    rows=[]; failures=[]; publishers=set(); script_pages=[]; ads_status=None; ads_ids=[]
    for path,status,body in responses:
        page=Page();page.feed(body)
        if path=='/ads.txt':
            ads_status=status
            if status==200:
                ads_ids=re.findall(r'^\s*google\.com\s*,\s*(pub-\d{16})\s*,\s*(?:DIRECT|RESELLER)\b',body,re.M|re.I)
            if status not in (200,404): failures.append({'path':path,'http':status})
        elif status!=200: failures.append({'path':path,'http':status})
        if any('noindex' in x.lower() for x in page.robots) and path in ('/','/hsh-about/'):
            failures.append({'path':path,'problem':'noindex'})
        ids=set(re.findall(r'ca-pub-\d{16}',body));publishers.update(ids)
        loaded=any('pagead2.googlesyndication.com/pagead/js/adsbygoogle.js' in x for x in page.scripts)
        if loaded: script_pages.append(path)
        if sum('pagead2.googlesyndication.com/pagead/js/adsbygoogle.js' in x for x in page.scripts)>1:
            failures.append({'path':path,'problem':'duplicate_adsense_script'})
        rows.append({'path':path,'http':status,'adsense_script':loaded,'form_present':page.forms>0})
    if publishers and (ads_status!=200 or any(x[3:] not in ads_ids for x in publishers)):
        failures.append({'path':'/ads.txt','problem':'active_publisher_missing_from_ads_txt'})
    return {'checked_at':datetime.now(timezone.utc).isoformat(),'checks':rows,'technical_failures':failures,
            'adsense':{'publisher_ids_detected':sorted(publishers),'script_pages':script_pages,
                       'ads_txt_http':ads_status,'ads_txt_google_ids':sorted(set(ads_ids)),
                       'account_and_site_approval':'not_verifiable_from_public_html'},
            'limits':['Technical checks do not establish policy compliance, approval, traffic or revenue.',
                      'An absent ads.txt or ad script is expected before setup; no identifiers are invented.',
                      'Contact form delivery, Google account and Search Console are not tested.']}

def main():
    p=argparse.ArgumentParser(description=__doc__);p.add_argument('--output',type=Path,required=True);a=p.parse_args()
    result=audit();a.output.parent.mkdir(parents=True,exist_ok=True)
    a.output.write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n')
    print(json.dumps({'technical_failures':len(result['technical_failures']),'adsense_script_pages':len(result['adsense']['script_pages']),'output':str(a.output)}))
    return bool(result['technical_failures'])
if __name__=='__main__': raise SystemExit(main())
