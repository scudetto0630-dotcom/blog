<?php
require __DIR__.'/plugin-update.php';
$d=hsh_topic_details();
check(array_keys($d)===array('cats','whisky','it-freelance'),'three existing category slugs preserved');
check(strpos($d['cats'][1],'まんもす')!==false && strpos($d['cats'][1],'5.5kg')!==false,'confirmed pet facts included');
check(strpos($d['whisky'][1],'シェリーカスク')!==false,'confirmed whisky preference included');
check(strpos($d['it-freelance'][1],'何から')!==false,'beginner experience included without invented achievements');
check(strpos(hsh_profile_markup(),'プロフィール画像はイラストです。')!==false,'brand illustration distinguished from real pet');
check(strpos(hsh_profile_markup(),'ひろがる趣味暮らし 管理人')!==false,'pseudonymous author displayed');
$search=hsh_search_markup('test-search');
check(strpos($search,'for="test-search"')!==false && strpos($search,'id="test-search"')!==false && strpos($search,'name="s"')!==false,'search has associated label and native WordPress query');
check(hsh_verification_value(array('token'=>str_repeat('a',40)))===str_repeat('a',40),'Google verification token accepted');
foreach(array(array('token'=>'<script>alert(1)</script>'),array('token'=>array()),array('token'=>'short'),array('token'=>str_repeat('a',40),'url'=>'https://example.test')) as $v){check(is_wp_error(hsh_verification_value($v)),'unsafe verification input rejected');}
$assets=hsh_article_assets();$total=0;
foreach($assets as $key=>$asset){$p=dirname(__DIR__).'/hirogaru-home/assets/articles/'.$asset['file'];check(substr($asset['file'],-5)==='.webp' && hash_file('sha256',$p)===$asset['sha256'],'optimized asset integrity');$total+=filesize($p);}
check($total<1000000,'six full-size editorial images below one megabyte total');
foreach(hsh_starter_articles() as $a){check(substr_count($a['content'],'<h2>')>=5 && strpos($a['content'],'よく')!==false && strpos($a['content'],'https://')!==false,'article keeps practical sections, FAQ and source navigation');}
ob_start();hsh_document_head();$head=ob_get_clean();check(substr_count($head,'name="description"')===1 && substr_count($head,'rel="canonical"')===1,'custom homepage emits description and canonical once');
$GLOBALS['test_wp_head']='<meta content="Existing description" name="description"><link href="https://example.test/" rel="canonical">';ob_start();hsh_document_head();$head=ob_get_clean();check(substr_count($head,'name="description"')===1 && substr_count($head,'rel="canonical"')===1,'existing SEO provider tags are preserved without duplicates');unset($GLOBALS['test_wp_head']);
echo "All site polish tests passed. Total tests: $tests\n";
