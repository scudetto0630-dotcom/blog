#!/usr/bin/env python3
"""Read-only validation of public blog navigation, structured data and utility pages."""
import concurrent.futures
from html.parser import HTMLParser
import json
from pathlib import Path
from urllib.parse import urlsplit, urljoin, unquote, quote
from urllib.request import Request, urlopen

BASE = 'https://hirogarushumi.com'
ROOT = Path(__file__).resolve().parents[1]


class Page(HTMLParser):
    def __init__(self):
        super().__init__()
        self.meta, self.canonical, self.ids, self.links, self.photos = {}, [], set(), [], []
        self.json_text, self.json_nodes, self.json_errors = None, [], []
        self.in_h1, self.h1, self.caption, self.captions = False, '', None, []
        self.breadcrumb_depth, self.breadcrumb_positions, self.breadcrumb_links = 0, [], []

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == 'div' and (self.breadcrumb_depth or a.get('itemtype', '').endswith('/BreadcrumbList')):
            self.breadcrumb_depth += 1
        if self.breadcrumb_depth:
            if tag == 'meta' and a.get('itemprop') == 'position':
                self.breadcrumb_positions.append(a.get('content'))
            if tag == 'a' and a.get('itemprop') == 'item':
                self.breadcrumb_links.append(a.get('href', ''))
        if a.get('id'):
            self.ids.add(a['id'])
        if tag == 'meta':
            self.meta.setdefault(a.get('property', a.get('name', '')), []).append(a.get('content', ''))
        if tag == 'link' and a.get('rel') == 'canonical':
            self.canonical.append(a.get('href', ''))
        if tag == 'a' and a.get('href'):
            self.links.append(a['href'])
        if tag == 'img' and '/assets/photos/' in a.get('src', ''):
            self.photos.append(a)
        if tag == 'script' and a.get('type') == 'application/ld+json':
            self.json_text = ''
        if tag == 'h1':
            self.in_h1 = True
        if tag == 'figcaption':
            self.caption = ''

    def handle_data(self, data):
        if self.json_text is not None:
            self.json_text += data
        if self.in_h1:
            self.h1 += data
        if self.caption is not None:
            self.caption += data

    def handle_endtag(self, tag):
        if tag == 'div' and self.breadcrumb_depth:
            self.breadcrumb_depth -= 1
        if tag == 'h1':
            self.in_h1 = False
        if tag == 'figcaption' and self.caption is not None:
            self.captions.append(self.caption)
            self.caption = None
        if tag == 'script' and self.json_text is not None:
            try:
                self.json_nodes.append(json.loads(self.json_text))
            except json.JSONDecodeError:
                self.json_errors.append('invalid JSON-LD')
            self.json_text = None


def nodes(value):
    if isinstance(value, dict):
        yield value
        for v in value.values():
            yield from nodes(v)
    elif isinstance(value, list):
        for v in value:
            yield from nodes(v)


def fetch(path):
    request = Request(BASE + quote(path, safe='/?=&%'), headers={'User-Agent': 'HirogaruReaderAudit/1.0 bot'})
    with urlopen(request, timeout=60) as response:
        p = Page()
        raw = response.read().decode('utf-8')
        p.feed(raw)
        p.php_diagnostics = any(term in raw for term in ('Undefined array key', 'Trying to access array offset', 'Fatal error:', 'Uncaught Error:'))
        return path, p, response.status


def audit():
    catalog = json.loads((ROOT / 'hirogaru-home/article-map.json').read_text()) + json.loads((ROOT / 'hirogaru-home/expanded-articles.json').read_text()) + json.loads((ROOT / 'hirogaru-home/affiliate-articles.json').read_text())
    paths = ['/' + r['slug'] + '/' for r in catalog]
    paths += ['/', '/category/cats/', '/category/whisky/', '/category/it-freelance/', '/hsh-about/', '/hsh-editorial/', '/hsh-gallery/', '/hsh-whisky-notes/', '/hsh-updates/', '/hsh-hakushu-visit/', '/hsh-reading-list/', '/ひろがる趣味暮らしについて/', '/お問い合わせ/', '/プライバシーポリシー/']
    problems, pages, rows = [], {}, []
    with concurrent.futures.ThreadPoolExecutor(max_workers=3) as pool:
        for path, p, status in pool.map(fetch, paths):
            pages[path] = p
            if status != 200 or len(p.canonical) != 1 or unquote(p.canonical[0]) != BASE + path:
                problems.append({'path': path, 'problem': 'HTTP or canonical mismatch'})
            if getattr(p, 'php_diagnostics', False):
                problems.append({'path': path, 'problem': 'visible PHP diagnostic'})
            if p.json_errors:
                problems.append({'path': path, 'problem': 'JSON-LD syntax'})
            if any(not a.get('alt') or not a.get('width') or not a.get('height') for a in p.photos):
                problems.append({'path': path, 'problem': 'photo description or dimensions missing'})
            if any('提供写真' in c or 'AI生成' in c or 'AIによる生成' in c for c in p.captions):
                problems.append({'path': path, 'problem': 'redundant photo label'})
            article_nodes = [n for root in p.json_nodes for n in nodes(root) if any(t in ('Article', 'BlogPosting', 'NewsArticle') for t in (n.get('@type') if isinstance(n.get('@type'), list) else [n.get('@type')]))]
            if path.strip('/') in {a['slug'] for a in catalog}:
                if not p.breadcrumb_positions or p.breadcrumb_positions != [str(i + 1) for i in range(len(p.breadcrumb_positions))] or len(p.breadcrumb_links) != len(p.breadcrumb_positions) or any(urlsplit(u).netloc != urlsplit(BASE).netloc for u in p.breadcrumb_links):
                    problems.append({'path': path, 'problem': 'breadcrumb positions or links mismatch'})
                if not article_nodes:
                    problems.append({'path': path, 'problem': 'Article structured data missing'})
                for n in article_nodes:
                    if n.get('headline') != p.h1.strip():
                        problems.append({'path': path, 'problem': 'Article headline mismatch'})
                    image = n.get('image', {})
                    url = image.get('url') if isinstance(image, dict) else image
                    if url != (p.meta.get('og:image') or [None])[0]:
                        problems.append({'path': path, 'problem': 'Article image mismatch'})
                    if not n.get('author') or not n.get('datePublished'):
                        problems.append({'path': path, 'problem': 'Article author/date missing'})
            if path == '/hsh-reading-list/' and not any('noindex' in r for r in p.meta.get('robots', [])):
                problems.append({'path': path, 'problem': 'device-local reading list must be noindex'})
            rows.append({'path': path, 'status': status, 'article_schema_nodes': len(article_nodes), 'photos': len(p.photos), 'robots': p.meta.get('robots', [])})
    inbound = {path: set() for path in paths}
    checked = 0
    for source, p in pages.items():
        for href in p.links:
            u = urlsplit(urljoin(BASE + source, href))
            target = unquote(u.path)
            if u.netloc != urlsplit(BASE).netloc or target not in pages:
                continue
            if target != source:
                inbound[target].add(source)
            checked += 1
            if u.fragment and unquote(u.fragment) not in pages[target].ids:
                problems.append({'path': source, 'problem': 'missing target anchor', 'href': href})
    orphaned = [p for p in paths if p != '/' and not inbound[p]]
    for path in orphaned:
        problems.append({'path': path, 'problem': 'no incoming managed-page link'})
    return {'scope': 'own public pages, local JSON-LD field checks; not Google Rich Results or Search Console index validation', 'pages': rows, 'internal_links_checked': checked, 'orphaned': orphaned, 'problems': problems}


if __name__ == '__main__':
    result = audit()
    print(json.dumps(result, ensure_ascii=False, indent=2))
    raise SystemExit(bool(result['problems']))
