import tempfile,unittest
from saved_reviews import SavedReviews
class Tests(unittest.TestCase):
    def setUp(self):
        self.tmp=tempfile.TemporaryDirectory();self.allowed=True
        self.s=SavedReviews(self.tmp.name+'/db',lambda c,p:self.allowed and (c,p) in [('a','viewer'),('b','other')])
        self.snapshot={'ranges':{'current':['2026-09-17','2026-10-16'],'comparison':['2026-08-18','2026-09-16']},'metrics':{'sessions':200}}
    def tearDown(self):self.tmp.cleanup()
    def publish(self,review=None):
        return self.s.publish('a','request','2026-10-17T20:00:00+00:00',self.snapshot,review or {'summary':'Example'})
    def test_read_preserves_original(self):
        key=self.publish();self.snapshot['metrics']['sessions']=999
        self.assertEqual(self.s.get('a','viewer',key)['snapshot']['metrics']['sessions'],200)
    def test_viewer_can_read_repeatedly(self):
        key=self.publish()
        self.assertEqual(self.s.get('a','viewer',key),self.s.get('a','viewer',key))
    def test_no_cross_company_read(self):
        key=self.publish()
        with self.assertRaises(LookupError):self.s.get('b','other',key)
    def test_removed_member_cannot_read(self):
        key=self.publish();self.allowed=False
        with self.assertRaises(PermissionError):self.s.get('a','viewer',key)
    def test_idempotent_publish(self):
        self.assertEqual(self.publish(),self.publish())
        self.assertEqual(len(self.s.list('a','viewer')),1)
    def test_immutable_publish(self):
        self.publish()
        with self.assertRaises(ValueError):self.publish({'summary':'Changed'})
    def test_list_company_only(self):
        self.publish();self.assertEqual(self.s.list('b','other'),[])
    def test_invalid_date_rejected(self):
        self.snapshot['ranges']['current'][0]='2026-02-30'
        with self.assertRaises(ValueError):self.publish()
if __name__=='__main__':unittest.main()
