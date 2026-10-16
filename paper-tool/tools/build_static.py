#!/usr/bin/env python3
"""Build a free static demo. No scientific engine or server credentials are included."""
import argparse
import json
import os
import shutil
import sys
from pathlib import Path
root=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(root))
from app.models import STAGES
from app.taxonomy import TAXONOMY, VERSION
from app.demo import make_result
parser=argparse.ArgumentParser();parser.add_argument('--demo',action='store_true',help='Build legacy demo for compatibility testing');args=parser.parse_args()
output=root/'build'
if output.exists():shutil.rmtree(output)
output.mkdir(exist_ok=True)
for source in (root/'app/static').iterdir():
    if source.is_file():shutil.copy2(source,output/source.name)
    elif source.is_dir():shutil.copytree(source,output/source.name,dirs_exist_ok=True)
p=output/'index.html'
s=p.read_text().replace('<html lang="ja">','<html lang="ja" data-storage="browser">').replace('<script src="/app.js" defer>', '<script src="/browser-store.js" defer></script><script src="/app.js" defer>')
p.write_text(s)
live_url=os.environ.get('PAPERDESK_LIVE_SITE_URL','').strip()
if live_url:
    from urllib.parse import urlsplit
    target=urlsplit(live_url)
    if target.scheme!='https' or not target.hostname or target.username or target.password or target.query or target.fragment or target.path not in ('','/'):
        raise SystemExit('PAPERDESK_LIVE_SITE_URL must be an HTTPS site root without credentials, query or fragment.')
    # No API key is embedded. Local products/history remain at the old origin;
    # ?provided=1 still opens the original provided-data application.
    (output/'live-entry.js').write_text('if(new URLSearchParams(location.search).get("provided")!=="1"){location.replace('+json.dumps(live_url.rstrip('/')+'/',ensure_ascii=False)+'+location.hash);}\n')
    p.write_text(s.replace('<script src="/research.js" defer>', '<script src="/live-entry.js"></script><script src="/research.js" defer>'))
(output/'config.json').write_text(json.dumps({'mode':'demo' if args.demo else 'provided','provided_data_enabled':not args.demo,'stages':STAGES,'taxonomy':TAXONOMY,'taxonomy_version':VERSION,'taxonomy_provisional':False,'demo_template':make_result({'name':'','ingredients':['デモ']})},ensure_ascii=False))
print('Free static site built:',output)
