import json, unittest
from pathlib import Path
from html.parser import HTMLParser
ROOT=Path(__file__).resolve().parents[1]
class Tags(HTMLParser):
 def __init__(self):super().__init__();self.ids=[];self.images=[]
 def handle_starttag(self,t,a):
  d=dict(a)
  if d.get('id'):self.ids.append(d['id'])
  if t=='img':self.images.append(d)
class ConsolidationContent(unittest.TestCase):
 def test_fixed_group_and_all_product_sections(self):
  plan=json.loads((ROOT/'hirogaru-home/article-consolidations.json').read_text());rows=json.loads((ROOT/'hirogaru-home/affiliate-articles.json').read_text());group=[a for a in rows if a['slug'].startswith('withpety-')];target=next(a for a in group if a['slug']==plan['target']);p=Tags();p.feed(target['content'])
  self.assertEqual(set(plan['sources']),{a['slug'] for a in group if a is not target});self.assertEqual(len(plan['sources']),9)
  self.assertEqual(target['consolidated_affiliate_ids'],[a['affiliate_id'] for a in group]);self.assertEqual(len(target['consolidated_affiliate_ids']),10)
  self.assertIn(plan['marker'],p.ids)
  for anchor in plan['sources'].values():self.assertEqual(p.ids.count(anchor),1)
  self.assertEqual(len(p.ids),len(set(p.ids)))
  manifest=json.loads((ROOT/'hirogaru-home/photo-assets.json').read_text())
  photos=[i for i in p.images if '/assets/photos/' in i.get('src','')];self.assertEqual(len(photos),10)
  for img in photos:
   asset=next(a for a in manifest['assets'].values() if img['src'].endswith('/'+a['file']));self.assertEqual(asset['kind'],'official');self.assertEqual(asset['rights'],'advertiser');self.assertTrue(img['alt'])
  self.assertEqual(len(target['comparison_sources']),10)
  for source in target['comparison_sources']:self.assertEqual(source['checked_at'],'2026-11-25');self.assertRegex(source['sha256'],r'^[a-f0-9]{64}$')
 def test_no_generic_reader_notes_repeated_after_comparison(self):
  source=(ROOT/'hirogaru-home/reader-studio.php').read_text();self.assertIn("hsh_brushup_type($slug)==='product'&&$slug!=='withpety-nekokenshin'",source)
if __name__=='__main__':unittest.main()
