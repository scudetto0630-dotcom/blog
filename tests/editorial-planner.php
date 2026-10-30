<?php
require __DIR__.'/blog-next.php';
require_once __DIR__.'/../hirogaru-home/editorial-planner.php';
function admin_url($path=''){return 'https://example.test/wp-admin/'.$path;}
function wp_attachment_is_image($id){return in_array($id,array(700,701),true);}
function wp_get_attachment_image_url($id,$size){return 'https://example.test/media/'.$id.'.jpg';}
function wp_unslash($v){return $v;}
function check_admin_referer($v){return true;}
$snapshot=hsh_planner_snapshot();
check(count($snapshot['articles'])===13,'planner lists the 13 owned articles');
check(count($snapshot['photos'])===45,'planner includes all 45 packaged photos');
$GLOBALS['test_extra_posts']=array((object)array('ID'=>1200,'post_type'=>'post','post_name'=>'new-owner-draft','post_title'=>'New owner draft','post_status'=>'draft','post_content'=>'<p>Draft</p>','post_category'=>array(3)));
$new_snapshot=hsh_planner_snapshot();check(isset($new_snapshot['articles']['post:1200'])&&$new_snapshot['articles']['post:1200']['stage']==='writing','new normal WordPress draft appears without changing the packaged catalog');
check($new_snapshot['articles']['post:1200']['url']==='','private draft does not get a public article link');
unset($GLOBALS['test_extra_posts']);

check(count($snapshot['photos']['asset:manmos-back']['used_in'])===1,'supplied gallery image is linked to the actual article');
check($snapshot['photos']['asset:manmos-back']['kind']==='provided','actual photos stay distinct from generated images');
check($snapshot['photos']['asset:detail-csv-original']['kind']==='generated','generated originals are not described as real photographs');
$aid='article:glenlivet-12-first-bottles';$before=get_page_by_path('glenlivet-12-first-bottles')->post_content;
$fields=array_intersect_key($snapshot['articles'][$aid],array_flip(array('title','topic','stage','affiliate','experience','product','link','notes','photo_notes','due')));
$fields['product']='Glenlivet 12';$fields['notes']='private planning note';$fields['due']='2026-11-30';
$save=array('action'=>'save','id'=>$aid,'expected'=>null,'fields'=>$fields);
$r=hsh_planner_save($save);check(!is_wp_error($r)&&$r['articles'][$aid]['notes']==='private planning note','article notes persist');
check(get_page_by_path('glenlivet-12-first-bottles')->post_content===$before,'planning does not publish or rewrite the article');
check(hsh_planner_save($save)->code==='hsh_planner_conflict','stale tab cannot overwrite saved notes');
$save['expected']=$r['articles'][$aid]['fingerprint'];$save['fields']['link']='javascript:alert(1)';check(hsh_planner_save($save)->code==='hsh_planner_url','script links are rejected');
$save['fields']['link']='https://example.test/offer';$save['fields']['due']='2026-02-30';check(hsh_planner_save($save)->code==='hsh_planner_date','invalid calendar date rejected');
$save['fields']['due']='';$save['fields']['stage']=array('published');check(hsh_planner_save($save)->code==='hsh_planner_length','array input cannot become an editorial string');
check(hsh_planner_save(array('id'=>'article:unrelated','action'=>'save','expected'=>null,'fields'=>array()))->code==='hsh_planner_scope','unrelated public posts cannot be managed');
check(hsh_planner_save(array('id'=>$aid,'action'=>'delete','expected'=>null))->code==='hsh_planner_scope','public articles cannot be deleted by the planner');
$pid='plan:12345678-1234-1234-1234-123456789abc';$fields['title']='Future article';$fields['stage']='idea';$fields['notes']='future idea note';
$r=hsh_planner_save(array('id'=>$pid,'action'=>'save','expected'=>null,'fields'=>$fields));check(!is_wp_error($r)&&isset($r['articles'][$pid]),'future article ideas can be saved without creating a post');
$photo=array('title'=>'Owner photo','kind'=>'provided','subject'=>'manmos','purpose'=>'reference','rights'=>'owner','notes'=>'Not for publication','articles'=>array($pid),'sources'=>array('asset:manmos-back'));
$r=hsh_planner_save(array('id'=>'media:700','action'=>'save','expected'=>null,'fields'=>$photo));check(!is_wp_error($r)&&$r['photos']['media:700']['sources']===array('asset:manmos-back'),'uploaded image records can reference an original and planned article');
check(hsh_planner_save(array('id'=>$pid,'action'=>'delete','expected'=>$r['articles'][$pid]['fingerprint']))->code==='hsh_planner_used','referenced plans cannot leave dangling photo associations');
$photo['rights']='unknown';$photo['purpose']='publish';check(hsh_planner_save(array('id'=>'media:701','action'=>'save','expected'=>null,'fields'=>$photo))->code==='hsh_planner_rights','unconfirmed rights cannot be marked publishable');
$photo['rights']='owner';$photo['purpose']='reference';check(hsh_planner_save(array('id'=>'media:999','action'=>'save','expected'=>null,'fields'=>$photo))->code==='hsh_planner_photo','non-image media ID rejected');
$photo['sources']=array('media:700');$r=hsh_planner_save(array('id'=>'media:701','action'=>'save','expected'=>null,'fields'=>$photo));check(!is_wp_error($r),'generated reference relationships can be recorded');
$photo['sources']=array('media:701');check(hsh_planner_save(array('id'=>'media:700','action'=>'save','expected'=>$r['photos']['media:700']['fingerprint'],'fields'=>$photo))->code==='hsh_planner_cycle','circular source lineage rejected');
$photo['sources']=array('media:701');check(hsh_planner_save(array('id'=>'media:701','action'=>'save','expected'=>$r['photos']['media:701']['fingerprint'],'fields'=>$photo))->code==='hsh_planner_relation','self-reference rejected');
$photo['sources']=array();$photo['articles']=array();$r=hsh_planner_save(array('id'=>'media:701','action'=>'save','expected'=>$r['photos']['media:701']['fingerprint'],'fields'=>$photo));
$r=hsh_planner_save(array('id'=>'media:701','action'=>'delete','expected'=>$r['photos']['media:701']['fingerprint']));check(!isset($r['photos']['media:701'])&&wp_attachment_is_image(701),'removing a photo record leaves media image intact');
$photo['kind']='generated';$photo['rights']='generated';check(hsh_planner_save(array('id'=>'asset:manmos-back','action'=>'save','expected'=>null,'fields'=>$photo))->code==='hsh_planner_origin','supplied image provenance cannot be overwritten');
$photo['kind']='provided';$photo['rights']='generated';check(hsh_planner_save(array('id'=>'media:701','action'=>'save','expected'=>null,'fields'=>$photo))->code==='hsh_planner_rights','image type and permission must agree');
add_option('hsh_planner_lock',time());check(hsh_planner_save(array())->code==='hsh_planner_busy','concurrent save does not race');delete_option('hsh_planner_lock');
check(!get_option('hsh_planner_lock'),'lock released after validation failures');
add_option('hsh_planner_lock',time()-121);check(hsh_planner_save(array())->code==='hsh_planner_input'&&!get_option('hsh_planner_lock'),'abandoned old lock recovers after timeout');

$good=hsh_planner_snapshot();$retry=array('action'=>'save','id'=>$aid,'expected'=>$good['articles'][$aid]['fingerprint'],'fields'=>array_intersect_key($good['articles'][$aid],array_flip(array('title','topic','stage','affiliate','experience','product','link','notes','photo_notes','due'))));
$retry['fields']['notes']='different private note';$unchanged=hsh_planner_store();$GLOBALS['test_planner_save_failure']=true;
check(hsh_planner_save($retry)->code==='hsh_planner_save','storage failure is reported instead of success');
check(hsh_planner_store()===$unchanged&&!get_option('hsh_planner_lock'),'failed save preserves prior records and releases lock');
unset($GLOBALS['test_planner_save_failure']);


// Histories, linking and imports must be atomic and stay within private metadata.
$s=hsh_planner_snapshot();$first=$s['articles'][$aid]['notes'];$fields=array_intersect_key($s['articles'][$aid],array_flip(hsh_planner_article_fields()));$fields['notes']='new revision';
$r=hsh_planner_save(array('action'=>'save','id'=>$aid,'expected'=>$s['articles'][$aid]['fingerprint'],'fields'=>$fields));
check(count($r['articles'][$aid]['history'])===1,'editing a saved record retains its prior revision');
$r=hsh_planner_save(array('action'=>'restore','id'=>$aid,'expected'=>$r['articles'][$aid]['fingerprint'],'revision'=>0));
check(!is_wp_error($r)&&$r['articles'][$aid]['notes']===$first&&count($r['articles'][$aid]['history'])===2,'restoring private notes is itself recorded in history');
check(get_page_by_path('glenlivet-12-first-bottles')->post_content===$before,'history restoration cannot change published prose');
$s=hsh_planner_snapshot();$backup=$s['backup'];$backup['records'][$aid]['notes']='imported note';
$bad=$backup;$bad['site']='https://foreign.test/';$original=hsh_planner_store();
check(hsh_planner_save(array('action'=>'import','backup'=>$bad,'expected_store'=>$s['store_fingerprint']))->code==='hsh_planner_import','foreign-site backup rejected');
$bad=$backup;$bad['records'][$aid]['title']=array('bad');
check(is_wp_error(hsh_planner_save(array('action'=>'import','backup'=>$bad,'expected_store'=>$s['store_fingerprint'])))&&hsh_planner_store()===$original,'bad scalar types fail without partial writes');
$bad=$backup;$bad['records']['media:700']['sources']=array('media:700');
check(is_wp_error(hsh_planner_save(array('action'=>'import','backup'=>$bad,'expected_store'=>$s['store_fingerprint'])))&&hsh_planner_store()===$original,'invalid imported lineage leaves every record untouched');
$GLOBALS['test_planner_save_failure']=true;
check(hsh_planner_save(array('action'=>'import','backup'=>$backup,'expected_store'=>$s['store_fingerprint']))->code==='hsh_planner_save'&&hsh_planner_store()===$original,'backup storage failure preserves prior store');unset($GLOBALS['test_planner_save_failure']);
$r=hsh_planner_save(array('action'=>'import','backup'=>$backup,'expected_store'=>$s['store_fingerprint']));
check(!is_wp_error($r)&&$r['articles'][$aid]['notes']==='imported note','validated same-site backup restores private notes');
check(hsh_planner_save(array('action'=>'import','backup'=>$backup,'expected_store'=>$s['store_fingerprint']))->code==='hsh_planner_conflict','backup import rejects concurrent changes');
$r=hsh_planner_save(array('action'=>'link','id'=>$pid,'expected'=>$r['articles'][$pid]['fingerprint'],'target'=>$aid,'expected_target'=>$r['articles'][$aid]['fingerprint']));
check(!is_wp_error($r)&&!isset($r['articles'][$pid])&&$r['photos']['media:700']['articles']===array($aid),'linking migrates photo associations and removes only the private idea');
check(strpos($r['articles'][$aid]['notes'],'future idea note')!==false&&get_page_by_path('glenlivet-12-first-bottles')->post_content===$before,'linking combines notes but leaves public content untouched');
// Rebuild the original UI fixture so existing browser checks remain stable.
update_option('hsh_editorial_planner',$original,false);
check(!empty(hsh_planner_snapshot()['photos']['asset:manmos-back']['placements']),'packaged photos disclose actual usage positions');
$GLOBALS['test_admin_cap']=false;ob_start();hsh_planner_admin();check(ob_get_clean()==='','non-admin cannot render private planning data');
$GLOBALS['test_admin_cap']=true;$_SERVER['REQUEST_METHOD']='GET';ob_start();hsh_planner_admin();$markup=ob_get_clean();
check(strpos($markup,'private planning note')!==false&&strpos($markup,'hsh-plan-media')!==false,'admin UI includes saved notes and photo selection');
check(strpos($markup,'reference')!==false,'reference-only purpose is available');
echo "All editorial planner checks passed. Total checks including shared suites: $tests\n";
echo "<!--HSH_PLANNER_FIXTURE_START-->".$markup."<!--HSH_PLANNER_FIXTURE_END-->";
