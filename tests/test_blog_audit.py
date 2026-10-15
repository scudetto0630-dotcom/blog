import importlib.util
from pathlib import Path
import threading
import unittest
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
spec=importlib.util.spec_from_file_location('blog_audit',Path(__file__).resolve().parents[1]/'tools/blog-audit.py');audit=importlib.util.module_from_spec(spec);spec.loader.exec_module(audit)
class Handler(BaseHTTPRequestHandler):
    requested=[]
    def do_GET(self):
        type(self).requested.append(self.path)
        status=404 if self.path=='/missing/' else 200
        self.send_response(status);self.end_headers()
        if self.path=='/': self.wfile.write('<html><title>試験</title><h1>ブログ</h1><a href="/お問い合わせ/">連絡</a><a href="/missing/">欠落</a></html>'.encode())
        else:self.wfile.write(b'<html><title>Page</title><h1>Page</h1></html>')
    def log_message(self,*args):pass
class AuditTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.server=ThreadingHTTPServer(('127.0.0.1',0),Handler);cls.thread=threading.Thread(target=cls.server.serve_forever,daemon=True);cls.thread.start();cls.result=audit.audit('http://127.0.0.1:'+str(cls.server.server_port))
    @classmethod
    def tearDownClass(cls):cls.server.shutdown();cls.server.server_close()
    def test_japanese_internal_link(self):
        row=next(r for r in self.result['checks'] if 'お問い合わせ' in r['url']);self.assertEqual(row['status'],200);self.assertTrue(any('%E3%81%8A' in p for p in Handler.requested))
    def test_broken_link_is_failure(self):
        row=next(r for r in self.result['checks'] if r['url'].endswith('/missing/'));self.assertTrue(row['errors']);self.assertGreaterEqual(self.result['failures'],1)
    def test_warnings_distinct_from_failures(self):
        row=self.result['checks'][0];self.assertFalse(row['errors']);self.assertTrue(row['warnings'])
if __name__=='__main__':unittest.main()
