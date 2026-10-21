<?php
if (!defined('ABSPATH')) { exit; }
/** Reporting-only Constant Contact client; vendor campaign scope also permits writes. */
final class WGPP_Constant_Contact {
    const APP='_wgpp_cc_app_v1';
    const TOKEN='_wgpp_cc_tokens_v1';
    const SCOPE='campaign_data offline_access';
    public static function boot() {
        foreach(['settings','start','callback','disconnect'] as $action) { add_action('admin_post_wgpp_cc_'.$action,[__CLASS__,$action]); }
    }
    public static function callback_url() { return admin_url('admin-post.php?action=wgpp_cc_callback'); }
    public static function encrypt($data,$context) { return WGPP_Google::encrypt($data,'constant-contact:'.$context); }
    public static function decrypt($data,$context) { return WGPP_Google::decrypt($data,'constant-contact:'.$context); }
    private static function app() {
        $saved=get_option(self::APP);
        if (!$saved) { throw new RuntimeException('A site administrator must configure Westgate Constant Contact credentials first.'); }
        return self::decrypt($saved,'application');
    }
    private static function owner() {
        if (!WGPP_Company::can('connections')) { throw new RuntimeException('Company owner access is required.'); }
        return WGPP_Company::context();
    }
    private static function back() {
        $raw=isset($_POST['return_to']) && is_string($_POST['return_to']) ? esc_url_raw(wp_unslash($_POST['return_to'])) : '';
        return wp_validate_redirect($raw,WGPP_Company::connection_url('email'));
    }
    private static function finish($message,$back=null) {
        set_transient('wgpp_notice_'.get_current_user_id(),$message,120);
        wp_safe_redirect($back ?: self::back());exit;
    }
    public static function settings() {
        if (!current_user_can('manage_options')) { wp_die('Access denied.','',['response'=>403]); }
        check_admin_referer('wgpp_cc_settings');
        try {
            $id=$_POST['client_id'] ?? ''; $secret=$_POST['client_secret'] ?? '';
            if (!is_string($id) || !is_string($secret)) { throw new RuntimeException('Invalid application credentials.'); }
            $id=trim(wp_unslash($id));$secret=trim(wp_unslash($secret));
            if (!preg_match('/^[a-zA-Z0-9_-]{10,200}$/D',$id) || strlen($secret)>1000) { throw new RuntimeException('Enter the Constant Contact application client ID and secret.'); }
            if (!$secret) {
                $old=self::app();
                if ($old['id']!==$id) { throw new RuntimeException('A changed client ID also needs its matching secret.'); }
                $secret=$old['secret'];
            }
            if (strlen($secret)<10) { throw new RuntimeException('Enter the Constant Contact client secret.'); }
            $saved=self::encrypt(['id'=>$id,'secret'=>$secret],'application');
            if (get_option(self::APP)!==$saved && !update_option(self::APP,$saved,false)) { throw new RuntimeException('Application settings could not be saved.'); }
            self::finish('Westgate Constant Contact application credentials saved.');
        } catch (RuntimeException $e) { self::finish($e->getMessage()); }
    }
    public static function token_response($response,$require_refresh=true) {
        if (!is_array($response) || !is_string($response['access_token'] ?? null) || !$response['access_token'] || !is_numeric($response['expires_in'] ?? null) || ($response['token_type'] ?? '')!=='Bearer' || $response['expires_in']<=0 || $response['expires_in']>86400) { throw new RuntimeException('Constant Contact returned an invalid authorization response. Reconnect to try again.'); }
        if (!is_string($response['refresh_token'] ?? null) || !$response['refresh_token']) { throw new RuntimeException('Offline authorization is required. Reconnect Constant Contact.'); }
        $scopes=explode(' ',$response['scope'] ?? '');
        if (!in_array('campaign_data',$scopes,true) || !in_array('offline_access',$scopes,true)) { throw new RuntimeException('Campaign reporting and offline authorization are required.'); }
        return $response;
    }
    private static function post($body) {
        $app=self::app();
        $r=wp_safe_remote_post('https://authz.constantcontact.com/oauth2/default/v1/token',['timeout'=>15,'redirection'=>0,'limit_response_size'=>65536,'headers'=>['Authorization'=>'Basic '.base64_encode($app['id'].':'.$app['secret']),'Content-Type'=>'application/x-www-form-urlencoded','Accept'=>'application/json'],'body'=>$body]);
        if (is_wp_error($r) || wp_remote_retrieve_response_code($r)!==200) { throw new RuntimeException('Constant Contact authorization failed. Reconnect to try again.'); }
        return self::token_response(json_decode(wp_remote_retrieve_body($r),true));
    }
    public static function start() {
        check_admin_referer('wgpp_cc_start');
        try {
            $c=self::owner();$app=self::app();
            if (wp_parse_url(self::callback_url(),PHP_URL_SCHEME)!=='https') { throw new RuntimeException('Use HTTPS before connecting Constant Contact.'); }
            $state=bin2hex(random_bytes(32));$key='wgpp_oauth_'.hash('sha256',$state);
            WGPP_Native_Reviews::locked($c['id'],static function()use($key,$c,$state){
                update_post_meta($c['id'],'_wgpp_cc_pending',hash('sha256',$state));
                set_transient($key,['company'=>$c['id'],'user'=>get_current_user_id(),'session'=>hash('sha256',wp_get_session_token()),'expires'=>time()+600,'back'=>self::back()],600);
            });
            $url='https://authz.constantcontact.com/oauth2/default/v1/authorize?'.http_build_query(['client_id'=>$app['id'],'redirect_uri'=>self::callback_url(),'response_type'=>'code','scope'=>self::SCOPE,'state'=>$state],'','&',PHP_QUERY_RFC3986);
            wp_redirect($url);exit;
        } catch (RuntimeException $e) { self::finish($e->getMessage()); }
    }
    public static function callback() {
        nocache_headers();
        header('Referrer-Policy: no-referrer');
        try {
            $c=self::owner();
            $state=$_GET['state'] ?? ''; $code=$_GET['code'] ?? '';
            if (!is_string($state) || !preg_match('/^[a-f0-9]{64}$/D',$state) || !is_string($code)) { throw new RuntimeException('Authorization state is invalid. Start the connection again.'); }
            $back=WGPP_Native_Reviews::locked($c['id'],static function()use($c,$state,$code){
                $key='wgpp_oauth_'.hash('sha256',$state);$record=get_transient($key);
                if (get_post_meta($c['id'],'_wgpp_cc_pending',true)!==hash('sha256',$state) || !$record || $record['company']!==$c['id'] || $record['user']!==get_current_user_id() || $record['expires']<=time() || !hash_equals($record['session'],hash('sha256',wp_get_session_token()))) { throw new RuntimeException('Authorization expired or belongs to a different session. Start again.'); }
                delete_transient($key);delete_post_meta($c['id'],'_wgpp_cc_pending');
                if (!$code) { throw new RuntimeException('Constant Contact authorization was cancelled.'); }
                $app=self::app();
                $tokens=self::post(['code'=>$code,'redirect_uri'=>self::callback_url(),'grant_type'=>'authorization_code']);
                $saved=['access_token'=>$tokens['access_token'],'refresh_token'=>$tokens['refresh_token'],'expires'=>time()+(int)$tokens['expires_in'],'authorizing_user'=>get_current_user_id(),'app_id'=>$app['id']];
                if (!update_post_meta($c['id'],self::TOKEN,self::encrypt($saved,'company:'.$c['id']))) { throw new RuntimeException('Could not save authorization.'); }
                update_post_meta($c['id'],'_wgpp_cc_grant',bin2hex(random_bytes(16)));
                return $record['back'];
            });
            self::finish('Constant Contact authorization saved. Apply dates to collect email campaign results.',$back);
        } catch (RuntimeException $e) { self::finish($e->getMessage(),WGPP_Company::connection_url('email')); }
    }
    public static function token($company) {
        // Internal use only after checking membership/entitlement.
        return WGPP_Native_Reviews::locked($company,static function()use($company){
            $data=self::decrypt(get_post_meta($company,self::TOKEN,true) ?: '', 'company:'.$company);$app=self::app();
            if ($data['app_id']!==$app['id']) { throw new RuntimeException('Constant Contact application changed. Reconnect the company.'); }
            if ($data['expires']>time()+60) { return $data['access_token']; }
            $response=self::post(['refresh_token'=>$data['refresh_token'],'grant_type'=>'refresh_token']);
            $data['refresh_token']=$response['refresh_token'];$data['access_token']=$response['access_token'];$data['expires']=time()+(int)$response['expires_in'];
            if (!update_post_meta($company,self::TOKEN,self::encrypt($data,'company:'.$company))) { delete_post_meta($company,self::TOKEN);throw new RuntimeException('Could not save rotated authorization. Reconnect Constant Contact.'); }
            return $data['access_token'];
        });
    }
    public static function disconnect() {
        check_admin_referer('wgpp_cc_disconnect');
        try {
            $c=self::owner();
            $data=WGPP_Native_Reviews::locked($c['id'],static function()use($c){
                $envelope=get_post_meta($c['id'],self::TOKEN,true);delete_post_meta($c['id'],self::TOKEN);delete_post_meta($c['id'],'_wgpp_cc_pending');delete_post_meta($c['id'],'_wgpp_cc_grant');delete_post_meta($c['id'],WGPP_CC_Reports::CACHE);
                return $envelope ? self::decrypt($envelope,'company:'.$c['id']) : null;
            });
            $message='Constant Contact authorization removed from this company. Remove the application in Constant Contact to revoke provider access.';
            self::finish($message);
        } catch (RuntimeException $e) { self::finish($e->getMessage()); }
    }
    private static function form($action,$button,$back) {
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('wgpp_cc_'.$action);
        echo '<input type="hidden" name="action" value="wgpp_cc_'.esc_attr($action).'"><input type="hidden" name="return_to" value="'.esc_url($back).'"><button>'.esc_html($button).'</button></form>';
    }
    public static function render() {
        $c=WGPP_Company::context();if (!$c) { return ''; }
        $back=WGPP_Company::connection_url('email');
        $configured=false;$id='';
        try { $app=self::app();$id=$app['id'];$configured=true; } catch (RuntimeException $e) {}
        $authorized=(bool)get_post_meta($c['id'],self::TOKEN,true);
        ob_start();
        echo '<details class="wgpa-import" data-connection="email"><summary>Constant Contact'.($authorized?' · Connected':' · Setup').'</summary><h3>Email campaign reporting</h3><p>'.($authorized?'Constant Contact authorization stored.':'Constant Contact is not connected.').'</p>';
        echo '<p>Reporting only: this connector never creates, sends, edits, or deletes campaigns. Constant Contact uses a shared campaign permission that also permits changes; authorize only if you accept that provider scope. Contact lists are not requested in this pilot.</p>';
        if (WGPP_Company::can('connections')) {
            if ($configured) { self::form('start',$authorized?'Reconnect Constant Contact':'Connect Constant Contact',$back); }
            else { echo '<p>A site administrator must configure the Westgate Constant Contact application first.</p>'; }
            if ($authorized) { self::form('disconnect','Disconnect Constant Contact',$back); }
        }
        if (is_admin() && current_user_can('manage_options')) {
            echo '<details><summary>Westgate Constant Contact application settings</summary><p>Use this exact authorized redirect URI in the Constant Contact application:</p><code>'.esc_html(self::callback_url()).'</code><p>Only site administrators manage these shared application credentials. Company members never see the client secret.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
            wp_nonce_field('wgpp_cc_settings');
            echo '<input type="hidden" name="action" value="wgpp_cc_settings"><input type="hidden" name="return_to" value="'.esc_url($back).'"><p><label>Constant Contact API key (client ID) <input name="client_id" value="'.esc_attr($id).'" size="55" required></label></p><p><label>Client secret <input name="client_secret" type="password" autocomplete="new-password"></label></p><p>Leave the secret blank to keep the existing value. Changing WordPress security salts requires reconnecting these credentials.</p><button>Save Constant Contact application</button></form></details>';
        }
        echo '</details>';return ob_get_clean();
    }
}
