#!/usr/bin/env python3
"""Consistent SQLite backup; never copy a live WAL database as a single raw file."""
import argparse
import sqlite3
from datetime import datetime, timezone
from pathlib import Path

root = Path(__file__).resolve().parents[1]
p = argparse.ArgumentParser()
p.add_argument('--db', default=str(root / 'data' / 'paperdesk.sqlite3'))
p.add_argument('--output')
args = p.parse_args()
source = Path(args.db)
if not source.exists(): p.error('保存データが見つかりません。')
destination = Path(args.output) if args.output else root / 'data' / 'backups' / (datetime.now(timezone.utc).strftime('%Y%m%d-%H%M%S') + '.sqlite3')
if destination.exists(): p.error('出力先が既にあります。上書きしません。')
destination.parent.mkdir(parents=True, exist_ok=True)
with sqlite3.connect(f'file:{source}?mode=ro', uri=True) as origin:
    with sqlite3.connect(destination) as backup:
        origin.backup(backup)
        result = backup.execute('PRAGMA integrity_check').fetchone()[0]
        if result != 'ok': raise RuntimeError('バックアップ検査に失敗しました。')
print('バックアップを作成し、整合性を確認しました:', destination)
