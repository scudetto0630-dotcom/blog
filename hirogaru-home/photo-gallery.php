<?php
if(!defined('ABSPATH'))exit;
/** Owner supplied photographs and explicitly identified generated scenes. */
function hsh_photo_manifest(){static $data=null;if($data===null){$data=json_decode(file_get_contents(__DIR__.'/photo-assets.json'),true);if(!is_array($data))$data=array('assets'=>array(),'articles'=>array());}return $data;}
function hsh_photo_for_slug($slug){$m=hsh_photo_manifest();$key=$m['articles'][$slug]??$m['affiliate_articles'][$slug]??$m['pages'][$slug]??null;return $key&&isset($m['assets'][$key])?$m['assets'][$key]:null;}
function hsh_photo_caption($asset){
    if(($asset['kind']??'')==='generated')return '';
    return trim(str_replace(array('（提供写真）','(提供写真)','・提供写真','提供写真・','管理人提供の写真です。','管理人提供の写真。'),'', $asset['caption']??''));
}
function hsh_photo_figcaption($asset){$caption=hsh_photo_caption($asset);return $caption!==''?'<figcaption>'.esc_html($caption).'</figcaption>':'';}

function hsh_photo_image($asset,$eager=false,$sizes='(max-width:760px) calc(100vw - 44px), 740px'){
    if(!isset($asset['file'])||!preg_match('/^[a-z0-9-]+\.webp$/D',$asset['file']))return '';
    $base=plugin_dir_url(__FILE__).'assets/photos/';$responsive='';$candidates=array();
    foreach($asset['variants']??array() as $v)if(isset($v['file'],$v['width'])&&preg_match('/^[a-z0-9-]+\.webp$/D',$v['file'])&&(int)$v['width']>0)$candidates[]=$base.$v['file'].' '.(int)$v['width'].'w';
    if($candidates){$candidates[]=$base.$asset['file'].' '.(int)$asset['width'].'w';$responsive=' srcset="'.esc_attr(implode(', ',$candidates)).'" sizes="'.esc_attr($sizes).'" data-hsh-full="'.esc_url($base.$asset['file']).'"';}
    $native=isset($asset['source_url'])&&(int)$asset['width']<480?' style="max-width:'.(int)$asset['width'].'px;margin-inline:auto"':'';
    return '<img'.$native.' src="'.esc_url($base.$asset['file']).'"'.$responsive.' alt="'.esc_attr($asset['alt']).'" width="'.(int)$asset['width'].'" height="'.(int)$asset['height'].'" loading="'.($eager?'eager':'lazy').'" decoding="async"'.($eager?' fetchpriority="high"':'').'>';
}
function hsh_photo_cover($post,$eager=false){
    if(!$post)return '';$asset=hsh_photo_for_slug($post->post_name);if(!$asset)return '';
    return '<figure class="hsh-photo'.($asset['height']>$asset['width']?' hsh-photo-portrait':'').'"><div class="hsh-cover hsh-photo-cover">'.hsh_photo_image($asset,$eager,$eager?'(max-width:760px) calc(100vw - 44px), 740px':'(max-width:600px) 104px, (max-width:1000px) 45vw, 360px').'</div>'.hsh_photo_figcaption($asset).'</figure>';
}
/** Place body photos at separate, balanced section boundaries, never within paragraphs or tables. */
function hsh_insert_inline_photos($content,$slug){
    $m=hsh_photo_manifest();$keys=$m['affiliate_product_images'][$slug]??$m['inline'][$slug]??$m['affiliate_inline'][$slug]??array();
    if(!$keys||strpos($content,'hsh-inline-photo')!==false)return $content;
    $end=strpos($content,'<nav class="hsh-next-read"');
    $body=$end!==false?substr($content,0,$end):$content;
    preg_match_all('~<h2\b[^>]*>~i',$body,$matches,PREG_OFFSET_CAPTURE);
    $positions=array();$n=count($matches[0]);
    foreach($keys as $i=>$key){
        if(!isset($m['assets'][$key]))continue;$a=$m['assets'][$key];
        $html='<figure class="hsh-photo hsh-inline-photo">'.hsh_photo_image($a).hsh_photo_figcaption($a).'</figure>';
        $index=$n?min($n-1,(int)floor(($i+1)*$n/(count($keys)+1))):0;
        $offset=$n?$matches[0][$index][1]:strlen($body);
        $positions[]=array($offset,$html);
    }
    usort($positions,function($a,$b){return $b[0]<=>$a[0];});
    foreach($positions as $insert)$content=substr_replace($content,$insert[1],$insert[0],0);
    return $content;
}
function hsh_topic_photo($slug){
    $m=hsh_photo_manifest();$key=$m['topics'][$slug]??'';
    if(!$key||!isset($m['assets'][$key]))return '';$a=$m['assets'][$key];
    return '<span class="hsh-topic-photo">'.hsh_photo_image($a).(hsh_photo_caption($a)!==''?'<span class="hsh-topic-caption">'.esc_html(hsh_photo_caption($a)).'</span>':'').'</span>';
}
add_filter('the_content',function($content){
    if(!is_singular('post')||!in_the_loop()||!is_main_query())return $content;
    $slug=get_post_field('post_name',get_the_ID());
    $manifest=hsh_photo_manifest();
    $content=hsh_insert_inline_photos($content,$slug);
    if($slug!=='manmos-cat-life')return $content;
    $assets=hsh_photo_manifest()['assets'];$gallery='<section class="hsh-manmos-gallery"><h2>まんもすのいろいろな姿</h2><div>';
    foreach(array('manmos-back','manmos-stretch') as $key){$gallery.='<figure class="hsh-photo">'.hsh_photo_image($assets[$key]).hsh_photo_figcaption($assets[$key]).'</figure>';}
    return $content.$gallery.'</div></section>';
},23);

/** The first hero photo is immediate; other photos wait until they are needed. */
function hsh_photo_slide_image($asset,$first=false) {
    $image=hsh_photo_image($asset,$first,'100vw');
    if($first||$image==='')return $image;
    $image=str_replace(array(' srcset=',' src='),array(' data-hsh-srcset=',' data-hsh-src='),$image);
    return str_replace('<img','<img src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs="',$image);
}
add_action('wp_head',function(){
    if(!is_front_page())return;
    $asset=hsh_photo_manifest()['assets']['manmos-rest']??null;
    if(!$asset)return;
    echo '<link rel="preload" as="image" href="'.esc_url(plugin_dir_url(__FILE__).'assets/photos/'.$asset['file']).'" fetchpriority="high">' . "\n";
},1);
