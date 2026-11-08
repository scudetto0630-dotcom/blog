<?php
if(!defined('ABSPATH'))exit;
/** Reader improvements; all product facts come from the packaged, sourced articles. */
function hsh_brushup_type($slug){
    foreach(hsh_affiliate_articles() as $a)if($a['slug']===$slug)return 'product';
    return in_array($slug,array('manmos-cat-life','glenlivet-12-first-bottles','sherry-cask-notes','it-data-work-flow','starting-it-without-specialty','hsh-hakushu-visit'),true)?'story':'guide';
}
function hsh_brushup_types(){return array('all'=>'すべて','story'=>'体験談','guide'=>'選び方・手順','product'=>'商品紹介');}
function hsh_brushup_choice($value){return is_string($value)&&isset(hsh_brushup_types()[$value])?$value:'all';}
function hsh_brushup_filters(){
    $choice=hsh_brushup_choice(isset($_GET['hsh_kind'])?wp_unslash($_GET['hsh_kind']):'');$out='<nav class="hsh-kind-filters" aria-label="記事の種類"><span>読みたい記事を選ぶ</span>';
    foreach(hsh_brushup_types() as $key=>$label)$out.='<a data-hsh-kind="'.esc_attr($key).'" href="'.esc_url(add_query_arg(array('hsh_kind'=>$key,'hsh_order'=>hsh_quality_order(isset($_GET['hsh_order'])?wp_unslash($_GET['hsh_order']):'')),hsh_category_url(hsh_current_topic()))).'"'.($key===$choice?' aria-current="true"':'').'>'.esc_html($label).'</a>';
    return $out.'</nav>';
}
add_action('pre_get_posts',function($q){
    if(is_admin()||!$q->is_main_query()||!$q->is_category())return;$choice=hsh_brushup_choice(isset($_GET['hsh_kind'])?wp_unslash($_GET['hsh_kind']):'');if($choice==='all')return;
    $ids=array();foreach(hsh_catalog() as $a)if(hsh_brushup_type($a['slug'])===$choice){$p=get_page_by_path($a['slug'],OBJECT,'post');if($p&&$p->post_status==='publish')$ids[]=(int)$p->ID;}$q->set('post__in',$ids?:array(0));
},30);
add_filter('wp_robots',function($r){if(isset($_GET['hsh_kind'])&&hsh_current_topic()){$r['noindex']=true;unset($r['index']);}return $r;},40);
function hsh_brushup_card_details($post){
    $topic=hsh_catalog_topic($post->post_name);if(!$topic)return '';$type=hsh_brushup_type($post->post_name);$names=array('cats'=>'猫','whisky'=>'ウイスキー','it-freelance'=>'仕事');
    $out='<span class="hsh-card-label hsh-card-label-'.esc_attr($topic).'">'.esc_html($names[$topic].' · '.hsh_brushup_types()[$type]).'</span>';
    if($type==='product'){$m=hsh_photo_manifest();$key=$m['affiliate_product_images'][$post->post_name][0]??null;if($key&&isset($m['assets'][$key]))$out.='<span class="hsh-card-product">'.hsh_photo_image($m['assets'][$key],false,'72px').'<span>紹介している商品・サービス</span></span>';}
    return $out;
}
function hsh_brushup_first_steps(){
    $out='<nav class="hsh-first-steps" aria-label="初めての方へ"><h2>初めての方へ</h2><p>気になる話をひとつ選んでみてください。</p><div>';
    foreach(hsh_reader_starters() as $topic=>$slug){$p=get_page_by_path($slug,OBJECT,'post');if($p&&$p->post_status==='publish')$out.='<a href="'.esc_url(get_permalink($p)).'"><small>'.esc_html(hsh_topics()[$topic][0]).'</small><strong>'.esc_html(get_the_title($p)).'</strong><span>ここから読む →</span></a>';}
    return $out.'</div></nav>';
}
function hsh_brushup_shopping($slug){
    $a=null;foreach(hsh_affiliate_articles() as $row)if($row['slug']===$slug)$a=$row;if(!$a)return '';
    $review_date=$a['source_reviewed_at']??'2026-11-04';$review_parts=explode('-',$review_date);$review_label=$review_parts[0].'年'.(int)$review_parts[1].'月'.(int)$review_parts[2].'日';
    $is_service=in_array($slug,array('pet-sougi-110-cat-guide','digxipop-cat-figure','pontely-cat-dna-test'),true);
    $is_food=preg_match('/food|canagan|mcadams|jaguar|mognyan/',$slug);$is_supplement=strpos($slug,'withpety-')===0;
    $fit=$is_food?'原材料や粒の特徴、今のごはんからの切り替え方を比べたい方':($is_supplement?'成分と与え方を確認し、必要性を獣医師に相談してから選びたい方':($is_service?'依頼する内容と料金条件を、申し込み前に整理したい方':'サイズや使い方が、自宅の環境や猫の体格に合うか確かめたい方'));
    $checks=$is_food?'対象年齢、総合栄養食などの区分、一日の給与量、保存方法':($is_supplement?'対象動物、成分、給与量、薬との併用についての相談':($is_service?'プランに含まれる範囲、追加料金、依頼後の変更・キャンセル条件':'寸法、設置場所、対応条件、お手入れ方法'));
    $fit=$a['purchase_fit']??$fit;$checks=$a['purchase_checks']??$checks;
    $condition=$a['purchase_condition']??'表示価格に加えて、送料と支払総額を確認してください。定期購入を選ぶ場合は、次回のお届け日、休止・解約の期限や手続きも確認しておきましょう。';
    return '<aside class="hsh-buying-note"><h2>選ぶときのポイント</h2><dl><dt>こんな比較をしたい方に</dt><dd>'.esc_html($fit).'</dd><dt>注文前に確認すること</dt><dd>'.esc_html($checks).'</dd><dt>支払い・手続きの条件</dt><dd>'.esc_html($condition).'</dd></dl><p>商品情報の確認日：<time datetime="'.esc_attr($review_date).'">'.esc_html($review_label).'</time>。変更の可能性があるため、注文時は公式案内をご確認ください。</p></aside>';
}
function hsh_brushup_comparison($topic){
    if($topic==='cats'){$rows=array(array('canagan-chicken-salmon','カナガン','チキンとサーモンを比較','主原料・栄養成分・給与量'),array('mcadams-cat-chicken-turkey','マックアダムズ','チキン＆ターキー、オーブンベイク製法','原材料・粒の特徴'),array('mognyan-cat-food-guide','モグニャン','白身魚を使ったフード','原材料・粒の大きさ・切り替え方'),array('essential-jaguar-cat-food','ジャガー','肉と魚を組み合わせたフード','原材料・カロリー・粒の特徴'));$title='猫用フード、どこを比べる？';$heads=array('商品','記事で紹介している特徴','比べたい項目');}
    elseif($topic==='whisky'){$rows=array(array('glenlivet-12-first-bottles','グレンリベット12年','花のような上品な香り','グラスのおまけがきっかけ'),array('hsh-whisky-notes','アルボラリス','想像ほど甘くなかったけれど飲みやすい','YouTubeで見て気になった'),array('sherry-cask-notes','グレンファークラス12年','ぶどうパンに顔をうずめたような匂い','シェリーカスクが好き'));$title='飲んだときの印象を比べる';$heads=array('ウイスキー','自分が感じたこと','選んだきっかけ・好み');}
    else return '';
    $out='<section class="hsh-comparison"><h2>'.esc_html($title).'</h2><p>'.($topic==='cats'?'商品の優劣を決めるランキングではなく、違いを調べるための入口です。':'ここに書いたのは、自分が飲んだときの感想です。感じ方には個人差があります。').'</p><table class="hsh-stack-table"><thead><tr>';foreach($heads as $h)$out.='<th scope="col">'.esc_html($h).'</th>';$out.='</tr></thead><tbody>';
    foreach($rows as $r){$p=get_page_by_path($r[0],OBJECT,$r[0]==='hsh-whisky-notes'?'page':'post');if(!$p||$p->post_status!=='publish')continue;$out.='<tr>';for($i=1;$i<4;$i++)$out.='<td data-label="'.esc_attr($heads[$i-1]).'">'.($i===1?'<a href="'.esc_url(get_permalink($p)).'">'.esc_html($r[$i]).' →</a>':esc_html($r[$i])).'</td>';$out.='</tr>';}
    return $out.'</tbody></table></section>';
}
function hsh_brushup_related($slug){
    if(hsh_brushup_type($slug)!=='product')return '';
    $slugs=preg_match('/food|canagan|mcadams|jaguar|mognyan/',$slug)?array('canagan-chicken-salmon','mognyan-cat-food-guide','cat-vet-routine'):(strpos($slug,'withpety-')===0?array('cat-vet-routine','before-welcoming-cat'):array('manmos-cat-life','cat-carrier-checklist','cat-teaser-small-parts'));
    foreach(hsh_affiliate_articles() as $a)if($a['slug']===$slug&&isset($a['related_slugs'])){$slugs=$a['related_slugs'];break;}
    $out='<nav class="hsh-product-related" aria-label="この商品とあわせて読む"><h2>あわせて読みたい記事</h2><ul>';$n=0;foreach($slugs as $s){if($s===$slug)continue;$p=get_page_by_path($s,OBJECT,'post');if($p&&$p->post_status==='publish'){$out.='<li><a href="'.esc_url(get_permalink($p)).'">'.esc_html(get_the_title($p)).' →</a></li>';if(++$n===2)break;}}return $out.'</ul></nav>';
}
function hsh_brushup_empty_search(){
    $out='<section class="hsh-empty-recommendations"><h2>こんな記事からどうぞ</h2><ul>';foreach(hsh_reader_starters() as $slug){$p=get_page_by_path($slug,OBJECT,'post');if($p&&$p->post_status==='publish')$out.='<li><a href="'.esc_url(get_permalink($p)).'">'.esc_html(get_the_title($p)).'</a></li>';}return $out.'</ul></section>';
}
add_filter('the_content',function($content){
    if(!in_the_loop()||!is_main_query())return $content;$p=get_post();if(!$p||$p->post_status!=='publish')return $content;
    if(hsh_owned_blog_post($p)&&hsh_brushup_type($p->post_name)==='product'){
        $slug=$p->post_name;$label='公式サイトで詳細・購入条件を見る';foreach(hsh_affiliate_articles() as $a)if($a['slug']===$slug){$label=($a['product_label']??preg_split('/[｜。]/u',preg_replace('/^【ご紹介】/u','',$a['title']))[0]).'の公式案内を見る';break;}
        $content=preg_replace_callback('~(<aside class="hsh-affiliate-cta"[^>]*>.*?<a\b[^>]*>)(.*?)(</a>)~s',function($m)use($slug,$label){return hsh_brushup_shopping($slug).$m[1].esc_html($label).$m[3];},$content,1);
        $content.=($slug==='canagan-chicken-salmon'?hsh_brushup_comparison('cats'):'').hsh_brushup_related($slug);
    }
    if($p->post_name==='sherry-cask-notes'&&hsh_owned_blog_post($p))$content.=hsh_brushup_comparison('whisky');
    if($p->post_name==='hsh-hakushu-visit'&&get_post_meta($p->ID,'_hsh_bridge_owned',true)==='1')$content='<nav class="hsh-visit-summary" aria-label="見学の思い出"><h2>2025年7月、初めての蒸溜所見学</h2><p>白州の看板、館内に並ぶボトル、そして飲み比べ。初めての見学で撮った写真を順に振り返ります。飲み比べも楽しかった。</p><ol><li>白州の入口へ</li><li>館内のウイスキー展示を見る</li><li>グラスと一緒に、楽しかった飲み比べを振り返る</li></ol></nav>'.$content;
    return $content;
},27);
add_action('wp_enqueue_scripts',function(){if(!hsh_reader_scope())return;wp_enqueue_style('hsh-brushup',plugin_dir_url(__FILE__).'assets/brushup.css',array('hsh-palette'),'1.38.1');wp_enqueue_script('hsh-brushup',plugin_dir_url(__FILE__).'assets/brushup.js',array('hsh-reader'),'1.38.1',true);},130);
function hsh_brushup_photo_stories(){
    $m=hsh_photo_manifest();$items=array(array('manmos-zip-pillow','枕の上でひと休み','まんもすは枕の上でのんびり。ふわふわの毛と、くつろいだ姿を写真に残しました。'),array('manmos-zip-scratcher','爪とぎの上が落ち着く場所','爪とぎの上もお気に入り。遊ぶためだけでなく、ひと休みする場所にもなっています。'),array('manmos-front','抱っこは苦手。でも甘えん坊','まんもすは抱っこがあまり好きではありません。甘えん坊でも、好きな距離感はまんもす次第。毎日のブラッシングをしながら、一緒に暮らしています。'));
    $out='<section class="hsh-photo-stories"><h2>写真でつづる、まんもすの毎日</h2>';
    foreach($items as $r){$a=$m['assets'][$r[0]]??null;if(!$a)continue;$out.='<article><h3>'.esc_html($r[1]).'</h3><figure class="hsh-inline-photo">'.hsh_photo_image($a,false).hsh_photo_figcaption($a).'</figure><p>'.esc_html($r[2]).'</p></article>';}
    return $out.'<p><a href="'.esc_url(home_url('/manmos-cat-life/')).'">まんもすとの暮らしを、もう少し詳しく →</a></p></section>';
}
add_filter('the_content',function($content){$p=get_post();if($p&&$p->post_status==='publish'&&in_the_loop()&&is_main_query()&&$p->post_name==='hsh-gallery'&&get_post_meta($p->ID,'_hsh_bridge_owned',true)==='1')$content.=hsh_brushup_photo_stories();return $content;},28);
