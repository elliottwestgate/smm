<?php
if (!defined('ABSPATH')) { exit; }
final class WGPP_GA4 {
    const CONFIG='_wgpp_ga4_property_v1';
    const CACHE='_wgpp_ga4_cache_v1';
    public static function boot() {
        add_action('admin_post_wgpp_ga4_property',[__CLASS__,'save']);
        add_action('wgpp_collect_ga4',[__CLASS__,'collect'],10,2);
        add_filter('wgpp_analytics_snapshot',[__CLASS__,'snapshot'],10,2);
    }
    public static function config($company) {
        $c=get_post_meta($company,self::CONFIG,true);
        return is_array($c) && get_post_meta($company,WGPP_Google::TOKEN,true) ? $c : null;
    }
    public static function identity($property) {
        if (!is_string($property) || !preg_match('/^[1-9][0-9]{0,19}$/D',$property)) { throw new InvalidArgumentException('Enter the numeric GA4 property ID, not the G- measurement ID.'); }
        return $property;
    }
    public static function goals($value) {
        if (!is_string($value)) { throw new InvalidArgumentException('Enter event names.'); }
        $names=array_unique(array_filter(array_map('trim',explode(',',$value))));
        if (!$names || count($names)>10) { throw new InvalidArgumentException('Use one to ten event names per goal type.'); }
        foreach($names as $name) { if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,39}$/D',$name)) { throw new InvalidArgumentException('Invalid GA4 event name.'); } }
        return array_values($names);
    }
    public static function query($company,$property,$from,$to,$dimensions,$metrics,$limit=1000,$filter=null) {
        $body=['dateRanges'=>[['startDate'=>$from,'endDate'=>$to]],'dimensions'=>array_map(static function($v){return ['name'=>$v];},$dimensions),'metrics'=>array_map(static function($v){return ['name'=>$v];},$metrics),'limit'=>$limit,'returnPropertyQuota'=>true];
        if($filter!==null){$body['dimensionFilter']=$filter;}
        if ($dimensions) { $body['orderBys']=[['metric'=>['metricName'=>$metrics[0]],'desc'=>true]]; }
        $response=wp_safe_remote_post('https://analyticsdata.googleapis.com/v1beta/properties/'.self::identity($property).':runReport',
            ['timeout'=>25,'redirection'=>0,'limit_response_size'=>1048576,'headers'=>['Authorization'=>'Bearer '.WGPP_Google::token($company),'Content-Type'=>'application/json'],'body'=>wp_json_encode($body)]);
        if (is_wp_error($response)) { throw new RuntimeException('Google reporting request did not complete. A temporary connection or timeout may be responsible.',0); }
        $code=wp_remote_retrieve_response_code($response);
        if ($code!==200) { throw new RuntimeException('Google reporting returned HTTP '.(int)$code.'.'.(in_array($code,[401,403],true)?' Check authorization and property access.':''),(int)$code); }
        $data=json_decode(wp_remote_retrieve_body($response),true);
        return self::parse($data,$dimensions,$metrics);
    }
    public static function parse($data,$dimensions,$metrics) {
        if (!is_array($data) || array_column($data['dimensionHeaders'] ?? [],'name')!==$dimensions || array_column($data['metricHeaders'] ?? [],'name')!==$metrics) { throw new RuntimeException('Unexpected Google report format.'); }
        $rows=[];
        foreach(($data['rows'] ?? []) as $row) {
            $ds=$row['dimensionValues'] ?? [];$ms=$row['metricValues'] ?? [];
            if (count($ds)!==count($dimensions) || count($ms)!==count($metrics)) { throw new RuntimeException('Unexpected Google report row.'); }
            $out=[];
            foreach($dimensions as $i=>$d) { $out[$d]=(string)$ds[$i]['value']; }
            foreach($metrics as $i=>$m) {
                $v=$ms[$i]['value'] ?? null;
                if (!is_numeric($v) || !is_finite((float)$v) || $v<0) { throw new RuntimeException('Invalid Google metric.'); }
                $out[$m]=(float)$v;
            }
            $rows[]=$out;
        }
        $meta=$data['metadata'] ?? [];
        $limited=!empty($meta['subjectToThresholding']) || !empty($meta['dataLossFromOtherRow']) || !empty($meta['samplingMetadatas']) || ($data['rowCount'] ?? count($rows))>count($rows);
        return ['rows'=>$rows,'limited'=>$limited,'timezone'=>$meta['timeZone'] ?? null,'metadata'=>$meta];
    }
    public static function save() {
        if (!WGPP_Company::can('connections')) { wp_die('Company owner access required.','',['response'=>403]); }
        check_admin_referer('wgpp_ga4_property');
        try {
            $company=WGPP_Company::context()['id'];
            $property=self::identity($_POST['property'] ?? null);
            $forms=self::goals(wp_unslash($_POST['form_events'] ?? ''));
            $phones=self::goals(wp_unslash($_POST['phone_events'] ?? ''));
            if (array_intersect($forms,$phones)) { throw new InvalidArgumentException('Form and phone event definitions cannot overlap.'); }
            $grant=get_post_meta($company,'_wgpp_google_grant',true);
            $probe=self::query($company,$property,'7daysAgo','yesterday',[],['sessions'],1);
            $tz=$probe['timezone'];
            if (!is_string($tz) || !in_array($tz,timezone_identifiers_list(),true)) { throw new RuntimeException('Google property timezone was not returned.'); }
            WGPP_Native_Reviews::locked($company,static function()use($company,$property,$forms,$phones,$tz,$grant){
                if(get_post_meta($company,'_wgpp_google_grant',true)!==$grant){throw new RuntimeException('Google authorization changed. Verify the property again.');}
                update_post_meta($company,self::CONFIG,['property'=>$property,'form_events'=>$forms,'phone_events'=>$phones,'timezone'=>$tz]);
                delete_post_meta($company,self::CACHE);
            });
            set_transient('wgpp_notice_'.get_current_user_id(),'GA4 property verified. Apply dates to queue reporting.',120);
        } catch (Throwable $e) { set_transient('wgpp_notice_'.get_current_user_id(),$e->getMessage(),120); }
        wp_safe_redirect(WGPP_Company::connection_url('google'));exit;
    }
    private static function signature($company,$config) { return hash('sha256',wp_json_encode($config).'|'.get_post_meta($company,'_wgpp_google_grant',true)); }
    public static function snapshot($prior,$q) {
        $company=$q['company_id'];$config=self::config($company);
        if (!$config) { return $prior; }
        $last=new DateTimeImmutable('yesterday',new DateTimeZone($config['timezone']));
        if ($q['to']>$last->format('Y-m-d')) { return null; }
        $key=hash('sha256',$q['from'].'|'.$q['to'].'|'.self::signature($company,$config));
        return WGPP_Native_Reviews::locked($company,static function()use($company,$config,$q,$key){
            $cache=get_post_meta($company,self::CACHE,true) ?: [];
            $entry=$cache[$key] ?? null;
            if ($entry && ($entry['status'] ?? '')==='complete' && $entry['updated']>time()-21600) { return $entry['snapshot']; }
            if ($entry && in_array($entry['status'],['queued','processing'],true) && $entry['updated']>time()-900) { return null; }
            if ($entry && $entry['status']==='failed' && $entry['updated']>time()-300) { return null; }
            // Bound retained ranges, including abandoned collection requests.
            if (count($cache)>=24) { uasort($cache,static function($a,$b){return $a['updated']<=>$b['updated'];});unset($cache[array_key_first($cache)]); }
            $cache[$key]=['status'=>'queued','updated'=>time(),'from'=>$q['from'],'to'=>$q['to'],'config'=>$config,'signature'=>self::signature($company,$config),'step'=>0,'reports'=>[]];
            update_post_meta($company,self::CACHE,$cache);
            $scheduled=wp_schedule_single_event(time()+1,'wgpp_collect_ga4',[$company,$key],true);
            // Scheduling can reject an already queued event; dashboard polling still advances this job.
            return null;
        });
    }
    public static function status($company,$from,$to) {
        $config=self::config($company);if (!$config) { return 'not_connected'; }
        $key=hash('sha256',$from.'|'.$to.'|'.self::signature($company,$config));
        $cache=get_post_meta($company,self::CACHE,true) ?: [];
        return $cache[$key]['status'] ?? 'unavailable';
    }
    /** One bounded report per authenticated poll; cron remains a fallback. */
    public static function progress($company,$ranges) {
        if (!WGPP_Company::can('view') || (int)(WGPP_Company::context()['id'] ?? 0)!==(int)$company) { throw new RuntimeException('Company access required.'); }
        $config=self::config($company);if (!$config) { return; }
        $cache=get_post_meta($company,self::CACHE,true) ?: [];
        foreach ($ranges as [$from,$to]) {
            $key=hash('sha256',$from.'|'.$to.'|'.self::signature($company,$config));
            if (($cache[$key]['status'] ?? '')==='queued') { self::collect($company,$key);return; }
        }
    }
    public static function detail($company,$from,$to) {
        $config=self::config($company);if(!$config){return '';}
        $key=hash('sha256',$from.'|'.$to.'|'.self::signature($company,$config));
        $cache=get_post_meta($company,self::CACHE,true) ?: [];$job=$cache[$key] ?? null;if(!$job){return '';}
        $labels=['Traffic totals','New and returning users','Goal events','Traffic sources','Age','Gender','Country','City','Device platform'];
        $step=min(8,(int)($job['step'] ?? 0));
        return ($job['status']==='failed'?'Stopped at ':'Report '.($step+1).' of 9: ').$labels[$step].'. '.($job['error'] ?? '');
    }
    public static function specifications() {
        return [[[],['sessions','totalUsers','newUsers']], [['newVsReturning'],['totalUsers']], [['eventName'],['eventCount']], [['sessionSourceMedium','sessionDefaultChannelGroup'],['sessions']],
            [['userAgeBracket'],['sessions']], [['userGender'],['sessions']], [['country'],['sessions']], [['city'],['sessions']], [['deviceCategory'],['sessions']]];
    }
    public static function collect($company,$key) {
        try {
            $job=WGPP_Native_Reviews::locked($company,static function()use($company,$key){
                $cache=get_post_meta($company,self::CACHE,true) ?: [];$entry=$cache[$key] ?? null;
                if (!$entry || $entry['status']!=='queued') { return null; }
                $cache[$key]['status']='processing';$cache[$key]['updated']=time();update_post_meta($company,self::CACHE,$cache);return $entry;
            });
            if (!$job) { return; }
            $config=self::config($company);$company_post=get_post($company);
            if (!$config || self::signature($company,$config)!==$job['signature'] || !$company_post || !apply_filters('wgpp_company_entitled',true,$company,$company_post->post_author)) { throw new RuntimeException('Reporting connection changed.'); }
            [$dimensions,$metrics]=self::specifications()[$job['step']];
            try { $result=self::query($company,$config['property'],$job['from'],$job['to'],$dimensions,$metrics,$job['step']===3?500:1000); }
            catch (RuntimeException $e) { if ($job['step']<4) { throw $e; } $result=null; }
            if ($result && $result['timezone'] && $result['timezone']!==$config['timezone']) { throw new RuntimeException('Reporting timezone changed.'); }
            WGPP_Native_Reviews::locked($company,static function()use($company,$key,$job,$result){
                $config=self::config($company);$cache=get_post_meta($company,self::CACHE,true) ?: [];
                if (!$config || self::signature($company,$config)!==$job['signature'] || ($cache[$key]['step'] ?? -1)!==$job['step']) { return; }
                $entry=$cache[$key];$entry['reports'][]=$result;unset($entry['error'],$entry['attempts']);$entry['step']++;$entry['updated']=time();
                if ($entry['step']===count(self::specifications())) {
                    $entry['snapshot']=self::assemble($company,$entry);$entry['status']='complete';unset($entry['reports']);
                } else { $entry['status']='queued'; }
                $cache[$key]=$entry;update_post_meta($company,self::CACHE,$cache);
                if ($entry['status']==='queued') {
                    $scheduled=wp_schedule_single_event(time()+1,'wgpp_collect_ga4',[$company,$key],true);
                    // Scheduling can reject an already queued event; dashboard polling still advances this job.
                }
            });
        } catch (Throwable $e) {
            try { WGPP_Native_Reviews::locked($company,static function()use($company,$key,$e){$cache=get_post_meta($company,self::CACHE,true) ?: [];if(isset($cache[$key])){
                    $entry=$cache[$key];$attempts=(int)($entry['attempts'] ?? 0)+1;
                    $temporary=in_array((int)$e->getCode(),[0,408,429,500,502,503,504],true);
                    $entry['status']=$temporary && $attempts<3?'queued':'failed';$entry['attempts']=$attempts;$entry['updated']=time();
                    $entry['error']=$e instanceof RuntimeException?$e->getMessage():'Report processing failed.';
                    $cache[$key]=$entry;update_post_meta($company,self::CACHE,$cache);
                }}); } catch(Throwable $ignored) {}
        }
    }
    public static function assemble($company,$job) {
        $reports=$job['reports'];$c=$job['config'];$totals=$reports[0]['rows'][0] ?? [];
        $metrics=['sessions'=>$totals['sessions'] ?? ($reports[0]['limited']?null:0),'users'=>$totals['totalUsers'] ?? ($reports[0]['limited']?null:0),'new_users'=>$totals['newUsers'] ?? ($reports[0]['limited']?null:0),'returning_users'=>$reports[1]['limited']?null:0];
        foreach($reports[1]['rows'] as $row) { if ($row['newVsReturning']==='returning') { $metrics['returning_users']=$row['totalUsers']; } }
        foreach(['form_events','phone_events'] as $goal) {
            $metrics[$goal]=$reports[2]['limited']?null:0;
            foreach($reports[2]['rows'] as $row) { if(!$reports[2]['limited'] && in_array($row['eventName'],$c[$goal],true)) { $metrics[$goal]=($metrics[$goal] ?? 0)+$row['eventCount']; } }
        }
        $sources=[];foreach($reports[3]['rows'] as $row) { $sources[]=['source_medium'=>$row['sessionSourceMedium'],'channel'=>$row['sessionDefaultChannelGroup'],'sessions'=>$row['sessions']]; }
        $profile=[];$notes=['Google data through yesterday may still be revised. Recorded goal events are not unique customers or verified sales/calls.'];
        foreach(['age','gender','country','city','device'] as $i=>$name) {
            $r=$reports[$i+4];$profile[$name]=null;
            if ($r!==null) { $d=self::specifications()[$i+4][0][0];$profile[$name]=[];foreach($r['rows'] as $row){$profile[$name][]=['label'=>$row[$d],'sessions'=>$row['sessions']];} }
            else { $notes[]='Audience '.$name.' report unavailable.'; }
        }
        foreach($reports as $i=>$r) { if ($r && $r['limited']) { $notes[]='Report section '.$i.' may be thresholded, sampled or row-limited. Do not assume omitted rows are zero.'; } }
        $post=get_post($company);
        return ['from'=>$job['from'],'to'=>$job['to'],'property'=>$c['property'],'company'=>$post->post_title,'website'=>'','timezone'=>$c['timezone'],'collected_at'=>gmdate('c'),'metrics'=>$metrics,'sources'=>$sources,'profile'=>$profile,'notes'=>$notes];
    }
    public static function render($company) {
        if (!WGPP_Company::can('connections')) { return ''; }
        $c=self::config($company) ?: [];
        ob_start();echo '<h4>GA4 property and goal events</h4><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('wgpp_ga4_property');
        echo '<input type="hidden" name="action" value="wgpp_ga4_property"><p><label>Numeric GA4 property ID <input name="property" inputmode="numeric" pattern="[0-9]+" value="'.esc_attr($c['property'] ?? '').'" required></label></p>';
        foreach(['form_events'=>'Form event names','phone_events'=>'Phone event names'] as $key=>$label) { echo '<p><label>'.esc_html($label).' <input name="'.esc_attr($key).'" value="'.esc_attr(implode(',',$c[$key] ?? [$key==='form_events'?'form_submission':'phone_call'])).'" required></label></p>'; }
        echo '<p>Use comma-separated recorded event names. Saving verifies property access and timezone. Numeric property ID is available in GA4 Property details; it is different from the G- measurement ID.</p><button>Verify and save property</button></form>';
        if ($c) { echo '<p>Selected property '.esc_html($c['property']).' · '.esc_html($c['timezone']).'. Date requests collect reports automatically while this dashboard is open. Cached reports refresh after six hours.</p>'; }
        return ob_get_clean();
    }
}
