#!/usr/bin/env python3
"""Build a read-only local preview from the PHP fixture's real public helpers.

Usage: python tools/build-reader-preview.py /tmp/reader-php.txt --output /tmp/hsh-reader-preview
Serve the output with python -m http.server --directory /tmp/hsh-reader-preview.
Forms and fetch are disabled by CSP; no WordPress writes are possible in the preview.
"""
import argparse
import json
from pathlib import Path
import re
import shutil

ROOT = Path(__file__).resolve().parents[1]


def build(source, output):
    text = Path(source).read_text()
    pages = json.loads(text.split('<!--HSH_READER_PREVIEW_START-->')[1].split('<!--HSH_READER_PREVIEW_END-->')[0])
    output = Path(output)
    if output.exists():
        raise ValueError('Use a new directory; the preview builder never overwrites existing files.')
    output.mkdir(parents=True)
    shutil.copytree(ROOT / 'hirogaru-home/assets', output / 'assets', ignore=shutil.ignore_patterns('*.png', 'vendor'))
    for file in ('brand-32.png', 'brand-180.png'):
        shutil.copy2(ROOT / 'hirogaru-home/assets' / file, output / 'assets' / file)
    styles = ['home.css', 'reading.css', 'blog-next.css', 'growth.css', 'reader.css', 'palette.css', 'brushup.css', 'studio.css', 'quality.css']
    head = '<meta http-equiv="Content-Security-Policy" content="default-src \'self\'; img-src \'self\' data:; style-src \'self\' \'unsafe-inline\'; script-src \'self\' \'unsafe-inline\'; connect-src \'none\'; form-action \'none\'; base-uri \'none\'">'
    head += ''.join('<link rel="stylesheet" href="assets/' + f + '">' for f in styles)
    head += '<style>body{margin:0;background:#fffaf3;color:#49382e;font-family:Meiryo,sans-serif}.hsh-preview-main{max-width:740px;padding:24px;margin:auto}.hsh-preview-note{padding:10px 24px;background:#eee2cf;font-size:13px}.hsh-anchor{display:block;scroll-margin-top:24px}</style>'
    for key, page in pages.items():
        html = page['html']
        data_match = re.search(r'(<script id="hsh-reader-data" type="application/json">)(.*?)(</script>)', html, re.S)
        data = json.loads(data_match[2])
        for article in data['articles']:
            original = article['url']
            article['url'] = article['id'] + '.html'
            html = html.replace(original, article['url'])
        data['savedUrl'] = 'saved.html'
        html = re.sub(r'(<script id="hsh-reader-data" type="application/json">).*?(</script>)', lambda m: m[1] + json.dumps(data, ensure_ascii=False).replace('<', '\\u003c') + m[2], html, flags=re.S)
        for base in ('https://example.test/plugins/hirogaru-home/assets/', 'https://hirogarushumi.com/wp-content/plugins/hirogaru-home/assets/'):
            html = html.replace(base, 'assets/')
        html = html.replace('https://example.test/hsh-reading-list/', 'saved.html').replace('https://example.test/', 'index.html')
        # Source article URLs become local only when that page was generated.
        for slug in pages:
            html = html.replace('https://hirogarushumi.com/' + slug + '/', slug + '.html')
        html = html.replace('</head>', head + '</head>')
        html = html.replace('<body class="hsh-reader-site">', '<body class="hsh-reader-site"><div class="hsh-preview-note">公開前のローカル確認画面です。送信・通信は無効です。</div>')
        html = html.replace('</body>', '<script>window.hshBlogTools={tokenUrl:"preview-disabled",reportUrl:"preview-disabled"}</script><script src="assets/blog-next.js"></script><script src="assets/reader.js"></script><script src="assets/brushup.js"></script><script src="assets/studio.js"></script><script src="assets/quality.js"></script></body>')
        if key == 'index':
            html = html.replace('<h1>まんもすとの暮らし、ウイスキー、仕事の話</h1>', '<h2>まんもすとの暮らし、ウイスキー、仕事の話</h2>')
        (output / (key + '.html')).write_text(html)
    (output / 'README.txt').write_text('Local read-only preview. Serve this directory using python -m http.server. Form submission and fetch are blocked by Content-Security-Policy.\n')
    return {'pages': len(pages), 'output': str(output), 'read_only': True}


if __name__ == '__main__':
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('source', type=Path)
    p.add_argument('--output', type=Path, required=True)
    a = p.parse_args()
    print(json.dumps(build(a.source, a.output)))
