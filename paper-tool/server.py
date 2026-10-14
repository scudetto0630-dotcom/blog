#!/usr/bin/env python3
import argparse
import base64
import fcntl
import hmac
import json
import logging
import os
import sqlite3
import sys
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import urlsplit
from app.models import STAGES, ValidationError, validate_input
from app.store import Store
from app.taxonomy import TAXONOMY, VERSION
from app.worker import DemoWorker

ROOT = Path(__file__).resolve().parent
STATIC = ROOT / 'app' / 'static'

class Handler(BaseHTTPRequestHandler):
    server_version = 'PaperDesk'

    def log_message(self, fmt, *args):
        # Do not log URL query data, authorization headers, or submitted input.
        logging.info('http method=%s status=%s', self.command, args[1] if len(args) > 1 else '-')

    def respond(self, status, payload=None, content_type='application/json; charset=utf-8'):
        data = payload if isinstance(payload, bytes) else json.dumps(payload, ensure_ascii=False).encode()
        self.send_response(status)
        self.send_header('Content-Type', content_type)
        self.send_header('Content-Length', str(len(data)))
        self.send_header('Cache-Control', 'no-store')
        self.send_header('X-Content-Type-Options', 'nosniff')
        self.send_header('Referrer-Policy', 'no-referrer')
        self.send_header('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'")
        self.end_headers()
        self.wfile.write(data)

    def authorized(self):
        password = self.server.password
        if not password: return True
        raw = self.headers.get('Authorization', '')
        try:
            decoded = base64.b64decode(raw.removeprefix('Basic '), validate=True).decode()
            valid = raw.startswith('Basic ') and hmac.compare_digest(decoded.encode('utf-8'), ('user:' + password).encode('utf-8'))
        except (ValueError, UnicodeDecodeError): valid = False
        if not valid:
            self.send_response(401)
            self.send_header('WWW-Authenticate', 'Basic realm="PaperDesk", charset="UTF-8"')
            self.send_header('Content-Length', '0')
            self.end_headers()
        return valid

    def input(self):
        length = self.headers.get('Content-Length', '0')
        if not length.isdigit() or not 0 < int(length) <= 32768:
            raise ValidationError('入力データのサイズが正しくありません。')
        if self.headers.get('Content-Type', '').split(';')[0] != 'application/json':
            raise ValidationError('JSON形式で送信してください。')
        try: return json.loads(self.rfile.read(int(length)))
        except (json.JSONDecodeError, UnicodeDecodeError): raise ValidationError('入力データを読み取れません。')

    def route(self):
        path = urlsplit(self.path).path
        if self.command == 'GET' and path == '/health': return self.respond(200, {'status': 'ok'})
        if not self.authorized(): return
        if self.command != 'GET':
            # JSON-only writes plus same-origin checks protect browser requests against CSRF.
            origin = self.headers.get('Origin')
            if origin and urlsplit(origin).netloc != self.headers.get('Host'):
                return self.respond(403, {'error': '別のサイトからの操作は許可されていません。'})
        store = self.server.store
        if self.command == 'GET':
            if path == '/api/config': return self.respond(200, {'mode': 'demo', 'stages': STAGES, 'taxonomy': TAXONOMY, 'taxonomy_version': VERSION, 'taxonomy_provisional': False})
            if path == '/api/products': return self.respond(200, {'products': store.products()})
            if path == '/api/jobs': return self.respond(200, {'jobs': store.jobs()})
            if path.startswith('/api/products/'):
                item = store.get_product(path.split('/')[-1])
                return self.respond(200 if item else 404, item or {'error': '製品が見つかりません。'})
            if path.startswith('/api/jobs/'):
                item = store.get_job(path.split('/')[-1])
                return self.respond(200 if item else 404, item or {'error': '処理履歴が見つかりません。'})
            files = {'/': ('index.html', 'text/html; charset=utf-8'), '/app.js': ('app.js', 'text/javascript; charset=utf-8'), '/style.css': ('style.css', 'text/css; charset=utf-8'), '/mark.svg': ('mark.svg', 'image/svg+xml'), '/research.js': ('research.js', 'text/javascript; charset=utf-8'), '/research-worker.js': ('research-worker.js', 'text/javascript; charset=utf-8'), '/vendor/fflate.js': ('vendor/fflate.js', 'text/javascript; charset=utf-8')}
            if path in files:
                name, mime = files[path]
                return self.respond(200, (STATIC / name).read_bytes(), mime)
            return self.respond(404, {'error': 'ページが見つかりません。'})
        if self.command in ('POST', 'PUT'):
            data = validate_input(self.input())
            if self.command == 'POST' and path == '/api/products': return self.respond(201, store.save_product(data))
            if self.command == 'PUT' and path.startswith('/api/products/'): return self.respond(200, store.save_product(data, path.split('/')[-1]))
            if self.command == 'POST' and path == '/api/jobs': return self.respond(202, store.create_job(data))
        if self.command == 'DELETE' and path.startswith('/api/products/'):
            deleted = store.delete_product(path.split('/')[-1])
            return self.respond(200 if deleted else 404, {'deleted': True} if deleted else {'error': '製品が見つかりません。'})
        return self.respond(404, {'error': 'この操作は利用できません。'})

    def handle_request(self):
        try: self.route()
        except ValidationError as exc: self.respond(400, {'error': str(exc)})
        except KeyError: self.respond(404, {'error': '製品が見つかりません。'})
        except (BrokenPipeError, ConnectionResetError): pass
        except sqlite3.Error:
            logging.exception('database_operation_failed')
            self.respond(503, {'error': '保存先にアクセスできません。入力内容を残したまま、時間をおいて再試行してください。'})
        except Exception:
            logging.exception('request_failed')
            self.respond(500, {'error': '処理に失敗しました。時間をおいて再試行してください。'})

    do_GET = handle_request
    do_POST = handle_request
    do_PUT = handle_request
    do_DELETE = handle_request


def main():
    parser = argparse.ArgumentParser(description='NU SKIN 学術論文整理ツール（第1段階・デモ）')
    parser.add_argument('--host', default=os.environ.get('HOST', '127.0.0.1'))
    parser.add_argument('--port', type=int, default=int(os.environ.get('PORT', '8000')))
    parser.add_argument('--db', default=os.environ.get('DATABASE_PATH', str(ROOT / 'data' / 'paperdesk.sqlite3')))
    parser.add_argument('--demo-delay', type=float, default=0.8)
    args = parser.parse_args()
    password = os.environ.get('APP_ACCESS_PASSWORD', '')
    if args.host not in ('127.0.0.1', 'localhost', '::1') and len(password) < 12:
        parser.error('外部公開には12文字以上の APP_ACCESS_PASSWORD を設定してください。')
    if args.demo_delay <= 0: parser.error('demo-delay must be positive')
    Path(args.db).parent.mkdir(parents=True, exist_ok=True)
    lock = open(args.db + '.lock', 'a')
    try: fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except BlockingIOError: parser.error('同じ保存先を使うサーバーが既に起動しています。')
    logging.basicConfig(level=logging.INFO, format='%(asctime)s %(levelname)s %(message)s')
    store = Store(args.db)
    server = ThreadingHTTPServer((args.host, args.port), Handler)
    server.store, server.password = store, password
    worker = DemoWorker(store, args.demo_delay)
    worker.start()
    print(f'PaperDesk demo: http://{args.host}:{args.port}', flush=True)
    try: server.serve_forever()
    except KeyboardInterrupt: pass
    finally:
        server.server_close()
        worker.stop()
        lock.close()

if __name__ == '__main__': main()
