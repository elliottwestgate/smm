<?php
require __DIR__.'/../plugin/message-matrix-pro/includes/domain.php';
require __DIR__.'/../plugin/message-matrix-pro/includes/store.php';
require __DIR__.'/../plugin/message-matrix-pro/includes/sources.php';
require __DIR__.'/../plugin/message-matrix-pro/includes/admin.php';
use WGMM\Admin as A;
use WGMM\Store as S;
define('ARRAY_A','ARRAY_A');
$count=0;$admin=true;$user=99;$options=['wgmm_settings'=>['level_ids'=>['1'],'seat_limit'=>5,'workspace_page_id'=>20]];$menus=[];$pages=[20=>(object)['ID'=>20,'post_type'=>'page','post_status'=>'publish','post_content'=>'[message_matrix_pro]']];
$people=[7=>(object)['ID'=>7,'display_name'=>'Owner','user_email'=>'owner@example.test'],8=>(object)['ID'=>8,'display_name'=>'Reviewer','user_email'=>'reviewer@example.test'],9=>(object)['ID'=>9,'display_name'=>'Other','user_email'=>'other@example.test'],99=>(object)['ID'=>99,'display_name'=>'Admin','user_email'=>'admin@example.test']];
function get_current_user_id(){global $user;return $user;}
function current_user_can($cap){global $admin;return $admin;}
function get_option($k,$default=null){global $options;return $options[$k]??$default;}
function update_option($k,$v,...$unused){global $options;$options[$k]=$v;return true;}
function wlmapi_get_member($id){return ['success'=>true,'member'=>[['Levels'=>['1'=>['Active'=>0]]]]];}
function get_userdata($id){global $people;return $people[$id]??false;}
function get_user_by($field,$value){global $people;foreach($people as $p){if($p->user_email===$value)return $p;}return false;}
function apply_filters($k,$v,...$args){return $v;}
function wp_json_encode($v){return json_encode($v);}
function is_email($v){return filter_var($v,FILTER_VALIDATE_EMAIL);}
function absint($v){return abs((int)$v);}
function get_posts($args){global $pages;return array_values($pages);}
function get_post($id){global $pages;return $pages[$id]??null;}
function has_shortcode($s,$tag){return str_contains($s,'['.$tag.']');}
function get_permalink($id){return 'https://example.test/workspace/'.$id.'/';}
function add_query_arg($key,$value=null,$url=null){if(is_array($key)){$url=$value;$args=$key;}else{$args=[$key=>$value];}return $url.(str_contains($url,'?')?'&':'?').http_build_query($args);}
function admin_url($s){return 'https://example.test/wp-admin/'.$s;}
function add_menu_page(...$a){global $menus;$menus['top']=$a;}
function add_submenu_page(...$a){global $menus;$menus['sub'][]=$a;}
function add_management_page(...$a){global $menus;$menus['legacy']=$a;}
function remove_submenu_page(...$a){global $menus;$menus['removed']=$a;}
class DB {
 public $prefix='wp_';public $revision=3;public $roles=[1=>[7=>'owner',8=>'reviewer'],2=>[8=>'viewer']];public $assets=[];
 function prepare($q,...$a){return ['sql'=>$q,'args'=>$a];}
 function get_row($q,$mode){return ['id'=>1,'title'=>'Company','owner_id'=>7,'payer_id'=>7,'grant_type'=>'membership','revision'=>$this->revision,'approved_version'=>'','updated_at'=>'2026-10-24 18:00:00','document'=>json_encode(array_replace(WGMM\Domain::blank(),['assets'=>$this->assets]))];}
 function get_var($q){return $this->roles[$q['args'][0]][$q['args'][1]]??'';}
 function get_results($q,$mode){if(str_contains($q['sql'],'versions'))return [];return array_map(fn($id,$role)=>['user_id'=>$id,'role'=>$role],array_keys($this->roles[$q['args'][0]]),array_values($this->roles[$q['args'][0]]));}
 function query($q){if(is_array($q)&&str_starts_with($q['sql'],'UPDATE ')){if($q['args'][4]!==$this->revision)return 0;$this->revision++;}return 1;}
 function replace($table,$values){$this->roles[$values['workspace_id']][$values['user_id']]=$values['role'];return 1;}
 function delete($table,$values){unset($this->roles[$values['workspace_id']][$values['user_id']]);return 1;}
}
$wpdb=new DB;
function check($v,$name){global $count;if(!$v)throw new Exception($name);++$count;}
function reject($fn,$code,$name){try{$fn();}catch(Throwable $e){check($code===0?$e instanceof InvalidArgumentException:$e->getCode()===$code,$name);return;}throw new Exception('Expected rejection: '.$name);}
A::menu();check($menus['top'][1]==='Social Media Magnet Pro'&&$menus['top'][2]==='manage_options','Admin top-level navigation');check(count($menus['sub'])===6,'Admin sections registered');check($menus['removed']===['tools.php','wgmm-settings'],'Tools duplicate removed');
check(A::workspace_url(1)==='https://example.test/workspace/20/?matrix_company=1','Company-scoped workspace link');
$options['wgmm_settings']['workspace_page_id']=0;check(A::workspace_url(2)==='https://example.test/workspace/20/?matrix_company=2','One shortcode page auto-detected');$pages[21]=(object)['ID'=>21,'post_type'=>'page','post_status'=>'publish','post_content'=>'[message_matrix_pro]'];check(A::workspace_url(1)==='','Ambiguous page requires explicit selection');$options['wgmm_settings']['workspace_page_id']=21;check(str_contains(A::workspace_url(1),'/21/'),'Explicit page resolves ambiguity');$pages[21]->post_content='Other content';check(A::workspace_url(1)==='','Invalid shortcode target blocked');$options['wgmm_settings']['workspace_page_id']=20;
$admin=false;reject(fn()=>A::change_team(1,3,'reviewer@example.test','strategist','save'),403,'Non-admin handler blocked');$user=7;reject(fn()=>S::team(1,3,'reviewer@example.test','strategist',false),403,'Expired ordinary company cannot change team');
$admin=true;$user=99;$out=A::change_team(1,3,'reviewer@example.test','strategist','save');check($wpdb->roles[1][8]==='strategist','Administrator changes role despite expired company access');check($wpdb->roles[2][8]==='viewer','Other company role untouched');check($wpdb->roles[1][7]==='owner','Owner preserved');check($out['revision']===4,'Revision increments');check($options['wgmm_admin_activity'][0]['details']['role']==='strategist','Activity records role and target');
reject(fn()=>A::change_team(1,3,'reviewer@example.test','reviewer','save'),409,'Stale revision rejected');check($wpdb->roles[1][8]==='strategist','Stale request cannot overwrite role');
reject(fn()=>A::change_team(1,4,'owner@example.test','reviewer','remove'),0,'Owner cannot be removed');reject(fn()=>A::change_team(1,4,'missing@example.test','reviewer','save'),0,'Missing user cannot be silently created');reject(fn()=>A::change_team(1,4,'reviewer@example.test','administrator','save'),0,'WordPress administrator cannot be assigned as company role');reject(fn()=>A::change_team(1,4,'reviewer@example.test','reviewer','anything'),0,'Unknown operation blocked');
$options['wgmm_settings']['seat_limit']=2;reject(fn()=>A::change_team(1,4,'other@example.test','reviewer','save'),0,'Seat limit respected');$wpdb->assets=[['assigned_user_id'=>8]];reject(fn()=>A::change_team(1,4,'reviewer@example.test','reviewer','remove'),0,'Assigned assets protect member removal');$wpdb->assets=[];
A::change_team(1,4,'reviewer@example.test','strategist','remove');check(!isset($wpdb->roles[1][8])&&$wpdb->roles[2][8]==='viewer','Removal only affects selected company');check($options['wgmm_admin_activity'][0]['action']==='company_access_removed','Removal recorded');
echo "$count admin authorization, role, concurrency and navigation checks passed (WordPress/database doubles)\n";
