<?php
require __DIR__.'/../plugin/message-matrix-pro/includes/domain.php';
require __DIR__.'/../plugin/message-matrix-pro/includes/store.php';
require __DIR__.'/../plugin/message-matrix-pro/includes/api.php';
use WGMM\Store as S;
define('ARRAY_A','ARRAY_A');
$user=7;$admin=false;$active=true;$count=0;
function get_current_user_id(){global $user;return $user;}
function current_user_can($cap){global $admin;return $admin;}
function get_option($key,$default=null){return $key==='wgmm_settings'?['level_ids'=>['matrix_level']]:$default;}
function wlmapi_get_member($id){global $active;return ['success'=>true,'member'=>[['Levels'=>['matrix_level'=>['Active'=>$active&&$id===7?1:0]]]]];}
function apply_filters($k,$v,...$args){return $v;}
function wp_json_encode($v){return json_encode($v);}
function is_user_logged_in(){return get_current_user_id()>0;}
function wp_verify_nonce($v,$a){return $v==='valid';}
class WP_Error {function __construct(public $code,public $message,public $data){}}
class DB {
  public $prefix='wp_';public $cas=1;public $last='';
  function prepare($sql,...$args){return ['sql'=>$sql,'args'=>$args];}
  function get_row($q,$mode){return ['id'=>1,'title'=>'Company A','owner_id'=>7,'payer_id'=>7,'grant_type'=>'membership','revision'=>3,'approved_version'=>'','document'=>json_encode(WGMM\Domain::blank())];}
  function get_var($q){return ($q['args'][0]===1&&$q['args'][1]===7)?'owner':'';}
  function query($q){$this->last=$q;return $this->cas;}
}
$wpdb=new DB;
function check($v,$name){global $count;if(!$v)throw new Exception($name);++$count;}
function rejected($fn,$code,$name){try{$fn();}catch(RuntimeException $e){check($e->getCode()===$code,$name);return;}throw new Exception('Expected rejection '.$name);}
check(S::access(1,'plan')['writable'],'Entitled owner');
$user=8;rejected(fn()=>S::access(1),403,'Company data inaccessible to unrelated user');
$user=7;$active=false;check(S::access(1)['writable']===false,'Expired access read-only');rejected(fn()=>S::access(1,'plan'),403,'Expired write blocked');
$active=true;check(S::membership_active(8)===false,'Payer membership not borrowed');
$row=S::row(1);$wpdb->cas=0;rejected(fn()=>S::cas($row,$row['document'],''),409,'Concurrent stale save rejected');
$wpdb->cas=false;rejected(fn()=>S::cas($row,$row['document'],''),500,'DB failure not reported saved');
$wpdb->cas=1;S::cas($row,$row['document'],'');check(str_contains($wpdb->last['sql'],'AND revision=%d')&&$wpdb->last['args'][3]===1&&$wpdb->last['args'][4]===3,'CAS scopes workspace and revision');
$r=new class {public $nonce='valid';function get_header($key){return $this->nonce;}};
check(WGMM\API::permission($r)===true,'Valid signed-in REST request');$r->nonce='bad';check(WGMM\API::permission($r) instanceof WP_Error,'Invalid nonce blocked');$r->nonce='valid';$user=0;check(WGMM\API::permission($r) instanceof WP_Error,'Anonymous REST request blocked');
echo "$count access and concurrency checks passed (WordPress/database doubles)\n";
