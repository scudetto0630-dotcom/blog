<?php
require __DIR__.'/whisky-split.php';
$profiles=array();foreach(hsh_catalog() as $a){$d=hsh_whisky_profile($a['slug']);if(!$d)continue;$slug=$a['slug'];$profiles[$slug]=$d;
    check(count($d['scores'])===6&&count($d['reasons'])===6,'six evidence-labelled axes for '.$slug);
    foreach($d['scores'] as $n)check(is_int($n)&&$n>=0&&$n<=10,'bounded editorial estimate '.$slug);
    foreach(array('種類','熟成樽','熟成年数') as $k)check(!empty($d['fields'][$k]),'product category/cask/age field '.$slug.' '.$k);
    foreach($d['prices'] as $p)check($p['volume']===(int)preg_replace('~.*・([0-9]+)ml.*~u','$1',$d['fields']['度数・標準容量'])&&$p['amount']>0,'price is for the exact standard bottle '.$slug);
    $original=hsh_studio_story_additions($a['content'],$slug);$html=hsh_whisky_enriched($original,$slug);
    check(substr_count($html,'data-hsh-whisky-profile=')===1&&substr_count($html,'<svg ')===1&&substr_count($html,'<meter ')===6,'one readable six-axis profile '.$slug);
    check(hsh_whisky_enriched($html,$slug)===$html,'enrichment cannot duplicate '.$slug);
    foreach(array('id','src') as $attr){preg_match_all('~\b'.$attr.'="([^"]+)"~',$original,$before);preg_match_all('~\b'.$attr.'="([^"]+)"~',$html,$after);check(!array_diff($before[1],$after[1]),'original anchors and photos survive '.$slug.' '.$attr);}
    check(strpos($html,'実測値や口コミの平均、筆者が採点した数値ではありません')!==false,'estimates do not pretend to be measured or owner-given scores '.$slug);
    $profiles[$slug]['preview']=$html;
}
foreach($profiles as $slug=>$d){check(strpos($d['preview'],'スパイシーさ')!==false&&strpos($d['preview'],'<dt>樽の香ばしさ</dt>')===false,'revised flavour axis '.$slug);check(strpos($d['preview'],'香味の方向を比べるための目安')===false,'removed unwanted caption '.$slug);}
check(count($profiles)===7,'all seven owner-tasted bottles have their own researched profile');
check(strpos($profiles['busker-irish-whiskey-review']['fields']['種類'],'ブレンデッド')!==false,'green Busker is not its single malt sibling');
check(strpos($profiles['glen-grant-arboralis-review']['fields']['熟成樽'],'オロロソ')!==false&&strpos($profiles['glen-grant-arboralis-review']['fields']['度数・標準容量'],'40%')!==false,'Arboralis uses the confirmed normal variant');
check(strpos($profiles['jim-beam-first-impression']['fields']['熟成樽'],'新樽')!==false,'bourbon uses new charred oak');
check(strpos($profiles['kirin-riku-review']['fields']['熟成樽'],'確認できず')!==false&&strpos($profiles['nikka-from-the-barrel-review']['fields']['熟成樽'],'確認できず')!==false,'unpublished cask detail is not invented');
check(hsh_whisky_enriched('<p>Unrelated</p>','manmos-cat-life')==='<p>Unrelated</p>','other topics are not enriched');
check(hsh_whisky_profile('compass-box-orchard-house')===null,'untried Orchard House has no invented tasting scores');
echo "All whisky-depth checks passed. Total: $tests\n";
if(getenv('HSH_WHISKY_PREVIEW'))echo 'WHISKY_PREVIEW_JSON:'.json_encode($profiles,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
