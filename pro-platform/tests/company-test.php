<?php
define('ABSPATH',__DIR__);
$uid=1;$active=[1=>10,2=>10,3=>10,4=>20,5=>10];$members=[10=>[1=>'owner',2=>'analyst',3=>'viewer'],20=>[4=>'owner']];$entitled=true;
function get_current_user_id(){global $uid;return $uid;}
function get_user_meta($id,$key,$single){global $active;return $active[$id]??0;}
function get_post($id){return in_array($id,[10,20],true)?(object)['post_type'=>'wgpp_company','post_status'=>'private','post_title'=>'Company '.$id,'post_author'=>$id===10?1:4]:null;}
function get_post_meta($id,$key,$single){global $members;return $members[$id]??[];}
function apply_filters($name,$value,...$args){global $entitled;return $entitled;}
function get_posts($args){return array_keys($GLOBALS['members']);}
require __DIR__.'/../plugin/westgate-practicum-pro/company.php';
function check($ok,$msg){if(!$ok)throw new Exception($msg);echo "PASS $msg\n";}
check(WGPP_Company::can('connections') && WGPP_Company::can('members'),'Owner can manage company');
$uid=2;check(WGPP_Company::can('review') && !WGPP_Company::can('members') && !WGPP_Company::can('connections'),'Analyst can review but cannot manage');
$uid=3;check(WGPP_Company::can('view') && !WGPP_Company::can('review'),'Viewer restricted to reading');
$uid=4;check(WGPP_Company::context()['id']===20,'Other company resolves separately');
$uid=5;check(WGPP_Company::context()===null,'Active-company pointer alone grants no access');
$uid=1;$entitled=false;check(WGPP_Company::context()===null,'Membership adapter can block inactive subscription');
check(WGPP_Company::plan()['seats']===5 && WGPP_Company::plan()['reviews_monthly']===4,'Plan allowances match approved offer');
