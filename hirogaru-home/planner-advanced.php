<?php
if(!defined('ABSPATH'))exit;
function hsh_planner_article_fields(){return array('title','topic','stage','affiliate','experience','product','link','notes','photo_notes','due','reason','usage','pros','cons','unknowns','source_notes','draft_text');}
function hsh_planner_photo_fields(){return array('title','kind','subject','purpose','rights','notes','articles','sources');}
function hsh_planner_with_history($next,$current){
    $history=is_array($current)?($current['history']??array()):array();
    if(is_array($current)){$old=$current;unset($old['history']);$history[]=$old;}
    $next['history']=array_slice($history,-5);return $next;
}
function hsh_planner_enrich($data,$store){
    $hashes=array();$manifest=hsh_photo_manifest();
    foreach($data['articles'] as $id=>&$a){foreach(array('reason','usage','pros','cons','unknowns','source_notes','draft_text') as $k)if(!isset($a[$k]))$a[$k]='';$a['history']=$a['history']??array();$a['due_soon']=!empty($a['due'])&&$a['due']<=wp_date('Y-m-d',time()+14*DAY_IN_SECONDS);$a['needs_experience']=$a['experience']==='unknown';$a['photo_waiting']=trim($a['photo_notes'])!=='';$a['has_reference']=trim($a['source_notes'])!=='';}unset($a);
    foreach($data['photos'] as $id=>&$p){
        $p['placements']=array();$p['history']=$p['history']??array();$p['sha256']='';$p['exif_status']='未点検';
        if(strpos($id,'asset:')===0){$key=substr($id,6);$asset=$manifest['assets'][$key];$p['sha256']=$asset['sha256']??'';$p['exif_status']='WebP化済み。原本の位置情報とは別に管理';
            foreach($manifest['articles'] as $slug=>$cover)if($cover===$key)$p['placements'][]=array('label'=>'表紙','article'=>'article:'.$slug);
            foreach($manifest['inline']??array() as $slug=>$keys)foreach($keys as $i=>$k)if($k===$key)$p['placements'][]=array('label'=>'本文写真'.($i+1),'article'=>'article:'.$slug);
            if(in_array($key,array('manmos-back','manmos-stretch'),true))$p['placements'][]=array('label'=>'記事末尾のギャラリー','article'=>'article:manmos-cat-life');
            if($key==='manmos-tower')$p['placements'][]=array('label'=>'ホームの大きな写真','article'=>'');
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
            foreach(array('product','link','due','reason','usage','pros','cons','unknowns','source_notes','draft_text') as $k)if(($fields[$k]??'')===''&&isset($source[$k]))$fields[$k]=$source[$k];
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
