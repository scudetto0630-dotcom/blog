import gzip
import io
import json
import tempfile
import unittest
import zipfile
from pathlib import Path
from app.engine.jobs import ResearchJobs
from test_decisions import D
class Client:
 def ids(self,ingredient):return iter(['123','124','123'])
 def fetch(self,ids):return [{'pmid':i,'title_en':'Title','pubmed_url':'https://pubmed.ncbi.nlm.nih.gov/'+i+'/','abstract':'Results','publication_date':{'Year':'2024','Month':'Jan'}} for i in ids]
class Judge:
 model='mock'
 def judge(self,*a):return D
class JobsTests(unittest.TestCase):
 def setup_jobs(self,d,paid=False):
  bundle=Path(d)/'bundle.gz'
  with gzip.open(bundle,'wt') as f:json.dump({'data':[]},f)
  return ResearchJobs(Path(d)/'jobs.db',bundle,Client(),(lambda id:Judge()) if paid else None)
 def test_unapproved_stops_after_free_retrieval_and_persists(self):
  with tempfile.TemporaryDirectory() as d:
   jobs=self.setup_jobs(d);job=jobs.create({'name':'製品','ingredients':['EGCG']});jobs.process(job['id'])
   current=jobs.get(job['id']);self.assertEqual(current['status'],'awaiting_approval');self.assertEqual(current['total'],2);self.assertIsNone(jobs.archive(job['id']))
   reopened=ResearchJobs(jobs.path,jobs.bundle,Client());self.assertEqual(reopened.get(job['id'])['status'],'awaiting_approval')
 def test_complete_mock_semantics_no_real_api(self):
  with tempfile.TemporaryDirectory() as d:
   jobs=self.setup_jobs(d,True);job=jobs.create({'name':'製品','ingredients':['EGCG']});jobs.process(job['id'])
   current=jobs.get(job['id']);self.assertEqual(current['status'],'completed');self.assertEqual(current['processed'],2)
   with zipfile.ZipFile(io.BytesIO(jobs.archive(job['id']))) as z:self.assertIsNone(z.testzip())

 def test_estimate_without_paid_call_and_explicit_cap_approval(self):
  import os
  from unittest.mock import patch
  from app.engine.decisions import ApprovalRequired
  with tempfile.TemporaryDirectory() as d:
   jobs=self.setup_jobs(d);job=jobs.create({'name':'製品','ingredients':['EGCG']});jobs.process(job['id'])
   with patch.dict(os.environ,{'PAPERDESK_AI_MODEL':'mock','PAPERDESK_INPUT_USD_PER_MILLION':'1','PAPERDESK_OUTPUT_USD_PER_MILLION':'1','PAPERDESK_JOB_CAP_USD':'1'},clear=True):
    estimate=jobs.estimate(job['id']);self.assertGreater(estimate['estimated_100_usd'],0)
    with self.assertRaises(ApprovalRequired):jobs.approve(job['id'],1,estimate['estimate_fingerprint'])
    with patch.dict(os.environ,{'PAPERDESK_ALLOW_PAID':'explicitly-approved','PAPERDESK_AI_KEY':'fake-key-never-used'}):
     with self.assertRaises(ValueError):jobs.approve(job['id'],2,estimate['estimate_fingerprint'])
     self.assertEqual(jobs.approve(job['id'],.5,estimate['estimate_fingerprint'])['approved'],1)
 def test_zero_papers_has_empty_51_output_and_no_paid_call(self):
  class Empty(Client):
   def ids(self,ingredient):return iter([])
  with tempfile.TemporaryDirectory() as d:
   jobs=self.setup_jobs(d);jobs.client=Empty();job=jobs.create({'name':'製品','ingredients':['EGCG']});jobs.process(job['id'])
   self.assertEqual(jobs.get(job['id'])['status'],'completed')
   with zipfile.ZipFile(io.BytesIO(jobs.archive(job['id']))) as z:
    self.assertEqual(len([n for n in z.namelist() if '/ヒト試験効果あり/' not in n and '/論文一覧_' in n]),51)
