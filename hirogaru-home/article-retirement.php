<?php
if(!defined('ABSPATH'))exit;
/** Scoped permanent withdrawal. Bodies remain private, unchanged, and recoverable. */
function hsh_retirement_plan(){static $plan=null;if($plan===null)$plan=json_decode(file_get_contents(__DIR__.'/article-retirements.json'),true);return $plan;}
function hsh_retired_articles(){
    $saved=get_option('hsh_article_retirements',array());$out=array();foreach(hsh_retirement_plan() as $slug=>$reason){if(!isset($saved[$slug]))continue;$p=get_page_by_path($slug,OBJECT,'post');if($p&&$p->post_status==='draft'&&get_post_meta($p->ID,'_hsh_bundle_owned',true)===$slug)$out[$slug]=(int)$p->ID;}return $out;
}
function hsh_apply_retirement($request){
    $d=$request->get_json_params();$plan=hsh_retirement_plan();
    if(!is_array($d)||array_keys($d)!==array('expected')||!is_array($d['expected'])||count($d['expected'])!==count($plan)||array_diff(array_keys($d['expected']),array_keys($plan)))return new WP_Error('hsh_input','対象全記事の指紋を指定してください。',array('status'=>400));
    if(!add_option('hsh_bundle_lock',time(),'',false))return new WP_Error('hsh_busy','他の記事を処理中です。',array('status'=>409));
    $changed=array();$committed=false;$old_saved=get_option('hsh_article_retirements',array());
    try{
        $posts=array();foreach($plan as $slug=>$reason){$p=get_page_by_path($slug,OBJECT,'post');$fp=$d['expected'][$slug]??null;if(!is_string($fp)||!preg_match('/^[a-f0-9]{64}$/D',$fp)||!$p||$p->post_status!=='publish'||get_post_meta($p->ID,'_hsh_bundle_owned',true)!==$slug||!hash_equals(hsh_article_fingerprint($p),$fp))return new WP_Error('hsh_conflict','記事が変更されています。再確認してください。',array('status'=>409));$posts[$slug]=$p;}
        $saved=get_option('hsh_article_retirements',array());
        foreach($posts as $slug=>$p){
            hsh_remember_blog_post($p);
            $changed[]=$p->ID;$result=wp_update_post(array('ID'=>$p->ID,'post_status'=>'draft'),true);
            $after=get_post($p->ID);if(is_wp_error($result)||!$after||$after->post_status!=='draft'||!hash_equals($d['expected'][$slug],hsh_article_fingerprint($after)))return new WP_Error('hsh_store','本文を変えずに保存できませんでした。',array('status'=>500));
            $saved[$slug]=array('at'=>time(),'fingerprint'=>$d['expected'][$slug],'reason'=>$plan[$slug]);
        }
        update_option('hsh_article_retirements',$saved,false);
        if(count(hsh_retired_articles())!==count($plan))return new WP_Error('hsh_store','公開終了の状態を保存できませんでした。',array('status'=>500));
        $committed=true;return hsh_edit_response(array('retired'=>array_keys($plan),'original_posts_preserved'=>true,'status'=>410,'fingerprints'=>$d['expected']));
    }finally{if(!$committed){foreach($changed as $id)wp_update_post(array('ID'=>$id,'post_status'=>'publish'),true);update_option('hsh_article_retirements',$old_saved,false);}delete_option('hsh_bundle_lock');}
}
add_action('rest_api_init',function(){register_rest_route('hirogaru/v1','/content-retirement',array('methods'=>'POST','callback'=>'hsh_apply_retirement','permission_callback'=>'hsh_edit_permission'));});
function hsh_retired_path($path){
    $base=rtrim(wp_parse_url(home_url('/'),PHP_URL_PATH)??'','/');foreach(hsh_retired_articles() as $slug=>$id)if($path===$base.'/'.$slug.'/'||$path===$base.'/'.$slug)return $slug;return '';
}
function hsh_is_retired_request(){return !empty($GLOBALS['hsh_retired_request']);}
add_action('template_redirect',function(){
    if(is_admin()||!in_array($_SERVER['REQUEST_METHOD']??'GET',array('GET','HEAD'),true))return;
    $path=wp_parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);$slug=is_string($path)?hsh_retired_path($path):'';
    if(!$slug)return;$GLOBALS['hsh_retired_request']=$slug;status_header(410);nocache_headers();
    // Execute here, before WordPress canonical redirection or a theme's soft 404.
    include __DIR__.'/retired-template.php';exit;
},-1);
