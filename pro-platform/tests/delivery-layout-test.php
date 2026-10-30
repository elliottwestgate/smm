<?php
require __DIR__.'/report-delivery-test.php';
$snapshot=$payload['snapshot'];$snapshot['current']['metrics']['returning_users']=90;$snapshot['current']['metrics']['new_users']=600;
$previous=$snapshot['current'];$previous['metrics']['returning_users']=100;$previous['metrics']['new_users']=1000;
$snapshot['comparison']=$previous;$snapshot['ranges']['comparison']=['2026-09-03','2026-09-30'];$snapshot['analysis']=WGPP_Analytics::analyse($snapshot['current'],$previous);
$posts=[];for($i=0;$i<5;$i++){$posts[]=['date'=>'2026-10-12','views'=>1000-$i,'text'=>'A useful Brand message for the audience 🏍️ 🔥 '.str_repeat('content ',50)."\nAddress should not spill into the post table."];}
$channel=['status'=>'complete','current'=>['views'=>['total'=>5000],'followers'=>['total'=>2000,'net'=>-29]],'comparison'=>['views'=>['total'=>9000],'followers'=>['total'=>2029,'net'=>37]],'posts'=>['top'=>$posts,'complete'=>true]];
$snapshot['context']['channels']=['facebook'=>$channel,'instagram'=>$channel,'email'=>['status'=>'complete','current'=>['metrics'=>['sends'=>1000,'clicks'=>10,'click_rate'=>1]],'comparison'=>['metrics'=>['sends'=>1000,'clicks'=>5,'click_rate'=>.5]]]];
$snapshot['current']['profile']=['country'=>array_fill(0,5,['label'=>'A long country name for column fitting','sessions'=>12345]),'city'=>array_fill(0,5,['label'=>'An unusually long city name','sessions'=>2345]),'device'=>array_fill(0,5,['label'=>'mobile','sessions'=>1234]),'age'=>[['label'=>'35-44','sessions'=>123]],'gender'=>[['label'=>'female','sessions'=>123]]];
$sample=['snapshot'=>$snapshot,'created'=>'2026-10-29T15:00:00Z'];$bytes=WGPP_Delivery_PDF::build('Example Brand',$sample);
check(str_contains($bytes,'/Count 4'),'Report has four deliberate evidence pages');
check(substr_count($bytes,'/FontFile2')===2,'PDF embeds both fonts for stable rendering');
check(str_contains($bytes,'Returning users fell 10.0%')&&str_contains($bytes,'not growth in returning visitors'),'Ratio improvement is not described as returning-audience growth');
check(str_contains($bytes,'-66')&&!str_contains($bytes,'-178.4%'),'Signed follower comparison uses absolute change');
check(str_contains($bytes,'+0.50 pp'),'Click-rate differences use percentage points');
check(!str_contains($bytes,'Address should not spill')&&str_contains($bytes,'...'),'Post title is bounded, with explicit truncation');
check(!str_contains(WGPP_Delivery_PDF::plain('Ride 🏍️ 🔥'),'?' ),'Emoji cleanup prevents question-mark replacement noise');
check(str_contains($bytes,'35-44: 123')&&str_contains($bytes,'female: 123'),'Available age and gender evidence is preserved');
if(isset($argv[1])){file_put_contents($argv[1],$bytes);}
