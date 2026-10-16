"""Search names, not scientific classification rules.

Known Japanese labels map to English PubMed search terms. Unknown Latin names
remain searchable; unknown Japanese names are not silently treated as no papers.
"""
import re
import unicodedata

GROUPS = {
    'epigallocatechin gallate': ['EGCG', 'エピガロカテキンガレート', 'エピガロカテキンガレード', 'エピガロカテキン没食子酸'],
    'epicatechin': ['エピカテキン'],
    'catechin': ['catechins', 'カテキン'],
    'curcumin': ['クルクミン'],
    'resveratrol': ['レスベラトロール'],
    'quercetin': ['ケルセチン', 'クエルセチン'],
    'collagen': ['コラーゲン'],
    'hyaluronic acid': ['ヒアルロン酸'],
    'coenzyme Q10': ['コエンザイムQ10', 'コエンザイムＱ１０', 'CoQ10'],
    'vitamin C': ['ビタミンC', 'アスコルビン酸', 'ascorbic acid'],
    'vitamin D': ['ビタミンD'],
    'vitamin E': ['ビタミンE'],
    'vitamin B12': ['ビタミンB12'],
    'folic acid': ['葉酸'],
    'magnesium': ['マグネシウム'],
    'zinc': ['亜鉛'],
    'calcium': ['カルシウム'],
    'iron': ['鉄'],
    'lutein': ['ルテイン'],
    'zeaxanthin': ['ゼアキサンチン'],
    'astaxanthin': ['アスタキサンチン'],
    'lycopene': ['リコピン'],
    'beta carotene': ['ベータカロテン', 'βカロテン', 'β-カロテン'],
    'glucosamine': ['グルコサミン'],
    'chondroitin': ['コンドロイチン'],
    'creatine': ['クレアチン'],
    'carnitine': ['カルニチン', 'L-カルニチン'],
    'taurine': ['タウリン'],
    'glycine': ['グリシン'],
    'arginine': ['アルギニン', 'L-アルギニン'],
    'citrulline': ['シトルリン', 'L-シトルリン'],
    'glutamine': ['グルタミン', 'L-グルタミン'],
    'leucine': ['ロイシン', 'L-ロイシン'],
    'isoleucine': ['イソロイシン'],
    'valine': ['バリン'],
    'tryptophan': ['トリプトファン'],
    'tyrosine': ['チロシン'],
    'cysteine': ['システイン'],
    'N-acetylcysteine': ['N-アセチルシステイン', 'NAC'],
    'theanine': ['テアニン', 'L-テアニン'],
    'caffeine': ['カフェイン'],
    'melatonin': ['メラトニン'],
    'berberine': ['ベルベリン'],
    'sulforaphane': ['スルフォラファン'],
    'nicotinamide mononucleotide': ['NMN', 'ニコチンアミドモノヌクレオチド'],
    'nicotinamide riboside': ['ニコチンアミドリボシド'],
    'docosahexaenoic acid': ['DHA', 'ドコサヘキサエン酸'],
    'eicosapentaenoic acid': ['EPA', 'エイコサペンタエン酸'],
    'omega-3 fatty acids': ['オメガ3', 'オメガ３', 'オメガ3脂肪酸'],
}

def normal(value):
    return ' '.join(unicodedata.normalize('NFKC', value).split()).casefold()

ALIASES = {normal(alias): name for name, aliases in GROUPS.items() for alias in [name, *aliases]}

def search_name(value):
    normalized = normal(value)
    if normalized in ALIASES:
        return ALIASES[normalized]
    if re.search(r'[\u3040-\u30ff\u3400-\u9fff]', value):
        raise ValueError('この成分の英語名を確認できません。成分名を英語で入力してください。推測した別成分では検索しません。')
    return unicodedata.normalize('NFKC', value).strip()

def search_term(value):
    name = search_name(value).replace('"', ' ').strip()
    if name == 'epigallocatechin gallate':
        return '("EGCG"[Title/Abstract] OR "epigallocatechin gallate"[Title/Abstract])'
    return f'"{name}"[Title/Abstract]'
