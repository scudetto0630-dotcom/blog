<?php
if(!defined('ABSPATH'))exit;
function hsh_planner_article_fields(){return array('title','topic','stage','affiliate','experience','product','link','notes','photo_notes','due','reason','usage','pros','cons','unknowns','source_notes','offer_fit','pre_use','followups','draft_text');}
function hsh_planner_photo_fields(){return array('title','kind','subject','purpose','rights','notes','articles','sources');}
function hsh_planner_with_history($next,$current){
    $history=is_array($current)?($current['history']??array()):array();
    if(is_array($current)){$old=$current;unset($old['history']);$history[]=$old;}
    $next['history']=array_slice($history,-5);return $next;
}
function hsh_planner_enrich($data,$store){
    $hashes=array();$manifest=hsh_photo_manifest();
    foreach($data['articles'] as $id=>&$a){foreach(array('reason','usage','pros','cons','unknowns','source_notes','offer_fit','pre_use','followups','draft_text') as $k)if(!isset($a[$k]))$a[$k]='';$a['history']=$a['history']??array();$a['due_soon']=!empty($a['due'])&&$a['due']<=wp_date('Y-m-d',time()+14*DAY_IN_SECONDS);$a['needs_experience']=$a['experience']==='unknown';$a['photo_waiting']=trim($a['photo_notes'])!=='';$a['has_reference']=trim($a['source_notes'])!=='';}unset($a);
    foreach($data['photos'] as $id=>&$p){
        $p['placements']=array();$p['history']=$p['history']??array();$p['sha256']='';$p['exif_status']='未点検';
        if(strpos($id,'asset:')===0){$key=substr($id,6);$asset=$manifest['assets'][$key];$p['sha256']=$asset['sha256']??'';$p['exif_status']='WebP化済み。原本の位置情報とは別に管理';
            foreach($manifest['articles'] as $slug=>$cover)if($cover===$key)$p['placements'][]=array('label'=>'表紙','article'=>'article:'.$slug);
            foreach($manifest['inline']??array() as $slug=>$keys)foreach($keys as $i=>$k)if($k===$key)$p['placements'][]=array('label'=>'本文写真'.($i+1),'article'=>'article:'.$slug);
            if(in_array($key,array('manmos-back','manmos-stretch'),true))$p['placements'][]=array('label'=>'記事末尾のギャラリー','article'=>'article:manmos-cat-life');
            if($key===($manifest['home']??'manmos-tower'))$p['placements'][]=array('label'=>'ホームの大きな写真','article'=>'');
            foreach($manifest['collections']??array() as $collection=>$keys)if(in_array($key,$keys,true))$p['placements'][]=array('label'=>$collection==='hakushu-visit'?'白州の見学写真':'ウイスキーの展示写真一覧','article'=>'');
            if($key==='manmos-front')$p['placements'][]=array('label'=>'プロフィール','article'=>'');
            foreach($manifest['topics']??array() as $topic=>$k)if($k===$key)$p['placements'][]=array('label'=>'ホーム・'.(hsh_planner_labels()['topic'][$topic]??$topic),'article'=>'');
        }elseif(strpos($id,'media:')===0){
            $media=(int)substr($id,6);$meta=wp_get_attachment_metadata($media);$p['exif_status']=!empty($meta['image_meta'])?'撮影情報あり。掲載前に確認':'WordPressの記録では撮影情報を確認できません';
            $file=get_attached_file($media);if(is_string($file)&&is_file($file)&&filesize($file)<=20000000){$cache_key='hsh_photo_hash_'.$media;$stamp=filemtime($file).':'.filesize($file);$cached=get_transient($cache_key);if(is_array($cached)&&($cached['stamp']??'')===$stamp)$p['sha256']=$cached['hash'];else{$p['sha256']=hash_file('sha256',$file);set_transient($cache_key,array('stamp'=>$stamp,'hash'=>$p['sha256']),DAY_IN_SECONDS);}}
            foreach($p['used_in'] as $article)$p['placements'][]=array('label'=>'本文','article'=>$article);
            foreach($data['articles'] as $aid=>$a){$post=strpos($aid,'article:')===0?get_page_by_path(substr($aid,8),OBJECT,'post'):(strpos($aid,'post:')===0?get_post((int)substr($aid,5)):null);if($post&&(int)get_post_thumbnail_id($post->ID)===$media)$p['placements'][]=array('label'=>'アイキャッチ','article'=>$aid);}
        }
        if($p['sha256']!=='')$hashes[$p['sha256']][]=$id;
    }unset($p);
    foreach($data['photos'] as $id=>&$p){$p['duplicates']=array_values(array_diff($hashes[$p['sha256']]??array(),array($id)));$p['similar_candidates']=array();$key=strpos($id,'asset:')===0?substr($id,6):'';$hash=$manifest['assets'][$key]['dhash']??'';if($hash!=='')foreach($manifest['assets'] as $other=>$asset){if($other===$key||empty($asset['dhash']))continue;$distance=0;for($i=0;$i<16;$i++){$xor=hexdec($hash[$i])^hexdec($asset['dhash'][$i]);$distance+=substr_count(decbin($xor),'1');}if($distance<=5)$p['similar_candidates'][]='asset:'.$other;}}unset($p);
    $data['backup']=array('format'=>'hsh-planner-backup-v1','site'=>home_url('/'),'exported_at'=>gmdate('c'),'records'=>$store?:new stdClass());
    $data['store_fingerprint']=hsh_planner_fingerprint($store);return $data;
}
function hsh_planner_advanced_save($d){
    if(!is_array($d)||!isset($d['action'])||!is_string($d['action']))return new WP_Error('hsh_planner_input','操作を確認してください。',array('status'=>400));
    $action=$d['action'];
    if($action==='restore'){
        if(array_diff(array_keys($d),array('action','id','expected','revision'))||!isset($d['id'],$d['expected'],$d['revision'])||!is_string($d['id'])||!is_int($d['revision']))return new WP_Error('hsh_planner_input','復元する記録を選んでください。',array('status'=>400));
        $store=hsh_planner_store();$current=$store[$d['id']]??null;$old=$current['history'][$d['revision']]??null;
        if(!is_array($old))return new WP_Error('hsh_planner_history','選んだ履歴がありません。',array('status'=>400));
        $keys=strpos($d['id'],'asset:')===0||strpos($d['id'],'media:')===0?hsh_planner_photo_fields():hsh_planner_article_fields();
        return hsh_planner_save(array('action'=>'save','id'=>$d['id'],'expected'=>$d['expected'],'fields'=>array_intersect_key($old,array_flip($keys))));
    }
    $old_lock=get_option('hsh_planner_lock',0);if(is_numeric($old_lock)&&(int)$old_lock>0&&time()-(int)$old_lock>120&&get_option('hsh_planner_lock',0)===$old_lock)delete_option('hsh_planner_lock');
    if(!add_option('hsh_planner_lock',time(),'',false))return new WP_Error('hsh_planner_busy','ほかの保存を処理中です。',array('status'=>409));
    try{
        $store=hsh_planner_store();$before=$store;$snapshot=hsh_planner_snapshot();
        if($action==='link'){
            if(array_diff(array_keys($d),array('action','id','expected','target','expected_target'))||!isset($d['id'],$d['target'])||!is_string($d['id'])||!is_string($d['target'])||!array_key_exists('expected',$d)||!array_key_exists('expected_target',$d)||strpos($d['id'],'plan:')!==0||!isset($store[$d['id']],$snapshot['articles'][$d['target']])||strpos($d['target'],'plan:')===0)return new WP_Error('hsh_planner_link','引き継ぎ先の記事を選んでください。',array('status'=>400));
            $source=$store[$d['id']];$target=$store[$d['target']]??null;
            if($d['expected']!==hsh_planner_fingerprint($source)||$d['expected_target']!==(is_array($target)?hsh_planner_fingerprint($target):null))return new WP_Error('hsh_planner_conflict','管理表が変わりました。更新してから引き継いでください。',array('status'=>409));
            $next=$snapshot['articles'][$d['target']];$fields=array_intersect_key($next,array_flip(hsh_planner_article_fields()));
            foreach(array('product','link','due','reason','usage','pros','cons','unknowns','source_notes','offer_fit','pre_use','followups','draft_text') as $k)if(($fields[$k]??'')===''&&isset($source[$k]))$fields[$k]=$source[$k];
            foreach(array('notes','photo_notes') as $k)if(!empty($source[$k]))$fields[$k]=($fields[$k]??'').(($fields[$k]??'')!==''?"\n\n":'').$source[$k];
            foreach(array('affiliate'=>'none','experience'=>'unknown') as $k=>$empty)if(($fields[$k]??$empty)===$empty&&isset($source[$k]))$fields[$k]=$source[$k];
            $valid=hsh_planner_validate(array('action'=>'save','id'=>$d['target'],'expected'=>$d['expected_target'],'fields'=>$fields),$snapshot);if(is_wp_error($valid))return $valid;
            $store[$d['target']]=hsh_planner_with_history($valid,$target);$store[$d['target']]['updated_at']=gmdate('c');unset($store[$d['id']]);
            foreach($store as $id=>&$row)if(isset($row['articles'])&&in_array($d['id'],$row['articles'],true)){$copy=$row;unset($copy['history']);$row['articles']=array_values(array_unique(array_map(function($v)use($d){return $v===$d['id']?$d['target']:$v;},$row['articles'])));$row=hsh_planner_with_history($row,$before[$id]??null);$row['updated_at']=gmdate('c');}unset($row);
        }elseif($action==='import'){
            if(array_diff(array_keys($d),array('action','backup','expected_store'))||!isset($d['backup'],$d['expected_store'])||!is_array($d['backup'])||!is_string($d['expected_store'])||$d['expected_store']!==hsh_planner_fingerprint($store))return new WP_Error('hsh_planner_conflict','管理表が変わったか、控えの形式が違います。更新して再確認してください。',array('status'=>409));
            $backup=$d['backup'];if(($backup['format']??'')!=='hsh-planner-backup-v1'||($backup['site']??'')!==home_url('/')||!isset($backup['records'])||!is_array($backup['records'])||count($backup['records'])>1500||strlen(wp_json_encode($backup))>2000000)return new WP_Error('hsh_planner_import','このサイトの管理表の控え（2MB以内）を選んでください。',array('status'=>400));
            $incoming=array();foreach($backup['records'] as $id=>$row){
                if(!is_string($id)||!is_array($row)||($row['id']??null)!==$id)return new WP_Error('hsh_planner_import','控えの項目を読み取れません。',array('status'=>400));
                if(!isset($snapshot['articles'][$id])&&!isset($snapshot['photos'][$id])&&!preg_match('/^plan:[a-f0-9-]{36}$/D',$id)&&!preg_match('/^media:[1-9][0-9]*$/D',$id))return new WP_Error('hsh_planner_scope','控えに対象外の記事・写真があります。',array('status'=>403));
                $keys=strpos($id,'media:')===0||strpos($id,'asset:')===0?hsh_planner_photo_fields():hsh_planner_article_fields();$incoming[$id]=array_intersect_key($row,array_flip($keys));foreach($incoming[$id] as $field=>$value){if(in_array($field,array('articles','sources'),true)){if(!is_array($value)||count($value)>100||count(array_filter($value,'is_string'))!==count($value))return new WP_Error('hsh_planner_import','関連付けの形式が違います。',array('status'=>400));}elseif(!is_string($value)||strlen($value)>60000)return new WP_Error('hsh_planner_import','控えの文字列が不正です。',array('status'=>400));}$store[$id]=array_merge($store[$id]??array(),$incoming[$id],array('id'=>$id));
            }
            if(count($store)>1500)return new WP_Error('hsh_planner_limit','管理表の上限1500件を超えます。',array('status'=>400));
            $candidate=hsh_planner_snapshot($store);
            foreach($incoming as $id=>$fields){$valid=hsh_planner_validate(array('action'=>'save','id'=>$id,'expected'=>null,'fields'=>$fields),$candidate);if(is_wp_error($valid))return $valid;$store[$id]=hsh_planner_with_history($valid,$before[$id]??null);$store[$id]['updated_at']=gmdate('c');}
        }else return new WP_Error('hsh_planner_input','操作を確認してください。',array('status'=>400));
        if($store!==$before&&!update_option('hsh_editorial_planner',$store,false))return new WP_Error('hsh_planner_save','保存できませんでした。以前の管理表はそのままです。',array('status'=>503));
        return hsh_planner_snapshot();
    }finally{delete_option('hsh_planner_lock');}
}
function hsh_planner_admin_payload($d,$post){
    if($d['action']==='restore')return array('action'=>'restore','id'=>$d['id'],'expected'=>$d['expected'],'revision'=>(int)($post['revision']??-1));
    if($d['action']==='link'){$target=wp_unslash($post['target']??'');$expected=wp_unslash($post['expected_target']??'');return array('action'=>'link','id'=>$d['id'],'expected'=>$d['expected'],'target'=>$target,'expected_target'=>$expected===''?null:$expected);}
    if($d['action']==='import'){$json=wp_unslash($post['backup_json']??'');return array('action'=>'import','backup'=>is_string($json)&&strlen($json)<=2000000?json_decode($json,true):null,'expected_store'=>wp_unslash($post['expected_store']??''));}
    return $d;
}
function hsh_planner_record_tools($row,$snapshot){
    if(!empty($row['placements'])){echo '<h4>使っている場所</h4><ul>';foreach($row['placements'] as $p)echo '<li>'.esc_html($p['label']).(!empty($snapshot['articles'][$p['article']]['title'])?'：'.esc_html($snapshot['articles'][$p['article']]['title']):'').'</li>';echo '</ul>';}
    if(!empty($row['duplicates']))echo '<p class="hsh-plan-alert">同じ画像ファイルの候補：'.esc_html(implode('、',$row['duplicates'])).'</p>';
    if(!empty($row['similar_candidates'])){echo '<p>構図が似ている候補（目で確認してください）：';foreach($row['similar_candidates'] as $id)echo '<a href="#hsh-photo-'.esc_attr(str_replace(':','-',$id)).'">'.esc_html($snapshot['photos'][$id]['title']).'</a> ';echo '</p>';}
    if(isset($row['exif_status']))echo '<p>撮影情報：'.esc_html($row['exif_status']).'</p>';
    if(!empty($row['history'])){echo '<details><summary>管理メモの履歴（最大5件）</summary>';foreach(array_reverse($row['history'],true) as $i=>$old){echo '<form method="post">';wp_nonce_field('hsh_editorial_planner');echo '<input type="hidden" name="id" value="'.esc_attr($row['id']).'"><input type="hidden" name="expected" value="'.esc_attr($row['fingerprint']??'').'"><input type="hidden" name="revision" value="'.(int)$i.'"><p>'.esc_html($old['updated_at']??'以前の記録').' <button class="button hsh-plan-restore" name="hsh_plan_action" value="restore">この管理メモへ戻す</button></p></form>';}echo '</details>';}
    if(strpos($row['id'],'plan:')===0&&!empty($row['stored'])){echo '<details><summary>実際の記事へメモを引き継ぐ</summary><p>本文は変更しません。メモを引き継ぎ、写真の関連先を移した後、この予定を管理表から外します。先にWordPressで投稿を作成してください。</p><form method="post" class="hsh-plan-link">';wp_nonce_field('hsh_editorial_planner');echo '<input type="hidden" name="id" value="'.esc_attr($row['id']).'"><input type="hidden" name="expected" value="'.esc_attr($row['fingerprint']).'"><input type="hidden" name="expected_target" value=""><select name="target" required><option value="">引き継ぎ先を選ぶ</option>';foreach($snapshot['articles'] as $id=>$a)if(strpos($id,'plan:')!==0)echo '<option value="'.esc_attr($id).'" data-fingerprint="'.esc_attr($a['fingerprint']??'').'">'.esc_html($a['title']).'</option>';echo '</select><button class="button" name="hsh_plan_action" value="link">引き継ぐ</button></form></details>';}
}
function hsh_planner_overview($snapshot){
    echo '<div class="hsh-plan-shortcuts"><button type="button" class="button" data-plan-preset="all">すべて</button><button type="button" class="button" data-plan-preset="photo_waiting">写真の準備メモあり</button><button type="button" class="button" data-plan-preset="needs_experience">体験を未確認</button><button type="button" class="button" data-plan-preset="due_soon">見直し日が近い</button><label>カテゴリー <select id="hsh-plan-topic"><option value="">すべて</option>';foreach(hsh_planner_labels()['topic'] as $k=>$v)echo '<option value="'.esc_attr($k).'">'.esc_html($v).'</option>';echo '</select></label><label>記事の準備 <select id="hsh-plan-stage"><option value="">すべて</option>';foreach(hsh_planner_labels()['stage'] as $k=>$v)echo '<option value="'.esc_attr($k).'">'.esc_html($v).'</option>';echo '</select></label></div>';
    echo '<details class="hsh-plan-import"><summary>保存した管理表の控えを読み込む</summary><p>このサイトから保存したJSONを選んで内容を確認します。同じ項目は控えの値に戻り、控えにない現在の項目は残ります。記事本文や画像ファイルは復元しません。</p><input type="file" id="hsh-plan-import-file" accept=".json,application/json"><p id="hsh-plan-import-preview" role="status"></p><form method="post" id="hsh-plan-import-form" hidden>';wp_nonce_field('hsh_editorial_planner');echo '<input type="hidden" name="expected_store" value="'.esc_attr($snapshot['store_fingerprint']).'"><textarea name="backup_json" hidden></textarea><button class="button hsh-plan-import-confirm" name="hsh_plan_action" value="import">確認した控えの内容を反映する</button></form></details>';
}
/** Private Search Console hand-off; the Google key never leaves the Actions runner. */
function hsh_gsc_service_email(){return 'blog-search-reader@hirogaru-search.iam.gserviceaccount.com';}
function hsh_gsc_audience(){return 'https://hirogarushumi.com/hirogaru-search-console';}
function hsh_gsc_error($code,$status=400){return new WP_Error('hsh_gsc_'.$code,'Search Console report validation failed: '.$code,array('status'=>$status));}
function hsh_gsc_decode($part){
    if(!is_string($part)||!preg_match('/^[A-Za-z0-9_-]+$/D',$part))return false;
    return base64_decode(strtr($part,'-_','+/').str_repeat('=',(4-strlen($part)%4)%4),true);
}
function hsh_gsc_google_certs(){
    $cached=get_transient('hsh_gsc_google_certs');if(is_array($cached)&&$cached)return $cached;
    $url='https://www.googleapis.com/robot/v1/metadata/x509/'.rawurlencode(hsh_gsc_service_email());
    $r=wp_remote_get($url,array('timeout'=>12,'redirection'=>0,'sslverify'=>true,'limit_response_size'=>150000));
    if(is_wp_error($r)||wp_remote_retrieve_response_code($r)!==200)return hsh_gsc_error('certificate_service',503);
    $certs=json_decode(wp_remote_retrieve_body($r),true);
    if(!is_array($certs)||!$certs||count($certs)>30)return hsh_gsc_error('certificate_response',503);
    foreach($certs as $kid=>$cert)if(!is_string($kid)||!is_string($cert)||strlen($cert)>12000||strpos($cert,'-----BEGIN CERTIFICATE-----')!==0)return hsh_gsc_error('certificate_response',503);
    set_transient('hsh_gsc_google_certs',$certs,300);return $certs;
}
function hsh_gsc_authenticate($token,$body,$certs,$now,$verify=null){
    if(!is_string($token)||strlen($token)>10000||!is_string($body)||strlen($body)>4*1024*1024)return hsh_gsc_error('token',401);
    $parts=explode('.',$token);if(count($parts)!==3)return hsh_gsc_error('token',401);
    $h=json_decode(hsh_gsc_decode($parts[0])?:'',true);$c=json_decode(hsh_gsc_decode($parts[1])?:'',true);$sig=hsh_gsc_decode($parts[2]);
    if(!is_array($h)||!is_array($c)||!is_string($sig)||!$sig||array_diff(array_keys($h),array('alg','typ','kid'))||($h['alg']??'')!=='RS256'||($h['typ']??'')!=='JWT'||!is_string($h['kid']??null)||!preg_match('/^[A-Za-z0-9_-]{1,200}$/D',$h['kid']))return hsh_gsc_error('token',401);
    if(($c['iss']??'')!==hsh_gsc_service_email()||($c['aud']??'')!==hsh_gsc_audience()||!is_int($c['iat']??null)||!is_int($c['exp']??null)||$c['iat']>$now+60||$c['iat']<$now-300||$c['exp']<=$now||$c['exp']<=$c['iat']||$c['exp']>$c['iat']+300||!is_string($c['jti']??null)||!preg_match('/^[A-Za-z0-9-]{16,100}$/D',$c['jti'])||!is_string($c['sha256']??null)||!hash_equals(hash('sha256',$body),$c['sha256']))return hsh_gsc_error('claims',401);
    if(!is_array($certs)||!isset($certs[$h['kid']]))return hsh_gsc_error('key',401);
    if($verify===null){if(!function_exists('openssl_verify'))return hsh_gsc_error('crypto_unavailable',503);$verify=function($data,$signature,$cert){return openssl_verify($data,$signature,$cert,OPENSSL_ALGO_SHA256)===1;};}
    if(!$verify($parts[0].'.'.$parts[1],$sig,$certs[$h['kid']]))return hsh_gsc_error('signature',401);
    return $c;
}
function hsh_gsc_ingest_permission($request){
    if(!is_ssl())return hsh_gsc_error('https',403);$h=$request->get_header('authorization');$body=$request->get_body();
    if(!is_string($h)||!preg_match('/^Bearer ([A-Za-z0-9_.-]+)$/D',$h,$m)||strlen($h)>10007||strlen($body)>4*1024*1024)return hsh_gsc_error('authorization',401);
    $certs=hsh_gsc_google_certs();if(is_wp_error($certs))return $certs;
    $r=hsh_gsc_authenticate($m[1],$body,$certs,time());return is_wp_error($r)?$r:true;
}
function hsh_gsc_valid_report($d,$now){
    if(!is_array($d)||($d['site']??'')!=='https://hirogarushumi.com/'||!in_array($d['state']??'',array('collected','no_finalized_data'),true)||!is_int($d['collected_at']??null)||$d['collected_at']>$now+60||$d['collected_at']<$now-3600)return hsh_gsc_error('report_scope');
    if(($d['state']==='collected')&&(($d['scope']??'')!=='https://www.googleapis.com/auth/webmasters.readonly'||($d['data_state']??'')!=='final'||($d['dimensions']??null)!==array('page','query','device','country')))return hsh_gsc_error('report_scope');
    if(!isset($d['daily'])||!is_array($d['daily'])||($d['state']==='collected'&&!$d['daily']))return hsh_gsc_error('daily_rows');
    foreach(array('daily','current_rows','previous_rows') as $key){
        $rows=$d[$key]??array();if(!is_array($rows)||count($rows)>50000)return hsh_gsc_error('row_limit');
        foreach($rows as $row){
            if(!is_array($row))return hsh_gsc_error('metrics');
            foreach(array('clicks','impressions','ctr','position') as $n)if(!isset($row[$n])||(!is_int($row[$n])&&!is_float($row[$n]))||!is_finite((float)$row[$n]))return hsh_gsc_error('metrics');
            if($row['clicks']<0||$row['clicks']>$row['impressions']||$row['ctr']<0||$row['ctr']>1||$row['position']<0)return hsh_gsc_error('metrics');
            if($key==='daily'){
                if(!is_string($row['date']??null)||!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D',$row['date'],$m)||!checkdate((int)$m[2],(int)$m[3],(int)$m[1]))return hsh_gsc_error('date');
            }else{
                if(!is_string($row['page']??null)||strpos($row['page'],'https://hirogarushumi.com/')!==0||strlen($row['page'])>4096||!is_string($row['query']??null)||strlen($row['query'])>4096||!in_array($row['device']??'',array('MOBILE','DESKTOP','TABLET'),true)||!is_string($row['country']??null)||!preg_match('/^[a-z]{3}$/D',$row['country']))return hsh_gsc_error('row_scope');
            }
        }
    }
    if($d['state']==='no_finalized_data'&&(!empty($d['daily'])||!empty($d['current_rows'])||!empty($d['previous_rows'])))return hsh_gsc_error('empty_state');
    return $d;
}
function hsh_gsc_receive($request){
    $raw=$request->get_body();if(strlen($raw)>4*1024*1024)return hsh_gsc_error('body_limit',413);
    $d=hsh_gsc_valid_report($request->get_json_params(),time());if(is_wp_error($d))return $d;
    $hash=hash('sha256',$raw);$old=get_option('hsh_gsc_private_report',array());
    if(($old['sha256']??'')===$hash)return hsh_edit_response(array('stored'=>true,'duplicate'=>true));
    if(($old['report']['collected_at']??0)>$d['collected_at'])return hsh_gsc_error('older_report',409);
    $value=array('received_at'=>time(),'sha256'=>$hash,'report'=>$d);
    if(!update_option('hsh_gsc_private_report',$value,false))return hsh_gsc_error('save',500);
    return hsh_edit_response(array('stored'=>true,'state'=>$d['state']));
}
function hsh_gsc_private_report($request){
    $v=get_option('hsh_gsc_private_report',null);return hsh_edit_response(array('connected'=>is_array($v),'received_at'=>$v['received_at']??null,'report'=>$v['report']??null));
}
add_action('rest_api_init',function(){register_rest_route('hirogaru/v1','/search-report',array(
    array('methods'=>'GET','permission_callback'=>'hsh_edit_permission','callback'=>'hsh_gsc_private_report'),
    array('methods'=>'POST','permission_callback'=>'hsh_gsc_ingest_permission','callback'=>'hsh_gsc_receive')
));});
function hsh_gsc_connection_status(){
    $v=get_option('hsh_gsc_private_report',array());$d=$v['report']??array();$dates=array_column($d['daily']??array(),'date');sort($dates);
    return array('connected'=>isset($v['received_at'])&&$v['received_at']>=time()-2*DAY_IN_SECONDS,'last_successful_import_at'=>$v['received_at']??null,'state'=>$d['state']??'not_connected','latest_data_date'=>$dates?end($dates):null);
}
