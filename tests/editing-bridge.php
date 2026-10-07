<?php
/* Isolated unit tests; WordPress stubs, not an integration test. */
define('ABSPATH', '/'); define('OBJECT', 'OBJECT');
$options = array(); $records = array(); $metadata = array(); $next_id = 100; $tests = 0;
class WP_Error { public $code; public function __construct($code, $message = '', $data = array()) { $this->code=$code; } }
class WP_REST_Response { public $data; public $headers=array(); public function __construct($data) {$this->data=$data;} public function header($k,$v){$this->headers[$k]=$v;} }
class Request { private $data; private $auth; public function __construct($data=array(),$auth=''){$this->data=$data;$this->auth=$auth;} public function get_json_params(){return $this->data;} public function get_header($key){return $this->auth;} }
function get_option($k,$default=false){global $options;return $options[$k] ?? $default;}
function update_option($k,$v,$autoload=null){global $options;$options[$k]=$v;return true;}
function add_option($k,$v,$unused='',$autoload=null){global $options;if(array_key_exists($k,$options))return false;$options[$k]=$v;return true;}
function delete_option($k){global $options;unset($options[$k]);}
function is_ssl(){return !get_option('test_http', false);}
function home_url($p='/'){return 'https://example.test'.$p;}
function wp_parse_url($url,$component){return parse_url($url,$component);}
function add_action(...$args){}
function add_filter(...$args){}
function register_deactivation_hook(...$args){}
function plugin_dir_path($path){return dirname($path).'/';}
function sanitize_textarea_field($value){return strip_tags($value);}
function sanitize_text_field($value){return trim(strip_tags($value));}
function wp_kses_post($value){return $value;} // WP implementation is outside this test.
function wp_slash($value){return $value;}
function is_wp_error($value){return $value instanceof WP_Error;}
function get_post($id){global $records;return isset($records[$id]) ? (object)$records[$id] : null;}
function get_post_type($id){$p=get_post($id);return $p ? $p->post_type : false;}
function get_post_status($id){$p=get_post($id);return $p ? $p->post_status : false;}
function get_post_meta($id,$key,$single=false){global $metadata;return $metadata[$id][$key] ?? '';}
function get_permalink($post){return 'https://example.test/?page_id='.(is_object($post)?$post->ID:$post);}
function get_page_by_path($slug,...$args){global $records;foreach($records as $p){if(($p['post_name'] ?? '')===$slug)return (object)$p;}return null;}
function wp_insert_post($post,$error=false){global $records,$metadata,$next_id;$id=$post['ID'] ?? $next_id++;$records[$id]=array_merge($records[$id] ?? array(),$post,array('ID'=>$id));foreach($post['meta_input'] ?? array() as $key=>$value){$metadata[$id][$key]=$value;}return $id;}
require dirname(__DIR__).'/hirogaru-home/editing-bridge.php';
function check($condition,$name){global $tests;if(!$condition){throw new Exception('FAIL: '.$name);}echo 'PASS: '.$name."\n";$tests++;}
$token=str_repeat('a',64);
check(hsh_edit_permission(new Request())->code==='hsh_auth','disabled connection rejects access');
update_option('hsh_edit_token_hash',hash('sha256',$token));
check(hsh_edit_permission(new Request())->code==='hsh_auth','missing key rejected');
check(hsh_edit_permission(new Request(array(),'Bearer '.str_repeat('b',64)))->code==='hsh_auth','wrong key rejected');
check(hsh_edit_permission(new Request(array(),'Bearer '.$token))===true,'valid key accepted');
update_option('test_http',true);check(hsh_edit_permission(new Request(array(),'Bearer '.$token))->code==='hsh_https','HTTP rejected');delete_option('test_http');
check(hsh_edit_permission(new Request(array(),'Bearer '.$token."\n"))->code==='hsh_auth','malformed key rejected');
delete_option('hsh_edit_token_hash');check(hsh_edit_permission(new Request(array(),'Bearer '.$token))->code==='hsh_auth','revoked key rejected');
check(hsh_edit_home(new Request(array('background'=>'red;display:none')))->code==='hsh_color','CSS injection rejected');
check(get_option('hsh_home_settings',array())===array(),'invalid request makes no changes');
check(hsh_edit_home(new Request(array('plugin_code'=>'anything')))->code==='hsh_field','unsupported edit rejected');
check(hsh_edit_home(new Request(array('heading_1'=>'')))->code==='hsh_heading','empty heading rejected');
check(hsh_edit_home(new Request(array('hide_empty'=>'true')))->code==='hsh_value','boolean type enforced');
$result=hsh_edit_home(new Request(array('heading_1'=>'猫との暮らし','hide_empty'=>true)));
check($result->data['updated']===true && get_option('hsh_home_settings')['hide_empty']===true,'allowed homepage changes stored');
check(count(get_option('hsh_home_history'))===1,'previous homepage settings retained');
check(hsh_edit_page(new Request(array('key'=>'any-page','title'=>'A','content'=>'B')))->code==='hsh_input','arbitrary page key rejected');
check(hsh_edit_page(new Request(array('key'=>array('about'),'title'=>'A','content'=>'B')))->code==='hsh_input','malformed page key rejected');
$data=array('key'=>'about','title'=>'運営者情報','content'=>'本文');$result=hsh_edit_page(new Request($data));$id=$result->data['id'];
check($result->data['status']==='draft','new page defaults to draft');
$result=hsh_edit_page(new Request(array_merge($data,array('status'=>'publish'))));
check($result->data['id']===$id && count($records)===1,'retry updates same page without duplicates');
check(count(hsh_edit_public_pages())===1,'owned published page appears in footer');
$metadata[$id]['_hsh_bridge_owned']='';
check(hsh_edit_page(new Request($data))->code==='hsh_scope','foreign page protected');
check(!get_option('hsh_edit_page_lock'),'lock released after rejected edit');
$options['hsh_edit_pages']=array();$records[$id]['post_name']='hsh-about';
check(hsh_edit_page(new Request($data))->code==='hsh_conflict','existing slug protected');
check(hsh_edit_page(new Request(array_merge($data,array('status'=>'private'))))->code==='hsh_status','unexpected status rejected');
check(hsh_edit_status()->headers['Cache-Control']==='no-store, private','authenticated response is not cacheable');
// Render the template and guard the previous global-posts regression.
function language_attributes(){echo 'lang="ja"';}
function bloginfo($field){echo 'UTF-8';}
function wp_head(){}
function body_class($class){echo 'class="'.$class.'"';}
function wp_body_open(){}
function esc_url($value){return htmlspecialchars($value, ENT_QUOTES);}
function esc_html($value){return htmlspecialchars($value, ENT_QUOTES);}
function esc_attr($value){return htmlspecialchars($value, ENT_QUOTES);}
function get_category_link($id){return 'https://example.test/category/'.$id;}
function get_posts($args){return array();}
function get_privacy_policy_url(){return '';}
function wp_date($format){return date($format);}
function wp_footer(){}
function get_the_title($page){return $page->post_title;}
require dirname(__DIR__).'/hirogaru-home/hirogaru-home.php';
$main_query = (object)array('posts'=>array('original-main-page'));
$posts =& $main_query->posts;
update_option('hsh_home_settings',array());
ob_start();require dirname(__DIR__).'/hirogaru-home/home-template.php';$html=ob_get_clean();
check($main_query->posts===array('original-main-page'),'template preserves WordPress global posts');
check(substr_count($html,'class="hsh-empty"')===3,'initial empty-state sections rendered');
update_option('hsh_home_settings',array('hide_empty'=>true));
ob_start();require dirname(__DIR__).'/hirogaru-home/home-template.php';$html=ob_get_clean();
check(strpos($html,'class="hsh-empty"')===false,'empty-state sections can be hidden');
check(strpos($html,'猫と過ごす日常。お気に入りの一杯。<br />')!==false,'default intro keeps line breaks');
echo "$tests tests passed.\n";
