<?php
require __DIR__.'/ga4-native-test.php';
function wp_safe_remote_get($url,$args){$GLOBALS['fb_gets'][]=[$url,$args];$r=array_shift($GLOBALS['fb_responses']);if(!$r){throw new Exception('Unexpected Facebook HTTP request');}return $r;}
$company=10;$uid=1;$role='owner';$fb_gets=[];$fb_responses=[];
$app=['id'=>'12345','secret'=>str_repeat('a',32),'config_id'=>'67890','version'=>'v25.0'];
$optionValues[WGPP_Facebook::APP]=WGPP_Facebook::encrypt($app,'application');
check(WGPP_Facebook::app()===$app,'Facebook application credentials round trip under dedicated encryption context');
try{WGPP_Facebook::decrypt(get_option(WGPP_Facebook::APP),'company:20:page');throw new Exception('Cross-context decryption allowed');}catch(RuntimeException $e){echo "PASS Facebook encrypted secrets cannot cross company/context boundaries\n";}
$permissions=['data'=>array_map(fn($p)=>['permission'=>$p,'status'=>'granted'],WGPP_Facebook::SCOPES)];WGPP_Facebook::validate_permissions($permissions);
try{WGPP_Facebook::validate_permissions(['data'=>[]]);throw new Exception('Missing scopes allowed');}catch(RuntimeException $e){echo "PASS missing Facebook read permissions rejected\n";}
check(in_array('business_management',WGPP_Facebook::SCOPES,true),'Meta Pages business-asset dependency is explicitly declared');
$empty=WGPP_Facebook::page_candidates(['data'=>[]]);check($empty['returned']===0 && $empty['pages']===[] && $empty['tasks']===[],'Empty Meta response is distinguished from locally rejected Page roles');
$permissions['data'][]=['permission'=>'pages_manage_posts','status'=>'granted'];try{WGPP_Facebook::validate_permissions($permissions);throw new Exception('Write scope allowed');}catch(RuntimeException $e){echo "PASS publishing permission is rejected by the read-only pilot\n";}
$state=str_repeat('b',64);$record=['company'=>10,'user'=>1,'session'=>hash('sha256','session-one'),'app'=>WGPP_Facebook::signature($app),'expires'=>time()+600];
WGPP_Facebook::validate_state($record,['id'=>10],$state,hash('sha256',$state),$app,1,'session-one');
foreach([[['id'=>20],1,'session-one'],[['id'=>10],2,'session-one'],[['id'=>10],1,'session-two']] as [$context,$user,$session]){try{WGPP_Facebook::validate_state($record,$context,$state,hash('sha256',$state),$app,$user,$session);throw new Exception('OAuth mismatch allowed');}catch(RuntimeException $e){echo "PASS Facebook OAuth rejects company/user/session mismatch\n";}}
try{WGPP_Facebook::validate_state($record,['id'=>10],$state,'processing:'.hash('sha256',$state),$app,1,'session-one');throw new Exception('OAuth replay allowed');}catch(RuntimeException $e){echo "PASS claimed Facebook OAuth state cannot be reused\n";}
$choices=WGPP_Facebook::page_candidates(['data'=>[['id'=>'11111','name'=>'Example Page','access_token'=>'page-token','tasks'=>['ANALYZE']],['id'=>'22222','name'=>'No analytics access','access_token'=>'other-token','tasks'=>['CREATE_CONTENT']]],'paging'=>['next'=>'https://untrusted.example/steal','cursors'=>['after'=>'next-cursor']]]);
check(count($choices['pages'])===1 && $choices['after']==='next-cursor','Page discovery retains analytics-capable Pages and extracts only pagination cursor');
try{WGPP_Facebook::get($app,'https://untrusted.example/steal');throw new Exception('Arbitrary URL allowed');}catch(RuntimeException $e){check(count($fb_gets)===0,'Facebook client rejects arbitrary URLs before HTTP');}
$page=['id'=>'11111','name'=>'Example Page','token'=>'test-page-token','app'=>WGPP_Facebook::signature($app),'grant'=>'grant-one'];
$meta[10][WGPP_Facebook::PAGE]=WGPP_Facebook::encrypt($page,'company:10:page');
$ranges=['current'=>['2026-09-01','2026-10-16'],'comparison'=>null];$chunks=WGPP_FB_Reports::chunks($ranges);
check(count($chunks)===2 && $chunks[0]['to']==='2026-09-30' && $chunks[1]['from']==='2026-10-01','Facebook reporting splits longer ranges into bounded 30-day chunks');
$ranges=['current'=>['2026-10-01','2026-10-02'],'comparison'=>null];
$make=fn($metric,$values)=>['status'=>200,'body'=>json_encode(['data'=>[['name'=>$metric,'period'=>'day','values'=>$values]]])];
$point=fn($date,$value)=>['end_time'=>$date.'T07:00:00+0000','value'=>$value];
$posts=[];foreach([2,9,1,20,3,7] as $n=>$views){$posts[]=['id'=>'11111_'.($n+1),'created_time'=>'2026-10-01T12:00:00+0000','message'=>'Post '.$n,'permalink_url'=>'https://www.facebook.com/11111/posts/'.($n+1),'insights'=>['data'=>[['name'=>'post_media_view','period'=>'lifetime','values'=>[['value'=>$views]]]]]];}
$fb_responses=[$make('page_media_view',[$point('2026-10-02',10),$point('2026-10-03',20)]),$make('page_follows',[$point('2026-10-01',100),$point('2026-10-02',110),$point('2026-10-03',105)]),['status'=>200,'body'=>json_encode(['data'=>$posts])]];
check(WGPP_FB_Reports::request(10,$ranges)['status']==='queued' && count($fb_gets)===1,'Facebook poll makes one bounded request');
WGPP_FB_Reports::request(10,$ranges);$result=WGPP_FB_Reports::request(10,$ranges);
check($result['status']==='complete' && $result['current']['views']['total']===30.0,'Additive daily views are summed across the exact range');
check($result['current']['followers']['total']===105.0 && $result['current']['followers']['net']===5.0,'Follower stock is not summed and net change includes the opening boundary');
check(count($result['posts']['top'])===5 && $result['posts']['top'][0]['views']===20.0 && $result['posts']['top'][4]['views']===2.0,'Top five posts rank all retrieved posts by lifetime views');
check($result['comparison']===null && $result['change']===null,'Compare None remains current-only in Facebook reporting');
WGPP_FB_Reports::request(10,$ranges);check(count($fb_gets)===3,'Completed Facebook report is served from company cache');
check(!str_contains($fb_gets[0][0],'test-page-token') && $fb_gets[0][1]['headers']['Authorization']==='Bearer test-page-token' && $fb_gets[0][1]['redirection']===0,'Report token stays out of URL and provider redirects are disabled');
$company=20;try{WGPP_FB_Reports::request(10,$ranges);throw new Exception('Cross-company cache allowed');}catch(RuntimeException $e){check(count($fb_gets)===3,'Other company cannot retrieve Facebook cache');}$company=10;
try{WGPP_FB_Reports::parse(['data'=>[]],'2026-10-01','2026-10-16');throw new Exception('Missing metric became zero');}catch(RuntimeException $e){echo "PASS unavailable Facebook metric is not converted to zero\n";}
check(WGPP_FB_Reports::summary([],['2026-10-01','2026-10-02'])['total']===null,'Incomplete daily views remain unavailable');
check(WGPP_FB_Reports::summary(['2026-10-02'=>['value'=>105]],['2026-10-01','2026-10-02'],true)['net']===null,'Missing follower opening boundary never becomes invented growth');
$fb_responses=[['status'=>400,'body'=>json_encode(['error'=>['code'=>100,'message'=>'not returned to user']])],$make('page_follows',[]),['status'=>200,'body'=>'{"data":[]}']];
$ranges=['current'=>['2026-08-01','2026-08-02'],'comparison'=>null];WGPP_FB_Reports::request(10,$ranges);WGPP_FB_Reports::request(10,$ranges);$partial=WGPP_FB_Reports::request(10,$ranges);
check($partial['status']==='complete' && $partial['current']['views']['total']===null && isset($partial['errors']['page_media_view']) && !str_contains($partial['errors']['page_media_view'],'not returned to user'),'Unavailable metric is isolated with sanitized diagnostics while other sections complete');
$timePaging=['data'=>[['name'=>WGPP_Facebook::METRIC,'period'=>'day','values'=>[['end_time'=>'2026-10-02T07:00:00+0000','value'=>5],['end_time'=>'2026-10-03T07:00:00+0000','value'=>7]]]],'paging'=>['next'=>'https://untrusted.example/not-followed']];
check(count(WGPP_FB_Reports::parse($timePaging,'2026-10-01','2026-10-02'))===2,'Complete requested daily coverage accepts Meta time navigation without following the URL');
try{WGPP_FB_Reports::parse($timePaging,'2026-10-01','2026-10-03');throw new Exception('Incomplete pagination allowed');}catch(RuntimeException $e){echo "PASS pagination with missing requested dates is rejected\n";}

$ranges=['current'=>['2026-07-01','2026-07-02'],'comparison'=>null];
$first=$posts[0];$first['created_time']='2026-07-01T12:00:00+0000';$winner=$posts[1];$winner['created_time']='2026-07-02T12:00:00+0000';$winner['insights']['data'][0]['values'][0]['value']=999;
$fb_responses=[$make('page_media_view',[]),$make('page_follows',[]),['status'=>200,'body'=>json_encode(['data'=>[$first],'paging'=>['next'=>'https://untrusted.example/secret','cursors'=>['after'=>'safe-cursor']]])],['status'=>200,'body'=>json_encode(['data'=>[$winner]])]];
WGPP_FB_Reports::request(10,$ranges);WGPP_FB_Reports::request(10,$ranges);$queued=WGPP_FB_Reports::request(10,$ranges);$ranked=WGPP_FB_Reports::request(10,$ranges);
check($queued['status']==='queued' && $ranked['posts']['count']===2 && $ranked['posts']['top'][0]['views']===999.0,'Post ranking includes later result pages before selecting the top five');
$last=$fb_gets[count($fb_gets)-1][0];check(str_starts_with($last,'https://graph.facebook.com/v25.0/11111/published_posts?') && str_contains($last,'after=safe-cursor') && !str_contains($last,'untrusted.example'),'Post pagination rebuilds a fixed reporting endpoint using only the cursor');
$unsafe=$first;$unsafe['permalink_url']='https://evil.example/phish';check(WGPP_FB_Reports::post_rows(['data'=>[$unsafe]],$ranges['current'])[$unsafe['id']]['url']==='','Post links reject non-Facebook URLs');

check(strlen(WGPP_FB_Reports::excerpt(str_repeat('😀',250)))===960,'Post excerpt preserves complete UTF-8 characters for safe JSON output');

check(count(array_intersect(array_column($result['posts']['top'],'id'),array_column($result['posts']['lower'],'id')))===0,'Lower-view sample never duplicates top-five posts');
check(str_contains($result['posts']['engagement_basis'],'not supplied'),'Post visibility is not mislabeled as engagement');
