import importlib.util
import json
import tempfile
import unittest
from datetime import date
from pathlib import Path
from app.pubmed import PubMed
spec=importlib.util.spec_from_file_location('fetch',Path(__file__).parents[1]/'tools/fetch_pubmed.py');module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)

class PubMedTests(unittest.TestCase):
    def test_date_partition_no_truncation(self):
        calls=[]
        def transport(endpoint,p):
            calls.append(p)
            large=p['mindate']=='2000-01-01' and p['maxdate']=='2000-01-04'
            result={'count':'10001' if large else '2','idlist':[] if not p['retmax'] else (['1','2'] if p['mindate']=='2000-01-01' else ['3','4'])}
            return json.dumps({'esearchresult':result}).encode()
        self.assertEqual(list(PubMed(transport).ids('EGCG',date(2000,1,1),date(2000,1,4))),['1','2','3','4'])
        self.assertTrue(any(p['mindate']=='2000-01-03' for p in calls))
    def test_single_day_limit_stops(self):
        client=PubMed(lambda *a:json.dumps({'esearchresult':{'count':'10000'}}).encode())
        with self.assertRaises(ValueError):list(client.ids('EGCG',date(2000,1,1),date(2000,1,1)))
    def test_missing_ids_not_success(self):
        client=PubMed(lambda *a:b'<PubmedArticleSet/>')
        with self.assertRaises(ValueError):client.fetch(['123'])
    def test_metadata_does_not_invent_decision(self):
        xml=b'<PubmedArticleSet><PubmedArticle><MedlineCitation><PMID>123</PMID><Article><ArticleTitle>A <i>real</i> title</ArticleTitle><Abstract><AbstractText>Result.</AbstractText></Abstract><Journal><JournalIssue><PubDate><Year>2020</Year></PubDate></JournalIssue></Journal></Article></MedlineCitation></PubmedArticle></PubmedArticleSet>'
        p=PubMed(lambda *a:xml).fetch(['123'])[0]
        self.assertEqual(p['title_en'],'A real title');self.assertIsNone(p['initial_decision']);self.assertEqual(p['checks'],[])
    def test_crash_resume_dedup_and_cache(self):
        class Client:
            fail=True;fetches=[]
            def ids(self,ingredient):return iter(str(i) for i in range(205))
            def fetch(self,ids):
                self.fetches.append(ids)
                if len(self.fetches)==2 and self.fail:raise ConnectionError('outage')
                return [{'pmid':i} for i in ids]
        with tempfile.TemporaryDirectory() as d:
            client=Client();path=str(Path(d)/'pubmed.db')
            with self.assertRaises(ConnectionError):module.retrieve('EGCG',path,client)
            client.fail=False
            self.assertEqual(module.retrieve('EGCG',path,client),205)
            self.assertEqual(len(client.fetches),4)
            self.assertEqual(module.retrieve('EGCG',path,client),205)
            self.assertEqual(len(client.fetches),4)
    def test_search_input_quoted(self):
        seen=[]
        PubMed(lambda _,p:seen.append(p) or b'{"esearchresult":{"count":"0"}}').search('EGCG" OR cancer',date(2000,1,1),date(2000,2,1))
        self.assertEqual(seen[0]['term'],'"EGCG  OR cancer"[Title/Abstract]')
