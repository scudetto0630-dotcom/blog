import unicodedata

STAGES = ['成分名の解析', 'PubMed論文検索', '論文の採用・除外判定', '51部位への自動仕分け', '第1回再チェック', '第2回再チェック', '結果ファイル生成']

class ValidationError(ValueError):
    pass

def normalize_ingredient(value):
    return ' '.join(unicodedata.normalize('NFKC', value).split()).casefold()

def validate_input(data):
    if not isinstance(data, dict):
        raise ValidationError('入力データの形式が正しくありません。')
    name = data.get('name', '')
    ingredients = data.get('ingredients')
    if not isinstance(name, str) or len(name) > 120:
        raise ValidationError('製品名は120文字以内で入力してください。')
    if not isinstance(ingredients, list) or not 1 <= len(ingredients) <= 5:
        raise ValidationError('成分は1〜5種類で入力してください。')
    cleaned, seen = [], set()
    for ingredient in ingredients:
        if not isinstance(ingredient, str) or not ingredient.strip():
            raise ValidationError('成分名を入力してください。空白だけの入力は保存できません。')
        ingredient = ingredient.strip()
        if len(ingredient) > 120:
            raise ValidationError('成分名は120文字以内で入力してください。')
        if any(ord(c) < 32 for c in ingredient):
            raise ValidationError('成分名に改行や制御文字は使用できません。')
        normalized = normalize_ingredient(ingredient)
        if normalized in seen:
            raise ValidationError('同じ成分が重複しています。入力を見直してください。')
        seen.add(normalized)
        cleaned.append(ingredient)
    return {'name': name.strip(), 'ingredients': cleaned}
