import importlib.util,json,unittest,hashlib
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
s=importlib.util.spec_from_file_location('private_gsc',ROOT/'tools/search-console-sync.py');g=importlib.util.module_from_spec(s);s.loader.exec_module(g)
class PrivateDelivery(unittest.TestCase):
 def setUp(self):self.info={'client_email':g.SERVICE_EMAIL,'private_key_id':'test-key'}
 def test_signature_claims_bind_body_and_expire(self):
  seen={}
  def sign(info,claims):seen.update(claims);return 'not-a-real-token'
  b=b'{"report":"private"}';g.signed_report_token(self.info,b,now=1000,sign=sign)
  self.assertEqual(seen['sha256'],hashlib.sha256(b).hexdigest());self.assertEqual(seen['exp'],1300);self.assertEqual(seen['aud'],g.BLOG_AUDIENCE)
 def test_wrong_sender_is_rejected_before_signing(self):
  with self.assertRaises(ValueError):g.signed_report_token(dict(self.info,client_email='other@test'),b'{}',sign=lambda *a:self.fail('Must not sign'))
 def test_report_is_posted_only_to_private_blog_endpoint(self):
  captured={}
  def send(req):captured.update(url=req.full_url,body=req.data,auth=req.get_header('Authorization'));return {'stored':True}
  report={'site':g.SITE,'query':'private search'}
  self.assertTrue(g.publish_private_report(self.info,report,send=send,sign=lambda *a:'scoped-jwt'))
  self.assertEqual(captured['url'],g.BLOG_REPORT_URL);self.assertEqual(json.loads(captured['body']),report);self.assertEqual(captured['auth'],'Bearer scoped-jwt')
 def test_unconfirmed_delivery_is_not_success(self):
  with self.assertRaises(ValueError):g.publish_private_report(self.info,{},send=lambda req:{'stored':False},sign=lambda *a:'token')
 def test_overlarge_report_is_not_sent(self):
  with self.assertRaises(ValueError):g.publish_private_report(self.info,{'data':'x'*(4*1024*1024)},send=lambda req:self.fail('Must not send'))
class Inspection(unittest.TestCase):
 def test_only_this_blog_can_be_inspected(self):
  with self.assertRaises(ValueError):g.inspection_request('fake','https://other.test/')
 def test_permission_failure_is_recorded_and_not_retried(self):
  from urllib.error import HTTPError
  calls=[]
  def forbidden(token,url):calls.append(url);raise HTTPError(url,403,'forbidden',{},None)
  result=g.inspect_urls('fake',forbidden)
  self.assertEqual(len(calls),1);self.assertEqual(result[0]['http'],403);self.assertTrue(all(x['state']=='not_attempted' for x in result[1:]))
 def test_no_live_index_request_is_made(self):
  result=g.inspect_urls('fake',lambda token,url:{'inspectionResult':{'indexStatusResult':{'verdict':'PASS','coverageState':'Submitted and indexed'}}})
  self.assertEqual(len(result),len(g.INSPECTION_URLS));self.assertTrue(all(x['index_status']['verdict']=='PASS' for x in result))
if __name__=='__main__':unittest.main()
