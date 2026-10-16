<?php
/** Serve the independent static site without changing WordPress pages or blog templates. */
if (!defined('ABSPATH')) { exit; }
function hsh_catguide_route($uri) {
    $path = parse_url($uri, PHP_URL_PATH);
    if (!is_string($path)) { return null; }
    $prefix = '/neko-guide';
    if ($path === $prefix) { return array('redirect' => $prefix.'/'); }
    if (strpos($path, $prefix.'/') !== 0) { return null; }
    $relative = rawurldecode(substr($path, strlen($prefix)+1));
    if (strpos($relative,"\0") !== false || strpos($relative,'\\') !== false || preg_match('~(^|/)\.\.(/|$)~',$relative)) { return array('file'=>'404.html','status'=>404); }
    if ($relative === '' || substr($relative,-1) === '/') { $relative .= 'index.html'; }
    $manifest = json_decode(file_get_contents(__DIR__.'/cat-guide-preview/manifest.json'),true);
    if (!is_array($manifest) || !in_array($relative,$manifest,true)) { return array('file'=>'404.html','status'=>404); }
    return array('file'=>$relative,'status'=>200);
}
add_action('parse_request',function() {
    $route=hsh_catguide_route($_SERVER['REQUEST_URI'] ?? '/');
    if ($route === null) { return; }
    if (isset($route['redirect'])) { wp_safe_redirect(home_url($route['redirect']),301); exit; }
    $method=$_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method,array('GET','HEAD'),true)) { status_header(405); header('Allow: GET, HEAD'); exit; }
    $root=realpath(__DIR__.'/cat-guide-preview');
    $file=$root ? realpath($root.'/'.$route['file']) : false;
    if (!$file || strpos($file,$root.DIRECTORY_SEPARATOR)!==0 || !is_file($file)) { status_header(503); exit; }
    $types=array('html'=>'text/html; charset=UTF-8','css'=>'text/css; charset=UTF-8','js'=>'text/javascript; charset=UTF-8','svg'=>'image/svg+xml','webp'=>'image/webp','xml'=>'application/xml; charset=UTF-8');
    $extension=pathinfo($file,PATHINFO_EXTENSION);
    if (!isset($types[$extension])) { status_header(404); exit; }
    status_header($route['status']);
    header('Content-Type: '.$types[$extension]);
    header('Cache-Control: no-store, max-age=0');
    if ($route['status'] !== 200) { header('X-Robots-Tag: noindex, nofollow'); }
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self'; script-src 'self'; style-src 'self'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'; object-src 'none'");
    if ($method !== 'HEAD') { readfile($file); }
    exit;
},0);

// Keep existing WordPress directives and advertise this independent site's sitemap.
function hsh_catguide_robots($output,$public) {
    if ($public && strpos($output,'/neko-guide/sitemap.xml')===false) { $output=rtrim($output)."\nSitemap: ".home_url('/neko-guide/sitemap.xml')."\n"; }
    return $output;
}
add_filter('robots_txt','hsh_catguide_robots',10,2);
