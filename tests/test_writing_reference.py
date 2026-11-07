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
 def test_author_with_nested_image(self):
  page='<li class="author"><a href="https://rocketnews24.com/author/ahiruneko/" rel="author"><img src="photo.webp">あひるねこ</a></li>'
  self.assertEqual(reader.verify_author(page,'ahiruneko')[0]['name'],'あひるねこ')
 def test_other_author_rejected(self):
  with self.assertRaises(ValueError):reader.verify_author('<a href="https://rocketnews24.com/author/other/" rel="author">別の人</a>','ahiruneko')
 def test_navigation_does_not_verify_author(self):
  with self.assertRaises(ValueError):reader.verify_author('<nav><a href="https://rocketnews24.com/author/ahiruneko/">あひるねこ</a></nav>','ahiruneko')
 def test_offsite_author_link_rejected(self):
  with self.assertRaises(ValueError):reader.verify_author('<a href="https://example.org/author/ahiruneko/" rel="author">あひるねこ</a>','ahiruneko')
 def test_related_byline_does_not_replace_primary_author(self):
  page='<a href="https://rocketnews24.com/author/other/" rel="author">別の人</a><aside><a href="https://rocketnews24.com/author/ahiruneko/" rel="author">あひるねこ</a></aside>'
  with self.assertRaises(ValueError):reader.verify_author(page,'ahiruneko')
 def test_copy_merge_preserves_published_figure(self):
  before='<p>前の文章です。</p><!--HSH_IMAGE:diagram-->'
  after='<p>整えた文章です。</p><!--HSH_IMAGE:diagram-->'
  live='<p>前の文章です。</p><figure><a href="https://example.org/diagram.webp"><img src="diagram.webp"></a></figure>'
  merged=editor.merge_paragraph_corrections(before,after,live)
  self.assertIn('<figure>',merged);self.assertIn('整えた文章',merged);self.assertNotIn('HSH_IMAGE:',merged)
 def test_copy_merge_refuses_stale_paragraph(self):
  with self.assertRaises(ValueError):editor.merge_paragraph_corrections('<p>前</p>','<p>後</p>','<p>別の変更</p>')
 def test_copy_merge_refuses_numeric_changes(self):
  with self.assertRaises(ValueError):editor.merge_paragraph_corrections('<p>5kg</p>','<p>6kg</p>','<p>5kg</p>')
 def test_copy_merge_refuses_tracking_pixel_loss(self):
  with self.assertRaises(ValueError):editor.merge_paragraph_corrections('<p>広告<img src="tracking.gif"></p>','<p>広告</p>','<p>広告<img src="tracking.gif"></p>')
 def test_related_paragraphs_can_be_joined(self):
  before='<p>一つ目。</p>\n<p>同じ話の続き。</p><!--HSH_IMAGE:diagram-->'
  after='<p>一つ目。同じ話の続き。</p><!--HSH_IMAGE:diagram-->'
  live='<p>一つ目。</p>\n<p>同じ話の続き。</p><figure><img src="diagram.webp"></figure>'
  merged=editor.merge_paragraph_corrections(before,after,live)
  self.assertIn('<p>一つ目。同じ話の続き。</p>',merged);self.assertIn('<figure>',merged)
 def test_join_cannot_cross_a_heading(self):
  with self.assertRaises(ValueError):editor.merge_paragraph_corrections('<p>前。</p><h2 id="x">別の話</h2><p>後。</p>','<p>前。後。</p><h2 id="x">別の話</h2>','<p>前。</p><h2 id="x">別の話</h2><p>後。</p>')
 def test_copy_merge_refuses_heading_changes(self):
  with self.assertRaises(ValueError):editor.merge_paragraph_corrections('<h2 id="a">前</h2><p>文</p>','<h2 id="b">後</h2><p>文</p>','<p>文</p>')
 def test_editing_meta_is_review_flag(self):
  d=editor.inspect({'slug':'test','title':'感想','content':'<p>詳しい香りの名前を足すより感想を書く。</p>'})
  self.assertIn('editorial-meta',[x['rule'] for x in d]);self.assertTrue(all(x['kind']=='style' for x in d))
 def test_repeated_opening(self):
  p='この商品を選ぶときは原材料と大きさを比べたい。容量も確認する。'
  d=editor.inspect({'slug':'test','title':'紹介','content':'<p>'+p+'</p><p>'+p+'</p>'})
  self.assertIn('repeated-opening',[x['rule'] for x in d])
if __name__=='__main__':unittest.main()
