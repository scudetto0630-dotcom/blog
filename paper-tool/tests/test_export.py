import io
import unittest
import zipfile
from app.engine.export import export_results
from app.taxonomy import NAMES
from test_decisions import D
class ExportTests(unittest.TestCase):
 def row(self,pmid,year,month,status='adopted'):
  return {'paper':{'pmid':pmid,'title_en':'Title '+pmid,'title_ja':'題名','publication_date':{'Year':year,'Month':month},'pubmed_url':'https://pubmed.ncbi.nlm.nih.gov/'+pmid+'/'},'ingredient':'EGCG','initial_decision':D,'checks':[{'round':1,'decision':D},{'round':2,'decision':D}],'final_decision':{**D,'status':status}}
 def test_full51_multilabel_order_and_unknown_separation(self):
  rows=[self.row('123','2023','Dec'),self.row('124','2024','Jan'),self.row('125','2024','Feb','review'),self.row('126','2024','Mar','excluded')]
  with zipfile.ZipFile(io.BytesIO(export_results('../製品',rows))) as z:
   self.assertIsNone(z.testzip());self.assertTrue(all(not n.startswith('../') for n in z.namelist()))
   for name in NAMES:self.assertTrue(any(n.endswith('/論文一覧_'+name+'.txt') for n in z.namelist()))
   skin=z.read(next(n for n in z.namelist() if n.endswith('/論文一覧_肌.txt'))).decode();liver=z.read(next(n for n in z.namelist() if n.endswith('/論文一覧_肝臓.txt'))).decode()
   self.assertEqual(skin,liver);self.assertLess(skin.index('124/'),skin.index('123/'));self.assertNotIn('125/',skin)
   self.assertIn('125/',z.read(next(n for n in z.namelist() if n.endswith('/要確認論文.txt'))).decode())
   self.assertIn('126/',z.read(next(n for n in z.namelist() if n.endswith('/除外論文.txt'))).decode())

 def test_dot_only_names_cannot_create_parent_paths(self):
  with zipfile.ZipFile(io.BytesIO(export_results('..',[],['..']))) as z:
   self.assertTrue(all('..' not in n.split('/') and '.' not in n.split('/') for n in z.namelist()))
