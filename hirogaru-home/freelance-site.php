<?php
/** Independent portfolio: exact routes only, no changes to WordPress content. */
if (!defined('ABSPATH')) { exit; }
function hsh_freelance_route($uri) {
    $path=parse_url($uri,PHP_URL_PATH);
    if($path==='/freelance')return 'redirect';
    return ($path==='/freelance/'||$path==='/freelance/index.html')?'page':null;
}
add_action('parse_request',function(){
    $route=hsh_freelance_route($_SERVER['REQUEST_URI'] ?? '/');
    if($route===null)return;
    $method=$_SERVER['REQUEST_METHOD'] ?? 'GET';
    if(!in_array($method,array('GET','HEAD'),true)){status_header(405);header('Allow: GET, HEAD');exit;}
    if($route==='redirect'){wp_safe_redirect(home_url('/freelance/'),301);exit;}
    $file=__DIR__.'/freelance-site/index.html';
    if(!is_file($file)){status_header(503);exit;}
    $html=file_get_contents($file);
    $csp="default-src 'none'; img-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'; object-src 'none'";
    foreach(array('style','script') as $tag){
        preg_match_all('~<'.$tag.'>(.*?)</'.$tag.'>~s',$html,$matches);
        $hashes=array();
        foreach($matches[1] as $source)$hashes[]="'sha256-".base64_encode(hash('sha256',$source,true))."'";
        if($hashes)$csp.='; '.$tag.'-src '.implode(' ',$hashes);
    }
    status_header(200);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Robots-Tag: noindex, follow');
    header('Content-Security-Policy: '.$csp);
    if($method!=='HEAD')echo $html;
    exit;
},0);
