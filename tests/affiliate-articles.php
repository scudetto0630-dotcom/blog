<?php
require __DIR__.'/reader.php';
$added=hsh_affiliate_articles();
check(count($added)===13&&count(array_unique(array_column($added,'slug')))===13,'thirteen separately scoped affiliate articles');
check(count(hsh_expanded_articles())===10&&count(hsh_article_map())===3,'existing packaged article catalogs remain unchanged');
$manifest=hsh_photo_manifest();check(count(array_unique($manifest['affiliate_articles']))===13,'new articles use thirteen distinct cover choices');
$existing=array();foreach(hsh_expanded_articles() as $a){$p=get_page_by_path($a['slug']);$existing[$a['slug']]=$p?hsh_article_fingerprint($p):null;}
foreach($added as $a){
    check($a['topic']==='cats'&&strpos($a['content'],'この記事には広告を含みます')!==false&&strpos($a['content'],'未使用')!==false,'ad and non-use disclosure '.$a['slug']);
    $img=hsh_photo_for_slug($a['slug']);check($img&&$img['kind']==='provided','owned cat photograph cover '.$a['slug']);
    $keys=array_merge(array($manifest['affiliate_articles'][$a['slug']]),$manifest['affiliate_inline'][$a['slug']]);check(count(array_unique($keys))===3,'cover and body photos differ within '.$a['slug']);
    $body=hsh_insert_inline_photos($a['content'],$a['slug']);check(substr_count($body,'hsh-inline-photo')===2,'two actual body photos '.$a['slug']);
    $response=hsh_release_article(new Request(array('slug'=>$a['slug'],'expected'=>null)));check($response instanceof WP_REST_Response,'only packaged affiliate article can publish '.$a['slug']);
    $p=get_post($response->data['id']);check($p->post_content===$a['content']&&$p->post_status==='publish','published exact reviewed body '.$a['slug']);
    check(hsh_release_article(new Request(array('slug'=>$a['slug'],'expected'=>null)))->code==='hsh_conflict','repeat cannot overwrite human edits '.$a['slug']);
}
foreach($existing as $slug=>$fingerprint){$p=get_page_by_path($slug);check(($p?hsh_article_fingerprint($p):null)===$fingerprint,'existing article untouched '.$slug);}
$aff_snapshot=hsh_planner_snapshot();foreach($added as $a)check(isset($aff_snapshot['articles']['article:'.$a['slug']])&&$aff_snapshot['articles']['article:'.$a['slug']]['stage']==='published','new public article appears in private planner '.$a['slug']);
check(hsh_release_article(new Request(array('slug'=>'excluded-dog-product','expected'=>null)))->code==='hsh_scope','excluded product cannot be published through scoped endpoint');
echo "All affiliate checks passed. Total including shared suites: $tests\n";

$aff_pages=array();$aff_public=hsh_reader_catalog();
foreach($added as $a){$p=get_page_by_path($a['slug']);$data=array('articles'=>$aff_public,'current'=>$a['slug'],'savedUrl'=>'https://example.test/saved.html');$body=hsh_insert_inline_photos($p->post_content,$a['slug']);$aff_pages[$a['slug']]=array('html'=>'<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'.hsh_reader_bootstrap().'</head><body class="hsh-reader-site">'.hsh_reader_header().'<main class="hsh-preview-main"><h1>'.esc_html($p->post_title).'</h1>'.hsh_reader_tools().'<div class="hsh-article-visual">'.hsh_photo_cover($p,true).'</div><div class="entry-content">'.$body.'</div></main><script id="hsh-reader-data" type="application/json">'.wp_json_encode($data).'</script></body></html>');}
echo '<!--HSH_AFF_PREVIEW_START-->'.wp_json_encode($aff_pages).'<!--HSH_AFF_PREVIEW_END-->';
