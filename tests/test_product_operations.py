import importlib.util,json,unittest
from pathlib import Path
from unittest.mock import patch
ROOT=Path(__file__).resolve().parents[1]
def module(name,file):
 spec=importlib.util.spec_from_file_location(name,ROOT/file);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);return m
register=module('register','tools/product-link-register.py');audit=module('sourceaudit','tools/audit-product-sources.py')
class ProductOperations(unittest.TestCase):
 def test_register_retains_all_tracking_urls_without_requesting_them(self):
  with patch('urllib.request.urlopen',side_effect=AssertionError('No network')):
   rows=register.build(checked_at='2026-11-08')
  self.assertEqual(len(rows),26)
  self.assertEqual(sum(row['link_type']=='広告リンク' for row in rows),6)
  for row in rows:
   self.assertTrue(row['affiliate_links']);self.assertTrue(row['product_images']);self.assertEqual(row['publication_status'],'not_verified');self.assertEqual(row['checked_at'],'2026-11-08')
 def test_source_200_with_sold_out_is_a_review_signal_not_a_verdict(self):
  class Response:
   status=200;url='https://example.com/product';headers={}
   def __enter__(self):return self
   def __exit__(self,*args):pass
   def read(self,*args):return ('<title>猫用フード</title><main>在庫切れ'+('商品の案内です。'*100)+'</main><script>販売終了</script>').encode()
  with patch('urllib.request.urlopen',return_value=Response()):r=audit.check({'slug':'test','source_url':'https://example.com/product'})
  self.assertEqual(r['result'],'retrieved');self.assertEqual(r['availability_review_signals'],['在庫切れ']);self.assertEqual(len(r['sampled_text_sha256']),64)
 def test_advertiser_sources_reject_tracking_urls(self):
  for url in ['https://px.a8.net/svt/test','http://example.com']:
   with self.assertRaises(ValueError):audit.check({'slug':'test','source_url':url})
if __name__=='__main__':unittest.main()
