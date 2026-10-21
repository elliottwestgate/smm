<?php
require __DIR__.'/dashboard-test.php';
function wp_parse_url($url){return false;} // No public website fetch in this endpoint-isolation fixture.
$uid=1;$role='owner';$company=10;$reviewReady=false;
$_REQUEST=['operation'=>'list'];
try{WGPP_Reviews::ajax();}catch(StopResponse $r){check($r->status===503,'Disconnected review service fails closed');}
$reviewReady=true;$role='viewer';$uid=2;
$_REQUEST=['operation'=>'generate'];
try{WGPP_Reviews::ajax();}catch(StopResponse $r){check($r->status===403 && count($reviewCalls)===0,'Viewer cannot reach generator');}
$_REQUEST=['operation'=>'list'];
try{WGPP_Reviews::ajax();}catch(StopResponse $r){check($r->status===200 && $reviewCalls[0][1]['company_id']===10,'Viewer history request receives server company identity');}
$uid=1;$role='owner';$_REQUEST=['operation'=>'generate'];$_POST=['confirmed'=>'1','request_id'=>'0123456789abcdef','preset'=>'30','start'=>'','end'=>'2026-10-16','comparison'=>'prior'];$_SERVER['REQUEST_METHOD']='POST';
$count=count($reviewCalls);
try{WGPP_Reviews::ajax();}catch(StopResponse $r){check($r->status===422 && count($reviewCalls)===$count,'Missing comparison does not reach generation service');}
$prior=$row;$prior['snapshots'][0]['from']='2026-08-18';$prior['snapshots'][0]['to']='2026-09-16';
$meta[10][WGPP_Analytics::META]['snapshots'][]=WGPP_Analytics::validate($prior)['snapshots'][0];
$_POST['confirmed']='0';
try{WGPP_Reviews::ajax();}catch(StopResponse $r){check($r->status===422,'Explicit review confirmation required');}
$_POST['confirmed']='1';
try{WGPP_Reviews::ajax();}catch(StopResponse $r){
 $call=end($reviewCalls);
 check($r->status===200 && $call[2]['snapshot']['ranges']['current']===['2026-09-17','2026-10-16'],'Generator receives frozen selected reporting periods');
 check($call[1]['principal']===1 && $call[1]['role']==='owner','Review principal and role come from server context');
}
