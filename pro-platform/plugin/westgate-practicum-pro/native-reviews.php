<?php
if (!defined('ABSPATH')) { exit; }

/** Native WordPress review journal. No Python service or external host required. */
final class WGPP_Native_Reviews {
    const META = '_wgpp_review_journal_v1';
    public static function boot() {
        add_filter('wgpp_review_service_ready',[__CLASS__,'ready'],10,2);
        add_filter('wgpp_review_service',[__CLASS__,'dispatch'],10,4);
        add_action('wgpp_process_review',[__CLASS__,'process'],10,2);
        add_action('admin_post_wgpp_reset_review_allowance',[__CLASS__,'reset_handler']);
        add_action('admin_post_wgpp_manage_saved_reviews',[__CLASS__,'manage_saved_handler']);
    }
    public static function ready($ready,$company) { return true; }
    private static function journal($company) {
        $value=get_post_meta($company,self::META,true);
        return is_array($value) ? $value : [];
    }
    public static function locked($company,$callback) {
        global $wpdb;
        $name='wgpp_'.hash('sha256',$wpdb->prefix.'|'.$company);
        $name=substr($name,0,64);
        if ((string)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 3)',$name))!=='1') {
            throw new RuntimeException('Reporting is busy. Please try again.');
        }
        try {
            // Avoid stale object-cache reads after waiting for another request.
            wp_cache_delete($company,'post_meta');
            return $callback();
        } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$name)); }
    }
    private static function write($company,$journal) {
        $old=self::journal($company);
        if ($old!==$journal && !update_post_meta($company,self::META,$journal)) { throw new RuntimeException('Could not save the review journal.'); }
    }
    private static function cycle($company) {
        // WishList/billing adapter supplies monthly windows, including annual plans.
        $cycle=apply_filters('wgpp_review_billing_cycle',null,$company);
        if ($cycle===null) { $now=new DateTimeImmutable('now',new DateTimeZone('America/Chicago'));$start=$now->modify('first day of this month')->setTime(0,0);$cycle=['start'=>$start->format('c'),'end'=>$start->modify('+1 month')->format('c'),'basis'=>'Pilot allowance · calendar month (America/Chicago). Paid billing is not connected for this workspace.']; }
        if (!is_array($cycle) || !isset($cycle['start'],$cycle['end'])) { return null; }
        try { $start=new DateTimeImmutable($cycle['start']); $end=new DateTimeImmutable($cycle['end']); }
        catch (Exception $e) { return null; }
        if (!preg_match('/(?:Z|[+-]\d{2}:\d{2})$/D',$cycle['start']) || !preg_match('/(?:Z|[+-]\d{2}:\d{2})$/D',$cycle['end'])) { return null; }
        $now=time();
        if ($start->getTimestamp()>$now || $end->getTimestamp()<=$now) { return null; }
        return ['start'=>$start->getTimestamp(),'end'=>$end->getTimestamp(),'limit'=>WGPP_Company::plan()['reviews_monthly'],'basis'=>$cycle['basis'] ?? 'Company monthly review allowance.'];
    }
    private static function usage($journal,$cycle) {
        $count=0;
        foreach($journal as $job) {
            if (empty($job['allowance_waived']) && $job['cycle_start']===$cycle['start'] && $job['cycle_end']===$cycle['end'] && in_array($job['status'],['queued','processing','completed'],true)) { $count++; }
        }
        return $count;
    }
    public static function reset_allowance($company,$reason,$completedToCount=0){
        if(!current_user_can('manage_options')){throw new InvalidArgumentException('Site administrator access required.');}
        $post=get_post($company);$cycle=self::cycle($company);
        if(!$post || $post->post_type!=='wgpp_company' || $post->post_status!=='private' || !$cycle){throw new InvalidArgumentException('Select an active company allowance.');}
        if(!is_string($reason)||trim($reason)===''||strlen($reason)>500){throw new InvalidArgumentException('Enter a reset reason of up to 500 characters.');}
        if(!is_int($completedToCount)||$completedToCount<0||$completedToCount>$cycle['limit']){throw new InvalidArgumentException('Choose a completed-review count within the monthly allowance.');}
        return self::locked($company,static function()use($company,$cycle,$reason,$completedToCount){
            $j=self::journal($company);$reset=['at'=>gmdate('c'),'by'=>get_current_user_id(),'reason'=>sanitize_text_field($reason),'cycle_start'=>$cycle['start'],'cycle_end'=>$cycle['end'],'waived'=>0,'completed_counted'=>$completedToCount];
            $completed=[];$pending=0;foreach($j as $id=>$job){if($job['cycle_start']!==$cycle['start']||$job['cycle_end']!==$cycle['end']){continue;}if($job['status']==='completed'){$completed[$id]=$job['created_at'];}elseif(in_array($job['status'],['queued','processing'],true)&&empty($job['allowance_waived'])){$pending++;}}
            if($completedToCount>count($completed)||$completedToCount+$pending>$cycle['limit']){throw new InvalidArgumentException('That count is unavailable. Pending reviews remain reserved.');}
            arsort($completed);$counted=array_slice(array_keys($completed),0,$completedToCount);
            foreach($completed as $id=>$stamp){if(in_array($id,$counted,true)){unset($j[$id]['allowance_waived']);}elseif(empty($j[$id]['allowance_waived'])){$j[$id]['allowance_waived']=$reset;$reset['waived']++;}}
            self::write($company,$j);$audit=get_post_meta($company,'_wgpp_review_resets',true);$audit=is_array($audit)?$audit:[];$audit[]=$reset;
            update_post_meta($company,'_wgpp_review_resets',array_slice($audit,-100));
            return ['remaining'=>max(0,$cycle['limit']-self::usage($j,$cycle)),'limit'=>$cycle['limit'],'waived'=>$reset['waived']];
        });
    }
    public static function reset_handler(){
        if(!current_user_can('manage_options')){wp_die('Site administrator access required.','',['response'=>403]);}check_admin_referer('wgpp_reset_review_allowance');
        try{$company=absint($_POST['company_id'] ?? 0);$reason=$_POST['reset_reason'] ?? '';if(!is_string($reason)){throw new InvalidArgumentException('Invalid reason.');}$target=$_POST['completed_to_count'] ?? '0';if(!is_string($target)||!ctype_digit($target)){throw new InvalidArgumentException('Invalid completed-review count.');}self::reset_allowance($company,wp_unslash($reason),(int)$target);wp_safe_redirect(admin_url('tools.php?page=wgpp-pro-review&allowance_reset='.$company));exit;}catch(InvalidArgumentException|RuntimeException $e){wp_die(esc_html($e->getMessage()));}
    }
    public static function reset_controls(){
        if(!current_user_can('manage_options')){return;}
        $c=WGPP_Company::context();$companies=get_posts(['post_type'=>'wgpp_company','post_status'=>'private','posts_per_page'=>200,'orderby'=>'title','order'=>'ASC']);
        echo '<section><h2>Company review allowance · administrator only</h2><p>Reset completed-review usage in the current allowance month. Saved reports remain available. Queued and processing reviews still reserve allowance; membership dates and future months are unchanged.</p>';
        if(isset($_GET['allowance_reset'])){echo '<p role="status"><strong>Review allowance reset. Saved reports retained.</strong></p>';}
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('wgpp_reset_review_allowance');echo '<input type="hidden" name="action" value="wgpp_reset_review_allowance"><p><label>Company <select name="company_id" required>';
        foreach($companies as $p){echo '<option value="'.(int)$p->ID.'" '.($c && $c['id']===$p->ID?'selected':'').'>'.esc_html($p->post_title).'</option>';}
        echo '</select></label></p><p><label>Completed reviews to count <input type="number" name="completed_to_count" value="0" min="0" max="4" required></label><br>Keep the newest completed reviews counted; all other completed reports are retained without using allowance. Use 0 to restore the full allowance.</p><p><label>Reason <input name="reset_reason" maxlength="500" required size="65" value="Development and report quality verification"></label></p><button class="button">Reset company review allowance</button></form>';
        if($c){$cycle=self::cycle($c['id']);if($cycle){$used=self::usage(self::journal($c['id']),$cycle);echo '<p>'.esc_html($c['name']).': '.max(0,$cycle['limit']-$used).' of '.$cycle['limit'].' reviews available.</p>';}$events=get_post_meta($c['id'],'_wgpp_review_resets',true);if(is_array($events)&&$events){$event=end($events);$time=(new DateTimeImmutable($event['at']))->setTimezone(new DateTimeZone('America/Chicago'))->format('m/d/Y g:i A').' Central Time';echo '<p>Last reset: '.esc_html($time).' · Administrator #'.(int)$event['by'].' · '.esc_html($event['reason']).'</p>';}}
        echo '</section>';self::saved_controls();
    }
    /** Recoverable removal from customer history; allowance accounting is unchanged. */
    public static function manage_saved($company,$ids,$restore=false) {
        if(!current_user_can('manage_options')){throw new InvalidArgumentException('Site administrator access required.');}
        $post=get_post($company);
        if(!$post || $post->post_type!=='wgpp_company' || $post->post_status!=='private' || !is_array($ids) || !$ids || count($ids)>100){throw new InvalidArgumentException('Select a company and saved Reviews.');}
        foreach($ids as $id){if(!is_string($id)||!preg_match('/^[a-zA-Z0-9-]{1,100}$/D',$id)){throw new InvalidArgumentException('Invalid saved Review.');}}
        return self::locked($company,static function()use($company,$ids,$restore){
            $j=self::journal($company);
            foreach($ids as $id){if(!isset($j[$id]) || $j[$id]['status']!=='completed'){throw new InvalidArgumentException('Saved Review unavailable for this company.');}}
            foreach($ids as $id){if($restore){unset($j[$id]['trashed_at'],$j[$id]['trashed_by']);}else{$j[$id]['trashed_at']=gmdate('c');$j[$id]['trashed_by']=get_current_user_id();}}
            self::write($company,$j);return count(array_unique($ids));
        });
    }
    public static function manage_saved_handler() {
        if(!current_user_can('manage_options')){wp_die('Site administrator access required.','',['response'=>403]);}check_admin_referer('wgpp_manage_saved_reviews');
        try{$company=absint($_POST['company_id']??0);$op=$_POST['saved_review_op']??'';if(!in_array($op,['trash','restore'],true)){throw new InvalidArgumentException('Invalid action.');}self::manage_saved($company,$_POST['report_ids']??[],$op==='restore');wp_safe_redirect(add_query_arg(['workspace'=>$company,'saved_reviews_updated'=>1],admin_url('tools.php?page=wgpp-pro-review')));exit;}catch(InvalidArgumentException|RuntimeException $e){wp_die(esc_html($e->getMessage()));}
    }
    public static function saved_controls() {
        if(!current_user_can('manage_options') || !($c=WGPP_Company::context())){return;}
        echo '<section><h2>Manage saved Reviews · '.esc_html($c['name']).'</h2><p>Move selected Reviews to Trash to remove them from the company list. They can be restored here. This does not reset review usage.</p>';
        if(isset($_GET['saved_reviews_updated'])){echo '<p role="status"><strong>Saved Reviews updated.</strong></p>';}
        $journal=self::journal($c['id']);uasort($journal,static function($a,$b){return strcmp($b['created_at'],$a['created_at']);});
        foreach(['trash'=>'Saved Reviews','restore'=>'Trash · recover saved Reviews'] as $op=>$title){
            echo '<h3>'.esc_html($title).'</h3><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('wgpp_manage_saved_reviews');echo '<input type="hidden" name="action" value="wgpp_manage_saved_reviews"><input type="hidden" name="company_id" value="'.(int)$c['id'].'"><input type="hidden" name="workspace" value="'.(int)$c['id'].'"><input type="hidden" name="saved_review_op" value="'.$op.'">';$n=0;
            foreach($journal as $id=>$job){if($job['status']!=='completed' || ($op==='restore')!==!empty($job['trashed_at'])){continue;}$n++;$stamp=(new DateTimeImmutable($job['created_at']))->setTimezone(new DateTimeZone('America/Chicago'))->format('m/d/Y g:i A').' Central Time';echo '<p><label><input type="checkbox" name="report_ids[]" value="'.esc_attr($id).'"> '.esc_html($stamp).'</label></p>';}
            echo $n?'<button class="button">'.($op==='trash'?'Move selected Reviews to Trash':'Restore selected Reviews').'</button>':'<p>None.</p>';echo '</form>';
        }echo '</section>';
    }
    public static function dispatch($prior,$operation,$context,$request) {
        $c=WGPP_Company::context();
        if (!$c || $c['id']!==$context['company_id'] || get_current_user_id()!==$context['principal']) { throw new InvalidArgumentException('Company access required.'); }
        $company=$c['id'];
        if ($operation==='list') {
            $reports=[];
            foreach(self::journal($company) as $id=>$job) {
                if ($job['status']==='completed' && empty($job['trashed_at'])) { $reports[]=['id'=>$id,'created_at'=>$job['created_at'],'mode'=>$job['review']['mode'] ?? 'Pro Analytics Review','framework_version'=>$job['review']['framework_version'] ?? null,'ranges'=>$job['snapshot']['ranges']]; }
            }
            usort($reports,static function($a,$b){return strcmp($b['created_at'],$a['created_at']);});
            return ['reports'=>array_slice($reports,0,100)];
        }
        if ($operation==='status') {
            $id=$request['report_id'];$job=self::journal($company)[$id] ?? null;
            if (!$job || !empty($job['trashed_at']) || ($job['status']!=='completed' && !WGPP_Company::can('review'))) { throw new InvalidArgumentException('Review unavailable.'); }
            if ($job['status']==='queued') { self::process($company,$id); }
            self::locked($company,static function()use($company,$id){$j=self::journal($company);if(($j[$id]['status'] ?? '')==='processing' && ($j[$id]['started'] ?? time())<time()-300){$j[$id]['status']='failed';self::write($company,$j);}});
            $job=self::journal($company)[$id];
            return $job['status']==='completed'?self::record($id,$job):['status'=>$job['status'],'request_id'=>$id,'message'=>$job['failure_message']??''];
        }
        if ($operation==='get') {
            $job=self::journal($company)[$request['report_id']] ?? null;
            if (!$job || !empty($job['trashed_at']) || $job['status']!=='completed') { throw new InvalidArgumentException('Saved report unavailable.'); }
            return self::record($request['report_id'],$job);
        }
        if (!WGPP_Company::can('review')) { throw new InvalidArgumentException('An owner or analyst must request evaluations.'); }
        $cycle=self::cycle($company);
        $ready=apply_filters('wgpp_evaluator_ready',false,$company)===true;
        if ($operation==='preview') {
            $used=$cycle ? self::usage(self::journal($company),$cycle) : 0;
            return ['can_generate'=>$cycle && $ready && $used<$cycle['limit'],'used'=>$used,'limit'=>4,
                'remaining'=>$cycle ? max(0,$cycle['limit']-$used) : 0,
                'basis'=>$cycle['basis'] ?? 'Membership allowance unavailable.','message'=>!$cycle?'Review allowance is awaiting membership setup.':(!$ready?'AI evaluations are awaiting configuration.':'The company review allowance has been reached.')];
        }
        if ($operation!=='generate') { throw new InvalidArgumentException('Invalid review operation.'); }
        if (!$cycle || !$ready) { throw new InvalidArgumentException('Configure membership allowance and AI evaluations first.'); }
        $id=$request['request_id'];
        return self::locked($company,static function()use($company,$id,$request,$cycle){
            $journal=self::journal($company);
            if (isset($journal[$id])) {
                return $journal[$id]['status']==='completed' ? self::record($id,$journal[$id]) : ['status'=>$journal[$id]['status'],'request_id'=>$id];
            }
            if (self::usage($journal,$cycle)>=$cycle['limit']) { throw new InvalidArgumentException('Monthly evaluation allowance reached.'); }
            $journal[$id]=['status'=>'queued','principal'=>get_current_user_id(),'cycle_start'=>$cycle['start'],'cycle_end'=>$cycle['end'],'created_at'=>gmdate('c'),'snapshot'=>$request['snapshot'],'review'=>null,'started'=>null];
            self::write($company,$journal);
            $scheduled=wp_schedule_single_event(time()+1,'wgpp_process_review',[$company,$id],true);
            if (is_wp_error($scheduled) || !$scheduled) {
                $journal[$id]['status']='failed';self::write($company,$journal);
                throw new RuntimeException('Could not queue evaluation. The allowance was released.');
            }
            return ['status'=>'queued','request_id'=>$id];
        });
    }
    private static function record($id,$job) {
        $snapshot=$job['snapshot'];unset($snapshot['context']['_foundation_files']);
        return ['schema'=>'wgpp-review-v1','id'=>$id,'created_at'=>$job['created_at'],'snapshot'=>$snapshot,'review'=>$job['review']];
    }
    /** Only application-owned messages may be exposed; never provider bodies or credentials. */
    public static function failure_message($error) {
        $allowed=['Invalid content post references.','Content recommendation cited an unavailable post.','Invalid Pro Analytics Review limitations.','Invalid Pro Analytics Review limitation.','SMM principle missing from an action.','Brand foundation assessment missing.','AI enhancement failed. The review allowance will be released.','AI enhancement did not complete.','Review did not assess each uploaded Brand foundation.','Review referenced an unavailable Brand foundation.','Incomplete Brand foundation assessment.','Invalid Pro Analytics Review response.','Incomplete Pro Analytics Review response.','Invalid Pro Analytics Review content.','Incomplete SMM foundation assessment.','Invalid SMM foundation assessment.','SMM foundation assessment missing.','Incomplete Message Matrix direction.','Invalid Message Matrix direction.','Evaluation did not return a valid report.','Document verification failed. Upload it again.','Generation access is no longer active.','An administrator must enable Review enhancement to assess the uploaded Brand foundations. No Review was completed.','The Review service timed out. Please retry.','The Review service reached its response limit. Please retry.'];
        return in_array($error->getMessage(),$allowed,true)?$error->getMessage():'The Review could not be completed. Reference: '.preg_replace('/[^a-zA-Z0-9._-]/','',basename($error->getFile())).':'.$error->getLine().'. Please contact the site administrator.';
    }
    public static function process($company,$id) {
        try {
            $job=self::locked($company,static function()use($company,$id){
                $journal=self::journal($company);
                if (!isset($journal[$id]) || $journal[$id]['status']!=='queued') { return null; }
                $journal[$id]['status']='processing';$journal[$id]['started']=time();self::write($company,$journal);
                return $journal[$id];
            });
            if (!$job) { return; }
            $members=get_post_meta($company,'_wgpp_members',true);
            $company_post=get_post($company);
            if (!$company_post || $company_post->post_status!=='private' || !in_array($members[$job['principal']] ?? '',['owner','analyst'],true) || !apply_filters('wgpp_company_entitled',true,$company,$company_post->post_author)) {
                throw new RuntimeException('Generation access is no longer active.');
            }
            $review=apply_filters('wgpp_evaluate_snapshot',null,$job['snapshot'],$company);
            if (!is_array($review) || !is_string($review['summary'] ?? null) || !is_array($review['actions'] ?? null) || !is_array($review['findings'] ?? null)) { throw new RuntimeException('Evaluation did not return a valid report.'); }
            self::locked($company,static function()use($company,$id,$review){
                $journal=self::journal($company);
                if (($journal[$id]['status'] ?? '')!=='processing') { return; }
                // One company-meta update commits saved review and consumption together.
                $journal[$id]['review']=$review;$journal[$id]['status']='completed';self::write($company,$journal);
            });
        } catch (Throwable $e) {
            try {
                self::locked($company,static function()use($company,$id,$e){
                    $journal=self::journal($company);
                    if (isset($journal[$id]) && $journal[$id]['status']!=='completed') { $journal[$id]['status']='failed';$journal[$id]['failure_message']=self::failure_message($e);self::write($company,$journal); }
                });
            } catch (Throwable $ignored) { /* Preserve reservation until administrator recovery. */ }
        }
    }
}
