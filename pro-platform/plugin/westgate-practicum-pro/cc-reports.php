<?php
if (!defined('ABSPATH')) { exit; }
/** Aggregate campaign statistics only. Never requests individual contact records. */
final class WGPP_CC_Reports {
    const CACHE='_wgpp_cc_reports_v1';
    const PATH='/v3/reports/summary_reports/email_campaign_summaries';
    public static function boot() {
        WGPP_Mailchimp::boot();
        add_action('wgpp_collect_cc',[__CLASS__,'collect'],10,2);
        add_action('wp_ajax_wgpp_email',[__CLASS__,'ajax']);
    }
    public static function next_path($path) {
        if (!is_string($path) || strlen($path)>8192 || preg_match('/[\r\n]/',$path)) { throw new RuntimeException('Invalid email report pagination.'); }
        $parts=wp_parse_url($path);
        if (!$parts || isset($parts['scheme']) || isset($parts['host']) || isset($parts['fragment']) || ($parts['path'] ?? '')!==self::PATH) { throw new RuntimeException('Unexpected email report pagination.'); }
        return $path;
    }
    public static function normalize($row) {
        if (!is_array($row) || !preg_match('/^[a-f0-9-]{36}$/Di',$row['campaign_id'] ?? '') || !is_string($row['last_sent_date'] ?? null) || !preg_match('/^\d{4}-\d{2}-\d{2}T/',$row['last_sent_date'])) { throw new RuntimeException('Invalid email campaign report.'); }
        try { $sent=new DateTimeImmutable($row['last_sent_date']); } catch (Exception $e) { throw new RuntimeException('Invalid campaign send date.'); }
        $out=['id'=>$row['campaign_id'],'sent'=>$sent->format('c'),'counts'=>[]];
        foreach (['sends','opens','clicks','bounces','optouts'] as $key) {
            $v=$row['unique_counts'][$key] ?? null;
            if (!(is_int($v) || (is_string($v) && preg_match('/^\d+$/D',$v))) || $v<0 || $v>1000000000000) { throw new RuntimeException('Incomplete email campaign counts.'); }
            $out['counts'][$key]=(int)$v;
        }
        if ($out['counts']['bounces']>$out['counts']['sends']) { throw new RuntimeException('Email delivery counts do not reconcile.'); }
        return $out;
    }
    public static function summarize($rows,$from,$to,$timezone) {
        WGPP_Analytics::date($from);WGPP_Analytics::date($to);
        if ($from>$to) { throw new InvalidArgumentException('Invalid email date range.'); }
        $tz=new DateTimeZone($timezone);$totals=array_fill_keys(['sends','opens','clicks','bounces','optouts'],0);$campaigns=[];
        foreach($rows as $row) {
            $date=(new DateTimeImmutable($row['sent']))->setTimezone($tz)->format('Y-m-d');
            if ($date<$from || $date>$to) { continue; }
            foreach($totals as $k=>$v) { $totals[$k]+=$row['counts'][$k]; }
            $campaigns[]=$row;
        }
        $totals['delivered']=$totals['sends']-$totals['bounces'];
        $totals['open_rate']=$totals['sends']>0?100*$totals['opens']/$totals['sends']:null;
        $totals['click_rate']=$totals['sends']>0?100*$totals['clicks']/$totals['sends']:null;
        return ['from'=>$from,'to'=>$to,'metrics'=>$totals,'campaigns'=>$campaigns];
    }
    private static function signature($company) {
        return hash('sha256',get_post_meta($company,'_wgpp_cc_grant',true).'|'.(get_option(WGPP_Constant_Contact::APP) ?: ''));
    }
    private static function schedule($company,$signature) {
        $ok=wp_schedule_single_event(time()+1,'wgpp_collect_cc',[$company,$signature],true);
        if (is_wp_error($ok) || !$ok) { throw new RuntimeException('Email collection could not be scheduled.'); }
    }
    public static function request($company) {
        $provider=WGPP_Mailchimp::provider($company);if($provider==='mailchimp'){return WGPP_Mailchimp::request($company);}if($provider!=='constant_contact'){return ['status'=>$provider==='none'?'not_used':'not_selected'];}
        if (!get_post_meta($company,WGPP_Constant_Contact::TOKEN,true)) { return ['status'=>'not_connected']; }
        $signature=self::signature($company);
        return WGPP_Native_Reviews::locked($company,static function()use($company,$signature){
            $c=get_post_meta($company,self::CACHE,true);
            if ($c && ($c['signature'] ?? '')===$signature) {
                if ($c['status']==='complete' && $c['updated']>time()-21600) { return $c; }
                if (in_array($c['status'],['queued','processing'],true) && $c['updated']>time()-900) { return ['status'=>$c['status']]; }
                if ($c['status']==='failed' && $c['updated']>time()-300) { return ['status'=>'failed']; }
            }
            $c=['status'=>'queued','updated'=>time(),'signature'=>$signature,'page'=>0,'path'=>self::PATH.'?limit=500','rows'=>[],'seen'=>[]];
            update_post_meta($company,self::CACHE,$c);
            try { self::schedule($company,$signature); } catch (RuntimeException $e) { $c['status']='failed';update_post_meta($company,self::CACHE,$c); }
            return ['status'=>$c['status']];
        });
    }
    public static function collect($company,$signature) {
        try {
            $job=WGPP_Native_Reviews::locked($company,static function()use($company,$signature){
                $c=get_post_meta($company,self::CACHE,true);
                if (!$c || $c['status']!=='queued' || $c['signature']!==$signature || self::signature($company)!==$signature || !get_post_meta($company,WGPP_Constant_Contact::TOKEN,true) || !apply_filters('wgpp_company_entitled',true,$company)) { return null; }
                $c['status']='processing';$c['updated']=time();update_post_meta($company,self::CACHE,$c);return $c;
            });
            if (!$job) { return; }
            $path=self::next_path($job['path']);
            $r=wp_safe_remote_get('https://api.cc.email'.$path,['timeout'=>15,'redirection'=>0,'limit_response_size'=>2097152,'headers'=>['Authorization'=>'Bearer '.WGPP_Constant_Contact::token($company),'Accept'=>'application/json']]);
            if (is_wp_error($r) || wp_remote_retrieve_response_code($r)!==200) { throw new RuntimeException('Email collection failed.'); }
            $data=json_decode(wp_remote_retrieve_body($r),true);
            if (!is_array($data['bulk_email_campaign_summaries'] ?? null)) { throw new RuntimeException('Invalid email reporting response.'); }
            $rows=[];foreach($data['bulk_email_campaign_summaries'] as $row) { $clean=self::normalize($row);$rows[$clean['id']]=$clean; }
            $next=$data['_links']['next']['href'] ?? null;
            if ($next!==null) { self::next_path($next); }
            WGPP_Native_Reviews::locked($company,static function()use($company,$signature,$job,$rows,$next){
                $c=get_post_meta($company,self::CACHE,true);
                if (!$c || self::signature($company)!==$signature || $c['signature']!==$signature || $c['page']!==$job['page'] || $c['status']!=='processing') { return; }
                $c['rows']=array_replace($c['rows'],$rows);$c['page']++;$c['updated']=time();
                if (count($c['rows'])>10000 || $c['page']>40 || ($next && isset($c['seen'][$next]))) { throw new RuntimeException('Email history exceeded pilot collection limits.'); }
                if ($next) { $c['seen'][$next]=true;$c['path']=$next;$c['status']='queued'; }
                else { $c['status']='complete';$c['collected_at']=gmdate('c');unset($c['seen'],$c['path']); }
                update_post_meta($company,self::CACHE,$c);
                if ($next) { self::schedule($company,$signature); }
            });
        } catch (Throwable $e) {
            try { WGPP_Native_Reviews::locked($company,static function()use($company,$signature){
                $c=get_post_meta($company,self::CACHE,true);
                if ($c && $c['signature']===$signature) { update_post_meta($company,self::CACHE,['status'=>'failed','updated'=>time(),'signature'=>$signature]); }
            }); } catch (Throwable $ignored) {}
        }
    }
    /** Advance one bounded page for the signed-in company; cron remains a fallback. */
    public static function progress($company) {
        if(WGPP_Mailchimp::provider($company)==='mailchimp'){return WGPP_Mailchimp::progress($company);}
        if (!WGPP_Company::can('view') || (int)(WGPP_Company::context()['id'] ?? 0)!==(int)$company) { throw new RuntimeException('Company access required.'); }
        $result=self::request($company);
        if ($result['status']==='queued') {
            $job=get_post_meta($company,self::CACHE,true);
            if ($job && $job['status']==='queued') { self::collect($company,$job['signature']); }
            $result=self::request($company);
        }
        return $result;
    }
    public static function ajax() {
        nocache_headers();check_ajax_referer('wgpp_analytics','nonce');
        if (!WGPP_Company::can('view')) { wp_send_json_error(['message'=>'Company access required.'],403);return; }
        try {
            $q=[];foreach(['preset','end','start','comparison'] as $k) { if (!is_string($_GET[$k] ?? null)) { throw new InvalidArgumentException('Select valid email reporting dates.'); }$q[$k]=wp_unslash($_GET[$k]); }
            $ranges=WGPP_Analytics::ranges($q['preset'],$q['end'],$q['start'],$q['comparison']);$company=WGPP_Company::context()['id'];
            $timezone=WGPP_GA4::config($company)['timezone'] ?? 'America/Chicago';
            if ($ranges['current'][1]>(new DateTimeImmutable('yesterday',new DateTimeZone($timezone)))->format('Y-m-d')) { throw new InvalidArgumentException('Select an end date through yesterday.'); }
            $cache=self::progress($company);$result=['provider'=>WGPP_Mailchimp::label($company),'status'=>$cache['status'],'ranges'=>$ranges,'timezone'=>$timezone];
            if ($cache['status']==='complete') {
                foreach(['current','comparison'] as $k) { if($ranges[$k]===null){$result[$k]=null;continue;} $result[$k]=self::summarize($cache['rows'],$ranges[$k][0],$ranges[$k][1],$timezone); }
                $result['collected_at']=$cache['collected_at'];
            }
            wp_send_json_success($result);
        } catch (InvalidArgumentException $e) { wp_send_json_error(['message'=>$e->getMessage()],400); }
        catch (Throwable $e) { wp_send_json_error(['message'=>'Email reporting unavailable. Check the connection and retry.'],503); }
    }
}


/** Company-scoped Mailchimp campaign reporting. No contact records or campaign writes. */
final class WGPP_Mailchimp {
    const AUTH='_wgpp_mc_auth_v1';
    const PENDING='_wgpp_mc_pending_v1';
    const CACHE='_wgpp_mc_reports_v1';
    public static function boot(){foreach(['verify','connect','disconnect','provider'] as $op){add_action('admin_post_wgpp_mc_'.$op,[__CLASS__,$op==='provider'?'save_provider':$op]);}}
    public static function provider($company){$p=get_post_meta($company,'_wgpp_email_provider',true);return in_array($p,['mailchimp','constant_contact','none'],true)?$p:(get_post_meta($company,WGPP_Constant_Contact::TOKEN,true)?'constant_contact':'');}
    public static function label($company){return ['mailchimp'=>'Mailchimp','constant_contact'=>'Constant Contact','none'=>'Email reporting not used',''=>'Choose an email provider'][self::provider($company)];}
    private static function owner(){if(!WGPP_Company::can('connections')){throw new RuntimeException('Only the company owner can manage reporting connections.');}return WGPP_Company::context();}
    private static function finish($message){set_transient('wgpp_notice_'.get_current_user_id(),$message,120);wp_safe_redirect(WGPP_Company::connection_url('email'));exit;}
    public static function credentials($key){if(!is_string($key)||!preg_match('/^[a-f0-9]{32}-(us[0-9]{1,3})$/Di',$key,$m)){throw new RuntimeException('Use the complete Mailchimp API key, including its server suffix.');}return ['key'=>$key,'server'=>strtolower($m[1])];}
    public static function api($auth,$path,$query=[]){
        if(!is_array($auth)||!preg_match('/^us[0-9]{1,3}$/D',$auth['server']??'')||!in_array($path,['/','/lists','/reports'],true)){throw new RuntimeException('Invalid Mailchimp reporting request.');}
        $url='https://'.$auth['server'].'.api.mailchimp.com/3.0'.$path.($query?'?'.http_build_query($query,'','&',PHP_QUERY_RFC3986):'');
        $r=wp_safe_remote_get($url,['timeout'=>15,'redirection'=>0,'limit_response_size'=>2097152,'headers'=>['Authorization'=>'Bearer '.$auth['key'],'Accept'=>'application/json']]);
        if(is_wp_error($r)||wp_remote_retrieve_response_code($r)!==200){throw new RuntimeException('Mailchimp could not verify or read this account. Check the key and account permissions.');}
        $data=json_decode(wp_remote_retrieve_body($r),true);if(!is_array($data)){throw new RuntimeException('Mailchimp returned an incomplete report.');}return $data;
    }
    private static function write($company,$key,$v){if(!update_post_meta($company,$key,$v)&&get_post_meta($company,$key,true)!==$v){throw new RuntimeException('Could not save the Mailchimp connection.');}}
    public static function save_provider(){
        check_admin_referer('wgpp_mc_provider');try{$c=self::owner();$p=$_POST['email_provider']??'';if(!is_string($p)||!in_array($p,['mailchimp','constant_contact','none'],true)){throw new RuntimeException('Choose Mailchimp, Constant Contact, or no email reporting.');}self::write($c['id'],'_wgpp_email_provider',$p);self::finish($p==='none'?'Email reporting skipped. You can add it later.':(($p==='mailchimp' && get_post_meta($c['id'],self::AUTH,true)) || ($p==='constant_contact' && get_post_meta($c['id'],WGPP_Constant_Contact::TOKEN,true))?'Email provider saved. Your existing connection remains in place.':'Email provider saved. Complete the connection below.'));}catch(RuntimeException $e){self::finish($e->getMessage());}
    }
    public static function verify(){
        check_admin_referer('wgpp_mc_verify');try{$c=self::owner();$key=$_POST['mc_key']??'';if(!is_string($key)){throw new RuntimeException('Enter this company’s Mailchimp API key.');}$auth=self::credentials(trim(wp_unslash($key)));
            $account=self::api($auth,'/',['fields'=>'account_name']);$lists=self::api($auth,'/lists',['fields'=>'lists.id,lists.name,total_items','count'=>1000]);
            if(!is_string($account['account_name']??null)||!is_array($lists['lists']??null)||!is_int($lists['total_items']??null)||$lists['total_items']!==count($lists['lists'])||!$lists['lists']){throw new RuntimeException('No complete audience list was returned. Check the Mailchimp account.');}
            $choices=[];foreach($lists['lists'] as $list){if(!is_string($list['id']??null)||!preg_match('/^[a-zA-Z0-9]{1,64}$/D',$list['id'])||!is_string($list['name']??null)){throw new RuntimeException('Invalid Mailchimp audience.');}$choices[$list['id']]=sanitize_text_field($list['name']);}
            $auth['account']=sanitize_text_field($account['account_name']);$auth['choices']=$choices;$auth['user']=get_current_user_id();$auth['expires']=time()+600;
            WGPP_Native_Reviews::locked($c['id'],static function()use($c,$auth){self::owner();self::write($c['id'],self::PENDING,WGPP_Google::encrypt($auth,'mailchimp:pending:'.$c['id']));});self::finish('Mailchimp account verified. Select this company’s audience below to finish connecting.');
        }catch(RuntimeException $e){self::finish($e->getMessage());}
    }
    public static function connect(){
        check_admin_referer('wgpp_mc_connect');try{$c=self::owner();$id=$_POST['mc_audience']??'';if(!is_string($id)){throw new RuntimeException('Select the company audience.');}
            WGPP_Native_Reviews::locked($c['id'],static function()use($c,$id){self::owner();$a=WGPP_Google::decrypt(get_post_meta($c['id'],self::PENDING,true),'mailchimp:pending:'.$c['id']);
                if(($a['user']??0)!==get_current_user_id()||($a['expires']??0)<=time()||!isset($a['choices'][$id])){throw new RuntimeException('Account verification expired or the audience was not offered. Verify again.');}
                $a['audience']=$id;$a['audience_name']=$a['choices'][$id];unset($a['choices'],$a['expires']);self::write($c['id'],self::AUTH,WGPP_Google::encrypt($a,'mailchimp:auth:'.$c['id']));self::write($c['id'],'_wgpp_email_provider','mailchimp');delete_post_meta($c['id'],self::PENDING);delete_post_meta($c['id'],self::CACHE);
            });self::finish('Mailchimp connected. Only the selected audience’s campaign results will appear in this company.');
        }catch(RuntimeException $e){self::finish($e->getMessage());}
    }
    public static function disconnect(){check_admin_referer('wgpp_mc_disconnect');try{$c=self::owner();WGPP_Native_Reviews::locked($c['id'],static function()use($c){self::owner();foreach([self::AUTH,self::PENDING,self::CACHE] as $key){delete_post_meta($c['id'],$key);}});self::finish('Mailchimp disconnected for this company.');}catch(RuntimeException $e){self::finish($e->getMessage());}}
    private static function form($op){echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('wgpp_mc_'.$op);echo '<input type="hidden" name="action" value="wgpp_mc_'.esc_attr($op).'">';}
    public static function render(){
        $c=WGPP_Company::context();if(!$c){return '';}$p=self::provider($c['id']);ob_start();echo '<section class="wgpa-account-card" data-connection="email"><h4>2. Email reporting <small>Optional</small></h4><p>Choose the service this company uses. Profile links and website settings do not connect email reporting.</p>';
        if(WGPP_Company::can('connections')){echo '<details'.($p?'':' open').'><summary>'.($p?'Change email provider':'Choose email provider').'</summary>';self::form('provider');echo '<label>Email provider <select name="email_provider" required><option value="">Choose your provider</option>';foreach(['mailchimp'=>'Mailchimp','constant_contact'=>'Constant Contact','none'=>'No email reporting'] as $v=>$label){echo '<option value="'.esc_attr($v).'"'.($p===$v?' selected':'').'>'.esc_html($label).'</option>';}echo '</select></label> <button>Save email provider</button></form></details>';}
        if($p==='constant_contact'){echo WGPP_Constant_Contact::render();}
        elseif($p==='mailchimp'){
            $a=null;try{$saved=get_post_meta($c['id'],self::AUTH,true);if($saved){$a=WGPP_Google::decrypt($saved,'mailchimp:auth:'.$c['id']);}}catch(RuntimeException $e){}
            echo '<h4>Mailchimp · '.($a?'Connected':'Not connected').'</h4>';
            if($a){echo '<p>Account: <strong>'.esc_html($a['account']).'</strong><br>Audience: <strong>'.esc_html($a['audience_name']).'</strong></p>';if(WGPP_Company::can('connections')){self::form('disconnect');echo '<button>Disconnect Mailchimp</button></form>';}}
            elseif(WGPP_Company::can('connections')){
                $pending=null;try{$saved=get_post_meta($c['id'],self::PENDING,true);if($saved){$pending=WGPP_Google::decrypt($saved,'mailchimp:pending:'.$c['id']);}}catch(RuntimeException $e){}
                if($pending && ($pending['user']??0)===get_current_user_id() && ($pending['expires']??0)>time()){
                    echo '<p>Verified Mailchimp account: <strong>'.esc_html($pending['account']).'</strong>. Choose the audience belonging to '.esc_html($c['name']).'.</p>';self::form('connect');echo '<label>Company audience <select name="mc_audience" required><option value="">Choose the company audience</option>';foreach($pending['choices'] as $id=>$name){echo '<option value="'.esc_attr($id).'">'.esc_html($name).'</option>';}echo '</select></label> <button>Connect selected audience</button></form>';
                }else{
                    echo '<p>One-time setup: enter this company’s Mailchimp API key, verify the account, then choose its audience. In Mailchimp, find API keys under Profile → Extras → API keys.</p><p class="wgpa-small-note">A Mailchimp API key grants full account access. This connection only reads aggregate campaign reports; it does not send emails or read contact lists. The key is encrypted and never displayed after saving.</p>';
                    self::form('verify');echo '<label>Mailchimp API key <input type="password" name="mc_key" autocomplete="new-password" required></label> <button>Verify Mailchimp account</button></form>';
                }
            }else{echo '<p>Your company owner can connect Mailchimp here.</p>';}
        }elseif($p==='none'){echo '<p>Email reporting is turned off for this company. You can still use website and social reporting.</p>';}
        echo '</section>';return ob_get_clean();
    }
    public static function normalize($r){
        if(!is_array($r)||!is_string($r['id']??null)||!preg_match('/^[a-zA-Z0-9_-]{1,100}$/D',$r['id'])||!is_string($r['send_time']??null)||!preg_match('/^\\d{4}-\\d{2}-\\d{2}T/',$r['send_time'])){throw new RuntimeException('Invalid Mailchimp campaign report.');}
        try{$sent=new DateTimeImmutable($r['send_time']);}catch(Exception $e){throw new RuntimeException('Invalid Mailchimp send date.');}
        $values=['sends'=>$r['emails_sent']??null,'opens'=>$r['opens']['unique_opens']??null,'clicks'=>$r['clicks']['unique_subscriber_clicks']??null,'bounces'=>null,'optouts'=>$r['unsubscribed']??null];
        foreach(['hard_bounces','soft_bounces'] as $key){if(!is_int($r['bounces'][$key]??null)||$r['bounces'][$key]<0){throw new RuntimeException('Incomplete Mailchimp bounce count.');}}$values['bounces']=$r['bounces']['hard_bounces']+$r['bounces']['soft_bounces'];
        foreach($values as $v){if(!is_int($v)||$v<0||$v>1000000000000){throw new RuntimeException('Incomplete Mailchimp campaign counts.');}}
        if($values['bounces']>$values['sends']||$values['opens']>$values['sends']||$values['clicks']>$values['sends']){throw new RuntimeException('Mailchimp campaign counts do not reconcile.');}
        return ['id'=>$r['id'],'sent'=>$sent->format('c'),'counts'=>$values];
    }
    private static function signature($company){return hash('sha256',(string)get_post_meta($company,self::AUTH,true));}
    public static function request($company){
        if(!get_post_meta($company,self::AUTH,true)){return ['status'=>'not_connected'];}$sig=self::signature($company);
        return WGPP_Native_Reviews::locked($company,static function()use($company,$sig){$cache=get_post_meta($company,self::CACHE,true);
            if(is_array($cache)&&($cache['signature']??'')===$sig){if($cache['status']==='complete'&&$cache['updated']>time()-21600){return $cache;}if(in_array($cache['status'],['queued','processing'],true)&&$cache['updated']>time()-900){return $cache;}if($cache['status']==='failed'&&$cache['updated']>time()-300){return ['status'=>'failed'];}}
            $cache=['status'=>'queued','signature'=>$sig,'updated'=>time(),'offset'=>0,'rows'=>[]];self::write($company,self::CACHE,$cache);return $cache;
        });
    }
    public static function progress($company){
        if(!WGPP_Company::can('view')||(int)(WGPP_Company::context()['id']??0)!==(int)$company){throw new RuntimeException('Company access required.');}
        $cache=self::request($company);if($cache['status']!=='queued'){return $cache;}$sig=self::signature($company);
        try{
            $job=WGPP_Native_Reviews::locked($company,static function()use($company,$sig){$j=get_post_meta($company,self::CACHE,true);if(!$j||$j['status']!=='queued'||$j['signature']!==$sig){return null;}$j['status']='processing';$j['updated']=time();self::write($company,self::CACHE,$j);return $j;});if(!$job){return ['status'=>'processing'];}
            $a=WGPP_Google::decrypt(get_post_meta($company,self::AUTH,true),'mailchimp:auth:'.$company);
            $data=self::api($a,'/reports',['count'=>100,'offset'=>$job['offset'],'fields'=>'total_items,reports.id,reports.list_id,reports.send_time,reports.emails_sent,reports.opens.unique_opens,reports.clicks.unique_subscriber_clicks,reports.bounces.hard_bounces,reports.bounces.soft_bounces,reports.unsubscribed']);
            if(!is_array($data['reports']??null)||!is_int($data['total_items']??null)||$data['total_items']<0||$data['total_items']>10000||count($data['reports'])>100||(!$data['reports']&&$job['offset']<$data['total_items'])){throw new RuntimeException('Incomplete Mailchimp campaign history.');}
            $rows=[];foreach($data['reports'] as $r){if(!is_string($r['list_id']??null)){throw new RuntimeException('Mailchimp campaign audience is missing.');}if($r['list_id']===$a['audience']){$row=self::normalize($r);$rows[$row['id']]=$row;}}
            return WGPP_Native_Reviews::locked($company,static function()use($company,$sig,$job,$rows,$data){if(self::signature($company)!==$sig){throw new RuntimeException('Mailchimp connection changed.');}$j=get_post_meta($company,self::CACHE,true);if(!$j||$j['signature']!==$sig||$j['status']!=='processing'||$j['offset']!==$job['offset']){throw new RuntimeException('Mailchimp collection changed.');}
                $j['rows']=array_replace($j['rows'],$rows);$j['offset']+=count($data['reports']);$j['updated']=time();$j['status']=$j['offset']>=$data['total_items']?'complete':'queued';if($j['status']==='complete'){$j['collected_at']=gmdate('c');}self::write($company,self::CACHE,$j);return $j;});
        }catch(Throwable $e){WGPP_Native_Reviews::locked($company,static function()use($company,$sig){if(self::signature($company)===$sig){self::write($company,self::CACHE,['signature'=>$sig,'updated'=>time(),'status'=>'failed']);}});return ['status'=>'failed'];}
    }
}
