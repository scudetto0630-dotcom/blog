#!/usr/bin/env python3
"""Score saved predictions on held-out human records; performs no API requests."""
import argparse
import gzip
import hashlib
import json
import sqlite3
import sys
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from app.taxonomy import NAMES

def evaluate(bundle,path,job):
    with gzip.open(bundle,'rt',encoding='utf-8') as f:rows=json.load(f)['data']
    gold={r['pmid']:r for r in rows if r['annotations'].get('EGCG',{}).get('human_main') and int(hashlib.sha256(('paperdesk-v1:'+r['pmid']).encode()).hexdigest(),16)%5==0}
    with sqlite3.connect(path) as db:predictions={p['paper']['pmid']:p for (text,) in db.execute('SELECT payload FROM research_final WHERE job=? AND ingredient=?',(job,'EGCG')) for p in [json.loads(text)]}
    matched=gold.keys() & predictions.keys();tp=fp=fn=exact=multi=multi_correct=adopted=effective=0;errors=[];evaluated=0;unmapped=0
    for pmid in matched:
        labels=set(gold[pmid]['annotations']['EGCG']['human_main']);decision=predictions[pmid]['final_decision']
        adopted+=decision['status']=='adopted';effective+=decision['human_trial']=='effective'
        if labels-set(NAMES):unmapped+=1;continue
        predicted=set(decision['categories']);tp+=len(labels&predicted);fp+=len(predicted-labels);fn+=len(labels-predicted);exact+=labels==predicted;evaluated+=1
        if len(labels)>1:multi+=1;multi_correct+=labels==predicted
        if labels!=predicted or decision['status']!='adopted' or decision['human_trial']!='effective':errors.append({'pmid':pmid,'human_categories':sorted(labels),'predicted':decision})
    ratio=lambda a,b:a/b if b else None
    return {'split':'heldout PMID only','gold_holdout':len(gold),'predictions_available':len(matched),'missing_predictions':sorted(gold.keys()-predictions.keys()),'unmapped_gold_skipped_for_taxonomy':unmapped,
        'adoption_positive_agreement':ratio(adopted,len(matched)),'adoption_overall_accuracy':None,'adoption_accuracy_unavailable_reason':'人間版に未掲載の論文は除外負例と断定できません。',
        'category_precision_micro':ratio(tp,tp+fp),'category_recall_micro':ratio(tp,tp+fn),'category_exact_match':ratio(exact,evaluated),'multilabel_exact_match':ratio(multi_correct,multi),
        'human_effect_positive_agreement':ratio(effective,len(matched)),'human_effect_overall_accuracy':None,'errors':errors,'paid_requests':0}

if __name__=='__main__':
    p=argparse.ArgumentParser();p.add_argument('--db',required=True);p.add_argument('--job',required=True);p.add_argument('--bundle',default='app/static/resources/provided.json.gz');p.add_argument('--output',required=True);a=p.parse_args()
    Path(a.output).write_text(json.dumps(evaluate(a.bundle,a.db,a.job),ensure_ascii=False,indent=2))
