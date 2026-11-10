import json,re,unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
class OwnerAnswers(unittest.TestCase):
 def setUp(self):
  self.rows={a['slug']:a for n in ['starter','expanded','affiliate'] for a in json.loads((ROOT/f'hirogaru-home/{n}-articles.json').read_text())}
 def test_corrected_arboralis_across_active_copy(self):
  active='\n'.join(a['content'] for a in self.rows.values())+(ROOT/'hirogaru-home/growth.php').read_text()+(ROOT/'hirogaru-home/reader-brushup.php').read_text()+json.loads((ROOT/'site-content/about.json').read_text())['content']
  self.assertNotRegex(active,r'(?:実際には|実際は|想像ほど|想像したほど)甘くなかった')
  self.assertIn('甘くて飲みやすかった',active)
 def test_untried_bottle_outside_tasted_cards(self):
  php=(ROOT/'hirogaru-home/growth.php').read_text();tastes=php.split('function hsh_growth_tastes()')[1].split('function hsh_growth_whisky()')[0]
  self.assertNotIn('オーチャードハウス',tastes);self.assertIn('まだ買っていない',php)
 def test_generated_gift_set_does_not_replace_existing_images(self):
  a=self.rows['glenlivet-12-first-bottles'];self.assertNotRegex(a['content'],r'AI生成|生成画像|生成イメージ');self.assertIn('hsh-product-illustration',a['content']);self.assertNotIn('ちゃんぽん',a['content'])
 def test_personal_highball_amount_separate_from_examples(self):
  body=self.rows['highball-measure-and-adjust']['content'];self.assertIn('普段入れるウイスキーは30ml',body);self.assertIn('計算例',body)
 def test_public_alts_do_not_restore_generation_labels(self):
  m=json.loads((ROOT/'hirogaru-home/photo-assets.json').read_text())
  for key,a in m['assets'].items():
   with self.subTest(asset=key):self.assertNotRegex(a['alt'],r'AI生成|生成画像|生成イメージ')
  for a in self.rows.values():self.assertNotRegex(a['content'],r'AI生成|生成画像|生成イメージ')
if __name__=='__main__':unittest.main()
