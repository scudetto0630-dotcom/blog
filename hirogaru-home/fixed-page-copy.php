<?php
if (!defined('ABSPATH')) { exit; }
/** Narrow display-only copy corrections; stored form shortcodes and legal clauses are retained. */
function hsh_fixed_page_copy($content,$id) {
    if ((int)$id===14) {
        return str_replace('当ブログへのお問い合わせは下記フォームよりお願いいたします。','お問い合わせは、以下のフォームからお願いします。',$content);
    }
    if ((int)$id===18) {
        $content=preg_replace('~<h1 class="wp-block-heading">プライバシーポリシー</h1>~u','',$content,1);
        return str_replace('広告配信事業者は、','広告を配信する事業者は、',$content);
    }
    return $content;
}
add_filter('the_content',function($content){
    if (is_singular('page') && in_the_loop() && is_main_query()) { return hsh_fixed_page_copy($content,get_the_ID()); }
    return $content;
},21);
function hsh_comment_note_copy($html) {
    return str_replace(' が付いている欄は必須項目です</p>','印のある欄は必須項目です。</p>',$html);
}
// Cocoon supplies explicit form arguments, which override comment_form_defaults.
// Restrict buffering to the core's paired comment-form hooks; preserve every field.
add_action('comment_form_before',function(){
    $GLOBALS['hsh_comment_buffer_levels'][]=ob_get_level();
    ob_start('hsh_comment_note_copy');
});
add_action('comment_form_after',function(){
    if(empty($GLOBALS['hsh_comment_buffer_levels'])){return;}
    $level=array_pop($GLOBALS['hsh_comment_buffer_levels']);
    if(ob_get_level()===$level+1){ob_end_flush();}
});
