<?php
define('ABSPATH',__DIR__);require __DIR__.'/../plugin/westgate-practicum-pro/company.php';
function check($v,$m){if(!$v)throw new Exception($m);echo "PASS $m\n";}
function member($flags=[]){return ['success'=>1,'member'=>[['Levels'=>['1792352584'=>(object)array_merge(['Level_ID'=>'1792352584','Active'=>1,'Cancelled'=>false,'Pending'=>false,'UnConfirmed'=>false,'Expired'=>false],$flags)]]]];}
check(WGPP_WishList::active(member()),'Active paid Pro level grants payer entitlement');
foreach(['Cancelled','Pending','UnConfirmed','Expired'] as $flag){check(!WGPP_WishList::active(member([$flag=>1])),'WishList '.$flag.' blocks every company seat');}
check(!WGPP_WishList::active(member(['Level_ID'=>'1531866216'])),'Course-only level does not grant a Pro company');
check(!WGPP_WishList::active(['success'=>0]),'Unavailable membership response fails closed');
check(!WGPP_WishList::active(member(['Active'=>'false'])),'Malformed active status is rejected');
$anchor=strtotime('2024-01-31T10:00:00-06:00');$window=WGPP_WishList::window($anchor,strtotime('2024-03-30T10:00:00-05:00'));
check($window['start']==='2024-02-29T10:00:00-06:00' && $window['end']==='2024-03-31T10:00:00-05:00','Monthly allowance clamps leap day without drifting the original anniversary');
$window=WGPP_WishList::window($anchor,strtotime('2024-04-30T10:00:00-05:00'));
check($window['start']==='2024-04-30T10:00:00-05:00' && $window['end']==='2024-05-31T10:00:00-05:00','Annual plans retain monthly windows and preserve day 31');
check(WGPP_WishList::window(null,time())===null,'Missing subscription anchor does not invent billing dates');
$apiCalls=[];$fixtures=[101=>member(['Timestamp'=>strtotime('2024-01-31T10:00:00-06:00')]),102=>member(['Cancelled'=>1])];
function wlmapi_get_member($id){global $apiCalls,$fixtures;$apiCalls[$id]=($apiCalls[$id] ?? 0)+1;return $fixtures[$id] ?? ['success'=>0];}
function get_post_meta($company,$key,$single){return [10=>101,20=>102][$company] ?? 0;}
check(WGPP_WishList::entitled(true,10),'Invited seats inherit the server-recorded payer entitlement');
check(!WGPP_WishList::entitled(true,20),'Cancelled payer blocks the company regardless of teammate membership');
check(!WGPP_WishList::entitled(false,10),'Membership adapter cannot override another access denial');
check(WGPP_WishList::entitled(true,30),'Explicit preview workspace remains separate from paid companies');
check(WGPP_WishList::billing_cycle(null,10)!==null && $apiCalls[101]===1,'Company allowance and access share one membership lookup per request');
check(WGPP_WishList::billing_cycle(null,20)===false,'Inactive subscription cannot fall back to the preview allowance');
