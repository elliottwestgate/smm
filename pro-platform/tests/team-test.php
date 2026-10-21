<?php
define('ABSPATH',__DIR__);define('DAY_IN_SECONDS',86400);
$meta=[];$users=[];$um=[];
function get_post_meta($id,$key,$single=true){global $meta;return $meta[$id][$key]??[];}
function update_post_meta($id,$key,$v){global $meta;$meta[$id][$key]=$v;return true;}
function get_user_meta($id,$key,$single=true){global $um;return $um[$id][$key]??0;}
function update_user_meta($id,$key,$v){global $um;$um[$id][$key]=$v;return true;}
function get_user_by($key,$email){global $users;foreach($users as $u)if($u->user_email===$email)return $u;return false;}
function get_post($id){return $id===10?(object)['post_type'=>'wgpp_company','post_status'=>'private','post_author'=>1]:null;}
function apply_filters($name,$v,...$args){return $GLOBALS['entitled']??true;}
function wp_parse_url($v){return parse_url($v);}
function wp_unslash($v){return $v;}
function esc_url_raw($v){return $v;}
function get_posts($args){return array_keys($GLOBALS['meta']);}
require __DIR__.'/../plugin/westgate-practicum-pro/company.php';
function check($ok,$label){if(!$ok)throw new Exception($label);echo "PASS $label\n";}
function rejects($f,$label){try{$f();}catch(RuntimeException $e){echo "PASS $label\n";return;}throw new Exception($label);}
$meta[10]['_wgpp_members']=[1=>'owner'];
$token=str_repeat('a',64);$hash=hash('sha256',$token);
WGPP_Team::reserve(10,'member@example.com','viewer',$hash);
check(count(WGPP_Team::pending(10))===1,'Pending invitation reserves a seat');
rejects(fn()=>WGPP_Team::reserve(10,'member@example.com','viewer',$hash),'Immediate resend is rate limited');
$user=(object)['ID'=>2,'user_email'=>'member@example.com'];$users[]=$user;
rejects(fn()=>WGPP_Team::accept(10,str_repeat('b',64),$user),'Wrong invitation token rejected');
rejects(fn()=>WGPP_Team::accept(11,$token,$user),'Cross-company invitation rejected');
rejects(fn()=>WGPP_Team::accept(10,$token,(object)['ID'=>3,'user_email'=>'other@example.com']),'Invitation bound to authenticated email');
WGPP_Team::accept(10,$token,$user);
check($meta[10]['_wgpp_members'][2]==='viewer'&&$um[2][WGPP_Company::ACTIVE]===10,'Acceptance sets company role and active workspace');
check(WGPP_Team::pending(10)===[],'Accepted link consumed');
rejects(fn()=>WGPP_Team::accept(10,$token,$user),'Consumed link cannot replay');
$meta[10]['_wgpp_members']=[1=>'owner',2=>'viewer',3=>'analyst',4=>'viewer'];
WGPP_Team::reserve(10,'fifth@example.com','analyst',$hash);
rejects(fn()=>WGPP_Team::reserve(10,'sixth@example.com','viewer',$hash),'Active and pending seats share five-seat limit');
$meta[10][WGPP_Team::INVITES]['fifth@example.com']['expires']=time()-1;
check(WGPP_Team::pending(10)===[],'Expired invitations release reserved seats');
rejects(fn()=>WGPP_Team::reserve(10,'bad@example.com','owner',$hash),'Invitation cannot assign owner role');
$meta[10]['_wgpp_members']=[1=>'owner'];
WGPP_Team::reserve(10,'member@example.com','analyst',$hash);$um[2][WGPP_Company::ACTIVE]=20;
 $meta[20]['_wgpp_members']=[2=>'owner'];WGPP_Team::accept(10,$token,$user);check($meta[20]['_wgpp_members'][2]==='owner' && $meta[10]['_wgpp_members'][2]==='analyst','Invitation adds a second company without replacing existing membership');
$um[2][WGPP_Company::ACTIVE]=0;$GLOBALS['entitled']=false;
rejects(fn()=>WGPP_Team::accept(10,$token,$user),'Inactive company cannot accept invitations');
check(WGPP_Company::url('https://example.com/path')==='https://example.com/path','Brand website URL accepted');
foreach(['javascript:alert(1)','https://user:secret@example.com','//example.com'] as $url)rejects(fn()=>WGPP_Company::url($url),'Unsafe profile URL rejected');
