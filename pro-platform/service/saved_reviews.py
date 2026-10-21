"""Immutable company review records. Only trusted review workers call publish.
Membership and entitlement are resolved on every read, including PDF fetches.
"""
import hashlib
import json
import sqlite3
import uuid
from datetime import date,datetime
from contextlib import nullcontext

class SavedReviews:
    def __init__(self,database,can_read):
        self.database=database;self.can_read=can_read
        with self.db() as db:
            db.execute("""CREATE TABLE IF NOT EXISTS saved_reviews (
              id TEXT PRIMARY KEY, company TEXT NOT NULL, request_id TEXT NOT NULL,
              created TEXT NOT NULL, payload TEXT NOT NULL, digest TEXT NOT NULL,
              UNIQUE(company,request_id))""")
    def db(self):return sqlite3.connect(self.database,timeout=10)
    def publish(self,company,request_id,created,snapshot,review,transaction=None):
        # company and request_id come from the completed generation job.
        if not company or not request_id:raise ValueError('Company and generation request required')
        stamp=datetime.fromisoformat(created)
        if stamp.tzinfo is None:raise ValueError('Timezone-aware creation date required')
        if not isinstance(snapshot,dict) or not isinstance(review,dict):raise ValueError('Report objects required')
        for side in ('current','comparison'):
            dates=snapshot.get('ranges',{}).get(side)
            if not isinstance(dates,list) or len(dates)!=2:raise ValueError('Both date ranges required')
            if date.fromisoformat(dates[0])>date.fromisoformat(dates[1]):raise ValueError('Invalid date range')
        payload=json.dumps({'schema':'wgpp-review-v1','snapshot':snapshot,'review':review},sort_keys=True,allow_nan=False,separators=(',',':'))
        if len(payload.encode())>2097152:raise ValueError('Report too large')
        digest=hashlib.sha256(payload.encode()).hexdigest()
        with (nullcontext(transaction) if transaction is not None else self.db()) as db:
            if transaction is None:db.execute('BEGIN IMMEDIATE')
            prior=db.execute('SELECT id,digest FROM saved_reviews WHERE company=? AND request_id=?',(company,request_id)).fetchone()
            if prior:
                if prior[1]!=digest:raise ValueError('A saved review cannot be overwritten')
                return prior[0]
            identifier=str(uuid.uuid4())
            db.execute('INSERT INTO saved_reviews VALUES(?,?,?,?,?,?)',(identifier,company,request_id,stamp.isoformat(),payload,digest))
        return identifier
    def authorize(self,company,principal):
        if not self.can_read(company,principal):raise PermissionError('Company report access required')
    def get(self,company,principal,identifier):
        self.authorize(company,principal)
        with self.db() as db:
            row=db.execute('SELECT created,payload,digest FROM saved_reviews WHERE company=? AND id=?',(company,identifier)).fetchone()
        if not row:raise LookupError('Report unavailable')
        if hashlib.sha256(row[1].encode()).hexdigest()!=row[2]:raise ValueError('Report integrity check failed')
        return {'id':identifier,'created_at':row[0],'content_hash':row[2],**json.loads(row[1])}
    def list(self,company,principal,limit=20,offset=0):
        self.authorize(company,principal)
        if not isinstance(limit,int) or not 1<=limit<=100 or not isinstance(offset,int) or offset<0:raise ValueError('Invalid page')
        with self.db() as db:
            rows=db.execute('SELECT id,created,payload FROM saved_reviews WHERE company=? ORDER BY created DESC,id DESC LIMIT ? OFFSET ?',(company,limit,offset)).fetchall()
        return [{'id':r[0],'created_at':r[1],'ranges':json.loads(r[2])['snapshot']['ranges']} for r in rows]
