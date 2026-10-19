<?php
define('ABSPATH',__DIR__);
function add_action(...$args){}
require __DIR__.'/../hirogaru-home/freelance-site.php';
$cases=array('/freelance'=>'redirect','/freelance/'=>'page','/freelance/index.html'=>'page','/freelance/?x=1'=>'page','/'=>null,'/neko-guide/'=>null,'/wp-admin/'=>null,'/freelance/../wp-config.php'=>null,'/freelance/%2e%2e/wp-config.php'=>null,'/freelancer/'=>null);
foreach($cases as $uri=>$expected){if(hsh_freelance_route($uri)!==$expected)throw new Exception('Bad route: '.$uri);}
echo 'PASS: 10 exact route and isolation checks'.PHP_EOL;
