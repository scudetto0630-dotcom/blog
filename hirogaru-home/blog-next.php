<?php
if(!defined('ABSPATH'))exit;
/** Packaged additions only. No arbitrary publishing endpoint. */
function hsh_expanded_articles(){
    $items=json_decode(file_get_contents(__DIR__.'/expanded-articles.json'),true);
    return is_array($items)?$items:array();
}
function hsh_catalog(){return array_merge(hsh_article_map(),hsh_expanded_articles());}
function hsh_owned_blog_post($post){
    return $post&&$post->post_type==='post'&&(get_post_meta($post->ID,'_hsh_starter_owned',true)===$post->post_name||get_post_meta($post->ID,'_hsh_bundle_owned',true)===$post->post_name);
}
function hsh_release_status(){
    $rows=array();foreach(hsh_expanded_articles() as $a){$p=get_page_by_path($a['slug'],OBJECT,'post');$owned=$p&&get_post_meta($p->ID,'_hsh_bundle_owned',true)===$a['slug'];$rows[]=array('slug'=>$a['slug'],'id'=>$owned?$p->ID:null,'status'=>$owned?$p->post_status:($p?'conflict':'missing'),'fingerprint'=>$owned?hsh_article_fingerprint($p):null,'url'=>$owned?get_permalink($p):null);}
    return hsh_edit_response(array('articles'=>$rows));
}
function hsh_release_article($request){
    $d=$request->get_json_params();
    if(!is_array($d)||array_diff(array_keys($d),array('slug','expected'))||!isset($d['slug'])||!is_string($d['slug'])||!array_key_exists('expected',$d)||($d['expected']!==null&&(!is_string($d['expected'])||!preg_match('/^[a-f0-9]{64}$/D',$d['expected']))))return new WP_Error('hsh_input','記事と確認用の指紋を指定してください。',array('status'=>400));
    $a=null;foreach(hsh_expanded_articles() as $row)if($row['slug']===$d['slug'])$a=$row;
    if(!$a)return new WP_Error('hsh_scope','同梱記事以外は公開できません。',array('status'=>403));
    if(!add_option('hsh_bundle_lock',time(),'',false))return new WP_Error('hsh_busy','他の記事を処理中です。',array('status'=>409));
    try{
        $p=get_page_by_path($a['slug'],OBJECT,'post');$home=get_post((int)get_option('hsh_page_id'));$author=$home?(int)$home->post_author:0;
        if(!$author||!user_can($author,'publish_posts'))return new WP_Error('hsh_author','投稿者を確認できません。',array('status'=>409));
        if($p&&(!hsh_owned_blog_post($p)||get_post_meta($p->ID,'_hsh_bundle_owned',true)!==$a['slug']||$p->post_status!=='publish'||!is_string($d['expected'])||!hash_equals(hsh_article_fingerprint($p),$d['expected'])))return new WP_Error('hsh_conflict','記事が変更されています。再確認してください。',array('status'=>409));
        if(!$p&&$d['expected']!==null)return new WP_Error('hsh_conflict','新規記事の指紋は空にしてください。',array('status'=>409));
        $category=get_term_by('slug',$a['topic'],'category');if(!$category)return new WP_Error('hsh_category','カテゴリーを確認できません。',array('status'=>409));
        $data=array('post_type'=>'post','post_author'=>$author,'post_status'=>'publish','post_name'=>$a['slug'],'post_title'=>sanitize_text_field($a['title']),'post_content'=>wp_kses_post($a['content']),'post_excerpt'=>sanitize_text_field($a['excerpt']),'post_category'=>array((int)$category->term_id),'comment_status'=>'closed');
        if($p){hsh_remember_blog_post($p);$data['ID']=$p->ID;}else{$data['meta_input']=array('_hsh_bundle_owned'=>$a['slug']);}
        $id=wp_insert_post(wp_slash($data),true);if(is_wp_error($id))return $id;
        update_post_meta($id,'the_page_meta_description',sanitize_text_field($a['excerpt']));
        update_post_meta($id,'_hsh_content_reviewed',$a['updated_at']);
        return hsh_edit_response(array('id'=>$id,'url'=>get_permalink($id),'fingerprint'=>hsh_article_fingerprint(get_post($id))));
    }finally{delete_option('hsh_bundle_lock');}
}
add_action('rest_api_init',function(){register_rest_route('hirogaru/v1','/content-release',array(array('methods'=>'GET','callback'=>'hsh_release_status','permission_callback'=>'hsh_edit_permission'),array('methods'=>'POST','callback'=>'hsh_release_article','permission_callback'=>'hsh_edit_permission')));});

function hsh_search_topic($value){return is_string($value)&&in_array($value,array('cats','whisky','it-freelance'),true)?$value:'';}
function hsh_search_alias($text){return strtr($text,array('ねこじゃらし'=>'ねこじゃらし','猫じゃらし'=>'ねこじゃらし','ネコじゃらし'=>'ねこじゃらし','コーデックス'=>'Codex','ねこ'=>'猫','ネコ'=>'猫','チャッピー'=>'ChatGPT','チャットGPT'=>'ChatGPT','チャットＧＰＴ'=>'ChatGPT','ＣＳＶ'=>'CSV'));}
add_action('pre_get_posts',function($q){
    if(is_admin()||!$q->is_main_query()||!$q->is_search())return;
    $term=$q->get('s');if(is_string($term))$q->set('s',hsh_search_alias($term));
    $topic=hsh_search_topic(isset($_GET['hsh_topic'])?wp_unslash($_GET['hsh_topic']):'');
    $q->set('post_type','post');if($topic)$q->set('category_name',$topic);
});
function hsh_start_routes($topic=null){
    $routes=array('cats'=>array('before-welcoming-cat','manmos-cat-life','cat-teaser-small-parts','cat-carrier-checklist','cat-vet-routine'),'whisky'=>array('single-malt-label-guide','glenlivet-12-first-bottles','sherry-cask-notes','highball-measure-and-adjust'),'it-freelance'=>array('starting-it-without-specialty','it-data-work-flow','ai-data-extraction-checks','csv-import-seven-checks'));
    $out='<nav class="hsh-start-routes" aria-label="初めての方向けの読む順番"><h2>どこから読めばいい？</h2>';
    foreach($routes as $key=>$slugs){if($topic&&$topic!==$key)continue;$out.='<section><h3>'.esc_html(hsh_topics()[$key][0]).'</h3><ol>';
        foreach($slugs as $slug){$p=get_page_by_path($slug,OBJECT,'post');if($p&&$p->post_status==='publish')$out.='<li><a href="'.esc_url(get_permalink($p)).'">'.esc_html(get_the_title($p)).'</a></li>';}
        $out.='</ol></section>';
    }
    return $out.'</nav>';
}
function hsh_card_cover($post){
    if(function_exists('hsh_photo_cover')){$photo=hsh_photo_cover($post,is_singular('post')&&(int)get_queried_object_id()===(int)$post->ID);if($photo)return $photo;}
    if(has_post_thumbnail($post))return get_the_post_thumbnail($post,'medium_large',array('loading'=>'lazy','decoding'=>'async'));
    $topic='';foreach(hsh_expanded_articles() as $a)if($a['slug']===$post->post_name)$topic=$a['topic'];
    if(!$topic)return '<div class="hsh-post-placeholder">ブログの記事</div>';
    $icons=array('cats'=>'cat','whisky'=>'glass','it-freelance'=>'data');
    return '<div class="hsh-cover hsh-cover-'.$topic.'"><img src="'.esc_url(plugin_dir_url(__FILE__).'assets/cover-'.$icons[$topic].'.svg').'" width="720" height="450" loading="lazy" alt="'.esc_attr(hsh_topics()[$topic][0].'のイラスト').'" ></div>';
}
function hsh_remember_blog_post($p){
    $h=get_post_meta($p->ID,'_hsh_edit_history',true);if(!is_array($h))$h=array();
    $snapshot=array('time'=>time(),'title'=>$p->post_title,'content'=>$p->post_content,'excerpt'=>$p->post_excerpt,'thumbnail'=>(int)get_post_thumbnail_id($p->ID));
    $last=$h?end($h):null;if(is_array($last)&&$last['title']===$snapshot['title']&&$last['content']===$snapshot['content']&&$last['excerpt']===$snapshot['excerpt']&&(int)$last['thumbnail']===$snapshot['thumbnail'])return;
    $h[]=$snapshot;
    update_post_meta($p->ID,'_hsh_edit_history',array_slice($h,-10));
}
function hsh_restore_blog_snapshot($id,$expected,$probe=false){
    $p=get_post($id);$owned=hsh_owned_blog_post($p);
    if($probe)$owned=$p&&$p->post_status==='draft'&&get_post_meta($id,'_hsh_restore_probe',true)==='1';
    if(!$owned||!is_string($expected)||!hash_equals(hsh_article_fingerprint($p),$expected))return new WP_Error('hsh_conflict','記事が変更されています。再確認してください。');
    $h=get_post_meta($id,'_hsh_edit_history',true);$snapshot=is_array($h)?end($h):false;
    if(!$snapshot||!isset($snapshot['title'],$snapshot['content'],$snapshot['excerpt']))return new WP_Error('hsh_history','復元できる履歴がありません。');
    $before=array('time'=>time(),'title'=>$p->post_title,'content'=>$p->post_content,'excerpt'=>$p->post_excerpt,'thumbnail'=>(int)get_post_thumbnail_id($id));
    $done=wp_update_post(wp_slash(array('ID'=>$id,'post_title'=>$snapshot['title'],'post_content'=>$snapshot['content'],'post_excerpt'=>$snapshot['excerpt'])),true);
    if(is_wp_error($done))return $done;
    if(!empty($snapshot['thumbnail']))set_post_thumbnail($id,(int)$snapshot['thumbnail']);else delete_post_thumbnail($id);
    update_post_meta($id,'the_page_meta_description',$snapshot['excerpt']);
    array_pop($h);update_post_meta($id,'_hsh_edit_history',$h);
    $log=get_post_meta($id,'_hsh_restore_history',true);if(!is_array($log))$log=array();$log[]=$before;update_post_meta($id,'_hsh_restore_history',array_slice($log,-10));
    return true;
}
function hsh_restore_probe(){
    $home=get_post((int)get_option('hsh_page_id'));if(!$home||!user_can((int)$home->post_author,'publish_posts'))return new WP_Error('hsh_author','投稿者を確認できません。');
    $id=wp_insert_post(array('post_type'=>'post','post_status'=>'draft','post_author'=>(int)$home->post_author,'post_title'=>'非公開の復旧確認','post_content'=>'<p>original</p>','post_excerpt'=>'original'),true);
    if(is_wp_error($id))return $id;
    try{
        update_post_meta($id,'_hsh_restore_probe','1');hsh_remember_blog_post(get_post($id));
        wp_update_post(array('ID'=>$id,'post_title'=>'changed','post_content'=>'<p>changed</p>','post_excerpt'=>'changed'));
        $r=hsh_restore_blog_snapshot($id,hsh_article_fingerprint(get_post($id)),true);if(is_wp_error($r))return $r;
        $p=get_post($id);$ok=$p->post_title==='非公開の復旧確認'&&$p->post_content==='<p>original</p>'&&$p->post_excerpt==='original'&&get_post_meta($id,'the_page_meta_description',true)==='original';
        if(!$ok)return new WP_Error('hsh_restore','復旧結果が一致しません。');
        update_option('hsh_restore_probe_result',array('at'=>time(),'passed'=>true,'scope'=>'temporary draft title/content/excerpt/SEO; no hosting restore'),false);
        return hsh_edit_response(array('passed'=>true,'scope'=>'temporary unpublished draft'));
    }finally{if(!wp_delete_post($id,true))return new WP_Error('hsh_cleanup','復旧確認用の下書きを削除できませんでした。管理画面で確認してください。');}
}
add_action('rest_api_init',function(){register_rest_route('hirogaru/v1','/restore-probe',array('methods'=>'POST','permission_callback'=>'hsh_edit_permission','callback'=>function($r){if($r->get_json_params()!==array())return new WP_Error('hsh_input','空の入力だけ受け付けます。',array('status'=>400));return hsh_restore_probe();}));});

function hsh_related_curated($topic,$id){
    $selected=get_post_meta($id,'_hsh_related_ids',true);$posts=array();
    if(is_array($selected))foreach($selected as $related){$p=get_post((int)$related);if($p&&$p->post_status==='publish'&&$p->ID!==$id&&has_category($topic,$p))$posts[]=$p;}
    return hsh_select_related(array_merge($posts,hsh_posts($topic,8)),$id);
}
function hsh_report_fields($data){
    if(!is_array($data)||!isset($data['slug'],$data['message'])||!is_string($data['slug'])||!is_string($data['message']))return new WP_Error('hsh_report','入力内容を確認してください。',array('status'=>400));
    if(!in_array($data['slug'],array_column(hsh_catalog(),'slug'),true)||strlen(trim($data['message']))<6||strlen($data['message'])>3000||!empty($data['website']))return new WP_Error('hsh_report','記事と報告内容を確認してください。',array('status'=>400));
    $message=trim(sanitize_textarea_field($data['message']));if(strlen($message)<6)return new WP_Error('hsh_report','報告内容を入力してください。',array('status'=>400));
    return array('slug'=>$data['slug'],'message'=>$message);
}
function hsh_receive_report($request){
    $d=$request->get_json_params();if(!is_array($d)||!isset($d['nonce'])||!is_string($d['nonce'])||!wp_verify_nonce($d['nonce'],'hsh_blog_report'))return new WP_Error('hsh_nonce','送信の有効期限が切れました。ページを読み込み直してください。',array('status'=>403));
    $valid=hsh_report_fields($d);if(is_wp_error($valid))return $valid;
    $client=$_SERVER['REMOTE_ADDR']??'unknown';$key='hsh_br_'.substr(hash_hmac('sha256',$client,wp_salt('nonce')),0,32);
    if(!add_option('hsh_blog_reports_lock',time(),'',false))return new WP_Error('hsh_busy','少し待ってから送信してください。',array('status'=>503));
    try{
        $count=(int)get_transient($key);if($count>=3)return new WP_Error('hsh_limit','時間をおいて再度お試しください。',array('status'=>429));
        $rows=get_option('hsh_blog_reports',array());if(!is_array($rows))$rows=array();$now=time();$rows=hsh_prune_blog_reports($rows,$now);
        if(count($rows)>=300)return new WP_Error('hsh_full','現在受け付けできません。お問い合わせをご利用ください。',array('status'=>503));
        $rows[]=array_merge($valid,array('id'=>wp_generate_uuid4(),'time'=>$now));if(!update_option('hsh_blog_reports',$rows,false))return new WP_Error('hsh_save','保存できませんでした。入力内容は消さずに再度お試しください。',array('status'=>503));
        set_transient($key,$count+1,HOUR_IN_SECONDS);return array('message'=>'報告を受け付けました。ありがとうございます。');
    }finally{delete_option('hsh_blog_reports_lock');}
}
add_action('rest_api_init',function(){register_rest_route('hirogaru/v1','/blog-report',array('methods'=>'POST','permission_callback'=>'__return_true','callback'=>'hsh_receive_report'));register_rest_route('hirogaru/v1','/blog-report-token',array('methods'=>'GET','permission_callback'=>'__return_true','callback'=>function(){$r=new WP_REST_Response(array('nonce'=>wp_create_nonce('hsh_blog_report')));$r->header('Cache-Control','no-store');return $r;}));});
function hsh_report_markup($p){
    return '<details class="hsh-report"><summary>この記事の誤りを知らせる</summary><p>「'.esc_html($p->post_title).'」への報告です。氏名・メールアドレスは不要です。個人情報は書かないでください。報告は管理人だけが確認し、90日を目安に削除します。</p><form class="hsh-report-form" data-slug="'.esc_attr($p->post_name).'"><label>訂正した方がよい箇所<textarea name="message" required minlength="6" maxlength="1000" rows="4"></textarea></label><div class="hsh-trap" aria-hidden="true"><label>空欄にしてください<input name="website" tabindex="-1" autocomplete="off"></label></div><button type="submit">誤りを報告する</button><p role="status" class="hsh-report-status"></p></form><noscript><p>送信にはJavaScriptが必要です。<a href="'.esc_url(home_url('/お問い合わせ/')).'">お問い合わせフォーム</a>も利用できます。</p></noscript></details>';
}
add_filter('body_class',function($classes){if(is_singular('post')&&hsh_owned_blog_post(get_post()))$classes[]='hsh-reviewed-post';return $classes;});
add_filter('the_content',function($content){
    if(!is_singular('post')||!in_the_loop()||!is_main_query())return $content;
    $p=get_post();if(!hsh_owned_blog_post($p))return $content;
    if(get_post_meta($p->ID,'_hsh_bundle_owned',true)){
        $topic='';foreach(hsh_expanded_articles() as $a)if($a['slug']===$p->post_name)$topic=$a['topic'];
        $content='<div class="hsh-article-visual">'.hsh_card_cover($p).'</div>'.$content;
        $content.='<p class="hsh-review-note">本文の見直し：<time datetime="2026-10-29">2026/10/29</time></p><p><a href="'.esc_url(hsh_category_url($topic)).'">'.esc_html(hsh_topics()[$topic][0]).'の記事一覧へ →</a></p>'.hsh_related_markup($topic,$p->ID).hsh_profile_markup();
    }
    return $content.'<div class="hsh-article-tools"><button type="button" class="hsh-print">この記事を印刷する</button><a href="#">ページの先頭へ ↑</a></div>'.hsh_report_markup($p);
},25);
add_action('wp_enqueue_scripts',function(){
    if(is_admin())return;
    wp_enqueue_style('hsh-next',plugin_dir_url(__FILE__).'assets/blog-next.css',array(),'1.21.0');
    wp_enqueue_script('hsh-next',plugin_dir_url(__FILE__).'assets/blog-next.js',array(),'1.21.0',true);
    wp_localize_script('hsh-next','hshBlogTools',array('reportUrl'=>rest_url('hirogaru/v1/blog-report'),'tokenUrl'=>rest_url('hirogaru/v1/blog-report-token')));
},110);
add_filter('template_include',function($template){return is_404()?__DIR__.'/not-found-template.php':$template;},102);

function hsh_health_scan(){
    $targets=array(home_url('/'),home_url('/wp-sitemap.xml'),home_url('/wp-content/plugins/hirogaru-home/assets/resources/extraction-input.csv'),home_url('/wp-content/plugins/hirogaru-home/assets/resources/extraction-expected.csv'));
    foreach(hsh_catalog() as $a)$targets[]=home_url('/'.$a['slug'].'/');
    foreach(array('cat-carrier-checklist.txt','highball-note.txt','csv-import-checklist.txt','csv-practice.csv','extraction-request.txt') as $file)$targets[]=home_url('/wp-content/plugins/hirogaru-home/assets/resources/'.$file);
    $targets=array_merge($targets,array('https://www.rfc-editor.org/rfc/rfc4180.txt','https://www.jreast.co.jp/kippu/20.html','https://www.suntory.co.jp/whisky/'));
    $rows=get_option('hsh_health_rows',array());if(!is_array($rows))$rows=array();$offset=(int)get_option('hsh_health_offset',0);$count=count($targets);
    for($i=0;$i<6;$i++){
        $url=$targets[($offset+$i)%$count];$r=wp_remote_head($url,array('timeout'=>6,'redirection'=>0));
        $code=is_wp_error($r)?0:wp_remote_retrieve_response_code($r);
        $rows[$url]=array('time'=>time(),'code'=>$code,'bytes'=>is_wp_error($r)?0:(int)wp_remote_retrieve_header($r,'content-length'),'result'=>is_wp_error($r)?'接続できませんでした':(($code>=200&&$code<300)?'応答あり（本文未照合）':(($code>=300&&$code<400)?'転送先の確認が必要':'確認が必要')));
    }
    update_option('hsh_health_rows',$rows,false);update_option('hsh_health_offset',($offset+6)%$count,false);
    $reports=get_option('hsh_blog_reports',array());if(is_array($reports)){update_option('hsh_blog_reports',hsh_prune_blog_reports($reports,time()),false);}
    return $rows;
}
add_action('init',function(){if(!wp_next_scheduled('hsh_blog_maintenance_daily'))wp_schedule_event(time()+DAY_IN_SECONDS,'daily','hsh_blog_maintenance_daily');});
add_action('hsh_blog_maintenance_daily','hsh_health_scan');
register_deactivation_hook(__DIR__.'/hirogaru-home.php',function(){wp_clear_scheduled_hook('hsh_blog_maintenance_daily');});
function hsh_blog_dashboard(){
    if(!current_user_can('manage_options'))return;
    $message='';
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
        check_admin_referer('hsh_blog_maintenance');
        $action=sanitize_key(wp_unslash($_POST['hsh_blog_action']??''));
        if($action==='scan'){hsh_health_scan();$message='6件を確認しました。次の実行で続きへ進みます。';}
        if($action==='reports-clear'){delete_option('hsh_blog_reports');$message='受け付けた報告を削除しました。';}
        if(in_array($action,array('related','restore'),true)){
            $id=absint($_POST['post_id']??0);$p=get_post($id);$expected=is_string($_POST['expected']??null)?wp_unslash($_POST['expected']):'';
            if(!$p||!hsh_owned_blog_post($p)||!hash_equals(hsh_article_fingerprint($p),$expected)){$message='記事が変更されています。画面を更新してください。';}
            elseif($action==='restore'){$r=hsh_restore_blog_snapshot($id,$expected);$message=is_wp_error($r)?$r->get_error_message():'前の本文・タイトル・説明文を復元しました。';}
            else{
                $ids=is_array($_POST['related']??null)?array_slice(array_unique(array_map('absint',$_POST['related'])),0,2):array();$valid=array();
                foreach($ids as $candidate){$r=get_post($candidate);if(!$r||$r->ID===$id||$r->post_status!=='publish'||!hsh_owned_blog_post($r))continue;foreach(array_keys(hsh_topics()) as $topic){if(has_category($topic,$p)&&has_category($topic,$r)){$valid[]=$candidate;break;}}}
                update_post_meta($id,'_hsh_related_ids',$valid);$message='関連記事の優先順を保存しました。候補は同じカテゴリーだけです。';
            }
        }
    }
    echo '<div class="wrap"><h1>ブログの記事点検</h1><p>公開記事の見直し、誤り報告、関連記事、編集履歴をまとめて確認します。全体のサーバーバックアップとは別の機能です。</p>';
    if($message)echo '<div class="notice notice-info"><p>'.esc_html($message).'</p></div>';
    echo '<h2>記事と履歴</h2><p>見直し目安は内容を確認するための日付です。日付を過ぎても自動で書き換えません。復元ボタンは1つ前の保存履歴に戻します。本文・タイトル・抜粋・表紙IDが対象です。</p>';
    $posts=array();foreach(hsh_catalog() as $a){$p=get_page_by_path($a['slug'],OBJECT,'post');if($p&&hsh_owned_blog_post($p))$posts[$p->ID]=$p;}
    foreach(hsh_catalog() as $a){$p=get_page_by_path($a['slug'],OBJECT,'post');if(!$p||!hsh_owned_blog_post($p))continue;$history=get_post_meta($p->ID,'_hsh_edit_history',true);if(!is_array($history))$history=array();$restores=get_post_meta($p->ID,'_hsh_restore_history',true);if(!is_array($restores))$restores=array();
        echo '<details style="background:white;padding:16px;margin:12px 0;border:1px solid #ddd"><summary><strong>'.esc_html($p->post_title).'</strong> ／ 見直し目安 '.esc_html($a['review_due_at']).' ／ 保存履歴 '.count($history).'件</summary><p><a href="'.esc_url(get_permalink($p)).'">公開記事を見る</a> ／ <a href="'.esc_url(get_edit_post_link($p->ID)).'">本文を編集する</a></p><form method="post">';wp_nonce_field('hsh_blog_maintenance');
        echo '<input type="hidden" name="post_id" value="'.(int)$p->ID.'"><input type="hidden" name="expected" value="'.esc_attr(hsh_article_fingerprint($p)).'"><p>優先する関連記事（上から最大2件）</p>';
        $selected=get_post_meta($p->ID,'_hsh_related_ids',true);if(!is_array($selected))$selected=array();
        for($i=0;$i<2;$i++){echo '<label>候補'.($i+1).' <select name="related[]"><option value="0">自動選択</option>';foreach($posts as $candidate){if($candidate->ID===$p->ID||$candidate->post_status!=='publish')continue;foreach(array_keys(hsh_topics()) as $topic){if(has_category($topic,$p)&&has_category($topic,$candidate)){echo '<option value="'.(int)$candidate->ID.'" '.selected((int)($selected[$i]??0),(int)$candidate->ID,false).'>'.esc_html($candidate->post_title).'</option>';break;}}}echo '</select></label> ';}
        echo '<p><button class="button" name="hsh_blog_action" value="related">関連記事を保存</button> ';
        if($history)echo '<button class="button" name="hsh_blog_action" value="restore">1つ前の保存内容へ戻す</button>';
        echo '</p></form><ul>';foreach(array_reverse($history) as $row)echo '<li>保存 '.esc_html(wp_date('Y/m/d H:i',(int)$row['time'])).'：'.esc_html($row['title']).'</li>';foreach(array_reverse($restores) as $row)echo '<li>復元直前の内容を退避 '.esc_html(wp_date('Y/m/d H:i',(int)$row['time'])).'</li>';echo '</ul></details>';
    }
    echo '<h2>画像の確認</h2><p>画像の有無・代替説明・ファイル容量を確認します。写真が必要な記事は、実際の写真が届くまでイラストであることを表示します。</p><ul>';
    foreach($posts as $p){preg_match_all('~<img\b[^>]*>~i',$p->post_content,$imgs);$missing=0;foreach($imgs[0] as $img)if(!preg_match('~\balt=[\x22\x27][^\x22\x27]+~',$img))$missing++;echo '<li>'.esc_html($p->post_title).'：本文画像 '.count($imgs[0]).'点、代替説明の要確認 '.(int)$missing.'点、表紙 '.(function_exists('hsh_photo_for_slug')&&hsh_photo_for_slug($p->post_name)?(hsh_photo_for_slug($p->post_name)['kind']==='provided'?'提供写真':'AI生成画像'):(has_post_thumbnail($p)?'設定済み':'カテゴリー共通イラスト')).'</li>';}
    if(function_exists('hsh_photo_manifest'))foreach(hsh_photo_manifest()['assets'] as $photo){$file=__DIR__.'/assets/photos/'.$photo['file'];echo '<li>'.esc_html($photo['file']).'：'.(is_file($file)?esc_html(number_format(filesize($file)/1024,1)).' KB':'ファイルを確認してください').' ／ '.esc_html($photo['kind']==='provided'?'提供写真':'AI生成').'</li>';}
    foreach(array('blog-scene.webp','brand-avatar.webp','cover-cat.svg','cover-glass.svg','cover-data.svg') as $asset)if(is_file(__DIR__.'/assets/'.$asset))echo '<li>'.esc_html($asset).'：'.esc_html(number_format(filesize(__DIR__.'/assets/'.$asset)/1024,1)).' KB'.(filesize(__DIR__.'/assets/'.$asset)>400000?'（容量を見直す）':'').'</li>';echo '</ul>';
    echo '<h2>リンク・保存資料の応答</h2><p>毎日6件ずつ巡回します。WordPressの定期実行はアクセス状況に依存します。転送や接続失敗はリンク切れと断定せず、確認対象として残します。応答があっても、資料の内容を確認したことにはなりません。</p><form method="post">';wp_nonce_field('hsh_blog_maintenance');echo '<button class="button" name="hsh_blog_action" value="scan">次の6件を確認</button></form><ul>';
    $health=get_option('hsh_health_rows',array());foreach((array)$health as $url=>$row)echo '<li><a href="'.esc_url($url).'">'.esc_html($url).'</a>：'.esc_html($row['result']).' / HTTP '.(int)$row['code'].' / '.esc_html(wp_date('Y/m/d H:i',$row['time'])).(!empty($row['bytes'])?' / '.esc_html(number_format($row['bytes']/1024,1)).' KB':'').'</li>';echo '</ul>';
    echo '<h2>読者からの誤り報告</h2><p>報告はこの画面だけに表示し、メール送信はしません。保存期間は90日です。</p>';$rows=get_option('hsh_blog_reports',array());foreach((array)$rows as $r)echo '<article style="background:white;padding:12px;margin:8px 0"><strong>'.esc_html($r['slug']).'</strong><small> '.esc_html(wp_date('Y/m/d H:i',$r['time'])).'</small><p>'.nl2br(esc_html($r['message'])).'</p></article>';
    echo '<form method="post">';wp_nonce_field('hsh_blog_maintenance');echo '<button class="button" name="hsh_blog_action" value="reports-clear">報告をすべて削除</button></form>';
    $probe=get_option('hsh_restore_probe_result',array());echo '<h2>復旧確認の記録</h2><p>'.(!empty($probe['passed'])?'非公開の一時記事で、本文・タイトル・抜粋・SEO説明文の復元を確認：'.esc_html(wp_date('Y/m/d H:i',$probe['at'])):'復旧確認はまだ実施していません。').'</p><p>サーバー全体、データベース全体、メディアファイルの復旧確認は含みません。</p></div>';
}
add_action('admin_menu',function(){add_theme_page('ブログの記事点検','ブログの記事点検','manage_options','hsh-blog-maintenance','hsh_blog_dashboard');});

/** Presentation changes leave stored attachments and prior originals intact. */
add_filter('post_thumbnail_html',function($html,$post_id){
    $p=get_post($post_id);if(!hsh_owned_blog_post($p))return $html;
    if(function_exists('hsh_photo_cover')){$photo=hsh_photo_cover($p,is_singular('post')&&(int)get_queried_object_id()===(int)$post_id);if($photo)return $photo;}
    $topic='';foreach(hsh_catalog() as $a){if($a['slug']===$p->post_name){$topic=$a['topic'];break;}}
    if(!$topic)return $html;$icons=array('cats'=>'cat','whisky'=>'glass','it-freelance'=>'data');
    $eager=is_singular('post')&&(int)get_queried_object_id()===(int)$post_id;
    return '<div class="hsh-cover hsh-cover-'.esc_attr($topic).'"><img src="'.esc_url(plugin_dir_url(__FILE__).'assets/cover-'.$icons[$topic].'.svg').'" width="720" height="450" loading="'.($eager?'eager':'lazy').'" decoding="async"'.($eager?' fetchpriority="high"':'').' alt="'.esc_attr(hsh_topics()[$topic][0].'を表すイラスト。実物の写真ではありません。').'"></div>';
},20,2);
add_filter('the_content',function($content){
    if(is_singular('post')&&in_the_loop()&&is_main_query()&&get_the_ID()===8)return $content.hsh_start_routes();
    return $content;
},22);

function hsh_prune_blog_reports($rows,$now){
    return array_values(array_filter($rows,function($r)use($now){return is_array($r)&&($r['time']??0)>$now-90*DAY_IN_SECONDS;}));
}

// Capture normal editor changes as well as scoped API changes.
add_action('pre_post_update',function($id){$p=get_post($id);if(hsh_owned_blog_post($p))hsh_remember_blog_post($p);});

// Owner-only diagnostics and a fixed-target scan; never an arbitrary URL proxy.
function hsh_blog_health_status(){
    $assets=array();foreach(array('blog-scene.webp','brand-avatar.webp','cover-cat.svg','cover-glass.svg','cover-data.svg') as $name){$p=__DIR__.'/assets/'.$name;$assets[$name]=array('exists'=>is_file($p),'bytes'=>is_file($p)?filesize($p):0);}
    if(function_exists('hsh_photo_manifest'))foreach(hsh_photo_manifest()['assets'] as $a){$p=__DIR__.'/assets/photos/'.$a['file'];$assets['photos/'.$a['file']]=array('exists'=>is_file($p),'bytes'=>is_file($p)?filesize($p):0,'kind'=>$a['kind']);}
    return hsh_edit_response(array('links'=>get_option('hsh_health_rows',array()),'next_scheduled'=>wp_next_scheduled('hsh_blog_maintenance_daily'),'restore_probe'=>get_option('hsh_restore_probe_result',array()),'assets'=>$assets));
}
add_action('rest_api_init',function(){register_rest_route('hirogaru/v1','/blog-health',array(array('methods'=>'GET','permission_callback'=>'hsh_edit_permission','callback'=>'hsh_blog_health_status'),array('methods'=>'POST','permission_callback'=>'hsh_edit_permission','callback'=>function($request){if($request->get_json_params()!==array())return new WP_Error('hsh_input','空の入力だけ受け付けます。',array('status'=>400));hsh_health_scan();return hsh_blog_health_status();})));});
