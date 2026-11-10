<?php
if(!defined('ABSPATH'))exit;
/** Public additions only use packaged facts and approved packaged photographs. */
function hsh_growth_summaries(){return array(
'before-welcoming-cat'=>'迎える前に用意したい物と、部屋を片付けるときの確認項目。',
'manmos-cat-life'=>'抱っこは苦手なまんもすの、好きな場所や毎日のブラッシングの話。',
'cat-teaser-small-parts'=>'100均のおもちゃも使う飼い主が、小さな部品や片付けで気にしていること。',
'cat-vet-routine'=>'診察で伝えたい様子と、出発前にまとめておきたい持ち物。',
'cat-carrier-checklist'=>'5.5kgの猫でリュック型が変形。買い替え経験と、内寸・構造の確認点。',
'glenlivet-12-first-bottles'=>'飲みやすさと花のような香りが好き。グラス付きで買ったきっかけも。',
'single-malt-label-guide'=>'シングルモルト、年数、樽。ボトルの表示を順番に見ていきます。',
'sherry-cask-notes'=>'グレンファークラス12年の香りの感想。ぶどうパンやレーズンを思い浮かべた話。',
'highball-measure-and-adjust'=>'薄さと炭酸の弱さを分けて考え、少しずつ好みの配合を探す方法。',
'it-data-work-flow'=>'抽出や並び替えなど、今の仕事でしているデータ加工とAIの使いどころ。',
'starting-it-without-specialty'=>'ITに興味を持った理由と、今の仕事にたどり着くまでの約1年。',
'ai-data-extraction-checks'=>'架空の表で、抽出条件や件数をどう確かめるか試します。',
'csv-import-seven-checks'=>'元ファイル、文字コード、先頭の0など、取り込み前に確かめたいこと。');}
function hsh_growth_next($slug){$pairs=array(
'before-welcoming-cat'=>'manmos-cat-life','manmos-cat-life'=>'cat-carrier-checklist','cat-carrier-checklist'=>'cat-vet-routine','cat-vet-routine'=>'cat-carrier-checklist','cat-teaser-small-parts'=>'manmos-cat-life',
'glenlivet-12-first-bottles'=>'sherry-cask-notes','sherry-cask-notes'=>'single-malt-label-guide','single-malt-label-guide'=>'glenlivet-12-first-bottles','highball-measure-and-adjust'=>'glenlivet-12-first-bottles',
'starting-it-without-specialty'=>'it-data-work-flow','it-data-work-flow'=>'ai-data-extraction-checks','ai-data-extraction-checks'=>'csv-import-seven-checks','csv-import-seven-checks'=>'ai-data-extraction-checks');return $pairs[$slug]??'';}
function hsh_growth_nav(){return '<nav class="hsh-explore-nav" aria-label="写真と記事を探す"><a href="'.esc_url(home_url('/hsh-gallery/')).'">まんもすの写真</a><a href="'.esc_url(home_url('/hsh-whisky-notes/')).'">飲んだウイスキーのメモ</a><a href="'.esc_url(home_url('/hsh-updates/')).'">最近の更新</a></nav>';}
add_action('init',function(){add_option('hsh_all_articles_counted_since',wp_date('Y_m_d'),'',false);});
function hsh_growth_popular(){
    $totals=array();$start=max(wp_date('Y_m_d',time()-29*DAY_IN_SECONDS),(string)get_option('hsh_all_articles_counted_since',wp_date('Y_m_d')));$end=wp_date('Y_m_d');
    foreach((array)get_option('hsh_request_counts',array()) as $day=>$rows)if($day>=$start&&$day<=$end)foreach($rows as $slug=>$count)if(isset(hsh_growth_summaries()[$slug]))$totals[$slug]=($totals[$slug]??0)+max(0,(int)$count);
    $totals=array_filter($totals,function($n){return $n>=10;});if(count($totals)<3||array_sum($totals)<50)return '';arsort($totals);$out='<section class="hsh-popular"><h2>最近、読まれている記事</h2><p>直近30日のサーバー到達件数をもとにしています。キャッシュ配信などは含まず、正確な閲覧数ではありません。</p><ul>';
    foreach(array_slice($totals,0,3,true) as $slug=>$n){$p=get_page_by_path($slug,OBJECT,'post');if($p&&$p->post_status==='publish')$out.='<li><a href="'.esc_url(get_permalink($p)).'">'.esc_html($p->post_title).'</a></li>';}
    return $out.'</ul></section>';
}
function hsh_growth_gallery(){
    $out='<section class="hsh-gallery-page"><p>爪とぎの上でくつろいだり、枕を使ったり。うちのまんもすです。</p>'.hsh_reader_gallery_filters().'<div class="hsh-photo-grid">';
    foreach(hsh_photo_manifest()['assets'] as $a)if($a['kind']==='provided'&&$a['subject']==='manmos')$out.='<figure class="hsh-photo"'.(hsh_reader_gallery_matches($a)?'':' hidden').' data-photo-tags="'.esc_attr(implode(' ',$a['tags']??array('relax'))).'">'.hsh_photo_image($a,false,'(max-width:600px) calc(100vw - 44px), 360px').hsh_photo_figcaption(array_merge($a,array('caption'=>$a['alt']))).'</figure>';
    return $out.'</div><p>写真は管理人から提供したものです。</p><p><a href="'.esc_url(home_url('/manmos-cat-life/')).'">まんもすとの暮らしを読む →</a></p></section>';
}
function hsh_growth_tastes(){return array(
array('name'=>'グレンリベット12年','note'=>'花のような、上品な香り。最初は詳しいことが分からなくても飲みやすかった。','reason'=>'グラスのおまけが付いていた。','slug'=>'glenlivet-12-first-bottles'),
array('name'=>'グレングラント アルボラリス','note'=>'オレンジジュースのような味を想像していた。実際には甘くなかったけど、飲みやすかった。','reason'=>'YouTubeで飲んでいるのを見て気になった。','slug'=>''),
array('name'=>'グレンファークラス12年','note'=>'ぶどうパンに顔をうずめたみたいな匂い。レーズンのような香りが好きなんだと思う。','reason'=>'シェリーカスクが好き。','slug'=>'sherry-cask-notes'),
array('name'=>'ジムビーム','note'=>'自分の口には合わなかった。ジムビームを飲んだときの感想です。','reason'=>'購入した理由は、まだメモに残していない。','slug'=>''));}
function hsh_growth_whisky(){
    $out='<section class="hsh-taste-index"><p>飲んだときの印象を、自分の言葉で残しています。詳しい香りの名前は分からなくても、「これは好き」はあるので。</p><p>普段はハイボールかハーフロック。自分が飲んだときの印象を、短くまとめています。</p>';
    foreach(hsh_growth_tastes() as $r){$arboralis=strpos($r['name'],'アルボラリス')!==false;$out.='<article class="hsh-taste-card"'.($arboralis?' id="arboralis-taste"':'').'><h2>'.esc_html($arboralis?'アルボラリスは甘い？飲んだ感想':$r['name']).'</h2><p>'.esc_html($r['note']).'</p><dl><dt>選んだきっかけ・好み</dt><dd>'.esc_html($r['reason']).'</dd></dl>';if($arboralis)$out.='<p>YouTubeで飲んでいるのを見て、オレンジジュースのような甘さを勝手に想像していた。でも、実際には甘くなかった。それでも飲みやすかったので、好きなウイスキーの一つです。</p><p>自分の印象は「想像ほど甘くなかったけれど、飲みやすかった」。ほかの人の香りの例えを、そのまま自分の味の感想にしなくてもいいんだと思う。</p>';if($r['slug'])$out.='<a href="'.esc_url(home_url('/'.$r['slug'].'/')).'">詳しい感想を読む →</a>';$out.='</article>';}
    return $out.hsh_whisky_display_gallery().'<p><a href="'.esc_url(home_url('/highball-measure-and-adjust/')).'">ハイボールの分量を調整したいときはこちら</a></p></section>';
}
function hsh_whisky_display_gallery(){
    $manifest=hsh_photo_manifest();$out='<section class="hsh-whisky-photos"><h2>白州で撮った、ウイスキーの展示</h2><p>25年の夏に白州蒸溜所を見学したときの写真です。ここからは、展示されていたボトルを紹介します。</p><p><a href="'.esc_url(home_url('/hsh-hakushu-visit/')).'">初めての白州蒸溜所見学。写真と一緒に振り返る →</a></p><div class="hsh-photo-grid">';
    foreach($manifest['collections']['whisky-displays']??array() as $key){$a=$manifest['assets'][$key];$out.='<figure class="hsh-photo">'.hsh_photo_image($a,false,'(max-width:600px) calc(100vw - 44px), 360px').hsh_photo_figcaption($a).'</figure>';}
    return $out.'</div></section>';
}
function hsh_growth_updates(){
    $rows=array();foreach(hsh_catalog() as $a){$p=get_page_by_path($a['slug'],OBJECT,'post');if($p&&$p->post_status==='publish')$rows[]=array('post'=>$p,'date'=>get_post_modified_time('Y-m-d',false,$p));}
    $visit=get_page_by_path('hsh-hakushu-visit',OBJECT,'page');if($visit&&$visit->post_status==='publish'&&get_post_meta($visit->ID,'_hsh_bridge_owned',true)==='1')$rows[]=array('post'=>$visit,'date'=>get_post_modified_time('Y-m-d',false,$visit));
    usort($rows,function($a,$b){return strcmp($b['date'],$a['date']);});$out='<section class="hsh-updates"><p>公開中の記事を、WordPressに記録された更新日の新しい順に並べています。</p><ul>';
    foreach($rows as $r)$out.='<li><time datetime="'.esc_attr($r['date']).'">'.esc_html(str_replace('-','/',$r['date'])).'</time> <a href="'.esc_url(get_permalink($r['post'])).'">'.esc_html($r['post']->post_title).'</a></li>';
    return $out.'</ul><p>内容の訂正に関する方針は、<a href="'.esc_url(home_url('/hsh-editorial/')).'">記事・広告について</a>にまとめています。</p></section>';
}
add_shortcode('hsh_manmos_gallery','hsh_growth_gallery');add_shortcode('hsh_whisky_notes','hsh_growth_whisky');add_shortcode('hsh_updates','hsh_growth_updates');
add_action('wp_enqueue_scripts',function(){wp_enqueue_style('hsh-growth',plugin_dir_url(__FILE__).'assets/growth.css',array('hsh-reading'),'1.38.2');if(hsh_reader_managed())wp_enqueue_script('hsh-growth',plugin_dir_url(__FILE__).'assets/growth.js',array(),'1.38.2',true);},110);
add_filter('the_content',function($content){
    if(!is_singular('post')||!in_the_loop()||!is_main_query())return $content;$p=get_post();if(!hsh_owned_blog_post($p))return $content;
    $slug=hsh_growth_next($p->post_name);$next=$slug?get_page_by_path($slug,OBJECT,'post'):null;$summaries=hsh_growth_summaries();
    if($next&&$next->post_status==='publish'&&preg_match_all('~<h2\b[^>]*>~i',$content,$m,PREG_OFFSET_CAPTURE)&&count($m[0])>=4){$nav='<aside class="hsh-context-link"><p>'.esc_html(hsh_reader_roles()[$slug]['related_label']??'こちらもどうぞ').'</p><a href="'.esc_url(get_permalink($next)).'">'.esc_html($next->post_title).'</a><p>'.esc_html($summaries[$slug]??'').'</p></aside>';$content.=$nav;}
    return $content;
},19);
add_filter('the_content',function($content){
    if(!is_singular('post')||!in_the_loop()||!is_main_query()||!hsh_owned_blog_post(get_post()))return $content;
    return $content.'<div class="hsh-share"><button type="button" data-hsh-copy="'.esc_url(get_permalink()).'">記事のURLをコピー</button><button type="button" data-hsh-share hidden>この記事を共有する</button><span role="status" aria-live="polite"></span></div>'.hsh_growth_nav();
},25);
add_action('wp_footer',function(){echo hsh_growth_nav();},25);
/** Replace theme defaults once, without adding conflicting OGP or schema nodes. */
function hsh_growth_metadata($html,$data){
    if(!$data||strpos($html,'</head>')===false)return $html;
    $head=substr($html,0,strpos($html,'</head>'));$tail=substr($html,strpos($html,'</head>'));$seen=array();
    $head=preg_replace_callback('~<meta\b[^>]*>~i',function($m)use($data,&$seen){if(!preg_match('~\b(?:name|property)\s*=\s*([\x22\x27])(.*?)\1~i',$m[0],$key))return $m[0];$k=strtolower($key[2]);if(!isset($data[$k]))return $m[0];if(isset($seen[$k]))return '';$seen[$k]=true;return '<meta '.(strpos($k,'og:')===0?'property':'name').'="'.esc_attr($k).'" content="'.esc_attr($data[$k]).'">';},$head);
    foreach($data as $k=>$value)if(!isset($seen[$k]))$head.='<meta '.(strpos($k,'og:')===0?'property':'name').'="'.esc_attr($k).'" content="'.esc_attr($value).'">'."\n";
    $head=preg_replace_callback('~<script\b[^>]*type=[\x22\x27]application/ld\+json[\x22\x27][^>]*>(.*?)</script>~is',function($m)use($data){$j=json_decode($m[1],true);if(!is_array($j))return $m[0];$walk=function(&$node)use(&$walk,$data){if(!is_array($node))return;$types=(array)($node['@type']??array());if(array_intersect($types,array('Article','BlogPosting','NewsArticle'))){$node['image']=array('@type'=>'ImageObject','url'=>$data['og:image'],'width'=>(int)$data['og:image:width'],'height'=>(int)$data['og:image:height']);$node['description']=$data['description'];if(isset($node['headline'])&&is_string($node['headline']))$node['headline']=html_entity_decode($node['headline'],ENT_QUOTES|ENT_HTML5,'UTF-8');}foreach($node as &$v)if(is_array($v))$walk($v);};$walk($j);return '<script type="application/ld+json">'.wp_json_encode($j,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>';},$head);
    return $head.$tail;
}
function hsh_growth_rendered($html){
    if(strpos($html,'</head>')===false)return $html;
    if(is_singular('post')){$p=get_post();if(!hsh_owned_blog_post($p)){if((int)$p->ID!==8||is_wp_error(hsh_intro_post()))return $html;$a=hsh_photo_manifest()['assets']['manmos-front'];}else $a=hsh_photo_for_slug($p->post_name);$description=$p->post_excerpt;$type='article';$url=get_permalink($p);$title=$p->post_title.' | ひろがる趣味暮らし';}
    elseif(is_page()&&(in_array((int)get_queried_object_id(),array_map('intval',(array)get_option('hsh_edit_pages',array())),true)||hsh_reader_scope())){$p=get_post();$m=hsh_photo_manifest();$key=$p->post_name==='hsh-hakushu-visit'?'hakushu-entrance':($p->post_name==='hsh-whisky-notes'?($m['topics']['whisky']??'manmos-tower'):'manmos-portrait');$key=$m['pages'][$p->post_name]??$key;$a=$m['assets'][$key]??null;$descriptions=array('お問い合わせ'=>'ひろがる趣味暮らしへのお問い合わせ。記事に関するご連絡はこちらからお送りください。','プライバシーポリシー'=>'ひろがる趣味暮らしの個人情報の取り扱いとプライバシーポリシーについて。','hsh-reading-list'=>'この端末で保存した、ひろがる趣味暮らしの記事をまとめて読み返せます。','hsh-hakushu-visit'=>'白州蒸溜所を初めて見学した25年の夏の感想。館内展示や飲み比べの写真と、これから行く前に公式案内で確認したい予約・アクセス情報をまとめます。','hsh-gallery'=>'うちの保護猫まんもすの写真。枕の上で休んだり、爪とぎに座ったり、いろいろな姿を集めました。','hsh-whisky-notes'=>'グレンリベット12年、アルボラリス、グレンファークラス12年、ジムビーム。実際に飲んだときの感想をまとめました。','hsh-updates'=>'ひろがる趣味暮らしの最近の更新。猫・ウイスキー・ITの公開記事を更新日順に紹介します。');$description=$descriptions[rawurldecode($p->post_name)]??wp_trim_words(wp_strip_all_tags($p->post_content),70);$title=$p->post_title.' | ひろがる趣味暮らし';$type='website';$url=get_permalink($p);}
    elseif(is_front_page()||hsh_current_topic()){$m=hsh_photo_manifest();$key=hsh_current_topic()?($m['topics'][hsh_current_topic()]??'manmos-tower'):($m['home']??'manmos-tower');$a=$m['assets'][$key]??null;$description=hsh_current_topic()?hsh_topic_details()[hsh_current_topic()][4]:'保護猫まんもすとの暮らし、飲んだウイスキーの感想、フリーランスのデータ加工の仕事を書いています。';$type='website';$url=hsh_current_topic()?get_pagenum_link(max(1,(int)get_query_var('paged'))):home_url('/');$title=hsh_current_topic()?hsh_topics()[hsh_current_topic()][0].' | ひろがる趣味暮らし':'ひろがる趣味暮らし｜猫・ウイスキー・ITの仕事';}
    else return $html;
    if(!$a)return $html;$image=plugin_dir_url(__FILE__).'assets/photos/'.$a['file'];
    return hsh_growth_metadata($html,array('description'=>$description,'og:type'=>$type,'og:title'=>$title,'og:description'=>$description,'og:url'=>$url,'og:image'=>$image,'og:image:secure_url'=>$image,'og:image:type'=>'image/webp','og:image:width'=>(string)$a['width'],'og:image:height'=>(string)$a['height'],'og:image:alt'=>$a['alt'],'twitter:card'=>'summary_large_image','twitter:image'=>$image,'twitter:image:alt'=>$a['alt'],'twitter:title'=>$title,'twitter:description'=>$description));
}
