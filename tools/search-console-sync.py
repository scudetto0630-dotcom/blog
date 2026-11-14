#!/usr/bin/env python3
"""Private, read-only Search Console collection. Never submits index or site changes."""
import argparse,datetime,json,os,tempfile,hashlib,time,uuid,re
from pathlib import Path
from urllib.parse import quote
from urllib.request import Request,build_opener,HTTPRedirectHandler
from urllib.error import HTTPError
from zoneinfo import ZoneInfo
ROOT=Path(__file__).resolve().parents[1]
SITE='https://hirogarushumi.com/'
SCOPE='https://www.googleapis.com/auth/webmasters.readonly'
ENDPOINT='https://www.googleapis.com/webmasters/v3/sites/'+quote(SITE,safe='')+'/searchAnalytics/query'
DIMS=['page','query','device','country']
class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self,req,fp,code,msg,headers,newurl):
        raise HTTPError(req.full_url,code,'Redirect rejected',headers,fp)
def credential_info():
    value=os.environ.get('GSC_SERVICE_ACCOUNT_JSON')
    if not value:return None
    try:info=json.loads(value)
    except (ValueError,TypeError):raise ValueError('Invalid service account configuration')
    if not isinstance(info,dict) or info.get('type')!='service_account' or info.get('token_uri')!='https://oauth2.googleapis.com/token':
        raise ValueError('Invalid service account configuration')
    if not all(isinstance(info.get(k),str) and info[k] for k in ('client_email','private_key','project_id')):
        raise ValueError('Incomplete service account configuration')
    return info
def access_token(info):
    from google.oauth2 import service_account
    from google.auth.transport.requests import Request as AuthRequest
    creds=service_account.Credentials.from_service_account_info(info,scopes=[SCOPE])
    creds.refresh(AuthRequest())
    return creds.token
def query(token,body):
    req=Request(ENDPOINT,data=json.dumps(body).encode(),headers={'Authorization':'Bearer '+token,'Content-Type':'application/json'},method='POST')
    with build_opener(NoRedirect()).open(req,timeout=60) as response:return json.load(response)
def fetch_rows(token,start,end,dimensions,call=query):
    rows=[];truncated=False
    for offset in range(0,100000,25000):
        result=call(token,{'startDate':start,'endDate':end,'dimensions':dimensions,'type':'web','dataState':'final','rowLimit':25000,'startRow':offset})
        part=result.get('rows',[])
        if not isinstance(part,list):raise ValueError('Invalid API rows')
        rows.extend(part)
        if len(part)<25000:break
    else:truncated=True
    return rows,truncated
def metrics(row,dimensions):
    keys=row.get('keys',[])
    if len(keys)!=len(dimensions) or any(not isinstance(x,str) for x in keys):raise ValueError('Dimension mismatch')
    v={k:float(row[k]) for k in ('clicks','impressions','ctr','position')}
    import math
    if not all(math.isfinite(n) for n in v.values()) or not 0<=v['clicks']<=v['impressions'] or not 0<=v['ctr']<=1 or v['position']<0:
        raise ValueError('Invalid search metrics')
    return dict(zip(dimensions,keys))|v
def analyze(current,previous,daily,start,end,truncated=False):
    start=datetime.date.fromisoformat(start);end=datetime.date.fromisoformat(end)
    if (end-start).days!=27:raise ValueError('Use an exact 28-day period')
    prev_end=start-datetime.timedelta(days=1);prev_start=prev_end-datetime.timedelta(days=27)
    dates={r['date'] for r in daily}
    needed={(prev_start+datetime.timedelta(days=i)).isoformat() for i in range(56)}
    coverage=needed<=dates and not truncated
    old={tuple(r[k] for k in DIMS):r for r in previous};candidates=[];groups={}
    for row in current:
        key=tuple(row[k] for k in DIMS);before=old.get(key)
        if coverage and before and row['impressions']>=100 and before['impressions']>=100:
            candidates.append({'page':row['page'],'query':row['query'],'device':row['device'],'country':row['country'],
                               'current_ctr':row['ctr'],'previous_ctr':before['ctr'],'position_change':row['position']-before['position'],
                               'decision':'review_same_scope; no_automatic_title_change'})
        if row['impressions']>0:groups.setdefault((row['query'],row['device'],row['country']),set()).add(row['page'])
    overlaps=[{'query':k[0],'device':k[1],'country':k[2],'pages':sorted(v),'decision':'review_intent; not_proof_of_cannibalization'} for k,v in groups.items() if len(v)>1]
    return {'equal_periods_have_all_dates':coverage,'current':{'start':start.isoformat(),'end':end.isoformat()},
            'previous':{'start':prev_start.isoformat(),'end':prev_end.isoformat()},
            'missing_dates':sorted(needed-dates),'ctr_review_candidates':candidates,'multi_page_query_candidates':overlaps,
            'limits':['API returns top rows and may omit anonymized queries; results are not exhaustive.',
                      'Omitted dates are not filled with zero.','Page/query totals do not replace property totals.',
                      'A 100-impression threshold is a review gate, not statistical significance or proof an edit caused change.']}
def collect(token,call=query,today=None):
    today=today or datetime.datetime.now(ZoneInfo('America/Los_Angeles')).date()
    rows,cut=fetch_rows(token,(today-datetime.timedelta(days=90)).isoformat(),(today-datetime.timedelta(days=1)).isoformat(),['date'],call)
    daily=[metrics(r,['date']) for r in rows]
    if not daily:return {'site':SITE,'state':'no_finalized_data','analysis':None,'daily':[]}
    if len({r['date'] for r in daily})!=len(daily):raise ValueError('Duplicate daily rows')
    end=max(datetime.date.fromisoformat(r['date']) for r in daily);start=end-datetime.timedelta(days=27);prev_end=start-datetime.timedelta(days=1);prev_start=prev_end-datetime.timedelta(days=27)
    a,ca=fetch_rows(token,start.isoformat(),end.isoformat(),DIMS,call);b,cb=fetch_rows(token,prev_start.isoformat(),prev_end.isoformat(),DIMS,call)
    current=[metrics(r,DIMS) for r in a];previous=[metrics(r,DIMS) for r in b]
    for rows in (current,previous):
        if len({tuple(r[k] for k in DIMS) for r in rows})!=len(rows):raise ValueError('Duplicate scope rows')
    return {'site':SITE,'state':'collected','scope':SCOPE,'data_state':'final','dimensions':DIMS,'daily':daily,
            'current_rows':current,'previous_rows':previous,'analysis':analyze(current,previous,daily,start.isoformat(),end.isoformat(),cut or ca or cb)}
def save_private(path,data):
    path=path.resolve()
    if path.is_relative_to(ROOT) and path.relative_to(ROOT).parts[0] not in ('.private','work'):
        raise ValueError('Choose a private output directory')
    path.parent.mkdir(parents=True,exist_ok=True,mode=0o700)
    fd,tmp=tempfile.mkstemp(dir=path.parent,prefix='.gsc-')
    try:
        os.fchmod(fd,0o600)
        with os.fdopen(fd,'w') as f:json.dump(data,f,ensure_ascii=False,indent=2)
        os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
BLOG_REPORT_URL='https://hirogarushumi.com/?rest_route=/hirogaru/v1/search-report'
BLOG_AUDIENCE='https://hirogarushumi.com/hirogaru-search-console'
SERVICE_EMAIL='blog-search-reader@hirogaru-search.iam.gserviceaccount.com'
def signed_report_token(info,body,now=None,sign=None):
    if info.get('client_email')!=SERVICE_EMAIL or not isinstance(info.get('private_key_id'),str) or not info['private_key_id']:
        raise ValueError('Report sender does not match the configured reader')
    now=int(time.time()) if now is None else now
    claims={'iss':SERVICE_EMAIL,'aud':BLOG_AUDIENCE,'iat':now,'exp':now+300,'jti':uuid.uuid4().hex,'sha256':hashlib.sha256(body).hexdigest()}
    if sign is not None:return sign(info,claims)
    from google.auth import crypt,jwt
    signer=crypt.RSASigner.from_service_account_info(info)
    token=jwt.encode(signer,claims,key_id=info['private_key_id'])
    return token.decode() if isinstance(token,bytes) else token
def publish_private_report(info,data,send=None,sign=None):
    body=json.dumps(data,ensure_ascii=False,separators=(',',':'),allow_nan=False).encode('utf-8')
    if len(body)>4*1024*1024:raise ValueError('Report is too large for private delivery')
    token=signed_report_token(info,body,sign=sign)
    request=Request(BLOG_REPORT_URL,data=body,method='POST',headers={'Authorization':'Bearer '+token,'Content-Type':'application/json','Cache-Control':'no-store'})
    if send is None:
        with build_opener(NoRedirect()).open(request,timeout=60) as response:result=json.load(response)
    else:result=send(request)
    if not isinstance(result,dict) or result.get('stored') is not True:raise ValueError('Private report was not accepted')
    return True
def main():
    p=argparse.ArgumentParser();p.add_argument('--status',action='store_true');p.add_argument('--publish-private',action='store_true');p.add_argument('--output',type=Path,default=ROOT/'.private/search-console/latest.json');a=p.parse_args()
    try:
        info=credential_info()
        if a.status:
            print(json.dumps({'configured':info is not None,'connection_verified':False,'network_attempted':False}));return 0
        if info is None:print('Search Console credentials are not configured. No API request was sent.');return 2
        data=collect(access_token(info));data['collected_at']=int(time.time());save_private(a.output,data)
        if a.publish_private:publish_private_report(info,data);print('Private report delivered to the blog. No queries or credentials printed.')
        else:print('Private Search Console report saved. No queries or credentials printed.')
        return 0
    except HTTPError as e:
        detail=''
        try:
            code=json.load(e).get('code','')
            if isinstance(code,str) and re.fullmatch(r'hsh_gsc_[a-z_]+',code):detail=' ('+code+')'
        except Exception:pass
        print('Search Console or authorization returned HTTP '+str(e.code)+detail+'. No automatic retry.');return 1
    except ImportError:print('Install tools/requirements-search.txt in a private virtual environment.');return 1
    except Exception:print('Search Console setup, response or private output could not be validated. No credentials printed.');return 1
if __name__=='__main__':raise SystemExit(main())
