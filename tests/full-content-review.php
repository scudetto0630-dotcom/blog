<?php
require __DIR__.'/article-consolidation.php';
require_once __DIR__.'/../hirogaru-home/fixed-page-copy.php';
$home=get_post((int)get_option('hsh_page_id'));
foreach(array('contact'=>array(14,'お問い合わせ'),'privacy'=>array(18,'プライバシーポリシー')) as $key=>$value){
 $records[$value[0]]=array('ID'=>$value[0],'post_type'=>'page','post_status'=>'publish','post_author'=>$home->post_author,'post_name'=>rawurlencode($value[1]),'post_title'=>$value[1],'post_content'=>$key==='contact'?'<p>old</p>[contact-form-7 id="16" title="Contact"]':'<p>old privacy</p>','post_excerpt'=>'old','post_date'=>'2026-06-20 10:00:00','post_date_gmt'=>'2026-06-20 01:00:00');
}
check(hsh_public_page_release(new Request(array('page'=>'arbitrary','expected'=>str_repeat('a',64))))->code==='hsh_scope','public-page publisher only accepts two packaged utility pages');
check(hsh_public_page_release(new Request(array('page'=>'contact','expected'=>str_repeat('a',64),'content'=>'injected')))->code==='hsh_input','public-page publisher never accepts arbitrary content');
check(hsh_public_page_release(new Request(array('page'=>'contact','expected'=>str_repeat('a',64))))->code==='hsh_conflict','stale public page fingerprints reject without changes');
$records[14]['post_author']=999;check(hsh_public_page_status()->data['pages']['contact']===array('status'=>'conflict'),'ownership conflict withholds raw page content');$records[14]['post_author']=$home->post_author;
$old=$records[14]['post_content'];$records[14]['post_content']='no shortcode';check(hsh_public_page_release(new Request(array('page'=>'contact','expected'=>hsh_article_fingerprint(get_post(14)))))->code==='hsh_form','missing form cannot be overwritten');$records[14]['post_content']=$old;
foreach(array('contact'=>14,'privacy'=>18) as $key=>$id){
 $before=get_post($id);$r=hsh_public_page_release(new Request(array('page'=>$key,'expected'=>hsh_article_fingerprint($before))));check($r instanceof WP_REST_Response,'packaged utility page released '.$key);
 $after=get_post($id);foreach(array('post_type','post_status','post_author','post_name','post_title','post_date','post_date_gmt') as $field)check($before->$field===$after->$field,'page identity and publication preserved '.$key.' '.$field);
 check(count(get_post_meta($id,'_hsh_public_page_history',true))===1,'public page original saved '.$key);
}
check(strpos(get_post(14)->post_content,'[contact-form-7 id="16" title="Contact"]')!==false,'existing contact shortcode preserved byte for byte');
check(strpos(get_post(18)->post_content,'氏名、メールアドレス、メッセージ')!==false&&strpos(get_post(18)->post_content,'個人を特定するものではありません')===false,'privacy describes actual contact data without blanket anonymity claim');
foreach(hsh_affiliate_articles() as $a)if(!empty($a['self_contained']))check(strpos($a['content'],'広告')!==false&&strpos($a['content'],'px.a8.net')!==false,'reviewed self-contained copy retains advertising disclosure '.$a['slug']);
$prices=hsh_whisky_profile('glenlivet-12-first-bottles')['prices'];check($prices[0]['checked']==='2026-11-13'&&$prices[1]['checked']==='2026-11-25','price observations keep different actual checking dates');
check(hsh_whisky_profile('sherry-cask-notes')['scores'][0]===0,'peat score reflects importer explicit non-peated malt statement');
$photos=hsh_photo_manifest();check($photos['affiliate_product_images']['hario-cat-bowl-guide']===array('official-a8-02-1','official-hario-pts-nyt-side'),'bowl article shows two views of the actual reviewed bowl, not cat grass');
$side=$photos['assets']['official-hario-pts-nyt-side'];check($side['kind']==='official'&&$side['rights']==='advertiser'&&$side['source_page']==='https://shop.hariocorp.co.jp/products/pts-nyt','replacement bowl image retains advertiser provenance');
echo "Full content review checks passed. Total: $tests\n";
