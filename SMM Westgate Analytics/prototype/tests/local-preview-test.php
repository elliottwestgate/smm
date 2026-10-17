<?php
// Lightweight WordPress API doubles: validates local storage behavior, not a WP install.
define('ABSPATH', __DIR__);
class WP_Error { public $code; public function __construct($code, $message) {$this->code=$code;} }
$uid=1; $metadata=[];
function get_current_user_id() {global $uid;return $uid;}
function wp_parse_url($url) {return parse_url($url);}
function get_user_meta($id,$key,$single=true) {global $metadata;$rows=$metadata[$id][$key]??[];return $single ? ($rows[0]??'') : $rows;}
function add_user_meta($id,$key,$value,$unique=false) {global $metadata;if($unique && !empty($metadata[$id][$key]))return false;$metadata[$id][$key][]=$value;return true;}
function update_user_meta($id,$key,$value,$previous) {global $metadata;if(get_user_meta($id,$key,true)!==$previous)return false;$metadata[$id][$key]=[$value];return true;}
require __DIR__.'/../plugin/westgate-practicum-pro/local-preview.php';
function check($condition,$message) {if(!$condition)throw new Exception($message);echo 'PASS '.$message."\n";}
$p=['name'=>'Aladdin','website'=>'https://aladdincleans.com','timezone'=>'America/Chicago','start_date'=>'2026-10-18','end_date'=>'2027-01-16','primary_action'=>'Inquiry','email_provider'=>'constant_contact','revision'=>0];
$r=WGPP_Local_Preview::request('PUT','/v1/project',$p);check($r['saved']===true,'Campaign saves');
check(WGPP_Local_Preview::request('GET','/v1/project',null)['project']['email_provider']==='constant_contact','Constant Contact selection persists');
check(WGPP_Local_Preview::request('PUT','/v1/project',$p) instanceof WP_Error,'Stale revision rejected');
$p['revision']=1;$p['email_provider']='mailchimp';check(WGPP_Local_Preview::request('PUT','/v1/project',$p)['saved'],'Mailchimp selection saves');
$report=WGPP_Local_Preview::request('GET','/v1/report',null);check($report['panels']['Brand Awareness'][0]['value']===null,'Unavailable metrics stay null');
$entry=['week_start'=>'2026-10-18','experiment'=>'Test CTA','success_measure'=>'Inquiries'];check(WGPP_Local_Preview::request('POST','/v1/reflections',$entry)['saved'],'Reflection saves');
check(count(WGPP_Local_Preview::request('GET','/v1/reflections',null)['reflections'])===1,'Reflection reloads');
$entry['week_start']='2026-10-19';check(WGPP_Local_Preview::request('POST','/v1/reflections',$entry) instanceof WP_Error,'Non-Sunday rejected');
$uid=2;check(WGPP_Local_Preview::request('GET','/v1/project',null)['project']===null,'Other user cannot see campaign');
check(WGPP_Local_Preview::request('GET','/v1/reflections',null) instanceof WP_Error,'Other user cannot see reflections');
$p['revision']=0;$p['end_date']='2026-02-30';check(WGPP_Local_Preview::request('PUT','/v1/project',$p) instanceof WP_Error,'Invalid calendar date rejected');
$uid=0;check(WGPP_Local_Preview::request('GET','/v1/project',null) instanceof WP_Error,'Anonymous request rejected');
