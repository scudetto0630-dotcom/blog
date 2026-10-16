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
  a=json.loads((ROOT/'content/articles.json').read_text());self.assertEqual(len(a),9);self.assertEqual(len({x['slug'] for x in a}),9)
  for x in a:
   self.assertGreaterEqual(len(x['sections']),4)
   self.assertNotIn('用品',json.dumps(x,ensure_ascii=False))
   self.assertNotIn('亡くなるまで',json.dumps(x,ensure_ascii=False))
 def test_sitemap_and_canonicals(self):
  import xml.etree.ElementTree as ET
  root=ET.parse(ROOT/'build/sitemap.xml').getroot();urls=[x.text for x in root.iter('{http://www.sitemaps.org/schemas/sitemap/0.9}loc')];self.assertEqual(len(urls),16);self.assertEqual(len(set(urls)),16)
  for url in urls:
   relative=url.removeprefix(build.SITE_URL);p=ROOT/'build'/relative/'index.html';self.assertTrue(p.is_file());self.assertIn('rel="canonical" href="'+url+'"',p.read_text())
 def test_stage_links_and_diagrams(self):
  home=(ROOT/'build/index.html').read_text();self.assertIn('guides/?stage=before',home);self.assertIn('guides/?stage=first',home);self.assertIn('guides/?stage=settled',home)
  for slug in ['first-days','safe-home','vet-visit']:self.assertIn('care-diagram',(ROOT/'build/articles'/slug/'index.html').read_text())
  for a in build.A:
   text=(ROOT/'build/articles'/a['slug']/'index.html').read_text();self.assertIn('source-box',text);self.assertIn('本文更新日',text);self.assertIn('related-reading',text);self.assertIn('corrections/?page=',text)
 def test_unverified_sources_are_not_claimed_as_reviewed(self):
  for a in build.A:
   text=(ROOT/'build/articles'/a['slug']/'index.html').read_text();self.assertIn('外部資料との照合は完了していません',text);self.assertNotIn('参照先の内容を確認した日',text)
 def test_local_freshness(self):
  import enhancements as extra,datetime
  day=datetime.date(2026,10,16);fresh={'title':'Test listing','official_url':'https://example.test/','verified_at':'2026-10-15','expires_at':'2026-12-31','event_start':'2026-10-20','event_end':'2026-10-20'}
  data={'organizations':[],'events':[fresh]};self.assertEqual(len(extra.current_local(data,day)[1]),1);markup=extra.local_cards(data,day);self.assertIn('NKT_FRESH_UNTIL:2026-10-20',markup)
  old=dict(fresh,verified_at='2026-10-01');self.assertEqual(extra.current_local({'events':[old]},day)[1],[])
  unknown=dict(fresh,verified_at=None);self.assertEqual(extra.current_local({'events':[unknown]},day)[1],[])
  ended=dict(fresh,event_end='2026-10-15');self.assertEqual(extra.current_local({'events':[ended]},day)[1],[])
 def test_maintenance_flags_unverified_and_overdue(self):
  import audit,datetime
  report=audit.editorial_report(datetime.date(2026,10,16));self.assertEqual(len(report['unverified_references']),3);self.assertEqual(report['overdue_articles'],[])
  report=audit.editorial_report(datetime.date(2027,2,1));self.assertEqual(len(report['overdue_articles']),9)
 def test_escape_untrusted_content(self):
  self.assertEqual(build.e('<script>"'), '&lt;script&gt;&quot;')
 def test_no_tracking_or_external_dependencies(self):
  for p in (ROOT/'build').rglob('*.html'):
   s=p.read_text();self.assertIn('noindex,nofollow' if p.name=='404.html' or p.parent.name=='corrections' else 'index,follow,max-image-preview:large',s);self.assertNotIn('制作中',s);self.assertNotIn('ねこのはじめ',s);self.assertNotRegex(s,r'<script[^>]+src="https?');self.assertNotIn('googletagmanager',s)
if __name__=='__main__':unittest.main()
