<?php
require __DIR__.'/followups.php';
$m=hsh_photo_manifest();
$first=hsh_photo_slide_image($m['assets']['manmos-rest'],true);
check(strpos($first,'src="data:')===false&&strpos($first,'fetchpriority="high"')!==false,'first hero photograph remains immediate and high priority');
$second=hsh_photo_slide_image($m['assets']['whisky-japanese-display']);
check(strpos($second,'data-hsh-src="')!==false&&strpos($second,'data-hsh-srcset="')!==false&&strpos($second,'src="data:image/gif')!==false,'inactive hero defers both source and responsive candidates');
check(strpos($second,'width="')!==false&&strpos($second,'height="')!==false&&strpos($second,'alt="')!==false,'deferred photographs retain dimensions and alternative text');
foreach(array('highball-measure-and-adjust'=>'hsh-highball-calculator','ai-data-extraction-checks'=>'hsh-extraction-example','cat-carrier-checklist'=>'hsh-sheet-carrier-measure','cat-vet-routine'=>'hsh-sheet-vet-note') as $slug=>$id)check(strpos(hsh_studio_tool_shortcut($slug),'href="#'.$id.'"')!==false,'tool shortcut uses a stable target '.$slug);
check(hsh_studio_tool_shortcut('sherry-cask-notes')==='','unrelated articles do not receive a tool shortcut');
check(strpos(hsh_studio_highball(),'id="hsh-highball-calculator"')!==false&&strpos(hsh_studio_highball(),'1:3')!==false,'calculator explains what the multiplier means');
check(strpos(hsh_studio_examples(),'id="hsh-extraction-example"')!==false&&strpos(hsh_studio_examples(),'件数と合計が合っていても')!==false,'worked example explains why matching IDs also matter');
check(strpos(hsh_studio_checklist('vet-note','Test',array('item')),'id="hsh-sheet-vet-note"')!==false,'checklist exposes its stable shortcut target');
$descriptions=array();foreach(hsh_reader_roles() as $slug=>$role)if(isset($role['search_description'])){$descriptions[]=$role['search_description'];check(mb_strlen($role['search_description'],'UTF-8')>=60&&mb_strlen($role['search_description'],'UTF-8')<=150,'description is a concise article-specific sentence '.$slug);}
check(count($descriptions)===6&&count(array_unique($descriptions))===6,'all six revised descriptions are distinct');
$items=hsh_affiliate_articles();$items[0]['title']='Local mutation';check(hsh_affiliate_articles()[0]['title']!=='Local mutation','cached article data is not mutated by a caller');
echo "All five-improvement checks passed. Total: $tests\n";
