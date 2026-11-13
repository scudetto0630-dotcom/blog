import importlib.util,datetime,json,os,tempfile,unittest,urllib.request
from pathlib import Path
from unittest.mock import patch
ROOT=Path(__file__).resolve().parents[1]
def module(n,p):
    s=importlib.util.spec_from_file_location(n,ROOT/p);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
gsc=module('gsc50','tools/search-console-sync.py')
planning=module('plan50','tools/growth-planning-report.py')
summary=module('summary50','tools/operations-summary.py')
sources=module('sources50','tools/audit-product-sources.py')
class SearchOperations(unittest.TestCase):
    def periods(self):
        end=datetime.date(2026,11,10);start=end-datetime.timedelta(days=27)
        daily=[{'date':(end-datetime.timedelta(days=i)).isoformat()} for i in range(56)]
        row={'page':'https://hirogarushumi.com/test/','query':'private query','device':'MOBILE','country':'jpn','clicks':1,'impressions':100,'ctr':.01,'position':5}
        return start,end,daily,row
    def test_missing_credentials_make_no_network_request(self):
        with patch.dict(os.environ,{},clear=True),patch.object(gsc,'query',side_effect=AssertionError('No network')):
            self.assertIsNone(gsc.credential_info())
    def test_malicious_token_endpoint_is_rejected(self):
        bad={'type':'service_account','token_uri':'https://example.com/token','private_key':'secret'}
        with patch.dict(os.environ,{'GSC_SERVICE_ACCOUNT_JSON':json.dumps(bad)},clear=True):
            with self.assertRaises(ValueError):gsc.credential_info()
    def test_reader_scope_and_property_are_fixed(self):
        calls=[]
        def fake(token,body):calls.append(body);return {'rows':[]}
        rows,cut=gsc.fetch_rows('not-a-real-token','2026-10-01','2026-10-28',gsc.DIMS,fake)
        self.assertEqual(rows,[]);self.assertFalse(cut);self.assertEqual(calls[0]['dataState'],'final')
        self.assertEqual(calls[0]['dimensions'],['page','query','device','country']);self.assertTrue(gsc.SCOPE.endswith('webmasters.readonly'))
        self.assertIn('hirogarushumi.com',gsc.ENDPOINT);self.assertTrue(gsc.ENDPOINT.endswith('/searchAnalytics/query'))
    def test_empty_api_is_not_fabricated_as_zero_traffic(self):
        r=gsc.collect('token',lambda *args:{'rows':[]},datetime.date(2026,11,13))
        self.assertEqual(r['state'],'no_finalized_data');self.assertIsNone(r['analysis'])
    def test_missing_dates_block_ctr_review(self):
        start,end,days,row=self.periods()
        d=gsc.analyze([row],[row],days[:-1],start.isoformat(),end.isoformat())
        self.assertFalse(d['equal_periods_have_all_dates']);self.assertEqual(d['ctr_review_candidates'],[])
    def test_only_same_country_device_page_query_are_compared(self):
        start,end,days,row=self.periods();old=dict(row,country='usa')
        d=gsc.analyze([row],[old],days,start.isoformat(),end.isoformat())
        self.assertTrue(d['equal_periods_have_all_dates']);self.assertEqual(d['ctr_review_candidates'],[])
        d=gsc.analyze([row],[row],days,start.isoformat(),end.isoformat());self.assertEqual(len(d['ctr_review_candidates']),1)
    def test_multi_page_query_is_only_a_candidate(self):
        start,end,days,row=self.periods();other=dict(row,page='https://hirogarushumi.com/other/')
        d=gsc.analyze([row,other],[row],days,start.isoformat(),end.isoformat())
        self.assertIn('not_proof',d['multi_page_query_candidates'][0]['decision'])
        other['country']='usa';d=gsc.analyze([row,other],[row],days,start.isoformat(),end.isoformat())
        self.assertEqual(d['multi_page_query_candidates'],[])
    def test_report_is_private_and_rejects_public_repo_path(self):
        with tempfile.TemporaryDirectory() as d:
            p=Path(d)/'private.json';gsc.save_private(p,{'query':'private'})
            self.assertEqual(p.stat().st_mode&0o777,0o600)
        with self.assertRaises(ValueError):gsc.save_private(ROOT/'site-content/unwanted-search.json',{})
    def test_invalid_metrics_are_rejected(self):
        for value in [float('nan'),float('inf'),-1]:
            with self.assertRaises(ValueError):gsc.metrics({'keys':['x'],'clicks':value,'impressions':2,'ctr':.1,'position':1},['query'])
class EditorialOperations(unittest.TestCase):
    def test_inventory_has_all_articles_and_real_owner_photos(self):
        r=planning.report(datetime.date(2026,11,13));self.assertEqual(r['article_count'],47)
        self.assertEqual(len({x['slug'] for x in r['articles']}),47)
        self.assertNotIn('manmos-rest',{x['key'] for x in r['owner_photos']})
        self.assertTrue(all('not_search_volume' in x['question_basis'] for x in r['articles']))
    def test_missing_audits_do_not_become_success(self):
        with tempfile.TemporaryDirectory() as d:
            s=summary.summarize(Path(d));self.assertIn('結果未取得',s);self.assertNotIn('検出事項 0',s)
    def test_official_redirect_never_follows_affiliate_or_http(self):
        h=sources.OfficialRedirects();req=urllib.request.Request('https://example.com')
        for url in ['https://px.a8.net/svt/test','https://a8.net/test','http://example.com/new']:
            with self.assertRaises(Exception):h.redirect_request(req,None,302,'redirect',{},url)
if __name__=='__main__':unittest.main()
