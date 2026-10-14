import gzip
import json
import tempfile
import unittest
from pathlib import Path
from app.engine.pipeline import Pipeline, teacher_examples
from test_decisions import D
class PipelineTests(unittest.TestCase):
 def test_persisted_round_resume(self):
  class Mock:
   model='mock';calls=[];fail=True
   def judge(self,id,ingredient,paper,examples,round_number,prior):
    self.calls.append(round_number)
    if round_number==1 and self.fail:raise ConnectionError('interrupted')
    return D
  with tempfile.TemporaryDirectory() as d:
   bundle=Path(d)/'bundle.gz'
   with gzip.open(bundle,'wt') as f:json.dump({'data':[]},f)
   mock=Mock();pipe=Pipeline(Path(d)/'job.db',mock,bundle);paper={'pmid':'123','title_en':'Test'}
   with self.assertRaises(ConnectionError):pipe.process('job','EGCG',paper)
   mock.fail=False;result=pipe.process('job','EGCG',paper)
   self.assertEqual(mock.calls,[0,1,1,2]);self.assertEqual(len(result['checks']),2)
   pipe.process('job','EGCG',paper);self.assertEqual(mock.calls,[0,1,1,2])
 def test_holdout_never_in_examples(self):
  import hashlib
  with tempfile.TemporaryDirectory() as d:
   rows=[{'pmid':str(i),'title_en':'EGCG study','annotations':{'EGCG':{'human_main':['肌']}}} for i in range(100)]
   bundle=Path(d)/'bundle.gz'
   with gzip.open(bundle,'wt') as f:json.dump({'data':rows},f)
   examples=teacher_examples(bundle,{'pmid':'1','title_en':'EGCG'},100)
   self.assertNotIn('1',[e['pmid'] for e in examples])
   self.assertTrue(all(int(hashlib.sha256(('paperdesk-v1:'+e['pmid']).encode()).hexdigest(),16)%5!=0 for e in examples))

 def test_completed_verifications_reused_for_other_product(self):
  class Mock:
   model='mock';calls=0
   def judge(self,*args):self.calls+=1;return D
  with tempfile.TemporaryDirectory() as d:
   bundle=Path(d)/'bundle.gz'
   with gzip.open(bundle,'wt') as f:json.dump({'data':[]},f)
   mock=Mock();pipeline=Pipeline(Path(d)/'jobs.db',mock,bundle);paper={'pmid':'123','title_en':'Same study'}
   first=pipeline.process('product1','EGCG',paper);second=pipeline.process('product2','EGCG',paper)
   self.assertEqual(mock.calls,3);self.assertFalse(first['cache_reused']);self.assertTrue(second['cache_reused'])
   pipeline.process('product3','EGCG',{**paper,'abstract':'Updated evidence'});self.assertEqual(mock.calls,6)
