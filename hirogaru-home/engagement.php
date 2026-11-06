<?php
if(!defined('ABSPATH'))exit;
/** Anonymous daily totals only: no visitor IDs, referrers, query terms or raw addresses. */
function hsh_engagement_allowed($slug){foreach(hsh_affiliate_articles() as $a)if($a['slug']===$slug){$p=get_page_by_path($slug,OBJECT,'post');return $p&&$p->post_status==='publish'&&hsh_owned_blog_post($p);}return false;}
function hsh_engagement_receive($request){
    $origin=$_SERVER['HTTP_ORIGIN']??'';$host=wp_parse_url(home_url('/'),PHP_URL_HOST);
    if(wp_parse_url($origin,PHP_URL_HOST)!==$host||wp_parse_url($origin,PHP_URL_SCHEME)!=='https'||(isset($_SERVER['HTTP_SEC_FETCH_SITE'])&&$_SERVER['HTTP_SEC_FETCH_SITE']!=='same-origin'))return new WP_Error('hsh_origin','送信元を確認できません。',array('status'=>403));
    if(($_SERVER['HTTP_DNT']??'')==='1'||($_SERVER['HTTP_SEC_GPC']??'')==='1')return array('recorded'=>false);
    $d=$request->get_json_params();if(!is_array($d)||array_keys($d)!==array('slug')||!is_string($d['slug'])||!hsh_engagement_allowed($d['slug']))return new WP_Error('hsh_event','対象の記事を確認してください。',array('status'=>400));
    // Temporary keyed rate limit is unrelated to the aggregate record and expires in one minute.
    $key='hsh_ec_'.substr(hash_hmac('sha256',$_SERVER['REMOTE_ADDR']??'unknown',wp_salt('nonce')),0,24);$count=(int)get_transient($key);if($count>=20)return new WP_Error('hsh_limit','時間をおいてください。',array('status'=>429));
    if(!add_option('hsh_engagement_lock',time(),'',false)){if(time()-(int)get_option('hsh_engagement_lock',time())>120)delete_option('hsh_engagement_lock');return new WP_Error('hsh_busy','集計中です。',array('status'=>503));}
    try{$rows=get_option('hsh_ad_click_counts',array());if(!is_array($rows))$rows=array();$day=wp_date('Y-m-d');$rows[$day][$d['slug']]=min(1000000,max(0,(int)($rows[$day][$d['slug']]??0))+1);$cut=wp_date('Y-m-d',time()-90*DAY_IN_SECONDS);foreach($rows as $date=>$v)if($date<$cut||!preg_match('/^20\d\d-\d\d-\d\d$/D',$date))unset($rows[$date]);$ok=update_option('hsh_ad_click_counts',$rows,false);set_transient($key,$count+1,MINUTE_IN_SECONDS);return array('recorded'=>$ok);}finally{delete_option('hsh_engagement_lock');}
}
function hsh_engagement_totals($month){$out=array();foreach((array)get_option('hsh_ad_click_counts',array()) as $day=>$rows)if(substr($day,0,7)===$month)foreach($rows as $slug=>$n)if(hsh_engagement_allowed($slug))$out[$slug]=($out[$slug]??0)+max(0,(int)$n);arsort($out);return $out;}
function hsh_engagement_status(){return hsh_edit_response(array('month'=>wp_date('Y-m'),'click_events'=>hsh_engagement_totals(wp_date('Y-m')),'retention_days'=>90,'monthly_snapshots'=>get_option('hsh_monthly_reviews',array()),'scope'=>'匿名の広告リンククリック通知数。売上・成果・訪問者数ではありません。'));}
add_action('rest_api_init',function(){register_rest_route('hirogaru/v1','/ad-click',array('methods'=>'POST','callback'=>'hsh_engagement_receive','permission_callback'=>'__return_true'));register_rest_route('hirogaru/v1','/engagement-status',array('methods'=>'GET','callback'=>'hsh_engagement_status','permission_callback'=>'hsh_edit_permission'));});
add_action('wp_footer',function(){if(!hsh_reader_scope())return;echo '<script id="hsh-engagement-config" type="application/json">'.wp_json_encode(array('endpoint'=>rest_url('hirogaru/v1/ad-click'),'current'=>hsh_reader_managed()?get_post_field('post_name',get_queried_object_id()):null),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>';},20);
function hsh_monthly_review_capture(){
    $month=wp_date('Y-m',strtotime('first day of last month'));$rows=get_option('hsh_monthly_reviews',array());if(!is_array($rows))$rows=array();
    if(!isset($rows[$month]))$rows[$month]=array('captured_at'=>time(),'month'=>$month,'ad_tracking_started'=>'2026-11-06','reach'=>hsh_growth_month($month),'ad_click_events'=>hsh_engagement_totals($month),'changes'=>$month==='2026-11'?array('2026-11-06：深緑と白の配色、案件タイトル整備、検索・保存・商品比較の改善'):array(),'note'=>'到達件数はPVではなく、広告クリック通知は成果件数ではありません。検索実績は取込済みの集計期間を参照。');ksort($rows);update_option('hsh_monthly_reviews',array_slice($rows,-12,null,true),false);
    $counts=get_option('hsh_ad_click_counts',array());$cut=wp_date('Y-m-d',time()-90*DAY_IN_SECONDS);foreach((array)$counts as $day=>$v)if($day<$cut)unset($counts[$day]);update_option('hsh_ad_click_counts',$counts,false);
}
add_action('hsh_monthly_review','hsh_monthly_review_capture');
add_action('init',function(){if(!wp_next_scheduled('hsh_monthly_review'))wp_schedule_event(time()+HOUR_IN_SECONDS,'daily','hsh_monthly_review');});
function hsh_engagement_admin(){
    if(!current_user_can('manage_options'))return;$month=isset($_GET['month'])&&is_string($_GET['month'])&&preg_match('/^20\d\d-(?:0[1-9]|1[0-2])$/D',$_GET['month'])?$_GET['month']:wp_date('Y-m');
    echo '<div class="wrap"><h1>広告リンクと月次記録</h1><p>日付と記事ごとのクリック通知数のみを保存します。訪問者ID・検索語・参照元は記録しません。DNT/GPCが有効なブラウザーでは送信しません。広告の成果や正確なクリック総数ではありません。</p><form><input type="hidden" name="page" value="hsh-engagement"><input type="month" name="month" value="'.esc_attr($month).'"><button class="button">表示</button></form><h2>'.esc_html($month).'の広告リンク</h2><table class="widefat striped"><thead><tr><th>記事</th><th>クリック通知</th></tr></thead><tbody>';
    $totals=hsh_engagement_totals($month);foreach($totals as $slug=>$n){$p=get_page_by_path($slug,OBJECT,'post');echo '<tr><td><a href="'.esc_url(get_permalink($p)).'">'.esc_html(get_the_title($p)).'</a></td><td>'.(int)$n.'</td></tr>';}echo '</tbody></table>';if(!$totals)echo '<p>この月の記録はまだありません。</p>';
    echo '<h2>保存した月次記録</h2><p>前月の到達・広告クリック・取り込み済み検索実績を自動で控え、12か月分残します。WP-Cronはサイトへのアクセスをきっかけに実行されます。</p>';
    foreach(array_reverse((array)get_option('hsh_monthly_reviews',array()),true) as $m=>$row)echo '<details><summary>'.esc_html($m).'</summary><pre>'.esc_html(wp_json_encode($row,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)).'</pre></details>';
    echo '<p><a href="'.esc_url(admin_url('themes.php?page=hsh-growth')).'">到達件数・検索実績を見る</a></p></div>';
}
add_action('admin_menu',function(){add_theme_page('広告リンクと月次記録','広告リンクと月次記録','manage_options','hsh-engagement','hsh_engagement_admin');});
add_filter('the_content',function($c){$p=get_post();if($p&&is_main_query()&&in_the_loop()&&rawurldecode($p->post_name)==='プライバシーポリシー')$c.='<section><h2>広告リンクの匿名集計</h2><p>記事の広告リンクが押された回数を、日付と記事ごとの合計として記録します。訪問者ID、検索語、参照元をこの集計に保存することはありません。クリック通知の送信にCookieやブラウザー保存領域は使用せず、DNTまたはGPCが有効な場合は送信しません。日別記録は90日、月次の合計記録は12か月保存します。不正な連続送信を抑えるため、接続元を秘密鍵付きで変換した一時キーを最大1分間使用します。</p></section>';return $c;},30);
