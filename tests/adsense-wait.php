<?php
require __DIR__.'/whisky-depth.php';
$html='<img src="https://example.test/wp-content/themes/cocoon-master/screenshot.jpg" width="880" height="660" loading="eager"><img src="https://example.test/wp-content/plugins/hirogaru-home/assets/photos/glenlivet.webp" loading="eager"><img src="https://other.test/wp-content/themes/cocoon-master/screenshot.jpg">';
$deferred=hsh_reader_defer_theme_placeholders($html);
check(substr_count($deferred,'loading="lazy"')===1&&strpos($deferred,'glenlivet.webp" loading="eager"')!==false,'only own theme placeholders are deferred; actual article images stay eager');
check(hsh_reader_defer_theme_placeholders($deferred)===$deferred,'placeholder deferral is idempotent');
$normal=(object)array('post_name'=>'not-managed','post_content'=>'Small text');check(hsh_studio_post_minutes($normal)===hsh_studio_minutes($normal->post_content),'ordinary article estimate is unchanged');
foreach(hsh_expanded_articles() as $a)if(in_array($a['slug'],array('kirin-riku-review','busker-irish-whiskey-review'),true)){$p=(object)array('post_name'=>$a['slug'],'post_content'=>$a['content']);$before=$p->post_content;check(hsh_studio_post_minutes($p)>hsh_studio_minutes($before),'reading estimate includes the actual researched text '.$a['slug']);check($p->post_content===$before,'estimating does not alter the saved article');}
$meta=hsh_growth_metadata('<head><meta name="thumbnail" content="theme.jpg"><meta name="thumbnail" content="duplicate.jpg"></head><body>body</body>',array('og:image'=>'https://example.test/correct.webp'));
check(substr_count($meta,'name="thumbnail"')===1&&strpos($meta,'name="thumbnail" content="https://example.test/correct.webp"')!==false,'generic thumbnail uses the same correct image as OGP once');
function gsc_part($a){return rtrim(strtr(base64_encode(json_encode($a)),'+/','-_'),'=');}
function gsc_token($h,$c){return gsc_part($h).'.'.gsc_part($c).'.'.gsc_part('signature');}
$now=time();$body='{"report":"private"}';$header=array('alg'=>'RS256','typ'=>'JWT','kid'=>'test-key');$claims=array('iss'=>hsh_gsc_service_email(),'aud'=>hsh_gsc_audience(),'iat'=>$now,'exp'=>$now+300,'jti'=>'1234567890abcdef1234','sha256'=>hash('sha256',$body));$verify=function(){return true;};$certs=array('test-key'=>'fixture');
check(is_array(hsh_gsc_authenticate(gsc_token($header,$claims),$body,$certs,$now,$verify)),'valid scoped claims and verified signature authorize a matching report');
check(is_wp_error(hsh_gsc_authenticate(gsc_token($header,$claims),$body.'changed',$certs,$now,$verify)),'JWT is bound to the exact body');
foreach(array(array('iss','other@invalid.test'),array('aud','https://other.test'),array('iat',$now+61),array('iat',$now-301),array('exp',$now),array('exp',$now+301),array('jti','short')) as $change){$bad=$claims;$bad[$change[0]]=$change[1];check(is_wp_error(hsh_gsc_authenticate(gsc_token($header,$bad),$body,$certs,$now,$verify)),'reject mismatched issuer, audience or time: '.$change[0]);}
foreach(array('none','HS256') as $alg){$bad=$header;$bad['alg']=$alg;check(is_wp_error(hsh_gsc_authenticate(gsc_token($bad,$claims),$body,$certs,$now,$verify)),'reject unapproved algorithm '.$alg);}
check(is_wp_error(hsh_gsc_authenticate(gsc_token($header,$claims),$body,array(),$now,$verify)),'unknown signing key cannot authenticate');
check(is_wp_error(hsh_gsc_authenticate(gsc_token($header,$claims),$body,$certs,$now,function(){return false;})),'signature failure cannot authenticate');
$empty=array('site'=>'https://hirogarushumi.com/','state'=>'no_finalized_data','analysis'=>null,'daily'=>array(),'collected_at'=>$now);
check(hsh_gsc_valid_report($empty,$now)===$empty,'an authenticated empty result stays no data, not zero traffic');
$bad=$empty;$bad['site']='https://other.test/';check(is_wp_error(hsh_gsc_valid_report($bad,$now)),'other properties cannot be stored');
$bad=$empty;$bad['collected_at']=$now-3601;check(is_wp_error(hsh_gsc_valid_report($bad,$now)),'stale reports cannot be imported');
$metric=array('clicks'=>1,'impressions'=>10,'ctr'=>.1,'position'=>4);$data=$empty;$data['state']='collected';$data['scope']='https://www.googleapis.com/auth/webmasters.readonly';$data['data_state']='final';$data['dimensions']=array('page','query','device','country');$data['daily']=array(array_merge($metric,array('date'=>'2026-11-13')));$data['current_rows']=array(array_merge($metric,array('page'=>'https://hirogarushumi.com/test/','query'=>'private search','device'=>'MOBILE','country'=>'jpn')));
check(hsh_gsc_valid_report($data,$now)===$data,'valid finalized page/query data remains private data');
foreach(array(array('clicks',11),array('ctr',1.2),array('page','https://hirogarushumi.com.evil.test/'),array('device','UNKNOWN')) as $change){$bad=$data;$bad['current_rows'][0][$change[0]]=$change[1];check(is_wp_error(hsh_gsc_valid_report($bad,$now)),'invalid metric or row scope rejected '.$change[0]);}
class GscRequest extends Request {private $raw;public function __construct($data,$raw){parent::__construct($data);$this->raw=$raw;}public function get_body(){return $this->raw;}}
$r=hsh_gsc_receive(new GscRequest($data,json_encode($data)));check($r instanceof WP_REST_Response&&$r->data['stored']===true,'valid report is stored without public output');
$read=hsh_gsc_private_report(new Request());check($read->headers['Cache-Control']==='no-store, private'&&$read->data['connected']===true,'authenticated reader returns a no-store private response');
check(hsh_edit_permission(new Request())->code==='hsh_auth','report GET uses the same editor authentication gate');
check(hsh_gsc_ingest_permission(new GscRequest(array(),''))->code==='hsh_gsc_authorization','anonymous ingestion is rejected before fetching certificates');
$connection=hsh_gsc_connection_status();check($connection['connected']===true&&strpos(json_encode($connection),'private search')===false,'connection status is current and never includes private queries');
echo "All AdSense-wait checks passed. Total: $tests\n";
