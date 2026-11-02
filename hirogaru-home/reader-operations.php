<?php
if (!defined('ABSPATH')) exit;

function hsh_reader_redirect_value($source,$target,$catalog=null) {
    if(!is_string($source)||!is_string($target)||strlen($source)>300||!preg_match('~^/[a-z0-9][a-z0-9/-]*/$~D',$source)||strpos($source,'//')!==false||preg_match('~/(?:wp-|neko-guide|freelance|paper-tool|hsh-)[^/]*~',$source)) return new WP_Error('hsh_redirect_path','転送元は、このブログの古い記事パスを指定してください。');
    $catalog=$catalog??hsh_reader_catalog();$found=null;
    foreach($catalog as $a)if($a['id']===$target)$found=$a;
    if(!$found||wp_parse_url($found['url'],PHP_URL_HOST)!==wp_parse_url(home_url('/'),PHP_URL_HOST))return new WP_Error('hsh_redirect_target','公開中の管理記事を転送先に選んでください。');
    if(wp_parse_url($found['url'],PHP_URL_PATH)===$source)return new WP_Error('hsh_redirect_loop','同じURLへの転送は登録できません。');
    foreach($catalog as $a)if(wp_parse_url($a['url'],PHP_URL_PATH)===$source)return new WP_Error('hsh_redirect_live','現在の記事URLを転送元にはできません。');
    return array('source'=>$source,'target'=>$target);
}
add_action('template_redirect',function() {
    if(!is_404()||!in_array($_SERVER['REQUEST_METHOD']??'GET',array('GET','HEAD'),true))return;
    $path=wp_parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);$map=get_option('hsh_reader_redirects',array());
    if(!is_string($path)||!isset($map[$path]))return;
    $v=hsh_reader_redirect_value($path,$map[$path]);if(is_wp_error($v))return;
    foreach(hsh_reader_catalog() as $a)if($a['id']===$v['target']){wp_safe_redirect($a['url'],301,'Hirogaru legacy article');exit;}
},1);
function hsh_reader_snapshot($label) {
    if(!is_string($label)||strlen($label)>300||trim($label)==='')return false;
    $old_lock=get_option('hsh_reader_checkpoint_lock',0);if(is_numeric($old_lock)&&(int)$old_lock>0&&time()-(int)$old_lock>120&&get_option('hsh_reader_checkpoint_lock',0)===$old_lock)delete_option('hsh_reader_checkpoint_lock');
    if(!add_option('hsh_reader_checkpoint_lock',time(),'',false))return false;
    try {
    $rows=get_option('hsh_reader_checkpoints',array());if(!is_array($rows))$rows=array();
    $posts=array();foreach(hsh_catalog() as $a){$p=get_page_by_path($a['slug'],OBJECT,'post');if($p&&hsh_owned_blog_post($p))$posts[$a['slug']]=array('title'=>$p->post_title,'fingerprint'=>hsh_article_fingerprint($p));}
    $rows[]=array('at'=>time(),'label'=>sanitize_text_field($label),'version'=>'1.24.1','articles'=>$posts,'requests'=>get_option('hsh_request_counts',array()),'search_pages'=>get_option('hsh_gsc_pages',null),'search_queries'=>get_option('hsh_gsc_queries',null));
    return update_option('hsh_reader_checkpoints',array_slice($rows,-12),false);
    } finally { delete_option('hsh_reader_checkpoint_lock'); }
}
function hsh_reader_metric_history($value,$old,$option) {
    if($old===$value)return $value;
    if($option==='hsh_gsc_queries')delete_option('hsh_reader_query_scope');
    $history=get_option('hsh_reader_metric_history',array());if(!is_array($history))$history=array();
    $history[]=array('at'=>time(),'option'=>$option,'data'=>$value);update_option('hsh_reader_metric_history',array_slice($history,-12),false);
    return $value;
}
foreach(array('hsh_gsc_pages','hsh_gsc_queries') as $option)add_filter('pre_update_option_'.$option,'hsh_reader_metric_history',10,3);

function hsh_reader_search_growth($current,$history){
    if(empty($current['rows'])||empty($current['start'])||empty($current['end']))return array();$days=strtotime($current['end'])-strtotime($current['start']);$previous=null;
    foreach((array)$history as $entry){$d=$entry['data']??array();if(($entry['option']??'')!=='hsh_gsc_pages'||empty($d['rows'])||empty($d['start'])||empty($d['end'])||$d['end']>=$current['start']||strtotime($d['end'])-strtotime($d['start'])!==$days)continue;if(!$previous||$d['end']>$previous['end'])$previous=$d;}
    if(!$previous)return array();$old=array();foreach($previous['rows'] as $row)$old[$row['page']]=$row;$out=array();
    foreach($current['rows'] as $r){$before=$old[$r['page']]??array('clicks'=>0,'impressions'=>0);if($r['impressions']>=50&&$r['clicks']>$before['clicks'])$out[]=array('page'=>$r['page'],'before_clicks'=>$before['clicks'],'clicks'=>$r['clicks'],'before_impressions'=>$before['impressions'],'impressions'=>$r['impressions'],'previous_start'=>$previous['start'],'previous_end'=>$previous['end']);}return $out;
}

function hsh_reader_audit_status() {
    $pages=get_option('hsh_gsc_pages',null);$queries=get_option('hsh_gsc_queries',null);$checkpoints=get_option('hsh_reader_checkpoints',array());
    return hsh_edit_response(array('version'=>'1.24.1','published_catalog_count'=>count(hsh_reader_catalog()),'redirects'=>get_option('hsh_reader_redirects',array()),'checkpoints'=>array_map(function($r){return array('at'=>$r['at'],'label'=>$r['label'],'version'=>$r['version']);},is_array($checkpoints)?$checkpoints:array()),'search_metrics'=>array('pages'=>$pages,'queries'=>$queries),'search_pages_imported'=>is_array($pages),'search_queries_imported'=>is_array($queries),'query_scope'=>get_option('hsh_reader_query_scope',''),'metric_history_count'=>count((array)get_option('hsh_reader_metric_history',array()))));
}
add_action('rest_api_init',function() {
    register_rest_route('hirogaru/v1','/reader-audit',array(
        array('methods'=>'GET','permission_callback'=>'hsh_edit_permission','callback'=>'hsh_reader_audit_status'),
        array('methods'=>'POST','permission_callback'=>'hsh_edit_permission','callback'=>function($request) {
            $d=$request->get_json_params();if(!is_array($d)||array_diff(array_keys($d),array('label'))||!isset($d['label'])||!is_string($d['label'])||strlen($d['label'])>300||trim($d['label'])==='')return new WP_Error('hsh_reader_input','短い作業名を指定してください。',array('status'=>400));
            if(!hsh_reader_snapshot($d['label']))return new WP_Error('hsh_reader_save','控えを保存できませんでした。',array('status'=>503));return hsh_reader_audit_status();
        })
    ));
});
function hsh_reader_operations() {
    if(!current_user_can('manage_options'))return;
    $message='';
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
        check_admin_referer('hsh_reader_ops');$action=sanitize_key(wp_unslash($_POST['reader_action']??''));
        if($action==='checkpoint')$message=hsh_reader_snapshot(wp_unslash($_POST['label']??'作業の控え'))?'改善前後の控えを保存しました。':'保存できませんでした。';
        if($action==='redirect') {
            $r=hsh_reader_redirect_value(wp_unslash($_POST['source']??''),wp_unslash($_POST['target']??''));
            if(is_wp_error($r))$message=$r->get_error_message();else{$map=get_option('hsh_reader_redirects',array());if(count($map)>=50&&!isset($map[$r['source']]))$message='登録上限は50件です。';else{$map[$r['source']]=$r['target'];$message=update_option('hsh_reader_redirects',$map,false)?'旧URLの転送を登録しました。':'変更はありません。';}}
        }
        if($action==='remove-redirect'){$map=get_option('hsh_reader_redirects',array());$path=wp_unslash($_POST['source']??'');if(is_string($path)&&isset($map[$path])){unset($map[$path]);update_option('hsh_reader_redirects',$map,false);$message='転送の登録を外しました。';}}
        if($action==='query-scope'){$target=wp_unslash($_POST['target']??'');$ids=array_column(hsh_reader_catalog(),'id');if($target===''||in_array($target,$ids,true)){update_option('hsh_reader_query_scope',$target,false);$message='検索語CSVを取得したときの対象ページを保存しました。';}}
    }
    echo '<div class="wrap"><h1>記事の改善と効果の確認</h1><p>検索実績、改善前後の控え、旧URLの転送をまとめます。ここで保存しても本文や広告は自動で公開されません。</p>';
    if($message)echo '<div class="notice notice-info"><p>'.esc_html($message).'</p></div>';
    echo '<h2>検索実績から確認する記事</h2>';$pages=get_option('hsh_gsc_pages',array());$queries=get_option('hsh_gsc_queries',array());
    if(empty($pages['rows']))echo '<p>ページ別の検索実績は未取込です。実際の順位やクリック率を使った記事の選定は、CSVが届いてから行います。</p>';
    else{echo '<p>集計期間：'.esc_html($pages['start'].'〜'.$pages['end']).'。候補の根拠を確認してから編集します。</p><table class="widefat striped"><thead><tr><th>ページ</th><th>表示</th><th>クリック</th><th>平均順位</th><th>次に確認すること</th></tr></thead><tbody>';foreach(array_slice($pages['rows'],0,20) as $r)echo '<tr><td><a href="'.esc_url($r['page']).'">'.esc_html($r['page']).'</a></td><td>'.(int)$r['impressions'].'</td><td>'.(int)$r['clicks'].'</td><td>'.esc_html($r['position']).'</td><td>'.esc_html($r['action']).'</td></tr>';echo '</tbody></table>';}
    echo '<h3>読まれ始めた記事から、次を考える</h3>';$growth=hsh_reader_search_growth($pages,get_option('hsh_reader_metric_history',array()));if(!$growth)echo '<p>同じ長さで重ならない2期間の実績がそろうと、表示回数50以上でクリックが増えた記事をここに出します。まだ判断できるデータはありません。</p>';else{echo '<ul>';foreach($growth as $r)echo '<li><a href="'.esc_url($r['page']).'">'.esc_html($r['page']).'</a>：クリック '.(int)$r['before_clicks'].' → '.(int)$r['clicks'].'、表示 '.(int)$r['before_impressions'].' → '.(int)$r['impressions'].'。検索語を確かめ、関連する記事で補える内容を検討します。</li>';echo '</ul><p>増減だけで、編集の効果や順位上昇を断定しません。集計期間と検索語も合わせて確認します。</p>';}
    echo '<p><a href="'.esc_url(admin_url('themes.php?page=hsh-search-review')).'">ページ別CSVを取り込む</a> ／ <a href="'.esc_url(admin_url('themes.php?page=hsh-growth')).'">検索語CSVを取り込む</a></p><h3>検索語を取得したページの記録</h3><p>Googleの画面でページを絞って取得したCSVの場合だけ、その対象ページを指定します。サイト全体の検索語を特定の記事へ自動で割り当てません。</p><form method="post">';wp_nonce_field('hsh_reader_ops');
    echo '<input type="hidden" name="reader_action" value="query-scope"><select name="target"><option value="">サイト全体・対象未指定</option>';foreach(hsh_reader_catalog() as $a)echo '<option value="'.esc_attr($a['id']).'" '.selected(get_option('hsh_reader_query_scope',''),$a['id'],false).'>'.esc_html($a['title']).'</option>';echo '</select> <button class="button">保存する</button></form>';
    echo '<h2>改善前後の控え</h2><p>記事の指紋、到達件数の概数、取り込まれた検索実績を最大12回分保存します。検索実績の期間が違う場合は、そのまま増減として比較しません。</p><form method="post">';wp_nonce_field('hsh_reader_ops');echo '<input type="hidden" name="reader_action" value="checkpoint"><label>作業名 <input type="text" name="label" maxlength="100" required></label> <button class="button">今の状態を控える</button></form><ul>';
    foreach(array_reverse((array)get_option('hsh_reader_checkpoints',array())) as $r){$summary=empty($r['search_pages']['rows'])?'検索実績なし':$r['search_pages']['start'].'〜'.$r['search_pages']['end'].'の検索実績';echo '<li>'.esc_html(wp_date('Y/m/d H:i',$r['at']).' '.$r['label'].' ／ '.$summary).'</li>';}echo '</ul>';
    echo '<h2>古い記事URLの転送</h2><p>URLを変えた場合に使います。現在の公開記事や管理画面、別サイトへの転送は登録できません。既存URLは今回変更していません。WordPress標準の旧スラッグ転送もそのまま使います。</p><form method="post">';wp_nonce_field('hsh_reader_ops');echo '<input type="hidden" name="reader_action" value="redirect"><label>古いパス <input type="text" name="source" placeholder="/old-article/" required></label> <label>転送先 <select name="target" required><option value="">公開記事を選ぶ</option>';foreach(hsh_reader_catalog() as $a)echo '<option value="'.esc_attr($a['id']).'">'.esc_html($a['title']).'</option>';echo '</select></label> <button class="button">登録する</button></form><ul>';
    foreach((array)get_option('hsh_reader_redirects',array()) as $path=>$target){echo '<li><form method="post">';wp_nonce_field('hsh_reader_ops');echo esc_html($path.' → '.$target).'<input type="hidden" name="source" value="'.esc_attr($path).'"><button class="button" name="reader_action" value="remove-redirect">登録を外す</button></form></li>';}echo '</ul>';
    echo '<h2>案件と使用後の記録</h2><p><a href="'.esc_url(admin_url('admin.php?page=hsh-editorial-planner')).'">記事・写真管理</a>に、案件との相性、使用前の記録、使用後の追記欄を追加しました。案件は未選定のままです。提携前の商品リンクを自動で追加しません。</p><p>広告を載せる場合は、選んだ理由・向く人・合わない人の説明の後へ置き、PRの表示とリンク先の条件を確認します。</p></div>';
}
add_action('admin_menu',function(){add_submenu_page('hsh-editorial-planner','記事の改善と効果の確認','改善と効果','manage_options','hsh-reader-operations','hsh_reader_operations');});
