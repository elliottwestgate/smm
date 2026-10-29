<?php
require __DIR__.'/report-delivery-test.php';
$uid=1;$_GET=[];$_SERVER['REQUEST_METHOD']='POST';$GLOBALS['nonceOK']=true;$roles[10]=[1=>'owner',2=>'analyst',3=>'viewer'];
function delivery_action($op,$values=[]){$_POST=array_merge(['workspace'=>'10','delivery_op'=>$op],$values);try{WGPP_Report_Delivery::handle();}catch(DeliveryRedirect $e){}}
$history=[];$base=$job;$base['review']='review-one';$base['principal']=1;$base['recipients']=['test@example.com'=>'accepted'];
for($i=0;$i<8;$i++){$j=$base;$j['id']='history-'.$i;$j['status']='accepted';$j['created']=$j['updated']=time()-100+$i;$j['request_id']='history-request-'.$i;$history[$j['id']]=$j;}
$pending=$base;$pending['id']='pending';$pending['status']='queued';$pending['recipients']=['test@example.com'=>'queued'];$pending['request_id']='pending-request-id';$history['pending']=$pending;
$meta[10][WGPP_Report_Delivery::META]=['schedules'=>[],'jobs'=>$history];$state=WGPP_Report_Delivery::state(10);
check(count($state['jobs'])===6&&!isset($state['jobs']['history-0'])&&isset($state['jobs']['history-7'],$state['jobs']['pending']),'History keeps the five most recent finished records plus pending work');
check(count($state['requests'])===9,'Pruned histories retain only short-lived duplicate-prevention hashes');
$n=count($mail);delivery_action('delete',['job_id'=>'history-7']);$state=WGPP_Report_Delivery::state(10);
check(!isset($state['jobs']['history-7'])&&isset($state['jobs']['pending'])&&count($mail)===$n,'Owner deletes only the selected finished history without sending mail');
check(isset($state['requests'][hash('sha256','history-request-7')]),'Deleting history preserves duplicate prevention');
delivery_action('delete',['job_id'=>'pending']);check(isset(WGPP_Report_Delivery::state(10)['jobs']['pending']),'Pending jobs cannot be deleted');
$uid=2;delivery_action('delete',['job_id'=>'history-6']);check(isset(WGPP_Report_Delivery::state(10)['jobs']['history-6']),'Analyst cannot delete company delivery history');$uid=1;
delivery_action('cancel',['job_id'=>'pending']);$state=WGPP_Report_Delivery::state(10);
check($state['jobs']['pending']['status']==='cancelled'&&$state['jobs']['pending']['recipients']['test@example.com']==='not_sent','Owner cancels a queued delivery without sending');
// Simulate an in-flight preparation worker resuming after cancellation won the lock.
$race=$base;$race['id']='race';$race['status']='processing';$race['lease']='old-worker';$race['recipients']=['test@example.com'=>'queued'];$race['pdf']=WGPP_Google::encrypt(['bytes'=>base64_encode($pdf),'title'=>'Pro Analytics Report'],'delivery:10:race');
$meta[10][WGPP_Report_Delivery::META]=['schedules'=>[],'jobs'=>['race'=>$race]];
delivery_action('cancel',['job_id'=>'race']);$process=new ReflectionMethod(WGPP_Report_Delivery::class,'process');$process->invoke(null,10,$race);
$state=WGPP_Report_Delivery::state(10);check($state['jobs']['race']['status']==='cancelled'&&!isset($state['jobs']['race']['pdf'],$state['jobs']['race']['lease'])&&count($mail)===$n,'Cancellation invalidates an active preparation worker before any mail can send');
$sending=$base;$sending['id']='sending';$sending['status']='sending';$sending['recipients']=['test@example.com'=>'sending'];$meta[10][WGPP_Report_Delivery::META]['jobs']['sending']=$sending;
delivery_action('cancel',['job_id'=>'sending']);check(WGPP_Report_Delivery::state(10)['jobs']['sending']['status']==='sending','An email already sending cannot be recalled');
$weeklyJob=$pending;$weeklyJob['id']='weekly-job';$weeklyJob['schedule']='weekly';$monthlyJob=$pending;$monthlyJob['id']='monthly-job';$monthlyJob['schedule']='monthly';
$meta[10][WGPP_Report_Delivery::META]=['schedules'=>['weekly'=>['enabled'=>true],'monthly'=>['enabled'=>true]],'jobs'=>['weekly-job'=>$weeklyJob,'monthly-job'=>$monthlyJob]];
delivery_action('cancel_schedule',['frequency'=>'weekly']);$state=WGPP_Report_Delivery::state(10);
check(!isset($state['schedules']['weekly'])&&isset($state['schedules']['monthly'])&&$state['jobs']['weekly-job']['status']==='cancelled'&&$state['jobs']['monthly-job']['status']==='queued','Canceling a schedule stops its pending jobs and leaves other schedules unchanged');
// A deleted history cannot reopen a recent confirmed form request.
$meta[10][WGPP_Report_Delivery::META]=['schedules'=>[],'jobs'=>[],'requests'=>[hash('sha256','confirmed-request-id')=>time()]];
delivery_action('send',['request_id'=>'confirmed-request-id','review_id'=>'review-one','recipients'=>'test@example.com','confirm_recipients'=>'1']);check(!WGPP_Report_Delivery::state(10)['jobs'],'Replaying a deleted delivery request cannot enqueue a duplicate');
$receipts=[];for($i=0;$i<20;$i++){$receipts[hash('sha256','rate-'.$i)]=time();}$meta[10][WGPP_Report_Delivery::META]['requests']=$receipts;
delivery_action('send',['request_id'=>'new-confirmed-request-id','review_id'=>'review-one','recipients'=>'test@example.com','confirm_recipients'=>'1']);check(!WGPP_Report_Delivery::state(10)['jobs'],'Deleting history cannot bypass the company hourly send limit');
