"""Google connection core. HTTP routes must supply trusted company, user and
session IDs after authorization. Never expose token() output in an HTTP response.
"""
import hashlib
import json
import secrets
import sqlite3
import time
from urllib.parse import urlencode, urlparse
from cryptography.fernet import Fernet

SCOPE='https://www.googleapis.com/auth/analytics.readonly'

class ConnectionError(Exception):
    pass

class GoogleConnection:
    def __init__(self,database,key,client_id,client_secret,redirect_uri,post_form,is_owner,clock=time.time):
        uri=urlparse(redirect_uri)
        if uri.scheme!='https' or not uri.netloc or uri.query or uri.fragment or uri.username:
            raise ValueError('Configure an exact HTTPS callback URL')
        if not client_id or not client_secret:raise ValueError('Google application credentials required')
        self.database=database;self.cipher=Fernet(key);self.client_id=client_id
        self.secret=client_secret;self.redirect=redirect_uri
        self.post_form=post_form;self.is_owner=is_owner;self.clock=clock
        with self.db() as db:
            db.executescript("""
              CREATE TABLE IF NOT EXISTS google_oauth_state(
                digest TEXT PRIMARY KEY, company TEXT NOT NULL, principal TEXT NOT NULL,
                session TEXT NOT NULL, expires INTEGER NOT NULL);
              CREATE TABLE IF NOT EXISTS google_connections(
                company TEXT PRIMARY KEY, encrypted BLOB NOT NULL, updated INTEGER NOT NULL);
            """)
    def db(self):return sqlite3.connect(self.database,timeout=10)
    @staticmethod
    def digest(value):return hashlib.sha256(value.encode()).hexdigest()
    def owner(self,company,user):
        if not self.is_owner(company,user):raise PermissionError('Company owner access required')
    def start(self,company,user,session):
        self.owner(company,user)
        if not session:raise ValueError('Authenticated session required')
        state=secrets.token_urlsafe(32)
        with self.db() as db:
            db.execute('DELETE FROM google_oauth_state WHERE expires<=?',(int(self.clock()),))
            db.execute('INSERT INTO google_oauth_state VALUES(?,?,?,?,?)',
                       (self.digest(state),company,user,self.digest(session),int(self.clock())+600))
        return 'https://accounts.google.com/o/oauth2/v2/auth?'+urlencode({
            'client_id':self.client_id,'redirect_uri':self.redirect,'response_type':'code',
            'scope':SCOPE,'access_type':'offline','prompt':'consent','state':state})
    def callback(self,company,user,session,state,code):
        self.owner(company,user)
        with self.db() as db:
            db.execute('BEGIN IMMEDIATE')
            record=db.execute('SELECT company,principal,session,expires FROM google_oauth_state WHERE digest=?',(self.digest(state),)).fetchone()
            if not record or record[:3]!=(company,user,self.digest(session)) or record[3]<=self.clock():
                raise ConnectionError('Authorization expired or does not match this session')
            db.execute('DELETE FROM google_oauth_state WHERE digest=?',(self.digest(state),))
        if not code:raise ConnectionError('Google authorization was not completed')
        response=self.post_form('https://oauth2.googleapis.com/token',{
            'code':code,'client_id':self.client_id,'client_secret':self.secret,
            'redirect_uri':self.redirect,'grant_type':'authorization_code'})
        if not isinstance(response,dict) or response.get('error'):
            raise ConnectionError('Google authorization failed; reconnect')
        if SCOPE not in response.get('scope','').split():
            raise ConnectionError('Read-only analytics permission was not granted')
        if not response.get('refresh_token') or not response.get('access_token'):
            raise ConnectionError('Offline authorization was not granted; reconnect')
        data={'company':company,'access_token':response['access_token'],
              'refresh_token':response['refresh_token'],
              'expires':self.clock()+self.lifetime(response),'scope':SCOPE}
        self.store(company,data)
        return {'status':'authorized','property_selected':False}
    @staticmethod
    def lifetime(response):
        try:seconds=int(response['expires_in'])
        except (KeyError,ValueError,TypeError):raise ConnectionError('Invalid token expiration') from None
        if not 0<seconds<=86400:raise ConnectionError('Invalid token expiration')
        return seconds
    def store(self,company,data):
        encrypted=self.cipher.encrypt(json.dumps(data).encode())
        with self.db() as db:
            db.execute('INSERT INTO google_connections VALUES(?,?,?) ON CONFLICT(company) DO UPDATE SET encrypted=excluded.encrypted,updated=excluded.updated',
                       (company,encrypted,int(self.clock())))
    def read(self,company):
        with self.db() as db:
            row=db.execute('SELECT encrypted FROM google_connections WHERE company=?',(company,)).fetchone()
        if not row:raise ConnectionError('Connect Google Analytics first')
        data=json.loads(self.cipher.decrypt(row[0]))
        if data.get('company')!=company:raise ConnectionError('Connection ownership mismatch')
        return data
    def token(self,company):
        # Service-only use, after company entitlement and reporting permission check.
        data=self.read(company)
        if data['expires']>self.clock()+60:return data['access_token']
        response=self.post_form('https://oauth2.googleapis.com/token',{
            'client_id':self.client_id,'client_secret':self.secret,
            'refresh_token':data['refresh_token'],'grant_type':'refresh_token'})
        if not isinstance(response,dict) or response.get('error') or not response.get('access_token'):
            raise ConnectionError('Google connection needs reconnection')
        data.update(access_token=response['access_token'],expires=self.clock()+self.lifetime(response))
        self.store(company,data)
        return data['access_token']
    def disconnect(self,company,user):
        self.owner(company,user)
        data=self.read(company)
        # Local collection stops even if Google is temporarily unreachable.
        with self.db() as db:
            db.execute('DELETE FROM google_connections WHERE company=?',(company,))
            db.execute('DELETE FROM google_oauth_state WHERE company=?',(company,))
        try:
            response=self.post_form('https://oauth2.googleapis.com/revoke',{'token':data['refresh_token']})
            revoked=isinstance(response,dict) and not response.get('error')
        except Exception:revoked=False
        return {'status':'disconnected','revocation_confirmed':revoked}
