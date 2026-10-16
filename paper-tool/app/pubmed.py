"""Free NCBI retrieval. No AI call or inferred scientific decision is made."""
import json
import time
from datetime import date, timedelta
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode
from urllib.request import Request, urlopen
from xml.etree import ElementTree as ET
from .ingredients import search_term

BASE = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/'

class PubMed:
    def __init__(self, transport=None, wait=time.sleep):
        self.transport = transport or self._network
        self.wait = wait

    def _network(self, endpoint, params):
        body = urlencode(params).encode()
        for attempt in range(5):
            self.wait(0.35)  # No key: at most three requests per second.
            try:
                with urlopen(Request(BASE + endpoint, data=body, headers={'User-Agent':'PaperDesk/1.0'}), timeout=45) as r:
                    return r.read()
            except HTTPError as e:
                if e.code not in (429, 500, 502, 503, 504) or attempt == 4:
                    raise
                retry=e.headers.get('Retry-After','0')
                self.wait(min(60, max(2**attempt, int(retry) if retry.isdigit() else 0)))
            except (URLError, TimeoutError):
                if attempt == 4: raise
                self.wait(2**attempt)

    def search(self, ingredient, start, end, count_only=False):
        # Quote user input as a phrase, preventing appended query operators.
        ingredient=ingredient.replace('"',' ').strip()
        if not ingredient or len(ingredient)>120: raise ValueError('成分名が正しくありません。')
        term=search_term(ingredient)
        payload=self.transport('esearch.fcgi', {'db':'pubmed','term':term,
            'mindate':start.isoformat(),'maxdate':end.isoformat(),'datetype':'pdat',
            'retmode':'json','retmax':0 if count_only else 9999,'sort':'pub date','tool':'PaperDesk'})
        result=json.loads(payload)['esearchresult']
        if result.get('ERROR') or result.get('errorlist'): raise ValueError('PubMed検索を確認してください。')
        return int(result['count']),result.get('idlist',[])

    def ids(self, ingredient, start=date(2000,1,1), end=None):
        """Partition publication dates to avoid PubMed's 10,000 search limit."""
        end=end or date.today()
        count,_=self.search(ingredient,start,end,True)
        if count>=10000:
            if start==end:
                raise ValueError('同日だけで10,000件以上あるため、安全に全件取得できません。切り捨てず停止します。')
            mid=start+timedelta(days=(end-start).days//2)
            yield from self.ids(ingredient,start,mid)
            yield from self.ids(ingredient,mid+timedelta(days=1),end)
        elif count:
            count,values=self.search(ingredient,start,end)
            if len(values)!=count: raise ValueError('PubMedの検索件数と取得ID数が一致しません。再試行してください。')
            yield from values

    def fetch(self, ids):
        if not ids: return []
        root=ET.fromstring(self.transport('efetch.fcgi',{'db':'pubmed','id':','.join(ids),'retmode':'xml','tool':'PaperDesk'}))
        papers=[]
        for item in root.findall('PubmedArticle'):
            citation=item.find('MedlineCitation');article=citation.find('Article')
            published=article.find('Journal/JournalIssue/PubDate')
            def txt(node): return ''.join(node.itertext()) if node is not None else ''
            pmid=txt(citation.find('PMID'))
            papers.append({'pmid':pmid,'title_en':txt(article.find('ArticleTitle')),
                'abstract':'\n'.join(txt(n) for n in article.findall('Abstract/AbstractText')),
                'publication_date': {n.tag:txt(n) for n in published} if published is not None else {},
                'publication_types':[txt(n) for n in article.findall('PublicationTypeList/PublicationType')],
                'pubmed_url':f'https://pubmed.ncbi.nlm.nih.gov/{pmid}/',
                'title_ja':None,'figure_urls':[], 'status':'review',
                'initial_decision':None,'checks':[], 'reason':'採否・分類・ヒト試験効果は未判定'})
        if {p['pmid'] for p in papers}!=set(ids): raise ValueError('PubMed本文に欠落があります。未取得として再試行します。')
        return papers
