<?php
require __DIR__.'/cat-guide-preview.php';
if(!defined('DAY_IN_SECONDS')){define('DAY_IN_SECONDS',86400);}
function wp_salt($scheme){return 'isolated-test-salt';}
function get_transient($key){return $GLOBALS['nkt_test_transients'][$key] ?? false;}
function set_transient($key,$value,$ttl){$GLOBALS['nkt_test_transients'][$key]=$value;return true;}
function wp_generate_uuid4(){static $id=1;return '00000000-0000-4000-8000-'.str_pad((string)$id++,12,'0',STR_PAD_LEFT);}
$base=array('page'=>'https://example.test/neko-guide/articles/first-days/','kind'=>'content','message'=>'気になる箇所です。','website'=>'');
check(!is_wp_error(nkt_validate_report($base)),'valid correction accepted');
foreach(array('','https://evil.test/','https://example.test/','javascript:alert(1)','https://example.test/neko-guide/assets/site.js','https://example.test/neko-guide/../','https://example.test:443/neko-guide/','https://example.test@evil.test/neko-guide/') as $url){$d=$base;$d['page']=$url;check(is_wp_error(nkt_validate_report($d)),'outside or non-page URL rejected');}
foreach(array('', '　　', "\n\t",str_repeat('猫',2001),array(),'<b></b>') as $message){$d=$base;$d['message']=$message;check(is_wp_error(nkt_validate_report($d)),'blank, malformed or overlong message rejected');}
$d=$base;$d['message']=str_repeat('猫',2000);check(!is_wp_error(nkt_validate_report($d)),'exactly 2000 Japanese characters accepted');
$d=$base;$d['website']='bot';check(nkt_validate_report($d)->code==='nkt_bot','honeypot rejected');
$d=$base;$d['admin']='yes';check(is_wp_error(nkt_validate_report($d)),'unknown public field rejected');
$d=$base;$d['kind']='delete';check(is_wp_error(nkt_validate_report($d)),'unknown correction kind rejected');
check(nkt_allowed_origin('https://example.test') && !nkt_allowed_origin('https://evil.test') && !nkt_allowed_origin('') && !nkt_allowed_origin('https://example.test.evil.test'),'same-origin submission required');
$now=time();$valid=nkt_validate_report($base);$result=nkt_store_report($valid,'client-one',$now);
check($result['received']===true && count(get_option('nkt_correction_reports'))===1,'correction is durably saved');
check(strpos(json_encode(get_option('nkt_correction_reports')),'client-one')===false,'raw client identifier is not saved with report');
for($i=0;$i<2;$i++){check(nkt_store_report($valid,'client-one',$now)['received']===true,'submission within rate limit accepted');}
check(nkt_store_report($valid,'client-one',$now)->code==='nkt_rate','fourth report within one hour rejected');
check(nkt_store_report($valid,'client-one',$now+3601)['received']===true,'rate limit resets after expiry');
$GLOBALS['test_nkt_save_failure']=true;check(nkt_store_report($valid,'client-save-fail',$now)->code==='nkt_save','database save failure is not reported as success');unset($GLOBALS['test_nkt_save_failure']);check(!get_option('nkt_reports_lock'),'report lock released after save failure');
add_option('nkt_reports_lock',$now);check(nkt_store_report($valid,'client-busy',$now)->code==='nkt_busy','simultaneous writes cannot overwrite reports');delete_option('nkt_reports_lock');
add_option('nkt_reports_lock',$now-121);check(nkt_store_report($valid,'client-recover',$now)['received']===true,'stale lock recovers after interrupted process');
$rows=get_option('nkt_correction_reports');$rows[]=array_merge($valid,array('id'=>'expired','created_at'=>$now-91*DAY_IN_SECONDS));update_option('nkt_correction_reports',$rows);check(count(nkt_pruned_reports($now))===count($rows)-1,'reports older than 90 days removed from retained set');
$delete=nkt_delete_report(new Request(array('id'=>$result['id'])));check($delete->data['deleted']===true,'authenticated scoped deletion removes test correction');
check(nkt_delete_report(new Request(array('id'=>$result['id'])))->code==='nkt_missing','repeat deletion does not pretend success');
check(is_wp_error(nkt_delete_report(new Request(array('id'=>'../bad')))),'malformed deletion identifier rejected');
update_option('nkt_correction_reports',array_fill(0,200,array_merge($valid,array('id'=>'capacity-test','created_at'=>$now))));check(nkt_store_report($valid,'client-capacity',$now)->code==='nkt_capacity','report storage has a fixed capacity');
$rows=array(array('title'=>'old','review_due_at'=>'2026-10-15'),array('title'=>'today','review_due_at'=>'2026-10-16'),array('title'=>'new','review_due_at'=>'2026-10-17'));check(count(nkt_due_articles($rows,'2026-10-16'))===1,'overdue articles identified without inventing review dates');
$html='before<!--NKT_FRESH_UNTIL:2026-10-15--><p>expired</p><!--/NKT_FRESH_UNTIL--><!--NKT_FRESH_UNTIL:2026-10-16--><p>current</p><!--/NKT_FRESH_UNTIL-->after';$filtered=hsh_catguide_filter_expired($html,'2026-10-16');check(strpos($filtered,'expired')===false && strpos($filtered,'current')!==false && strpos($filtered,'before')===0,'expired event removed on request while current listing stays');
check(hsh_catguide_route('/neko-guide/maintenance.json')['status']===404,'maintenance data not routed as a public page');
check(hsh_catguide_route('/neko-guide/api/report-token/')['api']==='token' && hsh_catguide_route('/neko-guide/api/report/')['api']==='report','only explicit public report endpoints are routed');
echo "All cat guide tools tests passed. Total tests: $tests\n";
