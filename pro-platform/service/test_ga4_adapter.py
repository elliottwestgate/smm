import unittest
from datetime import datetime,timezone
from ga4_adapter import GA4Adapter,ReportingError

class Tests(unittest.TestCase):
    def setUp(self):
        self.config={'property':'123','company':'Example','website':'https://example.com','timezone':'America/Chicago','form_events':['form_submission'],'phone_events':['phone_call']}
        self.now=datetime(2026,10,17,20,tzinfo=timezone.utc)
        self.calls=[]
    def transport(self,path,request):
        self.calls.append((path,request))
        dims=[v['name'] for v in request['dimensions']]
        metrics=[v['name'] for v in request['metrics']]
        values={():[([],['200','180','150'])],
                ('newVsReturning',):[(['returning'],['40']),(['new'],['150'])],
                ('eventName',):[(['form_submission'],['8']),(['phone_call'],['2']),(['scroll'],['500'])],
                ('sessionSourceMedium','sessionDefaultChannelGroup'):[(['facebook / social','Organic Social'],['20'])]}
        rows=[{'dimensionValues':[{'value':v} for v in ds],'metricValues':[{'value':v} for v in ms]} for ds,ms in values.get(tuple(dims),[])]
        return {'dimensionHeaders':[{'name':d} for d in dims],'metricHeaders':[{'name':m} for m in metrics],'rows':rows,'rowCount':len(rows),'metadata':{'timeZone':'America/Chicago'}}
    def adapter(self,transport=None):return GA4Adapter(transport or self.transport,self.config)
    def test_snapshot_contract(self):
        s=self.adapter().snapshot('2026-09-17','2026-10-16',self.now)['snapshots'][0]
        self.assertEqual(s['metrics'],{'sessions':200,'users':180,'new_users':150,'returning_users':40,'form_events':8,'phone_events':2})
        self.assertEqual(s['sources'][0]['sessions'],20)
        self.assertEqual(len(self.calls),9)
    def test_today_rejected(self):
        with self.assertRaises(ValueError):self.adapter().snapshot('2026-10-16','2026-10-17',self.now)
        self.assertEqual(self.calls,[])
    def test_overlapping_goals_rejected(self):
        self.config['phone_events']=['form_submission']
        with self.assertRaises(ValueError):self.adapter()
    def test_untrusted_property_rejected(self):
        self.config['property']='../other'
        with self.assertRaises(ValueError):self.adapter()
    def test_timezone_mismatch_rejected(self):
        def transport(p,r):
            v=self.transport(p,r);v['metadata']['timeZone']='UTC';return v
        with self.assertRaises(ReportingError):self.adapter(transport).snapshot('2026-10-01','2026-10-16',self.now)
    def test_thresholding_preserved_missing_goal_not_zero(self):
        def transport(p,r):
            v=self.transport(p,r)
            if r['dimensions']==[{'name':'eventName'}]:
                v['rows']=[];v['rowCount']=0;v['metadata']['subjectToThresholding']=True
            return v
        s=self.adapter(transport).snapshot('2026-10-01','2026-10-16',self.now)['snapshots'][0]
        self.assertIsNone(s['metrics']['form_events'])
        self.assertTrue(any('thresholding' in n for n in s['notes']))
    def test_failed_api_is_not_zero(self):
        with self.assertRaises(ReportingError):self.adapter(lambda p,r:{'error':{'code':403}}).snapshot('2026-10-01','2026-10-16',self.now)
    def test_pagination(self):
        offsets=[]
        def transport(p,r):
            v=self.transport(p,r)
            if r['dimensions']==[{'name':'eventName'}]:
                offsets.append(r['offset'])
                v['rows']=v['rows'][r['offset']:r['offset']+1]
            return v
        s=self.adapter(transport).snapshot('2026-10-01','2026-10-16',self.now)['snapshots'][0]
        self.assertEqual(offsets,[0,1,2]);self.assertEqual(s['metrics']['form_events'],8)
if __name__=='__main__':unittest.main()
