<?php
require __DIR__.'/seo-focus.php';
$head=hsh_adsense_head_markup();
check(substr_count($head,'<script ')===1&&substr_count($head,'adsbygoogle.js')===1,'one asynchronous Google script is rendered');
check(strpos($head,'client=ca-pub-1945643191231022')!==false,'owner supplied publisher is preserved exactly');
check(strpos($head,' async ')!==false&&strpos($head,'crossorigin="anonymous"')!==false,'Google asynchronous and crossorigin attributes remain intact');
check(hsh_adsense_ads_txt()==="google.com, pub-1945643191231022, DIRECT, f08c47fec0942fa0\n",'ads.txt uses the same publisher without the ca prefix');
check(strpos($head,'adsbygoogle.push')===false,'connection does not force a new ad placement');
check(strpos(hsh_adsense_privacy_notice(),'https://www.google.com/settings/ads')!==false&&strpos(hsh_adsense_privacy_notice(),'https://policies.google.com/technologies/partner-sites?hl=ja')!==false,'privacy notice supplies Google controls and explanation');
echo "All AdSense connection checks passed. Total with shared checks: $tests\n";
