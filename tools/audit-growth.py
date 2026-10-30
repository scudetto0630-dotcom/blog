#!/usr/bin/env python3
"""Read-only audit of this site's published managed pages and internal article links."""
import concurrent.futures
from html.parser import HTMLParser
import json
from pathlib import Path
import time
from urllib.parse import urlsplit, urljoin, unquote
from urllib.request import urlopen, Request

BASE = 'https://hirogarushumi.com'
ROOT = Path(__file__).resolve().parents[1]


class Page(HTMLParser):
    def __init__(self):
        super().__init__()
        self.meta, self.ids, self.links, self.canonical, self.images = {}, set(), [], [], []

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if a.get('id'):
            self.ids.add(a['id'])
        if tag == 'meta':
            self.meta.setdefault(a.get('property', a.get('name', '')), []).append(a.get('content', ''))
        if tag == 'link' and a.get('rel') == 'canonical':
            self.canonical.append(a.get('href'))
        if tag == 'a' and a.get('href'):
            self.links.append(a['href'])
        if tag == 'img':
            self.images.append(a)


def fetch(path):
    url = BASE + path
    request = Request(url + ('&' if '?' in url else '?') + '_hsh_audit=' + str(time.time_ns()),
                      headers={'User-Agent': 'HSH-owned-site-audit-bot/1.0'})
    with urlopen(request, timeout=60) as response:
        html = response.read().decode('utf-8')
        page = Page()
        page.feed(html)
        return path, page, response.status


def main():
    catalog = json.loads((ROOT / 'hirogaru-home/article-map.json').read_text()) + json.loads((ROOT / 'hirogaru-home/expanded-articles.json').read_text())
    paths = ['/' + r['slug'] + '/' for r in catalog] + ['/', '/hsh-about/', '/hsh-editorial/', '/hsh-gallery/', '/hsh-whisky-notes/', '/hsh-updates/']
    pages, rows, problems = {}, [], []
    with concurrent.futures.ThreadPoolExecutor(max_workers=3) as pool:
        for path, page, status in pool.map(fetch, paths):
            pages[path] = page
            row = {'path': path, 'http': status, 'canonical': page.canonical, 'description': page.meta.get('description', []), 'og_image': page.meta.get('og:image', [])}
            rows.append(row)
            if status != 200 or page.canonical != [BASE + path]:
                problems.append({'path': path, 'problem': 'http_or_canonical'})
            if path in ['/' + r['slug'] + '/' for r in catalog]:
                asset = json.loads((ROOT / 'hirogaru-home/photo-assets.json').read_text())
                image = asset['assets'][asset['articles'][path.strip('/')]]['file']
                expected = BASE + '/wp-content/plugins/hirogaru-home/assets/photos/' + image
                if page.meta.get('og:image') != [expected] or page.meta.get('twitter:image') != [expected]:
                    problems.append({'path': path, 'problem': 'social_image_mismatch'})
                if len(page.meta.get('description', [])) != 1:
                    problems.append({'path': path, 'problem': 'description_count'})
    checked = 0
    for path, page in pages.items():
        for href in page.links:
            parts = urlsplit(urljoin(BASE + path, href))
            target = unquote(parts.path)
            if parts.netloc != urlsplit(BASE).netloc or parts.query or target not in pages:
                continue
            checked += 1
            if parts.fragment and unquote(parts.fragment) not in pages[target].ids:
                problems.append({'path': path, 'problem': 'missing_anchor', 'href': href})
    technical = {}
    for path in ['/robots.txt', '/wp-sitemap.xml', '/wp-sitemap-posts-post-1.xml', '/wp-sitemap-posts-page-1.xml']:
        try:
            with urlopen(Request(BASE + path, headers={'User-Agent': 'HSH-owned-site-audit-bot/1.0'}), timeout=60) as r:
                technical[path] = {'http': r.status, 'bytes': len(r.read())}
        except Exception as e:
            technical[path] = {'error': type(e).__name__}
    result = {'pages': rows, 'managed_internal_links_checked': checked, 'problems': problems,
              'technical_endpoints': technical, 'google_index_status': 'not_available',
              'ranking_data': 'not_available_without_search_console_export'}
    print(json.dumps(result, ensure_ascii=False, indent=2))
    return bool(problems)


if __name__ == '__main__':
    raise SystemExit(main())
