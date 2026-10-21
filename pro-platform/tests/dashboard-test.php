<?php
// WordPress API doubles; actual plugin render and AJAX request handlers run below.
define('ABSPATH',__DIR__);
$uid=1;$company=10;$role='owner';$registered=[];$meta=[];$userMeta=[];
class StopResponse extends Exception {public $data;public $status;public function __construct($data,$status){$this->data=$data;$this->status=$status;}}
function add_action($name,$callback){global $registered;$registered[$name]=$callback;}
function add_filter(...$args){}
function add_shortcode($name,$callback){}
function is_user_logged_in(){global $uid;return $uid>0;}
function home_url($path=''){return 'https://example.com'.$path;}
function get_current_user_id(){global $uid;return $uid;}
function current_user_can($cap){global $uid;return $uid===1;}
function get_user_meta($user,$key,$single){global $company;return $key==='_wgpp_active_company'?$company:null;}
function get_posts($args){return [$GLOBALS['company']];}
function add_query_arg($key,$value,$url){return $url.(str_contains($url,'?')?'&':'?').urlencode($key).'='.urlencode((string)$value);}
function get_option($key){global $optionValues;return $optionValues[$key]??false;}
function absint($v){return abs((int)$v);}
function is_admin(){return $GLOBALS['testAdminSurface'] ?? true;}
function get_transient($key){return false;}
function delete_transient($key){}
function get_userdata($id){return (object)['ID'=>$id,'display_name'=>'Example owner','user_email'=>'member'.$id.'@example.com','user_pass'=>'test-hash'];}
function wp_get_current_user(){return get_userdata(get_current_user_id());}
function esc_textarea($v){return htmlspecialchars((string)$v,ENT_QUOTES);}
function get_post($id){return $id?(object)['post_type'=>'wgpp_company','post_status'=>'private','post_author'=>1,'post_title'=>'Example']:null;}
function get_post_meta($id,$key,$single){global $uid,$role,$meta;return $key==='_wgpp_members'?[$uid=>$role]:($meta[$id][$key]??null);}
$reviewReady=false;$reviewCalls=[];
function apply_filters($name,$value,...$args){
 global $reviewReady,$reviewCalls;
 if($name==='wgpp_review_service_ready')return $reviewReady;
 if($name==='wgpp_review_service'){$reviewCalls[]=[$args[0],$args[1],$args[2]];return ['reports'=>[]];}
 return $value;
}
function is_wp_error($v){return false;}
function wp_enqueue_style(...$args){}
function wp_enqueue_script(...$args){}
function plugins_url($path,$file){return 'https://example.com/plugins/'.$path;}
function admin_url($path){return 'https://example.com/wp-admin/'.$path;}
function wp_create_nonce($action){return 'test';}
function wp_json_encode($v){return json_encode($v);}
function esc_attr($v){return htmlspecialchars((string)$v,ENT_QUOTES);}
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES);}
function esc_url($v){return esc_attr($v);}
function wp_nonce_field($v){}
function sanitize_text_field($v){return strip_tags($v);}
function wp_unslash($v){return $v;}
function check_ajax_referer(...$args){}
function nocache_headers(){}
function wp_send_json_success($data){throw new StopResponse($data,200);}
function wp_send_json_error($data,$code){throw new StopResponse($data,$code);}
require __DIR__.'/../plugin/westgate-practicum-pro/westgate-practicum-pro.php';
function check($v,$message){if(!$v)throw new Exception($message);echo "PASS $message\n";}
$html=Westgate_Practicum_Pro::render();
check(str_contains($html,'Print / Save Snapshot') && str_contains($html,'data-profile'),'Dashboard renders controls and audience target');
check(isset($registered['wp_ajax_wgpp_snapshot']),'Authenticated analytics endpoint registered');
$row=['schema'=>'wgpp-snapshots-v1','snapshots'=>[['from'=>'2026-09-17','to'=>'2026-10-16','timezone'=>'America/Chicago','property'=>'123','company'=>'Example','website'=>'https://example.com','collected_at'=>'2026-10-17','metrics'=>['sessions'=>200,'users'=>150,'new_users'=>120,'returning_users'=>30,'form_events'=>5,'phone_events'=>5],'sources'=>[]]]];
$meta[10][WGPP_Analytics::META]=WGPP_Analytics::validate($row);
$_GET=['preset'=>'30','start'=>'','end'=>'2026-10-16','comparison'=>'prior'];
try{WGPP_Analytics::ajax();}catch(StopResponse $r){check($r->status===200 && $r->data['analysis']['metrics']['sessions']===200.0,'Company snapshot reaches dashboard response');}
$company=20;
try{WGPP_Analytics::ajax();}catch(StopResponse $r){check($r->data['analysis']===null,'Other company cannot retrieve imported snapshot');}
$company=10;$role='viewer';$uid=2;
try{WGPP_Analytics::ajax();}catch(StopResponse $r){check($r->status===200 && $r->data['analysis']!==null,'Viewer can explore shared company analytics');}
check(!WGPP_Company::can('connections') && !WGPP_Company::can('review'),'Viewer cannot manage connections or request reviews');
$role='revoked';
try{WGPP_Analytics::ajax();}catch(StopResponse $r){check($r->status===403,'Removed member denied dashboard response');}
