<?php
require __DIR__.'/team-test.php';
require __DIR__.'/../plugin/westgate-practicum-pro/account.php';
function get_userdata($id){foreach($GLOBALS['users'] as $u){if($u->ID===$id){return $u;}}return false;}
function is_email($email){return (bool)filter_var($email,FILTER_VALIDATE_EMAIL);}
function is_wp_error($v){return false;}
function wp_update_user($data){$u=get_userdata($data['ID']);foreach($data as $k=>$v){$u->$k=$v;}return $u->ID;}
function delete_user_meta($id,$key){unset($GLOBALS['um'][$id][$key]);}
function wp_cache_delete(...$args){}
function wp_check_password($p,$hash,$id){return password_verify($p,$hash);}
function add_filter($name,$callback,$priority){$GLOBALS['filters'][$name]=$callback;}
function remove_filter($name,$callback,$priority){unset($GLOBALS['filters'][$name]);}
function wp_mail($email,$subject,$body,$headers){$GLOBALS['sent'][]=['from'=>($GLOBALS['filters']['wp_mail_from_name'])(),'email'=>$email,'headers'=>$headers,'address'=>isset($GLOBALS['filters']['wp_mail_from'])?($GLOBALS['filters']['wp_mail_from'])():'wordpress@example.com'];if(!empty($GLOBALS['failMail'])){throw new RuntimeException('Simulated mail failure');}return true;}
$GLOBALS['entitled']=true;$users[]=(object)['ID'=>1,'user_email'=>'owner@example.com','display_name'=>'Owner','user_pass'=>password_hash('test-password-123',PASSWORD_DEFAULT)];
$users[0]->display_name='Member';$meta[10]['_wgpp_members']=[1=>'owner',2=>'viewer'];$um[1][WGPP_Company::ACTIVE]=10;$um[2][WGPP_Company::ACTIVE]=10;
rejects(fn()=>WGPP_Account::transfer(10,2,1),'Viewer cannot transfer ownership');
rejects(fn()=>WGPP_Account::transfer(10,1,99),'Ownership cannot be transferred to a nonmember');
WGPP_Account::transfer(10,1,2);
check($meta[10]['_wgpp_members']===[1=>'analyst',2=>'owner'],'Ownership transfer retains one owner and makes prior owner Analyst');
rejects(fn()=>WGPP_Account::password(1,'wrong'),'Current password is required for sensitive actions');
WGPP_Account::password(1,'test-password-123');
WGPP_Account::request_email(10,1,'new-owner@example.com');
check(get_userdata(1)->user_email==='owner@example.com','Request alone does not change login email');
rejects(fn()=>WGPP_Account::confirm_email(10,1,str_repeat('a',64)),'Unapproved email change cannot be confirmed');
$um[1][WGPP_Account::EMAIL]['state']='verification';$um[1][WGPP_Account::EMAIL]['hash']=hash('sha256',str_repeat('a',64));
rejects(fn()=>WGPP_Account::confirm_email(10,1,str_repeat('b',64)),'Wrong email-verification token rejected');
WGPP_Account::confirm_email(10,1,str_repeat('a',64));
check(get_userdata(1)->user_email==='new-owner@example.com' && !isset($um[1][WGPP_Account::EMAIL]),'Verified email applied and request consumed');
rejects(fn()=>WGPP_Account::confirm_email(10,1,str_repeat('a',64)),'Email verification cannot be replayed');
WGPP_Account::mail('member@example.com','Test only','Test only');
check($GLOBALS['sent'][0]['from']==='Social Media Magnet Pro' && empty($GLOBALS['filters']),'Branded sender is scoped to product email only');
WGPP_Account::mail('member@example.com','Test invitation','Test invitation',true);
check($GLOBALS['sent'][1]['address']==='support@socialmediamagnet.net' && empty($GLOBALS['filters']),'Invitation uses the branded address without leaking sender filters to other mail');
check(in_array('Reply-To: Social Media Magnet Pro <support@socialmediamagnet.net>',$GLOBALS['sent'][1]['headers'],true),'Invitation replies go to support at the requested .net domain');
WGPP_Account::mail('member@example.com','Other product mail','Other product mail');
check($GLOBALS['sent'][2]['address']==='wordpress@example.com','Other email retains its existing sender configuration');
$GLOBALS['failMail']=true;rejects(fn()=>WGPP_Account::mail('member@example.com','Test failure','Test failure',true),'Mailer failure is returned to caller');unset($GLOBALS['failMail']);
check(empty($GLOBALS['filters']),'Invitation sender filters are removed even if sending throws');
$meta[10][WGPP_Team::INVITES]=['invite@example.com'=>['hash'=>hash('sha256',str_repeat('c',64)),'role'=>'viewer','expires'=>time()+100,'sent_at'=>time()-100,'message'=>'Please review our performance.']];
check(WGPP_Team::invited_email(10,str_repeat('c',64))==='invite@example.com' && WGPP_Team::invited_email(10,str_repeat('d',64))===null,'Invited email is resolved from token, not editable user input');

$body=WGPP_Team::invitation_body('Example Owner','Example Company','invite@example.com','analyst','Please review our traffic this month.','https://example.com/invitation');
check(str_contains($body,'Example Owner has invited you to join Example Company') && str_contains($body,'Please review our traffic this month.') && str_contains($body,'specifically for invite@example.com'),'Invitation identifies sender, company, personal message and bound email');
function absint($v){return abs((int)$v);}
function is_user_logged_in(){return false;}
function wp_enqueue_script(...$args){}
function wp_enqueue_style(...$args){}
function plugins_url($path,$file){return 'https://example.com/plugin/'.$path;}
function home_url($path){return 'https://example.com'.$path;}
function admin_url($path){return 'https://example.com/wp-admin/'.$path;}
function wp_nonce_field($action){}
function esc_attr($v){return htmlspecialchars((string)$v,ENT_QUOTES);}
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES);}
function esc_url($v){return esc_attr($v);}
function wp_login_url($url){return 'https://example.com/login?redirect_to='.urlencode($url);}
function add_query_arg($args,$url){return $url.'?'.http_build_query($args);}
$_GET=['company'=>10,'invite'=>str_repeat('c',64)];$html=WGPP_Team::invitation();
check((bool)preg_match('/name="email"[^>]*value="invite@example.com"[^>]*readonly/',$html),'Invitation signup displays the token-bound email as read-only');
check(str_contains($html,'data-password-toggle') && str_contains($html,'Please review our performance.'),'Invitation page shows personal message and Show password control');
