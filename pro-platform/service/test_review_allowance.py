import tempfile
import unittest
from concurrent.futures import ThreadPoolExecutor
from review_allowance import ReviewAllowance

class Tests(unittest.TestCase):
    def setUp(self):
        self.tmp=tempfile.TemporaryDirectory()
        self.ledger=ReviewAllowance(self.tmp.name+'/usage.db')
        self.cycle=('2026-10-10T00:00:00+00:00','2026-11-10T00:00:00+00:00','2026-10-17T12:00:00+00:00')
    def tearDown(self):self.tmp.cleanup()
    def reserve(self,key,company='a',role='analyst'):
        return self.ledger.reserve(company,role,key,*self.cycle)
    def test_viewer_denied(self):
        with self.assertRaises(PermissionError):self.reserve('x',role='viewer')
    def test_four_company_wide(self):
        for i in range(4):self.reserve(str(i),role='owner' if i%2 else 'analyst')
        with self.assertRaises(ValueError):self.reserve('fifth')
    def test_retry_is_not_extra_charge(self):
        self.reserve('x')
        self.assertTrue(self.reserve('x')['reused'])
        self.assertEqual(self.ledger.usage('a',*self.cycle[:2]),1)
    def test_failed_generation_releases_allowance(self):
        self.reserve('x');self.ledger.finish('a','x',False)
        self.assertEqual(self.ledger.usage('a',*self.cycle[:2]),0)
    def test_completed_cannot_be_released(self):
        self.reserve('x');self.ledger.finish('a','x',True);self.ledger.finish('a','x',False)
        self.assertEqual(self.ledger.usage('a',*self.cycle[:2]),1)
    def test_company_isolation(self):
        self.reserve('x');self.reserve('x',company='b')
        self.assertEqual(self.ledger.usage('a',*self.cycle[:2]),1)
    def test_concurrent_reservations(self):
        def attempt(i):
            try:self.reserve(str(i));return True
            except ValueError:return False
        with ThreadPoolExecutor(max_workers=8) as pool:
            self.assertEqual(sum(pool.map(attempt,range(12))),4)
    def test_cycle_end_exclusive(self):
        start,end,_=self.cycle
        with self.assertRaises(ValueError):self.ledger.reserve('a','owner','x',start,end,end)
    def test_new_billing_cycle(self):
        self.reserve('x')
        self.ledger.reserve('a','owner','y','2026-11-10T00:00:00+00:00','2026-12-10T00:00:00+00:00','2026-11-11T00:00:00+00:00')
        self.assertEqual(self.ledger.usage('a',*self.cycle[:2]),1)
if __name__=='__main__':unittest.main()
