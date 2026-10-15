import unittest,json,sys,re
from pathlib import Path
from html.parser import HTMLParser
ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'tools'))
import build
class Links(HTMLParser):
 def __init__(self):super().__init__();self.links=[]
 def handle_starttag(self,t,attrs):
  d=dict(attrs)
  if t=='a' and 'href' in d:self.links.append(d['href'])
class Site(unittest.TestCase):
 def test_internal_links(self):
  build.build(ROOT/'build')
  for p in (ROOT/'build').rglob('*.html'):
   parser=Links();parser.feed(p.read_text())
   for link in parser.links:
    if link.startswith(('https:','#')):continue
    target=(ROOT/'build'/link.split('#')[0].split('?')[0]).resolve()
    self.assertTrue(target.is_relative_to(ROOT/'build'))
    self.assertTrue(target.is_file() or (target/'index.html').is_file(),(p,link))
 def test_article_contract(self):
  a=json.loads((ROOT/'content/articles.json').read_text());self.assertEqual(len(a),6);self.assertEqual(len({x['slug'] for x in a}),6)
  for x in a:
   self.assertGreaterEqual(len(x['sections']),4)
   self.assertNotIn('用品',json.dumps(x,ensure_ascii=False))
   self.assertNotIn('亡くなるまで',json.dumps(x,ensure_ascii=False))
 def test_escape_untrusted_content(self):
  self.assertEqual(build.e('<script>"'), '&lt;script&gt;&quot;')
 def test_no_tracking_or_external_dependencies(self):
  for p in (ROOT/'build').rglob('*.html'):
   s=p.read_text();self.assertIn('noindex,nofollow',s);self.assertNotRegex(s,r'<script[^>]+src="https?');self.assertNotIn('googletagmanager',s)
if __name__=='__main__':unittest.main()
