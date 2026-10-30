<?php
require __DIR__.'/editorial-planner.php';
$data=array('description'=>'Natural description','og:image'=>'https://example.test/photo.webp','og:image:width'=>'1280','og:image:height'=>'853','twitter:card'=>'summary_large_image');
$html='<html><head><meta property="og:image" content="theme.jpg"><meta property="og:image" content="duplicate.jpg"><meta name="robots" content="noindex,nofollow"><link rel="canonical" href="https://example.test/a/"><script type="application/ld+json">{"@type":"BlogPosting","image":"theme.jpg","author":{"@type":"Person","name":"Owner"}}</script></head><body>Original body</body></html>';
$new=hsh_growth_metadata($html,$data);
check(substr_count($new,'property="og:image"')===1&&strpos($new,'theme.jpg')===false,'OGP override removes conflicting theme defaults and updates article schema image');
check(strpos($new,'noindex,nofollow')!==false&&strpos($new,'rel="canonical" href="https://example.test/a/"')!==false,'metadata update preserves robots and canonical');
check(strpos($new,'Original body')!==false&&strpos($new,'"name":"Owner"')!==false,'metadata update preserves article body and schema author');
check(count(hsh_growth_tastes())===4&&strpos(hsh_growth_whisky(),'オレンジジュース')!==false,'taste index uses the four confirmed bottle impressions');
$gallery=hsh_growth_gallery();check(substr_count($gallery,'<img ')===8&&strpos($gallery,'AI生成')===false,'public gallery uses only eight available owner photographs');
check(count(hsh_growth_summaries())===13,'all thirteen articles have distinct related-reading summaries');
check(hsh_query_csv("Query,Clicks,Impressions,Position\nシェリー樽,3,100,8\n")[0]['query']==='シェリー樽','query CSV accepts owner-provided search data');
check(is_wp_error(hsh_query_csv("Page,Clicks,Impressions,Position\nhttps://example.test/,3,100,8\n")),'page metrics cannot be misread as query metrics');
check(is_wp_error(hsh_query_csv("Query,Clicks,Impressions,Position\nbad,100,2,8\n")),'impossible click counts are rejected');
check(is_wp_error(hsh_query_csv("Query,Clicks,Impressions,Position\na,1,2,8\na,1,2,8\n")),'duplicate query rows are rejected rather than double-counted');
check(is_wp_error(hsh_growth_month('2026-13')),'invalid reporting month is rejected');
update_option('hsh_request_counts',array('2026_10_30'=>array('manmos-cat-life'=>7),'2026_09_30'=>array('manmos-cat-life'=>100)),false);
$report=hsh_growth_month('2026-10');check($report['recorded_days']===1&&$report['requests']['manmos-cat-life']===7&&$report['search_pages']===null,'monthly report uses recorded month data and leaves missing search data absent');
echo "All growth checks passed. Total checks including shared suites: $tests\n";
