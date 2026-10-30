<?php
if(!defined('ABSPATH'))exit;

/** Private editorial records. Saving here never changes a post or publishes a photo/link. */
function hsh_planner_labels(){return array(
    'stage'=>array('idea'=>'ネタを整理中','writing'=>'執筆・修正中','review'=>'公開前の確認中','published'=>'公開済み'),
    'affiliate'=>array('none'=>'未選定','candidate'=>'候補あり','applying'=>'提携申請中','approved'=>'提携済み','added'=>'記事に掲載済み','ended'=>'終了・見直し'),
    'experience'=>array('unknown'=>'まだ確認していない','used'=>'自分で使った','researched'=>'調べた内容を紹介','mixed'=>'体験と調べた内容の両方'),
    'topic'=>array('cats'=>'猫との暮らし','whisky'=>'ウイスキー','it-freelance'=>'ITの仕事と働き方'),
    'kind'=>array('provided'=>'実写写真','generated'=>'生成画像'),
    'purpose'=>array('reference'=>'参考用のみ','publish'=>'記事掲載に使える','hold'=>'使用を保留'),
    'rights'=>array('unknown'=>'権利・許可を未確認','owner'=>'自分で撮影','permission'=>'使用許可あり','generated'=>'生成した画像'),
    'subject'=>array('manmos'=>'まんもす','cat-care'=>'猫の道具・世話','whisky'=>'ウイスキー','work'=>'IT・仕事','other'=>'その他')
);}
function hsh_planner_store(){ $v=get_option('hsh_editorial_planner',array());return is_array($v)?$v:array(); }
function hsh_planner_fingerprint($row){return hash('sha256',wp_json_encode($row));}
function hsh_planner_image($key){
    if(strpos($key,'asset:')===0){$a=hsh_photo_manifest()['assets'][substr($key,6)]??null;return $a?plugin_dir_url(__FILE__).'assets/photos/'.$a['file']:'';}
    if(preg_match('/^media:([1-9][0-9]*)$/D',$key,$m)&&wp_attachment_is_image((int)$m[1]))return wp_get_attachment_image_url((int)$m[1],'medium')?:'';
    return '';
}
function hsh_planner_snapshot($override=null){
    $store=is_array($override)?$override:hsh_planner_store();$articles=array();$photos=array();$manifest=hsh_photo_manifest();
    foreach(hsh_catalog() as $a){
        $p=get_page_by_path($a['slug'],OBJECT,'post');if(!$p||!hsh_owned_blog_post($p))continue;
        $id='article:'.$a['slug'];$saved=$store[$id]??null;
        $articles[$id]=array('id'=>$id,'title'=>$p->post_title,'topic'=>$a['topic'],'stage'=>$p->post_status==='publish'?'published':'writing','affiliate'=>'none','experience'=>'unknown','product'=>'','link'=>'','notes'=>'','photo_notes'=>'','due'=>'','url'=>get_permalink($p),'edit_url'=>get_edit_post_link($p->ID,'raw'),'stored'=>is_array($saved),'fingerprint'=>is_array($saved)?hsh_planner_fingerprint($saved):null);
        if(is_array($saved))$articles[$id]=array_merge($articles[$id],$saved);
        // The live title/URL always wins over a stale editorial note.
        $articles[$id]['title']=$p->post_title;$articles[$id]['url']=get_permalink($p);$articles[$id]['edit_url']=get_edit_post_link($p->ID,'raw');
    }
    // New posts made in the normal WordPress editor appear here as well.
    foreach(get_posts(array('post_type'=>'post','post_status'=>array('publish','draft','pending','future','private'),'numberposts'=>500,'orderby'=>'modified','order'=>'DESC')) as $p){
        if((int)$p->ID===8||isset($articles['article:'.$p->post_name]))continue;
        $id='post:'.$p->ID;$saved=$store[$id]??null;$topic='cats';foreach(array_keys(hsh_planner_labels()['topic']) as $candidate)if(has_category($candidate,$p)){$topic=$candidate;break;}
        $articles[$id]=array_merge(array('id'=>$id,'title'=>$p->post_title?:'無題の記事','topic'=>$topic,'stage'=>$p->post_status==='publish'?'published':'writing','affiliate'=>'none','experience'=>'unknown','product'=>'','link'=>'','notes'=>'','photo_notes'=>'','due'=>'','url'=>$p->post_status==='publish'?get_permalink($p):'','edit_url'=>get_edit_post_link($p->ID,'raw'),'stored'=>is_array($saved),'fingerprint'=>is_array($saved)?hsh_planner_fingerprint($saved):null),is_array($saved)?$saved:array());
        $articles[$id]['title']=$p->post_title?:'無題の記事';$articles[$id]['url']=$p->post_status==='publish'?get_permalink($p):'';$articles[$id]['edit_url']=get_edit_post_link($p->ID,'raw');
    }
    foreach($store as $id=>$row){
        if(strpos($id,'plan:')===0&&is_array($row))$articles[$id]=array_merge(array('url'=>'','edit_url'=>admin_url('post-new.php'),'stored'=>true,'fingerprint'=>hsh_planner_fingerprint($row)),$row);
    }
    foreach($manifest['assets'] as $key=>$a){
        $id='asset:'.$key;$saved=$store[$id]??null;$used=array();
        foreach($manifest['articles'] as $slug=>$cover){$list=array_merge(array($cover),$manifest['inline'][$slug]??array());if($slug==='manmos-cat-life')$list=array_merge($list,array('manmos-back','manmos-stretch'));if(in_array($key,$list,true))$used[]='article:'.$slug;}
        $subject=in_array($a['subject'],array('manmos','cat-care','whisky','work'),true)?$a['subject']:'other';
        $photos[$id]=array('id'=>$id,'title'=>$a['alt'],'kind'=>$a['kind'],'subject'=>$subject,'purpose'=>'publish','rights'=>$a['kind']==='provided'?'permission':'generated','notes'=>'','articles'=>array(),'sources'=>array(),'used_in'=>$used,'image'=>hsh_planner_image($id),'file'=>$a['file'],'stored'=>is_array($saved),'fingerprint'=>is_array($saved)?hsh_planner_fingerprint($saved):null);
        if(is_array($saved))$photos[$id]=array_merge($photos[$id],$saved);
        $photos[$id]['kind']=$a['kind'];$photos[$id]['used_in']=$used;
    }
    foreach($store as $id=>$row)if(strpos($id,'media:')===0&&is_array($row)){
        $image=hsh_planner_image($id);
        $used=array();$media_id=(int)substr($id,6);
        foreach($articles as $aid=>$article){$post=strpos($aid,'article:')===0?get_page_by_path(substr($aid,8),OBJECT,'post'):(strpos($aid,'post:')===0?get_post((int)substr($aid,5)):null);if($post&&(preg_match('/\bwp-image-'.$media_id.'\b/',$post->post_content)||strpos($post->post_content,'"id":'.$media_id.',')!==false||($image&&strpos($post->post_content,$image)!==false)))$used[]=$aid;}
        $photos[$id]=array_merge(array('used_in'=>$used,'stored'=>true,'fingerprint'=>hsh_planner_fingerprint($row),'image'=>$image,'file'=>'WordPressメディア #'.substr($id,6)),$row);
        $photos[$id]['image']=$image;
    }
    $result=array('articles'=>$articles,'photos'=>$photos);return function_exists('hsh_planner_enrich')?hsh_planner_enrich($result,$store):$result;
}
function hsh_planner_validate($d,$snapshot){
    $labels=hsh_planner_labels();
    if(!is_array($d)||array_diff(array_keys($d),array('action','id','expected','fields'))||!isset($d['action'],$d['id'])||!is_string($d['id'])||!is_string($d['action'])||!array_key_exists('expected',$d)||($d['expected']!==null&&(!is_string($d['expected'])||!preg_match('/^[a-f0-9]{64}$/D',$d['expected']))))return new WP_Error('hsh_planner_input','入力を確認してください。',array('status'=>400));
    $id=$d['id'];$article=isset($snapshot['articles'][$id])||preg_match('/^plan:[a-f0-9-]{36}$/D',$id);$photo=isset($snapshot['photos'][$id])||preg_match('/^media:[1-9][0-9]*$/D',$id);
    if(!$article&&!$photo)return new WP_Error('hsh_planner_scope','この管理表の項目ではありません。',array('status'=>403));
    if(strpos($id,'media:')===0&&!wp_attachment_is_image((int)substr($id,6)))return new WP_Error('hsh_planner_photo','画像をメディアから選び直してください。',array('status'=>400));
    if($d['action']==='delete'){
        if(strpos($id,'plan:')!==0&&strpos($id,'media:')!==0)return new WP_Error('hsh_planner_scope','公開記事と同梱写真は管理表から削除できません。',array('status'=>403));
        return array('id'=>$id,'delete'=>true);
    }
    if($d['action']!=='save'||!isset($d['fields'])||!is_array($d['fields']))return new WP_Error('hsh_planner_input','保存する内容を確認してください。',array('status'=>400));
    $allowed=$article?array('title','topic','stage','affiliate','experience','product','link','notes','photo_notes','due','reason','usage','pros','cons','unknowns','source_notes','draft_text'):array('title','kind','subject','purpose','rights','notes','articles','sources');
    if(array_diff(array_keys($d['fields']),$allowed))return new WP_Error('hsh_planner_field','保存できない項目があります。',array('status'=>400));
    $row=array('id'=>$id);$f=$d['fields'];
    foreach($allowed as $k){
        if(in_array($k,array('articles','sources'),true))continue;
        $v=$f[$k]??($snapshot[$article?'articles':'photos'][$id][$k]??'');
        if(!is_string($v)||strlen($v)>($k==='draft_text'?60000:(in_array($k,array('notes','photo_notes','reason','usage','pros','cons','unknowns','source_notes'),true)?12000:2400)))return new WP_Error('hsh_planner_length','入力が長すぎるか、形式が違います。',array('status'=>400));
        if(isset($labels[$k])){
            if(!array_key_exists($v,$labels[$k]))return new WP_Error('hsh_planner_choice','選択項目を確認してください。',array('status'=>400));
            $row[$k]=$v;
        }elseif($k==='link'){
            if($v!==''&&(!filter_var($v,FILTER_VALIDATE_URL)||!in_array(strtolower((string)wp_parse_url($v,PHP_URL_SCHEME)),array('https','http'),true)))return new WP_Error('hsh_planner_url','リンクはhttpまたはhttpsのURLにしてください。',array('status'=>400));
            $row[$k]=$v;
        }elseif($k==='due'){
            if($v!==''&&(!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D',$v,$m)||!checkdate((int)$m[2],(int)$m[3],(int)$m[1])))return new WP_Error('hsh_planner_date','見直し日を確認してください。',array('status'=>400));
            $row[$k]=$v;
        }else $row[$k]=in_array($k,array('notes','photo_notes','reason','usage','pros','cons','unknowns','source_notes','draft_text'),true)?sanitize_textarea_field($v):sanitize_text_field($v);
    }
    if(trim($row['title'])==='')return new WP_Error('hsh_planner_title','タイトルか写真の説明を入力してください。',array('status'=>400));
    if($photo){
        if(strpos($id,'asset:')===0&&$row['kind']!==$snapshot['photos'][$id]['kind'])return new WP_Error('hsh_planner_origin','既存写真の実写・生成の区別は変更できません。',array('status'=>400));
        if(($row['kind']==='generated'&&in_array($row['rights'],array('owner','permission'),true))||($row['kind']==='provided'&&$row['rights']==='generated'))return new WP_Error('hsh_planner_rights','写真の種類と権利の記録を確認してください。',array('status'=>400));
        if($row['purpose']==='publish'&&$row['rights']==='unknown')return new WP_Error('hsh_planner_rights','権利・許可を確認してから「記事掲載に使える」を選んでください。',array('status'=>400));
        foreach(array('articles','sources') as $k){
            $values=$f[$k]??array();if(!is_array($values)||count($values)>100)return new WP_Error('hsh_planner_relation','関連付けが多すぎるか、形式が違います。',array('status'=>400));
            $row[$k]=array();foreach($values as $v){if(!is_string($v)||!isset($snapshot[$k==='articles'?'articles':'photos'][$v])||$v===$id)return new WP_Error('hsh_planner_relation','関連先を選び直してください。',array('status'=>400));$row[$k][]=$v;}$row[$k]=array_values(array_unique($row[$k]));
        }
        // Source references describe provenance, not automatic model training.
        $visited=array();$visit=function($candidate)use(&$visit,&$visited,$snapshot,$id){if($candidate===$id)return true;if(isset($visited[$candidate]))return false;$visited[$candidate]=true;foreach($snapshot['photos'][$candidate]['sources']??array() as $source)if($visit($source))return true;return false;};
        foreach($row['sources'] as $source)if($visit($source))return new WP_Error('hsh_planner_cycle','参考元が循環しています。関連付けを見直してください。',array('status'=>400));
    }
    return $row;
}
function hsh_planner_save($d){
    if(is_array($d)&&in_array($d['action']??'',array('restore','link','import'),true)&&function_exists('hsh_planner_advanced_save'))return hsh_planner_advanced_save($d);
    $old_lock=get_option('hsh_planner_lock',0);if(is_numeric($old_lock)&&(int)$old_lock>0&&time()-(int)$old_lock>120&&get_option('hsh_planner_lock',0)===$old_lock)delete_option('hsh_planner_lock');
    if(!add_option('hsh_planner_lock',time(),'',false))return new WP_Error('hsh_planner_busy','ほかの保存を処理中です。少し待ってからお試しください。',array('status'=>409));
    try{
        $snapshot=hsh_planner_snapshot();$valid=hsh_planner_validate($d,$snapshot);if(is_wp_error($valid))return $valid;
        $store=hsh_planner_store();$id=$valid['id'];$current=$store[$id]??null;$expected=is_array($current)?hsh_planner_fingerprint($current):null;
        if($d['expected']!==$expected)return new WP_Error('hsh_planner_conflict','別の画面で変更されています。入力を控えてから、画面を更新してください。',array('status'=>409));
        if(!empty($valid['delete'])){
            foreach($store as $other=>$row)if($other!==$id&&(in_array($id,$row['sources']??array(),true)||in_array($id,$row['articles']??array(),true)))return new WP_Error('hsh_planner_used','ほかの記録で関連付けられています。先にその関連付けを外してください。',array('status'=>409));
            unset($store[$id]);
        }else{$valid=function_exists('hsh_planner_with_history')?hsh_planner_with_history($valid,$current):$valid;$valid['updated_at']=gmdate('c');$store[$id]=$valid;}
        if(count($store)>1500)return new WP_Error('hsh_planner_limit','管理件数が上限に達しました。不要な予定を整理してください。',array('status'=>400));
        $before=hsh_planner_store();
        if($before!==$store&&!update_option('hsh_editorial_planner',$store,false))return new WP_Error('hsh_planner_save','保存できませんでした。入力を残したまま、もう一度お試しください。',array('status'=>503));
        return hsh_planner_snapshot();
    }finally{delete_option('hsh_planner_lock');}
}
function hsh_planner_api($request){$r=hsh_planner_save($request->get_json_params());return is_wp_error($r)?$r:hsh_edit_response($r);}
add_action('rest_api_init',function(){register_rest_route('hirogaru/v1','/editorial-planner',array(array('methods'=>'GET','callback'=>function(){return hsh_edit_response(hsh_planner_snapshot());},'permission_callback'=>'hsh_edit_permission'),array('methods'=>'POST','callback'=>'hsh_planner_api','permission_callback'=>'hsh_edit_permission')));});

function hsh_planner_select($name,$labels,$value,$multiple=false){
    if($multiple){echo '<span class="hsh-plan-options">';foreach($labels as $key=>$label){echo '<label><input type="checkbox" name="'.esc_attr($name).'[]" value="'.esc_attr($key).'"'.(in_array($key,(array)$value,true)?' checked':'').'>';if(strpos($name,'sources')!==false){$image=hsh_planner_image($key);if($image)echo '<img data-hsh-thumb="'.esc_url($image).'" alt="" width="64" height="48">';}echo '<span>'.esc_html($label).'</span></label>';}echo '</span>';return;}
    echo '<select name="'.esc_attr($name).'">';foreach($labels as $key=>$label)echo '<option value="'.esc_attr($key).'"'.($key===$value?' selected':'').'>'.esc_html($label).'</option>';echo '</select>';
}
function hsh_planner_form($row,$type,$snapshot){
    $labels=hsh_planner_labels();$isphoto=$type==='photo';
    echo '<form method="post" class="hsh-plan-form">';wp_nonce_field('hsh_editorial_planner');
    echo '<input type="hidden" name="id" value="'.esc_attr($row['id']).'"><input type="hidden" name="expected" value="'.esc_attr($row['fingerprint']??'').'"><div class="hsh-plan-fields">';
    $names=$isphoto?array('title'=>'写真の説明','kind'=>'写真の種類','subject'=>'写っている対象','purpose'=>'使い道','rights'=>'権利・許可','notes'=>'撮影・生成の状況や注意点'):array('title'=>'記事名・仮タイトル','topic'=>'カテゴリー','stage'=>'記事の準備','affiliate'=>'案件の準備','experience'=>'使用経験','product'=>'紹介する商品・サービス','link'=>'案件リンク（保存するだけで記事には掲載されません）','due'=>'見直し日','notes'=>'体験・調べたこと・次にやること','photo_notes'=>'欲しい写真・撮影しておきたい場面','reason'=>'選んだ理由・使う前の期待','usage'=>'実際の使い方・使った期間','pros'=>'よかった点・具体的な反応','cons'=>'困った点・合わなかった点','unknowns'=>'まだ分からないこと','source_notes'=>'出典・確認日・変わりやすい情報','draft_text'=>'記事の下書き（公開されません）');
    foreach($names as $key=>$label){
        echo '<label>'.esc_html($label);
        if(isset($labels[$key]))hsh_planner_select('fields['.$key.']',$labels[$key],$row[$key]??'');
        elseif(in_array($key,array('notes','photo_notes','reason','usage','pros','cons','unknowns','source_notes','draft_text'),true))echo '<textarea name="fields['.$key.']" rows="'.($key==='draft_text'?'8':'3').'" maxlength="'.($key==='draft_text'?'20000':'4000').'">'.esc_html($row[$key]??'').'</textarea>';
        else echo '<input type="'.($key==='due'?'date':($key==='link'?'url':'text')).'" name="fields['.$key.']" value="'.esc_attr($row[$key]??'').'" maxlength="800"'.($key==='title'?' required':'').((strpos($row['id'],'article:')===0||strpos($row['id'],'post:')===0)&&$key==='title'?' readonly':'').'>';
        echo '</label>';
    }
    if($isphoto){
        echo '<fieldset><legend>使う予定の記事（チェックして選択）</legend>';hsh_planner_select('fields[articles]',array_map(function($a){return $a['title'];},$snapshot['articles']),$row['articles']??array(),true);echo '</fieldset>';
        $sources=array();foreach($snapshot['photos'] as $id=>$p)if($id!==$row['id'])$sources[$id]=$p['title'];
        echo '<fieldset><legend>参考にした元写真（写真を見ながら選択）</legend>';hsh_planner_select('fields[sources]',$sources,$row['sources']??array(),true);echo '</fieldset>';
    }
    echo '</div><p><button class="button button-primary" name="hsh_plan_action" value="save">保存する</button> ';
    if(!empty($row['stored'])&&(strpos($row['id'],'plan:')===0||strpos($row['id'],'media:')===0))echo '<button class="button hsh-plan-delete" name="hsh_plan_action" value="delete">管理表から外す</button>';
    echo '</p></form>';if(function_exists('hsh_planner_record_tools'))hsh_planner_record_tools($row,$snapshot);
}
function hsh_planner_admin(){
    if(!current_user_can('manage_options'))return;
    $message='';$failed=null;
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
        check_admin_referer('hsh_editorial_planner');
        $id=wp_unslash($_POST['id']??'');$fields=wp_unslash($_POST['fields']??array());$expected=wp_unslash($_POST['expected']??'');
        $d=array('id'=>$id,'action'=>wp_unslash($_POST['hsh_plan_action']??''),'expected'=>$expected===''?null:$expected,'fields'=>$fields);if(function_exists('hsh_planner_admin_payload'))$d=hsh_planner_admin_payload($d,$_POST);$result=hsh_planner_save($d);
        if(is_wp_error($result)){$message=$result->get_error_message();$failed=isset($d['id'],$d['fields'])?$d:null;}else $message='管理表を保存しました。記事の本文・写真・案件リンクは公開していません。';
    }
    $snapshot=hsh_planner_snapshot();$saved_snapshot=$snapshot;$labels=hsh_planner_labels();
    if($failed&&is_string($failed['id'])&&is_array($failed['fields'])){
        $type=strpos($failed['id'],'article:')===0||strpos($failed['id'],'post:')===0||strpos($failed['id'],'plan:')===0?'articles':'photos';
        // Keep the original expectation on failure; refresh is required after a conflict.
        $safe=array();foreach($failed['fields'] as $key=>$value){if(in_array($key,array('articles','sources'),true)&&is_array($value))$safe[$key]=array_values(array_filter($value,'is_string'));elseif(is_string($value))$safe[$key]=$value;}
        $snapshot[$type][$failed['id']]=array_merge($snapshot[$type][$failed['id']]??array('id'=>$failed['id']),array_intersect_key($safe,array_flip(array('title','topic','stage','affiliate','experience','product','link','notes','photo_notes','due','reason','usage','pros','cons','unknowns','source_notes','draft_text','kind','subject','purpose','rights','articles','sources'))),array('fingerprint'=>is_string($failed['expected'])?$failed['expected']:null));
    }
    echo '<div class="wrap hsh-planner"><h1>記事・写真管理</h1><p>記事の予定、案件の準備、参考写真をまとめます。ここで保存しても、記事の公開や画像の差し替えは行いません。</p>';
    if($message)echo '<div class="notice '.(is_wp_error($result??null)?'notice-error':'notice-success').'"><p>'.esc_html($message).'</p></div>';if(function_exists('hsh_planner_overview'))hsh_planner_overview($snapshot);
    echo '<div class="hsh-plan-toolbar"><label>表示するもの <select id="hsh-plan-type"><option value="all">すべて</option><option value="article">記事</option><option value="photo">写真</option></select></label><label>検索 <input id="hsh-plan-search" type="search" placeholder="記事名・写真の説明・商品名"></label><button type="button" class="button" id="hsh-plan-export">保存済みの管理表を控えとして保存</button></div><p id="hsh-plan-count" aria-live="polite"></p>';
    echo '<section data-hsh-group="article"><h2>記事 '.count($snapshot['articles']).'件</h2><p>記事名は本文の編集画面で変更します。今後WordPressで作る投稿も一覧に表示します（追加の投稿は更新順に最大500件）。まだ投稿を作らない段階なら、下の「記事の予定を追加」からメモできます。</p>';
    foreach($snapshot['articles'] as $a){
        echo '<details class="hsh-plan-card" data-hsh-type="article"><summary><strong>'.esc_html($a['title']).'</strong><span>'.esc_html(($labels['stage'][$a['stage']]??'').' ／ '.($labels['affiliate'][$a['affiliate']]??'')).'</span></summary><p>';
        if(!empty($a['url']))echo '<a href="'.esc_url($a['url']).'">公開記事を見る</a> ／ ';
        if(!empty($a['edit_url']))echo '<a href="'.esc_url($a['edit_url']).'">'.(strpos($a['id'],'plan:')===0?'投稿の新規作成へ（予定は自動転記されません）':'本文を編集する').'</a>';
        echo '</p><p>実際に掲載中の写真：';$found=false;foreach($snapshot['photos'] as $p)if(in_array($a['id'],$p['used_in']??array(),true)){$found=true;echo '<a href="#hsh-photo-'.esc_attr(str_replace(':','-',$p['id'])).'">'.esc_html($p['title']).'</a> ';}if(!$found)echo 'この管理表では未登録';echo '</p>';
        hsh_planner_form($a,'article',$snapshot);echo '</details>';
    }
    $new=array('id'=>'plan:'.wp_generate_uuid4(),'title'=>'','topic'=>'cats','stage'=>'idea','affiliate'=>'none','experience'=>'unknown','fingerprint'=>null);
    echo '<details class="hsh-plan-card"><summary>＋ 記事の予定を追加</summary>';hsh_planner_form($new,'article',$snapshot);echo '</details></section>';
    echo '<section data-hsh-group="photo"><h2>写真 '.count($snapshot['photos']).'枚</h2><p>実写と生成画像を区別し、参考用か記事掲載用かを記録します。参考元の登録は、AIに自動で学習させる操作ではありません。</p><button type="button" class="button" id="hsh-plan-media">＋ 写真を追加・メディアから選ぶ</button><p>WordPressメディアに置いた画像は「参考用のみ」でも、画像URLから閲覧できる場合があります。写真自体を非公開にしたい場合はアップロードせず、このチャットで渡してください。管理メモは管理者だけに表示します。</p><div class="hsh-plan-grid">';
    foreach($snapshot['photos'] as $p){
        echo '<details id="hsh-photo-'.esc_attr(str_replace(':','-',$p['id'])).'" class="hsh-plan-card" data-hsh-type="photo"><summary>';
        if(!empty($p['image']))echo '<img src="'.esc_url($p['image']).'" alt="'.esc_attr($p['title']).'" loading="lazy">';
        echo '<strong>'.esc_html($p['title']).'</strong><span>'.esc_html(($labels['kind'][$p['kind']]??'').' ／ '.($labels['purpose'][$p['purpose']]??'')).'</span></summary><p>ファイル：'.esc_html($p['file']??'メディアから選択').'</p><p>実際に掲載中：';
        foreach($p['used_in']??array() as $id)if(isset($snapshot['articles'][$id]))echo '<a href="'.esc_url($snapshot['articles'][$id]['url']).'">'.esc_html($snapshot['articles'][$id]['title']).'</a> ';if(empty($p['used_in']))echo '13記事の表紙・本文への掲載は未登録（ホームなどで使う画像も含みます）';echo '</p>';
        if(empty($p['image']))echo '<p>画像が見つかりません。メディアから削除されていないか確認してください。</p>';
        hsh_planner_form($p,'photo',$snapshot);echo '</details>';
    }
    echo '</div><details id="hsh-plan-new-photo" class="hsh-plan-card" hidden><summary>追加する写真の情報</summary><p id="hsh-plan-picked"></p>';
    hsh_planner_form(array('id'=>'','title'=>'','kind'=>'provided','subject'=>'manmos','purpose'=>'hold','rights'=>'unknown','fingerprint'=>null),'photo',$snapshot);
    echo '</details></section><p>「管理表から外す」は記録だけを外します。WordPressの記事やメディアの画像ファイルは削除しません。生成画像を購入・使用の証拠写真として扱わず、実体験は確認できた内容だけを記事に使ってください。</p></div>';
    // Private data is only embedded after the administrator capability check.
    echo '<script type="application/json" id="hsh-planner-data">'.wp_json_encode($saved_snapshot,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>';
}
add_action('admin_menu',function(){add_menu_page('記事・写真管理','記事・写真管理','manage_options','hsh-editorial-planner','hsh_planner_admin','dashicons-portfolio',26);});
add_action('admin_enqueue_scripts',function($hook){if($hook!=='toplevel_page_hsh-editorial-planner'||!current_user_can('manage_options'))return;wp_enqueue_media();wp_enqueue_style('hsh-planner',plugin_dir_url(__FILE__).'assets/editorial-planner.css',array(),'1.22.1');wp_enqueue_script('hsh-planner',plugin_dir_url(__FILE__).'assets/editorial-planner.js',array(),'1.22.1',true);});

require_once __DIR__.'/planner-advanced.php';
