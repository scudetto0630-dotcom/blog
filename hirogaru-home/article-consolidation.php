<?php
if(!defined('ABSPATH'))exit;
/** Permanent editorial merges. Original posts/history remain recoverable, never deleted. */
function hsh_consolidation_plans(){
    static $plans=null;if($plans===null){$data=json_decode(file_get_contents(__DIR__.'/article-consolidations.json'),true);$plans=array(array('target'=>$data['target'],'marker'=>$data['marker'],'sources'=>$data['sources']));foreach($data['groups']??array() as $plan)$plans[]=$plan;}return $plans;
}
function hsh_consolidation_plan($target=null){foreach(hsh_consolidation_plans() as $plan)if($target===null||$plan['target']===$target)return $plan;return null;}
function hsh_consolidation_option($plan){return $plan['target']===hsh_consolidation_plan()['target']?'hsh_supplement_consolidation':'hsh_consolidation_'.$plan['target'];}
function hsh_consolidation_records(){
    $records=array();foreach(hsh_consolidation_plans() as $plan){
        $saved=get_option(hsh_consolidation_option($plan),array());if(!is_array($saved)||($saved['target']??'')!==$plan['target'])continue;
        $p=get_page_by_path($plan['target'],OBJECT,'post');if(!$p||$p->post_status!=='publish'||get_post_meta($p->ID,'_hsh_bundle_owned',true)!==$plan['target'])continue;
        $complete=true;foreach(array_merge(array($plan['marker']),array_values($plan['sources'])) as $anchor)if(strpos($p->post_content,'id="'.$anchor.'"')===false)$complete=false;if(!$complete)continue;
        foreach($plan['sources'] as $slug=>$anchor){$source=get_page_by_path($slug,OBJECT,'post');if($source&&$source->post_status==='publish'&&get_post_meta($source->ID,'_hsh_bundle_owned',true)===$slug)$records[$slug]=array('target'=>$plan['target'],'anchor'=>$anchor);}
    }return $records;
}
function hsh_consolidations(){$out=array();foreach(hsh_consolidation_records() as $slug=>$record)$out[$slug]=$record['anchor'];return $out;}
function hsh_consolidation_targets(){$out=array();foreach(hsh_consolidation_records() as $slug=>$record)$out[$slug]=$record['target'];return $out;}
function hsh_apply_consolidation($request){
    $d=$request->get_json_params();$plan=is_array($d)&&is_string($d['target']??null)?hsh_consolidation_plan($d['target']):null;if(!$plan)return new WP_Error('hsh_input','指定された統合先は対象外です。',array('status'=>400));$slugs=array_merge(array($plan['target']),array_keys($plan['sources']));
    if(!is_array($d)||array_diff(array_keys($d),array('target','expected'))||($d['target']??null)!==$plan['target']||!isset($d['expected'])||!is_array($d['expected'])||count($d['expected'])!==count($slugs)||array_diff(array_keys($d['expected']),$slugs))return new WP_Error('hsh_input','統合先と全記事の指紋を指定してください。',array('status'=>400));
    if(!add_option('hsh_bundle_lock',time(),'',false))return new WP_Error('hsh_busy','他の記事を処理中です。',array('status'=>409));
    try{
        foreach($slugs as $slug){
            $p=get_page_by_path($slug,OBJECT,'post');$fp=$d['expected'][$slug]??null;
            if(!is_string($fp)||!preg_match('/^[a-f0-9]{64}$/D',$fp)||!$p||$p->post_status!=='publish'||get_post_meta($p->ID,'_hsh_bundle_owned',true)!==$slug||!hash_equals(hsh_article_fingerprint($p),$fp))return new WP_Error('hsh_conflict','記事が変更されています。再確認してください。',array('status'=>409));
            if($slug===$plan['target'])foreach(array_merge(array($plan['marker']),array_values($plan['sources'])) as $anchor)if(strpos($p->post_content,'id="'.$anchor.'"')===false)return new WP_Error('hsh_target','比較記事の公開と見出しを確認してください。',array('status'=>409));
        }
        update_option(hsh_consolidation_option($plan),array('target'=>$plan['target'],'at'=>time(),'fingerprints'=>$d['expected']),false);
        if(count(array_intersect_key(hsh_consolidations(),$plan['sources']))!==count($plan['sources']))return new WP_Error('hsh_store','統合状態を保存できませんでした。',array('status'=>500));
        return hsh_edit_response(array('target'=>home_url('/'.$plan['target'].'/'),'redirected'=>array_keys($plan['sources']),'original_posts_preserved'=>true));
    }finally{delete_option('hsh_bundle_lock');}
}
add_action('rest_api_init',function(){register_rest_route('hirogaru/v1','/content-consolidation',array('methods'=>'POST','callback'=>'hsh_apply_consolidation','permission_callback'=>'hsh_edit_permission'));});
function hsh_consolidated_ids(){
    $ids=array();foreach(hsh_consolidations() as $slug=>$anchor){$p=get_page_by_path($slug,OBJECT,'post');if($p&&get_post_meta($p->ID,'_hsh_bundle_owned',true)===$slug)$ids[]=(int)$p->ID;}return $ids;
}
function hsh_consolidation_query_args($args){$ids=hsh_consolidated_ids();if($ids)$args['post__not_in']=array_values(array_unique(array_merge((array)($args['post__not_in']??array()),$ids)));return $args;}
add_action('pre_get_posts',function($q){
    if(is_admin()||$q->is_singular())return;
    $type=$q->get('post_type');if($type&&$type!=='any'&&!in_array('post',(array)$type,true))return;
    $args=hsh_consolidation_query_args(array('post__not_in'=>$q->get('post__not_in')));if(!empty($args['post__not_in']))$q->set('post__not_in',$args['post__not_in']);
},50);
add_filter('rest_post_query',function($args){return hsh_consolidation_query_args($args);},50);
add_filter('wp_sitemaps_posts_query_args',function($args,$type){return $type==='post'?hsh_consolidation_query_args($args):$args;},1000,2);
function hsh_consolidation_destination($url){
    $parts=wp_parse_url($url);if(!is_array($parts)||isset($parts['query'])||isset($parts['user'])||isset($parts['pass']))return '';
    $base=wp_parse_url(home_url('/'));if(isset($parts['host'])&&(strtolower($parts['host'])!==strtolower($base['host'])||isset($parts['port'])))return '';
    if(isset($parts['scheme'])&&!in_array($parts['scheme'],array('https','http'),true))return '';
    $path=$parts['path']??'';$basepath=rtrim($base['path']??'','/');
    foreach(hsh_consolidation_records() as $slug=>$record)if($path===$basepath.'/'.$slug.'/'||$path===$basepath.'/'.$slug)return home_url('/'.$record['target'].'/').'#'.$record['anchor'];
    return '';
}
add_action('template_redirect',function(){
    if(is_admin()||!in_array($_SERVER['REQUEST_METHOD']??'GET',array('GET','HEAD'),true))return;
    $path=wp_parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);if(!is_string($path))return;
    $destination=hsh_consolidation_destination($path);
    if(!$destination){$p=get_post(get_queried_object_id());$merged=hsh_consolidation_records();if($p&&isset($merged[$p->post_name]))$destination=home_url('/'.$merged[$p->post_name]['target'].'/').'#'.$merged[$p->post_name]['anchor'];}
    if($destination){wp_safe_redirect($destination,301,'Hirogaru editorial consolidation');exit;}
},0);
function hsh_consolidation_links($html){
    if(!hsh_consolidations())return $html;
    return preg_replace_callback('~(\bhref\s*=\s*)([\x22\x27])([^\x22\x27]+)\2~i',function($m){$url=hsh_consolidation_destination(html_entity_decode($m[3],ENT_QUOTES,'UTF-8'));return $url?$m[1].$m[2].esc_url($url).$m[2]:$m[0];},$html);
}
