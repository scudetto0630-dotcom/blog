<?php
require __DIR__.'/fifty-growth.php';
check(hsh_adsense_prepared_slot()==='','manual ad placement is absent until explicitly configured');
foreach(array('', '123', '1234567890<script>', 1234567890) as $slot)check(hsh_adsense_slot_markup($slot)==='','invalid or missing ad slot cannot create an ad request');
$slot=hsh_adsense_slot_markup('1234567890');
check(substr_count($slot,'class="adsbygoogle"')===1&&strpos($slot,'data-ad-client="ca-pub-1945643191231022"')!==false,'prepared placement uses the existing publisher exactly once');
check(strpos($slot,'aria-label="広告"')!==false&&strpos($slot,'position:fixed')===false,'prepared ad is labelled and stays in document flow');
check(hsh_reader_needs_contact_assets('お問い合わせ','')&&hsh_reader_needs_contact_assets('page','[contact-form-7 id="1"]')&&hsh_reader_needs_contact_assets('page','<!-- wp:contact-form-7/contact-form-selector -->'),'contact page, shortcode and block retain form assets');
check(!hsh_reader_needs_contact_assets('glenlivet-12-first-bottles','<p>感想</p>'),'ordinary story does not require form assets');
$p=(object)array('post_name'=>'glenlivet-12-first-bottles','post_excerpt'=>'stored original');
check(strpos(hsh_reader_card_summary($p),'3本目')!==false&&$p->post_excerpt==='stored original','card description uses confirmed hook without changing the saved excerpt');
check(hsh_reader_card_summary((object)array('post_name'=>'unknown','post_excerpt'=>'Original'))==='Original','unmanaged summary remains untouched');
$a=hsh_photo_manifest()['assets']['article-sherry']??hsh_photo_manifest()['assets']['manmos-front'];$preload=hsh_photo_article_preload($a);
check(strpos($preload,'rel="preload"')!==false&&strpos($preload,'fetchpriority="high"')!==false&&strpos($preload,$a['file'])!==false,'article preload discovers the same existing photo');
if(!empty($a['variants']))check(strpos($preload,'imagesrcset="')!==false&&strpos($preload,'imagesizes="')!==false,'preload retains responsive image selection');
check(hsh_photo_article_preload(array('file'=>'../bad.webp'))==='','preload rejects invalid asset paths');
echo "All reader-next checks passed. Total: $tests\n";
