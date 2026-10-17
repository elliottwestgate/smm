import hashlib, hmac, json, os, tempfile, unittest, uuid
from app import API

class ContractTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.keys = {'pro': 'a'*40, 'other': 'b'*40}
        self.api = API(os.path.join(self.tmp.name,'test.db'), self.keys, clock=lambda: 1000)
        self.project = dict(name='Aladdin pilot',website='https://aladdincleans.com',timezone='America/Chicago',start_date='2026-10-18',end_date='2027-01-16',primary_action='Confirmed service inquiry',revision=0)
    def tearDown(self): self.tmp.cleanup()
    def request(self, method, path, data=None, owner='1', tenant='pro', nonce=None, stamp='1000'):
        body = json.dumps(data).encode() if data is not None else b''
        nonce = nonce or uuid.uuid4().hex
        canonical = '\n'.join([method,path,tenant,owner,stamp,nonce,hashlib.sha256(body).hexdigest()])
        signature = hmac.new(self.keys[tenant].encode(),canonical.encode(),hashlib.sha256).hexdigest()
        headers = dict(zip(['x-westgate-tenant','x-westgate-principal','x-westgate-timestamp','x-westgate-nonce','x-westgate-signature'],[tenant,owner,stamp,nonce,signature]))
        return self.api.handle(method,path,headers,body)
    def test_project_revision_conflict(self):
        self.assertEqual(self.request('PUT','/v1/project',self.project)[0],200)
        self.assertEqual(self.request('PUT','/v1/project',self.project)[0],409)
    def test_user_and_tenant_isolation(self):
        self.request('PUT','/v1/project',self.project)
        for kw in [{'owner':'2'},{'tenant':'other'}]:
            self.assertIsNone(self.request('GET','/v1/project',**kw)[1]['project'])
            self.assertEqual(self.request('GET','/v1/report',**kw)[0],404)
    def test_replay_and_expired_requests(self):
        nonce = uuid.uuid4().hex
        self.assertEqual(self.request('GET','/v1/project',nonce=nonce)[0],200)
        self.assertEqual(self.request('GET','/v1/project',nonce=nonce)[0],401)
        self.assertEqual(self.request('GET','/v1/project',stamp='1')[0],401)
    def test_unsigned_rejected(self):
        self.assertEqual(self.api.handle('GET','/v1/project',{},b'')[0],401)
    def test_missing_metrics_never_zero(self):
        self.request('PUT','/v1/project',self.project)
        report=self.request('GET','/v1/report')[1]
        self.assertIsNone(report['campaign_to_date'])
        self.assertTrue(all(m['value'] is None for panel in report['panels'].values() for m in panel))
    def test_invalid_dates_timezone_and_url(self):
        for key,value in [('end_date','2020-01-01'),('timezone','Invalid/Zone'),('website','http://example.com')]:
            self.assertEqual(self.request('PUT','/v1/project',dict(self.project,**{key:value}))[0],422)
    def test_reflection_scope_and_week(self):
        self.request('PUT','/v1/project',self.project)
        data=dict(week_start='2026-10-18',experiment='Test a service page CTA',success_measure='Confirmed inquiries')
        self.assertEqual(self.request('POST','/v1/reflections',data)[0],201)
        self.assertEqual(len(self.request('GET','/v1/reflections')[1]['reflections']),1)
        self.assertEqual(self.request('GET','/v1/reflections',owner='2')[0],404)
        self.assertEqual(self.request('POST','/v1/reflections',dict(data,week_start='2026-10-19'))[0],422)
    def test_email_provider_selection(self):
        for provider in ['constant_contact','mailchimp']:
            self.assertEqual(self.request('PUT','/v1/project',dict(self.project,email_provider=provider),owner='3' if provider=='mailchimp' else '4')[0],200)
        self.assertEqual(self.request('PUT','/v1/project',dict(self.project,email_provider='unknown'))[0],422)

    def test_signature_covers_body_and_path(self):
        body=b'{}'; nonce=uuid.uuid4().hex
        canonical='\n'.join(['GET','/v1/project','pro','1','1000',nonce,hashlib.sha256(body).hexdigest()])
        headers={'x-westgate-tenant':'pro','x-westgate-principal':'1','x-westgate-timestamp':'1000','x-westgate-nonce':nonce,'x-westgate-signature':hmac.new(self.keys['pro'].encode(),canonical.encode(),hashlib.sha256).hexdigest()}
        self.assertEqual(self.api.handle('PUT','/v1/project',headers,body)[0],401)

if __name__=='__main__': unittest.main()
