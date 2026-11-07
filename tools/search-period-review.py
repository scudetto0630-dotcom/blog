#!/usr/bin/env python3
"""Read a Search Console ZIP privately, without extracting or publishing its CSVs."""
import argparse,csv,datetime,io,json,zipfile
from pathlib import Path
def analyze(file,days=28):
    if not 7<=days<=90:raise ValueError('Period must be 7–90 days')
    rows=[]
    with zipfile.ZipFile(file) as z:
        if sum(i.file_size for i in z.infolist())>20_000_000:raise ValueError('ZIP too large')
        for name in z.namelist():
            if not name.endswith('.csv'):continue
            text=z.read(name).decode('utf-8-sig');r=list(csv.DictReader(io.StringIO(text)))
            if r and ('日付' in r[0] or 'Date' in r[0]):rows=r;break
    if not rows:raise ValueError('No daily performance CSV found')
    def val(r,j,e):return r.get(j,r.get(e,''))
    daily=[]
    for r in rows:
        date=datetime.date.fromisoformat(val(r,'日付','Date'));clicks=int(val(r,'クリック数','Clicks').replace(',',''));impressions=int(val(r,'表示回数','Impressions').replace(',',''));daily.append({'date':date,'clicks':clicks,'impressions':impressions})
    daily.sort(key=lambda r:r['date']);end=daily[-1]['date'];start=end-datetime.timedelta(days=days-1);prev_start=start-datetime.timedelta(days=days)
    def period(a,b):
        p=[r for r in daily if a<=r['date']<=b];complete=len({r['date'] for r in p})==(b-a).days+1
        return {'start':a.isoformat(),'end':b.isoformat(),'complete':complete,'available_days':len(p),'clicks':sum(r['clicks'] for r in p) if complete else None,'impressions':sum(r['impressions'] for r in p) if complete else None}
    current=period(start,end);previous=period(prev_start,start-datetime.timedelta(days=1));complete=current['complete'] and previous['complete'];return {'current':current,'previous':previous,'comparable':complete,'ctr_test_ready':False,'decision':'Review query/page/device/country before CTR test' if complete else 'Wait for equal complete periods; do not rewrite from missing data','limits':['This aggregate export does not prove the ranking of a target keyword.','Page totals cannot be summed to replace property totals.','CTR experiment eligibility requires page/query scope and sufficient comparable impressions, not this total alone.']}
if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('zip',type=Path);p.add_argument('--days',type=int,default=28);p.add_argument('--output',type=Path,required=True);a=p.parse_args();result=analyze(a.zip,a.days);a.output.parent.mkdir(parents=True,exist_ok=True);a.output.write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n');a.output.chmod(0o600);print('Private comparison saved; comparable='+str(result['comparable']))
