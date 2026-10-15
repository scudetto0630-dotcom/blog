<?php
require __DIR__.'/editing-bridge.php';
function user_can($id,$cap){return $id===3 && $cap==='publish_posts';}
function get_term_by($field,$slug,$taxonomy){global $missing_category;return isset($missing_category) && $missing_category===$slug ? false : (object)array('term_id'=>array('cats'=>8,'whisky'=>3,'it-freelance'=>9)[$slug]);}
require_once dirname(__DIR__).'/hirogaru-home/starter-posts.php';
$options['hsh_page_id']=900;$records[900]=array('ID'=>900,'post_type'=>'page','post_status'=>'publish','post_author'=>3);
check(hsh_starter_publish(new Request(array('content'=>'arbitrary')))->code==='hsh_input','arbitrary article data rejected');
$missing_category='whisky';$before=count($records);
check(hsh_starter_publish(new Request(array()))->code==='hsh_category' && count($records)===$before,'missing category causes no partial publication');unset($missing_category);
$records[901]=array('ID'=>901,'post_type'=>'post','post_name'=>'cat-carrier-checklist','post_status'=>'publish');
check(hsh_starter_publish(new Request(array()))->code==='hsh_conflict' && count($records)===$before+1,'existing unrelated post protected');unset($records[901]);
add_option('hsh_starter_lock',time());check(hsh_starter_publish(new Request(array()))->code==='hsh_busy','concurrent publication rejected');delete_option('hsh_starter_lock');
$result=hsh_starter_publish(new Request(array()));check($result instanceof WP_REST_Response,'packaged articles published');
check(count($result->data['articles'])===3,'exactly three articles');
foreach($result->data['articles'] as $article){$p=get_post($article['id']);check($p->post_status==='publish' && $p->post_type==='post','real post type published');check($p->post_author===3,'homepage author retained');check(count($p->post_category)===1,'one target category');}
$before=count($records);hsh_starter_publish(new Request(array()));check(count($records)===$before,'retry does not duplicate');
$id=$result->data['articles'][0]['id'];$records[$id]['post_content']='human edit';hsh_starter_publish(new Request(array()));check($records[$id]['post_content']==='human edit','subsequent human editing preserved');
$records[$id]['post_status']='draft';check(hsh_starter_publish(new Request(array()))->code==='hsh_changed','human unpublishing is respected');
check(!get_option('hsh_starter_lock'),'lock released after failure');
echo "Total tests: $tests\n";
