<?php
require __DIR__.'/blog-brushup.php';
require_once __DIR__.'/../hirogaru-home/blog-next.php';
if(!defined('DAY_IN_SECONDS'))define('DAY_IN_SECONDS',86400);
if(!defined('HOUR_IN_SECONDS'))define('HOUR_IN_SECONDS',3600);
function delete_post_thumbnail($id){return update_post_meta($id,'_thumbnail_id',0);}
function wp_salt($scheme){return 'isolated-fixture';}
function get_transient($key){return $GLOBALS['test_transients'][$key]??false;}
function set_transient($key,$value,$ttl){$GLOBALS['test_transients'][$key]=$value;return true;}
function wp_generate_uuid4(){static $i=0;return 'fixture-'.++$i;}
function wp_verify_nonce($value,$action){return $value==='valid-fixture'&&$action==='hsh_blog_report';}
check(hsh_search_alias('ねこ ネコ チャッピー ＣＳＶ')==='猫 猫 ChatGPT CSV','known search aliases normalize without external services');
check(hsh_search_alias('ねこじゃらし 猫じゃらし ネコじゃらし')==='ねこじゃらし ねこじゃらし ねこじゃらし','search preserves complete toy names while normalizing spelling');
check(hsh_search_topic('cats')==='cats'&&hsh_search_topic('wp-admin')===''&&hsh_search_topic(array('cats'))==='','search category accepts only scalar approved slugs');
check(hsh_release_article(new Request(array('slug'=>'bad','expected'=>null)))->code==='hsh_scope','publisher rejects unknown article');
check(hsh_release_article(new Request(array('slug'=>'before-welcoming-cat','expected'=>null,'content'=>'injected')))->code==='hsh_input','publisher rejects arbitrary body data');
$items=hsh_expanded_articles();check(count($items)===10&&count(array_unique(array_column($items,'slug')))===10,'ten distinct packaged articles');
foreach($items as $a)check(strpos($a['content'],'4匹')===false&&stripos($a['content'],'bpaas')===false,'corrected cat chronology and preferred work wording: '.$a['slug']);
$GLOBALS['test_publishers']=array(3);$options['hsh_page_id']=900;$records[900]['post_author']=3;
$before=count($records);$missing_category='cats';$r=hsh_release_article(new Request(array('slug'=>$items[0]['slug'],'expected'=>null)));check($r->code==='hsh_category'&&count($records)===$before,'missing category produces no partial article');unset($missing_category);
foreach($items as $a){$r=hsh_release_article(new Request(array('slug'=>$a['slug'],'expected'=>null)));check($r instanceof WP_REST_Response&&get_post($r->data['id'])->post_status==='publish','packaged publication: '.$a['slug']);}
$before=count($records);$r=hsh_release_article(new Request(array('slug'=>$items[0]['slug'],'expected'=>null)));check($r->code==='hsh_conflict'&&count($records)===$before,'retry does not duplicate or overwrite an existing article');
$p=get_page_by_path($items[0]['slug']);$id=$p->ID;$original=$p->post_content;
hsh_remember_blog_post($p);wp_update_post(array('ID'=>$id,'post_content'=>'changed','post_title'=>'Changed title','post_excerpt'=>'changed'));
check(hsh_restore_blog_snapshot($id,str_repeat('0',64))->code==='hsh_conflict'&&get_post($id)->post_content==='changed','stale restore leaves current post untouched');
$r=hsh_restore_blog_snapshot($id,hsh_article_fingerprint(get_post($id)));check($r===true&&get_post($id)->post_content===$original&&get_post($id)->post_title===$items[0]['title'],'saved title and actual article body restore correctly');
check(get_post_meta($id,'the_page_meta_description',true)===$items[0]['excerpt'],'restore also synchronizes SEO description');
check(count(get_post_meta($id,'_hsh_restore_history',true))===1,'restore keeps a copy of replaced content');
$reports_before=get_option('hsh_blog_reports',array());
check(hsh_receive_report(new Request(array('slug'=>$items[0]['slug'],'message'=>'fixture report')))->code==='hsh_nonce'&&get_option('hsh_blog_reports',array())===$reports_before,'nonce failure writes no report');
check(hsh_report_fields(array('slug'=>'unrelated','message'=>'a useful report'))->code==='hsh_report','report refuses unrelated site pages');
check(hsh_report_fields(array('slug'=>$items[0]['slug'],'message'=>'a useful report','website'=>'bot'))->code==='hsh_report','honeypot blocks spam');
check(hsh_report_fields(array('slug'=>$items[0]['slug'],'message'=>'<b></b>'))->code==='hsh_report','markup-only reports are rejected after sanitization');
check(hsh_report_fields(array('slug'=>$items[0]['slug'],'message'=>str_repeat('a',3001)))->code==='hsh_report','oversized reports are rejected');
$_SERVER['REMOTE_ADDR']='192.0.2.4';
for($i=0;$i<3;$i++){$r=hsh_receive_report(new Request(array('nonce'=>'valid-fixture','slug'=>$items[0]['slug'],'message'=>'fixture correction '.$i)));check(is_array($r)&&isset($r['message']),'report accepted below limit');}
check(hsh_receive_report(new Request(array('nonce'=>'valid-fixture','slug'=>$items[0]['slug'],'message'=>'fixture correction 4')))->code==='hsh_limit','fourth report is rate limited');
$stored=get_option('hsh_blog_reports');check(count($stored)===3&&strpos(json_encode($stored),'192.0.2.4')===false,'reports are private option records without stored IP');
check(count(hsh_prune_blog_reports(array(array('time'=>time()),array('time'=>time()-91*DAY_IN_SECONDS)),time()))===1,'expired reports leave retained set');
check(!get_option('hsh_blog_reports_lock'),'report lock released after rate limit');
ob_start();hsh_blog_dashboard();check(ob_get_clean()==='','unauthorized users cannot view reports or edit history');
function wp_nonce_field($action){echo '<input name="_wpnonce" value="test">';}
function selected($a,$b,$echo=true){return $a===$b?'selected':'';}
function get_edit_post_link($id){return 'https://example.test/wp-admin/post.php?post='.$id;}
function has_category($topic,$post){$ids=array('cats'=>8,'whisky'=>3,'it-freelance'=>9);return in_array($ids[$topic],$post->post_category??array(),true);}
function has_post_thumbnail($post){return get_post_thumbnail_id(is_object($post)?$post->ID:$post)>0;}
$GLOBALS['test_admin_cap']=true;$_SERVER['REQUEST_METHOD']='GET';
ob_start();hsh_blog_dashboard();$dashboard=ob_get_clean();
check(strpos($dashboard,'関連記事を保存')!==false&&strpos($dashboard,'fixture correction')!==false,'authorized dashboard renders curation and private reports');
check(strpos($dashboard,'1つ前の保存内容へ戻す')!==false||strpos($dashboard,'保存履歴')!==false,'dashboard exposes snapshot history and restore controls');
$GLOBALS['test_admin_cap']=false;
$photos=hsh_photo_manifest();
check(count($photos['assets'])===12&&count($photos['articles'])===13,'twelve photo assets cover thirteen owned articles');
foreach($photos['assets'] as $asset){
    $file=__DIR__.'/../hirogaru-home/assets/photos/'.$asset['file'];
    check(is_file($file)&&hash_file('sha256',$file)===$asset['sha256'],'photo integrity '.$asset['file']);
    check($asset['kind']==='provided'?strpos($asset['caption'],'提供写真')!==false:strpos($asset['caption'],'AI生成')!==false,'honest photo caption '.$asset['file']);
}
check(hsh_photo_for_slug('unrelated')===null,'photo mapping does not affect unrelated articles');
check(strpos(hsh_photo_image(array('file'=>'../escape.webp')), '<img')===false,'photo helper rejects non-packaged paths');
$cover=hsh_photo_cover((object)array('post_name'=>'manmos-cat-life'),true);
check(strpos($cover,'fetchpriority="high"')!==false&&strpos($cover,'提供写真')!==false,'real owner photo gets eager cover and accurate caption');
$cover=hsh_photo_cover((object)array('post_name'=>'cat-teaser-small-parts'));
check(strpos($cover,'AI生成')!==false&&strpos($cover,'loading="lazy"')!==false,'generated photo stays explicitly identified');
echo "All blog next tests passed. Total checks including shared suites: $tests\n";