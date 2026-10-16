"""Editorial components. Verification dates describe evidence, never successful builds."""
import html,json,datetime,urllib.parse
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
STAGES={'before':'迎える前','first':'迎えた直後','settled':'慣れてから'}
def e(s):return html.escape(str(s),quote=True)
def dates(a):return '<span class="article-meta">公開日：'+e(a['published_at'])+'　本文更新日：'+e(a['updated_at'])+'</span>'
def stage_choices():
 return '<section class="section stage-section"><div class="section-heading"><div><p class="eyebrow">YOUR FIRST STEP</p><h2>今の暮らしに合わせて読む</h2></div><p>迎える前も、一緒に暮らし始めてからも。</p></div><div class="stage-grid">'+''.join('<a href="guides/?stage='+key+'"><span>0'+str(i+1)+'</span><h3>'+name+'</h3><p>'+text+'</p><b aria-hidden="true">→</b></a>' for i,(key,name,text) in enumerate([('before','迎える前','部屋の準備や、保護猫を迎えるまでの流れ。'),('first','迎えた直後','初日の接し方、ごはんやトイレで迷ったときに。'),('settled','慣れてから','爪切りや留守番など、日々の世話を見直す。')]))+'</div></section>'
def stage_filters():return '<div class="stage-filter-list" role="group" aria-label="暮らしの段階">'+''.join('<button type="button" data-stage="'+key+'" aria-pressed="'+str(key=='all').lower()+'">'+name+'</button>' for key,name in [('all','すべての時期')]+list(STAGES.items()))+'</div>'
def diagram(slug):
 if slug=='first-days':
  return '<figure class="care-diagram"><figcaption>最初に過ごす部屋の、配置の一例</figcaption><div class="room-plan"><div class="room-bed">寝床・隠れ場所<small>落ち着いて休める場所</small></div><div class="room-food">ごはん・水<small>トイレから離して置く</small></div><div class="room-space">行き来できるスペース</div><div class="room-litter">トイレ<small>静かで、いつでも行ける場所</small></div></div><p>間取りや猫の様子に合わせて調整してください。家具で行き止まりを作らず、窓や出入口の脱走対策も忘れずに。</p></figure>'
 if slug=='safe-home':
  return '<figure class="care-diagram"><figcaption>家の中は、4か所に分けて確認</figcaption><div class="safety-grid"><div><strong>窓</strong><span>網戸だけに頼らず、転落・脱走の対策をする</span></div><div><strong>玄関</strong><span>扉を開ける前に、猫の居場所を確かめる</span></div><div><strong>床・机の上</strong><span>ひも、輪ゴム、小さな部品を片づける</span></div><div><strong>棚・収納</strong><span>薬や洗剤を閉まる場所にしまう</span></div></div><p>猫が登ったり、入り込んだりできる場所も見ておきましょう。</p></figure>'
 if slug=='vet-visit':
  return '<figure class="care-diagram"><figcaption>病院へ相談するときの流れ</figcaption><ol class="call-flow"><li><strong>気になる変化</strong><span>いつから、何が変わったか</span></li><li><strong>短く記録</strong><span>食事・排せつ・動画など</span></li><li><strong>病院へ電話</strong><span>状況を伝え、指示を聞く</span></li></ol><p><strong>苦しそうな呼吸、意識の異常、尿が出ない、誤飲の疑いがあるときは、記録より先に病院へ連絡してください。</strong></p></figure>'
 return ''
def sources(a,refs):
 used=[refs[k] for k in a.get('references',[]) if k in refs];verified=bool(used) and all(r.get('verified_at') for r in used)
 label='参照資料' if verified else '公的な情報を調べるには'
 text='参照先の内容を確認した日：'+max(r['verified_at'] for r in used) if verified else '外部資料との照合は完了していません。以下は、公的な飼育情報や相談先を調べるためのリンクです。'
 return '<section class="source-box" aria-label="情報の確認先"><h2>'+label+'</h2><p>'+e(text)+'</p><ul>'+''.join('<li><a href="'+e(r['url'])+'" target="_blank" rel="noopener noreferrer">'+e(r['title'])+' ↗</a></li>' for r in used)+'</ul></section>'
def related(a,articles):
 by={x['slug']:x for x in articles};items=[by[s] for s in a.get('related',[]) if s in by]
 return '<section class="related-reading"><h2>次に読む</h2><div>'+''.join('<a href="articles/'+e(x['slug'])+'/"><span>'+e(x['category'])+'</span><strong>'+e(x['title'])+'</strong><b aria-hidden="true">→</b></a>' for x in items)+'</div></section>'
def report_link(a):return '<p class="report-article"><a href="corrections/?page='+urllib.parse.quote('articles/'+a['slug']+'/',safe='')+'">この記事の間違いやリンク切れを知らせる</a></p>'
def health():
 return '''<section class="section health-hub"><aside class="urgent-panel"><p class="eyebrow">急いで相談したいとき</p><h2>記事を読み続けるより、先に病院へ電話を。</h2><ul><li>苦しそうに呼吸している</li><li>意識がはっきりしない</li><li>トイレへ行くのに尿が出ない</li><li>誤飲や中毒が疑われる</li></ul><p>かかりつけが休診なら、夜間・救急対応の病院へ連絡してください。受け入れが可能か、移動前に確認します。</p><p>自己判断で人用の薬を与えたり、無理に吐かせたりしないでください。</p></aside><div class="health-columns"><section><h2>普段の健康管理を知りたいとき</h2><p>食事や排せつ、体重の変化を、いつもの様子と比べて記録します。気になる変化が続くときも、動物病院へ相談してください。</p><ul><li><a href="articles/vet-visit/">受診のときに伝えたいこと</a></li><li><a href="articles/food-and-water/">ごはんと水の基本</a></li><li><a href="articles/litter-box/">トイレと排せつの様子</a></li></ul></section><section><h2>元気なうちに用意しておくもの</h2><ul><li>かかりつけの診療時間と休診日</li><li>夜間や休診日に相談できる病院の連絡先</li><li>薬や通院歴の分かるメモ</li><li>安全に運べるキャリー</li></ul><p>このサイトには、病名を判定する機能や診療相談の受付はありません。</p></section></div></section>'''
def corrections():
 return '''<section class="section correction-wrap"><aside class="form-notice"><strong>記事の間違いやリンク切れの連絡用です。</strong><p>診療相談、緊急の保護依頼、猫の引き取りは受け付けていません。体調の相談は動物病院へ直接連絡してください。</p></aside><form id="correction-form"><label for="report-page">該当ページのURL</label><input id="report-page" name="page" type="url" required maxlength="1000" value="https://hirogarushumi.com/neko-guide/"><label for="report-kind">連絡の種類</label><select id="report-kind" name="kind"><option value="content">記事の内容について</option><option value="link">リンク切れ・表示の不具合</option><option value="other">その他の訂正連絡</option></select><label for="report-message">気になった箇所と内容 <span>必須・2000文字以内</span></label><textarea id="report-message" name="message" required maxlength="2000" rows="8" placeholder="どの部分が、どのように違っているか教えてください。"></textarea><div class="form-honeypot" aria-hidden="true"><label>この欄は空欄にしてください<input name="website" tabindex="-1" autocomplete="off"></label></div><p class="form-help">名前やメールアドレスは不要です。個人情報や、第三者の連絡先は書かないでください。返信機能はありません。送信内容は運営者が確認するために保存します。</p><p><a href="privacy/#correction-data">送信内容の取り扱い</a></p><button class="button primary" type="submit">訂正連絡を送る →</button><p id="report-status" role="status" aria-live="polite"></p><noscript><p>フォームを送信するには、JavaScriptを有効にしてください。</p></noscript></form></section>'''
def profile():
 return '''<section class="manager-profile"><p class="eyebrow">猫と暮らす運営者</p><h2>保護猫のまんもすと暮らしています。</h2><p>これまで8匹の猫と暮らしてきました。今は、まんもすと一緒に暮らしています。</p><dl><div><dt>名前</dt><dd>まんもす</dd></div><div><dt>種類</dt><dd>スコティッシュフォールド</dd></div><div><dt>体重</dt><dd>5.5kg</dd></div><div><dt>使っているおもちゃ</dt><dd>100均のねこじゃらし</dd></div></dl><p>サイトに使っている猫の絵はイメージイラストです。まんもす本人や、譲渡を待っている猫の写真ではありません。</p></section>'''
def current_local(data,today):
 def valid(item,days):
  try:
   checked=datetime.date.fromisoformat(item.get('verified_at',''));end=datetime.date.fromisoformat(item.get('expires_at',''))
   return checked<=today<=end and (today-checked).days<=days and item.get('official_url','').startswith('https://')
  except (TypeError,ValueError):return False
 return ([x for x in data.get('organizations',[]) if valid(x,30)],[x for x in data.get('events',[]) if valid(x,7) and x.get('event_end','')>=today.isoformat()])
def local_cards(data,today):
 orgs,events=current_local(data,today);out='<section class="local-listings"><h2>保護団体・譲渡会の案内</h2>'
 if not orgs and not events:out+='<p>現在、個別の団体や譲渡会の情報は掲載していません。開催日時や参加条件は、主催者の最新の案内で確認してください。</p>'
 for x in orgs+events:
  fresh=(datetime.date.fromisoformat(x['verified_at'])+datetime.timedelta(days=7 if x in events else 30)).isoformat();end=min(x['expires_at'],x.get('event_end',x['expires_at']),fresh);out+='<!--NKT_FRESH_UNTIL:'+end+'--><article class="local-listing"><h3>'+e(x['title'])+'</h3><p>'+e(x.get('description',''))+'</p>'
  if x.get('event_end'):out+='<p>開催日：'+e(x['event_start'])+'〜'+e(x['event_end'])+'</p>'
  out+='<p>情報確認日：'+e(x['verified_at'])+'</p><a href="'+e(x['official_url'])+'" target="_blank" rel="noopener noreferrer">主催者の公式案内を見る ↗</a></article><!--/NKT_FRESH_UNTIL-->'
 return out+'</section>'
