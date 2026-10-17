"""Pro-first development API. Run behind a production WSGI server and TLS proxy.
Provider ingestion is deliberately not implemented in this foundation release.
"""
import hashlib
import hmac
import json
import os
import re
import sqlite3
import time
from datetime import date
from zoneinfo import ZoneInfo, ZoneInfoNotFoundError

METRICS = {
    'Brand Awareness': ['Total users', 'New users', 'Sessions', 'Views'],
    'Brand Acquisition': ['Tagged email sessions', 'Organic social sessions', 'Organic search sessions', 'Referral sessions', 'Direct / unattributed sessions'],
    'Brand Retention': ['Primary actions', 'Subscribed audience', 'Unique email clicks', 'Email opens (qualified)'],
}

class API:
    def __init__(self, database, tenant_keys, clock=time.time):
        if not tenant_keys or any(not re.fullmatch(r'[a-z0-9_-]{1,64}', t) or len(k) < 32 for t, k in tenant_keys.items()):
            raise ValueError('Configure tenant IDs and independent secrets of at least 32 characters')
        self.database, self.keys, self.clock = database, tenant_keys, clock
        with self.connect() as db:
            db.executescript('''
            CREATE TABLE IF NOT EXISTS nonces (tenant TEXT, nonce TEXT, expires INTEGER, PRIMARY KEY(tenant,nonce));
            CREATE TABLE IF NOT EXISTS projects (
                id INTEGER PRIMARY KEY, tenant TEXT NOT NULL, owner TEXT NOT NULL,
                data TEXT NOT NULL, revision INTEGER NOT NULL DEFAULT 1, updated INTEGER NOT NULL,
                UNIQUE(tenant,owner));
            CREATE TABLE IF NOT EXISTS reflections (
                id INTEGER PRIMARY KEY, project_id INTEGER NOT NULL REFERENCES projects(id),
                week_start TEXT NOT NULL, data TEXT NOT NULL, created INTEGER NOT NULL);
            ''')

    def connect(self):
        db = sqlite3.connect(self.database, timeout=10)
        db.row_factory = sqlite3.Row
        db.execute('PRAGMA foreign_keys=ON')
        return db

    def handle(self, method, path, headers, body):
        headers = {k.lower(): v for k, v in headers.items()}
        if len(body) > 32768:
            return 413, {'error': 'Request too large'}
        tenant = headers.get('x-westgate-tenant', '')
        owner = headers.get('x-westgate-principal', '')
        stamp = headers.get('x-westgate-timestamp', '')
        nonce = headers.get('x-westgate-nonce', '')
        signature = headers.get('x-westgate-signature', '')
        now = int(self.clock())
        if tenant not in self.keys or not re.fullmatch(r'[1-9][0-9]{0,18}', owner) or not re.fullmatch(r'[a-f0-9]{32}', nonce):
            return 401, {'error': 'Unauthorized'}
        if not stamp.isdigit() or abs(now - int(stamp)) > 120:
            return 401, {'error': 'Expired authorization'}
        canonical = '\n'.join([method, path, tenant, owner, stamp, nonce, hashlib.sha256(body).hexdigest()])
        expected = hmac.new(self.keys[tenant].encode(), canonical.encode(), hashlib.sha256).hexdigest()
        if not hmac.compare_digest(expected, signature):
            return 401, {'error': 'Unauthorized'}
        with self.connect() as db:
            db.execute('DELETE FROM nonces WHERE expires < ?', (now,))
            try:
                db.execute('INSERT INTO nonces VALUES(?,?,?)', (tenant, nonce, now + 240))
            except sqlite3.IntegrityError:
                return 401, {'error': 'Authorization already used'}
        try:
            payload = json.loads(body) if body else {}
            if not isinstance(payload, dict):
                raise ValueError('JSON object required')
            return self.route(method, path, tenant, owner, payload, now)
        except (ValueError, TypeError, KeyError) as exc:
            return 422, {'error': str(exc)}

    def route(self, method, path, tenant, owner, payload, now):
        if path not in ['/v1/project', '/v1/report', '/v1/reflections']:
            return 404, {'error': 'Not found'}
        with self.connect() as db:
            if method == 'PUT':
                db.execute('BEGIN IMMEDIATE')
            project = db.execute('SELECT * FROM projects WHERE tenant=? AND owner=?', (tenant, owner)).fetchone()
            if method == 'PUT' and path == '/v1/project':
                data = self.validate_project(payload)
                revision = project['revision'] if project else 0
                if payload.get('revision') != revision:
                    return 409, {'error': 'Campaign changed. Reload before saving.'}
                db.execute('''INSERT INTO projects(tenant,owner,data,updated) VALUES(?,?,?,?)
                    ON CONFLICT(tenant,owner) DO UPDATE SET data=excluded.data,updated=excluded.updated,revision=projects.revision+1''',
                    (tenant, owner, json.dumps(data), now))
                return 200, {'saved': True, 'revision': revision + 1}
            if method == 'GET' and path == '/v1/project':
                return 200, {'project': json.loads(project['data']) if project else None, 'revision': project['revision'] if project else 0}
            if not project:
                return 404, {'error': 'Set up your campaign first'}
            if method == 'GET' and path == '/v1/report':
                return 200, {'status': 'not_connected', 'weekly': None, 'campaign_to_date': None,
                    'panels': {group: [{'metric': name, 'value': None, 'status': 'not_connected'} for name in names] for group, names in METRICS.items()},
                    'connections': {'ga4': 'not_connected', 'email': {'provider': json.loads(project['data']).get('email_provider', 'constant_contact'), 'status': 'not_connected'}},
                    'assessment': {'status': 'pending_policy', 'score': None}}
            if path == '/v1/reflections':
                if method == 'GET':
                    rows = db.execute('SELECT id,week_start,data,created FROM reflections WHERE project_id=? ORDER BY id DESC LIMIT 100', (project['id'],)).fetchall()
                    return 200, {'reflections': [dict(r, data=json.loads(r['data'])) for r in rows]}
                if method == 'POST':
                    start = date.fromisoformat(payload['week_start'])
                    campaign = json.loads(project['data'])
                    if start.weekday() != 6:
                        raise ValueError('Week must start on Sunday')
                    end = date.fromisoformat(campaign['end_date'])
                    begin = date.fromisoformat(campaign['start_date'])
                    from datetime import timedelta
                    if start > end or start + timedelta(days=6) < begin:
                        raise ValueError('Week must overlap your campaign')
                    fields = ['evidence', 'alignment', 'swot', 'experiment', 'success_measure']
                    data = {f: self.text(payload, f, 3000) for f in fields}
                    if not data['experiment'] or not data['success_measure']:
                        raise ValueError('Add an experiment and a success measure')
                    db.execute('INSERT INTO reflections(project_id,week_start,data,created) VALUES(?,?,?,?)', (project['id'], start.isoformat(), json.dumps(data), now))
                    return 201, {'saved': True}
            return 405, {'error': 'Method not allowed'}

    @staticmethod
    def text(payload, key, limit):
        value = payload.get(key, '')
        if not isinstance(value, str) or len(value) > limit:
            raise ValueError('Invalid or oversized ' + key)
        return value.strip()

    def validate_project(self, payload):
        data = {f: self.text(payload, f, 2000 if f in ['story','promise','belief','trust'] else 250)
                for f in ['name','website','timezone','start_date','end_date','primary_action','story','promise','belief','trust']}
        if not data['name'] or not data['primary_action']:
            raise ValueError('Campaign name and primary action are required')
        from urllib.parse import urlsplit
        url = urlsplit(data['website'])
        if url.scheme != 'https' or not url.hostname or url.username or url.password:
            raise ValueError('Use an HTTPS website address without credentials')
        begin, end = date.fromisoformat(data['start_date']), date.fromisoformat(data['end_date'])
        if end < begin:
            raise ValueError('End date must follow start date')
        try:
            ZoneInfo(data['timezone'])
        except (ZoneInfoNotFoundError, ValueError):
            raise ValueError('Use a valid reporting timezone')
        provider = payload.get('email_provider', 'constant_contact')
        if provider not in ['constant_contact', 'mailchimp']:
            raise ValueError('Choose Constant Contact or Mailchimp')
        data['email_provider'] = provider
        data['week_starts_on'] = 'Sunday'
        data['edition'] = 'pro'
        return data

    def __call__(self, environ, start_response):
        if environ.get('QUERY_STRING'):
            status, data = 400, {'error': 'Query strings are not supported'}
        else:
            try:
                length = int(environ.get('CONTENT_LENGTH') or 0)
            except ValueError:
                length = -1
            if length < 0 or length > 32768:
                status, data = 413, {'error': 'Invalid request size'}
            else:
                headers = {key[5:].replace('_','-'): value for key,value in environ.items() if key.startswith('HTTP_')}
                try:
                    status, data = self.handle(environ['REQUEST_METHOD'], environ['PATH_INFO'], headers, environ['wsgi.input'].read(length))
                except Exception:
                    # Do not expose storage errors, secrets, or request data.
                    status, data = 500, {'error': 'Service unavailable'}
        from http import HTTPStatus
        response = json.dumps(data).encode()
        start_response(f'{status} {HTTPStatus(status).phrase}', [('Content-Type','application/json'), ('Cache-Control','private, no-store'), ('Content-Length',str(len(response)))])
        return [response]

def create_app():
    return API(os.environ.get('WESTGATE_DATABASE', 'westgate.sqlite3'), json.loads(os.environ['WESTGATE_TENANT_KEYS']))

if __name__ == '__main__':
    from wsgiref.simple_server import make_server
    make_server('127.0.0.1', 8787, create_app()).serve_forever()
