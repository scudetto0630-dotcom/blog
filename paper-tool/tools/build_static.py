#!/usr/bin/env python3
"""Build a free static demo. No scientific engine or server credentials are included."""
import json
import shutil
import sys
from pathlib import Path
root=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(root))
from app.models import STAGES
from app.taxonomy import TAXONOMY, VERSION
from app.demo import make_result
output=root/'build'
output.mkdir(exist_ok=True)
for source in (root/'app/static').iterdir():
    if source.is_file():shutil.copy2(source,output/source.name)
    elif source.is_dir():shutil.copytree(source,output/source.name,dirs_exist_ok=True)
p=output/'index.html'
s=p.read_text().replace('<html lang="ja">','<html lang="ja" data-storage="browser">').replace('<script src="/app.js" defer>', '<script src="/browser-store.js" defer></script><script src="/app.js" defer>')
p.write_text(s)
(output/'config.json').write_text(json.dumps({'mode':'demo','stages':STAGES,'taxonomy':TAXONOMY,'taxonomy_version':VERSION,'taxonomy_provisional':False,'demo_template':make_result({'name':'','ingredients':['デモ']})},ensure_ascii=False))
print('Free static demo built:',output)
