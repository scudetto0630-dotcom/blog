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
        cls.current=cls.bundle+[json.loads((ROOT/'site-content/articles/longform'/f"{a['slug']}.json").read_text()) for a in cls.old]
        cls.photos=json.loads((ROOT/'hirogaru-home/photo-assets.json').read_text())
    def test_every_article_is_at_least_three_times_its_previous_length(self):
        self.assertEqual(len(self.current),13)
        for a in self.current:
            with self.subTest(slug=a['slug']):
                old=next(x for x in self.previous if x['slug']==a['slug'])
                self.assertGreaterEqual(text_count(a['content']),3*text_count(old['content']))
    def test_original_links_and_anchor_ids_are_retained(self):
        for a in self.current:
            old=next(x for x in self.previous if x['slug']==a['slug'])
            for attr in ['href','id']:
                before=set(re.findall(r'\b'+attr+r'="([^"]+)"',old['content']))
                after=set(re.findall(r'\b'+attr+r'="([^"]+)"',a['content']))
                self.assertTrue(before<=after,(a['slug'],attr,before-after))
    def test_body_photos_do_not_reuse_article_covers_or_each_other(self):
        used=list(self.photos['articles'].values())
        for slug in self.photos['articles']:
            keys=self.photos['inline'].get(slug,[])
            if slug=='manmos-cat-life':keys=['manmos-lying','manmos-sitting','manmos-back','manmos-stretch']
            self.assertGreaterEqual(len(keys),2,slug)
            used.extend(keys)
        self.assertEqual(len(used),len(set(used)))
        for key in used:self.assertIn(key,self.photos['assets'])
    def test_three_distinct_category_photos(self):
        t=self.photos['topics'];self.assertEqual(set(t),{'cats','whisky','it-freelance'})
        self.assertEqual(len(set(t.values())),3)
        self.assertFalse(set(t.values())&set(self.photos['articles'].values()))
    def test_no_unrequested_business_term_or_unexpanded_placeholders(self):
        for a in self.current:
            self.assertNotRegex(a['content'],r'(?i)bpaas|TODO|HSH_IMAGE:')
            self.assertGreaterEqual(a['content'].count('<h2'),7)
if __name__=='__main__':unittest.main()
