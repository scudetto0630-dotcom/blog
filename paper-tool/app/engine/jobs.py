"""Optional server-side jobs. Not available on a Render static site."""
import json
import logging
import math
import hashlib
import os
import sqlite3
import threading
import uuid
from datetime import datetime,timezone
from pathlib import Path
from tools.fetch_pubmed import retrieve
from .decisions import ApprovalRequired,BudgetExceeded,Classifier
from .ledger import Ledger
from .pipeline import Pipeline,teacher_examples
from .export import export_results
from ..runtime import ai_key
from ..ingredients import search_name, normal

def now():return datetime.now(timezone.utc).isoformat()

class ResearchJobs:
    def __init__(self,path,bundle,client=None,classifier_factory=None):
        self.path,self.bundle=str(path),str(bundle)
        self.client,self.factory=client,classifier_factory
        with sqlite3.connect(path) as db:
            db.execute('CREATE TABLE IF NOT EXISTS research_jobs(id TEXT PRIMARY KEY,snapshot TEXT,status TEXT,phase INTEGER,processed INTEGER,total INTEGER,error TEXT,archive BLOB,created TEXT)')
            columns={r[1] for r in db.execute('PRAGMA table_info(research_jobs)')}
            for name,definition in [('approved','INTEGER NOT NULL DEFAULT 0'),('cap','REAL'),('estimate_hash','TEXT')]:
                if name not in columns:db.execute('ALTER TABLE research_jobs ADD COLUMN '+name+' '+definition)
        self.stop_event=threading.Event();self.thread=threading.Thread(target=self.run,daemon=True,name='research-worker')
    def create(self,snapshot):
        names=[normal(search_name(i)) for i in snapshot['ingredients']]
        if len(set(names))!=len(names):raise ValueError('同じ成分の日本語名と英語名が重複しています。')
        id=str(uuid.uuid4())
        with sqlite3.connect(self.path) as db:db.execute('INSERT INTO research_jobs(id,snapshot,status,phase,processed,total,error,archive,created) VALUES(?,?,?,0,0,0,NULL,NULL,?)',(id,json.dumps(snapshot,ensure_ascii=False),'queued',now()))
        return self.get(id)
    def get(self,id):
        with sqlite3.connect(self.path) as db:
            db.row_factory=sqlite3.Row
            row=db.execute('SELECT id,snapshot,status,phase,processed,total,error,created,approved,cap FROM research_jobs WHERE id=?',(id,)).fetchone()
        if not row:return None
        result=dict(row);result['snapshot']=json.loads(result['snapshot']);result['paid_enabled']=os.environ.get('PAPERDESK_ALLOW_PAID')=='explicitly-approved';return result
    def list(self):
        with sqlite3.connect(self.path) as db:ids=[r[0] for r in db.execute('SELECT id FROM research_jobs ORDER BY created DESC LIMIT 200')]
        return [self.get(id) for id in ids]
    def results(self,id,page=0,category=None,status=None):
        if page<0 or page>2000:raise ValueError('ページが不正です。')
        job=self.get(id)
        if not job or job['status']!='completed':raise ValueError('結果はまだ確定していません。')
        from ..taxonomy import NAMES
        where=['job=?'];parameters=[id]
        if category:
            if category not in NAMES:raise ValueError('指定51分類以外です。')
            where.append("EXISTS(SELECT 1 FROM json_each(payload,'$.final_decision.categories') WHERE value=?)");parameters.append(category)
        if status:
            if status not in ('adopted','excluded','review'):raise ValueError('判定が不正です。')
            where.append("json_extract(payload,'$.final_decision.status')=?");parameters.append(status)
        condition=' AND '.join(where)
        with sqlite3.connect(self.path) as db:
            exists=db.execute("SELECT 1 FROM sqlite_master WHERE name='research_final'").fetchone()
            if not exists:return {'papers':[],'summary':{'contexts':0,'adopted':0,'excluded':0,'review':0,'human_effective':0},'page':page,'filtered_count':0}
            summary={'contexts':db.execute('SELECT count(*) FROM research_final WHERE job=?',(id,)).fetchone()[0]}
            for status in ('adopted','excluded','review'):
                summary[status]=db.execute("SELECT count(*) FROM research_final WHERE job=? AND json_extract(payload,'$.final_decision.status')=?",(id,status)).fetchone()[0]
            summary['human_effective']=db.execute("SELECT count(*) FROM research_final WHERE job=? AND json_extract(payload,'$.final_decision.status')='adopted' AND json_extract(payload,'$.final_decision.human_trial')='effective'",(id,)).fetchone()[0]
            filtered=db.execute('SELECT count(*) FROM research_final WHERE '+condition,parameters).fetchone()[0]
            papers=[json.loads(r[0]) for r in db.execute("SELECT payload FROM research_final WHERE "+condition+" ORDER BY CAST(json_extract(payload,'$.paper.publication_date.Year') AS INTEGER) DESC,CAST(pmid AS INTEGER) DESC LIMIT 50 OFFSET ?",(*parameters,page*50))]
        return {'papers':papers,'summary':summary,'page':page,'filtered_count':filtered}
    def archive(self,id):
        with sqlite3.connect(self.path) as db:row=db.execute("SELECT archive FROM research_jobs WHERE id=? AND status='completed'",(id,)).fetchone()
        return row[0] if row else None
    def update(self,id,status,phase,processed=0,total=0,error=None,archive=None):
        with sqlite3.connect(self.path) as db:db.execute('UPDATE research_jobs SET status=?,phase=?,processed=?,total=?,error=?,archive=coalesce(?,archive) WHERE id=?',(status,phase,processed,total,error,archive,id))
    def classifier(self,id):
        if self.factory:return self.factory(id)
        if os.environ.get('PAPERDESK_ALLOW_PAID')!='explicitly-approved':return Classifier(None,os.environ.get('PAPERDESK_AI_MODEL','not-configured'))
        job=self.get(id)
        if not job['approved']:return Classifier(None,os.environ.get('PAPERDESK_AI_MODEL','not-configured'))
        key=ai_key();model=os.environ.get('PAPERDESK_AI_MODEL')
        if not key or not model:raise ApprovalRequired('運営者側のAI設定が未完了です。利用者によるAPI設定は不要です。')
        try:cap=job['cap'];ir=float(os.environ['PAPERDESK_INPUT_USD_PER_MILLION']);ou=float(os.environ['PAPERDESK_OUTPUT_USD_PER_MILLION'])
        except (KeyError,ValueError):raise ApprovalRequired('運営者によるモデル料金・ジョブ上限の設定が必要です。')
        return Classifier(Ledger(self.path,id,cap),model,key=key,approved=True,input_rate=ir,output_rate=ou)
    def estimate(self,id):
        job=self.get(id)
        if not job:raise ValueError('処理が見つかりません。')
        model=os.environ.get('PAPERDESK_AI_MODEL')
        try:ir=float(os.environ['PAPERDESK_INPUT_USD_PER_MILLION']);ou=float(os.environ['PAPERDESK_OUTPUT_USD_PER_MILLION']);maximum=float(os.environ['PAPERDESK_JOB_CAP_USD'])
        except (KeyError,ValueError):raise ApprovalRequired('運営者側のモデル料金・費用上限が未設定です。')
        if not model or not math.isfinite(maximum) or maximum<=0:raise ApprovalRequired('運営者側のモデル・費用上限が未設定です。')
        classifier=Classifier(None,model,input_rate=ir,output_rate=ou)
        with sqlite3.connect(self.path) as db:
            papers=[]
            for ingredient in job['snapshot']['ingredients']:
                papers.extend((ingredient,json.loads(row[0])) for row in db.execute('SELECT p.payload FROM papers p JOIN queue q ON p.pmid=q.pmid WHERE q.ingredient=? LIMIT 100',(ingredient,)))
        if not papers:raise ApprovalRequired('見積り用の論文取得がまだ完了していません。')
        samples=papers[:100];cost=0
        for ingredient,paper in samples:
            examples=teacher_examples(self.bundle,paper)
            for round_number in range(3):
                prior=[{'reason':'X'*6000},{'reason':'X'*6000}] if round_number==2 else None
                cost+=classifier.estimate(classifier.messages(ingredient,paper,examples,round_number,prior))
        per_paper=cost/len(samples)
        details={'model':model,'sample_size':len(samples),'estimated_100_usd':per_paper*100,'estimated_job_usd':per_paper*job['total'],'maximum_job_cap_usd':maximum,'currency':'USD','method':'UTF-8文字量を基に3判定分を保守的に見積り。請求額の保証ではありません。'}
        fingerprint=hashlib.sha256(json.dumps(details,sort_keys=True).encode()).hexdigest()
        with sqlite3.connect(self.path) as db:db.execute('UPDATE research_jobs SET estimate_hash=? WHERE id=?',(fingerprint,id))
        return {**details,'estimate_fingerprint':fingerprint}
    def approve(self,id,cap,fingerprint):
        if os.environ.get('PAPERDESK_ALLOW_PAID')!='explicitly-approved':raise ApprovalRequired('運営者が有料処理を許可していません。')
        if not ai_key():raise ApprovalRequired('運営者側のAI接続設定が未完了です。')
        details=self.estimate(id)
        if fingerprint!=details['estimate_fingerprint']:raise ApprovalRequired('最新の見積りを確認してください。')
        if not isinstance(cap,(int,float)) or isinstance(cap,bool) or not math.isfinite(cap) or cap<=0 or cap>details['maximum_job_cap_usd']:raise ValueError('費用上限が不正です。')
        with sqlite3.connect(self.path) as db:
            if not db.execute("UPDATE research_jobs SET approved=1,cap=?,status='queued' WHERE id=? AND status='awaiting_approval'",(cap,id)).rowcount:raise ValueError('承認待ちの処理ではありません。')
        return self.get(id)
    def process(self,id):
        job=self.get(id);snapshot=job['snapshot'];self.update(id,'running',1)
        base=0
        for ingredient in snapshot['ingredients']:
            if self.stop_event.is_set():return
            count=retrieve(ingredient,self.path,self.client,on_progress=lambda done,total:self.update(id,'running',1,base+done,base+total))
            base+=count
        with sqlite3.connect(self.path) as db:
            rows=[]
            for ingredient in snapshot['ingredients']:
                rows.extend((ingredient,json.loads(p[0])) for p in db.execute('SELECT p.payload FROM papers p JOIN queue q ON q.pmid=p.pmid WHERE q.ingredient=?',(ingredient,)))
        total=len(rows);self.update(id,'running',2,total=total)
        if not rows:
            self.update(id,'completed',7,archive=export_results(snapshot['name'],[],snapshot['ingredients']));return
        try:
            classifier=self.classifier(id)
            pipeline=Pipeline(self.path,classifier,self.bundle)
            results=[]
            for i,(ingredient,paper) in enumerate(rows):
                if self.stop_event.is_set():return
                results.append(pipeline.process(id,ingredient,paper,on_round=lambda r:self.update(id,'running',3+r,i,total)))
                self.update(id,'running',5,i+1,total)
            self.update(id,'running',6,len(rows),total)
            archive=export_results(snapshot['name'],results,snapshot['ingredients'])
            self.update(id,'completed',7,total,total,archive=archive)
        except (ApprovalRequired,BudgetExceeded) as e:
            current=self.get(id)
            self.update(id,'awaiting_approval',current['phase'],current['processed'],total,error=str(e))
    def retry(self,id):
        job=self.get(id)
        if not job:return None
        if job['status'] in ('failed','awaiting_approval'):self.update(id,'queued',job['phase'],job['processed'],job['total'])
        return self.get(id)
    def start(self):self.thread.start()
    def stop(self):self.stop_event.set();self.thread.join(timeout=5)
    def run(self):
        while not self.stop_event.is_set():
            with sqlite3.connect(self.path) as db:row=db.execute("SELECT id FROM research_jobs WHERE status IN ('queued','running') ORDER BY created LIMIT 1").fetchone()
            if not row:self.stop_event.wait(.2);continue
            try:self.process(row[0])
            except Exception:
                logging.exception('research_job_failure')
                job=self.get(row[0]);self.update(row[0],'failed',job['phase'],job['processed'],job['total'],'処理を中断しました。保存済みの取得・判定から再試行できます。')
                self.stop_event.wait(.5)
