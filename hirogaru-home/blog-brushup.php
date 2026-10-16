<?php
if (!defined('ABSPATH')) { exit; }

// Keep the theme's dependencies available without third-party CDN requests.
function hsh_local_core_script($src,$handle,$base) {
    $files=array('jquery-core'=>'jquery.min.js','jquery-migrate'=>'jquery-migrate.min.js');
    return isset($files[$handle]) ? $base.'js/jquery/'.$files[$handle] : $src;
}
add_filter('script_loader_src',function($src,$handle){
    return is_admin() ? $src : hsh_local_core_script($src,$handle,includes_url());
},20,2);
function hsh_local_footer_assets($html) {
    return preg_replace_callback('~(<script\b[^>]*\bsrc=[\x22\x27])(?:https?:)?//cdn\.jsdelivr\.net/clipboard\.js/1\.5\.13/clipboard\.min\.js([\x22\x27])~i',function($m){
        return $m[1].esc_url(plugin_dir_url(__FILE__).'assets/vendor/clipboard-1.5.13.min.js').$m[2];
    },$html);
}
// Cocoon prints this legacy tag after wp_footer, so filter the rendered HTML.
// WordPress flushes the buffer on shutdown; feeds, REST and admin do not use templates.
add_filter('template_include',function($template){
    ob_start('hsh_local_footer_assets');
    return $template;
},999);

function hsh_article_map() {
    $items=json_decode(file_get_contents(__DIR__.'/article-map.json'),true);
    return is_array($items)?$items:array();
}
function hsh_topic_reading_route($topic) {
    $routes=array(
        'cats'=>array('サイズを確認する'=>'cat-carrier-checklist-5','運び方を考える'=>'cat-carrier-checklist-2','確認メモを保存する'=>'cat-carrier-checklist-checklist'),
        'whisky'=>array('分量を確認する'=>'highball-measure-and-adjust-0','味と炭酸の違いを調べる'=>'highball-measure-and-adjust-2','配合メモを保存する'=>'highball-measure-and-adjust-checklist'),
        'it-freelance'=>array('元ファイルを残す'=>'csv-import-seven-checks-0','先頭の0を確認する'=>'csv-import-seven-checks-3','確認メモと練習用CSV'=>'csv-import-seven-checks-checklist'),
    );
    if(!isset($routes[$topic]))return '';
    $details=hsh_topic_details();$post=get_page_by_path($details[$topic][2],OBJECT,'post');
    if(!$post||$post->post_status!=='publish')return '';
    $out='<nav class="hsh-reading-route" aria-label="知りたい内容から読む"><h2>知りたい内容から読む</h2><ul>';
    foreach($routes[$topic] as $label=>$anchor){$out.='<li><a href="'.esc_url(get_permalink($post).'#'.$anchor).'">'.esc_html($label).' <span aria-hidden="true">→</span></a></li>';}
    return $out.'</ul></nav>';
}
function hsh_blog_extra_head($head,$url,$description,$title) {
    $tags=array('og:type'=>'website','og:locale'=>'ja_JP','og:site_name'=>'ひろがる趣味暮らし','og:title'=>$title,'og:description'=>$description,'og:url'=>$url,'og:image'=>plugin_dir_url(__FILE__).'assets/brand-avatar.webp','twitter:card'=>'summary');
    $out='';
    foreach($tags as $key=>$value){if(!preg_match('/<meta\b[^>]*(?:property|name)=[\'"]'.preg_quote($key,'/').'[\'"]/i',$head)){$out.='<meta property="'.esc_attr($key).'" content="'.esc_attr($value).'">' . "\n";}}
    if(strpos($head,'"@type":"WebSite"')===false && strpos($head,'"@type": "WebSite"')===false){
        $schema=array('@context'=>'https://schema.org','@type'=>'WebPage','@id'=>$url.'#webpage','url'=>$url,'name'=>$title,'description'=>$description,'inLanguage'=>'ja');
        $out.='<script type="application/ld+json">'.json_encode($schema,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>' . "\n";
    }
    return $out;
}
function hsh_article_review_note($slug) {
    foreach(hsh_article_map() as $a){if($a['slug']===$slug){return '<p class="hsh-review-note">本文の見直し：<time datetime="'.esc_attr($a['updated_at']).'">'.esc_html(str_replace('-','/',$a['updated_at'])).'</time> · 体験談と調べた情報を分けて掲載しています。</p>';}}
    return '';
}
function hsh_select_related($articles,$current_id,$limit=2) {
    $out=array();$seen=array();
    foreach($articles as $article){
        if((int)$article->ID===(int)$current_id||$article->post_status!=='publish'||isset($seen[$article->ID]))continue;
        $seen[$article->ID]=true;$out[]=$article;if(count($out)>=$limit)break;
    }
    return $out;
}
function hsh_related_markup($topic,$current_id) {
    $related=hsh_select_related(hsh_posts($topic,5),$current_id);
    if(!$related)return '';
    $out='<section class="hsh-related"><h2>あわせて読みたい記事</h2><ul>';
    foreach($related as $article){$out.='<li><a href="'.esc_url(get_permalink($article)).'">'.esc_html(get_the_title($article)).' →</a></li>';}
    return $out.'</ul></section>';
}
add_filter('wp_get_attachment_image_attributes',function($attrs,$attachment){
    if(is_singular('post')&&get_post_meta(get_queried_object_id(),'_hsh_starter_owned',true)&&(int)$attachment->ID===(int)get_post_thumbnail_id(get_queried_object_id())){
        $attrs['loading']='eager';$attrs['fetchpriority']='high';$attrs['decoding']='async';
    }
    return $attrs;
},10,2);
// Use the native WordPress search query with a readable result/empty-state template.
add_filter('template_include',function($template){return is_search()?__DIR__.'/search-template.php':$template;},101);
add_filter('wp_robots',function($robots){if(is_search()){$robots['noindex']=true;unset($robots['index']);}return $robots;});
function hsh_search_robots($head) {
    $tokens=array('noindex'=>true);
    $clean=preg_replace_callback('/<meta\b[^>]*>/i',function($match)use(&$tokens){
        if(!preg_match('/\bname\s*=\s*[\'"]robots[\'"]/i',$match[0]))return $match[0];
        if(preg_match('/\bcontent\s*=\s*([\'"])(.*?)\1/is',$match[0],$value)){
            foreach(explode(',',strtolower(html_entity_decode($value[2],ENT_QUOTES,'UTF-8'))) as $token){$token=trim($token);if($token!=='')$tokens[$token]=true;}
        }
        return '';
    },$head);
    unset($tokens['index']);if(isset($tokens['nofollow']))unset($tokens['follow']);
    return $clean.'<meta name="robots" content="'.esc_attr(implode(', ',array_keys($tokens))).'">' . "\n";
}

/** Read-only page metrics from an owner-supplied Search Console Pages CSV. */
function hsh_gsc_number($value) {
    $value=str_replace(',', '',trim($value));
    return preg_match('/^\d+(?:\.\d+)?$/D',$value)?(float)$value:null;
}
function hsh_gsc_priority($clicks,$impressions,$position) {
    if($impressions<50)return array('score'=>0,'action'=>'判断するにはデータが少ないため、期間を延ばして確認する');
    $ctr=$clicks/$impressions;
    if($position>5&&$position<=20)return array('score'=>3,'action'=>'検索語と記事の内容が合っているか確認し、説明の不足を見直す');
    if($impressions>=100&&$ctr<0.02)return array('score'=>2,'action'=>'検索語を確認してから、タイトルと説明文が内容を伝えているか見直す');
    return array('score'=>1,'action'=>'現在の内容を維持し、次の期間と比べる');
}
function hsh_gsc_parse($csv,$start,$end,$today=null) {
    $today=$today??wp_date('Y-m-d');
    if(!is_string($csv)||strlen($csv)>2000000||strpos($csv,"\0")!==false||preg_match('//u',$csv)!==1)return new WP_Error('hsh_gsc_size','UTF-8のCSVを2MB以内で選んでください。');
    foreach(array($start,$end) as $date){if(!is_string($date)||!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D',$date,$m)||!checkdate((int)$m[2],(int)$m[3],(int)$m[1]))return new WP_Error('hsh_gsc_date','集計期間を正しい日付で入力してください。');}
    if($start>$end||$end>$today)return new WP_Error('hsh_gsc_date','集計期間の順序と日付を確認してください。');
    $stream=fopen('php://temp','r+');fwrite($stream,preg_replace('/^\xEF\xBB\xBF/','',$csv));rewind($stream);
    try{
        $headers=fgetcsv($stream,0,',','"','');if(!is_array($headers))return new WP_Error('hsh_gsc_header','CSVの見出しを確認できません。');
        $aliases=array('page'=>array('上位のページ','最上位のページ','ページ','Top pages','Page'),'clicks'=>array('クリック数','Clicks'),'impressions'=>array('表示回数','Impressions'),'position'=>array('掲載順位','平均掲載順位','Position'));
        $columns=array();foreach($aliases as $field=>$labels){foreach($headers as $i=>$header){if(in_array(trim($header),$labels,true)){$columns[$field]=$i;break;}}}
        if(count($columns)!==4)return new WP_Error('hsh_gsc_header','Search Consoleの「ページ」タブのCSVを選んでください。検索語や日付だけのCSVは取り込めません。');
        $site=wp_parse_url(home_url('/'));$rows=array();$seen=array();$skipped=0;$count=0;
        while(($values=fgetcsv($stream,0,',','"',''))!==false){
            if(count($values)===1&&($values[0]===null||$values[0]===''))continue;
            if(++$count>2000)return new WP_Error('hsh_gsc_rows','一度に取り込めるのは2000行までです。');
            $page=trim($values[$columns['page']]??'');$parts=wp_parse_url($page);
            if(!$parts||($parts['scheme']??'')!=='https'||strtolower($parts['host']??'')!==strtolower($site['host'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])||isset($parts['port'])||preg_match('~/(?:wp-admin|wp-login|wp-json|wp-content)(?:/|\.|$)~i',$parts['path']??'')||preg_match('~^/neko-guide(?:/|$)~',rawurldecode($parts['path']??''))||strlen($page)>2000){$skipped++;continue;}
            $clicks=hsh_gsc_number($values[$columns['clicks']]??'');$impressions=hsh_gsc_number($values[$columns['impressions']]??'');$position=hsh_gsc_number($values[$columns['position']]??'');
            if($clicks===null||$impressions===null||$position===null||$clicks!==(float)(int)$clicks||$impressions!==(float)(int)$impressions||$clicks>$impressions||$position>1000||($impressions>0&&$position<1)||isset($seen[$page])){$skipped++;continue;}
            $seen[$page]=true;$priority=hsh_gsc_priority($clicks,$impressions,$position);
            $rows[]=array('page'=>$page,'clicks'=>(int)$clicks,'impressions'=>(int)$impressions,'ctr'=>$impressions?$clicks/$impressions:0,'position'=>$position,'priority'=>$priority['score'],'action'=>$priority['action']);
        }
        if(!$rows)return new WP_Error('hsh_gsc_empty','取り込める自サイトのページデータがありません。');
        usort($rows,function($a,$b){return ($b['priority']<=>$a['priority'])?:($b['impressions']<=>$a['impressions']);});
        return array('source'=>'manual_search_console_pages_csv','start'=>$start,'end'=>$end,'rows'=>$rows,'skipped'=>$skipped);
    }finally{fclose($stream);}
}
function hsh_gsc_admin() {
    if(!current_user_can('manage_options'))return;
    $message='';$error='';
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
        check_admin_referer('hsh_gsc_import');
        if(isset($_POST['hsh_gsc_clear'])){delete_option('hsh_gsc_pages');$message='取り込んだ集計データを削除しました。';}
        elseif(isset($_FILES['hsh_gsc_csv'])){
            $file=$_FILES['hsh_gsc_csv'];
            if(!is_array($file)||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!isset($file['tmp_name'],$file['size'])||!is_string($file['tmp_name'])||!is_int($file['size'])||$file['size']>2000000||!is_uploaded_file($file['tmp_name'])){$error='2MB以内のCSVを選び直してください。';}
            else{
                $result=hsh_gsc_parse(file_get_contents($file['tmp_name']),sanitize_text_field(wp_unslash($_POST['hsh_gsc_start']??'')),sanitize_text_field(wp_unslash($_POST['hsh_gsc_end']??'')));
                if(is_wp_error($result)){$error=$result->get_error_message();}else{$result['imported_at']=time();if(update_option('hsh_gsc_pages',$result,false)){$message='ページ別の集計を取り込みました。自動接続ではありません。';}else{$error='保存できませんでした。以前の集計はそのままです。';}}
            }
        }
    }
    echo '<div class="wrap"><h1>検索データの確認</h1><p>Search Consoleで「検索パフォーマンス」→「ページ」を開き、期間を選んでCSVをエクスポートしてください。「ページ」のCSVだけをここに取り込みます。ZIPで保存された場合は展開して「ページ.csv」を選んでください。</p><p>データがまだなければ、取り込まずにお待ちください。表示回数や順位を推測して補うことはありません。検索語との対応はCSVだけでは分からないため、改善前にGoogleの画面でも確認します。</p>';
    if($message)echo '<div class="notice notice-success"><p>'.esc_html($message).'</p></div>';
    if($error)echo '<div class="notice notice-error"><p>'.esc_html($error).'</p></div>';
    echo '<form method="post" enctype="multipart/form-data">';wp_nonce_field('hsh_gsc_import');
    echo '<p><label>集計開始日 <input type="date" name="hsh_gsc_start" required></label> <label>集計終了日 <input type="date" name="hsh_gsc_end" required max="'.esc_attr(wp_date('Y-m-d')).'"></label></p><p><label>ページのCSV（UTF-8・2MB以内） <input type="file" name="hsh_gsc_csv" accept=".csv,text/csv" required></label></p><p><button class="button button-primary">取り込んで改善候補を確認</button></p></form>';
    $data=get_option('hsh_gsc_pages',array());
    if(!empty($data['rows'])){
        echo '<h2>改善を検討するページ</h2><p>指定した集計期間：'.esc_html($data['start']).'〜'.esc_html($data['end']).'。対象外のURL・形式不正・重複などで取り込まなかった行：'.(int)$data['skipped'].'。</p><p>優先度は表示回数・クリック率・順位から付けた目安で、改善効果の保証ではありません。実際の検索語と記事を読んでから修正します。</p><table class="widefat striped"><thead><tr><th>ページ</th><th>クリック</th><th>表示</th><th>クリック率</th><th>平均順位</th><th>確認すること</th></tr></thead><tbody>';
        foreach($data['rows'] as $row){echo '<tr><td><a href="'.esc_url($row['page']).'">'.esc_html(rawurldecode(wp_parse_url($row['page'],PHP_URL_PATH))).'</a></td><td>'.(int)$row['clicks'].'</td><td>'.(int)$row['impressions'].'</td><td>'.esc_html(number_format($row['ctr']*100,2)).'%</td><td>'.esc_html(number_format($row['position'],1)).'</td><td>'.esc_html($row['action']).'</td></tr>';}
        echo '</tbody></table><form method="post">';wp_nonce_field('hsh_gsc_import');echo '<p><button class="button" name="hsh_gsc_clear" value="1">集計データを削除する</button></p></form>';
    }else{echo '<h2>集計データは未取込です</h2><p>Googleの所有権確認・サイトマップ送信と、検索実績の取得は別です。まだ改善対象の記事は選んでいません。</p>';}
    echo '<h2>記事の見直し予定</h2><table class="widefat striped"><thead><tr><th>記事</th><th>本文見直し</th><th>次回の確認目安</th><th>外部資料の照合</th></tr></thead><tbody>';
    foreach(hsh_article_map() as $a){echo '<tr><td>'.esc_html($a['title']).'</td><td>'.esc_html($a['updated_at']).'</td><td>'.esc_html($a['review_due_at']).'</td><td>未完了。現行内容の確認が必要</td></tr>';}
    echo '</tbody></table><p>取り込んだ集計はこの管理画面だけで表示します。APIキーやGoogleのパスワードは必要ありません。最後に取り込んだ集計を保持し、上のボタンで削除できます。</p></div>';
}
add_action('admin_menu',function(){add_theme_page('検索データの確認','検索データの確認','manage_options','hsh-search-review','hsh_gsc_admin');});
