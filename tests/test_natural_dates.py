import json,re,unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
class NaturalDates(unittest.TestCase):
 def test_article_prose_does_not_repeat_calendar_precision(self):
  for name in ['starter','expanded','affiliate']:
   for a in json.loads((ROOT/f'hirogaru-home/{name}-articles.json').read_text()):
    with self.subTest(slug=a['slug']):
     prose=re.sub(r'<p class="hsh-source-note">.*?</p>','',a['content'],flags=re.S)
     self.assertNotRegex(prose,r'20\d{2}年\d{1,2}月')
 def test_profile_and_visit_use_natural_time_but_keep_photographs(self):
  about=json.loads((ROOT/'site-content/about.json').read_text())['content'];visit=json.loads((ROOT/'site-content/hakushu-visit.json').read_text())['content']
  for body in [about,visit]:self.assertNotRegex(body,r'20\d{2}年\d{1,2}月')
  self.assertIn('現在5歳',about);self.assertIn('25年の夏',visit);self.assertEqual(visit.count('class="hsh-photo hsh-visit-photo"'),6)
 def test_enrollment_deadlines_and_source_evidence_dates_remain(self):
  rows={x['slug']:x for x in json.loads((ROOT/'hirogaru-home/affiliate-articles.json').read_text())}
  body=rows['aigan-dobutsu-course']['content']
  for value in ['4月1日時点で満15歳','2月1日〜4月15日','6月1日〜8月15日']:self.assertIn(value,body)
  self.assertIn('2026年11月4日時点',rows['paws-green-deli-cat-food']['content'])
if __name__=='__main__':unittest.main()
