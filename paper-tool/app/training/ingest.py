import hashlib
import io
import json
import re
import sqlite3
import zipfile
from pathlib import Path
from ..taxonomy import NAMES

PMID = re.compile(r'https?://pubmed\.ncbi\.nlm\.nih\.gov/(\d+)(?:/|\b)')
DATE = re.compile(r'^\s*(\d{4})/(\d{1,2})\s*$')
CATEGORY = re.compile(r'論文一覧_(.+?)(?:_ヒト試験効果あり_(\d+)件)?(?:\(\d+\))?\.txt$')


def records(text):
    """Read literal source fields; never infer a scientific conclusion from the title."""
    hits = list(PMID.finditer(text))
    output = []
    for i, hit in enumerate(hits):
        before = text[max(0, hit.start()-4096):hit.start()].splitlines()
        previous = next((line for line in reversed(before) if line.strip()), '')
        date = DATE.match(previous)
        end = hits[i+1].start() if i+1 < len(hits) else len(text)
        lines = [line.strip() for line in text[hit.end():end].splitlines() if line.strip()]
        lines = [line for line in lines if not DATE.match(line) and line != '/']
        figures = [line for line in lines if line.startswith(('http://','https://')) and '/figure/' in line]
        titles = [line for line in lines if not line.startswith(('http://','https://')) and not line.startswith(('部位:', 'ヒト試験で'))]
        output.append({'pmid':hit.group(1), 'date':f'{date[1]}/{int(date[2]):02d}' if date else None,
                       'pubmed_url':f'https://pubmed.ncbi.nlm.nih.gov/{hit.group(1)}/',
                       'figure_urls':figures,'title_ja':titles[-2] if len(titles)>=2 else None,
                       'title_en':titles[-1] if titles else None})
    return output


def decode_name(name):
    try: return name.encode('cp437').decode('utf-8')
    except UnicodeError: return name


def read_archive(path, max_bytes=300*1024*1024, max_files=3000):
    """Inspect nested archives in memory; do not extract or execute uploaded files."""
    used_bytes = 0
    used_files = 0
    def walk(archive, prefix='', depth=0):
        nonlocal used_bytes, used_files
        if depth>4: raise ValueError('ZIPの入れ子が深すぎます。')
        for entry in archive.infolist():
            if entry.is_dir(): continue
            name=decode_name(entry.filename).replace('\\','/')
            if name.startswith('__MACOSX/') or Path(name).name.startswith('.'): continue
            if name.startswith('/') or '..' in name.split('/'): raise ValueError('安全でないZIP内のパスです。')
            used_bytes+=entry.file_size;used_files+=1
            if used_bytes>max_bytes or used_files>max_files: raise ValueError('ZIPの検査上限を超えました。')
            data=archive.read(entry)
            full=prefix+name
            if name.endswith('.zip'):
                yield from walk(zipfile.ZipFile(io.BytesIO(data)),full+'/',depth+1)
            elif name.endswith('.txt'):
                yield full,data.decode('utf-8-sig'),hashlib.sha256(data).hexdigest()
    with zipfile.ZipFile(path) as archive:
        yield from walk(archive)


def analyze(source, ai_zip, human_zip, output_dir, reference_zip=None):
    """Build provenance and a PMID-grouped split. Unknown labels remain unknown."""
    output_dir=Path(output_dir);output_dir.mkdir(parents=True,exist_ok=True)
    # Re-running writes a new transaction into only this analysis database.
    database=sqlite3.connect(output_dir/'teacher.sqlite3')
    database.executescript('''
    CREATE TABLE IF NOT EXISTS papers (pmid TEXT PRIMARY KEY, fields TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS occurrences (origin TEXT, ingredient TEXT, path TEXT, pmid TEXT,
        raw_category TEXT, canonical_id TEXT, disposition TEXT, content_hash TEXT);
    CREATE TABLE IF NOT EXISTS splits (pmid TEXT PRIMARY KEY, fold TEXT NOT NULL);
    ''')
    raw=records(Path(source).read_text(encoding='utf-8-sig'))
    known={r['pmid'] for r in raw}
    ai_all={};ai_human={};human_main={};human_unused={};reference={}
    warnings=[];files=[];hashes={}
    category_ids={n:f'part-{i:02d}' for i,n in enumerate(NAMES,1)}
    try:
        database.execute('DELETE FROM occurrences');database.execute('DELETE FROM papers');database.execute('DELETE FROM splits')
        for r in raw:database.execute('INSERT OR IGNORE INTO papers VALUES(?,?)',(r['pmid'],json.dumps(r,ensure_ascii=False)))
        def consume(path, kind):
            for name,text,digest in read_archive(path):
                items=records(text);match=CATEGORY.search(Path(name).name)
                category=match[1] if match else None
                if kind=='ai':
                    origin='ai_human' if '_ヒト試験効果あり_' in name else 'ai_all'
                    target=ai_human if origin=='ai_human' else ai_all
                    disposition='ai_proposal'
                elif kind=='human':
                    origin='human_unused' if '使用しない/' in name else 'human_main'
                    target=human_unused if origin=='human_unused' else human_main
                    disposition='needs_folder_semantics' if origin=='human_unused' else 'human_positive_example'
                else:
                    origin='product_reference';target=reference;disposition='unverified_reference'
                ingredient='カテキン' if '/カテキン/' in name else 'EGCG'
                files.append({'origin':origin,'path':name,'sha256':digest,'records':len(items),'unique_pmids':len({r['pmid'] for r in items})})
                if match and match[2] is not None and int(match[2])!=len(items):
                    warnings.append({'type':'filename_count_mismatch','path':name,'declared':int(match[2]),'actual':len(items)})
                if category and category not in category_ids and items:
                    warnings.append({'type':'unmapped_category','path':name,'category':category})
                hashes.setdefault(digest,[]).append({'origin':origin,'path':name})
                for r in items:
                    database.execute('INSERT OR IGNORE INTO papers VALUES(?,?)',(r['pmid'],json.dumps(r,ensure_ascii=False)))
                    if match: target.setdefault(r['pmid'],set()).add(category)
                    database.execute('INSERT INTO occurrences VALUES(?,?,?,?,?,?,?,?)',(origin,ingredient,name,r['pmid'],category,category_ids.get(category),disposition,digest))
        consume(ai_zip,'ai');consume(human_zip,'human')
        if reference_zip:consume(reference_zip,'reference')
        for pmid in human_main:
            fold='evaluation' if int(hashlib.sha256(('paperdesk-v1:'+pmid).encode()).hexdigest(),16)%5==0 else 'training'
            database.execute('INSERT INTO splits VALUES(?,?)',(pmid,fold))
        database.commit()
        differences=[]
        for pmid in sorted(set(ai_human)|set(human_main)|set(human_unused)):
            differences.append({'pmid':pmid,'in_source':pmid in known,'ai_human_categories':sorted(ai_human.get(pmid,set())),
                'human_categories':sorted(human_main.get(pmid,set())), 'human_unused_categories':sorted(human_unused.get(pmid,set())),
                'teacher_status':'human_positive_example' if pmid in human_main else 'folder_semantics_unconfirmed' if pmid in human_unused else 'human_judgment_unknown',
                'not_a_verified_exclusion':pmid not in human_main})
        folds=dict(database.execute('SELECT fold,count(*) FROM splits GROUP BY fold'))
        report={'is_analysis_only':True,'model_trained':False,'paid_api_calls':0,
            'source_records':len(raw),'source_unique_pmids':len(known),'ai_all_pmids':len(ai_all),'ai_human_pmids':len(ai_human),
            'human_main_pmids':len(human_main),'human_unused_pmids':len(human_unused),
            'human_new_vs_ai':len(set(human_main)-set(ai_human)), 'ai_missing_from_human':len(set(ai_human)-set(human_main)),
            'human_multilabel_pmids':sum(len(v)>1 for v in human_main.values()),'fold_counts':folds,
            'human_pmids_not_in_source':sorted(set(human_main)-known),
            'unmapped_categories':sorted(set().union(*human_main.values())-set(NAMES)) if human_main else [],
            'duplicate_content_groups':[v for digest,v in hashes.items() if len(v)>1 and any(x['origin']=='product_reference' for x in v)],
            'warnings':warnings,
            'limitations':['未掲載を除外とみなさない','使用しないフォルダの意味は未確定','51分類外の名称は自動変換しない','原文抄録・本文と判断理由の照合は未実施','評価用ラベルはルール生成に使用しない','分類精度・ヒト試験判定精度は未測定']}
        (output_dir/'summary.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
        (output_dir/'pmid-differences.json').write_text(json.dumps(differences,ensure_ascii=False,indent=2)+'\n')
        (output_dir/'file-inventory.json').write_text(json.dumps(files,ensure_ascii=False,indent=2)+'\n')
        return report
    finally:database.close()
