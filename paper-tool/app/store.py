import json
import sqlite3
import uuid
from contextlib import contextmanager
from datetime import datetime, timezone
from pathlib import Path


def now():
    return datetime.now(timezone.utc).isoformat()

class Store:
    """Persistence boundary. A future authenticated adapter can scope all reads by owner_id."""
    def __init__(self, path):
        self.path = str(path)
        Path(path).parent.mkdir(parents=True, exist_ok=True)
        with self.connection() as db:
            db.execute('PRAGMA journal_mode=WAL')
            db.execute('''CREATE TABLE IF NOT EXISTS products (
                id TEXT PRIMARY KEY, owner_id TEXT NOT NULL DEFAULT 'local', name TEXT NOT NULL,
                ingredients TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)''')
            db.execute('''CREATE TABLE IF NOT EXISTS jobs (
                id TEXT PRIMARY KEY, owner_id TEXT NOT NULL DEFAULT 'local', snapshot TEXT NOT NULL,
                status TEXT NOT NULL, completed_steps INTEGER NOT NULL DEFAULT 0,
                result TEXT, error TEXT, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)''')
            db.execute('PRAGMA user_version=1')

    @contextmanager
    def connection(self):
        db = sqlite3.connect(self.path, timeout=10)
        db.row_factory = sqlite3.Row
        db.execute('PRAGMA busy_timeout=10000')
        try:
            yield db
            db.commit()
        except Exception:
            db.rollback()
            raise
        finally:
            db.close()

    @staticmethod
    def product(row):
        if not row: return None
        data = dict(row)
        data['ingredients'] = json.loads(data['ingredients'])
        return data

    @staticmethod
    def job(row):
        if not row: return None
        data = dict(row)
        data['snapshot'] = json.loads(data['snapshot'])
        data['result'] = json.loads(data['result']) if data['result'] else None
        data['is_demo'] = True
        data['processed_count'] = len(data['snapshot']['ingredients']) * 6 if data['completed_steps'] >= 3 else 0
        return data

    def products(self):
        with self.connection() as db:
            return [self.product(r) for r in db.execute("SELECT * FROM products WHERE owner_id='local' ORDER BY updated_at DESC, id")]

    def get_product(self, product_id):
        with self.connection() as db:
            return self.product(db.execute("SELECT * FROM products WHERE id=? AND owner_id='local'", (product_id,)).fetchone())

    def save_product(self, data, product_id=None):
        timestamp = now()
        with self.connection() as db:
            if product_id:
                count = db.execute("UPDATE products SET name=?,ingredients=?,updated_at=? WHERE id=? AND owner_id='local'", (data['name'], json.dumps(data['ingredients'], ensure_ascii=False), timestamp, product_id)).rowcount
                if not count: raise KeyError('製品が見つかりません。')
            else:
                product_id = str(uuid.uuid4())
                db.execute('INSERT INTO products(id,name,ingredients,created_at,updated_at) VALUES(?,?,?,?,?)', (product_id, data['name'], json.dumps(data['ingredients'], ensure_ascii=False), timestamp, timestamp))
        return self.get_product(product_id)

    def delete_product(self, product_id):
        with self.connection() as db:
            return db.execute("DELETE FROM products WHERE id=? AND owner_id='local'", (product_id,)).rowcount > 0

    def create_job(self, snapshot):
        job_id, timestamp = str(uuid.uuid4()), now()
        with self.connection() as db:
            db.execute('INSERT INTO jobs(id,snapshot,status,created_at,updated_at) VALUES(?,?,?,?,?)', (job_id, json.dumps(snapshot, ensure_ascii=False), 'queued', timestamp, timestamp))
        return self.get_job(job_id)

    def jobs(self):
        with self.connection() as db:
            # History does not need large paper payloads.
            result = [self.job(r) for r in db.execute("SELECT * FROM jobs WHERE owner_id='local' ORDER BY created_at DESC,id LIMIT 200")]
            for job in result:
                if job['result']: job['result'] = {'summary': job['result']['summary'], 'is_demo': True}
            return result

    def get_job(self, job_id):
        with self.connection() as db:
            return self.job(db.execute("SELECT * FROM jobs WHERE id=? AND owner_id='local'", (job_id,)).fetchone())

    def next_job(self):
        with self.connection() as db:
            return self.job(db.execute("SELECT * FROM jobs WHERE status IN ('running','queued') AND owner_id='local' ORDER BY created_at LIMIT 1").fetchone())

    def update_job(self, job_id, status, completed_steps, result=None, error=None):
        with self.connection() as db:
            db.execute('UPDATE jobs SET status=?,completed_steps=?,result=?,error=?,updated_at=? WHERE id=?', (status, completed_steps, json.dumps(result, ensure_ascii=False) if result else None, error, now(), job_id))
