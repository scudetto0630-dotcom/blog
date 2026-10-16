<?php
require __DIR__.'/proofread-copy.php';
require_once dirname(__DIR__).'/hirogaru-home/cat-guide-preview.php';
check(hsh_catguide_route('/')===null && hsh_catguide_route('/cat-carrier-checklist/')===null,'existing blog routes remain untouched');
check(hsh_catguide_route('/neko-guidebook/')===null,'similar unrelated URL remains untouched');
check(hsh_catguide_route('/neko-guide')['redirect']==='/neko-guide/','preview root has canonical trailing slash');
check(hsh_catguide_route('/neko-guide/')['file']==='index.html','preview home resolves');
check(hsh_catguide_route('/neko-guide/articles/first-days/?q=x')['status']===200,'article route accepts safe query');
foreach(array('/neko-guide/../editing-bridge.php','/neko-guide/%2e%2e/editing-bridge.php','/neko-guide/assets/%00','/neko-guide/manifest.json','/neko-guide/missing/','/neko-guide/assets/..%5Cediting-bridge.php') as $path){check(hsh_catguide_route($path)['status']===404,'unknown or unsafe preview file cannot be read');}
$root=dirname(__DIR__).'/hirogaru-home/cat-guide-preview/';
foreach(json_decode(file_get_contents($root.'manifest.json'),true) as $file){check(is_file($root.$file),'manifest target exists');}
check(hsh_catguide_route('/neko-guide/sitemap.xml')['status']===200,'public sitemap is routed');
$robots="User-agent: *\nDisallow: /wp-admin/\n";
$updated=hsh_catguide_robots($robots,true);
check(strpos($updated,'Disallow: /wp-admin/')!==false && substr_count($updated,'/neko-guide/sitemap.xml')===1,'sitemap added while retaining existing robots directives');
check(hsh_catguide_robots($updated,true)===$updated && hsh_catguide_robots($robots,false)===$robots,'robots keeps private setting and does not duplicate sitemap');
echo "All cat guide preview tests passed. Total tests: $tests\n";
