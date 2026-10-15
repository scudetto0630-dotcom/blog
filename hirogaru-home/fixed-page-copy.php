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
add_filter('comment_form_defaults',function($defaults){
    $defaults['comment_notes_before']='<p class="comment-notes">メールアドレスは公開されません。*印のある欄は必須項目です。</p>';
    return $defaults;
});
