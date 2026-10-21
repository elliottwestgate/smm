"""Review coordinator, independent of web and AI vendors.
All dependencies are trusted server adapters, never values supplied by a browser.
"""
import json
from review_allowance import ReviewAllowance
from saved_reviews import SavedReviews

class ReviewWorkflow:
    def __init__(self,database,membership,billing,snapshot,evaluate,clock):
        self.membership=membership;self.billing=billing;self.snapshot=snapshot
        self.evaluate=evaluate;self.clock=clock
        self.allowance=ReviewAllowance(database)
        self.reports=SavedReviews(database,self.can_read)
    def role(self,company,principal):
        role=self.membership(company,principal)
        if role not in ('owner','analyst','viewer'):raise PermissionError('Company access required')
        return role
    def can_read(self,company,principal):
        try:self.role(company,principal);return True
        except PermissionError:return False
    def preview(self,company,principal):
        role=self.role(company,principal)
        if role=='viewer':return {'can_generate':False,'message':'An owner or analyst can request a new evaluation.'}
        cycle=self.billing(company)
        start,end=self.allowance.boundaries(cycle['start'],cycle['end'],self.clock())
        used=self.allowance.usage(company,start,end)
        return {'can_generate':used<cycle['limit'],'used':used,'limit':cycle['limit'],
                'remaining':max(0,cycle['limit']-used),
                'message':'Generating a new evaluation uses one company review. Existing reports remain available.'}
    def generate(self,company,principal,request_id,selection,confirmed=False):
        role=self.role(company,principal)
        if role=='viewer':raise PermissionError('An owner or analyst must request evaluations')
        if not confirmed:raise ValueError('Confirm use of one review before generating')
        cycle=self.billing(company);now=self.clock()
        reservation=self.allowance.reserve(company,role,request_id,cycle['start'],cycle['end'],now,cycle['limit'])
        if reservation['reused']:
            if reservation['status']=='completed':
                with self.reports.db() as db:
                    row=db.execute('SELECT id FROM saved_reviews WHERE company=? AND request_id=?',(company,request_id)).fetchone()
                if not row:raise RuntimeError('Completed review record missing')
                return self.reports.get(company,principal,row[0])
            return {'status':reservation['status'],'request_id':request_id}
        try:
            # Connector resolves/validates dates and company property. Freeze before model use.
            frozen=json.loads(json.dumps(self.snapshot(company,selection),allow_nan=False))
            review=self.evaluate(json.loads(json.dumps(frozen)))
            # Recheck access after slow processing, before publishing.
            if self.role(company,principal) not in ('owner','analyst'):raise PermissionError('Generation access removed')
            # Publication and consumption commit together; failures roll back both.
            with self.reports.db() as db:
                db.execute('BEGIN IMMEDIATE')
                report_id=self.reports.publish(company,request_id,now,frozen,review,transaction=db)
                self.allowance.finish(company,request_id,True,transaction=db)
        except Exception:
            self.allowance.finish(company,request_id,False)
            raise
        return self.reports.get(company,principal,report_id)
