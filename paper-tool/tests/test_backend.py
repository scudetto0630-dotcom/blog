"""Validation, persistence and live HTTP tests; no external API is called."""
import json
import os
import socket
import sqlite3
import subprocess
import sys
import tempfile
import time
import unittest
import urllib.error
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT))
from app.models import ValidationError, validate_input
from app.demo import make_result
from app.taxonomy import NAMES

class ModelsTests(unittest.TestCase):
    def test_japanese_and_english(self):
        self.assertEqual(validate_input({'ingredients':[' EGCG ', 'ビタミンC']})['ingredients'], ['EGCG','ビタミンC'])
    def test_empty_and_whitespace(self):
        for value in [[], [''], ['  '], ['EGCG', '']]:
            with self.subTest(value=value), self.assertRaises(ValidationError): validate_input({'ingredients':value})
    def test_max_five(self):
        self.assertEqual(len(validate_input({'ingredients':['A','B','C','D','E']})['ingredients']),5)
        with self.assertRaises(ValidationError): validate_input({'ingredients':['A','B','C','D','E','F']})
    def test_duplicate_normalization(self):
        for ingredients in [['EGCG','egcg'],['ＥＧＣＧ','EGCG'],['Vitamin C','Vitamin   C']]:
            with self.subTest(ingredients=ingredients), self.assertRaises(ValidationError): validate_input({'ingredients':ingredients})
    def test_invalid_types(self):
        for data in [None, [], {'ingredients':'A'}, {'ingredients':[None]}, {'name':22,'ingredients':['A']}]:
            with self.subTest(data=data), self.assertRaises(ValidationError): validate_input(data)
    def test_length_boundary(self):
        self.assertEqual(len(validate_input({'name':'x'*120,'ingredients':['a'*120]})['name']),120)
        for data in [{'name':'x'*121,'ingredients':['A']},{'ingredients':['A'*121]}]:
            with self.assertRaises(ValidationError): validate_input(data)
    def test_control_characters(self):
        with self.assertRaises(ValidationError): validate_input({'ingredients':['line\nbreak']})
    def test_special_characters_not_interpreted(self):
        text='<script>alert(1)</script> & "test"'
        self.assertEqual(validate_input({'ingredients':[text]})['ingredients'][0], text)
    def test_exact_user_taxonomy(self):
        self.assertEqual(len(NAMES),51)
        self.assertEqual(NAMES[:3],['肌','髪','まつ毛'])
        self.assertEqual(NAMES[49:],[ 'そのたの部位で人への影響があるもの','人への影響でないもの、製造方法、保存方法'])
    def test_synthetic_results(self):
        result=make_result({'name':'Test','ingredients':['EGCG','ビタミンC']})
        self.assertEqual(result['summary']['retrieved'],12)
        self.assertEqual(result['summary']['adopted']+result['summary']['excluded']+result['summary']['review'],12)
        self.assertIsNone(result['artifact']['download_url'])
        for paper in result['papers']:
            self.assertIsNone(paper['pmid']); self.assertTrue(paper['is_demo'])
            self.assertEqual([c['round'] for c in paper['checks']],[1,2])
            self.assertIn('term=',paper['pubmed_url'])
    def test_decision_history_not_overwritten(self):
        p=make_result({'ingredients':['EGCG']})['papers'][1]
        self.assertNotEqual(p['initial_decision']['body_ids'],p['checks'][0]['decision']['body_ids'])
        self.assertEqual(p['checks'][0]['decision'],p['final_decision'])

class HTTPTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temp=tempfile.TemporaryDirectory(prefix='paperdesk-http-')
        cls.db=str(Path(cls.temp.name)/'test.sqlite3')
        with socket.socket() as sock:
            sock.bind(('127.0.0.1',0)); cls.port=sock.getsockname()[1]
        cls.base=f'http://127.0.0.1:{cls.port}'
        cls.start_server()
    @classmethod
    def start_server(cls):
        cls.log=open(Path(cls.temp.name)/'server.log','a')
        cls.proc=subprocess.Popen([sys.executable,str(ROOT/'server.py'),'--port',str(cls.port),'--db',cls.db,'--demo-delay','0.12'],cwd=ROOT,env={**os.environ,'APP_ACCESS_PASSWORD':''},stdout=cls.log,stderr=cls.log)
        for _ in range(100):
            try:
                urllib.request.urlopen(cls.base+'/health',timeout=1).close(); return
            except Exception: time.sleep(.04)
        raise RuntimeError('test server failed to start')
    @classmethod
    def stop_server(cls):
        cls.proc.terminate();cls.proc.wait(timeout=5);cls.log.close()
    @classmethod
    def tearDownClass(cls):
        cls.stop_server();cls.temp.cleanup()
    def request(self,path,method='GET',body=None,headers=None):
        req=urllib.request.Request(self.base+path,data=json.dumps(body).encode() if body is not None else None,method=method,headers={'Content-Type':'application/json',**(headers or {})})
        try:
            with urllib.request.urlopen(req,timeout=20) as r:return r.status,json.load(r),r.headers
        except urllib.error.HTTPError as e:return e.code,json.load(e),e.headers
    def test_config_and_security_headers(self):
        code,data,headers=self.request('/api/config')
        self.assertEqual(code,200);self.assertFalse(data['taxonomy_provisional']);self.assertEqual(len(data['taxonomy']),51)
        self.assertIn("frame-ancestors 'none'",headers['Content-Security-Policy'])
    def test_crud_and_search_source(self):
        code,p,_=self.request('/api/products','POST',{'name':'日本語製品','ingredients':['EGCG']});self.assertEqual(code,201)
        code,p,_=self.request('/api/products/'+p['id'],'PUT',{'name':'編集済み','ingredients':['ビタミンC']});self.assertEqual(p['name'],'編集済み')
        self.assertTrue(any(x['id']==p['id'] for x in self.request('/api/products')[1]['products']))
        self.assertEqual(self.request('/api/products/'+p['id'],'DELETE')[0],200)
        self.assertEqual(self.request('/api/products/'+p['id'])[0],404)
    def test_invalid_input_http(self):
        for data in [{'ingredients':[' ']},{'ingredients':['EGCG','egcg']},{'ingredients':['x'*121]},{'ingredients':['A','B','C','D','E','F']}]:
            self.assertEqual(self.request('/api/products','POST',data)[0],400)
    def test_missing_product_update(self):
        self.assertEqual(self.request('/api/products/missing','PUT',{'ingredients':['A']})[0],404)
    def test_cross_origin_write_rejected(self):
        self.assertEqual(self.request('/api/products','POST',{'ingredients':['A']},{'Origin':'https://other.example'})[0],403)
    def test_malformed_json_rejected(self):
        req=urllib.request.Request(self.base+'/api/products',data=b'not json',method='POST',headers={'Content-Type':'application/json'})
        with self.assertRaises(urllib.error.HTTPError) as e:urllib.request.urlopen(req)
        self.assertEqual(e.exception.code,400)
    def test_static_traversal_rejected(self):
        self.assertEqual(self.request('/../server.py')[0],404)
    def test_save_failure_returns_actionable_error(self):
        with sqlite3.connect(self.db) as db:db.execute("CREATE TRIGGER fail_save BEFORE INSERT ON products BEGIN SELECT RAISE(ABORT,'test save failure'); END")
        try:
            code,data,_=self.request('/api/products','POST',{'ingredients':['A']})
            self.assertEqual(code,503);self.assertIn('保存',data['error'])
        finally:
            with sqlite3.connect(self.db) as db:db.execute('DROP TRIGGER fail_save')
        self.assertEqual(self.request('/api/products','POST',{'ingredients':['A']})[0],201)
    def test_demo_job_completion_and_history(self):
        code,job,_=self.request('/api/jobs','POST',{'name':'デモ','ingredients':['EGCG']});self.assertEqual(code,202)
        for _ in range(100):
            data=self.request('/api/jobs/'+job['id'])[1]
            if data['status']=='completed':break
            time.sleep(.04)
        self.assertEqual(data['completed_steps'],7);self.assertEqual(data['result']['summary']['retrieved'],6)
        self.assertTrue(any(j['id']==job['id'] for j in self.request('/api/jobs')[1]['jobs']))
    def test_deleted_product_does_not_erase_history(self):
        p=self.request('/api/products','POST',{'name':'削除予定','ingredients':['A']})[1]
        j=self.request('/api/jobs','POST',{'name':p['name'],'ingredients':p['ingredients']})[1]
        self.request('/api/products/'+p['id'],'DELETE')
        self.assertEqual(self.request('/api/jobs/'+j['id'])[1]['snapshot']['name'],'削除予定')
    def test_server_restart_resumes_job_and_keeps_products(self):
        p=self.request('/api/products','POST',{'name':'再起動保存','ingredients':['A']})[1]
        j=self.request('/api/jobs','POST',{'name':'再開テスト','ingredients':['A']})[1]
        for _ in range(100):
            current=self.request('/api/jobs/'+j['id'])[1]
            if current['completed_steps']>=1:break
            time.sleep(.03)
        self.stop_server();self.start_server()
        self.assertEqual(self.request('/api/products/'+p['id'])[0],200)
        for _ in range(100):
            current=self.request('/api/jobs/'+j['id'])[1]
            if current['status']=='completed':break
            time.sleep(.04)
        self.assertEqual(current['status'],'completed');self.assertEqual(current['completed_steps'],7)
    def test_corrupt_record_failure_is_recoverable(self):
        with sqlite3.connect(self.db) as db:db.execute("INSERT INTO products VALUES('bad','local','bad','invalid','now','now')")
        try:self.assertEqual(self.request('/api/products')[0],500)
        finally:
            with sqlite3.connect(self.db) as db:db.execute("DELETE FROM products WHERE id='bad'")
        self.assertEqual(self.request('/api/products')[0],200)

class SecurityTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temp=tempfile.TemporaryDirectory(prefix='paperdesk-security-')
        cls.password='日本語の接続パスワード12345'
        with socket.socket() as sock:
            sock.bind(('127.0.0.1',0));cls.port=sock.getsockname()[1]
        cls.url=f'http://127.0.0.1:{cls.port}'
        cls.proc=subprocess.Popen([sys.executable,str(ROOT/'server.py'),'--port',str(cls.port),'--db',str(Path(cls.temp.name)/'db.sqlite3')],cwd=ROOT,env={**os.environ,'APP_ACCESS_PASSWORD':cls.password},stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
        for _ in range(100):
            try:urllib.request.urlopen(cls.url+'/health',timeout=1).close();break
            except Exception:time.sleep(.04)
    @classmethod
    def tearDownClass(cls):
        cls.proc.terminate();cls.proc.wait(timeout=5);cls.temp.cleanup()
    def test_unauthenticated_access_rejected(self):
        with self.assertRaises(urllib.error.HTTPError) as error:urllib.request.urlopen(self.url+'/api/products')
        self.assertEqual(error.exception.code,401)
        self.assertIn('Basic',error.exception.headers['WWW-Authenticate'])
    def test_unicode_shared_password_supported(self):
        import base64
        value=base64.b64encode(('user:'+self.password).encode()).decode()
        req=urllib.request.Request(self.url+'/api/products',headers={'Authorization':'Basic '+value})
        with urllib.request.urlopen(req) as r:self.assertEqual(r.status,200)
    def test_wrong_password_rejected(self):
        import base64
        value=base64.b64encode(b'user:wrong').decode()
        req=urllib.request.Request(self.url+'/api/products',headers={'Authorization':'Basic '+value})
        with self.assertRaises(urllib.error.HTTPError) as error:urllib.request.urlopen(req)
        self.assertEqual(error.exception.code,401)
    def test_public_bind_without_password_is_refused(self):
        result=subprocess.run([sys.executable,str(ROOT/'server.py'),'--host','0.0.0.0','--port','0','--db',str(Path(self.temp.name)/'unused.sqlite3')],env={**os.environ,'APP_ACCESS_PASSWORD':''},capture_output=True,timeout=5)
        self.assertNotEqual(result.returncode,0)
        self.assertIn(b'APP_ACCESS_PASSWORD',result.stderr)
    def test_backup_and_restore_data(self):
        from app.store import Store
        source=Path(self.temp.name)/'backup-source.sqlite3';destination=Path(self.temp.name)/'backup-output.sqlite3'
        product=Store(source).save_product({'name':'復元対象','ingredients':['EGCG']})
        result=subprocess.run([sys.executable,str(ROOT/'tools/backup.py'),'--db',str(source),'--output',str(destination)],capture_output=True,timeout=5)
        self.assertEqual(result.returncode,0)
        self.assertEqual(Store(destination).get_product(product['id'])['name'],'復元対象')

if __name__=='__main__':unittest.main(verbosity=2)
