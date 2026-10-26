<?php
if(!defined('ABSPATH'))exit;
/** Owner supplied photographs and explicitly identified generated scenes. */
function hsh_photo_manifest(){static $data=null;if($data===null){$data=json_decode(file_get_contents(__DIR__.'/photo-assets.json'),true);if(!is_array($data))$data=array('assets'=>array(),'articles'=>array());}return $data;}
function hsh_photo_for_slug($slug){$m=hsh_photo_manifest();$key=$m['articles'][$slug]??null;return $key&&isset($m['assets'][$key])?$m['assets'][$key]:null;}
function hsh_photo_caption($asset){return $asset['kind']==='provided'?'まんもす（提供写真）':($asset['subject']==='manmos'?'提供写真を参考にしたAI生成画像です。':'AI生成のイメージです。');}
function hsh_photo_image($asset,$eager=false){
    if(!isset($asset['file'])||!preg_match('/^[a-z0-9-]+\.webp$/D',$asset['file']))return '';
    return '<img src="'.esc_url(plugin_dir_url(__FILE__).'assets/photos/'.$asset['file']).'" alt="'.esc_attr($asset['alt']).'" width="'.(int)$asset['width'].'" height="'.(int)$asset['height'].'" loading="'.($eager?'eager':'lazy').'" decoding="async"'.($eager?' fetchpriority="high"':'').'>';
}
function hsh_photo_cover($post,$eager=false){
    if(!$post)return '';$asset=hsh_photo_for_slug($post->post_name);if(!$asset)return '';
    return '<figure class="hsh-photo"><div class="hsh-cover hsh-photo-cover">'.hsh_photo_image($asset,$eager).'</div><figcaption>'.esc_html($asset['caption']).'</figcaption></figure>';
}
add_filter('the_content',function($content){
    if(!is_singular('post')||!in_the_loop()||!is_main_query())return $content;
    if(get_post_field('post_name',get_the_ID())!=='manmos-cat-life')return $content;
    $assets=hsh_photo_manifest()['assets'];$gallery='<section class="hsh-manmos-gallery"><h2>まんもすのいろいろな姿</h2><div>';
    foreach(array('manmos-lying','manmos-sitting','manmos-back','manmos-stretch') as $key){$gallery.='<figure class="hsh-photo">'.hsh_photo_image($assets[$key]).'<figcaption>'.esc_html($assets[$key]['caption']).'</figcaption></figure>';}
    return $content.$gallery.'</div></section>';
},23);
