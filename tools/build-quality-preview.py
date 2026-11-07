#!/usr/bin/env python3
"""Select the final studio fixture, never the earlier shared reader fixture."""
import argparse,importlib.util,tempfile
from pathlib import Path
p=argparse.ArgumentParser();p.add_argument('source',type=Path);p.add_argument('--output',type=Path,required=True);a=p.parse_args();text=a.source.read_text()
if 'Fatal error:' in text or 'FAIL:' in text or 'All quality checks passed.' not in text:raise SystemExit('PHP checks did not complete successfully')
source=text.split('<!--HSH_STUDIO_PREVIEW_START-->')[1].split('<!--HSH_STUDIO_PREVIEW_END-->')[0]
s=importlib.util.spec_from_file_location('reader_preview',Path(__file__).with_name('build-reader-preview.py'));m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
with tempfile.NamedTemporaryFile('w+',suffix='.txt') as f:
 f.write('<!--HSH_READER_PREVIEW_START-->'+source+'<!--HSH_READER_PREVIEW_END-->');f.flush();print(m.build(f.name,a.output))
