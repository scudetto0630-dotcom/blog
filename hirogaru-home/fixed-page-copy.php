<?php
if (!defined('ABSPATH')) { exit; }
/** Narrow display-only copy corrections; stored form shortcodes and legal clauses are retained. */
function hsh_fixed_page_copy($content,$id) {
    if ((int)$id===14) {
        return str_replace('当ブログへのお問い合わせは下記フォームよりお願いいたします。','お問い合わせは、以下のフォームからお願いします。',$content);
    }
    if ((int)$id===18) {
        $content.='<h2>記事の誤り報告について</h2><p>記事名と報告内容を、訂正の確認に利用します。氏名やメールアドレスの入力は不要です。報告は管理人だけが確認し、90日を目安に削除します。個人情報や秘密情報は書かないでください。送信回数を制限するため、接続元のIPアドレスから一時的な識別子を作り、最大1時間保持します。報告本文にIPアドレスそのものは保存しません。</p>';
        $content=preg_replace('~<h1 class="wp-block-heading">プライバシーポリシー</h1>~u','',$content,1);
        return str_replace(array('広告配信事業者は、','第三者配信の広告サービス','トラフィックデータ収集のために','当サイトに掲載している情報については、可能な限り正確な情報を掲載するよう努めておりますが、その正確性や安全性を保証するものではありません。'),array('広告を配信する事業者は、','外部の事業者が配信する広告サービス','アクセス状況を調べるために','当サイトでは、できる限り正確な情報を掲載するよう努めています。ただし、掲載内容の正確性や安全性を保証するものではありません。'),$content);
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

/** Only the two existing public utility pages may receive packaged, fingerprinted copy. */
function hsh_public_page_specs(){static $rows=null;if($rows===null)$rows=json_decode(file_get_contents(__DIR__.'/public-page-copy.json'),true);return $rows;}
function hsh_public_page_post($key){
    $spec=hsh_public_page_specs()[$key]??null;if(!$spec)return null;$p=get_post($spec['id']);$home=get_post((int)get_option('hsh_page_id'));
    return $p&&$home&&$p->post_type==='page'&&$p->post_status==='publish'&&rawurldecode($p->post_name)===$spec['slug']&&(int)$p->post_author===(int)$home->post_author?$p:null;
}
function hsh_public_page_status(){
    $rows=array();foreach(hsh_public_page_specs() as $key=>$spec){$p=hsh_public_page_post($key);$rows[$key]=$p?array('id'=>(int)$p->ID,'fingerprint'=>hsh_article_fingerprint($p),'content'=>$p->post_content,'title'=>$p->post_title,'published_at'=>$p->post_date,'published_at_gmt'=>$p->post_date_gmt):array('status'=>'conflict');}return hsh_edit_response(array('pages'=>$rows));
}
function hsh_public_page_release($request){
    $d=$request->get_json_params();if(!is_array($d)||array_diff(array_keys($d),array('page','expected'))||!isset($d['page'],$d['expected'])||!is_string($d['page'])||!is_string($d['expected'])||!preg_match('/^[a-f0-9]{64}$/D',$d['expected']))return new WP_Error('hsh_input','ページと現在の指紋を指定してください。',array('status'=>400));
    $spec=hsh_public_page_specs()[$d['page']]??null;if(!$spec)return new WP_Error('hsh_scope','指定の公開ページだけを更新できます。',array('status'=>403));
    if(!add_option('hsh_public_page_lock',time(),'',false))return new WP_Error('hsh_busy','他のページを更新中です。',array('status'=>409));
    try{
        $p=hsh_public_page_post($d['page']);if(!$p||!hash_equals(hsh_article_fingerprint($p),$d['expected']))return new WP_Error('hsh_conflict','ページが変更されています。再確認してください。',array('status'=>409));
        $content=$spec['content']??'';
        if($d['page']==='contact'){
            preg_match_all('~\[contact-form-7\b[^\]]+\]~',$p->post_content,$forms);
            if(count($forms[0])!==1)return new WP_Error('hsh_form','現在のお問い合わせフォームを確認してください。',array('status'=>409));
            $content=$spec['intro']."\n".$forms[0][0]."\n".$spec['after'];
        }
        $history=get_post_meta($p->ID,'_hsh_public_page_history',true);if(!is_array($history))$history=array();$history[]=array('at'=>time(),'fingerprint'=>$d['expected'],'title'=>$p->post_title,'content'=>$p->post_content,'excerpt'=>$p->post_excerpt,'published_at'=>$p->post_date,'published_at_gmt'=>$p->post_date_gmt);update_post_meta($p->ID,'_hsh_public_page_history',array_slice($history,-10));
        $id=wp_update_post(wp_slash(array('ID'=>$p->ID,'post_content'=>wp_kses_post($content),'post_excerpt'=>sanitize_text_field($spec['excerpt']),'post_date'=>$p->post_date,'post_date_gmt'=>$p->post_date_gmt)),true);if(is_wp_error($id))return $id;
        update_post_meta($p->ID,'the_page_meta_description',sanitize_text_field($spec['excerpt']));return hsh_edit_response(array('id'=>$id,'fingerprint'=>hsh_article_fingerprint(get_post($id))));
    }finally{delete_option('hsh_public_page_lock');}
}
add_action('rest_api_init',function(){register_rest_route('hirogaru/v1','/public-page-review',array(array('methods'=>'GET','callback'=>'hsh_public_page_status','permission_callback'=>'hsh_edit_permission'),array('methods'=>'POST','callback'=>'hsh_public_page_release','permission_callback'=>'hsh_edit_permission')));});
