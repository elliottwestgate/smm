import tempfile,unittest
from urllib.parse import urlparse,parse_qs
from cryptography.fernet import Fernet
from google_connection import GoogleConnection,ConnectionError,SCOPE
class Tests(unittest.TestCase):
    def setUp(self):
        self.tmp=tempfile.TemporaryDirectory();self.now=1000;self.owner=True;self.calls=[]
        self.response={'access_token':'secret-access','refresh_token':'secret-refresh','expires_in':3600,'scope':SCOPE}
        self.c=GoogleConnection(self.tmp.name+'/db',Fernet.generate_key(),'app','secret','https://example.com/google/callback',self.post,lambda c,u:self.owner,lambda:self.now)
    def tearDown(self):self.tmp.cleanup()
    def post(self,url,data):self.calls.append((url,data));return self.response
    def start(self):return parse_qs(urlparse(self.c.start('a','1','session')).query)['state'][0]
    def connect(self):
        state=self.start();return self.c.callback('a','1','session',state,'code')
    def test_owner_only(self):
        self.owner=False
        with self.assertRaises(PermissionError):self.start()
    def test_wrong_session(self):
        state=self.start()
        with self.assertRaises(ConnectionError):self.c.callback('a','1','other',state,'code')
        self.assertEqual(self.calls,[])
    def test_wrong_company(self):
        state=self.start()
        with self.assertRaises(ConnectionError):self.c.callback('b','1','session',state,'code')
    def test_state_once(self):
        state=self.start();self.c.callback('a','1','session',state,'code')
        with self.assertRaises(ConnectionError):self.c.callback('a','1','session',state,'code')
    def test_expiration(self):
        state=self.start();self.now+=601
        with self.assertRaises(ConnectionError):self.c.callback('a','1','session',state,'code')
    def test_encrypted_at_rest(self):
        self.connect()
        with self.c.db() as db:blob=db.execute('SELECT encrypted FROM google_connections').fetchone()[0]
        self.assertNotIn(b'secret-access',blob);self.assertNotIn(b'secret-refresh',blob)
    def test_refresh(self):
        self.connect();self.now+=3600
        self.response={'access_token':'replacement','expires_in':3600}
        self.assertEqual(self.c.token('a'),'replacement')
        self.assertEqual(self.c.read('a')['refresh_token'],'secret-refresh')
    def test_missing_scope(self):
        self.response['scope']=''
        with self.assertRaises(ConnectionError):self.connect()
    def test_disconnect_stops_collection(self):
        self.connect();self.c.disconnect('a','1')
        with self.assertRaises(ConnectionError):self.c.token('a')
    def test_revocation_failure_still_disconnects(self):
        self.connect();self.response={'error':'unavailable'}
        self.assertFalse(self.c.disconnect('a','1')['revocation_confirmed'])
        with self.assertRaises(ConnectionError):self.c.token('a')
if __name__=='__main__':unittest.main()
