<?php
if (!defined('ABSPATH')) { exit; }
/** Native read-only Google OAuth. Application secrets never go to the browser. */
final class WGPP_Google {
    const APP='_wgpp_google_app_v1';
    const TOKEN='_wgpp_google_tokens_v1';
    const SCOPE='https://www.googleapis.com/auth/analytics.readonly';
    public static function boot() {
        foreach(['settings','start','callback','disconnect'] as $action) { add_action('admin_post_wgpp_google_'.$action,[__CLASS__,$action]); }
    }
    public static function callback_url() { return admin_url('admin-post.php?action=wgpp_google_callback'); }
    private static function key() {
        if (!function_exists('openssl_encrypt')) { throw new RuntimeException('OpenSSL is required for protected connections.'); }
        return hash('sha256',wp_salt('auth').'|wgpp-native-connections-v1',true);
    }
    public static function encrypt($data,$context) {
        $iv=random_bytes(12);$tag='';
        $cipher=openssl_encrypt(wp_json_encode($data),'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$iv,$tag,$context);
        if ($cipher===false) { throw new RuntimeException('Could not protect connection credentials.'); }
        return base64_encode($iv.$tag.$cipher);
    }
    public static function decrypt($envelope,$context) {
        $bytes=base64_decode($envelope,true);
        if ($bytes===false || strlen($bytes)<29) { throw new RuntimeException('Connection needs to be configured again.'); }
        $plain=openssl_decrypt(substr($bytes,28),'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,substr($bytes,0,12),substr($bytes,12,16),$context);
        $data=$plain===false ? null : json_decode($plain,true);
        if (!is_array($data)) { throw new RuntimeException('Connection needs to be configured again.'); }
        return $data;
    }
    private static function app() {
        $saved=get_option(self::APP);
        if (!$saved) { throw new RuntimeException('A site administrator must configure Westgate Google credentials first.'); }
        return self::decrypt($saved,'application');
    }
    private static function owner() {
        if (!WGPP_Company::can('connections')) { throw new RuntimeException('Company owner access is required.'); }
        return WGPP_Company::context();
    }
    private static function back() {
        $raw=isset($_POST['return_to']) && is_string($_POST['return_to']) ? esc_url_raw(wp_unslash($_POST['return_to'])) : '';
        return wp_validate_redirect($raw,WGPP_Company::connection_url('google'));
    }
    private static function finish($message,$back=null) {
        set_transient('wgpp_notice_'.get_current_user_id(),$message,120);
        wp_safe_redirect($back ?: self::back());exit;
    }
    public static function settings() {
        if (!current_user_can('manage_options')) { wp_die('Access denied.','',['response'=>403]); }
        check_admin_referer('wgpp_google_settings');
        try {
            $id=$_POST['client_id'] ?? ''; $secret=$_POST['client_secret'] ?? '';
            if (!is_string($id) || !is_string($secret)) { throw new RuntimeException('Invalid application credentials.'); }
            $id=trim(wp_unslash($id));$secret=trim(wp_unslash($secret));
            if (!preg_match('/^[a-zA-Z0-9._-]+\.apps\.googleusercontent\.com$/D',$id) || strlen($secret)>1000) { throw new RuntimeException('Enter the Google web application client ID and secret.'); }
            if (!$secret) {
                $old=self::app();
                if ($old['id']!==$id) { throw new RuntimeException('A changed client ID also needs its matching secret.'); }
                $secret=$old['secret'];
            }
            if (strlen($secret)<10) { throw new RuntimeException('Enter the Google client secret.'); }
            update_option(self::APP,self::encrypt(['id'=>$id,'secret'=>$secret],'application'),false);
            self::finish('Westgate Google application credentials saved.');
        } catch (RuntimeException $e) { self::finish($e->getMessage()); }
    }
    public static function token_response($response,$require_refresh) {
        if (!is_array($response) || !is_string($response['access_token'] ?? null) || !is_numeric($response['expires_in'] ?? null)) { throw new RuntimeException('Google authorization could not be completed. Reconnect to try again.'); }
        if (($response['token_type'] ?? '')!=='Bearer' || $response['expires_in']<=0 || $response['expires_in']>86400) { throw new RuntimeException('Google returned an unexpected token.'); }
        if ($require_refresh && (!is_string($response['refresh_token'] ?? null) || !in_array(self::SCOPE,explode(' ',$response['scope'] ?? ''),true))) { throw new RuntimeException('Read-only offline Analytics permission is required. Reconnect and approve it.'); }
        return $response;
    }
    private static function post($url,$body) {
        $r=wp_safe_remote_post($url,['timeout'=>15,'redirection'=>0,'limit_response_size'=>65536,'body'=>$body]);
        if (is_wp_error($r) || wp_remote_retrieve_response_code($r)!==200) { throw new RuntimeException('Google could not complete the connection request. Reconnect to try again.'); }
        if ($url==='https://oauth2.googleapis.com/revoke' && trim(wp_remote_retrieve_body($r))==='') { return []; }
        $data=json_decode(wp_remote_retrieve_body($r),true);
        if (!is_array($data) || isset($data['error'])) { throw new RuntimeException('Google connection needs authorization again.'); }
        return $data;
    }
    public static function start() {
        check_admin_referer('wgpp_google_start');
        try {
            $c=self::owner();$app=self::app();
            if (wp_parse_url(self::callback_url(),PHP_URL_SCHEME)!=='https') { throw new RuntimeException('Use HTTPS before connecting Google.'); }
            $state=bin2hex(random_bytes(32));$key='wgpp_oauth_'.hash('sha256',$state);
            WGPP_Native_Reviews::locked($c['id'],static function()use($key,$c,$state){
                update_post_meta($c['id'],'_wgpp_google_pending',hash('sha256',$state));
                set_transient($key,['company'=>$c['id'],'user'=>get_current_user_id(),'session'=>hash('sha256',wp_get_session_token()),'expires'=>time()+600,'back'=>self::back()],600);
            });
            $url='https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query(['client_id'=>$app['id'],'redirect_uri'=>self::callback_url(),'response_type'=>'code','scope'=>self::SCOPE,'access_type'=>'offline','prompt'=>'consent','state'=>$state],'','&',PHP_QUERY_RFC3986);
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
                if (get_post_meta($c['id'],'_wgpp_google_pending',true)!==hash('sha256',$state) || !$record || $record['company']!==$c['id'] || $record['user']!==get_current_user_id() || $record['expires']<=time() || !hash_equals($record['session'],hash('sha256',wp_get_session_token()))) { throw new RuntimeException('Authorization expired or belongs to a different session. Start again.'); }
                delete_transient($key);delete_post_meta($c['id'],'_wgpp_google_pending');
                if (!$code) { throw new RuntimeException('Google authorization was cancelled.'); }
                $app=self::app();
                $tokens=self::token_response(self::post('https://oauth2.googleapis.com/token',['code'=>$code,'client_id'=>$app['id'],'client_secret'=>$app['secret'],'redirect_uri'=>self::callback_url(),'grant_type'=>'authorization_code']),true);
                $saved=['access_token'=>$tokens['access_token'],'refresh_token'=>$tokens['refresh_token'],'expires'=>time()+(int)$tokens['expires_in'],'authorizing_user'=>get_current_user_id(),'app_id'=>$app['id']];
                if (!update_post_meta($c['id'],self::TOKEN,self::encrypt($saved,'company:'.$c['id']))) { throw new RuntimeException('Could not save authorization.'); }
                update_post_meta($c['id'],'_wgpp_google_grant',bin2hex(random_bytes(16)));
                return $record['back'];
            });
            self::finish('Google authorization saved. Verify the company GA4 property to enable reporting.',$back);
        } catch (RuntimeException $e) { self::finish($e->getMessage(),WGPP_Company::connection_url('google')); }
    }
    public static function token($company) {
        // Internal use only after checking membership/entitlement.
        return WGPP_Native_Reviews::locked($company,static function()use($company){
            $data=self::decrypt(get_post_meta($company,self::TOKEN,true) ?: '', 'company:'.$company);$app=self::app();
            if ($data['app_id']!==$app['id']) { throw new RuntimeException('Google application changed. Reconnect the company.'); }
            if ($data['expires']>time()+60) { return $data['access_token']; }
            $response=self::token_response(self::post('https://oauth2.googleapis.com/token',['client_id'=>$app['id'],'client_secret'=>$app['secret'],'refresh_token'=>$data['refresh_token'],'grant_type'=>'refresh_token']),false);
            $data['access_token']=$response['access_token'];$data['expires']=time()+(int)$response['expires_in'];
            update_post_meta($company,self::TOKEN,self::encrypt($data,'company:'.$company));
            return $data['access_token'];
        });
    }
    public static function disconnect() {
        check_admin_referer('wgpp_google_disconnect');
        try {
            $c=self::owner();
            $data=WGPP_Native_Reviews::locked($c['id'],static function()use($c){
                $envelope=get_post_meta($c['id'],self::TOKEN,true);delete_post_meta($c['id'],self::TOKEN);delete_post_meta($c['id'],'_wgpp_google_pending');delete_post_meta($c['id'],'_wgpp_google_grant');
                return $envelope ? self::decrypt($envelope,'company:'.$c['id']) : null;
            });
            $message='Google authorization removed from this company.';
            if ($data) {
                try { self::post('https://oauth2.googleapis.com/revoke',['token'=>$data['refresh_token']]); }
                catch (RuntimeException $e) { $message.=' Google revocation could not be confirmed; you can also remove access in your Google account.'; }
            }
            self::finish($message);
        } catch (RuntimeException $e) { self::finish($e->getMessage()); }
    }
    private static function form($action,$button,$back) {
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('wgpp_google_'.$action);
        echo '<input type="hidden" name="action" value="wgpp_google_'.esc_attr($action).'"><input type="hidden" name="return_to" value="'.esc_url($back).'"><button>'.esc_html($button).'</button></form>';
    }
    public static function render() {
        $c=WGPP_Company::context();if (!$c) { return ''; }
        $back=WGPP_Company::connection_url('google');
        $configured=false;$id='';
        try { $app=self::app();$id=$app['id'];$configured=true; } catch (RuntimeException $e) {}
        $authorized=(bool)get_post_meta($c['id'],self::TOKEN,true);
        ob_start();
        echo '<details class="wgpa-import" data-connection="google"'.($authorized && WGPP_GA4::config($c['id'])?'':' open').'><summary>Google Analytics'.($authorized && WGPP_GA4::config($c['id'])?' · Connected':' · Setup').'</summary><h3>Google Analytics</h3><p>'.($authorized?'Google authorization is saved. Reconnect only to change authorization or resolve an access error.':'Google Analytics is not connected.').'</p>';
        if (WGPP_Company::can('connections')) {
            if ($configured) { self::form('start',$authorized?'Reconnect Google Analytics':'Connect Google Analytics',$back); }
            else { echo '<p>A site administrator must configure the Westgate Google application first.</p>'; }
            if ($authorized) { self::form('disconnect','Disconnect Google Analytics',$back); }
        }
        if ($authorized) { echo WGPP_GA4::render($c['id']); }

        if (is_admin() && current_user_can('manage_options')) {
            echo '<details><summary>Westgate application settings</summary><p>Use this exact authorized redirect URI in the Google web application:</p><code>'.esc_html(self::callback_url()).'</code><p>Only site administrators manage these shared application credentials. Company members never see the client secret.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
            wp_nonce_field('wgpp_google_settings');
            echo '<input type="hidden" name="action" value="wgpp_google_settings"><input type="hidden" name="return_to" value="'.esc_url($back).'"><p><label>Google client ID <input name="client_id" value="'.esc_attr($id).'" size="55" required></label></p><p><label>Client secret <input name="client_secret" type="password" autocomplete="new-password"></label></p><p>Leave the secret blank to keep the existing value. Changing WordPress security salts requires reconnecting these credentials.</p><button>Save Google application</button></form></details>';
        }
        echo '</details>';return ob_get_clean();
    }
}
