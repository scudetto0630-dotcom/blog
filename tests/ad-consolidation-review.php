<?php
require __DIR__.'/full-content-review.php';
require_once __DIR__.'/../hirogaru-home/article-retirement.php';
$plan=hsh_consolidation_plan('canagan-chicken-salmon');$slugs=array_merge(array($plan['target']),array_keys($plan['sources']));$expected=array();$originals=array();foreach($slugs as $slug){$p=get_page_by_path($slug,OBJECT,'post');$expected[$slug]=hsh_article_fingerprint($p);$originals[$slug]=$p;}
check(count(hsh_consolidations())===9,'new food group inactive until explicit verified activation');
$input=array('target'=>$plan['target'],'expected'=>$expected);$bad=$input;$bad['expected']['mognyan-cat-food-guide']=str_repeat('0',64);check(hsh_apply_consolidation(new Request($bad))->code==='hsh_conflict'&&count(hsh_consolidations())===9,'food conflict leaves previous supplement redirects intact');
$bad=$input;$bad['expected']['withpety-nekoaigan']=$expected[$plan['target']];check(hsh_apply_consolidation(new Request($bad))->code==='hsh_input','food group cannot absorb arbitrary supplement source');
check(hsh_apply_consolidation(new Request($input)) instanceof WP_REST_Response&&count(hsh_consolidations())===14,'both reviewed groups can be active together');
check(count(hsh_catalog())===41,'five food duplicates removed from catalog');
foreach($plan['sources'] as $slug=>$anchor){check(hsh_consolidation_destination('/'.$slug.'/')===home_url('/'.$plan['target'].'/').'#'.$anchor,'food redirects to correct food section '.$slug);check(hsh_consolidation_targets()[$slug]===$plan['target'],'saved food bookmark maps to food comparison '.$slug);}
check(hsh_consolidation_destination('/withpety-nekoaigan/')===home_url('/withpety-nekokenshin/').'#supplement-nekoaigan','second activation does not retarget existing supplement URLs');
foreach($originals as $slug=>$p)check(hsh_article_fingerprint(get_post($p->ID))===$expected[$slug],'food originals remain unchanged '.$slug);
$rp=hsh_retirement_plan();$re=array();$old=array();foreach($rp as $slug=>$reason){$p=get_page_by_path($slug,OBJECT,'post');$re[$slug]=hsh_article_fingerprint($p);$old[$slug]=$p;}
check(hsh_retired_articles()===array(),'deployment alone does not withdraw articles');
$bad=$re;$bad['linx-as-store']=str_repeat('0',64);check(hsh_apply_retirement(new Request(array('expected'=>$bad)))->code==='hsh_conflict'&&hsh_retired_articles()===array(),'withdrawal verifies entire fixed group before changing status');
foreach($old as $slug=>$p)check(get_post($p->ID)->post_status==='publish','failed retirement keeps published status '.$slug);
check(hsh_apply_retirement(new Request(array('expected'=>$re,'content'=>'injected')))->code==='hsh_input','retirement cannot receive arbitrary bodies');
add_option('hsh_bundle_lock',time());check(hsh_apply_retirement(new Request(array('expected'=>$re)))->code==='hsh_busy','retirement shares edit lock');delete_option('hsh_bundle_lock');
check(hsh_apply_retirement(new Request(array('expected'=>$re))) instanceof WP_REST_Response&&count(hsh_retired_articles())===4,'four fixed articles withdrawn together');
check(count(hsh_catalog())===37,'catalog keeps 37 main articles plus separately managed introduction');
foreach($old as $slug=>$p){$after=get_post($p->ID);check($after->post_status==='draft'&&hsh_article_fingerprint($after)===$re[$slug],'full body, A8 links and featured image preserved privately '.$slug);foreach(array('post_type','post_author','post_date','post_date_gmt') as $field)check($after->$field===$p->$field,'original identity preserved '.$slug.' '.$field);check(hsh_retired_path('/'.$slug.'/')===$slug&&hsh_retired_path('/'.$slug)===$slug,'withdrawal covers exact original path '.$slug);check(hsh_consolidation_destination('/'.$slug.'/')==='','withdrawn article has no unrelated redirect '.$slug);check(hsh_release_article(new Request(array('slug'=>$slug,'expected'=>$re[$slug])))->code==='hsh_conflict','normal publisher cannot silently republish retired draft '.$slug);}
foreach(array('/unrelated/','/linx-as-store/more/','//evil.test/linx-as-store/') as $path)check(hsh_retired_path($path)==='','410 applies only to scoped original path '.$path);
foreach(hsh_release_status()->data['articles'] as $row)if(isset($rp[$row['slug']]))check(!isset($row['content'])&&$row['status']==='draft','private archived bodies withheld from status '.$row['slug']);
$p=$old['linx-as-store'];$records[$p->ID]['post_status']='publish';check(!isset(hsh_retired_articles()['linx-as-store']),'explicit administrator republication clears active 410');$records[$p->ID]['post_status']='draft';
foreach(array_keys($rp) as $slug)check(!in_array($slug,array_column(hsh_reader_catalog(),'id'),true),'retired articles absent from reader fallback '.$slug);
echo "Advertising consolidation and retirement passed. Total: $tests\n";
