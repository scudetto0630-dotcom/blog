import importlib.util, unittest
from pathlib import Path
p=Path(__file__).resolve().parents[1]/'tools/editorial-quality.py';s=importlib.util.spec_from_file_location('editorial_quality',p);m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
class EditorialQuality(unittest.TestCase):
 def test_affiliate_errors(self):
  f=m.inspect({'slug':'sample','title':'犬用フード','content':'<p>必ず治る</p>'},True)
  self.assertTrue({'affiliate-title','ad-integrity','scope','unsupported-claim'}<=set(x['rule'] for x in f))
 def test_no_fabricated_use(self):
  f=m.inspect({'slug':'sample','title':'【ご紹介】猫用フード','content':'広告 <a href="https://px.a8.net/x">公式</a>実際に使って感じた'},True)
  self.assertIn('experience-provenance',[x['rule'] for x in f])
 def test_fact_signature(self):
  self.assertNotEqual(m.facts('<p>5.5kg</p>'),m.facts('<p>6kg</p>'))
  self.assertEqual(m.facts('<p>5.5kg</p>'),m.facts('<div><strong>5.5kg</strong></div>'))
 def test_style_not_errors(self):
  f=m.inspect({'slug':'sample','title':'猫','content':'<p>'+('文'*126)+'。</p>'})
  self.assertEqual({x['kind'] for x in f},{'style'})
 def test_table_cells_are_not_one_sentence(self):
  f=m.inspect({'slug':'sample','title':'表','content':'<table>'+''.join('<tr><td>'+('項目'*25)+'</td><td>説明</td></tr>' for _ in range(6))+'</table>'})
  self.assertNotIn('long-sentence',[x['rule'] for x in f])
 def test_catalog_integrity(self):
  d=m.run();self.assertEqual(d['articles'],33);self.assertEqual([x for x in d['flags'] if x['kind']=='error'],[])
if __name__=='__main__':unittest.main()
