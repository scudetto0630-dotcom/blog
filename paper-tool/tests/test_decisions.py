import tempfile
import unittest
from pathlib import Path
from app.engine.decisions import Classifier,ApprovalRequired,BudgetExceeded,final_decision,validate_decision
from app.engine.ledger import Ledger

D={'status':'adopted','categories':['肌','肝臓'],'human_trial':'effective','reason':'抄録の記載','intervention':'単独投与','evidence':'介入結果'}
class DecisionsTests(unittest.TestCase):
 def test_unapproved_never_calls(self):
  c=Classifier(None,'unused',key='fake',transport=lambda p:self.fail('paid request'))
  with self.assertRaises(ApprovalRequired):c.judge('1','EGCG',{'abstract':'介入結果'},[],0)
 def test_unmapped_category_rejected(self):
  with self.assertRaises(ValueError):validate_decision({**D,'categories':['記憶力']})
 def test_two_reviews_required(self):
  with self.assertRaises(ValueError):final_decision(D,[D])
 def test_disagreement_is_unknown(self):
  self.assertEqual(final_decision(D,[D,{**D,'categories':['肌']}])['status'],'review')
 def test_ledger_cap_cache_ambiguity(self):
  with tempfile.TemporaryDirectory() as d:
   ledger=Ledger(Path(d)/'spending.db','job',1)
   self.assertIsNone(ledger.reserve('r1',0.7))
   with self.assertRaises(BudgetExceeded):ledger.reserve('r2',0.4)
   with self.assertRaises(ApprovalRequired):ledger.reserve('r1',0.7)
   ledger.complete('r1',0.1,D);self.assertEqual(ledger.reserve('r1',0.7),D)
   self.assertIsNone(ledger.reserve('r2',0.8))
 def test_review1_blind_and_review2_context_mock_only(self):
  payloads=[]
  def mock(p):
   payloads.append(p)
   import json
   return {'usage':{'prompt_tokens':20,'completion_tokens':20},'choices':[{'message':{'content':json.dumps(D)}}]}
  with tempfile.TemporaryDirectory() as d:
   c=Classifier(Ledger(Path(d)/'l.db','job',10),'mock',key='fake',approved=True,input_rate=1,output_rate=1,transport=mock)
   initial=c.judge('0','EGCG',{'abstract':'介入結果'},[],0)
   check=c.judge('1','EGCG',{'abstract':'介入結果'},[],1,initial)
   final=c.judge('2','EGCG',{'abstract':'介入結果'},[],2,[initial,check])
   self.assertNotIn('earlier_decisions": [',payloads[1]['messages'][1]['content'])
   self.assertIn('earlier_decisions": [',payloads[2]['messages'][1]['content'])
   self.assertEqual(final_decision(initial,[check,final])['status'],'adopted')

 def test_nonfinite_budget_rejected(self):
  with tempfile.TemporaryDirectory() as d:
   with self.assertRaises(ValueError):Ledger(Path(d)/'l.db','j',float('nan'))
   ledger=Ledger(Path(d)/'l.db','j',1)
   with self.assertRaises(ValueError):ledger.reserve('r',float('nan'))
 def test_request_cache_does_not_cross_jobs(self):
  with tempfile.TemporaryDirectory() as d:
   path=Path(d)/'l.db';a=Ledger(path,'a',1);b=Ledger(path,'b',1)
   a.reserve('r',0.1);a.complete('r',0.1,D)
   with self.assertRaises(ValueError):b.reserve('r',0.1)
