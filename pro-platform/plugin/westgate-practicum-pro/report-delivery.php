<?php
if (!defined('ABSPATH')) { exit; }

/** Private, company-scoped delivery. No public report files or unauthenticated routes. */
final class WGPP_Report_Delivery {
    const META = '_wgpp_report_delivery_v1';
    const HOOK = 'wgpp_report_delivery';
    const VERSION = '0.6.34-preview';
    private static $progressNote='Preparing reporting data. No email sent.';
    public static function boot() {
        add_action('admin_post_wgpp_report_delivery', [__CLASS__, 'handle']);
        add_action(self::HOOK, [__CLASS__, 'run'], 10, 1);
        add_action('wp_ajax_wgpp_delivery_progress',[__CLASS__,'ajax']);
    }
    public static function activate() {
        $page=1;
        do{$ids=get_posts(['post_type'=>WGPP_Company::TYPE,'post_status'=>'private','meta_key'=>self::META,'fields'=>'ids','posts_per_page'=>100,'paged'=>$page++,'orderby'=>'ID','order'=>'ASC']);
            foreach($ids as $company){try{self::wake((int)$company,time()+60);}catch(Throwable $e){}}
        }while(count($ids)===100);
    }
    /** An authenticated dashboard can advance already-confirmed work if WP-Cron is late. */
    public static function ajax() {
        check_ajax_referer('wgpp_delivery_progress','nonce');nocache_headers();
        try{
            $c=WGPP_Company::context();if(!$c){throw new RuntimeException('Company access required.');}
            self::access($c['id'],get_current_user_id());
            if(($_SERVER['REQUEST_METHOD'] ?? '')!=='POST'){throw new RuntimeException('Use a protected POST request.');}
            if(($_POST['advance'] ?? '')==='1'){self::run($c['id']);}
            wp_send_json_success(['jobs'=>self::public_jobs($c['id'])]);
        }catch(RuntimeException $e){wp_send_json_error(['message'=>$e->getMessage()],403);}
    }
    public static function public_jobs($company) {
        $out=[];foreach(array_reverse(self::state($company)['jobs']) as $j){
            $out[]=['id'=>$j['id'],'status'=>$j['status'],'created'=>self::stamp($j['created']),
                'updated'=>self::stamp($j['updated']),'attempts'=>(int)$j['attempts'],
                'type'=>$j['review']?'Pro Analytics Review':'Analytics Report',
                'period'=>implode(' to ',array_map(static function($d){return WGPP_Analytics::date($d)->format('m/d/Y');},$j['ranges']['current'])),
                'message'=>$j['message'] ?? 'Waiting for the delivery worker. No email sent.',
                'recipients'=>$j['recipients']];
        }return $out;
    }
    public static function state($company) {
        $v = get_post_meta($company, self::META, true);
        return self::prune(is_array($v) ? array_merge(['schedules'=>[], 'jobs'=>[]], $v) : ['schedules'=>[], 'jobs'=>[]]);
    }
    private static function write($company, $state) {
        if (get_post_meta($company,self::META,true) !== $state && !update_post_meta($company, self::META, $state)) {
            throw new RuntimeException('Could not save Report Delivery. Please try again.');
        }
    }
    public static function recipients($value) {
        if (!is_string($value) || strlen($value)>2500 || preg_match('/[\r\n]/', $value)) {
            throw new InvalidArgumentException('Enter email addresses separated by commas or semicolons.');
        }
        $out=[];
        foreach (preg_split('/[,;]+/', $value) as $email) {
            $email=trim($email);
            if (!$email || strlen($email)>254 || !is_email($email)) { throw new InvalidArgumentException('Check every recipient email address.'); }
            $out[strtolower($email)]=$email;
        }
        if (!$out || count($out)>10) { throw new InvalidArgumentException('Use one to ten recipient addresses.'); }
        return array_values($out);
    }
    public static function access($company, $user, $owner=false) {
        $c=WGPP_Company::for_user($company, $user);
        if (!$c || !in_array($c['role'], $owner?['owner']:['owner','analyst'], true)) {
            throw new RuntimeException($owner?'Company owner access required.':'Owner or Analyst access required.');
        }
        return $c;
    }
    public static function central($stamp) {
        return (new DateTimeImmutable('@'.(int)$stamp))->setTimezone(new DateTimeZone('America/Chicago'));
    }
    public static function stamp($stamp) { return self::central($stamp)->format('m/d/Y g:i A').' Central Time'; }
    public static function schedule($input, $now) {
        $frequency=$input['frequency'] ?? '';
        if (!in_array($frequency,['weekly','monthly','four_weeks'],true)) { throw new InvalidArgumentException('Choose Weekly, Monthly or Every 4 weeks.'); }
        $start=$input['first_send'] ?? '';WGPP_Analytics::date($start);
        $time=$input['send_time'] ?? '';
        if (!is_string($time) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$time)) { throw new InvalidArgumentException('Choose a valid Central Time delivery time.'); }
        $at=new DateTimeImmutable($start.' '.$time, new DateTimeZone('America/Chicago'));
        if ($at->getTimestamp()<=$now) { throw new InvalidArgumentException('Choose a first delivery date and time in the future.'); }
        if ($frequency==='monthly' && (int)$at->format('j')>28) { throw new InvalidArgumentException('For monthly delivery, choose a day from the 1st through the 28th.'); }
        $compare=$input['comparison'] ?? 'prior';
        if (!in_array($compare,['none','prior','year'],true)) { throw new InvalidArgumentException('Choose a comparison.'); }
        return ['frequency'=>$frequency,'time'=>$time,'anchor'=>$start,'next'=>$at->getTimestamp(),'comparison'=>$compare,'recipients'=>self::recipients($input['recipients'] ?? ''),'enabled'=>true];
    }
    public static function next_date($s, $stamp) {
        $at=self::central($stamp);
        return $at->modify(['weekly'=>'+7 days','monthly'=>'+1 month','four_weeks'=>'+28 days'][$s['frequency']])->getTimestamp();
    }
    public static function period($s, $stamp) {
        $day=self::central($stamp)->setTime(0,0);
        if ($s['frequency']==='monthly') { $start=$day->modify('first day of last month');$end=$day->modify('first day of this month')->modify('-1 day'); }
        elseif ($s['frequency']==='weekly') { $thisMonday=$day->modify('-'.((int)$day->format('N')-1).' days');$end=$thisMonday->modify('-1 day');$start=$end->modify('-6 days'); }
        else { $end=$day->modify('-1 day');$start=$end->modify('-27 days'); }
        return WGPP_Analytics::ranges('custom',$end->format('Y-m-d'),$start->format('Y-m-d'),$s['comparison']);
    }
    /** Keep one earliest wake-up per company, without silently losing scheduling errors. */
    public static function wake($company, $at) {
        $args=[(int)$company];$old=wp_next_scheduled(self::HOOK,$args);
        if ($old && $old<=$at) { return; }
        if ($old) { wp_unschedule_event($old,self::HOOK,$args); }
        $ok=wp_schedule_single_event($at,self::HOOK,$args,true);
        if (is_wp_error($ok) || !$ok) { throw new RuntimeException('WordPress could not schedule delivery. Save again or ask your administrator to check WP-Cron.'); }
    }
    private static function new_job($company,$user,$ranges,$recipients,$review='',$note='') {
        return ['id'=>wp_generate_uuid4(),'principal'=>$user,'origin'=>home_url('/'),'created'=>time(),'updated'=>time(),'status'=>'queued','attempts'=>0,'ranges'=>$ranges,'review'=>$review,'note'=>$note,'recipients'=>array_fill_keys($recipients,'queued')];
    }
    private static function prune($state) {
        // Display/store five terminal deliveries; keep pending work and compact 24-hour
        // request hashes separately so deleting history cannot resend or bypass rate limits.
        $receipts=$state['requests'] ?? [];foreach($state['jobs'] as $j){if(!empty($j['request_id'])){$receipts[hash('sha256',$j['request_id'])]=$j['created'];}}
        $state['requests']=array_filter($receipts,static function($at){return $at>time()-86400;});
        $terminal=array_filter($state['jobs'],static function($j){return !in_array($j['status'],['queued','processing','sending'],true);});
        uasort($terminal,static function($a,$b){return (($b['updated'] ?? $b['created'])<=>($a['updated'] ?? $a['created'])) ?: ($b['created']<=>$a['created']);});
        foreach(array_slice(array_keys($terminal),5) as $id){unset($state['jobs'][$id]);}return $state;
    }
    private static function cancel_job(&$job){
        if(!in_array($job['status'],['queued','processing'],true)){throw new RuntimeException('This delivery has already started sending or finished and cannot be canceled.');}
        $job['status']='cancelled';$job['updated']=time();$job['message']='Canceled. No further recipients will be emailed. Previously accepted emails cannot be recalled.';
        foreach($job['recipients'] as &$recipient){if($recipient==='queued'){$recipient='not_sent';}}unset($recipient,$job['lease'],$job['pdf'],$job['preparation']);
    }
    public static function handle() {
        check_admin_referer('wgpp_report_delivery');$c=WGPP_Company::context();
        try {
            if (!$c) { throw new RuntimeException('Company access required.'); }
            $op=$_POST['delivery_op'] ?? '';$user=get_current_user_id();
            self::access($c['id'],$user,$op!=='send');
            if (!is_string($op) || !in_array($op,['save','pause','send','delete','cancel','cancel_schedule'],true)) { throw new InvalidArgumentException('Invalid delivery action.'); }
            $schedule=$emails=$ranges=$review=null;
            $input=[];foreach(['frequency','first_send','send_time','comparison','recipients','start','end','review_id','note','request_id','job_id'] as $key) {
                $v=$_POST[$key] ?? '';if(!is_string($v)){throw new InvalidArgumentException('Invalid delivery selection.');}$input[$key]=wp_unslash($v);
            }
            if ($op==='save' && ($_POST['confirm_recipients'] ?? '')!=='1') { throw new InvalidArgumentException('Confirm that these recipients may receive this company report.'); }
            if ($op==='save') { $schedule=self::schedule($input,time());$schedule['principal']=$user;$schedule['origin']=home_url('/'); }
            if ($op==='send') {
                if (($_POST['confirm_recipients'] ?? '')!=='1') { throw new InvalidArgumentException('Confirm that these recipients may receive this company report.'); }
                $emails=self::recipients($input['recipients']);
                if(strlen($input['note'])>2000){throw new InvalidArgumentException('Keep the message within 2,000 characters.');}
                if(!preg_match('/^[a-zA-Z0-9-]{16,100}$/D',$input['request_id'])){throw new InvalidArgumentException('Reload Report Delivery before sending.');}
                $review=$input['review_id'];
                if($review){$saved=self::saved_review($c['id'],$review);$ranges=$saved['snapshot']['ranges'];}
                else{$ranges=WGPP_Analytics::ranges('custom',$input['end'],$input['start'],$input['comparison']);}
                self::past_dates($ranges,$c['id']);
            }
            if(!in_array($op,['delete','cancel','cancel_schedule'],true)){self::wake($c['id'],time()+5);}
            WGPP_Native_Reviews::locked($c['id'],static function()use($c,$user,$op,$input,$schedule,$emails,$ranges,$review){
                self::access($c['id'],$user,$op!=='send');$state=self::state($c['id']);
                if($op==='delete'){
                    $id=$input['job_id'];if(!preg_match('/^[a-zA-Z0-9-]{1,100}$/D',$id)||!isset($state['jobs'][$id])){throw new InvalidArgumentException('Delivery record is no longer available.');}
                    if(in_array($state['jobs'][$id]['status'],['queued','processing','sending'],true)){throw new RuntimeException('A pending delivery cannot be deleted.');}
                    unset($state['jobs'][$id]);
                }elseif($op==='cancel'){
                    $id=$input['job_id'];if(!isset($state['jobs'][$id])){throw new InvalidArgumentException('Delivery is no longer available.');}
                    self::cancel_job($state['jobs'][$id]);
                }elseif($op==='cancel_schedule'){
                    $frequency=$input['frequency'];if(!isset($state['schedules'][$frequency])){throw new InvalidArgumentException('Schedule is no longer available.');}
                    unset($state['schedules'][$frequency]);
                    foreach($state['jobs'] as &$scheduled){if(($scheduled['schedule'] ?? '')===$frequency && in_array($scheduled['status'],['queued','processing'],true)){self::cancel_job($scheduled);}}unset($scheduled);
                }elseif($op==='save'){$state['schedules'][$schedule['frequency']]=$schedule;}
                elseif($op==='pause'){if(!isset($state['schedules'][$input['frequency']])){throw new InvalidArgumentException('Schedule unavailable.');}$state['schedules'][$input['frequency']]['enabled']=false;}
                else {
                    if(isset($state['requests'][hash('sha256',$input['request_id'])])){return;}
                    foreach($state['jobs'] as $j){if(($j['request_id'] ?? '')===$input['request_id']){return;}}
                    $pending=array_filter($state['jobs'],static function($j){return in_array($j['status'],['queued','processing','sending'],true);});
                    if(count($pending)>=10){throw new RuntimeException('Ten deliveries are already pending. Wait for them to finish.');}
                    $recent=array_filter($state['requests'] ?? [],static function($at){return $at>time()-3600;});if(count($recent)>=20){throw new RuntimeException('The company hourly send limit has been reached. Try again later.');}
                    $job=self::new_job($c['id'],$user,$ranges,$emails,$review,sanitize_textarea_field($input['note']));$job['request_id']=$input['request_id'];$state['jobs'][$job['id']]=$job;
                }
                self::write($c['id'],self::prune($state));
            });
            $message=$op==='cancel'?'Delivery canceled. Any email already accepted cannot be recalled.':($op==='cancel_schedule'?'Schedule canceled, including its deliveries still being prepared. Emails already sending or accepted cannot be recalled.':($op==='delete'?'Delivery record deleted. No emails or schedules were changed.':($op==='save'?'Delivery schedule saved.':($op==='pause'?'Schedule paused. Pending deliveries remain listed below.':'Report queued. It will be emailed after preparation is complete. Check Delivery history for the result.'))));
        } catch (InvalidArgumentException|RuntimeException $e) { $message=$e->getMessage(); }
        set_transient('wgpp_notice_'.get_current_user_id(),$message,120);
        wp_safe_redirect(add_query_arg('workspace',$c['id'] ?? 0,WGPP_Team::portal()).'#account-delivery');exit;
    }
    private static function past_dates($ranges,$company) {
        $tz=WGPP_GA4::config($company)['timezone'] ?? 'America/Chicago';
        $last=(new DateTimeImmutable('yesterday',new DateTimeZone($tz)))->format('Y-m-d');
        foreach($ranges as $r){if($r && $r[1]>$last){throw new InvalidArgumentException('Choose reporting dates through yesterday.');}}
    }
    public static function saved_review($company,$id) {
        if(!is_string($id)||!preg_match('/^[a-zA-Z0-9-]{1,100}$/D',$id)){throw new InvalidArgumentException('Invalid saved Review.');}
        $all=get_post_meta($company,WGPP_Native_Reviews::META,true);$j=is_array($all)?($all[$id] ?? null):null;
        if(!$j || $j['status']!=='completed' || !empty($j['trashed_at'])){throw new InvalidArgumentException('Saved Review unavailable for this company.');}
        return $j;
    }
    /** Cron only adopts the job's existing principal after checking current company membership. */
    public static function run($company) {
        $company=(int)$company;$now=time();$claim=null;
        try {
            $claim=WGPP_Native_Reviews::locked($company,static function()use($company,$now){
                $s=self::state($company);
                foreach($s['schedules'] as $key=>&$plan){
                    if(empty($plan['enabled']) || $plan['next']>$now){continue;}
                    $due=$plan['next'];while(self::next_date($plan,$due)<=$now){$due=self::next_date($plan,$due);}
                    $plan['next']=self::next_date($plan,$due);
                    // A staging clone must not send copied production schedules.
                    if($plan['origin']!==home_url('/') || wp_get_environment_type()!=='production'){$plan['notice']='Automatic delivery is paused outside its original production site.';continue;}
                    try{self::access($company,$plan['principal'],true);$ranges=self::period($plan,$due);$job=self::new_job($company,$plan['principal'],$ranges,$plan['recipients']);$job['schedule']=$key;$s['jobs'][$job['id']]=$job;unset($plan['notice']);}
                    catch(Throwable $e){$plan['enabled']=false;$plan['notice']='Schedule paused: the scheduling owner no longer has access.';}
                }unset($plan);
                foreach($s['jobs'] as $id=>&$j){
                    if($j['status']==='processing' && $j['updated']<$now-600){$j['status']='queued';}
                    if($j['status']==='sending' && $j['updated']<$now-600){$j['status']='unknown';$j['message']='Sending was interrupted. Check the mail log before sending again.';foreach($j['recipients'] as &$recipient){$recipient=$recipient==='sending'?'unknown':($recipient==='queued'?'not_sent':$recipient);}unset($recipient,$j['pdf'],$j['preparation']);continue;}
                    if($j['status']!=='queued' || ($j['retry_at'] ?? 0)>$now){continue;}
                    $j['status']='processing';$j['updated']=$now;$j['lease']=wp_generate_uuid4();$out=$j;$out['id']=$id;unset($j);
                    self::write($company,self::prune($s));return $out;
                }unset($j);self::write($company,self::prune($s));return null;
            });
            if($claim){self::process($company,$claim);}
        } catch(Throwable $e) { /* No provider/credential text is recorded or sent. */ }
        finally {
            $s=self::state($company);$next=null;
            foreach($s['schedules'] as $p){if(!empty($p['enabled'])){$next=$next===null?$p['next']:min($next,$p['next']);}}
            foreach($s['jobs'] as $j){if(in_array($j['status'],['queued','processing','sending'],true)){$at=max(time()+15,$j['retry_at'] ?? 0);$next=$next===null?$at:min($next,$at);}}
            if($next!==null){try{self::wake($company,$next);}catch(Throwable $e){/* UI surfaces missing wake-up. */}}
        }
    }
    private static function process($company,$job) {
        $priorUser=get_current_user_id();$post=$_POST;$get=$_GET;
        try {
            if($job['origin']!==home_url('/')){throw new RuntimeException('This delivery belongs to another site.');}
            $c=self::access($company,$job['principal'],isset($job['schedule']));
            wp_set_current_user($job['principal']);$_POST['workspace']=(string)$company;$_GET['workspace']=(string)$company;
            $document=isset($job['pdf'])?WGPP_Google::decrypt($job['pdf'],'delivery:'.$company.':'.$job['id']):null;
            if(!$document){$payload=self::collect($company,$job);if($payload===null){self::finish($company,$job,'queued',self::$progressNote,true);return;}
                $document=['bytes'=>base64_encode(WGPP_Delivery_PDF::build($c['name'],$payload,$company)),'title'=>$job['review']?'Pro Analytics Review':'Pro Analytics Report'];
                $job['pdf']=WGPP_Google::encrypt($document,'delivery:'.$company.':'.$job['id']);
            }
            $bytes=base64_decode($document['bytes'],true);if(!$bytes || !str_starts_with($bytes,'%PDF-')){throw new RuntimeException('Report preparation failed.');}
            // Freeze the document before any sends. The send marker prevents an automatic duplicate after a crash.
            self::store_claim($company,$job,'processing');
            foreach($job['recipients'] as $email=>$status){
                if($status!=='queued'){continue;}
                self::access($company,$job['principal'],isset($job['schedule']));if($job['review']){self::saved_review($company,$job['review']);}
                $job['recipients'][$email]='sending';self::store_claim($company,$job,'sending');
                $name=sanitize_file_name($c['name'].'-'.$document['title'].'-'.self::central($job['created'])->format('Y-m-d_H-i-s').'_CT.pdf');
                $period=implode(' to ',array_map(static function($d){return WGPP_Analytics::date($d)->format('m/d/Y');},$job['ranges']['current']));
                $body="Attached is ".$c['name']." — ".$document['title'].".\nReporting period: ".$period."\nPrepared: ".self::stamp(time())."\n\n".($job['note']!==''?$job['note']."\n\n":'')."This report is shared privately as an attachment. No public report page is created.\n\nSign in to Pro Analytics:\n".wp_login_url(add_query_arg('workspace',$company,WGPP_Team::portal()))."\nA registered login with access to this company is required to open the dashboard.\n\nSocial Media Magnet Pro";
                $ok=self::mail($email,$c['name'].' · '.$document['title'].' · '.$period,$body,$bytes,$name);
                $job['recipients'][$email]=$ok?'accepted':'failed';self::store_claim($company,$job,'processing');
            }
            $failed=in_array('failed',$job['recipients'],true);self::finish($company,$job,$failed?'failed':'accepted',$failed?'One or more recipients were not accepted by the mail service.':'Accepted by mail service. Inbox delivery is not confirmed.');
        } catch(Throwable $e){self::finish($company,$job,'failed','Delivery stopped. Check company access, reporting connections and mail settings. No automatic resend.');}
        finally{$_POST=$post;$_GET=$get;wp_set_current_user($priorUser);}
    }
    private static function store_claim($company,&$job,$status) {
        WGPP_Native_Reviews::locked($company,static function()use($company,&$job,$status){
            $s=self::state($company);$current=$s['jobs'][$job['id']] ?? null;
            if(!$current || ($current['lease'] ?? '')!==$job['lease']){throw new RuntimeException('Delivery changed.');}
            $job['status']=$status;$job['updated']=time();$s['jobs'][$job['id']]=$job;self::write($company,$s);
        });
    }
    private static function finish($company,$job,$status,$message,$retry=false) {
        try{WGPP_Native_Reviews::locked($company,static function()use($company,$job,$status,$message,$retry){
            $s=self::state($company);$j=$s['jobs'][$job['id']] ?? null;if(!$j || ($j['lease'] ?? '')!==$job['lease']){return;}
            if($retry){$j['attempts']++;$j['retry_at']=time()+15;if(isset($job['preparation'])){$j['preparation']=$job['preparation'];}if($j['attempts']>=120 || time()-$j['created']>1800){$status='failed';$message='Report preparation stopped after waiting too long for reporting data. No email sent. Check the channel connections, then try again.';}}
            $j['status']=$status;$j['message']=$message;$j['updated']=time();
            if(!in_array($status,['queued','processing','sending'],true)){unset($j['pdf'],$j['preparation']);foreach($j['recipients'] as &$recipient){if($recipient==='sending'){$recipient='unknown';}elseif($recipient==='queued'){$recipient='not_sent';}}unset($recipient);}
            $s['jobs'][$job['id']]=$j;self::write($company,self::prune($s));
        });}catch(Throwable $ignored){}
    }
    public static function mail($email,$subject,$body,$bytes,$name) {
        // Attachment exists only in memory; no indexable uploads or temporary public files.
        $attached=false;$mailer=null;
        $hook=static function($m)use($bytes,$name,&$attached,&$mailer){$mailer=$m;$attached=$m->addStringAttachment($bytes,$name,'base64','application/pdf');};
        $sender=static function(){return 'Social Media Magnet Pro';};
        $address=static function(){return WGPP_Account::INVITATION_FROM;};
        add_action('phpmailer_init',$hook,PHP_INT_MAX);add_filter('wp_mail_from_name',$sender,PHP_INT_MAX);add_filter('wp_mail_from',$address,PHP_INT_MAX);
        // Refuse a preempting transport: it would bypass our in-memory attachment.
        $guard=static function($pre){if($pre!==null){throw new RuntimeException('Mail transport bypasses PDF attachments.');}return null;};
        add_filter('pre_wp_mail',$guard,PHP_INT_MAX);
        try{$ok=wp_mail($email,$subject,$body,['Content-Type: text/plain; charset=UTF-8','Reply-To: Social Media Magnet Pro <'.WGPP_Account::INVITATION_FROM.'>']);return $ok && $attached;}
        finally{remove_action('phpmailer_init',$hook,PHP_INT_MAX);remove_filter('wp_mail_from_name',$sender,PHP_INT_MAX);remove_filter('wp_mail_from',$address,PHP_INT_MAX);remove_filter('pre_wp_mail',$guard,PHP_INT_MAX);if($mailer){$mailer->clearAttachments();}}
    }
    public static function collect($company,&$job) {
        if($job['review']){$saved=self::saved_review($company,$job['review']);return ['snapshot'=>$saved['snapshot'],'review'=>$saved['review'],'created'=>$saved['created_at']];}
        self::past_dates($job['ranges'],$company);
        // Freeze each completed section for this delivery. Provider caches may expire
        // while a later section is collecting; that must not restart prior sections.
        $context='delivery-preparation:'.$company.':'.$job['id'];
        $prepared=isset($job['preparation'])?WGPP_Google::decrypt($job['preparation'],$context):[];
        if(($prepared['ranges'] ?? $job['ranges'])!==$job['ranges']){throw new RuntimeException('Preparation dates changed.');}
        $a=$prepared['current'] ?? WGPP_Analytics::lookup(...$job['ranges']['current']);
        $b=$prepared['comparison'] ?? ($job['ranges']['comparison']?WGPP_Analytics::lookup(...$job['ranges']['comparison']):null);
        if(!$a || ($job['ranges']['comparison'] && !$b)){WGPP_GA4::progress($company,array_values(array_filter($job['ranges'])));self::$progressNote='Preparing website analytics for the selected periods. No email sent.';return null;}
        if($b && ($a['property']!==$b['property'] || $a['timezone']!==$b['timezone'])){throw new RuntimeException('Comparison identity differs.');}
        $prepared=array_merge($prepared,['ranges'=>$job['ranges'],'current'=>$a,'comparison'=>$b]);
        $channels=$prepared['channels'] ?? [];
        foreach(['facebook'=>'WGPP_FB_Reports','instagram'=>'WGPP_IG_Reports'] as $key=>$class){
            if(isset($channels[$key])){continue;}
            try{$result=$class::request($company,$job['ranges']);}catch(Throwable $e){$result=['status'=>'unavailable'];}
            if(in_array($result['status'] ?? '',['queued','processing'],true)){
                $prepared['channels']=$channels;$job['preparation']=WGPP_Google::encrypt($prepared,$context);
                $step=isset($result['step'],$result['steps'])?' Step '.(int)$result['step'].' of '.(int)$result['steps'].'.':'';
                self::$progressNote='Preparing '.ucfirst($key).' reporting.'.$step.' Completed sections are saved. No email sent.';return null;
            }
            $channels[$key]=$result;
        }
        $email=WGPP_CC_Reports::progress($company);
        if(in_array($email['status'] ?? '',['queued','processing'],true)){
            $prepared['channels']=$channels;$job['preparation']=WGPP_Google::encrypt($prepared,$context);
            self::$progressNote='Preparing email campaign reporting. Website and social sections are saved. No email sent.';return null;
        }
        $channels['email']=['status'=>$email['status'] ?? 'unavailable'];
        if(($email['status'] ?? '')==='complete'){$channels['email']['basis']='Campaigns sent during each period; cumulative responses through collection, not period-only responses.';foreach($job['ranges'] as $side=>$range){$channels['email'][$side]=$range?WGPP_CC_Reports::summarize($email['rows'],...array_merge($range,[$a['timezone']])):null;}}
        unset($job['preparation']);
        return ['snapshot'=>['ranges'=>$job['ranges'],'current'=>$a,'comparison'=>$b,'analysis'=>WGPP_Analytics::analyse($a,$b),'context'=>['channels'=>$channels]],'created'=>gmdate('c')];
    }
    private static function form($company,$op) {
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('wgpp_report_delivery');
        echo '<input type="hidden" name="action" value="wgpp_report_delivery"><input type="hidden" name="workspace" value="'.(int)$company.'"><input type="hidden" name="delivery_op" value="'.esc_attr($op).'">';
    }
    private static function compare($value='prior') {
        echo '<label>Compare with <select name="comparison">';foreach(['none'=>'None','prior'=>'Prior period','year'=>'Previous year'] as $v=>$label){echo '<option value="'.$v.'" '.($v===$value?'selected':'').'>'.$label.'</option>';}echo '</select></label>';
    }
    public static function render($c) {
        if(!in_array($c['role'],['owner','analyst'],true)){return '<h4>Report Delivery</h4><p>Your Owner or an Analyst can email company reports.</p>';}
        wp_enqueue_script('wgpp-delivery',plugins_url('assets/report-delivery.js',__FILE__),['wgpp-portal'],self::VERSION,true);
        $stored=get_post_meta($c['id'],self::META,true);if(is_array($stored)&&count($stored['jobs'] ?? [])>5){WGPP_Native_Reviews::locked($c['id'],static function()use($c){self::write($c['id'],self::state($c['id']));});}
        $s=self::state($c['id']);$today=self::central(time());$last=$today->modify('-1 day');ob_start();
        echo '<h4>Report Delivery</h4><p>Send a private PDF attachment to up to ten email addresses. Recipients do not need an account. No public report page is created. PDFs contain selectable text and compact reporting sections.</p>';
        if(wp_get_environment_type()!=='production'){echo '<p role="status"><strong>Staging:</strong> automatic schedules do not send here. You can explicitly send a report below to test delivery.</p>';}
        if($c['role']==='owner'){
            echo '<h4>Automatic analytics reports</h4><p>Weekly covers the previous Monday–Sunday. Monthly covers the previous calendar month. Every 4 weeks covers the preceding 28 days, starting from your chosen first delivery. Each schedule has its own recipients. Times use Central Time and adjust for daylight saving. Saving enables or replaces that frequency.</p>';
            foreach(['weekly'=>'Weekly','monthly'=>'Monthly','four_weeks'=>'Every 4 weeks'] as $key=>$title){$plan=$s['schedules'][$key] ?? null;
                echo '<details class="wgpa-account-card"><summary>'.esc_html($title).' · '.($plan && $plan['enabled']?'Enabled':'Off / paused').'</summary>';
                if($plan){echo '<p>Next: '.esc_html(self::stamp($plan['next'])).'</p>';if(!empty($plan['notice'])){echo '<p>'.esc_html($plan['notice']).'</p>';}}
                self::form($c['id'],'save');echo '<input type="hidden" name="frequency" value="'.$key.'"><p><label>Recipients <input name="recipients" type="text" value="'.esc_attr(implode(', ',$plan['recipients'] ?? [])).'" placeholder="name@company.com, another@company.com" maxlength="2500" required></label></p>';
                $first=$today->modify('+1 day');if($key==='monthly'){$first=$today->modify('first day of next month');}elseif($key==='weekly'){$first=$today->modify('next monday');}
                echo '<p><label>First / next delivery date <input type="date" name="first_send" value="'.$first->format('Y-m-d').'" required></label> <label>Time (Central Time) <input type="time" name="send_time" value="'.esc_attr($plan['time'] ?? '08:00').'" required></label></p>';self::compare($plan['comparison'] ?? 'prior');
                echo '<p><label><input type="checkbox" name="confirm_recipients" value="1" required> These recipients may receive this company’s analytics reports.</label></p><button>Save '.$title.' schedule</button></form>';
                if($plan){self::form($c['id'],'cancel_schedule');echo '<input type="hidden" name="frequency" value="'.$key.'"><button>Cancel '.$title.' schedule</button></form>';}
                echo '</details>';
            }
        }else{echo '<p>Your Owner manages recurring schedules.</p>';}
        echo '<h4>Email this report</h4>';self::form($c['id'],'send');echo '<div data-delivery-manual><input type="hidden" name="request_id" value="'.esc_attr(wp_generate_uuid4()).'"><p><label>Report <select name="review_id"><option value="">Analytics Report · choose dates below</option>';
        $reviews=get_post_meta($c['id'],WGPP_Native_Reviews::META,true);$reviews=is_array($reviews)?$reviews:[];uasort($reviews,static function($a,$b){return strcmp($b['created_at'],$a['created_at']);});
        foreach(array_slice($reviews,0,100,true) as $id=>$j){if($j['status']==='completed' && empty($j['trashed_at'])){echo '<option value="'.esc_attr($id).'">Saved Pro Analytics Review · '.esc_html(self::stamp(strtotime($j['created_at']))).' · '.esc_html(implode(' to ',$j['snapshot']['ranges']['current'])).'</option>';}}
        echo '</select></label></p><p>Saved Reviews keep their original dates and content; emailing them does not create or use another Review.</p><div data-delivery-dates><p><label>Start <input name="start" type="date" value="'.$last->modify('-29 days')->format('Y-m-d').'" required></label> <label>End <input name="end" type="date" value="'.$last->format('Y-m-d').'" required></label></p>';self::compare();echo '</div><p><label>Recipients <input type="text" name="recipients" maxlength="2500" required placeholder="name@company.com"></label></p><p><label>Optional message <textarea name="note" maxlength="2000" rows="3"></textarea></label></p><p><label><input type="checkbox" name="confirm_recipients" value="1" required> These recipients may receive this company report.</label></p><button>Prepare and email report</button></div></form>';
        echo '<h4>Delivery history</h4><p>Only the five most recent finished deliveries are kept. Pending deliveries remain visible. Owners can cancel a queued/preparing delivery or delete a finished record. Emails already sending or accepted cannot be recalled. Cancel a recurring schedule above, then create a new one to reschedule.</p><p>“Accepted” means the mail service accepted the email, not proof of inbox delivery. Failed or interrupted sends are never automatically resent. Review the recipient results before sending again.</p>';
        $pending=array_filter($s['jobs'],static function($j){return in_array($j['status'],['queued','processing','sending'],true);});if($pending && !wp_next_scheduled(self::HOOK,[(int)$c['id']])){echo '<p role="alert">Delivery is waiting, but its WordPress wake-up is missing. Save a schedule again and ask your administrator to check WP-Cron.</p>';}
        echo '<p class="wgpa-small-note">Delivery runs through the website’s WordPress scheduler. A low-traffic site may run later than the selected time.</p>';
        $settings=['url'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('wgpp_delivery_progress'),'workspace'=>$c['id'],'canDelete'=>$c['role']==='owner','formUrl'=>admin_url('admin-post.php'),'deleteNonce'=>wp_create_nonce('wgpp_report_delivery')];
        echo '<div data-delivery-live data-delivery-config="'.esc_attr(wp_json_encode($settings)).'" data-pending="'.($pending?'1':'0').'"><p class="wgpa-operation-status" data-tone="loading" data-delivery-progress role="status" aria-live="polite">'.($pending?'Your report is queued. This open page will check and advance preparation automatically.':'No pending deliveries.').'</p><button type="button" data-delivery-check>Check delivery now</button><div data-delivery-history>';
        foreach(self::public_jobs($c['id']) as $j){echo '<details><summary>'.esc_html(ucfirst($j['status']).' · '.$j['created'].' · '.$j['type']).'</summary><p>'.esc_html($j['period']).'</p><p>'.esc_html($j['message']).'</p><p>Last activity: '.esc_html($j['updated']).' · Preparation attempts: '.(int)$j['attempts'].'</p>';foreach($j['recipients'] as $email=>$status){echo '<p>'.esc_html($email).' · '.esc_html($status).'</p>';}if($c['role']==='owner'&&in_array($j['status'],['queued','processing'],true)){self::form($c['id'],'cancel');echo '<input type="hidden" name="job_id" value="'.esc_attr($j['id']).'"><button>Cancel delivery</button></form>';}if($c['role']==='owner'&&!in_array($j['status'],['queued','processing','sending'],true)){self::form($c['id'],'delete');echo '<input type="hidden" name="job_id" value="'.esc_attr($j['id']).'"><button>Delete record</button></form>';}echo '</details>';}
        if(!$s['jobs']){echo '<p>No report emails yet.</p>';}echo '</div></div>';
        return ob_get_clean();
    }
}
