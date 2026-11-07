#!/usr/bin/env python3
"""Rebuild the bounded deployment ZIP while preserving its curated membership."""
from pathlib import Path
import zipfile
ROOT=Path(__file__).resolve().parents[1];target=ROOT/'hirogaru-home.zip'
with zipfile.ZipFile(target) as z:names=z.namelist()
for file in ['reader-quality.php','assets/quality.js','assets/quality.css']:
 name='hirogaru-home/'+file
 if name not in names:names.append(name)
if len(names)>400:raise SystemExit('Too many files for scoped updater')
for name in names:
 if not name.startswith('hirogaru-home/') or '..' in Path(name).parts:raise SystemExit('Invalid member')
 if not (ROOT/name).is_file():raise SystemExit('Missing member: '+name)
temp=target.with_suffix('.tmp.zip')
try:
 with zipfile.ZipFile(temp,'w',zipfile.ZIP_DEFLATED,compresslevel=9) as z:
  for name in names:z.write(ROOT/name,name)
 with zipfile.ZipFile(temp) as z:
  assert not z.testzip();assert sum(i.file_size for i in z.infolist())<=50*1024*1024
  for name in names:assert z.read(name)==(ROOT/name).read_bytes()
 assert temp.stat().st_size<=16*1024*1024
 temp.replace(target);print('Curated ZIP verified: '+str(len(names))+' files, '+str(target.stat().st_size)+' bytes')
finally:
 if temp.exists():temp.unlink()
