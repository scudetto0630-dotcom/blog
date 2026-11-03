import unittest,json,re,html
from pathlib import Path
from html.parser import HTMLParser
ROOT=Path(__file__).resolve().parents[1]
class Links(HTMLParser):
 def __init__(self):super().__init__();self.anchors=[];self.images=[]
 def handle_starttag(self,t,a):
  if t=='a':self.anchors.append(dict(a))
  if t=='img':self.images.append(dict(a))
class AffiliateTests(unittest.TestCase):
 @classmethod
 def setUpClass(cls):
  cls.rows=json.loads((ROOT/'site-content/affiliates/2026-11-03/links.json').read_text());cls.articles=json.loads((ROOT/'hirogaru-home/affiliate-articles.json').read_text());cls.by={r['id']:r for r in cls.rows}
 def test_received_count_and_approved_scope(self):
  self.assertEqual(len(self.rows),24);self.assertEqual(len(self.articles),13);self.assertEqual(len({a['affiliate_id'] for a in self.articles}),13)
  self.assertFalse({'A8-20261103-03','A8-20261103-04','A8-20261103-08','A8-20261103-09','A8-20261103-11','A8-20261103-12'}&{a['affiliate_id'] for a in self.articles})
 def test_original_affiliate_destinations_and_pixels_preserved(self):
  for a in self.articles:
   with self.subTest(a=a['slug']):
    r=self.by[a['affiliate_id']];p=Links();p.feed(a['content']);ad=[x for x in p.anchors if 'px.a8.net' in x.get('href','')]
    self.assertEqual(len(ad),1);self.assertEqual(ad[0]['href'],r['affiliate_url']);self.assertTrue({'nofollow','sponsored'}<=set(ad[0]['rel'].split()))
    original=Links();original.feed(r['source_code']);expected=[i['src'] for i in original.images]
    self.assertEqual([i['src'] for i in p.images],expected)
 def test_substance_structure_and_nonfabrication(self):
  for a in self.articles:
   with self.subTest(a=a['slug']):
    s=a['content'];text=html.unescape(re.sub('<[^>]*>','',s));self.assertGreaterEqual(len(re.sub(r'\s+','',text)),1500)
    self.assertEqual(s.count('<h2 '),7);self.assertIn('<table>',s);self.assertIn('未使用',s);self.assertIn('使用写真ではありません',s)
    self.assertNotRegex(s,r'TODO|HSH_IMAGE:|実際に使ってみた|飲ませてみた|購入しました|必ず治る|治療できます|100％安全')
    self.assertRegex(a['source_sha256'],r'^[a-f0-9]{64}$');self.assertEqual(a['source_status'],'owner_supplied_official_page_snapshot')
 def test_archive_scope_does_not_invent_missing_thirteen(self):
  self.assertEqual(len({r['id'] for r in self.rows}),24)
  self.assertTrue(all(r['received_date']=='2026-11-03' for r in self.rows))
if __name__=='__main__':unittest.main()
