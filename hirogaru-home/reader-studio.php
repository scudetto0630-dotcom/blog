<?php
if(!defined('ABSPATH'))exit;
function hsh_studio_minutes($text){return max(1,(int)ceil(mb_strlen(wp_strip_all_tags($text),'UTF-8')/600));}
function hsh_studio_catalog($slug){foreach(hsh_catalog() as $a)if($a['slug']===$slug)return $a;return null;}
function hsh_studio_card_meta($post){return '<span class="hsh-read-time">約'.hsh_studio_minutes($post->post_content).'分で読めます</span>';}
function hsh_studio_article_link($slug,$label='',$anchor=''){$p=get_page_by_path($slug,OBJECT,'post');if(!$p||$p->post_status!=='publish')return '';return '<a href="'.esc_url(get_permalink($p).($anchor?'#'.$anchor:'')).'">'.esc_html($label?:get_the_title($p)).'</a>';}
function hsh_studio_featured(){
 $out='<section class="hsh-featured"><p class="hsh-eyebrow">STORIES</p><h2>じっくり読む、暮らしの話</h2><div class="hsh-featured-grid">';
 foreach(array('manmos-cat-life','glenlivet-12-first-bottles','it-data-work-flow') as $slug){$p=get_page_by_path($slug,OBJECT,'post');if($p&&$p->post_status==='publish')$out.='<article>'.hsh_studio_article_link($slug).'<p>'.esc_html(hsh_growth_summaries()[$slug]??'').'</p></article>';}
 return $out.'</div></section>';
}
function hsh_studio_postscript($slug,$topic){
 $out='<nav class="hsh-after-reading" aria-label="読んだ後のおすすめ"><h2>次は、どの話を読もう？</h2><div><a href="'.esc_url(hsh_category_url($topic)).'">同じ分野の記事を読む →</a>';foreach(hsh_topics() as $k=>$t)if($k!==$topic)$out.='<a href="'.esc_url(hsh_category_url($k)).'">'.esc_html($t[0]).'へ →</a>';
 return $out.'</div></nav>';
}
function hsh_studio_checklist($id,$title,$items){
 $out='<section class="hsh-note-sheet" data-sheet="'.esc_attr($id).'"><h2>'.esc_html($title).'</h2><p class="hsh-device-note">チェックとメモはこの端末のブラウザー内に保存します。個人情報は書き込まないでください。</p><fieldset><legend>確認すること</legend>';
 foreach($items as $i=>$item)$out.='<label><input type="checkbox" data-sheet-check="'.(int)$i.'"> '.esc_html($item).'</label>';
 return $out.'</fieldset><label>メモ<textarea data-sheet-note maxlength="2000" rows="3" placeholder="気付いたこと、次に確認したいこと"></textarea></label><p data-sheet-status role="status" aria-live="polite"></p><div class="hsh-sheet-actions"><button type="button" data-sheet-print>このシートを印刷</button><button type="button" data-sheet-reset>すべて解除</button></div></section>';
}
function hsh_studio_highball(){return '<section class="hsh-highball-tool"><h2>好みの割合で、分量を計算する</h2><p>ウイスキー1に対して、ソーダを何倍にするかで計算します。グラス容量には氷も入るため、飲み物の合計量は別に指定してください。</p><form data-highball><label>ウイスキーの量（ml）<input name="whisky" type="number" min="5" max="100" step="1" value="30" required></label><label>ソーダの倍率<input name="ratio" type="number" min="1" max="10" step="0.5" value="3" required></label><label>グラス容量（ml・任意）<input name="glass" type="number" min="100" max="1000" step="1" placeholder="例：350"></label><button>分量を計算</button></form><p data-highball-result role="status" aria-live="polite">ウイスキー30ml＋ソーダ90ml＝飲み物120ml。氷を入れる余地は別に必要です。</p></section>';}
function hsh_studio_examples(){
 return '<section class="hsh-data-practice"><h2>架空の表で、データ抽出を試す</h2><p>次の4行は練習用に作った架空データです。顧客や勤務先の実データではありません。「区分が猫、金額が1,000円以上」を条件に抜き出します。</p><h3>処理前</h3><table><thead><tr><th>ID</th><th>区分</th><th>金額（円）</th></tr></thead><tbody><tr><td>0001</td><td>猫</td><td>1200</td></tr><tr><td>0002</td><td>ウイスキー</td><td>4000</td></tr><tr><td>0003</td><td>猫</td><td>800</td></tr><tr><td>0004</td><td>猫</td><td>1500</td></tr></tbody></table><h3>期待する結果</h3><p>ID「0001」「0004」の2件です。合計金額は2,700円。先頭の0を残し、元の表の順番を保ちます。</p><h3>依頼文の例</h3><pre data-copy-example>元データを残したまま、区分が「猫」、金額が1000円以上の行を抽出してください。IDは文字列として先頭の0を保持し、元の並び順を維持してください。抽出件数と金額の合計を示してください。</pre><pre data-copy-example>金額を数値として昇順に並べ替えてください。同額の場合は元の並び順を保ち、IDと金額の対応を変えないでください。</pre><pre data-copy-example>IDの重複を調べ、重複したIDと該当行を別表にまとめてください。元データからは削除しないでください。</pre><h3>Excel・スプレッドシート・CSVの違い</h3><dl><dt>Excel</dt><dd>表計算ソフトです。xlsx形式なら複数のシート、書式、数式などを保存できます。</dd><dt>スプレッドシート</dt><dd>ブラウザーで共同編集できる表計算サービスです。共有範囲と編集権限を確認して使います。</dd><dt>CSV</dt><dd>行と列の値を区切り文字で表すテキスト形式です。書式や複数シートの保存には向きません。取り込み時の文字コードやデータ型に注意が必要です。</dd></dl></section>';
}
function hsh_studio_terms($topic,$slug=''){
 if($topic==='cats')$terms=array('総合栄養食'=>'指定された対象や成長段階に合う製品を、表示に従って与えるための栄養区分。個々の商品表示を確認します。','療法食'=>'病気などに応じて栄養成分を調整した食事。自己判断で選ばず獣医師に相談します。','サプリメント'=>'健康維持のために使われる補助的な製品。医薬品と同じ治療効果を意味するものではありません。');
 elseif($topic==='whisky')$terms=array('シングルモルト'=>'ひとつの蒸溜所で造られたモルトウイスキーを指します。','年数表示'=>'混和された原酒のうち最も若い原酒の熟成年数を示します。','ハーフロック'=>'氷を入れたグラスに、ウイスキーと水を同量程度ずつ加える飲み方。');else return '';
 $article=$slug!==''?hsh_studio_catalog($slug):null;if($article){$post=get_page_by_path($slug,OBJECT,'post');$body=wp_strip_all_tags($article['content']??($post->post_content??''));$terms=array_filter($terms,function($description,$term)use($body){return strpos($body,$term)!==false;},ARRAY_FILTER_USE_BOTH);}if(!$terms)return '';
 $out='<details class="hsh-term-notes"><summary>この記事に出てくる言葉</summary><dl>';foreach($terms as $term=>$description)$out.='<dt>'.esc_html($term).'</dt><dd>'.esc_html($description).'</dd>';
 if($topic==='whisky')$out.='<dd>'.hsh_studio_article_link('single-malt-label-guide','ボトルの表示をもう少し詳しく読む','single-malt-label-guide-1').'</dd>';
 return $out.'</dl></details>';
}
function hsh_studio_source_content(){static $v=null;if($v===null)$v=json_decode(file_get_contents(__DIR__.'/product-reader-notes.json'),true)?:array();return $v;}
function hsh_studio_product_notes($slug){
 $d=hsh_studio_source_content()[$slug]??null;if(!$d)return '';$out='<section class="hsh-product-notes"><h2>'.esc_html($d['heading']).'</h2><h3>申し込み・購入の流れ</h3><ol>';foreach($d['steps'] as $s)$out.='<li>'.esc_html($s).'</li>';$out.='</ol><h3>よくある疑問</h3>';
 foreach($d['qa'] as $q)$out.='<details><summary>'.esc_html($q[0]).'</summary><p>'.esc_html($q[1]).'</p></details>';
 $a=hsh_studio_catalog($slug);$context='';if($a&&preg_match('~href="(https://px\.a8\.net/[^"]+)"~',$a['content'],$m)){$name=preg_split('/[｜。]/u',preg_replace('/^【ご紹介】/u','',$a['title']))[0];$context='<p class="hsh-affiliate-context"><small>広告</small> <a rel="nofollow sponsored" href="'.esc_url(html_entity_decode($m[1],ENT_QUOTES,'UTF-8')).'">'.esc_html($name).'の公式案内へ →</a></p>';}
 return $out.$context.'</section>';
}
add_filter('the_content',function($c){
 if(!in_the_loop()||!is_main_query())return $c;$p=get_post();if(!$p||!hsh_owned_blog_post($p))return $c;$slug=$p->post_name;$a=hsh_studio_catalog($slug);if(!$a)return $c;
 $c=preg_replace('~<p[^>]*>(?:(?!</p>).)*csv-practice\.csv(?:(?!</p>).)*</p>~s','',$c);
 $c=preg_replace('~<li[^>]*>(?:(?!</li>).)*extraction-(?:input|expected)\.csv(?:(?!</li>).)*</li>~s','',$c);
 // Keep the introduction continuous; the native table of contents remains available.
 $c.=hsh_studio_terms($a['topic'],$slug);
 if(hsh_brushup_type($slug)==='product'){$c.=hsh_studio_product_notes($slug);if(strpos($slug,'withpety-')===0)$c.='<aside class="hsh-supplement-note"><h2>フード・サプリ・医薬品の違い</h2><p>サプリは、主食となるフードや治療に使う医薬品と役割が異なります。現在の薬や食事と一緒に獣医師へ伝え、病気の治療の代わりとして使わないでください。</p></aside>';}
 if($slug==='cat-carrier-checklist'){$c.=hsh_studio_checklist('carrier-measure','キャリーの採寸メモ',array('猫が自然に立ち、向きを変え、伏せられる内寸か確認する','入口の幅・高さと、持ち手の位置を確認する','徒歩：持ち上げたときの安定と自分が運べる重さを確認する','車：車内で固定できるか、メーカーの案内を確認する','公共交通：利用する交通機関の寸法・重量・容器の規定を確認する'));}
 if(in_array($slug,array('hario-cat-bowl-guide','onekosama-cat-tower','catlog-cat-monitor'),true))$c.=hsh_studio_checklist('place-'.$slug,'設置前に測っておくこと',array('設置場所の幅・奥行き・高さを測る','猫が出入りする動線を確保する','扉・窓・家具との干渉を確認する','掃除や電源、固定方法をメーカーの説明で確認する'));
 if($slug==='cat-vet-routine')$c.=hsh_studio_checklist('vet-note','診察で伝えたいこと',array('気になる様子がいつから始まったか','食事・水・排泄の変化','現在のフード・薬・サプリの名前','撮影した写真や動画を見せられるようにする'));
 if(in_array($slug,array('mognyan-cat-food-guide','canagan-chicken-salmon','paws-green-deli-cat-food'),true))$c.=hsh_studio_checklist('food-note','ごはんの切り替え記録',array('開始日と、これまでのフードを記録する','与えた種類・量・食べ残しを記録する','便や体調で気付いたことを記録する','不調があれば変更を進めず獣医師に相談する'));
 if($slug==='highball-measure-and-adjust')$c.=hsh_studio_highball();
 if(in_array($slug,array('glenlivet-12-first-bottles','sherry-cask-notes','highball-measure-and-adjust'),true))$c.=hsh_studio_checklist('tasting-'.$slug,'自分の好みを残すメモ',array('銘柄と飲んだ日','飲み方と、自分で用意した分量','香りで思い浮かんだもの','飲みやすさと、次に変えてみたいこと'));
 if($slug==='ai-data-extraction-checks')$c.=hsh_studio_examples();
 if($slug==='csv-import-seven-checks'){$example=hsh_studio_examples();if(preg_match('~<h3>Excel・スプレッドシート・CSVの違い</h3>(.*?)</section>~s',$example,$formats))$c.='<section class="hsh-data-practice"><h2>Excel・スプレッドシート・CSVの違い</h2>'.$formats[1].'</section>';}
 return $c.hsh_studio_postscript($slug,$a['topic']);
},29);
add_action('wp_enqueue_scripts',function(){if(!hsh_reader_scope())return;wp_enqueue_style('hsh-studio',plugin_dir_url(__FILE__).'assets/studio.css',array('hsh-brushup'),'1.33.3');wp_enqueue_script('hsh-studio',plugin_dir_url(__FILE__).'assets/studio.js',array('hsh-brushup'),'1.33.3',true);},140);
// The native WordPress relevance ordering already prioritizes matching titles.
add_action('pre_get_posts',function($q){if(!is_admin()&&$q->is_main_query()&&$q->is_search())$q->set('orderby','relevance');},35);
function hsh_studio_home_products(){
 $out='<section class="hsh-articles hsh-home-products"><div class="hsh-article-heading"><div><p class="hsh-eyebrow">PRODUCTS & SERVICES</p><h2>猫との暮らしの商品紹介</h2></div><a href="'.esc_url(hsh_category_url('cats').'?hsh_kind=product').'">商品紹介をすべて見る →</a></div><div class="hsh-post-grid">';$n=0;
 foreach(hsh_posts('cats',50) as $p){if(hsh_brushup_type($p->post_name)!=='product')continue;$out.='<article><a href="'.esc_url(get_permalink($p)).'">'.hsh_card_cover($p).'<time datetime="'.esc_attr(get_the_date('c',$p)).'">'.esc_html(get_the_date('Y.m.d',$p)).'</time><h3>'.esc_html(get_the_title($p)).'</h3><p>'.esc_html($p->post_excerpt).'</p><span class="hsh-read-more">記事を読む →</span></a></article>';if(++$n===3)break;}return $out.'</div></section>';
}
function hsh_studio_series($slug){
 $groups=array('cats'=>array('manmos-cat-life','before-welcoming-cat','cat-teaser-small-parts','cat-carrier-checklist','cat-vet-routine'),'whisky'=>array('glenlivet-12-first-bottles','sherry-cask-notes','single-malt-label-guide','highball-measure-and-adjust'),'it-freelance'=>array('it-data-work-flow','starting-it-without-specialty','ai-data-extraction-checks','csv-import-seven-checks'));
 foreach($groups as $topic=>$slugs){$i=array_search($slug,$slugs,true);if($i===false)continue;return '<p class="hsh-studio-series">'.esc_html(hsh_topics()[$topic][0]).'の読む順番：'.($i+1).' / '.count($slugs).'　'.(isset($slugs[$i+1])?hsh_studio_article_link($slugs[$i+1],'次の記事へ →'):'<a href="'.esc_url(hsh_category_url($topic)).'">一覧へ →</a>').'</p>';}
 return '';
}
function hsh_studio_care(){return '<section class="hsh-care-notes"><h3>素材と部品ごとに、手入れの表示を確認</h3><dl><dt>陶器・ガラスの器</dt><dd>欠けやひびを点検し、使用できる洗剤・温度・食器洗浄機への対応は、その商品の説明に従います。</dd><dt>布・ニット・クッション</dt><dd>洗濯表示と取り外せる部品を確認します。水洗いできると決めつけず、指定の乾かし方まで確認します。</dd><dt>木・段ボール・支柱</dt><dd>濡らしてよい範囲や交換方法は素材・製品で異なります。固定部の緩みもメーカーの案内に沿って点検します。</dd><dt>電子機器</dt><dd>水洗いできる部分と電子部品を分けて確認し、電源・充電・清掃方法は取扱説明書に従います。</dd></dl></section>';}
add_filter('the_content',function($c){if(!in_the_loop()||!is_main_query())return $c;$p=get_post();if(!$p||!hsh_owned_blog_post($p))return $c;$c=hsh_studio_dates($p).hsh_studio_series($p->post_name).$c;if(in_array($p->post_name,array('hario-cat-bowl-guide','onekosama-cat-tower','catlog-cat-monitor','m-and-cat-knit-bag'),true))$c.=hsh_studio_care();return $c;},30);
function hsh_studio_rendered($html){
 if(strpos($html,'hsh-reader-site')===false||strpos($html,'</head>')===false)return $html;
 $paged=function_exists('get_query_var')?max(1,(int)get_query_var('paged')):1;if($paged>1&&hsh_current_topic())$html=preg_replace_callback('~<title>(.*?)</title>~s',function($m)use($paged){return '<title>'.$m[1].'（'.$paged.'ページ目）</title>';},$html,1);
 $legacy=json_decode(file_get_contents(__DIR__.'/legacy-title-labels.json'),true)?:array();$map=array();foreach($legacy as $r)$map[$r['before']]=$r['after'];
 return preg_replace_callback('~(<a\b[^>]*href=[\x22\x27]'.preg_quote(home_url('/'),'~').'[^\x22\x27]*[\x22\x27][^>]*>)([^<]+)(</a>)~u',function($m)use($map){$label=html_entity_decode($m[2],ENT_QUOTES,'UTF-8');return isset($map[$label])?$m[1].esc_html($map[$label]).$m[3]:$m[0];},$html);
}
add_filter('the_content',function($c){$p=get_post();if($p&&in_the_loop()&&is_main_query()&&rawurldecode($p->post_name)==='プライバシーポリシー')$c.='<section><h2>この端末に保存する読書の記録</h2><p>保存した記事、閲覧履歴、読了マーク、読んだ位置、文字サイズ、記録シートは、ご利用のブラウザー内に保存します。これらの記録を、この読書機能からサーバーへ送信することはありません。「読み方・この端末の記録」から履歴の停止や記録の削除ができます。記録シートに個人情報を書き込まないでください。保存データは各機能で削除するか、ブラウザーのサイトデータを消去するまで残ります。別の端末とは同期しません。お問い合わせフォームの入力内容を、この機能で自動保存することはありません。</p></section>';return $c;},31);
function hsh_studio_dates($p){
 $published=get_the_date('c',$p);$modified=get_post_modified_time('c',false,$p);
 $out='<p class="hsh-entry-dates"><span>公開：<time data-date-kind="published" datetime="'.esc_attr($published).'">'.esc_html(get_the_date('Y.m.d',$p)).'</time></span>';
 if($modified&&$modified!==$published)$out.='<span>更新：<time data-date-kind="modified" datetime="'.esc_attr($modified).'">'.esc_html(get_post_modified_time('Y.m.d',false,$p)).'</time></span>';
 return $out.'</p>';
}
