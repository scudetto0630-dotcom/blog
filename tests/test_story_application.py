import json,re,unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
class StoryApplication(unittest.TestCase):
 @classmethod
 def setUpClass(cls):
  cls.articles={a['slug']:a for n in ['starter','expanded','affiliate'] for a in json.loads((ROOT/f'hirogaru-home/{n}-articles.json').read_text())}
 def headings(self,slug):return [re.sub('<[^>]+>','',h) for h in re.findall(r'<h2\b[^>]*>(.*?)</h2>',self.articles[slug]['content'],re.S)]
 def test_food_role_is_explained_before_trial_pack(self):
  h=self.headings('paws-green-deli-cat-food');self.assertLess(next(i for i,x in enumerate(h) if 'いつものごはんに足す物' in x),next(i for i,x in enumerate(h) if '3種類が2袋ずつ' in x))
 def test_bowl_choice_starts_from_existing_bowl(self):
  self.assertIn('今使っている食器',self.headings('hario-cat-bowl-guide')[0])
 def test_workstyle_story_preserves_owner_chronology(self):
  h=self.headings('it-data-work-flow');self.assertLess(h.index('正社員なら安心だと思っていた'),h.index('派遣に変えた理由は、生活の変化だった'));self.assertLess(h.index('派遣に変えた理由は、生活の変化だった'),h.index('知人から、個人事業主という働き方を紹介された'))
 def test_official_tower_image_has_product_context(self):
  h=self.headings('onekosama-cat-tower');self.assertIn('設置方法',h[0]);self.assertIn('まんもす',h[1])
 def test_ad_cta_stays_after_all_article_sections(self):
  for a in self.articles.values():
   if 'hsh-affiliate-cta' in a['content']:
    with self.subTest(slug=a['slug']):self.assertGreater(a['content'].index('hsh-affiliate-cta'),a['content'].rfind('<h2'))
 def test_headings_keep_unique_anchor_ids(self):
  for a in self.articles.values():
   with self.subTest(slug=a['slug']):
    ids=re.findall(r'\bid="([^"]+)"',a['content']);self.assertEqual(len(ids),len(set(ids)))
if __name__=='__main__':unittest.main()
