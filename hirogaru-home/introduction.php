<?php
if (!defined('ABSPATH')) { exit; }
/** The existing introduction was inspected before adding this single-post scope. */
function hsh_intro_post() {
    $post = get_post(8);
    if (!$post || $post->post_type !== 'post' || $post->post_status !== 'publish' || (int)$post->post_author !== 1 || rawurldecode($post->post_name) !== 'ひろがる趣味暮らしについて' || !user_can(1,'publish_posts')) { return new WP_Error('hsh_intro_scope','元のブログ紹介記事を確認できません。',array('status'=>409)); }
    return $post;
}
function hsh_intro_review() {
    $post=hsh_intro_post();if(is_wp_error($post)){return $post;}
    return hsh_edit_response(array('id'=>$post->ID,'title'=>$post->post_title,'content'=>$post->post_content,'excerpt'=>$post->post_excerpt,'fingerprint'=>hsh_article_fingerprint($post),'url'=>get_permalink($post)));
}
function hsh_intro_refresh($request) {
    $data=$request->get_json_params();
    if (!is_array($data) || array_keys($data)!==array('expected') || !is_string($data['expected']) || !preg_match('/^[a-f0-9]{64}$/D',$data['expected'])) { return new WP_Error('hsh_input','確認済みの記事の指紋が必要です。',array('status'=>400)); }
    if (!add_option('hsh_starter_lock',time(),'',false)) { return new WP_Error('hsh_busy','ほかの記事を更新中です。',array('status'=>409)); }
    try {
        $post=hsh_intro_post();if(is_wp_error($post)){return $post;}
        if (!hash_equals(hsh_article_fingerprint($post),$data['expected'])) { return new WP_Error('hsh_conflict','確認後に紹介記事が変更されています。',array('status'=>409)); }
        $article=json_decode(file_get_contents(plugin_dir_path(__FILE__).'site-introduction.json'),true);
        if(!is_array($article)||empty($article['title'])||empty($article['content'])||!isset($article['excerpt'])){return new WP_Error('hsh_bundle','紹介文を読み込めません。',array('status'=>500));}
        $history=get_post_meta($post->ID,'_hsh_edit_history',true);if(!is_array($history)){$history=array();}
        $history[]=array('time'=>time(),'title'=>$post->post_title,'content'=>$post->post_content,'excerpt'=>$post->post_excerpt,'thumbnail'=>(int)get_post_thumbnail_id($post->ID));
        update_post_meta($post->ID,'_hsh_edit_history',array_slice($history,-10));
        $result=wp_update_post(wp_slash(array('ID'=>$post->ID,'post_title'=>sanitize_text_field($article['title']),'post_content'=>wp_kses_post($article['content']),'post_excerpt'=>sanitize_text_field($article['excerpt']))),true);
        if(is_wp_error($result)){return $result;}
        update_post_meta($post->ID,'the_page_meta_description',sanitize_text_field($article['excerpt']));
        update_post_meta($post->ID,'_hsh_intro_revision','proofread-20261015-v4');
        return hsh_intro_review();
    } finally { delete_option('hsh_starter_lock'); }
}
add_action('rest_api_init',function(){register_rest_route('hirogaru/v1','/introduction',array(array('methods'=>'GET','callback'=>'hsh_intro_review','permission_callback'=>'hsh_edit_permission'),array('methods'=>'POST','callback'=>'hsh_intro_refresh','permission_callback'=>'hsh_edit_permission')));});
