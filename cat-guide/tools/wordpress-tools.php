<?php
if (!defined('ABSPATH')) { exit; }
function nkt_char_length($s) { return preg_match_all('/./us',$s,$matches); }
function nkt_report_page($url) {
    if (!is_string($url) || strlen($url)>1000) { return false; }
    $u=wp_parse_url($url);$home=wp_parse_url(home_url('/'));
    if (!$u || ($u['scheme'] ?? '')!=='https' || ($u['host'] ?? '')!==($home['host'] ?? '') || isset($u['user']) || isset($u['pass']) || isset($u['port'])) { return false; }
    $prefix='/neko-guide/';$path=$u['path'] ?? '/';
    if(strpos($path,$prefix)!==0){return false;}
    $relative=substr($path,strlen($prefix));
    if(substr($relative,-10)==='index.html'){$relative=substr($relative,0,-10);}
    if($relative!==''&&substr($relative,-1)!=='/'){$relative.='/';}
    $manifest=json_decode(file_get_contents(__DIR__.'/cat-guide-preview/manifest.json'),true);
    if (!is_array($manifest) || !in_array($relative.'index.html',$manifest,true)) { return false; }
    return home_url($prefix.$relative);
}
function nkt_validate_report($data) {
    if (!is_array($data) || array_diff(array_keys($data),array('page','kind','message','website','nonce')) || !isset($data['page'],$data['kind'],$data['message'])) { return new WP_Error('nkt_input','入力内容を確認してください。',array('status'=>400)); }
    $page=nkt_report_page($data['page']);
    if (!$page) { return new WP_Error('nkt_page','このサイト内のページURLを入力してください。',array('status'=>400)); }
    if (!is_string($data['kind']) || !in_array($data['kind'],array('content','link','other'),true)) { return new WP_Error('nkt_kind','連絡の種類を選んでください。',array('status'=>400)); }
    if (!is_string($data['message']) || strlen($data['message'])>8000 || !preg_match('//u',$data['message'])) { return new WP_Error('nkt_message','本文は2000文字以内で入力してください。',array('status'=>400)); }
    $message=preg_replace('/^[\s\x{3000}]+|[\s\x{3000}]+$/u','',$data['message']);
    if ($message==='' || nkt_char_length($message)>2000) { return new WP_Error('nkt_message','本文を1〜2000文字で入力してください。',array('status'=>400)); }
    if (isset($data['website']) && $data['website']!=='') { return new WP_Error('nkt_bot','送信を受け付けられませんでした。',array('status'=>400)); }
    $message=trim(sanitize_textarea_field($message));
    if($message===''){return new WP_Error('nkt_message','本文を入力してください。',array('status'=>400));}
    return array('page'=>$page,'kind'=>$data['kind'],'message'=>$message);
}
function nkt_pruned_reports($now=null) {
    $now=$now ?? time();$rows=get_option('nkt_correction_reports',array());
    if(!is_array($rows)){$rows=array();}
    return array_values(array_filter($rows,function($r)use($now){return is_array($r)&&isset($r['created_at'])&&$r['created_at']>=$now-90*DAY_IN_SECONDS;}));
}
function nkt_acquire_lock($key,$now=null) {
    $now=$now ?? time();$previous=get_option($key,false);
    if(is_int($previous)&&$previous<$now-120&&get_option($key,false)===$previous){delete_option($key);}
    return add_option($key,$now,'',false);
}
function nkt_store_report($report,$client,$now=null) {
    $now=$now ?? time();
    $rate='nkt_rate_'.substr(hash_hmac('sha256',$client,wp_salt('nonce')),0,32);
    if(!nkt_acquire_lock('nkt_reports_lock',$now)){return new WP_Error('nkt_busy','ほかの送信を処理中です。少し待ってからお試しください。',array('status'=>503));}
    try {
    $window=get_transient($rate);if(!is_array($window)){$window=array('count'=>0,'expires'=>$now+3600);}
    if($window['expires']<=$now){$window=array('count'=>0,'expires'=>$now+3600);}
    if($window['count']>=3){return new WP_Error('nkt_rate','送信回数が多いため、時間をおいて再度お試しください。',array('status'=>429));}
        $rows=nkt_pruned_reports($now);
        if(count($rows)>=200){return new WP_Error('nkt_capacity','現在、連絡を受け付けられません。時間をおいてお試しください。',array('status'=>503));}
        $id=wp_generate_uuid4();$rows[]=array_merge($report,array('id'=>$id,'created_at'=>$now));
        if(!update_option('nkt_correction_reports',$rows,false)){return new WP_Error('nkt_save','保存できませんでした。本文を残したまま、もう一度お試しください。',array('status'=>503));}
        $window['count']++;set_transient($rate,$window,max(1,$window['expires']-$now));
        return array('received'=>true,'id'=>$id);
    } finally {delete_option('nkt_reports_lock');}
}
function nkt_allowed_origin($origin) {
    $home=wp_parse_url(home_url('/'));return is_string($origin)&&$origin===$home['scheme'].'://'.$home['host'];
}
function nkt_public_report_api($kind) {
    header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store, private');header('X-Robots-Tag: noindex, nofollow');header('X-Content-Type-Options: nosniff');
    $method=$_SERVER['REQUEST_METHOD'] ?? 'GET';
    if($kind==='token'){
        if($method!=='GET'){wp_send_json(array('message'=>'送信方法が正しくありません。'),405);}
        wp_send_json(array('nonce'=>wp_create_nonce('nkt_public_report')));
    }
    if($method!=='POST'){wp_send_json(array('message'=>'送信方法が正しくありません。'),405);}
    if(!nkt_allowed_origin($_SERVER['HTTP_ORIGIN'] ?? '')){wp_send_json(array('message'=>'このサイトのフォームから送信してください。'),403);}
    if(stripos($_SERVER['CONTENT_TYPE'] ?? '','application/json')!==0){wp_send_json(array('message'=>'送信形式が正しくありません。'),415);}
    $body=file_get_contents('php://input',false,null,0,12001);
    if(strlen($body)>12000){wp_send_json(array('message'=>'送信内容が長すぎます。'),413);}
    $data=json_decode($body,true);
    if(!is_array($data)||!isset($data['nonce'])||!is_string($data['nonce'])||!wp_verify_nonce($data['nonce'],'nkt_public_report')){wp_send_json(array('message'=>'送信の有効期限が切れました。もう一度送信してください。'),403);}
    $valid=nkt_validate_report($data);
    if(is_wp_error($valid)){wp_send_json(array('message'=>$valid->get_error_message()),$valid->get_error_data()['status']);}
    $result=nkt_store_report($valid,$_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if(is_wp_error($result)){wp_send_json(array('message'=>$result->get_error_message()),$result->get_error_data()['status']);}
    wp_send_json($result,201);
}
function nkt_delete_report($request) {
    $data=$request->get_json_params();
    if(!is_array($data)||array_keys($data)!==array('id')||!is_string($data['id'])||!preg_match('/^[a-f0-9-]{36}$/D',$data['id'])){return new WP_Error('nkt_id','連絡IDが正しくありません。',array('status'=>400));}
    if(!nkt_acquire_lock('nkt_reports_lock')){return new WP_Error('nkt_busy','処理中です。',array('status'=>409));}
    try {
        $rows=nkt_pruned_reports();$next=array_values(array_filter($rows,function($r)use($data){return $r['id']!==$data['id'];}));
        if(count($next)===count($rows)){return new WP_Error('nkt_missing','対象の連絡が見つかりません。',array('status'=>404));}
        if(!update_option('nkt_correction_reports',$next,false)){return new WP_Error('nkt_save','削除できませんでした。',array('status'=>503));}
        return hsh_edit_response(array('deleted'=>true));
    } finally {delete_option('nkt_reports_lock');}
}
function nkt_maintenance_data() {
    $data=json_decode(file_get_contents(__DIR__.'/cat-guide-preview/maintenance.json'),true);
    return is_array($data)?$data:array('articles'=>array(),'references'=>array());
}
function nkt_due_articles($articles,$day) {return array_values(array_filter($articles,function($a)use($day){return ($a['review_due_at'] ?? '')!==''&&$a['review_due_at']<$day;}));}
function nkt_check_reference_links() {
    if(!nkt_acquire_lock('nkt_link_check_lock')){return false;}
    try {
        $data=nkt_maintenance_data();$rows=array();
        foreach($data['references'] as $id=>$reference){
            $response=wp_safe_remote_head($reference['url'],array('timeout'=>8,'redirection'=>3,'user-agent'=>'NekoNoTonari LinkCheck/1.0'));
            $rows[$id]=array('checked_at'=>gmdate('c'),'http_status'=>is_wp_error($response)?null:(int)wp_remote_retrieve_response_code($response),'reachable'=>!is_wp_error($response)&&wp_remote_retrieve_response_code($response)>=200&&wp_remote_retrieve_response_code($response)<400);
        }
        update_option('nkt_reference_link_checks',$rows,false);return true;
    } finally {delete_option('nkt_link_check_lock');}
}
add_action('init',function(){if(!wp_next_scheduled('nkt_daily_maintenance')){wp_schedule_event(time()+300,'daily','nkt_daily_maintenance');}});
add_action('nkt_daily_maintenance',function(){update_option('nkt_correction_reports',nkt_pruned_reports(),false);nkt_check_reference_links();});
add_action('rest_api_init',function(){
    register_rest_route('hirogaru/v1','/cat-guide-maintenance',array(array('methods'=>'GET','permission_callback'=>'hsh_edit_permission','callback'=>'nkt_maintenance_status'),array('methods'=>'POST','permission_callback'=>'hsh_edit_permission','callback'=>'nkt_maintenance_run')));
    register_rest_route('hirogaru/v1','/cat-guide-reports',array('methods'=>'GET','permission_callback'=>'hsh_edit_permission','callback'=>function(){return hsh_edit_response(array('reports'=>nkt_pruned_reports()));}));
    register_rest_route('hirogaru/v1','/cat-guide-report-delete',array('methods'=>'POST','permission_callback'=>'hsh_edit_permission','callback'=>'nkt_delete_report'));
});
function nkt_admin_page() {
    if(!current_user_can('manage_options')){return;}
    if(($_SERVER['REQUEST_METHOD'] ?? '')==='POST'){
        check_admin_referer('nkt_admin');
        if(isset($_POST['nkt_check_links'])){nkt_check_reference_links();}
        if(isset($_POST['nkt_delete'])&&is_string($_POST['nkt_delete'])){
            $id=sanitize_text_field(wp_unslash($_POST['nkt_delete']));
            if(nkt_acquire_lock('nkt_reports_lock')){try{$rows=nkt_pruned_reports();update_option('nkt_correction_reports',array_values(array_filter($rows,function($r)use($id){return $r['id']!==$id;})),false);}finally{delete_option('nkt_reports_lock');}}
        }
    }
    $data=nkt_maintenance_data();$day=gmdate('Y-m-d');$due=nkt_due_articles($data['articles'],$day);$rows=nkt_pruned_reports();$checks=get_option('nkt_reference_link_checks',array());
    echo '<div class="wrap"><h1>ねこのとなり 運営状況</h1><p>記事の見直し、リンク確認、訂正連絡を確認できます。リンクが開けることと、記事内容が正しいことは別に管理します。</p><h2>見直し期限を過ぎた記事：'.count($due).'件</h2><ul>';
    foreach($due as $article){echo '<li>'.esc_html($article['title'].'（期限：'.$article['review_due_at'].'）').'</li>';}
    echo '</ul><h2>外部資料の照合状況</h2><ul>';
    foreach($data['references'] as $id=>$reference){$link=$checks[$id] ?? null;echo '<li>'.esc_html($reference['title'].' ／ 本文照合：'.($reference['verified_at'] ?? '未完了').' ／ リンク確認：'.($link?($link['checked_at'].' HTTP '.($link['http_status'] ?? '接続失敗')):'未実施')).'</li>';}
    echo '</ul><form method="post">';wp_nonce_field('nkt_admin');echo '<button class="button" name="nkt_check_links" value="1">公式サイトへのリンクを確認</button></form><p>日次点検はWordPressの定期処理で実行します。アクセスがない場合は遅れるため、GitHubの定期点検も併用します。いずれも本文の自動審査・自動リライトではありません。</p><h2>訂正連絡：'.count($rows).'件</h2>';
    foreach(array_reverse($rows) as $report){echo '<section style="background:white;padding:18px;margin:15px 0"><h3>'.esc_html(gmdate('Y-m-d H:i',$report['created_at']).' UTC ／ '.$report['kind']).'</h3><p><a href="'.esc_url($report['page']).'">'.esc_html($report['page']).'</a></p><p style="white-space:pre-wrap">'.esc_html($report['message']).'</p><form method="post">';wp_nonce_field('nkt_admin');echo '<button class="button" name="nkt_delete" value="'.esc_attr($report['id']).'">確認済みとして削除</button></form></section>';}
    echo '<p>連絡の保存期間は原則90日です。期限を過ぎたものは定期処理時または次回受付時に削除します。</p></div>';
}
add_action('admin_menu',function(){add_theme_page('ねこのとなり 運営状況','ねこのとなり運営','manage_options','nkt-operations','nkt_admin_page');});

function nkt_maintenance_status() {
    $data=nkt_maintenance_data();
    return hsh_edit_response(array('overdue_articles'=>nkt_due_articles($data['articles'],wp_date('Y-m-d')),'source_content_review_completed'=>false,'link_checks'=>get_option('nkt_reference_link_checks',array()),'next_scheduled_check'=>wp_next_scheduled('nkt_daily_maintenance'),'retained_correction_count'=>count(nkt_pruned_reports())));
}
function nkt_maintenance_run($request) {
    $data=$request->get_json_params();
    if(!is_array($data)||$data!==array('run'=>true)){return new WP_Error('nkt_input','run=trueだけを指定できます。',array('status'=>400));}
    if(!nkt_check_reference_links()){return new WP_Error('nkt_busy','点検を実行中です。',array('status'=>409));}
    return nkt_maintenance_status();
}
