#!/usr/bin/env python3
"""Offline analysis of the user's supplied files; does not call or train an AI model."""
import argparse,json,sys
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from app.training.ingest import analyze
p=argparse.ArgumentParser(description=__doc__)
p.add_argument('--source',required=True);p.add_argument('--ai',required=True);p.add_argument('--human',required=True)
p.add_argument('--reference');p.add_argument('--output',default='data/teacher-analysis')
a=p.parse_args()
r=analyze(a.source,a.ai,a.human,a.output,a.reference)
print(json.dumps({k:r[k] for k in ['model_trained','paid_api_calls','source_unique_pmids','human_main_pmids','fold_counts','unmapped_categories']},ensure_ascii=False,indent=2))
