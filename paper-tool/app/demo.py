"""Synthetic fixtures only. No PubMed requests, AI calls, or scientific decisions."""
from copy import deepcopy
from urllib.parse import urlencode
from .taxonomy import TAXONOMY, VERSION


def make_result(snapshot):
    papers = []
    for ingredient_index, ingredient in enumerate(snapshot['ingredients']):
        for index in range(6):
            decision = ['adopted', 'adopted', 'adopted', 'excluded', 'excluded', 'review'][index]
            body_ids = [TAXONOMY[(ingredient_index * 7 + index * 3) % 51]['id']]
            if index == 0:
                body_ids.append(TAXONOMY[(ingredient_index * 7 + 5) % 51]['id'])
            human = ['effective', 'human_no_effect', 'non_human', 'non_human', 'unclear', 'unclear'][index]
            initial = {
                'status': decision, 'body_ids': body_ids if decision != 'excluded' else [],
                'human_trial': human,
                'reason': 'デモ用の固定判定です。成分の介入・研究内容・効果は検証していません。',
                'evidence': [], 'engine_version': 'synthetic-fixture-v1',
            }
            first = deepcopy(initial)
            second = deepcopy(initial)
            # Demonstrate a corrected classification and a held decision without claiming research.
            if index == 1:
                first['body_ids'] = [TAXONOMY[(ingredient_index * 7 + 8) % 51]['id']]
                first['reason'] = 'デモ：第1回で仮の部位分類を変更した例です。'
                second = deepcopy(first)
            if index == 5:
                second['reason'] = 'デモ：第2回でも要確認のまま保留する例です。'
            papers.append({
                'id': f'demo-{ingredient_index + 1}-{index + 1}', 'is_demo': True,
                'ingredient': ingredient, 'pmid': None, 'doi': None, 'year': None,
                'title': f'デモ項目 {index + 1}：{ingredient}の研究サンプル（架空）',
                'abstract': '画面の操作確認用に作成した架空の項目です。実在する論文の要約ではありません。',
                'pubmed_url': 'https://pubmed.ncbi.nlm.nih.gov/?' + urlencode({'term': ingredient}),
                'initial_decision': initial,
                'checks': [
                    {'round': 1, 'is_demo': True, 'status': 'completed', 'decision': first, 'changed': first != initial, 'evidence': []},
                    {'round': 2, 'is_demo': True, 'status': 'completed', 'decision': second, 'changed': second != first, 'evidence': []},
                ],
                'final_decision': second,
            })
    return {
        'is_demo': True, 'taxonomy_version': VERSION, 'papers': papers,
        'summary': {
            'retrieved': len(papers),
            'adopted': sum(p['final_decision']['status'] == 'adopted' for p in papers),
            'excluded': sum(p['final_decision']['status'] == 'excluded' for p in papers),
            'human_effective': sum(p['final_decision']['status'] == 'adopted' and p['final_decision']['human_trial'] == 'effective' for p in papers),
            'review': sum(p['final_decision']['status'] == 'review' for p in papers),
            'corrected': sum(any(c['changed'] for c in p['checks']) for p in papers),
        },
        'artifact': {'status': 'not_implemented', 'download_url': None},
    }
