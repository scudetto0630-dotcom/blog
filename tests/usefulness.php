<?php
require __DIR__.'/ad-consolidation-review.php';
$budget=hsh_studio_budget();
check(substr_count($budget,'data-budget-field=')===4&&substr_count($budget,' required')===2,'budget separates the two known inputs from optional unknown reserves');
check(strpos($budget,'data-budget-app hidden')!==false&&strpos($budget,' name=')===false,'budget remains non-submittable without initialization and has no URL field names');
check(strpos($budget,' value=')===false,'budget never silently substitutes zero for unknown taxes');
check(strpos($budget,'税額を自動計算する欄ではありません')!==false&&strpos($budget,'送信・保存しません')!==false,'budget explains its arithmetic and handling of personal amounts');
check(strpos($budget,'<noscript>')!==false,'manual calculation remains available without JavaScript');
$dest=getenv('HSH_BUDGET_PREVIEW');
if($dest)file_put_contents($dest,'<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/studio.css"></head><body class="hsh-reader-site"><main style="max-width:760px;margin:24px auto;padding:16px"><h1>予算の計算</h1>'.$budget.'</main><script src="/budget.js"></script></body></html>');
echo "Usefulness feature checks passed. Total: $tests\n";
