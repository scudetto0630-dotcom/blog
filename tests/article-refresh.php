<?php
require __DIR__.'/starter-posts.php';
function wp_json_encode($v){return json_encode($v);}
function get_post_thumbnail_id($id){global $metadata;return (int)($metadata[$id]['_thumbnail_id']??0);}
function update_post_meta($id,$key,$value){global $metadata;$metadata[$id][$key]=$value;return true;}
function wp_update_post($post,$error=false){return wp_insert_post($post,$error);}
foreach($result->data['articles'] as $item){$records[$item['id']]['post_status']='publish';}
$articles=hsh_refresh_plan()->data['articles'];
check(count($articles)===3,'review only three known posts');
$expected=array();foreach($articles as $article){$expected[$article['slug']]=$article['fingerprint'];}
check(hsh_refresh_articles(new Request(array('expected'=>array())))->code==='hsh_input','missing revision expectations rejected');
$wrong=$expected;$wrong['cat-carrier-checklist']=str_repeat('0',64);
check(hsh_refresh_articles(new Request(array('expected'=>$wrong)))->code==='hsh_conflict','stale read rejected before importing media');
check(hsh_article_media('../escape',33,3)->code==='hsh_asset','unsafe asset key rejected');
check(hsh_managed_article(new Request(array('slug'=>'another-post','expected'=>str_repeat('0',64),'content'=>'overwrite')))->code==='hsh_owned','unrelated article cannot be edited');
$id=$articles[0]['id'];$slug=$articles[0]['slug'];
check(hsh_managed_article(new Request(array('slug'=>$slug,'expected'=>str_repeat('0',64),'content'=>'stale')))->code==='hsh_conflict','stale managed edit rejected');
$records[$id]['post_excerpt']=$records[$id]['post_excerpt']??'';
$fingerprint=hsh_article_fingerprint(get_post($id));
$response=hsh_managed_article(new Request(array('slug'=>$slug,'expected'=>$fingerprint,'content'=>'<p>New editorial text</p>')));
check($response instanceof WP_REST_Response && get_post($id)->post_content==='<p>New editorial text</p>','owned article text can be revised');
check(count(get_post_meta($id,'_hsh_edit_history',true))===1,'previous editorial version backed up');
check(!get_option('hsh_starter_lock'),'revision lock released');
echo "Refresh tests complete. Total tests: $tests\n";

/* Simulated WordPress media API; this is not an integration test of GD/Imagick. */
function wp_upload_bits($name,$unused,$bytes){return array('file'=>'/tmp/'.$name,'url'=>'https://example.test/uploads/'.$name,'error'=>false);}
function wp_insert_attachment($post,$file,$parent,$error){$post['post_type']='attachment';$id=wp_insert_post($post,true);update_post_meta($id,'_file',$file);return $id;}
function get_attached_file($id){return get_post_meta($id,'_file',true);}
function wp_get_attachment_metadata($id){return get_post_meta($id,'_metadata',true);}
function wp_generate_attachment_metadata($id,$file){return array('width'=>1672,'height'=>941,'file'=>basename($file));}
function wp_update_attachment_metadata($id,$data){return update_post_meta($id,'_metadata',$data);}
function wp_get_attachment_url($id){return 'https://example.test/uploads/'.basename(get_attached_file($id));}
function wp_get_attachment_image($id,$size,$icon,$attributes){return '<img src="'.wp_get_attachment_url($id).'" alt="'.htmlspecialchars($attributes['alt']).'" />';}
function set_post_thumbnail($id,$image){return update_post_meta($id,'_thumbnail_id',$image);}
function clean_post_cache($id){}
check(hsh_starter_image(new Request(array('slug'=>'another-post','key'=>'cat-cover')))->code==='hsh_owned','asset import refuses unrelated post');
check(hsh_starter_image(new Request(array('slug'=>'cat-carrier-checklist','key'=>'csv-cover')))->code==='hsh_owned','asset import refuses another category image');
$before=count($records);$image=hsh_starter_image(new Request(array('slug'=>'cat-carrier-checklist','key'=>'cat-cover')));
check($image instanceof WP_REST_Response && count($records)===$before+1,'packaged image creates one attachment');
$again=hsh_starter_image(new Request(array('slug'=>'cat-carrier-checklist','key'=>'cat-cover')));
check($again->data['id']===$image->data['id'] && count($records)===$before+1,'retry reuses attachment');
$expected=array();foreach(hsh_refresh_plan()->data['articles'] as $article){$expected[$article['slug']]=$article['fingerprint'];}
$refreshed=hsh_refresh_articles(new Request(array('expected'=>$expected)));
check($refreshed instanceof WP_REST_Response,'all three editorial revisions apply');
foreach($refreshed->data['articles'] as $article){$p=get_post($article['id']);check(strpos($p->post_content,'<figure')!==false && strpos($p->post_content,'<!--HSH_IMAGE:')===false,'inline illustration rendered');check($article['featured_image_id']>0 && $article['revision']==='voice-20261027-v10','cover and revision saved');}
$before=count($records);$expected=array();foreach(hsh_refresh_plan()->data['articles'] as $article){$expected[$article['slug']]=$article['fingerprint'];}hsh_refresh_articles(new Request(array('expected'=>$expected)));
check(count($records)===$before,'revision retry creates no duplicate media or posts');
echo "All article revision tests passed. Total tests: $tests\n";
