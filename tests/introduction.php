<?php
require __DIR__.'/site-polish.php';
$GLOBALS['test_publishers']=array(1,3);
$records[8]=array('ID'=>8,'post_type'=>'post','post_status'=>'publish','post_author'=>1,'post_name'=>rawurlencode('ひろがる趣味暮らしについて'),'post_title'=>'Old introduction','post_content'=>'Old text about camping and golf','post_excerpt'=>'Old excerpt');
$plan=hsh_intro_review();check($plan instanceof WP_REST_Response && $plan->data['id']===8,'single existing introduction can be reviewed');
check(hsh_intro_refresh(new Request(array('expected'=>str_repeat('0',64))))->code==='hsh_conflict','stale introduction fingerprint rejected');
check($records[8]['post_content']==='Old text about camping and golf','rejected update preserves existing introduction');
check(hsh_intro_refresh(new Request(array('expected'=>$plan->data['fingerprint'],'content'=>'arbitrary')))->code==='hsh_input','arbitrary introduction content rejected');
$updated=hsh_intro_refresh(new Request(array('expected'=>$plan->data['fingerprint'])));
check($updated instanceof WP_REST_Response && strpos($records[8]['post_content'],'まんもす')!==false && strpos($records[8]['post_content'],'キャンプ')===false,'packaged introduction replaces outdated categories');
check(get_post_meta(8,'_hsh_edit_history',true)[0]['content']==='Old text about camping and golf','original introduction retained in history');
check($records[8]['post_name']===rawurlencode('ひろがる趣味暮らしについて'),'introduction URL preserved');
$records[8]['post_name']='another-post';check(hsh_intro_review()->code==='hsh_intro_scope','wrong slug rejected');
$records[8]['post_name']=rawurlencode('ひろがる趣味暮らしについて');$records[8]['post_author']=3;check(hsh_intro_review()->code==='hsh_intro_scope','changed author rejected');
check(!get_option('hsh_starter_lock'),'introduction edit lock released');
echo "All introduction tests passed. Total tests: $tests\n";
