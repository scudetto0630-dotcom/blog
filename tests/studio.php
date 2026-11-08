<?php
require __DIR__.'/brushup.php';
check(hsh_studio_minutes(str_repeat('あ',1201))===3&&hsh_studio_minutes('<b></b>')===1,'reading estimate counts text and has a one minute floor');
check(count(hsh_studio_source_content())===20,'every affiliate article has distinct source-backed reader notes');
foreach(hsh_affiliate_articles() as $a){$d=hsh_studio_source_content()[$a['slug']];check($d['source']===$a['source_url']&&count($d['steps'])===3&&count($d['qa'])===2,'product instructions and source stay aligned '.$a['slug']);}
check(strpos(hsh_studio_product_notes('withpety-nekobihada'),'PS-B1')!==false&&strpos(hsh_studio_product_notes('withpety-nekobihada'),'コラーゲン')===false,'Bihada formula cannot drift to a different variant');
check(strpos(hsh_studio_product_notes('withpety-nekoryoukan'),'肝臓')!==false&&strpos(hsh_studio_product_notes('withpety-nekoryoukan'),'腎臓')===false,'liver supplement remains distinct from kidney products');
check(hsh_studio_product_notes('unknown')==='','unknown product cannot gain invented content');
check(strpos(hsh_studio_checklist('test','<script>title</script>',array('<img>item')),'<script>')===false,'note-sheet labels are escaped');
check(strpos(hsh_studio_highball(),'グラス容量')!==false&&strpos(hsh_studio_highball(),'氷')!==false,'calculator separates liquid volume from ice capacity');
check(strpos(hsh_studio_examples(),'0001')!==false&&strpos(hsh_studio_examples(),'2,700')!==false,'worked data example preserves leading zeros and exact expected total');
check(strpos(hsh_studio_examples(),'download')===false,'user excluded practice CSV downloads');
check(strpos(hsh_studio_featured(),'STORIES')!==false&&strpos(hsh_studio_home_products(),'商品紹介')!==false,'home separates original stories and product introductions');
check(strpos(hsh_studio_series('manmos-cat-life'),'1 / 5')!==false,'reading route shows actual position');
check(strpos(hsh_studio_care(),'取扱説明書')!==false,'material care defers unverified specifics to actual product instructions');
$dateArticle=hsh_affiliate_articles()[0];$datePost=get_page_by_path($dateArticle['slug']);$dateResult=hsh_release_article(new Request(array('slug'=>$dateArticle['slug'],'expected'=>hsh_article_fingerprint($datePost))));$datePost=get_post($datePost->ID);$knownDate=new DateTimeImmutable($dateArticle['original_published_at']);check(!is_wp_error($dateResult)&&$datePost->post_date===$knownDate->format('Y-m-d H:i:s')&&$datePost->post_date_gmt===$knownDate->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),'article refresh restores and preserves verified publication date independently from modification date');
echo "All studio checks passed. Total: $tests\n";
$public=hsh_reader_catalog();
$extras=hsh_studio_highball().hsh_studio_checklist('preview-test','確認シート',array('確認1','確認2')).hsh_studio_examples().hsh_brushup_comparison('cats').hsh_studio_product_notes('mognyan-cat-food-guide');
$pages['studio']=array('title'=>'読書と練習ツール','current'=>'highball-measure-and-adjust','html'=>'<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'.hsh_reader_bootstrap().'</head><body class="hsh-reader-site">'.hsh_reader_header().'<main class="hsh-preview-main"><h1>読書と練習ツール</h1>'.hsh_reader_tools().'<div class="entry-content"><h2 id="practice-heading">練習する</h2>'.$extras.'</div></main><script id="hsh-reader-data" type="application/json">'.wp_json_encode(array('articles'=>$public,'current'=>'highball-measure-and-adjust')).'</script></body></html>');
foreach($pages as &$page){$page['html']=preg_replace('~(<script id="hsh-reader-data" type="application/json">).*?(</script>)~s','$1'.wp_json_encode(array('articles'=>$public,'current'=>$page['current'])).'$2',$page['html']);}unset($page);
echo '<!--HSH_STUDIO_PREVIEW_START-->'.wp_json_encode($pages).'<!--HSH_STUDIO_PREVIEW_END-->';
