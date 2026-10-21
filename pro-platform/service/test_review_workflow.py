import tempfile,unittest
from review_workflow import ReviewWorkflow
class Tests(unittest.TestCase):
    def setUp(self):
        self.tmp=tempfile.TemporaryDirectory();self.roles={('a','owner'):'owner',('a','viewer'):'viewer'};self.calls=0
        self.snapshot={'ranges':{'current':['2026-09-17','2026-10-16'],'comparison':['2026-08-18','2026-09-16']},'metrics':{'sessions':200}}
        self.w=ReviewWorkflow(self.tmp.name+'/db',lambda c,p:self.roles.get((c,p)),lambda c:{'start':'2026-10-01T00:00:00+00:00','end':'2026-11-01T00:00:00+00:00','limit':4},lambda c,s:self.snapshot,self.evaluate,lambda:'2026-10-17T20:00:00+00:00')
    def tearDown(self):self.tmp.cleanup()
    def evaluate(self,snapshot):self.calls+=1;return {'summary':'Traffic assessed'}
    def generate(self,key='x'):return self.w.generate('a','owner',key,{},True)
    def test_complete_path(self):
        r=self.generate()
        self.assertEqual(r['snapshot']['metrics']['sessions'],200)
        self.assertEqual(self.w.preview('a','owner')['remaining'],3)
    def test_repeat_download_no_generation(self):
        r=self.generate()
        self.w.reports.get('a','viewer',r['id']);self.w.reports.get('a','viewer',r['id'])
        self.assertEqual(self.calls,1);self.assertEqual(self.w.preview('a','owner')['remaining'],3)
    def test_idempotent_request(self):
        self.assertEqual(self.generate()['id'],self.generate()['id']);self.assertEqual(self.calls,1)
    def test_viewer_cannot_generate(self):
        with self.assertRaises(PermissionError):self.w.generate('a','viewer','x',{},True)
        self.assertEqual(self.calls,0)
    def test_confirmation_required(self):
        with self.assertRaises(ValueError):self.w.generate('a','owner','x',{})
        self.assertEqual(self.w.preview('a','owner')['remaining'],4)
    def test_failed_generation_refunded(self):
        self.w.evaluate=lambda s:(_ for _ in ()).throw(RuntimeError('Provider failed'))
        with self.assertRaises(RuntimeError):self.generate()
        self.assertEqual(self.w.preview('a','owner')['remaining'],4)
        self.assertEqual(self.w.reports.list('a','owner'),[])
    def test_invalid_output_rolls_back(self):
        self.w.evaluate=lambda s:['invalid']
        with self.assertRaises(ValueError):self.generate()
        self.assertEqual(self.w.preview('a','owner')['remaining'],4)
        self.assertEqual(self.w.reports.list('a','owner'),[])
    def test_model_mutation_does_not_change_evidence(self):
        def evaluate(s):s['metrics']['sessions']=999;return {'summary':'Example'}
        self.w.evaluate=evaluate
        self.assertEqual(self.generate()['snapshot']['metrics']['sessions'],200)
    def test_limit_no_extra_model_call(self):
        for i in range(4):self.generate(str(i))
        with self.assertRaises(ValueError):self.generate('fifth')
        self.assertEqual(self.calls,4)
    def test_membership_revoked_during_generation(self):
        def evaluate(s):self.roles.clear();return {'summary':'Example'}
        self.w.evaluate=evaluate
        with self.assertRaises(PermissionError):self.generate()
        self.assertEqual(self.w.allowance.usage('a','2026-10-01T00:00:00+00:00','2026-11-01T00:00:00+00:00'),0)
if __name__=='__main__':unittest.main()
