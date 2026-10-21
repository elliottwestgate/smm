<?php
define('ABSPATH',__DIR__);
function wp_salt($s){return 'test-only-salt';}
function wp_json_encode($v){return json_encode($v);}
function wp_parse_url($v){return parse_url($v);}
$GLOBALS['meta']=[];$GLOBALS['options']=[];$GLOBALS['gets']=[];$GLOBALS['posts']=[];
function get_post_meta($id,$k,$single=true){return $GLOBALS['meta'][$id][$k]??'';}
function update_post_meta($id,$k,$v){$GLOBALS['meta'][$id][$k]=$v;return true;}
function delete_post_meta($id,$k){unset($GLOBALS['meta'][$id][$k]);return true;}
function get_option($k){return $GLOBALS['options'][$k]??false;}
function apply_filters($name,$value,...$args){return $value;}
function wp_schedule_single_event($time,$hook,$args,$error){$GLOBALS['scheduled'][]=[$hook,$args];return true;}
function is_wp_error($v){return false;}
function wp_remote_retrieve_response_code($v){return $v['code'];}
function wp_remote_retrieve_body($v){return json_encode($v['body']);}
function wp_safe_remote_get($url,$args){$GLOBALS['gets'][]=[$url,$args];return array_shift($GLOBALS['get_responses']);}
function wp_safe_remote_post($url,$args){$GLOBALS['posts'][]=[$url,$args];return array_shift($GLOBALS['post_responses']);}
final class WGPP_Company{static function can($cap){return $GLOBALS['allowed']??true;}static function context(){return ['id'=>1];}}
final class WGPP_Native_Reviews{static function locked($company,$f){return $f();}}
require __DIR__.'/../plugin/westgate-practicum-pro/google.php';
require __DIR__.'/../plugin/westgate-practicum-pro/constant-contact.php';
require __DIR__.'/../plugin/westgate-practicum-pro/analytics.php';
require __DIR__.'/../plugin/westgate-practicum-pro/cc-reports.php';
function check($ok,$label){if(!$ok)throw new Exception($label);echo "PASS $label\n";}
function rejected($f,$label){try{$f();}catch(RuntimeException $e){echo "PASS $label\n";return;}throw new Exception($label);}
$grant=['token_type'=>'Bearer','access_token'=>'access','refresh_token'=>'rotated','expires_in'=>3600,'scope'=>'campaign_data offline_access'];
check(WGPP_Constant_Contact::token_response($grant)===$grant,'Campaign/offline grant accepted');
rejected(fn()=>WGPP_Constant_Contact::token_response(array_replace($grant,['scope'=>'contact_data'])),'Wrong scopes rejected');
rejected(fn()=>WGPP_Constant_Contact::token_response(array_replace($grant,['refresh_token'=>''])),'Missing rotating token rejected');
$env=WGPP_Constant_Contact::encrypt($grant,'company:1');
rejected(fn()=>WGPP_Constant_Contact::decrypt($env,'company:2'),'Cross-company credential decryption rejected');
rejected(fn()=>WGPP_Google::decrypt($env,'company:1'),'Provider credential context isolated');
foreach(['https://evil.test/v3/reports/summary_reports/email_campaign_summaries','//evil.test/v3/reports/summary_reports/email_campaign_summaries','/v3/contacts','/v3/reports/summary_reports/email_campaign_summaries#fragment'] as $path)rejected(fn()=>WGPP_CC_Reports::next_path($path),'Pagination cannot redirect tokens: '.$path);
$raw=['campaign_id'=>'11111111-2222-3333-4444-555555555555','last_sent_date'=>'2026-10-01T02:00:00Z','unique_counts'=>['sends'=>'100','opens'=>'40','clicks'=>'8','bounces'=>'2','optouts'=>'1']];
$row=WGPP_CC_Reports::normalize($raw);
$summary=WGPP_CC_Reports::summarize([$row],'2026-09-30','2026-09-30','America/Chicago');
check($summary['metrics']['sends']===100&&$summary['metrics']['delivered']===98,'Cohort selects property-local send date and reconciles delivered');
check($summary['metrics']['open_rate']==40.0&&$summary['metrics']['click_rate']==8.0,'Rates use sends denominator');
check(WGPP_CC_Reports::summarize([$row],'2026-10-01','2026-10-01','America/Chicago')['metrics']['sends']===0,'Campaign outside local date range excluded');
check(WGPP_CC_Reports::summarize([],'2026-10-01','2026-10-01','America/Chicago')['metrics']['open_rate']===null,'No-send rates unavailable, not zero');
$bad=$raw;unset($bad['unique_counts']['clicks']);rejected(fn()=>WGPP_CC_Reports::normalize($bad),'Missing counts are unavailable, never invented zero');
$bad=$raw;$bad['unique_counts']['bounces']=101;rejected(fn()=>WGPP_CC_Reports::normalize($bad),'Impossible delivery counts rejected');
$GLOBALS['options'][WGPP_Constant_Contact::APP]=WGPP_Constant_Contact::encrypt(['id'=>'client-1234567890','secret'=>'secret-1234567890'],'application');
$GLOBALS['meta'][1][WGPP_Constant_Contact::TOKEN]=WGPP_Constant_Contact::encrypt(['access_token'=>'expired','refresh_token'=>'old','expires'=>0,'app_id'=>'client-1234567890'],'company:1');
$GLOBALS['post_responses']=[['code'=>200,'body'=>$grant]];
check(WGPP_Constant_Contact::token(1)==='access','Expired access token refreshes');
$stored=WGPP_Constant_Contact::decrypt(get_post_meta(1,WGPP_Constant_Contact::TOKEN),'company:1');
check($stored['refresh_token']==='rotated','Rotated refresh token persisted atomically');
check($GLOBALS['posts'][0][0]==='https://authz.constantcontact.com/oauth2/default/v1/token'&&$GLOBALS['posts'][0][1]['headers']['Authorization']==='Basic '.base64_encode('client-1234567890:secret-1234567890'),'Token exchange uses fixed endpoint and HTTP Basic');
check(in_array(WGPP_CC_Reports::request(2)['status'],['not_selected','not_connected'],true),'Company without grant never sees other company cache');
$GLOBALS['meta'][1]['_wgpp_cc_grant']='grant-one';
check(WGPP_CC_Reports::request(1)['status']==='queued','Connected company starts a reporting job');
$job=get_post_meta(1,WGPP_CC_Reports::CACHE);$next=WGPP_CC_Reports::PATH.'?next=page-two';
$GLOBALS['get_responses']=[['code'=>200,'body'=>['bulk_email_campaign_summaries'=>[$raw],'_links'=>['next'=>['href'=>$next]]]],['code'=>200,'body'=>['bulk_email_campaign_summaries'=>[$raw]]]];
WGPP_CC_Reports::collect(1,$job['signature']);
check(get_post_meta(1,WGPP_CC_Reports::CACHE)['status']==='queued','Pagination remains incomplete until final page');
WGPP_CC_Reports::collect(1,$job['signature']);$done=get_post_meta(1,WGPP_CC_Reports::CACHE);
check($done['status']==='complete'&&count($done['rows'])===1,'Final page completes and duplicate campaign IDs do not inflate totals');
$GLOBALS['meta'][1]['_wgpp_cc_grant']='replacement-grant';
WGPP_CC_Reports::collect(1,$job['signature']);
check(count($GLOBALS['gets'])===2,'Stale job cannot use replacement authorization');
check(WGPP_CC_Reports::request(1)['status']==='queued','Reauthorization invalidates prior cached report');
$job=get_post_meta(1,WGPP_CC_Reports::CACHE);
$GLOBALS['get_responses']=[['code'=>429,'body'=>[]]];WGPP_CC_Reports::collect(1,$job['signature']);
check(get_post_meta(1,WGPP_CC_Reports::CACHE)['status']==='failed','Rate limit failure does not publish partial data');

// An open dashboard can make progress without a cron runner.
$GLOBALS['meta'][1][WGPP_CC_Reports::CACHE]['updated']=0;
$GLOBALS['get_responses']=[['code'=>200,'body'=>['bulk_email_campaign_summaries'=>[$raw]]]];
check(WGPP_CC_Reports::progress(1)['status']==='complete','Dashboard progresses one bounded page without cron');
rejected(fn()=>WGPP_CC_Reports::progress(2),'Dashboard cannot progress another company');
$GLOBALS['allowed']=false;
rejected(fn()=>WGPP_CC_Reports::progress(1),'Revoked member cannot progress collection');
$GLOBALS['allowed']=true;
