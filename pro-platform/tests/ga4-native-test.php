<?php
require __DIR__.'/dashboard-test.php';
class LockDB {public $prefix='wp_';public function prepare($sql,...$args){return $sql;}public function get_var($sql){return 1;}}
$wpdb=new LockDB();$role='owner';$uid=1;$company=10;$scheduled=[];$httpCalls=0;
function wp_salt($scheme){return 'unit-test-unique-key';}
function wp_cache_delete(...$args){}
function update_post_meta($id,$key,$value){global $meta;$meta[$id][$key]=$value;return true;}
function wp_schedule_single_event($time,$hook,$args,$error){global $scheduled;$scheduled[]=[$hook,$args];return empty($GLOBALS['rejectSchedule']);}
function wp_safe_remote_post($url,$args){
 global $httpCalls;
 $httpCalls++;$request=json_decode($args['body'],true);
 $dims=array_column($request['dimensions'],'name');$metrics=array_column($request['metrics'],'name');
 if(!empty($GLOBALS['trendMode'])){
  $bucket=$dims[0]==='date'?'20261001':($dims[0]==='yearMonth'?'202610':'202640');
  $rows=[];$pairs=count($dims)===1?[[[$bucket],['100','90','70']]]:($dims[1]==='newVsReturning'?[[[$bucket,'returning'],['25']]]:[[[$bucket,'form_submission'],['8']],[[$bucket,'phone_call'],['2']]]);
  foreach($pairs as [$ds,$ms]){$rows[]=['dimensionValues'=>array_map(fn($v)=>['value'=>$v],$ds),'metricValues'=>array_map(fn($v)=>['value'=>$v],$ms)];}
  return ['body'=>json_encode(['dimensionHeaders'=>array_map(fn($v)=>['name'=>$v],$dims),'metricHeaders'=>array_map(fn($v)=>['name'=>$v],$metrics),'rows'=>$rows,'rowCount'=>count($rows),'metadata'=>['timeZone'=>'America/Chicago']]),'status'=>200];
 }
 $values=[
 ''=>[[[],['100','90','70']]],
 'newVsReturning'=>[[['returning'],['25']]],
 'eventName'=>[[['form_submission'],['8']],[['phone_call'],['2']]],
 'sessionSourceMedium,sessionDefaultChannelGroup'=>[[['google / organic','Organic Search'],['100']]],
 'userAgeBracket'=>[[['unknown'],['90']],[['25-34'],['10']]],
 'userGender'=>[[['unknown'],['95']],[['female'],['5']]],
 'country'=>[[['United States'],['100']]],
 'city'=>[[['Example City'],['100']]],
 'deviceCategory'=>[[['desktop'],['60']],[['mobile'],['40']]],
 ];
 $rows=[];
 foreach($values[implode(',',$dims)] as [$ds,$ms]){$rows[]=['dimensionValues'=>array_map(fn($v)=>['value'=>$v],$ds),'metricValues'=>array_map(fn($v)=>['value'=>$v],$ms)];}
 return ['body'=>json_encode(['dimensionHeaders'=>array_map(fn($v)=>['name'=>$v],$dims),'metricHeaders'=>array_map(fn($v)=>['name'=>$v],$metrics),'rows'=>$rows,'rowCount'=>count($rows),'metadata'=>['timeZone'=>'America/Chicago']]),'status'=>($GLOBALS['googleStatus'] ?? 200)];
}
function wp_remote_retrieve_response_code($r){return $r['status'];}
function wp_remote_retrieve_body($r){return $r['body'];}
$optionValues[WGPP_Google::APP]=WGPP_Google::encrypt(['id'=>'test.apps.googleusercontent.com','secret'=>'fake-secret-only'],'application');
$meta[10][WGPP_Google::TOKEN]=WGPP_Google::encrypt(['access_token'=>'test-token','refresh_token'=>'test-refresh','expires'=>time()+3600,'app_id'=>'test.apps.googleusercontent.com'],'company:10');
$meta[10]['_wgpp_google_grant']='grant-one';
$meta[10][WGPP_GA4::CONFIG]=['property'=>'123','timezone'=>'America/Chicago','form_events'=>['form_submission'],'phone_events'=>['phone_call']];
$q=['company_id'=>10,'user_id'=>1,'from'=>'2026-09-17','to'=>'2026-10-16'];
check(WGPP_GA4::snapshot(null,$q)===null && count($scheduled)===1,'Native GA4 request queues collection instead of blocking dashboard');
$key=$scheduled[0][1][1];
for($i=0;$i<9;$i++)WGPP_GA4::collect(10,$key);
$snapshot=WGPP_GA4::snapshot(null,$q);
check($snapshot['metrics']['sessions']===100.0 && $snapshot['metrics']['returning_users']===25.0,'Native GA4 collection assembles traffic and users');
check($snapshot['metrics']['form_events']===8.0 && $snapshot['metrics']['phone_events']===2.0,'Native GA4 configured raw goal events');
check($snapshot['profile']['age'][0]['label']==='unknown','Native audience profile retained');
check($httpCalls===9,'Native collector makes one bounded request per step');
WGPP_GA4::snapshot(null,$q);check($httpCalls===9,'Fresh native cache avoids additional Google calls');
$validated=WGPP_Analytics::validate(['schema'=>'wgpp-snapshots-v1','snapshots'=>[$snapshot]]);
check(count($validated['snapshots'])===1,'Native result conforms to dashboard import contract');
$meta[10]['_wgpp_google_grant']='grant-two';
check(WGPP_GA4::snapshot(null,$q)===null,'Reauthorization invalidates old cached identity');
WGPP_GA4::collect(10,$key);
check(($meta[10][WGPP_GA4::CACHE][$key]['status']??'')!=='processing','Old collection job cannot resume against new authorization');
try{WGPP_GA4::identity('G-ABC123');throw new Exception('Measurement ID accepted');}catch(InvalidArgumentException $e){echo "PASS measurement ID rejected as property ID\n";}
try{WGPP_GA4::parse(['dimensionHeaders'=>[],'metricHeaders'=>[['name'=>'sessions']],'rows'=>[['dimensionValues'=>[],'metricValues'=>[['value'=>'NaN']]]]],[],['sessions']);throw new Exception('NaN accepted');}catch(RuntimeException $e){echo "PASS malformed native Google metric rejected\n";}
$q['from']='2026-07-19';WGPP_GA4::snapshot(null,$q);
$before=$httpCalls;
WGPP_GA4::progress(10,[[$q['from'],$q['to']]]);
check($httpCalls===$before+1,'Dashboard advances one Google report without running cron');
$company=20;
try{WGPP_GA4::progress(10,[[$q['from'],$q['to']]]);throw new Exception('Cross-company progress allowed');}catch(RuntimeException $e){check($httpCalls===$before+1,'Other company cannot advance Google collection');}
$company=10;$role='viewer';
for($i=0;$i<8;$i++)WGPP_GA4::progress(10,[[$q['from'],$q['to']]]);
check(WGPP_GA4::snapshot(null,$q)['metrics']['sessions']===100.0,'Viewer polling completes the selected range without cron');
$before=$httpCalls;WGPP_GA4::progress(10,[[$q['from'],$q['to']]]);
check($httpCalls===$before,'Completed range polling does not query Google again');

$rejectSchedule=true;$q['from']='2026-06-01';WGPP_GA4::snapshot(null,$q);
check(WGPP_GA4::status(10,$q['from'],$q['to'])==='queued','Rejected cron scheduling retains a runnable job');
for($i=0;$i<9;$i++)WGPP_GA4::progress(10,[[$q['from'],$q['to']]]);
check(WGPP_GA4::status(10,$q['from'],$q['to'])==='complete','Polling completes Google reports even when cron rejects duplicate events');

$q['from']='2026-05-01';WGPP_GA4::snapshot(null,$q);$googleStatus=503;
WGPP_GA4::progress(10,[[$q['from'],$q['to']]]);
check(WGPP_GA4::status(10,$q['from'],$q['to'])==='queued','Temporary Google failure remains retryable');
check(str_contains(WGPP_GA4::detail(10,$q['from'],$q['to']),'HTTP 503'),'Collection details identify the report and safe HTTP error');
$googleStatus=200;for($i=0;$i<9;$i++)WGPP_GA4::progress(10,[[$q['from'],$q['to']]]);
check(WGPP_GA4::status(10,$q['from'],$q['to'])==='complete','Temporary Google failure recovers without restarting the date range');
$q['from']='2026-04-01';WGPP_GA4::snapshot(null,$q);$googleStatus=403;
WGPP_GA4::progress(10,[[$q['from'],$q['to']]]);
check(WGPP_GA4::status(10,$q['from'],$q['to'])==='failed','Authorization error stops instead of repeatedly calling Google');
