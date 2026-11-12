<?php
if(!defined('ABSPATH'))exit;
/** Public additions only use packaged facts and approved packaged photographs. */
function hsh_growth_summaries(){return array(
'before-welcoming-cat'=>'猫を迎える前に用意する物と、部屋を片付けるときの注意点をまとめています。',
'manmos-cat-life'=>'まんもすとの出会いや、甘えるときの様子、好きなおもちゃを写真と一緒に紹介しています。',
'cat-teaser-small-parts'=>'100均の羽付きねこじゃらしを愛用中。小さな部品の外れやすさと、遊んだ後の片付けについて書いています。',
'cat-vet-routine'=>'通院の持ち物と、診察で伝えたい猫の様子を紹介しています。',
'cat-carrier-checklist'=>'5.5kgのまんもすを入れるとリュックキャリーがへこみ、古いキャリーを使い直した経緯を書いています。',
'glenlivet-12-first-bottles'=>'グラス付きで買ったグレンリベット12年の、飲みやすさと好きな香りについて書いています。',
'single-malt-label-guide'=>'初めてウイスキーを選ぶときに見たい商品名・年数・容量・度数と、値段の比べ方を紹介しています。',
'sherry-cask-notes'=>'グレンファークラス12年のぶどうパンのような香りと、何度も買う理由を書いています。',
'highball-measure-and-adjust'=>'ハイボールの味や炭酸が物足りないとき、分量や作り方をどう調整するかを紹介しています。',
'it-data-work-flow'=>'データ加工の仕事内容と、AIを使って楽になったことを書いています。',
'starting-it-without-specialty'=>'ITに興味を持ってから、自分に合う仕事に出会うまでの約1年を振り返っています。',
'ai-data-extraction-checks'=>'架空の表を使って、AIの抽出結果に抜けや誤りがないかを確認する方法を紹介しています。',
'csv-import-seven-checks'=>'CSVの文字コードや先頭の0、空欄など、取り込む前に確認する項目をまとめています。');}
function hsh_growth_next($slug){$pairs=array(
'before-welcoming-cat'=>'manmos-cat-life','manmos-cat-life'=>'cat-carrier-checklist','cat-carrier-checklist'=>'cat-vet-routine','cat-vet-routine'=>'cat-carrier-checklist','cat-teaser-small-parts'=>'manmos-cat-life',
'glenlivet-12-first-bottles'=>'sherry-cask-notes','sherry-cask-notes'=>'single-malt-label-guide','single-malt-label-guide'=>'glenlivet-12-first-bottles','highball-measure-and-adjust'=>'glenlivet-12-first-bottles',
'starting-it-without-specialty'=>'it-data-work-flow','it-data-work-flow'=>'ai-data-extraction-checks','ai-data-extraction-checks'=>'csv-import-seven-checks','csv-import-seven-checks'=>'ai-data-extraction-checks');return $pairs[$slug]??'';}
function hsh_growth_nav(){return '<nav class="hsh-explore-nav" aria-label="写真と記事を探す"><a href="'.esc_url(home_url('/hsh-gallery/')).'">まんもすの写真</a><a href="'.esc_url(home_url('/hsh-whisky-notes/')).'">飲んだウイスキーのメモ</a><a href="'.esc_url(home_url('/hsh-updates/')).'">最近の更新</a></nav>';}
add_action('init',function(){add_option('hsh_all_articles_counted_since',wp_date('Y_m_d'),'',false);});
function hsh_growth_popular(){
    $totals=array();$start=max(wp_date('Y_m_d',time()-29*DAY_IN_SECONDS),(string)get_option('hsh_all_articles_counted_since',wp_date('Y_m_d')));$end=wp_date('Y_m_d');
    foreach((array)get_option('hsh_request_counts',array()) as $day=>$rows)if($day>=$start&&$day<=$end)foreach($rows as $slug=>$count)if(isset(hsh_growth_summaries()[$slug]))$totals[$slug]=($totals[$slug]??0)+max(0,(int)$count);
    $totals=array_filter($totals,function($n){return $n>=10;});if(count($totals)<3||array_sum($totals)<50)return '';arsort($totals);$out='<section class="hsh-popular"><h2>最近、読まれている記事</h2><p>直近30日のおおよその閲覧状況を参考にしています。</p><ul>';
    foreach(array_slice($totals,0,3,true) as $slug=>$n){$p=get_page_by_path($slug,OBJECT,'post');if($p&&$p->post_status==='publish')$out.='<li><a href="'.esc_url(get_permalink($p)).'">'.esc_html($p->post_title).'</a></li>';}
    return $out.'</ul></section>';
}
function hsh_growth_gallery(){
    $out='<section class="hsh-gallery-page"><p>爪とぎの上や枕の上でくつろぐ、まんもすの写真を集めました。</p>'.hsh_reader_gallery_filters().'<div class="hsh-photo-grid">';
    foreach(hsh_photo_manifest()['assets'] as $a)if($a['kind']==='provided'&&$a['subject']==='manmos')$out.='<figure class="hsh-photo"'.(hsh_reader_gallery_matches($a)?'':' hidden').' data-photo-tags="'.esc_attr(implode(' ',$a['tags']??array('relax'))).'">'.hsh_photo_image($a,false,'(max-width:600px) calc(100vw - 44px), 360px').hsh_photo_figcaption(array_merge($a,array('caption'=>$a['alt']))).'</figure>';
    return $out.'</div><p><a href="'.esc_url(home_url('/manmos-cat-life/')).'">まんもすとの暮らしを読む →</a></p></section>';
}
function hsh_growth_tastes(){return array(
array('name'=>'キリンウイスキー 陸','image'=>'riku-generated','note'=>'初めて買ったウイスキー。当時はまだウイスキーに慣れていなくて、おいしいと思えず、コーラで割って飲んでいた。','reason'=>'','slug'=>''),
array('name'=>'バスカー アイリッシュウイスキー','image'=>'busker-green-generated','note'=>'2本目に買ったバスカー緑は、おいしいと感じた。炭酸とコーラで交互に割って飲むうちに、ウイスキーの味にも慣れていった。','reason'=>'','slug'=>''),
array('name'=>'グレンリベット12年','note'=>'3本目に買って、最初はハイボールで飲んだ。飲みやすくて、それまで買ったウイスキーより格段にうまいと感じた。花のような香りも好き。','reason'=>'YouTubeで気になっていたところ、スーパーでグラス付きのセットを見つけた。知っていた金額より少し安く、迷わず購入。','slug'=>'glenlivet-12-first-bottles'),
array('name'=>'グレングラント アルボラリス カスクストレングス','image'=>'arboralis-generated','note'=>'甘くて飲みやすかった。オーケーで買ったときは2,200円で、好きなウイスキーの一つだ。','reason'=>'YouTubeで飲んでいるのを見て気になった。','slug'=>''),
array('name'=>'グレンファークラス12年','image'=>'article-sherry','note'=>'ぶどうパンに顔をうずめたみたいな香りが、とにかく好き。まろやかでうまいけど、ストレートではかすかにアルコールの刺激も感じる。','reason'=>'ぶどうパンが大好きで、YouTubeで聞いた香りの例えにひかれた。特別な日に飲みたくて、何度も買っている。','slug'=>'sherry-cask-notes'),
array('name'=>'ジムビーム','image'=>'jim-beam-generated','note'=>'自分には、セメダインのような接着剤の香りに感じられた。アルコールの刺激も強く感じて気分が悪くなり、口に合わなかった。','reason'=>'','slug'=>''));}
function hsh_growth_whisky(){
    $out='<section class="hsh-taste-index"><p>最初に買ったのは陸、次がバスカー アイリッシュウイスキー（バスカー緑）、3本目がグレンリベット12年。飲んでみて好きだったものも、口に合わなかったものも、自分の感想を残している。</p><p>普段はその日の気分で、飲みたいものを選ぶ。ハイボールはウイスキーを30ml入れて、炭酸はできるだけ多めに。グレンファークラスはストレートかハーフロックで、香りを楽しむのが好きだ。</p>';
    foreach(hsh_growth_tastes() as $r){$arboralis=strpos($r['name'],'アルボラリス')!==false;$out.='<article class="hsh-taste-card"'.($arboralis?' id="arboralis-taste"':'').'><h2>'.esc_html($arboralis?'アルボラリスは甘い？カスクストレングスを飲んだ感想':$r['name']).'</h2>';if(!empty($r['image'])){$asset=hsh_photo_manifest()['assets'][$r['image']];$out.='<figure class="hsh-photo hsh-taste-photo">'.hsh_photo_image($asset,false).'</figure>';}$out.='<p>'.esc_html($r['note']).'</p>';if($r['reason']!=='')$out.='<dl><dt>選んだ理由</dt><dd>'.esc_html($r['reason']).'</dd></dl>';if($r['name']==='バスカー アイリッシュウイスキー')$out.='<p><a href="https://whisk-e.co.jp/products/buskerirish/">バスカー アイリッシュウイスキーの公式商品案内</a></p>';if($r['slug'])$out.='<a href="'.esc_url(home_url('/'.$r['slug'].'/')).'">詳しい感想を読む →</a>';$out.='</article>';}
    $out.='<section class="hsh-next-bottle"><h2>次に気になっているのは、オーチャードハウス</h2><p>コンパスボックスのオーチャードハウスは、フルーツ感があると聞いて気になっている。ローソンで売っていた缶ハイボールは飲んでみたけど、あまり口に合わなかった。それでもボトルの方は飲んでみたい。まだ買っていないので、ボトルで飲むのを次の楽しみにしている。</p></section>';
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
    usort($rows,function($a,$b){return strcmp($b['date'],$a['date']);});$out='<section class="hsh-updates"><p>最近更新した記事を、新しい順に並べています。</p><ul>';
    foreach($rows as $r)$out.='<li><time datetime="'.esc_attr($r['date']).'">'.esc_html(str_replace('-','/',$r['date'])).'</time> <a href="'.esc_url(get_permalink($r['post'])).'">'.esc_html($r['post']->post_title).'</a></li>';
    return $out.'</ul></section>';
}
add_shortcode('hsh_manmos_gallery','hsh_growth_gallery');add_shortcode('hsh_whisky_notes','hsh_growth_whisky');add_shortcode('hsh_updates','hsh_growth_updates');
add_action('wp_enqueue_scripts',function(){wp_enqueue_style('hsh-growth',plugin_dir_url(__FILE__).'assets/growth.css',array('hsh-reading'),'1.38.2');if(hsh_reader_managed()||is_front_page())wp_enqueue_script('hsh-growth',plugin_dir_url(__FILE__).'assets/growth.js',array(),'1.40.0',true);},110);
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
    elseif(is_page()&&(in_array((int)get_queried_object_id(),array_map('intval',(array)get_option('hsh_edit_pages',array())),true)||hsh_reader_scope())){$p=get_post();$m=hsh_photo_manifest();$key=$p->post_name==='hsh-hakushu-visit'?'hakushu-entrance':($p->post_name==='hsh-whisky-notes'?($m['topics']['whisky']??'manmos-tower'):'manmos-portrait');$key=$m['pages'][$p->post_name]??$key;$a=$m['assets'][$key]??null;$descriptions=array('お問い合わせ'=>'ひろがる趣味暮らしへのお問い合わせ。記事に関するご連絡はこちらからお送りください。','プライバシーポリシー'=>'ひろがる趣味暮らしの個人情報の取り扱いとプライバシーポリシーについて。','hsh-reading-list'=>'この端末で保存した、ひろがる趣味暮らしの記事をまとめて読み返せます。','hsh-hakushu-visit'=>'白州蒸溜所を初めて見学した25年の夏の感想。館内展示や飲み比べの写真と、これから行く前に公式案内で確認したい予約・アクセス情報をまとめます。','hsh-gallery'=>'うちの保護猫まんもすの写真。枕の上で休んだり、爪とぎに座ったり、いろいろな姿を集めました。','hsh-whisky-notes'=>'陸、バスカー アイリッシュウイスキー、グレンリベット12年、アルボラリス、グレンファークラス12年、ジムビーム。実際に飲んだときの感想をまとめました。','hsh-updates'=>'ひろがる趣味暮らしの最近の更新。猫・ウイスキー・ITの公開記事を更新日順に紹介します。');$description=$descriptions[rawurldecode($p->post_name)]??wp_trim_words(wp_strip_all_tags($p->post_content),70);$title=$p->post_title.' | ひろがる趣味暮らし';$type='website';$url=get_permalink($p);}
    elseif(is_front_page()||hsh_current_topic()){$m=hsh_photo_manifest();$key=hsh_current_topic()?($m['topics'][hsh_current_topic()]??'manmos-tower'):($m['home']??'manmos-tower');$a=$m['assets'][$key]??null;$description=hsh_current_topic()?hsh_topic_details()[hsh_current_topic()][4]:'保護猫まんもすとの暮らし、飲んだウイスキーの感想、フリーランスのデータ加工の仕事を書いています。';$type='website';$url=hsh_current_topic()?get_pagenum_link(max(1,(int)get_query_var('paged'))):home_url('/');$title=hsh_current_topic()?hsh_topics()[hsh_current_topic()][0].' | ひろがる趣味暮らし':'ひろがる趣味暮らし｜猫・ウイスキー・ITの仕事';}
    else return $html;
    if(!$a)return $html;$image=plugin_dir_url(__FILE__).'assets/photos/'.$a['file'];
    return hsh_growth_metadata($html,array('description'=>$description,'og:type'=>$type,'og:title'=>$title,'og:description'=>$description,'og:url'=>$url,'og:image'=>$image,'og:image:secure_url'=>$image,'og:image:type'=>'image/webp','og:image:width'=>(string)$a['width'],'og:image:height'=>(string)$a['height'],'og:image:alt'=>$a['alt'],'twitter:card'=>'summary_large_image','twitter:image'=>$image,'twitter:image:alt'=>$a['alt'],'twitter:title'=>$title,'twitter:description'=>$description));
}

/** Consolidate existing recommendation blocks; never rewrite the article itself. */
function hsh_growth_compact_followups($content) {
    $pattern='~<(nav|section|aside)\b[^>]*\bclass="(?:[^"]*\s)?(?:hsh-next-read|hsh-related|hsh-context-link|hsh-product-related)(?:\s[^"]*)?"[^>]*>.*?</\1>~si';
    $origin=parse_url(home_url('/'),PHP_URL_HOST);
    $blocks=array();$links=array();
    preg_match_all($pattern,$content,$matches,PREG_SET_ORDER);
    foreach($matches as $match){
        $block=$match[0];
        // Only merge the known text-only link blocks, preserving richer content intact.
        if(preg_match('~<(?:img|figure|table|form|blockquote|script)\b|\bid\s*=~i',$block))continue;
        preg_match_all('~<a\b[^>]*href="([^"]+)"[^>]*>(.*?)</a>~si',$block,$anchors,PREG_SET_ORDER);
        if(!$anchors)continue;
        $accepted=array();
        foreach($anchors as $a){
            $url=html_entity_decode($a[1],ENT_QUOTES,'UTF-8');
            if(parse_url($url,PHP_URL_HOST)!==$origin||!in_array(parse_url($url,PHP_URL_SCHEME),array('http','https'),true)){$accepted=array();break;}
            $accepted[$url]=trim(wp_strip_all_tags($a[2]));
        }
        if(!$accepted)continue;
        $blocks[]=$block;
        foreach($accepted as $url=>$label)if(!isset($links[$url]))$links[$url]=$label;
    }
    if(count($blocks)<2)return $content;
    $out='<nav class="hsh-related hsh-followups" aria-label="あわせて読みたい記事"><h2>あわせて読みたい記事</h2><ul>';
    foreach($links as $url=>$label){
        $slug=rawurldecode(basename(rtrim((string)parse_url($url,PHP_URL_PATH),'/')));
        $post=get_page_by_path($slug,OBJECT,'post');
        if($post&&$post->post_status==='publish')$label=get_the_title($post);
        $out.='<li><a href="'.esc_url($url).'">'.esc_html($label).' <span aria-hidden="true">→</span></a></li>';
    }
    $out.='</ul></nav>';
    // Keep the first block's position, ahead of the profile and article tools.
    $first=true;
    return preg_replace_callback($pattern,function($m)use($blocks,$out,&$first){
        if(!in_array($m[0],$blocks,true))return $m[0];
        if($first){$first=false;return $out;}
        return '';
    },$content);
}
add_filter('the_content',function($content){
    if(!is_singular('post')||!in_the_loop()||!is_main_query()||!hsh_owned_blog_post(get_post()))return $content;
    return hsh_growth_compact_followups($content);
},29);
