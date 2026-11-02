<?php
function get_queried_object_id(){return $GLOBALS['reader_id']??0;}
function wp_strip_all_tags($v){return strip_tags($v);}
function strip_shortcodes($v){return preg_replace('/\[[^\]]*\]/','',$v);}
function is_search(){return $GLOBALS['reader_search']??false;}
function is_date(){return $GLOBALS['reader_date']??false;}
function is_author(){return false;}
function is_tag(){return false;}
function is_page($id=null){return false;}
require __DIR__.'/growth.php';
$excerpt=hsh_reader_search_excerpt(str_repeat('前の説明。',50).'先頭の0を保つために文字列を指定します。後ろの説明。','先頭の0',50);
check(strpos($excerpt,'先頭の0')!==false&&strpos($excerpt,'…')===0,'search excerpt finds a matching passage deep inside an article');
check(strpos(hsh_reader_search_excerpt('<script>unsafe</script>読みたい本文','本文'),'<script>')===false,'search excerpt is plain text before template escaping');
check(in_array('ハイボール',hsh_reader_search_suggestions('ウイスキー'),true),'empty search offers related whisky vocabulary');
check(hsh_reader_archive_noindex('date')&&hsh_reader_archive_noindex('author'),'utility date and author archives are noindex');
check(!hsh_reader_archive_noindex('category',1,''),'curated categories are not classified as thin utility archives');
check(hsh_reader_archive_noindex('tag',1,'短い説明')&&!hsh_reader_archive_noindex('tag',3,str_repeat('説明',30)),'tag value depends on article count and a useful description');
$catalog=array(array('id'=>'one','url'=>'https://example.test/one/'),array('id'=>'two','url'=>'https://example.test/two/'));
check(hsh_reader_redirect_value('/old-story/','one',$catalog)===array('source'=>'/old-story/','target'=>'one'),'legacy path may point to a published managed article');
foreach(array('//evil.test/','/wp-admin/','/neko-guide/article/','/../one/','/old/?x=1','/old/%2f/') as $path)check(is_wp_error(hsh_reader_redirect_value($path,'one',$catalog)),'unsafe or unrelated redirect source rejected '.$path);
check(is_wp_error(hsh_reader_redirect_value('/one/','one',$catalog)),'self redirect rejected');
check(is_wp_error(hsh_reader_redirect_value('/one/','two',$catalog)),'live article cannot be hijacked by a redirect');
check(is_wp_error(hsh_reader_redirect_value('/old-story/','foreign',array(array('id'=>'foreign','url'=>'https://foreign.test/a/')))),'offsite redirect target rejected');
$GLOBALS['reader_search']=true;$head=hsh_reader_rendered('<head><meta name="robots" content="index,nofollow"><link rel="canonical" href="https://example.test/"></head>body');
check(strpos($head,'noindex')!==false&&strpos($head,'nofollow')!==false&&strpos($head,'rel="canonical"')!==false,'reader robots integration preserves restrictive directives and canonical');$GLOBALS['reader_search']=false;
$GLOBALS['test_admin_cap']=false;ob_start();hsh_reader_operations();check(ob_get_clean()==='','private improvement dashboard refuses non-admin readers');
check(strpos(hsh_reader_saved_page(),'この端末')!==false,'reading list explains device-local persistence');
$snap=hsh_planner_snapshot();$aid='article:manmos-cat-life';$f=array_intersect_key($snap['articles'][$aid],array_flip(hsh_planner_article_fields()));$f['offer_fit']='体格と移動方法に合う道具';$f['pre_use']='使用前の記録は未取得';$f['followups']='追記日・使用期間・変化';$r=hsh_planner_save(array('action'=>'save','id'=>$aid,'expected'=>$snap['articles'][$aid]['fingerprint'],'fields'=>$f));
check(!is_wp_error($r)&&$r['articles'][$aid]['followups']===$f['followups'],'affiliate fit and dated follow-up fields persist privately');
$metrics=array('start'=>'2026-10-11','end'=>'2026-10-20','rows'=>array(array('page'=>'https://example.test/one/','clicks'=>8,'impressions'=>100)));
$history=array(array('option'=>'hsh_gsc_pages','data'=>array('start'=>'2026-10-01','end'=>'2026-10-10','rows'=>array(array('page'=>'https://example.test/one/','clicks'=>2,'impressions'=>60)))));
check(count(hsh_reader_search_growth($metrics,$history))===1,'growth candidates compare equal-length non-overlapping actual metric periods');
$history[0]['data']['end']='2026-10-09';check(hsh_reader_search_growth($metrics,$history)===array(),'different period lengths are not compared as growth');
check(hsh_reader_search_growth(array(),array())===array(),'missing search data creates no synthetic candidates');
check(hsh_reader_snapshot('before test')&&count(get_option('hsh_reader_checkpoints'))===1,'improvement checkpoint stores current state privately');
add_option('hsh_reader_checkpoint_lock',time());check(hsh_reader_snapshot('concurrent')===false,'concurrent checkpoint cannot overwrite another append');delete_option('hsh_reader_checkpoint_lock');
$GLOBALS['test_extra_posts']=array((object)array('ID'=>1900,'post_type'=>'post','post_status'=>'private','post_name'=>'private-future','post_title'=>'Private','post_excerpt'=>'private','post_category'=>array(8)),(object)array('ID'=>1901,'post_type'=>'post','post_status'=>'publish','post_name'=>'new-story','post_title'=>'New story','post_excerpt'=>'Public','post_category'=>array(8)));
$ids=array_column(hsh_reader_catalog(),'id');check(in_array('new-story',$ids,true)&&!in_array('private-future',$ids,true),'reader catalog includes new published stories and excludes private posts');unset($GLOBALS['test_extra_posts']);
$entries=array(array('loc'=>home_url('/hsh-reading-list/'),'lastmod'=>'2026-11-03'),array('loc'=>home_url('/hsh-hakushu-visit/'),'lastmod'=>'2026-10-30'));check(hsh_reader_sitemap_entries($entries)===array($entries[1]),'sitemap excludes local-only saved list and preserves public entry metadata');
echo "All reader checks passed. Total including shared suites: $tests\n";

// Read-only preview pages use the same PHP helpers, CSS and JS as production.
$public=hsh_reader_catalog();$pages=array();
$pages['index']=array('title'=>'ひろがる趣味暮らし','content'=>'<main class="hsh-main"><h1>まんもすとの暮らし、ウイスキー、仕事の話</h1>'.hsh_start_routes().'</main>','current'=>null);
$pages['gallery']=array('title'=>'まんもすの写真','content'=>hsh_growth_gallery(),'current'=>null);
$pages['saved']=array('title'=>'保存した記事','content'=>hsh_reader_saved_page(),'current'=>null);
foreach(hsh_catalog() as $a){$p=get_page_by_path($a['slug'],OBJECT,'post');if(!$p)continue;$toc='<div class="toc"><input type="checkbox" class="toc-checkbox" id="toc-toggle" checked><label class="toc-title" for="toc-toggle">目次</label><div class="toc-content"><ol><li><a href="#sample-content">本文へ</a></li></ol></div></div>';$tools=hsh_reader_tools();$body=hsh_insert_inline_photos($p->post_content,$a['slug']);$pages[$a['slug']]=array('title'=>$p->post_title,'content'=>$tools.'<div class="hsh-article-visual">'.hsh_photo_cover($p,true).'</div>'.$toc.'<div id="sample-content">'.$body.'</div><form class="hsh-report-form" data-slug="'.$a['slug'].'"><label>内容<textarea name="message" required minlength="6"></textarea></label><input name="website" type="hidden"><button type="submit">送信</button><span role="status"></span></form>','current'=>$a['slug']);}
foreach($pages as $key=>&$page){$data=array('articles'=>$public,'current'=>$page['current'],'savedUrl'=>'https://example.test/saved.html');$page['html']='<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'.hsh_reader_bootstrap().'</head><body class="hsh-reader-site">'.hsh_reader_header().'<main class="hsh-preview-main"><h1>'.esc_html($page['title']).'</h1><div class="entry-content">'.$page['content'].'</div></main><script id="hsh-reader-data" type="application/json">'.wp_json_encode($data).'</script></body></html>';}unset($page);
echo '<!--HSH_READER_PREVIEW_START-->'.wp_json_encode($pages).'<!--HSH_READER_PREVIEW_END-->';
