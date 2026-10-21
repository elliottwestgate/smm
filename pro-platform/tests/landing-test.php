<?php
define('ABSPATH',__DIR__);
$active=[];$members=[];$posts=[];$paid=[];$entitled=true;$uid=0;
function add_action(...$a){} function add_filter(...$a){} function add_shortcode(...$a){}
function home_url($p=''){return 'https://example.com'.$p;}
function get_user_meta($u,$k,$s){return $GLOBALS['active'][$u]??0;}
function get_post($id){return $GLOBALS['posts'][$id]??null;}
function get_post_meta($id,$k,$s){return $GLOBALS['members'][$id]??[];}
function apply_filters($n,$v,...$a){return $GLOBALS['entitled'];}
function is_user_logged_in(){return $GLOBALS['uid']>0;}
function esc_url($s){return htmlspecialchars($s,ENT_QUOTES);}
function wp_login_url($s){return 'https://example.com/wp-login.php?redirect_to='.urlencode($s);}
class WGPP_Company { static function product_logo(){return '<span>Pro Analytics</span>';} const ACTIVE='active';const TYPE='wgpp_company'; static function for_user($id,$user){$p=get_post($id);$r=get_post_meta($id,'_wgpp_members',true)[$user]??null;return $p && $p->post_status==='private' && in_array($r,['owner','analyst','viewer'],true) && apply_filters('entitled',true)?['id'=>$id,'name'=>$p->post_title,'role'=>$r]:null;} static function companies($user){return [];} static function decorate($html,$destination){return $html;} static function context(){return WGPP_Landing::company_for($GLOBALS['uid']);} static function brand($id){return ['website'=>'https://company.example'];} static function render(){return '<form>Create a Pro workspace</form>';} }
class WGPP_Mailchimp {static function provider($id){return '';}static function label($id){return 'Choose your provider';}}
class WGPP_GA4 {static function config($id){return ['property'=>'123'];}}
class WGPP_Native_Reviews {static function dispatch(...$a){return ['reports'=>[]];}}
function get_current_user_id(){return $GLOBALS['uid'];}
function wp_get_current_user(){return (object)['first_name'=>'Sam','display_name'=>'Sam'];}
function wp_enqueue_style(...$a){} function plugins_url($p,$f){return '/plugin/'.$p;}
function esc_html($s){return htmlspecialchars($s,ENT_QUOTES);}
class WGPP_WishList {static function payer_active($u){return !empty($GLOBALS['paid'][$u]);}}
require __DIR__.'/../plugin/westgate-practicum-pro/portal.php';
function check($v,$m){if(!$v){throw new Exception($m);}echo "PASS $m\n";}
$posts[10]=(object)['post_type'=>'wgpp_company','post_status'=>'private','post_author'=>1,'post_title'=>'Company'];
foreach(['owner','analyst','viewer'] as $i=>$role){$u=$i+1;$active[$u]=10;$members[10][$u]=$role;check(WGPP_Landing::login_redirect('https://example.com/dashboard/','',(object)['ID'=>$u])===WGPP_Landing::url(),$role.' gets Pro landing');}
check(WGPP_Landing::login_redirect('/dashboard/','',(object)['ID'=>20])==='/dashboard/','Curriculum-only destination retained');
$paid[20]=true;check(WGPP_Landing::eligible(20),'New active purchaser can create company');
foreach(['/dashboard/?courseware=1','/pro-analytics/?company=10&invite=token','/wp-admin/','/lesson-one/','/pro-analytics/#review','https://other.example/dashboard/'] as $target){check(WGPP_Landing::login_redirect($target,$target,(object)['ID'=>1])===$target,'Explicit deep link retained: '.$target);}
check(WGPP_Landing::login_redirect('/wp-admin/','',(object)['ID'=>1])===WGPP_Landing::url(),'Default WP destination routes to workspace');
$entitled=false;check(!WGPP_Landing::eligible(2),'Cancelled company entitlement denied');$entitled=true;
$members[10][2]='removed';check(!WGPP_Landing::eligible(2),'Removed seat is not routed');
$posts[10]->post_status='publish';check(!WGPP_Landing::eligible(3),'Invalid company record denied');
check(!str_contains(WGPP_Landing::render(),'Company'),'Logged-out workspace discloses no company');
check(WGPP_Landing::login_redirect('/dashboard/','',null)==='/dashboard/','Failed authentication unaffected');

$posts[10]->post_status='private';$uid=3;
$html=WGPP_Landing::render();check(!str_contains($html,'Manage your workspace')&&!str_contains($html,'Users &amp; Invitations')&&!str_contains($html,'Open the Live Campaign'),'Viewer has no owner tools or course card');
$uid=1;$html=WGPP_Landing::render();check(str_contains($html,'Manage your workspace')&&str_contains($html,'account-users'),'Owner has team tools');
$uid=20;$html=WGPP_Landing::render();check(str_contains($html,'Create a Pro workspace')&&str_contains($html,'Open the Live Campaign'),'Active purchaser gets setup and included curriculum');
