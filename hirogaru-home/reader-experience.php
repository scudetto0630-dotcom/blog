<?php
if (!defined('ABSPATH')) exit;

/** All public reader data is derived from already published, managed content. */
function hsh_reader_catalog() {
    $rows = array();
    foreach (hsh_catalog() as $a) {
        $p = get_page_by_path($a['slug'], OBJECT, 'post');
        if (!$p || $p->post_status !== 'publish') continue;
        $photo = hsh_photo_for_slug($a['slug']);
        $rows[] = array('id'=>$a['slug'], 'title'=>$p->post_title, 'url'=>get_permalink($p), 'topic'=>$a['topic'], 'summary'=>hsh_growth_summaries()[$a['slug']]??$p->post_excerpt,'minutes'=>function_exists('hsh_studio_minutes')?hsh_studio_minutes($p->post_content):1, 'image'=>$photo?plugin_dir_url(__FILE__).'assets/photos/'.$photo['file']:'');
    }
    $known=array_column($rows,'id');
    foreach(get_posts(array('post_type'=>'post','post_status'=>'publish','numberposts'=>500,'orderby'=>'date','order'=>'DESC')) as $p){
        if($p->post_status!=='publish'||(int)$p->ID===8||in_array($p->post_name,$known,true))continue;
        $topic='';foreach(array_keys(hsh_topics()) as $key)if(has_category($key,$p)){$topic=$key;break;}if(!$topic)continue;
        $rows[]=array('id'=>$p->post_name,'title'=>$p->post_title,'url'=>get_permalink($p),'topic'=>$topic,'summary'=>mb_substr(wp_strip_all_tags($p->post_excerpt??''),0,100,'UTF-8'),'image'=>'');
    }
    $p = get_page_by_path('hsh-hakushu-visit', OBJECT, 'page');
    if ($p && $p->post_status === 'publish' && get_post_meta($p->ID, '_hsh_bridge_owned', true) === '1') {
        $rows[] = array('id'=>'hsh-hakushu-visit', 'title'=>$p->post_title, 'url'=>get_permalink($p), 'topic'=>'whisky', 'summary'=>'25年の夏、初めての白州蒸溜所見学と飲み比べの写真。', 'image'=>plugin_dir_url(__FILE__).'assets/photos/hakushu-entrance.webp');
    }
    $intro=hsh_intro_post();if(!is_wp_error($intro))$rows[]=array('id'=>$intro->post_name,'title'=>$intro->post_title,'url'=>get_permalink($intro),'topic'=>'','summary'=>'まんもす、ウイスキー、仕事。このブログで書いていること。','image'=>'');
    return $rows;
}
function hsh_reader_roles(){static $data=null;if($data===null)$data=json_decode(file_get_contents(__DIR__.'/reader-content-map.json'),true)?:array();return $data;}
function hsh_reader_starters(){return array('cats'=>'manmos-cat-life','whisky'=>'glenlivet-12-first-bottles','it-freelance'=>'it-data-work-flow');}
function hsh_reader_manmos_series(){
    $m=hsh_photo_manifest();$out='<section class="hsh-manmos-series"><h2>写真で読む、まんもすの日常</h2><div>';
    $items=array(array('manmos-zip-pillow','枕の上でのんびり','/hsh-gallery/?hsh_photo=sleep'),array('manmos-zip-scratcher','爪とぎの上が好き','/hsh-gallery/?hsh_photo=scratcher'),array('manmos-portrait','ブラッシングは毎日','/manmos-cat-life/#manmos-cat-life-detail-5'));
    foreach($items as $item)$out.='<a href="'.esc_url(home_url($item[2])).'">'.hsh_photo_image($m['assets'][$item[0]],false,'104px').'<span>'.esc_html($item[1]).' <span aria-hidden="true">→</span></span></a>';
    return $out.'</div></section>';
}
function hsh_reader_current_topic() {
    $topic = hsh_current_topic();
    if ($topic) return $topic;
    if (!function_exists('get_queried_object_id')) return '';
    $p = get_post(get_queried_object_id());
    if (!$p) return '';
    foreach (hsh_catalog() as $a) if ($p->post_name === $a['slug']) return $a['topic'];
    if($p->post_type==='post')foreach(array_keys(hsh_topics()) as $key)if(has_category($key,$p))return $key;
    if (in_array($p->post_name, array('hsh-whisky-notes','hsh-hakushu-visit'), true)) return 'whisky';
    return $p->post_name === 'hsh-gallery' ? 'cats' : '';
}
function hsh_reader_header() {
    $current = hsh_reader_current_topic();
    $out = '<header class="hsh-header hsh-reader-header"><a class="hsh-brand" href="'.esc_url(home_url('/')).'"><img src="'.esc_url(plugin_dir_url(__FILE__).'assets/brand-mark.svg').'" width="38" height="38" alt=""><span>ひろがる<span>趣味暮らし</span></span></a><nav aria-label="主要カテゴリー">';
    foreach (hsh_topics() as $key=>$topic) $out .= '<a'.($current===$key?' aria-current="true"':'').' href="'.esc_url(hsh_category_url($key)).'">'.esc_html($topic[0]).'</a>';
    return $out.'<a class="hsh-saved-link" aria-label="保存した記事" href="'.esc_url(home_url('/hsh-reading-list/')).'"><span class="hsh-saved-label">保存した記事</span><span class="hsh-saved-short" aria-hidden="true">保存</span></a></nav></header>';
}
function hsh_reader_managed() {
    if (!function_exists('get_queried_object_id')) return false;
    $p = get_post(get_queried_object_id());
    if(!$p||$p->post_status!=='publish')return false;
    if((int)$p->ID===8&&!is_wp_error(hsh_intro_post()))return true;
    if(hsh_owned_blog_post($p)||($p->post_name==='hsh-hakushu-visit'&&get_post_meta($p->ID,'_hsh_bridge_owned',true)==='1'))return true;
    if($p->post_type==='post'&&(int)$p->ID!==8)foreach(array_keys(hsh_topics()) as $key)if(has_category($key,$p))return true;
    return false;
}
function hsh_reader_scope() {
    if (is_admin()) return false;
    if (is_front_page() || hsh_current_topic() || is_search() || is_404() || hsh_reader_managed()) return true;
    $p=get_post(get_queried_object_id());
    if($p&&$p->post_status==='publish'&&((int)$p->ID===8||in_array(rawurldecode($p->post_name),array('お問い合わせ','プライバシーポリシー'),true)))return true;
    return is_page() && in_array((int)get_queried_object_id(), array_map('intval',(array)get_option('hsh_edit_pages',array())),true);
}
add_filter('body_class', function($classes) { if (hsh_reader_scope()) $classes[]='hsh-reader-site'; return $classes; });
add_action('wp_body_open', function() {
    if (hsh_reader_scope() && !is_front_page() && !hsh_current_topic() && !is_search() && !is_404()) {
        echo '<a class="hsh-skip" href="#main">本文へ移動</a>'.hsh_reader_header();
    }
});
add_action('wp_enqueue_scripts', function() {
    if (!hsh_reader_scope()) return;
    wp_enqueue_style('hsh-home', plugin_dir_url(__FILE__).'assets/home.css', array(), '1.38.2');
    wp_enqueue_style('hsh-reader', plugin_dir_url(__FILE__).'assets/reader.css', array('hsh-growth'), '1.38.2');
    wp_enqueue_script('hsh-reader', plugin_dir_url(__FILE__).'assets/reader.js', array(), '1.38.9', true);

}, 120);
function hsh_reader_bootstrap(){return '<script>document.documentElement.classList.add("hsh-reader-js");</script>';}
add_action('wp_head',function(){if(hsh_reader_scope())echo hsh_reader_bootstrap();},0);
add_action('wp_head', function() {
    if (!hsh_reader_scope()) return;
    echo '<link rel="icon" type="image/svg+xml" href="'.esc_url(plugin_dir_url(__FILE__).'assets/brand-mark.svg').'">';
    echo '<link rel="icon" type="image/png" sizes="32x32" href="'.esc_url(plugin_dir_url(__FILE__).'assets/brand-32.png').'">';
    echo '<link rel="apple-touch-icon" sizes="180x180" href="'.esc_url(plugin_dir_url(__FILE__).'assets/brand-180.png').'">';
}, 99);
add_action('wp_footer', function() {
    if (!hsh_reader_scope()) return;
    $p = hsh_reader_managed() ? get_post(get_queried_object_id()) : null;
    $data = array('articles'=>hsh_reader_catalog(), 'current'=>$p?$p->post_name:null, 'savedUrl'=>home_url('/hsh-reading-list/'));
    echo '<script id="hsh-reader-data" type="application/json">'.wp_json_encode($data, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>';
}, 19);
add_filter('the_content', function($content) {
    if (!in_the_loop() || !is_main_query() || !hsh_reader_managed()) return $content;
    return hsh_reader_tools().$content;
}, 26);
function hsh_reader_tools(){return '<div class="hsh-reader-tools" id="hsh-reading-content"><button type="button" data-hsh-bookmark aria-pressed="false" hidden>この記事を保存</button><button type="button" data-hsh-resume aria-label="前に読んだところから続けて読む" hidden>続きから読む</button><span class="hsh-reader-status" role="status" aria-live="polite"></span></div>';}
function hsh_reader_saved_page() {
    return '<section class="hsh-saved-page"><p>この端末で保存した記事をまとめています。ログインは不要です。</p><p class="hsh-device-note">別の端末とは同期しません。ブラウザーの保存データを消すと、この一覧も消えます。</p><div data-hsh-saved-list><p>保存した記事は、この端末のブラウザーで表示します。</p></div><button type="button" data-hsh-clear-saved hidden>保存した記事をすべて外す</button><button type="button" data-hsh-clear-places>読んだ位置の記録を消す</button><p role="status" aria-live="polite" data-hsh-saved-status></p><noscript><p>一覧の表示にはJavaScriptを有効にしてください。<a href="'.esc_url(home_url('/')).'">トップから記事を探す</a></p></noscript></section>';
}
add_shortcode('hsh_reading_list','hsh_reader_saved_page');

function hsh_reader_gallery_choice(){return isset($_GET['hsh_photo'])&&is_string($_GET['hsh_photo'])&&in_array($_GET['hsh_photo'],array('all','face','relax','sleep','scratcher','paws','whole'),true)?$_GET['hsh_photo']:'all';}
function hsh_reader_gallery_matches($asset){$choice=hsh_reader_gallery_choice();return $choice==='all'||in_array($choice,$asset['tags']??array('relax'),true);}
function hsh_reader_gallery_filters() {
    $out='<div class="hsh-gallery-filters" role="group" aria-label="まんもすの写真を絞り込む">';
    foreach(array('all'=>'すべて','face'=>'顔・表情','relax'=>'くつろぐ','sleep'=>'寝姿','scratcher'=>'爪とぎの上','paws'=>'肉球','whole'=>'全身') as $key=>$label) $out.='<button type="button" data-hsh-photo-filter="'.esc_attr($key).'" aria-pressed="'.($key===hsh_reader_gallery_choice()?'true':'false').'">'.esc_html($label).'</button>';
    $count=0;foreach(hsh_photo_manifest()['assets'] as $a)if($a['kind']==='provided'&&$a['subject']==='manmos'&&hsh_reader_gallery_matches($a))$count++;
    return $out.'</div><p class="hsh-gallery-count" role="status" aria-live="polite">'.(int)$count.'枚の写真を表示しています</p>';
}
function hsh_reader_search_excerpt($content,$query,$length=135) {
    $query=hsh_search_alias($query);
    $plain = trim(preg_replace('/\s+/u',' ',wp_strip_all_tags(strip_shortcodes($content))));
    $terms = preg_split('/\s+/u',trim((string)$query)); $offset=false;
    foreach($terms as $term) { if($term==='')continue; $hit=mb_stripos($plain,$term,0,'UTF-8'); if($hit!==false&&($offset===false||$hit<$offset))$offset=$hit; }
    $start=$offset===false?0:max(0,$offset-35);
    return ($start?'…':'').mb_substr($plain,$start,$length,'UTF-8').(mb_strlen($plain,'UTF-8')>$start+$length?'…':'');
}
function hsh_reader_search_suggestions($query) {
    $q=mb_strtolower(trim((string)$query),'UTF-8');
    $groups=array(array('猫','まんもす','マンモス','ねこ','キャリー','おもちゃ'),array('ウイスキー','ウィスキー','白州','アルボラリス','ハイボール','グレン'),array('AI','ai','IT','it','CSV','csv','Excel','データ','フリーランス'));
    foreach($groups as $i=>$words)foreach($words as $word)if($q!==''&&mb_stripos($q,$word,0,'UTF-8')!==false)return array_slice(array_filter($words,function($v)use($q){return mb_strtolower($v,'UTF-8')!==$q;}),0,4);
    return array('まんもす','ハイボール','データ抽出');
}
function hsh_reader_search_ids($topic=''){
    $ids=array();foreach(hsh_reader_catalog() as $a){if($topic!==''&&$a['topic']!==$topic)continue;$p=get_page_by_path($a['id'],OBJECT,'post');if($p&&$p->post_status==='publish')$ids[]=(int)$p->ID;}
    foreach(array('hsh-gallery'=>'cats','hsh-whisky-notes'=>'whisky','hsh-hakushu-visit'=>'whisky','hsh-about'=>'') as $slug=>$category){if($topic!==''&&$category!==$topic)continue;$p=get_page_by_path($slug,OBJECT,'page');if($p&&$p->post_status==='publish'&&get_post_meta($p->ID,'_hsh_bridge_owned',true)==='1')$ids[]=(int)$p->ID;}
    return $ids?array_values(array_unique($ids)):array(0);
}
add_action('pre_get_posts',function($q){if(is_admin()||!$q->is_main_query()||!$q->is_search())return;$topic=hsh_search_topic(isset($_GET['hsh_topic'])?wp_unslash($_GET['hsh_topic']):'');$q->set('post_type',array('post','page'));$q->set('post_status','publish');$q->set('category_name','');$q->set('post__in',hsh_reader_search_ids($topic));},20);
/** Only thin utility archives are noindex; curated categories remain indexable. */
function hsh_reader_archive_noindex($type,$count=0,$description='') {
    return in_array($type,array('date','author'),true) || ($type==='tag' && ((int)$count<3 || mb_strlen(trim(wp_strip_all_tags($description)),'UTF-8')<40));
}
function hsh_reader_should_noindex() {
    if (is_search() || is_date() || is_author()) return true;
    if (is_tag()) { $t=get_queried_object(); return hsh_reader_archive_noindex('tag',$t->count??0,$t->description??''); }
    if(is_page()&&get_post_field('post_name',get_queried_object_id())==='hsh-reading-list')return true;
    return isset($_GET['hsh_photo']) && is_page() && get_post_field('post_name',get_queried_object_id())==='hsh-gallery';
}
function hsh_reader_sitemap_entries($entries) {
    $saved=rtrim(home_url('/hsh-reading-list/'),'/');
    return array_values(array_filter($entries,function($entry)use($saved){return !isset($entry['loc'])||rtrim($entry['loc'],'/')!==$saved;}));
}
add_filter('wp_sitemaps_posts_query_args',function($args,$type){if($type==='page'){$owned=get_option('hsh_edit_pages',array());$id=(int)($owned['reading-list']??0);if(!$id){$p=get_page_by_path('hsh-reading-list',OBJECT,'page');$id=$p?(int)$p->ID:0;}if($id)$args['post__not_in']=array_values(array_unique(array_merge($args['post__not_in']??array(),array($id))));}return $args;},999,2);
// Preserve the provider's entries and extension fields while filtering utility URLs.
// The guard lets the inner provider call finish its normal query without recursion.
add_filter('wp_sitemaps_posts_pre_url_list',function($list,$type,$page){
    if($type!=='page')return $list;
    static $building=false;if($building)return $list;
    if($list===null){$building=true;try{$provider=wp_sitemaps_get_server()->registry->get_provider('posts');$list=$provider?$provider->get_url_list($page,$type):null;}finally{$building=false;}}
    return is_array($list)?hsh_reader_sitemap_entries($list):$list;
},PHP_INT_MAX,3);
add_filter('wp_robots',function($robots) { if(hsh_reader_should_noindex()) { $robots['noindex']=true;unset($robots['index']); } return $robots; },30);
function hsh_reader_rendered($html) {
    if(strpos($html,'</head>')===false)return $html;
    if(hsh_reader_should_noindex()) { $end=strpos($html,'</head>'); $html=hsh_search_robots(substr($html,0,$end)).substr($html,$end); }
    return hsh_reader_responsive_html($html);
}
function hsh_reader_responsive_html($html){
    $base=plugin_dir_url(__FILE__).'assets/photos/';$lookup=array();foreach(hsh_photo_manifest()['assets'] as $a)if(!empty($a['variants']))$lookup[$base.$a['file']]=$a;
    return preg_replace_callback('~<img\b[^>]*>~i',function($m)use($lookup,$base){$tag=$m[0];if(preg_match('/\bsrcset\s*=/i',$tag)||!preg_match('~\bsrc\s*=\s*([\x22\x27])(.*?)\1~i',$tag,$match))return $tag;$url=html_entity_decode($match[2],ENT_QUOTES,'UTF-8');if(!isset($lookup[$url]))return $tag;$a=$lookup[$url];$srcset=array();foreach($a['variants'] as $v)if(preg_match('/^[a-z0-9-]+\.webp$/D',$v['file']))$srcset[]=$base.$v['file'].' '.(int)$v['width'].'w';$srcset[]=$url.' '.(int)$a['width'].'w';return preg_replace('~/?>$~','',$tag).' srcset="'.esc_attr(implode(', ',$srcset)).'" sizes="(max-width:760px) calc(100vw - 44px), 740px">';},$html);
}

// Final visual layer follows the functional reading and accessibility styles.
add_action('wp_enqueue_scripts',function(){if(hsh_reader_scope())wp_enqueue_style('hsh-palette',plugin_dir_url(__FILE__).'assets/palette.css',array('hsh-quality'),'1.39.1');},190);
