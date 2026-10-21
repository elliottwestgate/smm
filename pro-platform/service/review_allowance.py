"""Company review allowance. Call only after trusted company/role resolution.
Billing boundaries must come from the membership adapter, never browser input.
Dashboard reads and existing report downloads do not call this ledger.
"""
import sqlite3
from datetime import datetime
from contextlib import nullcontext

class ReviewAllowance:
    def __init__(self, database):
        self.database = database
        with self.connect() as db:
            db.execute("""CREATE TABLE IF NOT EXISTS review_usage (
              company TEXT NOT NULL, request_id TEXT NOT NULL, cycle_start TEXT NOT NULL,
              cycle_end TEXT NOT NULL, status TEXT NOT NULL,
              PRIMARY KEY(company,request_id))""")

    def connect(self):
        return sqlite3.connect(self.database, timeout=10)

    @staticmethod
    def boundaries(start, end, now):
        values = [datetime.fromisoformat(v) for v in (start, end, now)]
        if any(v.tzinfo is None for v in values):
            raise ValueError('Timezone-aware billing dates required')
        a,b,n=values
        if not a <= n < b:
            raise ValueError('No active billing period')
        return a.isoformat(), b.isoformat()

    def reserve(self, company, role, request_id, start, end, now, limit=4):
        if role not in ('owner','analyst'):
            raise PermissionError('Only owners and analysts can generate evaluations')
        if not company or not request_id or len(request_id)>100 or not isinstance(limit,int) or not 0<=limit<=1000:
            raise ValueError('Invalid review request')
        start,end=self.boundaries(start,end,now)
        with self.connect() as db:
            db.execute('BEGIN IMMEDIATE')
            prior=db.execute('SELECT status,cycle_start,cycle_end FROM review_usage WHERE company=? AND request_id=?',(company,request_id)).fetchone()
            if prior:
                if prior[1:] != (start,end):
                    raise ValueError('Request belongs to a different billing period')
                return {'status':prior[0],'reused':True}
            used=db.execute("SELECT COUNT(*) FROM review_usage WHERE company=? AND cycle_start=? AND cycle_end=? AND status IN ('reserved','completed')",(company,start,end)).fetchone()[0]
            if used>=limit:
                raise ValueError('Monthly review allowance reached')
            db.execute('INSERT INTO review_usage VALUES (?,?,?,?,?)',(company,request_id,start,end,'reserved'))
            return {'status':'reserved','remaining':limit-used-1,'reused':False}

    def finish(self, company, request_id, success, transaction=None):
        # The worker calls this; the client cannot mark its own generation failed.
        with (nullcontext(transaction) if transaction is not None else self.connect()) as db:
            if transaction is None:db.execute('BEGIN IMMEDIATE')
            row=db.execute('SELECT status FROM review_usage WHERE company=? AND request_id=?',(company,request_id)).fetchone()
            if not row:
                raise ValueError('No matching review reservation')
            if row[0]=='reserved':
                db.execute('UPDATE review_usage SET status=? WHERE company=? AND request_id=?',('completed' if success else 'failed',company,request_id))
            # Completed reports cannot later be released by a retry or timeout.

    def usage(self, company, start, end):
        with self.connect() as db:
            return db.execute("SELECT COUNT(*) FROM review_usage WHERE company=? AND cycle_start=? AND cycle_end=? AND status IN ('reserved','completed')",(company,start,end)).fetchone()[0]
