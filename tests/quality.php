<?php
require __DIR__.'/seo-focus.php';
check(hsh_quality_order('updated')==='updated','updated order is accepted');
check(hsh_quality_order(array('route'))==='newest','array sort input uses safe default');
check(hsh_quality_order('unknown')==='newest','unknown sort input uses safe default');
check(hsh_quality_aliases(array('もぐにゃん'=>'モグニャン'))===array('もぐにゃん'=>'モグニャン'),'search alias accepts exact text mapping');
check(hsh_quality_aliases(array(''=> 'empty','cat'=>array('bad')) )===array(),'invalid alias shapes are discarded');
check(hsh_quality_aliases(array_fill_keys(range(0,50),'x'))===array(),'alias map is bounded to 50');
check(hsh_quality_aliases(array('<b>ねこ</b>'=>'<i>猫</i>'))===array('ねこ'=>'猫'),'alias markup is stripped');
update_option('hsh_search_aliases',array('お猫'=>'猫'));
check(hsh_search_alias('お猫')==='猫','custom search aliases integrate with existing search');
check(hsh_search_alias('アルボラリス')==='グレングラント','built in alias remains');
echo "All quality checks passed. Total with shared checks: $tests\n";
