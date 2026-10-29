<?php
define('ABSPATH',__DIR__);define('DAY_IN_SECONDS',86400);
$meta=[];$uid=1;$roles=[10=>[1=>'owner',2=>'analyst',3=>'viewer']];$events=[];$mail=[];$filters=[];$environment='production';$uuid=0;
function get_current_user_id(){return $GLOBALS['uid'];}function wp_set_current_user($v){$GLOBALS['uid']=$v;}
function get_post_meta($c,$k,$s=true){if($k==='_wgpp_members'){return $GLOBALS['roles'][$c]??[];}return $GLOBALS['meta'][$c][$k]??null;}
function update_post_meta($c,$k,$v){$GLOBALS['meta'][$c][$k]=$v;return true;}
function get_post($c){return $c===10?(object)['post_type'=>'wgpp_company','post_status'=>'private','post_author'=>1,'post_title'=>'Example Brand']:null;}
function get_userdata($v){return (object)['ID'=>$v];}
function apply_filters($key,$v,...$a){foreach($GLOBALS['filters'][$key]??[] as $f){$v=$f($v,...$a);}return $v;}
function add_filter($key,$f,...$a){$GLOBALS['filters'][$key][]=$f;}function remove_filter($key,$f,...$a){$GLOBALS['filters'][$key]=array_values(array_filter($GLOBALS['filters'][$key]??[],fn($v)=>$v!==$f));}
function add_action(...$a){add_filter(...$a);}function remove_action(...$a){remove_filter(...$a);}
function wp_login_url($u){return 'https://example.com/login/?redirect_to='.rawurlencode($u);}
function home_url($s=''){return 'https://example.com'.$s;}
function wp_get_environment_type(){return $GLOBALS['environment'];}
function wp_generate_uuid4(){return '11111111-1111-4111-8111-'.sprintf('%012d',++$GLOBALS['uuid']);}
function is_email($v){return filter_var($v,FILTER_VALIDATE_EMAIL);}
function is_wp_error($v){return false;}
function wp_next_scheduled($h,$a){return $GLOBALS['events'][$a[0]]??false;}
function wp_schedule_single_event($at,$h,$a,$e=false){$GLOBALS['events'][$a[0]]=$at;return true;}
function wp_unschedule_event($at,$h,$a){unset($GLOBALS['events'][$a[0]]);}
function sanitize_text_field($s){return strip_tags($s);}function sanitize_file_name($s){return preg_replace('/[^a-zA-Z0-9._-]/','-',$s);}
class WGPP_Native_Reviews {const META='reviews';static function locked($c,$f){return $f();}}
class WGPP_GA4 {static function config($c){return null;}static function progress(...$v){}}
class WGPP_Google {static function encrypt($a,$context){return json_encode([$context,$a]);}static function decrypt($a,$context){$v=json_decode($a,true);if($v[0]!==$context){throw new RuntimeException('Envelope scope');}return $v[1];}}
class WGPP_Account {const INVITATION_FROM='invitations@example.com';}
class WGPP_FB_Reports {static function request(...$a){return ['status'=>'not_connected'];}}
class WGPP_IG_Reports extends WGPP_FB_Reports {}
class WGPP_CC_Reports {static function progress($c){return ['status'=>'not_connected'];}}
class Mailer {public $attachment=false;function addStringAttachment($b,$n,...$rest){$this->attachment=str_starts_with($b,'%PDF-')&&str_ends_with($n,'.pdf');return $this->attachment;}function clearAttachments(){$GLOBALS['cleared']=true;}}
function wp_mail($to,$subject,$body,$headers){apply_filters('pre_wp_mail',null);$m=new Mailer;foreach($GLOBALS['filters']['phpmailer_init']??[] as $f){$f($m);}$GLOBALS['mail'][]=[$to,$subject,$body,$m->attachment];return true;}
require __DIR__.'/../plugin/westgate-practicum-pro/analytics.php';
require __DIR__.'/../plugin/westgate-practicum-pro/company.php';
require __DIR__.'/../plugin/westgate-practicum-pro/report-delivery.php';
require __DIR__.'/../plugin/westgate-practicum-pro/delivery-pdf.php';
function check($v,$m){if(!$v){throw new Exception($m);}echo "PASS $m\n";}
function rejects($f,$m){try{$f();}catch(InvalidArgumentException|RuntimeException $e){check(true,$m);return;}throw new Exception($m);}
check(WGPP_Report_Delivery::recipients('A@example.com, a@example.com; b@example.com')===['a@example.com','b@example.com'],'Deduplicates recipients without combining their emails');
foreach(["a@example.com\r\nBcc:evil@example.com",'name <a@example.com>','',str_repeat('a@example.com,',200)] as $v){rejects(fn()=>WGPP_Report_Delivery::recipients($v),'Rejects malformed or injected recipient list');}
rejects(fn()=>WGPP_Report_Delivery::access(10,3),'Viewers cannot email reports');rejects(fn()=>WGPP_Report_Delivery::access(20,1),'Other company cannot be emailed');rejects(fn()=>WGPP_Report_Delivery::access(10,2,true),'Analysts cannot schedule');
check(WGPP_Report_Delivery::access(10,2)['role']==='analyst','Analysts can send a report');
$plan=['frequency'=>'weekly','first_send'=>'2026-11-02','send_time'=>'08:00','comparison'=>'prior','recipients'=>'a@example.com'];
$s=WGPP_Report_Delivery::schedule($plan,strtotime('2026-10-29T12:00:00Z'));
check(WGPP_Report_Delivery::central($s['next'])->format('P')==='-06:00','Central delivery follows daylight-saving transition');
$r=WGPP_Report_Delivery::period($s,$s['next']);check($r['current']===['2026-10-26','2026-11-01']&&$r['comparison']===['2026-10-19','2026-10-25'],'Weekly Monday-Sunday and comparison boundaries');
$weekly=$s;$weekly['next']=strtotime('2026-10-26T08:00:00-05:00');check(WGPP_Report_Delivery::central(WGPP_Report_Delivery::next_date($weekly,$weekly['next']))->format('Y-m-d H:i P')==='2026-11-02 08:00 -06:00','Weekly delivery keeps local time across DST');
$plan['frequency']='monthly';$plan['first_send']='2028-03-01';$s=WGPP_Report_Delivery::schedule($plan,0);check(WGPP_Report_Delivery::period($s,$s['next'])['current']===['2028-02-01','2028-02-29'],'Monthly report covers leap-year calendar month');
$plan['first_send']='2026-11-29';rejects(fn()=>WGPP_Report_Delivery::schedule($plan,0),'Monthly day 29 rejected to prevent skipped month');
$plan['frequency']='four_weeks';$s=WGPP_Report_Delivery::schedule($plan,0);$r=WGPP_Report_Delivery::period($s,$s['next']);check($r['current']===['2026-11-01','2026-11-28'],'Four-week schedule is 28 days, not a calendar month');
check(WGPP_Report_Delivery::central(WGPP_Report_Delivery::next_date($s,$s['next']))->format('Y-m-d')==='2026-12-27','Four-week cadence remains anchored');
$plan['send_time']='25:00';rejects(fn()=>WGPP_Report_Delivery::schedule($plan,0),'Invalid send time rejected');
$m=['sessions'=>1000,'users'=>850,'new_users'=>700,'returning_users'=>150,'form_events'=>30,'phone_events'=>20];
$a=['from'=>'2026-10-01','to'=>'2026-10-28','timezone'=>'America/Chicago','property'=>'123','company'=>'Example Brand','website'=>'https://example.com','collected_at'=>'2026-10-29T12:00:00Z','metrics'=>$m,'sources'=>[['source_medium'=>'google / organic','channel'=>'Organic Search','sessions'=>1000]],'notes'=>[]];
$r=['current'=>['2026-10-01','2026-10-28'],'comparison'=>null];$payload=['snapshot'=>['current'=>$a,'comparison'=>null,'ranges'=>$r,'analysis'=>WGPP_Analytics::analyse($a,null)],'created'=>'2026-10-29T12:00:00Z'];
$pdf=WGPP_Delivery_PDF::build('Example Brand',$payload);check(str_starts_with($pdf,'%PDF-1.4')&&str_contains($pdf,'Brand Axis')&&str_contains($pdf,'/Title (Example Brand'),'Native PDF has selectable evidence and document title');
if(isset($argv[1])){file_put_contents($argv[1],$pdf);}
check(WGPP_Report_Delivery::mail('a@example.com','Report','Body',$pdf,'report.pdf'),'Mailer sends in-memory PDF');check($mail[0][3]&&$GLOBALS['cleared']&&!count($filters['phpmailer_init']),'Attachment and mail hooks are cleaned after send');
add_filter('pre_wp_mail',fn()=>true);rejects(fn()=>WGPP_Report_Delivery::mail('a@example.com','Report','Body',$pdf,'report.pdf'),'Preempting transport cannot silently send without PDF');$filters['pre_wp_mail']=[];
$meta[10]['reviews']=['review-one'=>['status'=>'completed','created_at'=>'2026-10-29T12:00:00Z','snapshot'=>$payload['snapshot'],'review'=>['summary'=>'Improve the Brand handoff.','actions'=>[],'findings'=>[]]]];
rejects(fn()=>WGPP_Report_Delivery::saved_review(20,'review-one'),'Review IDs do not cross company boundary');
$meta[10]['reviews']['trashed']=['status'=>'completed','trashed_at'=>'date'];rejects(fn()=>WGPP_Report_Delivery::saved_review(10,'trashed'),'Trashed Reviews cannot be emailed');
$job=['id'=>'job','principal'=>1,'origin'=>home_url('/'),'created'=>time(),'updated'=>time(),'status'=>'queued','attempts'=>0,'ranges'=>$r,'review'=>'review-one','note'=>'','recipients'=>['one@example.com'=>'queued','two@example.com'=>'queued']];
$meta[10][WGPP_Report_Delivery::META]=['schedules'=>[],'jobs'=>['job'=>$job]];$_POST=['workspace'=>'original'];$_GET=[];
WGPP_Report_Delivery::run(10);$done=WGPP_Report_Delivery::state(10)['jobs']['job'];check($done['status']==='accepted'&&$done['recipients']===['one@example.com'=>'accepted','two@example.com'=>'accepted'],'Queued saved Review sent separately to each recipient');check(str_contains(end($mail)[2],'Sign in to Pro Analytics:')&&str_contains(end($mail)[2],'workspace%3D10'),'Email includes login link to the correct protected company dashboard');check(!isset($done['pdf'])&&$_POST===['workspace'=>'original']&&$uid===1,'Private PDF removed and request context restored');$n=count($mail);WGPP_Report_Delivery::run(10);check(count($mail)===$n,'Accepted jobs never resend automatically');
$job['id']='revoked';$job['principal']=2;$roles[10][2]='viewer';$meta[10][WGPP_Report_Delivery::META]['jobs']['revoked']=$job;WGPP_Report_Delivery::run(10);check(WGPP_Report_Delivery::state(10)['jobs']['revoked']['status']==='failed'&&count($mail)===$n,'Revoked sender access stops queued delivery');
$job['id']='interrupted';$job['status']='sending';$job['updated']=time()-700;$meta[10][WGPP_Report_Delivery::META]['jobs']['interrupted']=$job;WGPP_Report_Delivery::run(10);check(WGPP_Report_Delivery::state(10)['jobs']['interrupted']['status']==='unknown'&&count($mail)===$n,'Interrupted sends are marked unknown and never duplicated');
$s=$weekly;$s['principal']=1;$s['origin']=home_url('/');$s['next']=time()-60;$s['enabled']=true;$meta[10][WGPP_Report_Delivery::META]=['schedules'=>['weekly'=>$s],'jobs'=>[]];$environment='staging';WGPP_Report_Delivery::run(10);check(!WGPP_Report_Delivery::state(10)['jobs'],'Staging does not send recurring reports');
$environment='production';$s['origin']='https://other.example/';$meta[10][WGPP_Report_Delivery::META]=['schedules'=>['weekly'=>$s],'jobs'=>[]];WGPP_Report_Delivery::run(10);check(!WGPP_Report_Delivery::state(10)['jobs'],'Copied production origin cannot send scheduled reports');
echo "Report Delivery checks complete.\n";
class DeliveryRedirect extends RuntimeException {}
function check_admin_referer($a){if(empty($GLOBALS['nonceOK'])){throw new RuntimeException('nonce');}}
function wp_unslash($s){return $s;}function sanitize_textarea_field($s){return strip_tags($s);}
function set_transient($k,$m,$n){$GLOBALS['notice']=$m;}
function add_query_arg($k,$v,$url){return $url.'?'.$k.'='.$v;}
function wp_safe_redirect($u){$GLOBALS['redirect']=$u;throw new DeliveryRedirect;}
function get_user_meta($u,$k,$s){return 10;}
$environment='production';$roles[10]=[1=>'owner',2=>'analyst',3=>'viewer'];$meta[10][WGPP_Report_Delivery::META]=['schedules'=>[],'jobs'=>[]];$uid=1;$_GET=[];$GLOBALS['nonceOK']=true;
$_POST=['workspace'=>'10','delivery_op'=>'send','review_id'=>'review-one','recipients'=>'a@example.com','confirm_recipients'=>'1','request_id'=>'valid-request-12345'];
try{WGPP_Report_Delivery::handle();}catch(DeliveryRedirect $e){}
// Saved Review is dated in October; date gate deliberately uses a historical fixture independent of test clock.
$meta[10]['reviews']['review-one']['snapshot']['ranges']['current']=['2020-01-01','2020-01-28'];
try{WGPP_Report_Delivery::handle();}catch(DeliveryRedirect $e){}
$count=count(WGPP_Report_Delivery::state(10)['jobs']);check($count===1&&str_ends_with($GLOBALS['redirect'],'#account-delivery'),'Send handler queues once and stays on Report Delivery');
try{WGPP_Report_Delivery::handle();}catch(DeliveryRedirect $e){}
check(count(WGPP_Report_Delivery::state(10)['jobs'])===$count,'Duplicate form submission is idempotent');
$_POST['request_id']='valid-request-67890';$_POST['confirm_recipients']='0';try{WGPP_Report_Delivery::handle();}catch(DeliveryRedirect $e){}
check(count(WGPP_Report_Delivery::state(10)['jobs'])===$count,'Send handler requires recipient confirmation');
$uid=3;$_POST['confirm_recipients']='1';try{WGPP_Report_Delivery::handle();}catch(DeliveryRedirect $e){}
check(count(WGPP_Report_Delivery::state(10)['jobs'])===$count,'Forged Viewer POST cannot queue company reports');
$uid=1;$_POST=['workspace'=>'10','delivery_op'=>'save','frequency'=>'weekly','first_send'=>'2099-11-02','send_time'=>'08:00','comparison'=>'prior','recipients'=>'owner@example.com'];
try{WGPP_Report_Delivery::handle();}catch(DeliveryRedirect $e){}
check(!WGPP_Report_Delivery::state(10)['schedules'],'Schedule handler requires recipient confirmation');
$_POST['confirm_recipients']='1';try{WGPP_Report_Delivery::handle();}catch(DeliveryRedirect $e){}
check(isset(WGPP_Report_Delivery::state(10)['schedules']['weekly']),'Owner can save weekly schedule');
$uid=2;$_POST['frequency']='monthly';try{WGPP_Report_Delivery::handle();}catch(DeliveryRedirect $e){}
check(!isset(WGPP_Report_Delivery::state(10)['schedules']['monthly']),'Analyst cannot forge schedule creation');
$uid=1;$GLOBALS['nonceOK']=false;rejects(fn()=>WGPP_Report_Delivery::handle(),'Nonce failure blocks request before mutation');$GLOBALS['nonceOK']=true;
function wp_enqueue_script(...$args){}
function plugins_url($path,$file){return 'https://example.com/plugins/'.$path;}
$uid=1;$s=$weekly;$s['next']=time()-60;$s['principal']=1;$s['origin']=home_url('/');$s['enabled']=true;$s['recipients']=['scheduled@example.com'];
$ranges=WGPP_Report_Delivery::period($s,$s['next']);$rows=[];foreach($ranges as $range){if($range){$row=$a;$row['from']=$range[0];$row['to']=$range[1];$rows[]=$row;}}
$meta[10][WGPP_Analytics::META]=WGPP_Analytics::validate(['schema'=>'wgpp-snapshots-v1','snapshots'=>$rows]);$meta[10][WGPP_Report_Delivery::META]=['schedules'=>['weekly'=>$s],'jobs'=>[]];
WGPP_Report_Delivery::run(10);$state=WGPP_Report_Delivery::state(10);$sent=end($state['jobs']);check($sent['status']==='accepted'&&$sent['ranges']===$ranges,'Due production schedule builds and sends exact-range analytics');
$n=count($mail);WGPP_Report_Delivery::run(10);check(count($mail)===$n&&$state['schedules']['weekly']['next']>time(),'Repeated cron pass cannot duplicate a scheduled period');
class DeliveryJSON extends Exception {public $data,$status;function __construct($d,$s){$this->data=$d;$this->status=$s;}}
function check_ajax_referer($action,$field){if(empty($GLOBALS['nonceOK'])){throw new RuntimeException('nonce');}}
function nocache_headers(){}function wp_send_json_success($d){throw new DeliveryJSON($d,200);}function wp_send_json_error($d,$s){throw new DeliveryJSON($d,$s);}
$uid=1;$_SERVER['REQUEST_METHOD']='POST';$_GET=[];$_POST=['workspace'=>'10','advance'=>'1'];$GLOBALS['nonceOK']=true;
$job['id']='ajax-queued';$job['principal']=1;$job['review']='review-one';$job['status']='queued';$job['created']=$job['updated']=time();$job['recipients']=['ajax@example.com'=>'queued'];
$meta[10][WGPP_Report_Delivery::META]=['schedules'=>[],'jobs'=>['ajax-queued'=>$job]];$n=count($mail);
try{WGPP_Report_Delivery::ajax();}catch(DeliveryJSON $r){check($r->status===200&&$r->data['jobs'][0]['status']==='accepted'&&count($mail)===$n+1,'Authenticated dashboard advances a previously queued send without cron');check(!isset($r->data['jobs'][0]['pdf'])&&$r->data['jobs'][0]['period']==='10/01/2026 to 10/28/2026','Progress response contains formatted dates and no encrypted report payload');}
try{WGPP_Report_Delivery::ajax();}catch(DeliveryJSON $r){check(count($mail)===$n+1,'Repeated progress polls cannot duplicate the email');}
$uid=3;try{WGPP_Report_Delivery::ajax();}catch(DeliveryJSON $r){check($r->status===403,'Viewer cannot advance or inspect delivery queue');}
$uid=1;$_POST['workspace']='20';try{WGPP_Report_Delivery::ajax();}catch(DeliveryJSON $r){check($r->status===403,'Progress cannot cross company boundary');}
$_POST['workspace']='10';$_SERVER['REQUEST_METHOD']='GET';try{WGPP_Report_Delivery::ajax();}catch(DeliveryJSON $r){check($r->status===403,'Progress mutation rejects GET requests');}
