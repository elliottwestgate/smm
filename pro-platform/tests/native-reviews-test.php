<?php
define('ABSPATH',__DIR__);
$journal=[];$resetAudit=[];$isAdmin=true;$cycleStart=time()-100;$scheduled=true;$evaluate=true;$role='owner';$company=10;
class WGPP_Company {
 public static function context(){global $role,$company;return ['id'=>$company,'role'=>$role];}
 public static function can($a){global $role;return in_array($role,['owner','analyst'],true);}
 public static function plan(){return ['reviews_monthly'=>4];}
}
class DB {
 public $prefix='wp_';
 public function prepare($query,...$args){return $query;}
 public function get_var($query){return 1;}
}
$wpdb=new DB();
function get_current_user_id(){return 1;}
function current_user_can($cap){return $GLOBALS['isAdmin'];}
function sanitize_text_field($v){return strip_tags($v);}
function get_post_meta($id,$key,$single){global $journal,$role,$resetAudit;if($key==='_wgpp_review_resets')return $resetAudit[$id]??[];return $key==='_wgpp_members'?[1=>$role]:($journal[$id]??[]);}
function update_post_meta($id,$key,$value){global $journal,$resetAudit;if($key==='_wgpp_review_resets'){$resetAudit[$id]=$value;}else{$journal[$id]=$value;}return true;}
function wp_cache_delete(...$args){}
function wp_schedule_single_event(...$args){global $scheduled;return $scheduled;}
function is_wp_error($v){return false;}
function get_post($id){return (object)['post_type'=>'wgpp_company','post_status'=>'private','post_author'=>1];}
function apply_filters($name,$default,...$args){
 global $evaluate;
 if($name==='wgpp_review_billing_cycle')return ['start'=>gmdate('c',$GLOBALS['cycleStart']),'end'=>gmdate('c',time()+86400)];
 if($name==='wgpp_evaluator_ready')return true;
 if($name==='wgpp_evaluate_snapshot')return $evaluate?['summary'=>'Sample assessment','findings'=>[],'actions'=>[]]:null;
 return $default;
}
require __DIR__.'/../plugin/westgate-practicum-pro/native-reviews.php';
function check($ok,$msg){if(!$ok)throw new Exception($msg);echo "PASS $msg\n";}
function call($op,$request=[]){global $company,$role;return WGPP_Native_Reviews::dispatch(null,$op,['company_id'=>$company,'principal'=>1,'role'=>$role],$request);}
$s=['ranges'=>['current'=>['2026-09-17','2026-10-16'],'comparison'=>['2026-08-18','2026-09-16']],'analysis'=>['metrics'=>['sessions'=>200]]];
$r=call('generate',['request_id'=>'first','snapshot'=>$s]);
check($r['status']==='queued' && call('preview')['remaining']===3,'WordPress queue reserves one company review');
check(call('generate',['request_id'=>'first','snapshot'=>$s])['status']==='queued' && call('preview')['remaining']===3,'Duplicate native request consumes once');
check(call('status',['report_id'=>'first'])['schema']==='wgpp-review-v1','Owner status poll progresses a queued report without relying on cron');
WGPP_Native_Reviews::process(10,'first');
check(call('get',['report_id'=>'first'])['snapshot']===$s && count(call('list')['reports'])===1,'WordPress worker saves original evidence and history');
$role='viewer';check(call('get',['report_id'=>'first'])['review']['summary']==='Sample assessment','Viewer reads completed native report');
try{call('generate',['request_id'=>'blocked','snapshot'=>$s]);throw new Exception('Viewer generated');}catch(InvalidArgumentException $e){echo "PASS native viewer generation denied\n";}
$role='owner';$evaluate=false;call('generate',['request_id'=>'failure','snapshot'=>$s]);WGPP_Native_Reviews::process(10,'failure');
check(call('preview')['remaining']===3,'Failed native evaluation releases allowance');
$evaluate=true;$scheduled=false;
try{call('generate',['request_id'=>'schedule-failure','snapshot'=>$s]);throw new Exception('Queue accepted failure');}catch(RuntimeException $e){check(call('preview')['remaining']===3,'Scheduling failure releases allowance');}
$scheduled=true;
foreach(['second','third','fourth'] as $id)call('generate',['request_id'=>$id,'snapshot'=>$s]);
try{call('generate',['request_id'=>'fifth','snapshot'=>$s]);throw new Exception('Quota exceeded');}catch(InvalidArgumentException $e){echo "PASS native company allowance stops fifth review\n";}
$company=20;check(call('list')['reports']===[],'Native history remains company-isolated');
try{call('get',['report_id'=>'first']);throw new Exception('Cross-company read');}catch(InvalidArgumentException $e){echo "PASS native cross-company report unavailable\n";}

$company=10;$isAdmin=false;
try{WGPP_Native_Reviews::reset_allowance(10,'Test');throw new Exception('Non-admin reset');}catch(InvalidArgumentException $e){echo "PASS owner without site-admin rights cannot reset allowance\n";}
$isAdmin=true;$before=call('get',['report_id'=>'first']);$r=WGPP_Native_Reviews::reset_allowance(10,'Development test');
check($r['remaining']===1 && $r['waived']===1,'Reset releases completed usage but still reserves three queued reviews');
check(call('get',['report_id'=>'first'])===$before,'Reset preserves the saved report and frozen evidence');
check($resetAudit[10][0]['by']===1 && $resetAudit[10][0]['reason']==='Development test','Reset records administrator, timestamp and reason');
foreach(['second','third','fourth'] as $id)WGPP_Native_Reviews::process(10,$id);
$r=WGPP_Native_Reviews::reset_allowance(10,'Quality verification');
check($r['remaining']===4 && count(call('list')['reports'])===4,'Admin restores all four reviews without deleting saved history');
check(call('preview')['remaining']===4,'Reset allowance is reflected by the actual generation preview');
check(WGPP_Native_Reviews::reset_allowance(10,'Repeat reset')['waived']===0,'Repeated reset does not double-credit completed usage');
$company=20;check(call('preview')['remaining']===4,'Other company allowance remains unchanged');

$company=10;$countBefore=count(call('list')['reports']);$r=WGPP_Native_Reviews::reset_allowance(10,'Keep one production review',1);
check($r['remaining']===3 && call('preview')['remaining']===3,'Admin can set exactly one completed review used and three available');
check(count(call('list')['reports'])===$countBefore,'Usage adjustment preserves every saved report');
check(WGPP_Native_Reviews::reset_allowance(10,'Repeat one',1)['remaining']===3,'Repeating a target usage adjustment is idempotent');
try{WGPP_Native_Reviews::reset_allowance(10,'Invalid target',5);throw new Exception('Accepted excess count');}catch(InvalidArgumentException $e){echo "PASS out-of-range count rejected\n";}
$remaining=call('preview')['remaining'];$countBefore=count(call('list')['reports']);$record=call('get',['report_id'=>'first']);
check(WGPP_Native_Reviews::manage_saved(10,['first'])===1 && count(call('list')['reports'])===$countBefore-1,'Trashed Review disappears from history');
try{call('get',['report_id'=>'first']);throw new Exception('Trash remains readable');}catch(InvalidArgumentException $e){echo "PASS trashed Review is unavailable by direct reference\n";}
check(call('preview')['remaining']===$remaining,'Trash preserves review allowance accounting');
WGPP_Native_Reviews::manage_saved(10,['first'],true);check(call('get',['report_id'=>'first'])===$record,'Restore recovers the exact saved Review');
try{WGPP_Native_Reviews::manage_saved(20,['first']);throw new Exception('Cross-company trash');}catch(InvalidArgumentException $e){echo "PASS cannot trash a Review from another company\n";}
try{WGPP_Native_Reviews::manage_saved(10,['first','missing']);throw new Exception('Partially trashed');}catch(InvalidArgumentException $e){check(call('get',['report_id'=>'first'])===$record,'Invalid selection leaves all Reviews unchanged');}
$isAdmin=false;try{WGPP_Native_Reviews::manage_saved(10,['first']);throw new Exception('Non-admin trash');}catch(InvalidArgumentException $e){echo "PASS non-admin cannot manage Review trash\n";}$isAdmin=true;
check(call('status',['report_id'=>'failure'])['message']==='Evaluation did not return a valid report.','Failed Review returns its application-owned cause');
check(!str_contains(WGPP_Native_Reviews::failure_message(new RuntimeException('Provider body with secret sk-private')),'sk-private'),'Unknown exceptions never expose provider bodies or secrets');
