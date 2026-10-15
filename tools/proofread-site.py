#!/usr/bin/env python3
import json,urllib.request,urllib.parse,concurrent.futures
from html.parser import HTMLParser
from pathlib import Path
class Node:
 def __init__(self,tag='root',attrs=None,parent=None):self.tag=tag;self.attrs=dict(attrs or []);self.children=[];self.parent=parent
class Parse(HTMLParser):
 def __init__(self):super().__init__();self.root=Node();self.current=self.root
 def handle_starttag(self,tag,attrs):
  n=Node(tag,attrs,self.current);self.current.children.append(n)
  if tag not in ('area','base','br','col','embed','hr','img','input','link','meta','param','source','track','wbr'):self.current=n
 def handle_endtag(self,tag):
  n=self.current
  while n.parent:
   if n.tag==tag:self.current=n.parent;return
   n=n.parent
 def handle_data(self,d):self.current.children.append(d)
def hidden(n):
 return n.tag in ('script','style','noscript','head','svg') or any(x in n.attrs.get('class','').split() for x in ['sns-share','sns-follow','mobile-footer-menu-buttons','hidden-fields-container','screen-reader-response']) or n.attrs.get('aria-hidden')=='true'
def text(n):
 if isinstance(n,str):return n
 if hidden(n):return ''
 return ''.join(text(x) for x in n.children)
blocks={'h1','h2','h3','h4','h5','h6','p','li','figcaption','th','td','label','button','a','time','small'}
def nestedblock(n):
 return any(not isinstance(x,str) and (x.tag in (blocks-{'a'}) or nestedblock(x)) for x in n.children)
def walk(n):
 if isinstance(n,str):
  t=' '.join(n.split())
  return ['### '+t] if t else []
 if hidden(n):return []
 if n.tag in blocks and not nestedblock(n):
  t=' '.join(text(n).split())
  if not t:return []
  is_heading=n.tag.startswith('h') and n.tag[1:].isdigit()
  is_label=(n.tag=='p' and (t.endswith(('…','...')) or any(x in n.attrs.get('class','').split() for x in ['hsh-kicker','hsh-eyebrow']) or t.startswith('制定日：'))) or n.tag not in ('p','figcaption') or any(x in n.attrs.get('class','').split() for x in ['entry-card-snippet']) or (n.tag=='p' and '。' not in t and any(isinstance(x,Node) and x.tag=='a' for x in n.children))
  return [('## ' if is_heading else '### ' if is_label else '')+t]
 out=[]
 for x in n.children:out+=walk(x)
 if n.tag in ['img','input']:
  for k in ['alt','placeholder']:
   if n.attrs.get(k):out.append('### '+n.attrs[k])
 return out
def fetch(item):
 name,u=item;p=urllib.parse.urlsplit(u);u=urllib.parse.urlunsplit((p.scheme,p.netloc,urllib.parse.quote(p.path,safe='/%'),p.query,''))
 with urllib.request.urlopen(urllib.request.Request(u,headers={'Cache-Control':'no-cache','User-Agent':'HirogaruProofreading bot'}),timeout=40) as r:h=r.read().decode()
 parser=Parse();parser.feed(h);body='\n\n'.join(walk(parser.root))+'\n'
 (dest/(name+'.md')).write_text(body);(dest/(name+'.html')).write_text(h)
 return {'name':name,'url':u,'characters':len(body),'blocks':len(walk(parser.root))}
def main():
 import argparse
 parser=argparse.ArgumentParser(description="Collect public Japanese site copy for textlint; never uses credentials.")
 parser.add_argument('--output',required=True)
 parser.add_argument('--from-html',help='Re-extract a saved corpus without changing its original HTML')
 args=parser.parse_args()
 global dest
 dest=Path(args.output);dest.mkdir(parents=True,exist_ok=True)
 if args.from_html:
  source=Path(args.from_html)
  results=json.loads((source/'inventory.json').read_text())
  for item in results:
   html=(source/(item['name']+'.html')).read_text();p=Parse();p.feed(html)
   (dest/(item['name']+'.md')).write_text('\n\n'.join(walk(p.root))+'\n')
 else:
  def catalog(kind):
   with urllib.request.urlopen('https://hirogarushumi.com/?rest_route=/wp/v2/'+kind+'&per_page=100',timeout=40) as response:return json.load(response)
  pages=catalog('pages');posts=catalog('posts');categories=catalog('categories')
  urls=[('page-'+str(p['id']),p['link']) for p in pages]+[('post-'+str(p['id']),p['link']) for p in posts]+[('category-'+p['slug'],p['link']) for p in categories]+[('author','https://hirogarushumi.com/author/hirogarulife/'),('search','https://hirogarushumi.com/?s=CSV')]
  with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:results=list(pool.map(fetch,urls))
 (dest/'inventory.json').write_text(json.dumps(results,ensure_ascii=False,indent=2)+'\n')
 print(json.dumps({'pages':len(results),'output':str(dest)}))
if __name__=='__main__':main()
