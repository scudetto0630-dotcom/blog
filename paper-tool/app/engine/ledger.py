import json
import math
import sqlite3
from pathlib import Path
from .decisions import BudgetExceeded, ApprovalRequired

class Ledger:
    """Atomic cap reservations. Ambiguous failures block automatic charge retries."""
    def __init__(self,path,job_id,cap):
        if not math.isfinite(cap) or cap<=0:raise ValueError('正の費用上限が必要です。')
        self.path,self.job_id,self.cap=str(path),job_id,cap
        Path(path).parent.mkdir(parents=True,exist_ok=True)
        with sqlite3.connect(path) as db:
            db.executescript('CREATE TABLE IF NOT EXISTS spending(request_id TEXT PRIMARY KEY,job_id TEXT,reserved REAL,actual REAL,decision TEXT);')
    def reserve(self,request_id,cost):
        if not math.isfinite(cost) or cost<=0:raise ValueError('費用見積りが不正です。')
        with sqlite3.connect(self.path) as db:
            db.execute('BEGIN IMMEDIATE')
            old=db.execute('SELECT job_id,decision FROM spending WHERE request_id=?',(request_id,)).fetchone()
            if old:
                if old[0]!=self.job_id:raise ValueError('別ジョブの費用予約は再利用できません。')
                if old[1] is None:raise ApprovalRequired('前回APIの請求・応答状態が不明です。自動再送を停止しました。')
                return json.loads(old[1])
            used=db.execute('SELECT coalesce(sum(coalesce(actual,reserved)),0) FROM spending WHERE job_id=?',(self.job_id,)).fetchone()[0]
            if used+cost>self.cap:raise BudgetExceeded('ジョブ費用上限に達するため新規APIリクエストを停止しました。')
            db.execute('INSERT INTO spending VALUES(?,?,?,NULL,NULL)',(request_id,self.job_id,cost))
        return None
    def complete(self,request_id,actual,decision):
        if not math.isfinite(actual) or actual<0:raise ValueError('実際の費用が不正です。')
        with sqlite3.connect(self.path) as db:
            row=db.execute('SELECT reserved FROM spending WHERE request_id=? AND job_id=?',(request_id,self.job_id)).fetchone()
            if not row:raise ValueError('費用予約がありません。')
            db.execute('UPDATE spending SET actual=?,decision=? WHERE request_id=?',(actual,json.dumps(decision,ensure_ascii=False),request_id))
        if actual>row[0]:raise BudgetExceeded('見積りを超過したため停止しました。モデル料金・トークン上限の設定を確認してください。')
