import gzip
import io
import json
import os
import tempfile
import unittest
import zipfile
from pathlib import Path
from unittest.mock import patch

from app.ingredients import search_name, search_term
from app.pubmed import PubMed
from app.runtime import ai_key, readiness
from app.engine.jobs import ResearchJobs
from app.engine.decisions import Classifier
from app.engine.ledger import Ledger


class LiveReadinessTests(unittest.TestCase):
    def test_other_ingredients_are_distinct_from_egcg(self):
        self.assertEqual(search_name('エピカテキン'), 'epicatechin')
        self.assertEqual(search_name('EGCG'), 'epigallocatechin gallate')
        self.assertNotEqual(search_term('エピカテキン'), search_term('EGCG'))
        self.assertEqual(search_name('クルクミン'), 'curcumin')
        self.assertEqual(search_name('ビタミンＣ'), 'vitamin C')
        self.assertEqual(search_name('unlisted english ingredient'), 'unlisted english ingredient')

    def test_unknown_japanese_is_not_reported_as_zero_papers(self):
        with self.assertRaisesRegex(ValueError, '英語'):
            search_name('未知の成分名')

    def test_pubmed_uses_translated_search_and_2000_date(self):
        requests = []
        def transport(endpoint, params):
            requests.append(params)
            return json.dumps({'esearchresult': {'count': '0'}}).encode()
        list(PubMed(transport).ids('エピカテキン'))
        self.assertEqual(requests[0]['term'], '"epicatechin"[Title/Abstract]')
        self.assertEqual(requests[0]['mindate'], '2000-01-01')

    def test_standard_api_key_works_and_readiness_never_exposes_it(self):
        with patch.dict(os.environ, {'OPENAI_API_KEY': 'fake-do-not-send', 'PAPERDESK_AI_MODEL': 'mock', 'PAPERDESK_INPUT_USD_PER_MILLION': '1', 'PAPERDESK_OUTPUT_USD_PER_MILLION': '1', 'PAPERDESK_JOB_CAP_USD': '1', 'PAPERDESK_ALLOW_PAID': 'explicitly-approved'}, clear=True):
            self.assertEqual(ai_key(), 'fake-do-not-send')
            self.assertTrue(readiness()['ai_ready'])
            self.assertNotIn('fake-do-not-send', json.dumps(readiness()))
        with patch.dict(os.environ, {}, clear=True):
            self.assertFalse(readiness()['ai_ready'])

    def test_non_egcg_job_three_passes_restart_and_archive(self):
        requests = []
        class Client:
            def ids(self, ingredient): return iter(['123', '123'])
            def fetch(self, ids):
                return [{'pmid': p, 'title_en': 'Epicatechin intervention', 'abstract': 'In adults epicatechin changed muscle markers.', 'publication_date': {'Year': '2024', 'Month': 'May'}, 'pubmed_url': f'https://pubmed.ncbi.nlm.nih.gov/{p}/'} for p in ids]
        def transport(payload):
            requests.append(payload)
            decision = {'status': 'adopted', 'categories': ['筋肉'], 'human_trial': 'unclear', 'reason': 'Mock transport; not scientific validation', 'intervention': 'epicatechin', 'evidence': 'epicatechin changed muscle markers.', 'title_ja': '模擬テストの論文'}
            return {'usage': {'prompt_tokens': 100, 'completion_tokens': 100}, 'choices': [{'message': {'content': json.dumps(decision)}}]}
        with tempfile.TemporaryDirectory() as folder:
            path, bundle = Path(folder)/'jobs.db', Path(folder)/'bundle.gz'
            with gzip.open(bundle, 'wt') as f: json.dump({'data': []}, f)
            def factory(id): return Classifier(Ledger(path, id, 1), 'mock', key='fake', approved=True, input_rate=1, output_rate=1, transport=transport)
            jobs = ResearchJobs(path, bundle, Client(), factory)
            with self.assertRaisesRegex(ValueError, '重複'):
                jobs.create({'name': '', 'ingredients': ['エピカテキン', 'epicatechin']})
            job = jobs.create({'name': '非EGCGテスト', 'ingredients': ['エピカテキン']})
            jobs.process(job['id'])
            self.assertEqual(jobs.get(job['id'])['total'], 1)
            self.assertEqual(len(requests), 3)
            reopened = ResearchJobs(path, bundle, Client(), factory)
            self.assertEqual(reopened.get(job['id'])['status'], 'completed')
            self.assertEqual(reopened.results(job['id'])['papers'][0]['final_decision']['categories'], ['筋肉'])
            with zipfile.ZipFile(io.BytesIO(reopened.archive(job['id']))) as archive:
                content = archive.read('非EGCGテスト/エピカテキン/論文一覧_筋肉.txt').decode()
                self.assertIn('123', content)
                self.assertIn('模擬テストの論文', content)
                self.assertIsNone(archive.testzip())
            second = reopened.create({'name': '英語名でも再利用', 'ingredients': ['epicatechin']})
            reopened.process(second['id'])
            self.assertEqual(len(requests), 3)
            self.assertEqual(reopened.results(second['id'])['papers'][0]['ingredient'], 'epicatechin')

