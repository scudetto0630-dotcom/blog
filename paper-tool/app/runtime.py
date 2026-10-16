"""Non-secret runtime readiness information for the application, never key values."""
import math
import os

def ai_key():
    return os.environ.get('OPENAI_API_KEY') or os.environ.get('PAPERDESK_AI_KEY')

def readiness():
    missing = []
    if not ai_key(): missing.append('OpenAI APIキー')
    if not os.environ.get('PAPERDESK_AI_MODEL'): missing.append('AIモデル')
    for key, label in [('PAPERDESK_INPUT_USD_PER_MILLION', '入力単価'), ('PAPERDESK_OUTPUT_USD_PER_MILLION', '出力単価'), ('PAPERDESK_JOB_CAP_USD', 'ジョブ費用上限')]:
        try:
            value = float(os.environ.get(key, ''))
            if not math.isfinite(value) or value <= 0: raise ValueError()
        except ValueError: missing.append(label)
    if os.environ.get('PAPERDESK_ALLOW_PAID') != 'explicitly-approved': missing.append('運営者の有料処理許可')
    return {'ai_ready': not missing, 'missing_settings': missing, 'model': os.environ.get('PAPERDESK_AI_MODEL') or None}
