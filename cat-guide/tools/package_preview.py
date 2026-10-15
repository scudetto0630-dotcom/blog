from pathlib import Path
import shutil,json
from build import build,ROOT
build(ROOT/'build')
target=ROOT.parent/'hirogaru-home/cat-guide-preview'
if target.exists():shutil.rmtree(target)
shutil.copytree(ROOT/'build',target)
files=sorted(str(p.relative_to(target)) for p in target.rglob('*') if p.is_file())
(target/'manifest.json').write_text(json.dumps(files,ensure_ascii=False,indent=2)+'\n')
shutil.copyfile(ROOT/'tools/wordpress-router.php',target.parent/'cat-guide-preview.php')
print('Packaged',len(files),'public files')
