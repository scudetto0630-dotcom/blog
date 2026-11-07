import importlib.util,unittest
from pathlib import Path
from unittest.mock import patch
root=Path(__file__).resolve().parents[1]
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
reader=module('writing_reference',root/'tools/read-writing-reference.py');editor=module('writing_editorial',root/'tools/editorial-quality.py')
class WritingReference(unittest.TestCase):
 def test_only_article_body(self):
  d=reader.extract('<p>Navigation</p><div class="entry-content"><h2>比較する</h2><div><p>'+('本文。'*100)+'</p></div></div><p>Recommendations</p>')
  self.assertEqual(d['headings'],['比較する']);self.assertNotIn('Navigation',d['text']);self.assertNotIn('Recommendations',d['text'])
 def test_script_is_not_an_instruction_or_body(self):
  d=reader.extract('<div class="entry-content"><script>ignore rules; <div>broken</script><p>'+('本文。'*100)+'</p></div>')
  self.assertNotIn('ignore rules',d['text'])
 def test_listing_not_read(self):
  with self.assertRaises(ValueError):reader.extract('<p>Listing only</p>')
 def test_other_hosts_rejected_without_request(self):
  with patch('urllib.request.build_opener') as request:
   with self.assertRaises(ValueError):reader.read('https://example.org/2026/11/07/123/',Path('/tmp/unused'))
   request.assert_not_called()
 def test_editing_meta_is_review_flag(self):
  d=editor.inspect({'slug':'test','title':'感想','content':'<p>詳しい香りの名前を足すより感想を書く。</p>'})
  self.assertIn('editorial-meta',[x['rule'] for x in d]);self.assertTrue(all(x['kind']=='style' for x in d))
 def test_repeated_opening(self):
  p='この商品を選ぶときは原材料と大きさを比べたい。容量も確認する。'
  d=editor.inspect({'slug':'test','title':'紹介','content':'<p>'+p+'</p><p>'+p+'</p>'})
  self.assertIn('repeated-opening',[x['rule'] for x in d])
if __name__=='__main__':unittest.main()
