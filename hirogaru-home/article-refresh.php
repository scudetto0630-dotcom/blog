<?php
if (!defined('ABSPATH')) { exit; }
/** Media + revision updates are limited to the same three bridge-owned articles. */
function hsh_article_fingerprint($post) {
    return hash('sha256', wp_json_encode(array($post->post_title, $post->post_content, $post->post_excerpt, (int)get_post_thumbnail_id($post->ID))));
}
function hsh_article_assets() {
    $manifest = json_decode(file_get_contents(plugin_dir_path(__FILE__).'assets/articles/manifest.json'), true);
    return is_array($manifest) ? $manifest : array();
}
function hsh_article_media($key, $post_id, $author) {
    $assets = hsh_article_assets();
    if (!isset($assets[$key]) || !preg_match('/^(cat|whisky|csv)-(cover|diagram)$/D', $key)) { return new WP_Error('hsh_asset','未対応の画像です。',array('status'=>400)); }
    $asset = $assets[$key];
    if (!isset($asset['file']) || !in_array($asset['file'], array($key.'.png', $key.'.webp'), true)) { return new WP_Error('hsh_asset','画像形式が不正です。',array('status'=>400)); }
    $extension = pathinfo($asset['file'], PATHINFO_EXTENSION);
    $path = plugin_dir_path(__FILE__).'assets/articles/'.$asset['file'];
    if (!is_file($path) || !hash_equals($asset['sha256'], hash_file('sha256',$path))) { return new WP_Error('hsh_asset','画像ファイルの整合性を確認できません。',array('status'=>500)); }
    $map = get_option('hsh_article_media',array());
    $cache_key = $key.':'.$asset['sha256'];
    $id = isset($map[$cache_key]) ? (int)$map[$cache_key] : 0;
    if ($id && (get_post_type($id)!=='attachment' || get_post_meta($id,'_hsh_article_asset',true)!==$cache_key)) { return new WP_Error('hsh_media_conflict','登録済み画像が変更されています。',array('status'=>409)); }
    if (!function_exists('wp_generate_attachment_metadata')) { require_once ABSPATH.'wp-admin/includes/image.php'; }
    if (!$id) {
        $upload = wp_upload_bits('hsh-'.$key.'-'.substr($asset['sha256'],0,10).'.'.$extension,null,file_get_contents($path));
        if (!empty($upload['error'])) { return new WP_Error('hsh_upload','画像を保存できませんでした。',array('status'=>500)); }
        $id = wp_insert_attachment(wp_slash(array('post_mime_type'=>($extension==='webp'?'image/webp':'image/png'),'post_title'=>$asset['alt'],'post_status'=>'inherit','post_author'=>$author,'post_parent'=>$post_id,'meta_input'=>array('_hsh_article_asset'=>$cache_key))),$upload['file'],$post_id,true);
        if (is_wp_error($id)) { return $id; }
        $map[$cache_key]=(int)$id; update_option('hsh_article_media',$map,false);
        update_post_meta($id,'_wp_attachment_image_alt',$asset['alt']);
    }
    if (!wp_get_attachment_metadata($id)) {
        $metadata = wp_generate_attachment_metadata($id,get_attached_file($id));
        if (!is_array($metadata) || empty($metadata['width'])) { return new WP_Error('hsh_media_metadata','画像サイズを作成できません。登録済み画像を保持して停止しました。',array('status'=>500)); }
        wp_update_attachment_metadata($id,$metadata);
    }
    return (int)$id;
}
function hsh_refresh_plan() {
    $items = array();
    foreach (hsh_starter_articles() as $article) {
        $post = get_page_by_path($article['slug'],OBJECT,'post');
        $owned = $post && get_post_meta($post->ID,'_hsh_starter_owned',true)===$article['slug'];
        $items[] = array('slug'=>$article['slug'],'id'=>$owned?$post->ID:null,'status'=>$owned?$post->post_status:'unavailable','fingerprint'=>$owned?hsh_article_fingerprint($post):null,'content'=>$owned?$post->post_content:null,'title'=>$owned?$post->post_title:null,'revision'=>$owned?get_post_meta($post->ID,'_hsh_article_revision',true):null,'featured_image_id'=>$owned?(int)get_post_thumbnail_id($post->ID):null,'url'=>$owned?get_permalink($post):null);
    }
    return hsh_edit_response(array('articles'=>$items,'target_revision'=>'language-20261015-v3'));
}
function hsh_refresh_articles($request) {
    $data=$request->get_json_params();
    if (!is_array($data) || array_diff(array_keys($data),array('expected')) || !isset($data['expected']) || !is_array($data['expected']) || count($data['expected'])!==3) { return new WP_Error('hsh_input','3記事の確認済み指紋が必要です。',array('status'=>400)); }
    if (!add_option('hsh_starter_lock',time(),'',false)) { return new WP_Error('hsh_busy','他の投稿処理が実行中です。',array('status'=>409)); }
    try {
        $articles=hsh_starter_articles();
        if (count($articles)!==3) { return new WP_Error('hsh_bundle','記事データが不正です。',array('status'=>500)); }
        foreach ($articles as $article) {
            $post=get_page_by_path($article['slug'],OBJECT,'post');
            if (!$post || get_post_meta($post->ID,'_hsh_starter_owned',true)!==$article['slug'] || $post->post_status!=='publish' || !user_can((int)$post->post_author,'publish_posts')) { return new WP_Error('hsh_owned','公開済みの専用記事を確認できません。',array('status'=>409)); }
            $expected=isset($data['expected'][$article['slug']])?$data['expected'][$article['slug']]:null;
            if (!is_string($expected) || !preg_match('/^[a-f0-9]{64}$/D',$expected) || !hash_equals(hsh_article_fingerprint($post),$expected)) { return new WP_Error('hsh_conflict','確認後に記事が変更されています。再確認が必要です。',array('status'=>409)); }
        }
        foreach ($articles as $article) {
            $post=get_page_by_path($article['slug'],OBJECT,'post');
            $topic=substr($article['hero'],0,-6);
            $hero=hsh_article_media($article['hero'],$post->ID,(int)$post->post_author); if(is_wp_error($hero)){return $hero;}
            $diagram=hsh_article_media($topic.'-diagram',$post->ID,(int)$post->post_author); if(is_wp_error($diagram)){return $diagram;}
            $assets=hsh_article_assets();
            $caption=array('cat'=>'購入前に見ておきたい5つの場所。図は説明用のイラストです。','whisky'=>'量る・静かに注ぐ・メモする。分量は比較のための一例です。','csv'=>'元ファイルを残し、コピーで確認する流れ。図は説明用です。');
            $image=wp_get_attachment_image($diagram,'large',false,array('alt'=>$assets[$topic.'-diagram']['alt'],'loading'=>'lazy','decoding'=>'async'));
            if (!$image) { return new WP_Error('hsh_media','本文画像を確認できません。',array('status'=>500)); }
            $figure='<figure class="wp-block-image size-large"><a href="'.esc_url(wp_get_attachment_url($diagram)).'">'.$image.'</a><figcaption>'.esc_html($caption[$topic]).'</figcaption></figure>';
            $content=str_replace('<!--HSH_IMAGE:'.$topic.'-diagram-->',$figure,$article['content']);
            if (strpos($content,'<!--HSH_IMAGE:')!==false) { return new WP_Error('hsh_placeholder','画像の差し込み位置が不正です。',array('status'=>500)); }
            // A second check detects edits made while image variants were generated.
            clean_post_cache($post->ID); $latest=get_post($post->ID);
            if (!$latest || !hash_equals($data['expected'][$article['slug']],hsh_article_fingerprint($latest))) { return new WP_Error('hsh_conflict','画像準備中に記事が変更されました。本文は更新していません。',array('status'=>409)); }
            $history=get_post_meta($post->ID,'_hsh_edit_history',true);if(!is_array($history)){$history=array();}
            $history[]=array('time'=>time(),'title'=>$post->post_title,'content'=>$post->post_content,'excerpt'=>$post->post_excerpt,'thumbnail'=>(int)get_post_thumbnail_id($post->ID));
            update_post_meta($post->ID,'_hsh_edit_history',array_slice($history,-10));
            $updated=wp_update_post(wp_slash(array('ID'=>$post->ID,'post_title'=>sanitize_text_field($article['title']),'post_content'=>wp_kses_post($content),'post_excerpt'=>sanitize_text_field($article['excerpt']))),true);
            if(is_wp_error($updated)){return $updated;}
            set_post_thumbnail($post->ID,$hero);
            if ((int)get_post_thumbnail_id($post->ID)!==$hero) { return new WP_Error('hsh_thumbnail','アイキャッチを設定できませんでした。',array('status'=>500)); }
            update_post_meta($post->ID,'_hsh_article_revision',$article['revision']);
            update_post_meta($post->ID,'the_page_meta_description',sanitize_text_field($article['excerpt']));
        }
        return hsh_refresh_plan();
    } finally { delete_option('hsh_starter_lock'); }
}
add_action('rest_api_init',function(){
    register_rest_route('hirogaru/v1','/starter-refresh',array(
        array('methods'=>'GET','callback'=>'hsh_refresh_plan','permission_callback'=>'hsh_edit_permission'),
        array('methods'=>'POST','callback'=>'hsh_refresh_articles','permission_callback'=>'hsh_edit_permission'),
    ));
});

/** Subsequent text corrections need no plugin reinstall, but only these owned posts. */
function hsh_managed_article($request) {
    $data=$request->get_json_params();
    if (!is_array($data) || array_diff(array_keys($data),array('slug','expected','title','content','excerpt')) || !isset($data['slug'],$data['expected'],$data['content']) || !is_string($data['slug']) || !is_string($data['expected']) || !is_string($data['content']) || strlen($data['content'])>100000) { return new WP_Error('hsh_input','入力項目が不正です。',array('status'=>400)); }
    $allowed=array_column(hsh_starter_articles(),'slug');
    if (!in_array($data['slug'],$allowed,true)) { return new WP_Error('hsh_owned','専用3記事以外は編集できません。',array('status'=>403)); }
    foreach(array('title'=>600,'excerpt'=>2400) as $field=>$max){if(isset($data[$field]) && (!is_string($data[$field]) || strlen($data[$field])>$max || ($field==='title' && trim(sanitize_text_field($data[$field]))===''))){return new WP_Error('hsh_input','文字列が不正です。',array('status'=>400));}}
    if (!add_option('hsh_starter_lock',time(),'',false)) { return new WP_Error('hsh_busy','他の編集処理が実行中です。',array('status'=>409)); }
    try {
        $post=get_page_by_path($data['slug'],OBJECT,'post');
        if (!$post || get_post_meta($post->ID,'_hsh_starter_owned',true)!==$data['slug'] || $post->post_status!=='publish' || !user_can((int)$post->post_author,'publish_posts')) { return new WP_Error('hsh_owned','公開済みの専用記事を確認できません。',array('status'=>409)); }
        if (!preg_match('/^[a-f0-9]{64}$/D',$data['expected']) || !hash_equals(hsh_article_fingerprint($post),$data['expected'])) { return new WP_Error('hsh_conflict','記事が変更されています。',array('status'=>409)); }
        $history=get_post_meta($post->ID,'_hsh_edit_history',true);if(!is_array($history)){$history=array();}
        $history[]=array('time'=>time(),'title'=>$post->post_title,'content'=>$post->post_content,'excerpt'=>$post->post_excerpt,'thumbnail'=>(int)get_post_thumbnail_id($post->ID));
        update_post_meta($post->ID,'_hsh_edit_history',array_slice($history,-10));
        $update=array('ID'=>$post->ID,'post_content'=>wp_kses_post($data['content']));
        if(isset($data['title'])){$update['post_title']=sanitize_text_field($data['title']);}
        if(isset($data['excerpt'])){$update['post_excerpt']=sanitize_text_field($data['excerpt']);}
        $result=wp_update_post(wp_slash($update),true);if(is_wp_error($result)){return $result;}
        return hsh_refresh_plan();
    } finally { delete_option('hsh_starter_lock'); }
}
add_action('rest_api_init',function(){register_rest_route('hirogaru/v1','/managed-article',array('methods'=>'POST','callback'=>'hsh_managed_article','permission_callback'=>'hsh_edit_permission'));});

/** Import one packaged asset per request so image resizing stays below host timeouts. */
function hsh_starter_image($request) {
    $data=$request->get_json_params();
    if (!is_array($data) || array_diff(array_keys($data),array('slug','key')) || !isset($data['slug'],$data['key']) || !is_string($data['slug']) || !is_string($data['key'])) { return new WP_Error('hsh_input','画像の指定が不正です。',array('status'=>400)); }
    $selected=null;foreach(hsh_starter_articles() as $article){if($article['slug']===$data['slug']){$selected=$article;break;}}
    if (!$selected || !in_array($data['key'],array($selected['hero'],substr($selected['hero'],0,-6).'-diagram'),true)) { return new WP_Error('hsh_owned','指定記事の同梱画像だけを登録できます。',array('status'=>403)); }
    if (!add_option('hsh_starter_lock',time(),'',false)) { return new WP_Error('hsh_busy','他の処理が実行中です。',array('status'=>409)); }
    try {
        $post=get_page_by_path($data['slug'],OBJECT,'post');
        if (!$post || get_post_meta($post->ID,'_hsh_starter_owned',true)!==$data['slug'] || $post->post_status!=='publish' || !user_can((int)$post->post_author,'publish_posts')) { return new WP_Error('hsh_owned','公開済み専用記事を確認できません。',array('status'=>409)); }
        $id=hsh_article_media($data['key'],$post->ID,(int)$post->post_author);if(is_wp_error($id)){return $id;}
        return hsh_edit_response(array('id'=>$id,'key'=>$data['key'],'url'=>wp_get_attachment_url($id)));
    } finally { delete_option('hsh_starter_lock'); }
}
add_action('rest_api_init',function(){register_rest_route('hirogaru/v1','/starter-image',array('methods'=>'POST','callback'=>'hsh_starter_image','permission_callback'=>'hsh_edit_permission'));});
