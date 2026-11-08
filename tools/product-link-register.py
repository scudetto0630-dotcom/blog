#!/usr/bin/env python3
"""Build an auditable placement register without requesting any affiliate URL."""
import argparse,csv,datetime,hashlib,json,re
from html import unescape
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def build(live=None,checked_at=None):
    manifest=json.loads((ROOT/'hirogaru-home/photo-assets.json').read_text())
    live={a['slug']:a for a in (live or {}).get('articles',[])}
    rows=[]
    for a in json.loads((ROOT/'hirogaru-home/affiliate-articles.json').read_text()):
        body=a['content'];record=live.get(a['slug'],{})
        anchors=re.findall(r'<a\b[^>]*href="([^"]+)"[^>]*>',body)
        links=[unescape(u) for u in anchors if 'px.a8.net/' in u]
        pixels=re.findall(r'<img\b[^>]*src="([^"]+)"[^>]*>',body)
        rows.append({'slug':a['slug'],'title':a['title'],'page_url':'https://hirogarushumi.com/'+a['slug']+'/',
          'placement':'記事末尾の商品案内（.hsh-affiliate-cta）','link_type':'商品リンク',
          'affiliate_links':links,'tracking_pixels':[unescape(u) for u in pixels if 'a8.net/' in u],
          'official_source':a['source_url'],'product_images':manifest.get('affiliate_product_images',{}).get(a['slug'],[]),
          'publication_status':record.get('status','not_verified'),'post_id':record.get('id'),
          'checked_at':checked_at or datetime.datetime.now(datetime.timezone.utc).isoformat(),
          'content_sha256':hashlib.sha256(body.encode()).hexdigest(),
          'limits':'掲載状態は認証APIの情報。商品在庫・価格の確認日や初回掲載日を意味しない。'})
    return rows
if __name__=='__main__':
    p=argparse.ArgumentParser();p.add_argument('--live-status',type=Path);p.add_argument('--output',type=Path,required=True);a=p.parse_args()
    rows=build(json.loads(a.live_status.read_text()) if a.live_status else None);a.output.mkdir(parents=True,exist_ok=True)
    (a.output/'product-links.json').write_text(json.dumps(rows,ensure_ascii=False,indent=2)+'\n')
    with (a.output/'product-links.csv').open('w',encoding='utf-8-sig',newline='') as f:
        fields=['title','page_url','placement','link_type','publication_status','checked_at','official_source','affiliate_links'];w=csv.DictWriter(f,fieldnames=fields);w.writeheader()
        for r in rows:w.writerow({k:'\n'.join(r[k]) if isinstance(r[k],list) else r[k] for k in fields})
    print(f'{len(rows)} placements recorded; no affiliate requests made')
