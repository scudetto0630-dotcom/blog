<?php
require __DIR__.'/introduction.php';
$original='<p>当ブログへのお問い合わせは下記フォームよりお願いいたします。</p>[contact-form-7 id="16"]';
$changed=hsh_fixed_page_copy($original,14);
check(strpos($changed,'以下のフォームから')!==false,'contact introduction uses plain Japanese');
check(strpos($changed,'[contact-form-7 id="16"]')!==false,'contact shortcode preserved');
check(hsh_fixed_page_copy($original,999)===$original,'unrelated page untouched');
$privacy='<h1 class="wp-block-heading">プライバシーポリシー</h1><p>広告配信事業者は、Cookieを使用することがあります。</p><p>責任の範囲は変えません。</p>';
$changed=hsh_fixed_page_copy($privacy,18);
check(strpos($changed,'<h1')===false && strpos($changed,'広告を配信する事業者は、')!==false,'duplicate privacy title and dense wording corrected');
check(strpos($changed,'<p>責任の範囲は変えません。</p>')!==false,'other privacy clauses preserved');
check(hsh_edit_home(new Request(array('heading_2'=>'','heading_3'=>'')))->data['updated']===true,'optional heading lines may be omitted');
check(hsh_edit_home(new Request(array('heading_1'=>'')))->code==='hsh_heading','main heading remains required');
$form='<form action="/wp-comments-post.php"><p class="comment-notes"><span>*</span> が付いている欄は必須項目です</p><input name="email" value=""><textarea name="comment"></textarea></form>';
$fixed=hsh_comment_note_copy($form);check(strpos($fixed,'印のある欄は必須項目です。')!==false && strpos($fixed,'<input name="email" value=""><textarea name="comment"></textarea>')!==false,'theme comment note corrected while form fields remain intact');
echo "All proofreading copy tests passed. Total tests: $tests\n";
