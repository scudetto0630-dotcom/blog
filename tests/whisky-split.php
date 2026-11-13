<?php
require __DIR__.'/reader-next.php';
$reviews=array();foreach(hsh_expanded_articles() as $a)$reviews[$a['slug']]=$a;
$arbo=$reviews['glen-grant-arboralis-review'];$ftb=$reviews['nikka-from-the-barrel-review'];$jim=$reviews['jim-beam-first-impression'];
check(strpos($arbo['content'],'5本目')!==false&&strpos($arbo['content'],'2,200円')!==false&&strpos($arbo['content'],'比喩')!==false,'Arboralis retains the fifth purchase, historical price and the meaning of the YouTube comparison');
check(strpos($arbo['title'].$arbo['content'],'カスクストレングス')===false,'owner-confirmed normal Arboralis is never presented as cask strength');
check(strpos($jim['content'],'4本目')!==false&&strpos($jim['content'],'グラスセット')!==false&&strpos($jim['content'],'セメダイン')!==false,'Jim Beam has its own fourth-bottle purchase and negative impression');
check(strpos($ftb['content'],'3,900円')!==false&&strpos($ftb['content'],'かすかにメロンっぽい香り')!==false&&strpos($ftb['content'],'こぼれる')!==false,'From the Barrel keeps the purchase, qualified aroma and pouring experience together');
check(strpos($reviews['sherry-cask-notes']['content'],'アルボラリス')===false&&strpos($reviews['sherry-cask-notes']['content'],'フロム・ザ・バレル')===false,'Glenfarclas source has no other bottle tasting review');
$index=hsh_growth_whisky();foreach(array($arbo,$ftb,$jim) as $a)check(strpos($index,'/'.$a['slug'].'/')!==false,'index links to independent review '.$a['slug']);
check(strpos($index,'焦がしたキャラメル')===false&&strpos($index,'セメダイン')===false,'index no longer contains the relocated full tasting passages');
$photos=hsh_photo_manifest();foreach(array('glen-grant-arboralis-review'=>'arboralis-generated','nikka-from-the-barrel-review'=>'from-the-barrel-generated','jim-beam-first-impression'=>'jim-beam-generated') as $slug=>$key)check($photos['articles'][$slug]===$key&&is_file(__DIR__.'/../hirogaru-home/assets/photos/'.$photos['assets'][$key]['file']),'independent review has its matching packaged bottle image '.$slug);
check(hsh_photo_caption($photos['assets']['arboralis-generated'])===''&&hsh_photo_caption($photos['assets']['from-the-barrel-generated'])==='','internal provenance does not create a public AI-image label');
echo "All separate-whisky checks passed. Total: $tests\n";
