<?php
require __DIR__.'/adsense-wait.php';
require_once __DIR__.'/../hirogaru-home/article-consolidation.php';
$plan=hsh_consolidation_plan();$before=count(hsh_catalog());
check($before===55&&hsh_consolidations()===array(),'no article is merged by deployment alone');
check(hsh_apply_consolidation(new Request(array('target'=>'unrelated','expected'=>array())))->code==='hsh_input','only the reviewed fixed group may be merged');
$slugs=array_merge(array($plan['target']),array_keys($plan['sources']));$originals=array();$expected=array();
foreach($slugs as $slug){$p=get_page_by_path($slug,OBJECT,'post');$originals[$slug]=$p;$expected[$slug]=hsh_article_fingerprint($p);}
$input=array('target'=>$plan['target'],'expected'=>$expected);
$bad=$input;$bad['expected'][$slugs[1]]=str_repeat('0',64);check(hsh_apply_consolidation(new Request($bad))->code==='hsh_conflict'&&!get_option('hsh_supplement_consolidation'),'a stale source fingerprint leaves the entire group unchanged');
$bad=$input;unset($bad['expected'][$slugs[1]]);check(hsh_apply_consolidation(new Request($bad))->code==='hsh_input','all ten fingerprints are required');
$id=$originals[$plan['target']]->ID;$body=$records[$id]['post_content'];$records[$id]['post_content']=str_replace('id="supplement-nekoaigan"','id="missing-eye"',$body);$bad=$input;$bad['expected'][$plan['target']]=hsh_article_fingerprint(get_post($id));
check(hsh_apply_consolidation(new Request($bad))->code==='hsh_target'&&!get_option('hsh_supplement_consolidation'),'missing target section cannot activate any redirect');$records[$id]['post_content']=$body;
$source=$originals[$slugs[1]];$meta=$metadata[$source->ID]['_hsh_bundle_owned'];$metadata[$source->ID]['_hsh_bundle_owned']='unrelated';check(hsh_apply_consolidation(new Request($input))->code==='hsh_conflict','unowned source is never absorbed');$metadata[$source->ID]['_hsh_bundle_owned']=$meta;
add_option('hsh_bundle_lock',time());check(hsh_apply_consolidation(new Request($input))->code==='hsh_busy','consolidation shares the publisher lock');delete_option('hsh_bundle_lock');
$result=hsh_apply_consolidation(new Request($input));check($result instanceof WP_REST_Response&&count(hsh_consolidations())===9,'the complete comparison activates nine permanent merges');
check(count(hsh_catalog())===46,'public article catalog has one comparison instead of ten near duplicates');
foreach($originals as $slug=>$p){check(hsh_article_fingerprint(get_post($p->ID))===$expected[$slug]&&get_post($p->ID)->post_status==='publish','native original and history remain recoverable '.$slug);}
foreach($plan['sources'] as $slug=>$anchor){
 check(hsh_consolidation_destination(home_url('/'.$slug.'/'))===home_url('/'.$plan['target'].'/').'#'.$anchor,'old URL resolves directly to its product section '.$slug);
 check(hsh_release_article(new Request(array('slug'=>$slug,'expected'=>$expected[$slug])))->code==='hsh_merged','publisher cannot accidentally revive the old article '.$slug);
}
check(hsh_consolidation_destination('https://evil.test/withpety-nekoaigan/')==='','external URL is not rewritten');

foreach(array('//evil.test/withpety-nekoaigan/','javascript:alert(1)','/withpety-nekoaigan/?next=evil','/unrelated/') as $url)check(hsh_consolidation_destination($url)==='','unrelated or unsafe URL is unchanged '.$url);
$markup='<a href="/withpety-nekoaigan/">eye</a><img src="/withpety-nekoaigan/"><a href="https://px.a8.net/x">ad</a>';
$rewritten=hsh_consolidation_links($markup);check(strpos($rewritten,'#supplement-nekoaigan')!==false&&strpos($rewritten,'<img src="/withpety-nekoaigan/">')!==false&&strpos($rewritten,'https://px.a8.net/x')!==false,'only internal links move; photos and advertising destinations stay intact');
$args=hsh_consolidation_query_args(array('post__not_in'=>array(123456)));check(count($args['post__not_in'])===10&&in_array(123456,$args['post__not_in'],true),'sitemap and public query exclusions preserve pre-existing exclusions');
$reader_ids=array_column(hsh_reader_catalog(),'id');foreach(array_keys($plan['sources']) as $slug)check(!in_array($slug,$reader_ids,true),'reader fallback never re-adds merged original '.$slug);
$records[$id]['post_status']='draft';check(hsh_consolidations()===array()&&hsh_consolidation_destination('/withpety-nekoaigan/')==='','unavailable target fails closed without broken redirects');$records[$id]['post_status']='publish';
echo "All permanent consolidation checks passed. Total: $tests\n";
