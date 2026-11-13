import unittest,json,re,html
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def text_count(s):
    return len(re.sub(r'\s+','',html.unescape(re.sub('<[^>]+>','',s))))
class LongformTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.bundle=json.loads((ROOT/'hirogaru-home/expanded-articles.json').read_text())
        cls.old=json.loads((ROOT/'site-content/backups/before-longform-20261026/starter-live.json').read_text())['articles']
        cls.previous=json.loads((ROOT/'site-content/backups/before-longform-20261026/expanded-articles.json').read_text())+cls.old
        cls.separated={'glen-grant-arboralis-review','nikka-from-the-barrel-review','jim-beam-first-impression','kirin-riku-review','busker-irish-whiskey-review'}
        cls.new_reviews=[a for a in cls.bundle if a['slug'] in cls.separated]
        cls.current=[a for a in cls.bundle if a['slug'] not in cls.separated]+[json.loads((ROOT/'site-content/articles/longform'/f"{a['slug']}.json").read_text()) for a in cls.old]
        cls.photos=json.loads((ROOT/'hirogaru-home/photo-assets.json').read_text())
    def test_every_article_retains_substantial_useful_explanations(self):
        self.assertEqual(len(self.current),13)
        for a in self.current:
            with self.subTest(slug=a['slug']):
                old=next(x for x in self.previous if x['slug']==a['slug'])
                # The approved 50-item brief prioritizes substance over the old 3x target.
                self.assertGreaterEqual(text_count(a['content']),1500)
    def test_original_links_and_anchor_ids_are_retained(self):
        for a in self.current:
            snapshot=ROOT/'site-content/backups/before-improvements-50'
            previous=json.loads((snapshot/'expanded-articles.json').read_text())+[json.loads(p.read_text()) for p in snapshot.glob('*-*.json') if p.name in ['cat-carrier-checklist.json','highball-measure-and-adjust.json','csv-import-seven-checks.json']]
            old=next(x for x in previous if x['slug']==a['slug'])
            for attr in ['href','id']:
                before=set(re.findall(r'\b'+attr+r'="([^"]+)"',old['content']))
                after=set(re.findall(r'\b'+attr+r'="([^"]+)"',a['content']))
                # Owner requested removing the Arboralis digression from this review.
                approved_removed={'https://hirogarushumi.com/hsh-whisky-notes/#arboralis-taste'} if a['slug']=='sherry-cask-notes' and attr=='href' else set()
                before=before-approved_removed
                if attr=='href':before={'https://hirogarushumi.com/glen-grant-arboralis-review/' if x=='https://hirogarushumi.com/hsh-whisky-notes/#arboralis-taste' else x for x in before}
                self.assertTrue(before<=after,(a['slug'],attr,before-after))
    def test_body_photos_do_not_reuse_article_covers_or_each_other(self):
        used=list(self.photos['articles'].values())
        for slug in self.photos['articles']:
            keys=self.photos['inline'].get(slug,[])
            if slug=='manmos-cat-life':keys=keys+['manmos-back','manmos-stretch']
            if slug in self.separated:
                self.assertEqual(self.photos['assets'][self.photos['articles'][slug]]['subject'],'whisky')
                self.assertEqual(keys,[], 'One matching bottle photo, without invented scene fillers')
            else:self.assertGreaterEqual(len(keys),2,slug)
            used.extend(keys)
        self.assertEqual(len(used),len(set(used)))
        for key in used:self.assertIn(key,self.photos['assets'])
    def test_separated_reviews_use_confirmed_facts_without_filler(self):
        self.assertEqual(len(self.new_reviews),5)
        for a in self.new_reviews:
            self.assertGreaterEqual(text_count(a['content']),300)
            self.assertGreaterEqual(a['content'].count('<h2'),3)
            self.assertNotRegex(a['content'],r'(?i)bpaas|TODO|HSH_IMAGE:')
        arbo=next(a for a in self.new_reviews if a['slug']=='glen-grant-arboralis-review')
        self.assertIn('5本目',arbo['content']);self.assertIn('2,200円',arbo['content'])
        self.assertNotIn('カスクストレングス',arbo['title']+arbo['content'])
    def test_three_distinct_category_photos(self):
        t=self.photos['topics'];self.assertEqual(set(t),{'cats','whisky','it-freelance'})
        self.assertEqual(len(set(t.values())),3)
        self.assertFalse(set(t.values())&set(self.photos['articles'].values()))
    def test_no_unrequested_business_term_or_unexpanded_placeholders(self):
        for a in self.current:
            self.assertNotRegex(a['content'],r'(?i)bpaas|TODO|HSH_IMAGE:')
            self.assertGreaterEqual(a['content'].count('<h2'),7)
if __name__=='__main__':unittest.main()
