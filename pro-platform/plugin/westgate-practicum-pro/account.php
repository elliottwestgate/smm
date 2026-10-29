<?php
if(!defined('ABSPATH')){exit;}
final class WGPP_Account {
    const EMAIL='_wgpp_email_change_v1';
    const INVITATION_FROM='support@socialmediamagnet.net';
    public static function boot(){add_action('admin_post_wgpp_account',[__CLASS__,'handle']);}
    public static function assets(){wp_enqueue_script('wgpp-account',plugins_url('assets/account.js',__FILE__),[],'0.5.2-preview',true);wp_enqueue_style('wgpp-account',plugins_url('assets/analytics.css',__FILE__),[],'0.5.2-preview');}
    public static function mail($email,$subject,$body,$invitation=false){
        $sender=static function(){return 'Social Media Magnet Pro';};add_filter('wp_mail_from_name',$sender,PHP_INT_MAX);
        $address=static function(){return self::INVITATION_FROM;};if($invitation){add_filter('wp_mail_from',$address,PHP_INT_MAX);}
        $headers=['Content-Type: text/plain; charset=UTF-8'];if($invitation){$headers[]='Reply-To: Social Media Magnet Pro <'.self::INVITATION_FROM.'>';}
        try{return wp_mail($email,$subject,$body,$headers);}finally{remove_filter('wp_mail_from_name',$sender,PHP_INT_MAX);if($invitation){remove_filter('wp_mail_from',$address,PHP_INT_MAX);}}
    }
    public static function password($uid,$password){$u=get_userdata($uid);if(!$u || !is_string($password) || !wp_check_password(wp_unslash($password),$u->user_pass,$uid)){throw new RuntimeException('Your current password is incorrect.');}}
    public static function transfer($company,$actor,$target){
        $members=get_post_meta($company,'_wgpp_members',true) ?: [];
        if(($members[$actor] ?? '')!=='owner' || $actor===$target || !isset($members[$target]) || $members[$target]==='owner' || !get_userdata($target)){throw new RuntimeException('Select an active member of this company as the new owner.');}
        $members[$target]='owner';$members[$actor]='analyst';
        if(!update_post_meta($company,'_wgpp_members',$members) && get_post_meta($company,'_wgpp_members',true)!==$members){throw new RuntimeException('Could not transfer ownership.');}
    }
    public static function request_email($company,$uid,$email){
        $members=get_post_meta($company,'_wgpp_members',true) ?: [];$user=get_userdata($uid);
        if(!isset($members[$uid]) || !$user || !is_email($email) || strtolower($user->user_email)===$email || get_user_by('email',$email)){throw new RuntimeException('Enter a different, unused email address for this member.');}
        $prior=get_user_meta($uid,self::EMAIL,true);
        if(is_array($prior) && ($prior['requested'] ?? 0)>time()-60){throw new RuntimeException('Wait one minute before requesting another email change.');}
        $request=['company'=>$company,'email'=>$email,'old_email'=>strtolower($user->user_email),'requested'=>time(),'expires'=>time()+7*DAY_IN_SECONDS,'state'=>'requested'];
        if(!update_user_meta($uid,self::EMAIL,$request)){throw new RuntimeException('Could not save the email-change request.');}
    }
    public static function confirm_email($company,$uid,$token){
        wp_cache_delete($uid,'user_meta');if(function_exists('clean_user_cache')){clean_user_cache($uid);}
        $request=get_user_meta($uid,self::EMAIL,true);$members=get_post_meta($company,'_wgpp_members',true) ?: [];$u=get_userdata($uid);
        if(!is_array($request) || (int)($request['company'] ?? 0)!==(int)$company || ($request['state'] ?? '')!=='verification' || ($request['expires'] ?? 0)<time() || !isset($members[$uid]) || !$u || strtolower($u->user_email)!==($request['old_email'] ?? '') || !hash_equals($request['hash'] ?? '',hash('sha256',$token))){throw new RuntimeException('This email-change link is invalid or expired.');}
        $existing=get_user_by('email',$request['email']);if($existing && (int)$existing->ID!==$uid){throw new RuntimeException('That email address is already in use.');}
        $result=wp_update_user(['ID'=>$uid,'user_email'=>$request['email']]);if(is_wp_error($result)){throw new RuntimeException('Could not change the email address.');}
        delete_user_meta($uid,self::EMAIL);
    }
    public static function handle(){
        check_admin_referer('wgpp_account');$section='profile';
        try{
            $c=WGPP_Company::context();if(!$c){throw new RuntimeException('Company access required.');}$uid=get_current_user_id();$op=is_string($_POST['account_op'] ?? null)?$_POST['account_op']:'';
            if($op==='name'){
                $name=$_POST['display_name'] ?? '';if(!is_string($name) || !trim($name) || strlen($name)>100){throw new RuntimeException('Enter a name up to 100 characters.');}
                if(is_wp_error(wp_update_user(['ID'=>$uid,'display_name'=>sanitize_text_field(wp_unslash($name))]))){throw new RuntimeException('Could not save your name.');}$message='Your name was saved.';
            }elseif($op==='email_request'){
                self::password($uid,$_POST['current_password'] ?? '');$email=$_POST['new_email'] ?? '';if(!is_string($email)){throw new RuntimeException('Enter a valid email address.');}$email=strtolower(sanitize_email(wp_unslash($email)));
                WGPP_Native_Reviews::locked($c['id'],static function()use($c,$uid,$email){self::request_email($c['id'],$uid,$email);});$message='Email-change request recorded. Your owner must approve it, then you must verify the new address. Your current email remains unchanged.';
            }elseif($op==='email_cancel'){
                delete_user_meta($uid,self::EMAIL);$message='Email-change request canceled.';
            }elseif($op==='email_confirm'){
                $token=$_POST['email_token'] ?? '';if(!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D',$token)){throw new RuntimeException('Invalid confirmation.');}
                $request=get_user_meta($uid,self::EMAIL,true);$email=is_array($request)?($request['email'] ?? ''):'';
                WGPP_Native_Reviews::locked('team-email:'.hash('sha256',$email),static function()use($c,$uid,$token){WGPP_Native_Reviews::locked($c['id'],static function()use($c,$uid,$token){self::confirm_email($c['id'],$uid,$token);});});$message='Your email address was updated.';
            }elseif($op==='transfer'){
                $section='users';if(!WGPP_Company::can('members') || ($_POST['confirm_transfer'] ?? '')!=='1'){throw new RuntimeException('The current owner must confirm the ownership transfer.');}self::password($uid,$_POST['current_password'] ?? '');$target=absint($_POST['new_owner'] ?? 0);
                WGPP_Native_Reviews::locked($c['id'],static function()use($c,$uid,$target){self::transfer($c['id'],$uid,$target);});$message='Ownership transferred. You now have Analyst access.';
            }elseif(in_array($op,['email_approve','email_reject'],true)){
                $section='users';if(!WGPP_Company::can('members')){throw new RuntimeException('Owner access required.');}$target=absint($_POST['member'] ?? 0);$token=bin2hex(random_bytes(32));
                $email=WGPP_Native_Reviews::locked($c['id'],static function()use($c,$target,$op,$token){
                    if(!WGPP_Company::can('members')){throw new RuntimeException('Owner access changed.');}$members=get_post_meta($c['id'],'_wgpp_members',true) ?: [];$r=get_user_meta($target,self::EMAIL,true);
                    if(!isset($members[$target]) || !is_array($r) || (int)$r['company']!==(int)$c['id'] || $r['expires']<time()){throw new RuntimeException('No active email request for this member.');}
                    if($op==='email_reject'){delete_user_meta($target,self::EMAIL);return null;}
                    if($r['state']==='verification' && ($r['sent_at'] ?? 0)>time()-60){throw new RuntimeException('Wait one minute before resending verification.');}
                    $r['state']='verification';$r['hash']=hash('sha256',$token);$r['expires']=time()+DAY_IN_SECONDS;$r['sent_at']=time();if(!update_user_meta($target,self::EMAIL,$r) && get_user_meta($target,self::EMAIL,true)!==$r){throw new RuntimeException('Could not save email verification.');}return $r['email'];
                });
                if($email){$url=add_query_arg(['wgpp_email_confirm'=>$token,'member'=>$target],WGPP_Team::portal());if(!self::mail($email,'Verify your Social Media Magnet Pro email change',"Your company owner approved your requested email change.\n\nSign in to your existing account and open this link within 24 hours:\n".$url."\n\nYour email will change only after you confirm on that page. If you did not request this, ignore this message.")){throw new RuntimeException('Verification email could not be sent. Try approving again after one minute.');}$message='Verification sent to the requested address. The existing email remains unchanged until confirmed.';}else{$message='Email-change request declined.';}
            }else{throw new RuntimeException('Invalid account action.');}
        }catch(RuntimeException $e){$message=$e->getMessage();}
        set_transient('wgpp_notice_'.get_current_user_id(),$message,120);wp_safe_redirect(WGPP_Team::portal().'#account-'.$section);exit;
    }
    public static function form($op){echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('wgpp_account');echo '<input type="hidden" name="action" value="wgpp_account"><input type="hidden" name="account_op" value="'.esc_attr($op).'">';}
    public static function password_field($label='Current password'){echo '<div data-password-field><label>'.esc_html($label).' <input type="password" name="current_password" autocomplete="current-password" required data-password-input></label><button type="button" data-password-toggle aria-pressed="false">Show password</button></div>';}
    public static function profile($c){
        self::assets();$u=wp_get_current_user();$r=get_user_meta($u->ID,self::EMAIL,true);ob_start();echo '<h4>Your login profile</h4><p>Login email: <strong>'.esc_html($u->user_email).'</strong>. Email changes require a request, owner approval and verification of the new address.</p>';
        self::form('name');echo '<p><label>Your name <input name="display_name" maxlength="100" value="'.esc_attr($u->display_name).'" required></label></p><button>Save your name</button></form>';
        if(is_array($r) && ($r['expires'] ?? 0)>time()){echo '<p>Pending email change: '.esc_html($r['email']).' · '.esc_html($r['state']==='verification'?'Awaiting verification of the new address':'Awaiting owner approval').'</p>';self::form('email_cancel');echo '<button>Cancel email-change request</button></form>';}
        echo '<details><summary>Request an email change</summary><p>This login may also be used for your course access. The current address remains in use until the new address is approved and verified.</p>';self::form('email_request');echo '<p><label>Requested new email <input type="email" name="new_email" autocomplete="email" required></label></p>';self::password_field();echo '<button>Request email change</button></form></details>';return ob_get_clean();
    }
    public static function owner_controls($c){
        if(!WGPP_Company::can('members')){return '';}$members=get_post_meta($c['id'],'_wgpp_members',true) ?: [];ob_start();echo '<h4>Email-change requests</h4>';$found=false;
        foreach($members as $uid=>$role){$r=get_user_meta($uid,self::EMAIL,true);if(!is_array($r) || (int)($r['company'] ?? 0)!==(int)$c['id'] || ($r['expires'] ?? 0)<time()){continue;}$found=true;$u=get_userdata($uid);echo '<p>'.esc_html($u?$u->display_name:'Member').' · '.esc_html($r['email']).' · '.esc_html($r['state']==='verification'?'Awaiting verification':'Requested').'</p>';self::form('email_approve');echo '<input type="hidden" name="member" value="'.esc_attr($uid).'"><button>'.($r['state']==='verification'?'Resend verification':'Approve and send verification').'</button></form>';self::form('email_reject');echo '<input type="hidden" name="member" value="'.esc_attr($uid).'"><button>Decline request</button></form>';}
        if(!$found){echo '<p>No email-change requests.</p>';}
        echo '<details><summary>Transfer company ownership</summary><p>The new owner must already be an active company member. You will become an Analyst. This does not change WordPress administrator privileges or course enrollment.</p>';self::form('transfer');echo '<label>New owner <select name="new_owner" required><option value="">Select a team member</option>';
        foreach($members as $uid=>$role){if($role==='owner'){continue;}$u=get_userdata($uid);if($u){echo '<option value="'.esc_attr($uid).'">'.esc_html($u->display_name).' · '.esc_html($u->user_email).'</option>';}}
        echo '</select></label>';self::password_field();echo '<p><label><input type="checkbox" name="confirm_transfer" value="1" required> I confirm that this person will manage the company workspace.</label></p><button>Transfer ownership</button></form></details>';return ob_get_clean();
    }
    public static function confirmation(){
        $token=$_GET['wgpp_email_confirm'] ?? '';$uid=absint($_GET['member'] ?? 0);if(!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D',$token) || !$uid){return '';}
        self::assets();if(!is_user_logged_in()){return '<section class="wgpa-import"><h2>Verify your email change</h2><p><a href="'.esc_url(wp_login_url(add_query_arg(['wgpp_email_confirm'=>$token,'member'=>$uid],WGPP_Team::portal()))).'">Sign in to your existing account to confirm</a></p></section>';}
        if(get_current_user_id()!==$uid){return '<p>Sign in to the existing account that requested this email change.</p>';}
        $r=get_user_meta($uid,self::EMAIL,true);if(!is_array($r) || ($r['state'] ?? '')!=='verification' || ($r['expires'] ?? 0)<time() || !hash_equals($r['hash'] ?? '',hash('sha256',$token))){return '<p>This email-change link has expired or been replaced.</p>';}
        ob_start();echo '<section class="wgpa-import"><h2>Confirm your new email</h2><p>Change your login email to <strong>'.esc_html($r['email']).'</strong>?</p>';self::form('email_confirm');echo '<input type="hidden" name="email_token" value="'.esc_attr($token).'"><button>Confirm email change</button></form></section>';return ob_get_clean();
    }
}
