#!/usr/bin/env python3
"""Resumable free retrieval; intentionally cannot execute paid inference."""
import argparse
import json
import sqlite3
import sys
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from app.pubmed import PubMed

def retrieve(ingredient, path, client=None, on_progress=None):
    client=client or PubMed()
    Path(path).parent.mkdir(parents=True,exist_ok=True)
    db=sqlite3.connect(path)
    db.execute('PRAGMA journal_mode=WAL')
    db.executescript('CREATE TABLE IF NOT EXISTS queue (ingredient TEXT, pmid TEXT, PRIMARY KEY(ingredient,pmid)); CREATE TABLE IF NOT EXISTS papers(pmid TEXT PRIMARY KEY, payload TEXT); CREATE TABLE IF NOT EXISTS scans(ingredient TEXT PRIMARY KEY, completed INTEGER);')
    try:
        scanned=db.execute('SELECT completed FROM scans WHERE ingredient=?',(ingredient,)).fetchone()
        if not scanned or not scanned[0]:
            for pmid in client.ids(ingredient):
                db.execute('INSERT OR IGNORE INTO queue VALUES(?,?)',(ingredient,pmid))
                db.commit()
            db.execute('INSERT OR REPLACE INTO scans VALUES(?,1)',(ingredient,));db.commit()
        def progress():
            if on_progress:
                total=db.execute('SELECT count(*) FROM queue WHERE ingredient=?',(ingredient,)).fetchone()[0]
                done=db.execute('SELECT count(*) FROM queue q JOIN papers p ON q.pmid=p.pmid WHERE q.ingredient=?',(ingredient,)).fetchone()[0]
                on_progress(done,total)
        progress()
        while True:
            ids=[r[0] for r in db.execute('SELECT q.pmid FROM queue q LEFT JOIN papers p ON p.pmid=q.pmid WHERE q.ingredient=? AND p.pmid IS NULL LIMIT 100',(ingredient,))]
            if not ids: break
            papers=client.fetch(ids)
            if {p['pmid'] for p in papers}!=set(ids): raise ValueError('取得データに欠落があります。')
            with db:
                for p in papers:db.execute('INSERT OR IGNORE INTO papers VALUES(?,?)',(p['pmid'],json.dumps(p,ensure_ascii=False)))
            progress()
        return db.execute('SELECT count(*) FROM queue WHERE ingredient=?',(ingredient,)).fetchone()[0]
    finally:db.close()

if __name__=='__main__':
    parser=argparse.ArgumentParser();parser.add_argument('ingredient');parser.add_argument('--db',default='data/pubmed.sqlite3');args=parser.parse_args()
    print(json.dumps({'retrieved':retrieve(args.ingredient,args.db),'paid_api_requests':0,'classification':'not_executed'}))
