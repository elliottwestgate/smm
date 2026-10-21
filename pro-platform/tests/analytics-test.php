<?php
define('ABSPATH',__DIR__);
function sanitize_text_field($s){return strip_tags($s);}
require __DIR__.'/../plugin/westgate-practicum-pro/analytics.php';
function check($ok,$msg){if(!$ok)throw new Exception($msg);echo "PASS $msg\n";}
$r=WGPP_Analytics::ranges('30','2026-10-16','','prior');
check($r['current']===['2026-09-17','2026-10-16'] && $r['comparison']===['2026-08-18','2026-09-16'],'Inclusive prior period');
$r=WGPP_Analytics::ranges('custom','2024-02-29','2024-02-01','year');
check($r['comparison']===['2023-02-01','2023-02-28'],'Leap-year comparison clamps correctly');
try{WGPP_Analytics::date('2026-02-30');throw new Exception('Accepted invalid date');}catch(InvalidArgumentException $e){echo "PASS invalid date rejected\n";}
check(WGPP_Analytics::percent(10,0)===null,'Zero baseline does not invent percent');
$a=['metrics'=>['sessions'=>200,'users'=>170,'new_users'=>150,'returning_users'=>20,'form_events'=>5,'phone_events'=>5],'sources'=>[['source_medium'=>'facebook / social','channel'=>'Organic Social','sessions'=>5],['source_medium'=>'facebook / cpc','channel'=>'Paid Social','sessions'=>7]]];
$b=['metrics'=>['sessions'=>100,'users'=>90,'new_users'=>70,'returning_users'=>20,'form_events'=>4,'phone_events'=>6],'sources'=>[]];
$x=WGPP_Analytics::analyse($a,$b);
check($x['goal_rate']===5.0 && $x['prior_goal_rate']===10.0 && $x['goal_change']===-50.0,'Goal events per 100 sessions');
check(abs($x['vertical']-100)<.001 && $x['horizontal']<0,'Traffic up and audience left calculated independently');
check(count($x['sources'])===2,'Paid and organic stay separate');
$x=WGPP_Analytics::analyse($a,null);
check($x['horizontal']===null && $x['vertical']===null && $x['goal_change']===null,'Missing comparison stays unavailable');
$sample=['from'=>'2026-09-17','to'=>'2026-10-16','timezone'=>'America/Chicago','property'=>'test','company'=>'Test','website'=>'https://example.com','collected_at'=>'2026-10-17','metrics'=>$a['metrics'],'sources'=>$a['sources']];
$valid=WGPP_Analytics::validate(['schema'=>'wgpp-snapshots-v1','snapshots'=>[$sample]]);
check(count($valid['snapshots'])===1,'Provider-neutral snapshot validates');
$sample['metrics']['sessions']=-1;
try{WGPP_Analytics::validate(['schema'=>'wgpp-snapshots-v1','snapshots'=>[$sample]]);throw new Exception('Accepted negative metric');}catch(InvalidArgumentException $e){echo "PASS negative metric rejected\n";}

$sample['metrics']['sessions']=200;
$sample['profile']=['age'=>[['label'=>'25–34','sessions'=>12]],'gender'=>[['label'=>'unknown','sessions'=>200]],'device'=>[['label'=>'mobile','sessions'=>100]]];
$v=WGPP_Analytics::validate(['schema'=>'wgpp-snapshots-v1','snapshots'=>[$sample]]);
check($v['snapshots'][0]['profile']['age'][0]['sessions']===12.0,'Audience sessions retained');
check($v['snapshots'][0]['profile']['city']===null,'Missing audience dimension remains unavailable');
$sample['profile']['age'][0]['sessions']=-3;
try{WGPP_Analytics::validate(['schema'=>'wgpp-snapshots-v1','snapshots'=>[$sample]]);throw new Exception('Accepted invalid profile');}catch(InvalidArgumentException $e){echo "PASS invalid audience counts rejected\n";}
$rows=[];foreach([100,80,60,30,10] as $i=>$n){$rows[]=['name'=>'Source '.$i,'current'=>$n,'comparison'=>$n*2,'evidence'=>[]];}
$summary=WGPP_Analytics::source_summary($rows);
check(count($summary)===4 && $summary[3]['current']===40 && $summary[3]['comparison']===80 && $summary[3]['change']===-50.0,'Top three plus Other preserves period totals and computes aggregate change');
$rows[4]['comparison']=null;$summary=WGPP_Analytics::source_summary($rows);
check($summary[3]['comparison']===null && $summary[3]['change']===null,'Other sources preserves missing comparison');
