#!/usr/bin/env python3
"""Read-only public blog checks. No credentials, analytics IDs or external-link requests."""
import argparse
import concurrent.futures
import datetime
from html.parser import HTMLParser
import json
from pathlib import Path
import urllib.error
import urllib.parse
import urllib.request

class Document(HTMLParser):
    def __init__(self):
        super().__init__(); self.links=[]; self.images=[]; self.h1=0; self.robots=[]; self.title=''; self.in_title=False; self.canonical=[]; self.descriptions=[]
    def handle_starttag(self,tag,attrs):
        a=dict(attrs)
        if tag=='a' and a.get('href'): self.links.append(a['href'])
        if tag=='img': self.images.append(a)
        if tag=='h1': self.h1+=1
        if tag=='title': self.in_title=True
        if tag=='meta' and a.get('name')=='robots': self.robots.append(a.get('content',''))
        if tag=='meta' and a.get('name')=='description': self.descriptions.append(a.get('content',''))
        if tag=='link' and a.get('rel')=='canonical': self.canonical.append(a.get('href',''))
    def handle_endtag(self,tag):
        if tag=='title': self.in_title=False
    def handle_data(self,data):
        if self.in_title: self.title+=data

def fetch(url):
    parts=urllib.parse.urlsplit(url)
    url=urllib.parse.urlunsplit((parts.scheme,parts.netloc,urllib.parse.quote(parts.path,safe='/%:@'),urllib.parse.quote(parts.query,safe='=&%:+,;/?'),''))
    request=urllib.request.Request(url,headers={'User-Agent':'HirogaruSiteAudit/1.0 (public site maintenance; bot)','Cache-Control':'no-cache'})
    try:
        with urllib.request.urlopen(request,timeout=30) as r: return r.status,r.read().decode('utf-8','replace')
    except (urllib.error.URLError,TimeoutError) as e: return 0,str(e)

def audit(base):
    paths=['/','/category/cats/','/category/whisky/','/category/it-freelance/','/cat-carrier-checklist/','/highball-measure-and-adjust/','/csv-import-seven-checks/','/hsh-about/','/hsh-editorial/','/wp-sitemap.xml','/robots.txt']
    rows=[]; extra=set(); origin=urllib.parse.urlsplit(base).netloc
    for path in paths:
        url=base.rstrip('/')+path; status,body=fetch(url); errors=[]; warnings=[]
        if status!=200: errors.append('HTTP request failed: '+str(status))
        if '<html' in body.lower():
            doc=Document();doc.feed(body)
            if doc.h1!=1: warnings.append('h1 count: '+str(doc.h1))
            if not doc.title.strip(): errors.append('Missing page title')
            if any('noindex' in x for x in doc.robots): errors.append('noindex is set')
            if len(doc.descriptions)!=1: warnings.append('description count: '+str(len(doc.descriptions)))
            for href in doc.links:
                parsed=urllib.parse.urlsplit(urllib.parse.urljoin(url,href))
                if parsed.netloc==origin and not parsed.query and parsed.path and not any(x in parsed.path for x in ('wp-admin','wp-login','feed','wp-content','wp-json')):
                    extra.add(urllib.parse.urlunsplit((parsed.scheme,parsed.netloc,parsed.path,'','')))
        rows.append({'url':url,'status':status,'errors':errors,'warnings':warnings})
    known={r['url'] for r in rows}; todo=sorted(extra-known)[:40]
    with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
        for url,(status,_) in zip(todo,pool.map(fetch,todo)):
            rows.append({'url':url,'status':status,'errors':[] if status==200 else ['Internal link request failed'],'warnings':[]})
    return {'checked_at':datetime.datetime.now(datetime.timezone.utc).isoformat(),'checks':rows,'failures':sum(bool(r['errors']) for r in rows),'limitations':['Public HTTP checks only; no Search Console data, conversions, server backups or external-link verification.','Warnings require review and are not automatically proof of a defect.']}

def main():
    p=argparse.ArgumentParser(description=__doc__);p.add_argument('--base',default='https://hirogarushumi.com');p.add_argument('--output',default='/tmp/hsh-public-audit.json');a=p.parse_args()
    result=audit(a.base);Path(a.output).write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n');print(json.dumps({'checked':len(result['checks']),'failures':result['failures'],'output':a.output}));return bool(result['failures'])
if __name__=='__main__':raise SystemExit(main())
