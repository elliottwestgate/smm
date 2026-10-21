<?php
require __DIR__.'/ga4-native-test.php';
$googleStatus=200;$trendMode=true;$company=10;$role='viewer';
check(WGPP_Trends::dimension('2026-09-17','2026-10-16')==='date','30-day timeline uses daily intervals');
check(WGPP_Trends::dimension('2026-07-19','2026-10-16')==='isoYearIsoWeek','90-day timeline queries weekly users directly');
check(WGPP_Trends::dimension('2025-10-17','2026-10-16')==='yearMonth','Annual timeline uses months');
$b=WGPP_Trends::buckets('2025-12-29','2026-01-05','isoYearIsoWeek');
check(count($b)===2 && $b[202601]['from']==='2025-12-29' && $b[202602]['to']==='2026-01-05','ISO year boundary and partial weeks retain exact range');
$before=$httpCalls;for($i=0;$i<4;$i++){$result=WGPP_Trends::request(10,'2026-10-01','2026-10-16');}
check($result['status']==='complete' && $httpCalls===$before+3,'Timeline collects in three bounded requests and then serves cache');
check($result['points'][0]['users']===90.0 && $result['points'][0]['returning_users']===25.0 && $result['points'][0]['goal_rate']===10.0,'Timeline preserves interval users and calculates goals per 100 sessions');
check($result['points'][1]['sessions']===0 && $result['points'][1]['goal_rate']===null,'Missing complete daily rows are zero but zero-session rates remain undefined');
WGPP_Trends::request(10,'2026-10-01','2026-10-16');check($httpCalls===$before+3,'Reopening cached timeline does not call Google');
$company=20;try{WGPP_Trends::request(10,'2026-10-01','2026-10-16');throw new Exception('Cross-company access allowed');}catch(RuntimeException $e){check($httpCalls===$before+3,'Other company cannot load cached timeline');}
