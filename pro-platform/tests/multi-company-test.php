<?php
define('ABSPATH',__DIR__);define('DAY_IN_SECONDS',86400);
$uid=1;$admin=true;$posts=[];$meta=[];$um=[];$inactive=[];$paid=[];
function get_current_user_id(){return $GLOBALS['uid'];}
function current_user_can($cap){return $GLOBALS['admin'] && $cap==='manage_options';}
function get_userdata($id){return $id>0?(object)['ID'=>$id]:false;}
function get_post($id){return $GLOBALS['posts'][$id]??null;}
function get_posts($args){return array_keys($GLOBALS['posts']);}
function get_post_meta($id,$key,$single=true){return $GLOBALS['meta'][$id][$key]??'';}
function update_post_meta($id,$key,$value){$GLOBALS['meta'][$id][$key]=$value;return true;}
function get_user_meta($id,$key,$single=true){return $GLOBALS['um'][$id][$key]??0;}
function update_user_meta($id,$key,$value){$GLOBALS['um'][$id][$key]=$value;return true;}
function wp_insert_post($args,$errors){$id=count($GLOBALS['posts'])+100;$GLOBALS['posts'][$id]=(object)$args;return $id;}
function is_wp_error($v){return false;}
function apply_filters($name,$v,...$args){return $name==='wgpp_company_entitled'?WGPP_WishList::entitled(!in_array($args[0],$GLOBALS['inactive']),$args[0]):$v;}
function wp_doing_ajax(){return false;}
function wp_die($s,...$args){throw new RuntimeException($s);}
function home_url($s){return 'https://example.com'.$s;}
function admin_url($s){return home_url('/wp-admin/'.$s);}
function esc_url($s){return htmlspecialchars($s,ENT_QUOTES);}
function esc_attr($s){return htmlspecialchars((string)$s,ENT_QUOTES);}
function esc_html($s){return htmlspecialchars($s,ENT_QUOTES);}
function wp_nonce_field($s){echo '<input type="hidden" name="_wpnonce" value="nonce">';}
function wp_parse_url($s){return parse_url($s);}
function add_query_arg($key,$v,$url){$p=parse_url($url);parse_str($p['query']??'',$q);$q[$key]=$v;return ($p['scheme']??'https').'://'.($p['host']??'example.com').($p['path']??'').'?'.http_build_query($q).(isset($p['fragment'])?'#'.$p['fragment']:'');}
class WGPP_Native_Reviews{static function locked($key,$f){return $f();}}
require __DIR__.'/../plugin/westgate-practicum-pro/company.php';
function check($v,$m){if(!$v){throw new Exception($m);}echo "PASS $m\n";}
function rejects($f,$m){try{$f();}catch(RuntimeException $e){echo "PASS $m\n";return;}throw new Exception($m);}
foreach([10=>'Aladdin',20=>'V1Moto',30=>'Unrelated'] as $id=>$name){$posts[$id]=(object)['post_type'=>WGPP_Company::TYPE,'post_status'=>'private','post_title'=>$name,'post_author'=>1];}
$meta[10]['_wgpp_members']=[1=>'owner'];$meta[20]['_wgpp_members']=[1=>'analyst',2=>'owner'];$meta[30]['_wgpp_members']=[3=>'owner'];$um[1][WGPP_Company::ACTIVE]=10;
check(count(WGPP_Company::companies(1))===2,'Lists only companies with a real membership');
check(WGPP_Company::can('members'),'Owner role applies in Aladdin');
$_GET=['workspace'=>'20'];check(WGPP_Company::can('review')&&!WGPP_Company::can('members'),'Same login has Analyst rights only in V1Moto');
$um[1][WGPP_Company::ACTIVE]=10;check(WGPP_Company::context()['id']===20,'A pinned tab keeps V1Moto after another tab selects Aladdin');
foreach(['30','999','0','bad',['10']] as $id){$_GET=['workspace'=>$id];check(WGPP_Company::context()===null,'Unauthorized or malformed company selection fails closed');}
$_GET=[];$_POST=['workspace'=>'20','action'=>'wgpp_brand'];check(WGPP_Company::context()['id']===20,'Form submission stays pinned to original company');
$_POST=['action'=>'wgpp_brand'];rejects(fn()=>WGPP_Company::guard_request(),'Old unscoped forms are rejected for multi-company users');
$_POST=['workspace'=>'30','action'=>'wgpp_brand'];rejects(fn()=>WGPP_Company::guard_request(),'Unauthorized company request rejected');
$_POST=[];$_GET=['workspace'=>'20'];$inactive=[20];check(WGPP_Company::context()===null,'Cancelled company is blocked without affecting other memberships');$_GET=[];check(WGPP_Company::context()['id']===10,'Aladdin stays accessible');$inactive=[];
$_GET=['workspace'=>'10'];$html=WGPP_Company::decorate('<form method="post"><input name="action" value="wgpp_brand"></form><form class="wgpa-controls"></form><a href="https://example.com/pro-analytics/#review">Review</a>');
check(substr_count($html,'name="workspace" value="10"')===2 && str_contains($html,'workspace=10#review'),'Every company form and internal link pins the company');
$admin=false;rejects(fn()=>WGPP_Company::create('Free exploit',1,true),'Only administrators may grant a complimentary company');$admin=true;
$new=WGPP_Company::create('V1Moto Customer',1,true);check(WGPP_Company::for_user($new,1)['role']==='owner','Complimentary company belongs to the current owner');
check(get_post_meta($new,'_wgpp_billing_owner',true)==='' && get_post_meta($new,'_wgpp_complimentary',true)['granted_by']===1,'Free entitlement belongs to the company with an audit record');
$cycle=WGPP_WishList::billing_cycle(null,$new);check(is_array($cycle)&&strtotime($cycle['end'])>time(),'Complimentary reviews have a monthly allowance window');
check($meta[10]['_wgpp_members']===[1=>'owner'] && empty($meta[10]['_wgpp_complimentary']),'Adding free account preserves Aladdin membership and billing');
rejects(fn()=>WGPP_Company::create('V1Moto Customer',1,true),'Duplicate company creation is rejected');
rejects(fn()=>WGPP_Company::create('Subscription bypass',1,false),'One existing subscription cannot create unlimited companies');
$meta[10]['_wgpp_members']=[];$_GET=[];$um[1][WGPP_Company::ACTIVE]=10;check(WGPP_Company::context()!==null && WGPP_Company::context()['id']!==10,'Removed company pointer falls back only to another authorized workspace');

class CompanyRedirect extends RuntimeException {public $url;function __construct($url){$this->url=$url;}}
function check_admin_referer($action){}
function wp_safe_redirect($url){throw new CompanyRedirect($url);}
$meta[10]['_wgpp_members']=[1=>'owner'];
foreach(['workspace','report',''] as $from){$_GET=[];$_POST=['company_id'=>'10','destination'=>$from];try{WGPP_Company::switch_company();}catch(CompanyRedirect $r){check($r->url==='https://example.com/pro-analytics/?workspace=10#report','Open company opens the selected Report from '.$from);}}
