"""Durable three-pass orchestration; hosting and paid approval remain prerequisites."""
import gzip
from functools import lru_cache
import hashlib
import json
import re
import sqlite3
from pathlib import Path
from .decisions import final_decision


@lru_cache(maxsize=2)
def training_rows(bundle,version):
    with gzip.open(bundle,'rt',encoding='utf-8') as f:rows=json.load(f)['data']
    return [r for r in rows if r['annotations'].get('EGCG',{}).get('human_main') and int(hashlib.sha256(('paperdesk-v1:'+r['pmid']).encode()).hexdigest(),16)%5!=0]

def teacher_examples(bundle, paper, limit=8, version=None):
    """Training-only positive examples. Similarity chooses examples, not decisions."""
    stamp=Path(bundle).stat();rows=training_rows(str(bundle),version or (stamp.st_mtime_ns,stamp.st_size))
    words=set(re.findall(r'[a-z]+',paper.get('title_en','').lower()))
    examples=[]
    for row in rows:
        if row['pmid']==paper['pmid']:continue
        if int(hashlib.sha256(('paperdesk-v1:'+row['pmid']).encode()).hexdigest(),16)%5==0:continue
        labels=row['annotations'].get('EGCG',{}).get('human_main',[])
        if not labels:continue
        score=len(words.intersection(re.findall(r'[a-z]+',(row.get('title_en') or '').lower())))
        examples.append((score,{'pmid':row['pmid'],'title_en':row['title_en'],'human_raw_categories':labels,
            'ai_original_categories':row['annotations']['EGCG'].get('ai_human',[]),
            'human_added_categories':sorted(set(labels)-set(row['annotations']['EGCG'].get('ai_human',[]))),
            'human_removed_categories':sorted(set(row['annotations']['EGCG'].get('ai_human',[]))-set(labels)),
            'human_folder':'ヒト試験効果あり','reason':None,'absence_does_not_mean_exclusion':True}))
    return [x[1] for x in sorted(examples,key=lambda x:(-x[0],x[1]['pmid']))[:limit]]

class Pipeline:
    def __init__(self,path,classifier,bundle):
        self.path,self.classifier,self.bundle=str(path),classifier,str(bundle)
        self.teacher_version=hashlib.sha256(Path(bundle).read_bytes()).hexdigest()
        Path(path).parent.mkdir(parents=True,exist_ok=True)
        with sqlite3.connect(path) as db:
            db.executescript('CREATE TABLE IF NOT EXISTS research_decisions(job TEXT,ingredient TEXT,pmid TEXT,round INTEGER,payload TEXT,PRIMARY KEY(job,ingredient,pmid,round)); CREATE TABLE IF NOT EXISTS research_final(job TEXT,ingredient TEXT,pmid TEXT,payload TEXT,PRIMARY KEY(job,ingredient,pmid)); CREATE TABLE IF NOT EXISTS research_cache(key TEXT PRIMARY KEY,payload TEXT);')
    def process(self,job,ingredient,paper,on_round=None):
        signature=json.dumps(['threepass-v2',ingredient,paper,self.classifier.model,self.teacher_version],sort_keys=True,ensure_ascii=False)
        cache_key=hashlib.sha256(signature.encode()).hexdigest();checkpoint_job=job+':'+cache_key
        with sqlite3.connect(self.path) as db:cached=db.execute('SELECT payload FROM research_cache WHERE key=?',(cache_key,)).fetchone()
        if cached:
            result=json.loads(cached[0]);result['cache_reused']=True
            with sqlite3.connect(self.path) as db:db.execute('INSERT OR REPLACE INTO research_final VALUES(?,?,?,?)',(job,ingredient,paper['pmid'],json.dumps(result,ensure_ascii=False)))
            return result
        decisions=[]
        examples=teacher_examples(self.bundle,paper,version=self.teacher_version)
        # Round 1 is blind; round 2 sees earlier results to inspect consistency.
        for round_number in range(3):
            if on_round:on_round(round_number)
            with sqlite3.connect(self.path) as db:
                existing=db.execute('SELECT payload FROM research_decisions WHERE job=? AND ingredient=? AND pmid=? AND round=?',(checkpoint_job,ingredient,paper['pmid'],round_number)).fetchone()
            if existing:decision=json.loads(existing[0])
            else:
                request_id=hashlib.sha256(json.dumps([job,cache_key,round_number],ensure_ascii=False).encode()).hexdigest()
                decision=self.classifier.judge(request_id,ingredient,paper,examples,round_number,decisions)
                with sqlite3.connect(self.path) as db:
                    db.execute('INSERT OR IGNORE INTO research_decisions VALUES(?,?,?,?,?)',(checkpoint_job,ingredient,paper['pmid'],round_number,json.dumps(decision,ensure_ascii=False)))
            decisions.append(decision)
        final=final_decision(decisions[0],decisions[1:])
        paper=dict(paper)
        if isinstance(decisions[0].get('title_ja'),str) and decisions[0]['title_ja'].strip():paper['title_ja']=decisions[0]['title_ja'][:1200]
        result={'cache_reused':False,'teacher_version':self.teacher_version,'paper':paper,'ingredient':ingredient,'initial_decision':decisions[0],
            'checks':[{'round':i,'decision':d} for i,d in enumerate(decisions[1:],1)],'final_decision':final}
        with sqlite3.connect(self.path) as db:
            db.execute('INSERT OR REPLACE INTO research_final VALUES(?,?,?,?)',(job,ingredient,paper['pmid'],json.dumps(result,ensure_ascii=False)))
            db.execute('INSERT OR REPLACE INTO research_cache VALUES(?,?)',(cache_key,json.dumps(result,ensure_ascii=False)))
        return result
