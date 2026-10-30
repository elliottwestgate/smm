<?php
if (!defined('ABSPATH')) { exit; }

/** Provider-neutral reporting contract. No credentials or company data shipped. */
final class WGPP_Analytics {
    const META = '_wgpp_analytics_snapshots_v1';
    public static function allowed() { return is_user_logged_in() && WGPP_Company::can('view'); }
    public static function date($value) {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) { throw new InvalidArgumentException('Use valid dates.'); }
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if (!$d || $d->format('Y-m-d') !== $value) { throw new InvalidArgumentException('Use valid dates.'); }
        return $d;
    }
    private static function year_before($date) {
        $d = self::date($date); $year = (int)$d->format('Y') - 1;
        $first = self::date(sprintf('%04d-%s-01', $year, $d->format('m')));
        return $first->setDate($year, (int)$d->format('m'), min((int)$d->format('d'), (int)$first->format('t')))->format('Y-m-d');
    }
    public static function ranges($preset, $end, $start, $comparison) {
        $last = self::date($end);
        if ($preset === 'custom') { $first = self::date($start); }
        elseif (in_array($preset, ['30','60','90','365'], true)) { $first = $last->modify('-' . ((int)$preset - 1) . ' days'); }
        else { throw new InvalidArgumentException('Select a date range.'); }
        $days = (int)$first->diff($last)->format('%r%a') + 1;
        if ($days < 1 || $days > 1096) { throw new InvalidArgumentException('Choose a range from 1 to 1,096 days.'); }
        if ($comparison === 'none') { $compare=null; }
        elseif ($comparison === 'prior') { $ce = $first->modify('-1 day'); $cs = $ce->modify('-' . ($days - 1) . ' days'); $compare = [$cs->format('Y-m-d'),$ce->format('Y-m-d')]; }
        elseif ($comparison === 'year') { $compare = [self::year_before($first->format('Y-m-d')),self::year_before($end)]; }
        else { throw new InvalidArgumentException('Select a comparison.'); }
        return ['current'=>[$first->format('Y-m-d'),$end], 'comparison'=>$compare];
    }
    public static function percent($current, $previous) { return $current === null || $previous === null || $previous == 0 ? null : ($current / $previous - 1) * 100; }
    public static function validate($input) {
        if (!is_array($input) || ($input['schema'] ?? '') !== 'wgpp-snapshots-v1' || !is_array($input['snapshots'] ?? null) || count($input['snapshots']) > 48) { throw new InvalidArgumentException('Unsupported snapshot file.'); }
        $out = ['schema'=>'wgpp-snapshots-v1','snapshots'=>[]];
        foreach ($input['snapshots'] as $row) {
            if (!is_array($row)) { throw new InvalidArgumentException('Invalid snapshot.'); }
            foreach (['from','to'] as $f) { self::date($row[$f] ?? null); }
            if ($row['from'] > $row['to']) { throw new InvalidArgumentException('Invalid snapshot range.'); }
            $tz = $row['timezone'] ?? '';
            if (!in_array($tz, timezone_identifiers_list(), true)) { throw new InvalidArgumentException('Invalid reporting timezone.'); }
            $clean = ['from'=>$row['from'],'to'=>$row['to'],'timezone'=>$tz];
            foreach (['property','company','website','collected_at'] as $f) {
                if (!is_string($row[$f] ?? null) || strlen($row[$f]) > 250) { throw new InvalidArgumentException('Invalid snapshot identity.'); }
                $clean[$f] = sanitize_text_field($row[$f]);
            }
            $clean['metrics'] = [];
            foreach (['sessions','users','new_users','returning_users','form_events','phone_events'] as $f) {
                $n = $row['metrics'][$f] ?? null;
                if ($n !== null && (!is_numeric($n) || !is_finite((float)$n) || $n < 0 || $n > 1e12)) { throw new InvalidArgumentException('Invalid metric.'); }
                $clean['metrics'][$f] = $n === null ? null : (float)$n;
            }
            $clean['sources'] = [];
            if (!is_array($row['sources'] ?? null) || count($row['sources']) > 500) { throw new InvalidArgumentException('Invalid sources.'); }
            foreach ($row['sources'] as $source) {
                if (!is_string($source['source_medium'] ?? null) || !is_string($source['channel'] ?? null) || strlen($source['source_medium']) > 250 || strlen($source['channel']) > 100 || !is_numeric($source['sessions'] ?? null) || !is_finite((float)$source['sessions']) || $source['sessions'] < 0 || $source['sessions'] > 1e12) { throw new InvalidArgumentException('Invalid source row.'); }
                $clean['sources'][] = ['source_medium'=>sanitize_text_field($source['source_medium']),'channel'=>sanitize_text_field($source['channel']),'sessions'=>(float)$source['sessions']];
            }
            $clean['profile'] = [];
            foreach (['age','gender','country','city','device'] as $dimension) {
                $profile = $row['profile'][$dimension] ?? null;
                if ($profile === null) { $clean['profile'][$dimension] = null; continue; }
                if (!is_array($profile) || count($profile)>1000) { throw new InvalidArgumentException('Invalid audience profile.'); }
                $clean['profile'][$dimension] = [];
                foreach ($profile as $item) {
                    if (!is_string($item['label'] ?? null) || strlen($item['label'])>250 || !is_numeric($item['sessions'] ?? null) || !is_finite((float)$item['sessions']) || $item['sessions']<0 || $item['sessions']>1e12) { throw new InvalidArgumentException('Invalid audience row.'); }
                    $clean['profile'][$dimension][] = ['label'=>sanitize_text_field($item['label']),'sessions'=>(float)$item['sessions']];
                }
            }
            $clean['notes'] = [];
            foreach (array_slice(is_array($row['notes'] ?? null) ? $row['notes'] : [],0,20) as $note) { if (is_string($note)) { $clean['notes'][] = sanitize_text_field(substr($note,0,1000)); } }
            $out['snapshots'][] = $clean;
        }
        return $out;
    }
    public static function source_key($source) {
        [$host,$medium] = array_pad(explode(' / ', strtolower($source['source_medium']),2),2,'');
        $paid = preg_match('/paid|cpc|display|cross-network/i', $source['channel'] . ' ' . $medium);
        if (in_array($host,['facebook','facebook.com','m.facebook.com','l.facebook.com','lm.facebook.com'],true)) { return $paid ? 'Facebook · paid' : 'Facebook · organic'; }
        if (in_array($host,['instagram','instagram.com','l.instagram.com'],true)) { return $paid ? 'Instagram · paid' : 'Instagram'; }
        if ($host === 'ig') { return 'ig · alias unverified'; }
        if ($host === 'google' && $paid) { return 'Google · paid'; }
        if ($host === 'chatgpt.com') { return 'ChatGPT'; }
        return $source['source_medium'];
    }
    public static function analyse($a,$b) {
        $am=$a['metrics']; $bm=$b ? $b['metrics'] : array_fill_keys(array_keys($am),null);
        $events=($am['form_events']===null || $am['phone_events']===null) ? null : $am['form_events']+$am['phone_events'];
        $prior_events=($bm['form_events']===null || $bm['phone_events']===null) ? null : $bm['form_events']+$bm['phone_events'];
        $yield=$events!==null && $am['sessions']>0 ? $events/$am['sessions']*100 : null;
        $prior_yield=$prior_events!==null && $bm['sessions']>0 ? $prior_events/$bm['sessions']*100 : null;
        $ratio=$am['returning_users']!==null && $am['new_users']>0 ? $am['returning_users']/$am['new_users'] : null;
        $prior_ratio=$bm['returning_users']!==null && $bm['new_users']>0 ? $bm['returning_users']/$bm['new_users'] : null;
        $changes=[]; foreach($am as $key=>$n) { $changes[$key]=self::percent($n,$bm[$key]); }
        $groups=[];
        foreach ([['current',$a],['comparison',$b]] as [$side,$snapshot]) {
            if (!$snapshot) { continue; }
            foreach ($snapshot['sources'] as $row) { $key=self::source_key($row); if (!isset($groups[$key])) { $groups[$key]=['name'=>$key,'current'=>0,'comparison'=>$b ? 0 : null,'evidence'=>[]]; } $groups[$key][$side]+=$row['sessions']; $groups[$key]['evidence'][]=$side . ': ' . $row['source_medium'] . ' [' . $row['channel'] . ']'; }
        }
        foreach($groups as &$g) { $g['change']=self::percent($g['current'],$g['comparison']); } unset($g);
        usort($groups,static function($x,$y){return $y['current']<=>$x['current'];});
        return ['metrics'=>$am,'changes'=>$changes,'goal_rate'=>$yield,'prior_goal_rate'=>$prior_yield,'goal_change'=>self::percent($yield,$prior_yield),'horizontal'=>self::percent($ratio,$prior_ratio),'vertical'=>$changes['sessions'],'sources'=>array_values($groups)];
    }
    public static function source_summary($sources) {
        usort($sources,static function($a,$b){return ($b['current']<=>$a['current']) ?: strcmp($a['name'],$b['name']);});
        $top=array_slice($sources,0,3);$rest=array_slice($sources,3);
        if ($rest) {
            $other=['name'=>'Other sources','current'=>0,'comparison'=>0,'evidence'=>[count($rest).' additional sources combined.']];
            foreach($rest as $row){$other['current']+=$row['current'];if($row['comparison']===null){$other['comparison']=null;}elseif($other['comparison']!==null){$other['comparison']+=$row['comparison'];}}
            $other['change']=self::percent($other['current'],$other['comparison']);$top[]=$other;
        }
        return $top;
    }
    public static function lookup($from,$to) {
        // A trusted server-side connector may supply an exact-range snapshot for this principal.
        $provided=apply_filters('wgpp_analytics_snapshot',null,['from'=>$from,'to'=>$to,'user_id'=>get_current_user_id(),'company_id'=>WGPP_Company::context()['id']]);
        if ($provided !== null) { $valid=self::validate(['schema'=>'wgpp-snapshots-v1','snapshots'=>[$provided]]); $row=$valid['snapshots'][0]; if ($row['from']!==$from || $row['to']!==$to) { throw new InvalidArgumentException('Provider returned different dates.'); } $row['mode']='connected'; return $row; }
        if (class_exists('WGPP_GA4') && WGPP_GA4::config(WGPP_Company::context()['id'])) { return null; }
        wp_enqueue_script('wgpp-email',plugins_url('assets/email.js',__FILE__),['wgpp-analytics'],'0.6.6-preview.4',true);
        $saved=get_post_meta(WGPP_Company::context()['id'],self::META,true);
        foreach (($saved['snapshots'] ?? []) as $row) { if ($row['from']===$from && $row['to']===$to) { $row['mode']='imported'; return $row; } }
        return null;
    }
    public static function ajax() {
        if (!self::allowed()) { wp_send_json_error(['message'=>'Access denied.'],403); }
        check_ajax_referer('wgpp_analytics','nonce');
        nocache_headers();
        try {
            $q=[]; foreach(['preset','end','start','comparison'] as $key) { if (isset($_GET[$key]) && !is_string($_GET[$key])) { throw new InvalidArgumentException('Invalid selection.'); } $q[$key]=sanitize_text_field(wp_unslash($_GET[$key] ?? '')); }
            $ranges=self::ranges($q['preset'],$q['end'],$q['start'],$q['comparison']);
            $a=self::lookup(...$ranges['current']); $b=$ranges['comparison']?self::lookup(...$ranges['comparison']):null;
            WGPP_GA4::progress(WGPP_Company::context()['id'],array_values(array_filter($ranges)));
            $a=self::lookup(...$ranges['current']); $b=$ranges['comparison']?self::lookup(...$ranges['comparison']):null;
            if ($a && $b && ($a['property']!==$b['property'] || $a['timezone']!==$b['timezone'])) { throw new InvalidArgumentException('Comparison property or timezone differs.'); }
            $analysis=$a ? self::analyse($a,$b) : null;
            $company=WGPP_Company::context()['id'];
            $collection=['current'=>WGPP_GA4::status($company,...$ranges['current']),'comparison'=>$ranges['comparison']?WGPP_GA4::status($company,...$ranges['comparison']):'off'];
            wp_send_json_success(['ranges'=>$ranges,'snapshot'=>$a,'comparison_snapshot'=>$b,'comparison_available'=>(bool)$b,'comparison_enabled'=>$ranges['comparison']!==null,'analysis'=>$analysis,'collection_detail'=>['current'=>WGPP_GA4::detail($company,...$ranges['current']),'comparison'=>$ranges['comparison']?WGPP_GA4::detail($company,...$ranges['comparison']):''],'source_summary'=>$analysis?self::source_summary($analysis['sources']):[],'collection'=>$collection]);
        } catch (InvalidArgumentException $e) { wp_send_json_error(['message'=>$e->getMessage()],422); }
        catch (RuntimeException $e) { wp_send_json_error(['message'=>$e->getMessage()],503); }
    }
    public static function sample() {
        if (!WGPP_Company::can('connections')) { wp_die('Access denied.','',['response'=>403]); }
        check_admin_referer('wgpp_sample');
        $id=WGPP_Company::context()['id'];
        $saved=get_post_meta($id,self::META,true);
        if (!empty($saved['snapshots'])) { wp_die('Sample data cannot replace existing company snapshots.'); }
        $last=(new DateTimeImmutable('yesterday',new DateTimeZone('America/Chicago')));
        $snapshots=[];
        foreach ([0,1] as $period) {
            $end=$last->modify('-'.($period*30).' days');
            $start=$end->modify('-29 days');
            $snapshots[]=['from'=>$start->format('Y-m-d'),'to'=>$end->format('Y-m-d'),'timezone'=>'America/Chicago',
                'property'=>'sample','company'=>'Example Company — SAMPLE DATA','website'=>'https://example.com','collected_at'=>gmdate('c'),
                'metrics'=>['sessions'=>$period?1000:1040,'users'=>$period?850:900,'new_users'=>$period?750:820,'returning_users'=>$period?150:130,'form_events'=>$period?40:35,'phone_events'=>$period?20:25],
                'sources'=>[['source_medium'=>'google / organic','channel'=>'Organic Search','sessions'=>$period?600:620],['source_medium'=>'facebook / social','channel'=>'Organic Social','sessions'=>$period?50:70],['source_medium'=>'(direct) / (none)','channel'=>'Direct','sessions'=>$period?350:350]],
                'profile'=>['age'=>[['label'=>'25–34','sessions'=>40],['label'=>'35–44','sessions'=>30],['label'=>'unknown','sessions'=>$period?930:970]],
                    'gender'=>[['label'=>'Female','sessions'=>40],['label'=>'Male','sessions'=>50],['label'=>'unknown','sessions'=>$period?910:950]],
                    'country'=>[['label'=>'United States','sessions'=>$period?900:950],['label'=>'unknown','sessions'=>$period?100:90]],
                    'city'=>[['label'=>'Example City','sessions'=>$period?750:800],['label'=>'unknown','sessions'=>$period?250:240]],
                    'device'=>[['label'=>'desktop','sessions'=>$period?600:650],['label'=>'mobile','sessions'=>$period?400:390]]],
                'notes'=>['SAMPLE DATA. These are invented demonstration figures, not Aladdin or connected Google results.']];
        }
        update_post_meta($id,self::META,self::validate(['schema'=>'wgpp-snapshots-v1','snapshots'=>$snapshots]));
        wp_safe_redirect(admin_url('tools.php?page=wgpp-preview')); exit;
    }
    public static function import() {
        if (!WGPP_Company::can('connections')) { wp_die('Access denied.', '', ['response'=>403]); }
        check_admin_referer('wgpp_import');
        try {
            $f=$_FILES['snapshot'] ?? [];
            if (($f['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || ($f['size'] ?? 0)>2097152 || !is_uploaded_file($f['tmp_name'] ?? '')) { throw new InvalidArgumentException('Choose a JSON snapshot file under 2 MB.'); }
            $input=json_decode(file_get_contents($f['tmp_name']),true,64,JSON_THROW_ON_ERROR);
            $data=self::validate($input);
            $identities=[]; foreach($data['snapshots'] as $row) { $identities[$row['property'].'|'.$row['timezone']]=true; }
            if (count($identities)>1) { throw new InvalidArgumentException('Import one property and timezone at a time.'); }
            update_post_meta(WGPP_Company::context()['id'],self::META,$data);
            set_transient('wgpp_notice_'.get_current_user_id(),'Snapshot data imported for your company.',60);
        } catch (Throwable $e) { set_transient('wgpp_notice_'.get_current_user_id(),'Import failed. Check the snapshot format and dates.',60); }
        wp_safe_redirect(admin_url('tools.php?page=wgpp-preview')); exit;
    }
    public static function render($account = '') {
        if (!self::allowed()) { return '<p>Sign in with an enabled Pro account to view analytics.</p>'; }
        wp_enqueue_style('wgpp-analytics',plugins_url('assets/analytics.css',__FILE__),[],'0.6.6-preview.4');
        wp_enqueue_script('wgpp-export-name',plugins_url('assets/export-name.js',__FILE__),[],'0.6.6-preview.4',true);
        wp_enqueue_script('wgpp-analytics',plugins_url('assets/analytics.js',__FILE__),['wgpp-export-name'],'0.6.6-preview.4',true);
        wp_enqueue_script('wgpp-trends',plugins_url('assets/trends.js',__FILE__),['wgpp-analytics'],'0.6.6-preview.4',true);
        wp_enqueue_script('wgpp-portal',plugins_url('assets/portal.js',__FILE__),['wgpp-analytics'],'0.6.27-preview',true);
        wp_enqueue_script('wgpp-facebook',plugins_url('assets/facebook.js',__FILE__),['wgpp-analytics'],'0.6.6-preview.4',true);
        wp_enqueue_script('wgpp-reviews',plugins_url('assets/reviews.js',__FILE__),['wgpp-analytics'],'0.6.27-preview',true);
        wp_enqueue_script('wgpp-email',plugins_url('assets/email.js',__FILE__),['wgpp-analytics'],'0.6.6-preview.4',true);
        $saved=get_post_meta(WGPP_Company::context()['id'],self::META,true); $rows=$saved['snapshots'] ?? []; $latest=null;
        foreach($rows as $row) { if (!$latest || $row['to']>$latest['to'] || ($row['to']===$latest['to'] && $row['from']>$latest['from'])) { $latest=$row; } }
        $connection=WGPP_GA4::config(WGPP_Company::context()['id']);
        if ($connection) { $latest=null; }
        $today=new DateTimeImmutable('yesterday',new DateTimeZone($connection['timezone'] ?? $latest['timezone'] ?? 'America/Chicago'));
        $end=$latest['to'] ?? $today->format('Y-m-d'); $start=$latest['from'] ?? $today->modify('-29 days')->format('Y-m-d');
        $brand=WGPP_Company::brand(WGPP_Company::context()['id']);
        $config=['workspace'=>WGPP_Company::context()['id'],'brand_logo'=>!empty($brand['logo_id'])?(wp_get_attachment_image_url($brand['logo_id'],'medium') ?: ''):'','url'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('wgpp_analytics'),'styles'=>plugins_url('assets/analytics.css',__FILE__),'reviews_ready'=>WGPP_Reviews::ready(),'can_review'=>WGPP_Company::can('review')];
        $notice=get_transient('wgpp_notice_'.get_current_user_id());
        if ($notice) { delete_transient('wgpp_notice_'.get_current_user_id()); }
        ob_start(); ?>
        <section id="report" class="wgpa" data-config="<?php echo esc_attr(wp_json_encode($config)); ?>">
          <?php if($notice): ?><p role="status"><?php echo esc_html($notice); ?></p><?php endif; ?>
          <p class="wgpa-preview-note">Choose dates, compare your results and see how your Brand is moving. Connect your reporting accounts under Account → Connections.</p>
          <header><div><?php echo WGPP_Company::product_logo(); ?><div class="wgpa-company-identity" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin:14px 0 4px"><?php if($config['brand_logo']): ?><img class="wgpa-company-logo" src="<?php echo esc_url($config['brand_logo']); ?>" alt="<?php echo esc_attr(WGPP_Company::context()['name'].' logo'); ?>" style="display:block;width:auto;max-width:120px;max-height:64px;object-fit:contain"><?php endif; ?><h2 data-company style="margin:0"><?php echo esc_html(WGPP_Company::context()['name']); ?></h2></div><p class="wgpa-title">Message Magnet Performance</p></div><div><span>PRO ANALYTICS</span><br><button type="button" data-print disabled>Print / Save Snapshot</button><small data-print-status role="status" style="display:block;max-width:240px;margin-top:6px;font:12px/1.4 Arial,sans-serif">Loading selected report…</small></div></header>
          <div data-sticky-controls><nav class="wgpa-tablist wgpa-primary-tabs" aria-label="Analytics workspace" data-primary-tabs><button type="button" data-primary-tab="report">Report</button><button type="button" data-primary-tab="review">Review</button><button type="button" data-primary-tab="account">Account</button></nav>
          <div data-shared-dates><form class="wgpa-controls"><label>Reporting range<select name="preset"><option value="30">30 days</option><option value="60">60 days</option><option value="90">90 days</option><option value="365">1 year</option><option value="custom" selected>Custom</option></select></label><label>Start<input name="start" type="date" value="<?php echo esc_attr($start); ?>" required></label><label>End<input name="end" type="date" value="<?php echo esc_attr($end); ?>" required></label><label>Compare with<select name="comparison"><option value="none">None</option><option value="prior" selected>Prior period</option><option value="year">Previous year</option></select></label><button>Apply dates</button></form></div></div>
          <section data-primary-panel="report"><nav class="wgpa-report-links" aria-label="Report sections"><button type="button" data-report-jump="overview">Overview</button><button type="button" data-report-jump="awareness">Awareness</button><button type="button" data-report-jump="acquisition">Acquisition</button><button type="button" data-report-jump="retention">Retention</button><button type="button" data-report-jump="timeline">Timeline</button><button type="button" data-report-jump="sources">Sources</button><button type="button" data-report-jump="audience">Audience</button><button type="button" data-report-jump="goals">Goals</button><button type="button" data-report-jump="email">Email</button><button type="button" data-report-jump="facebook">Facebook</button><button type="button" data-report-jump="instagram">Instagram</button></nav><p data-status role="status" aria-live="polite">Loading snapshot…</p><?php if(!$connection && !$rows): ?><section class="wgpa-account-card"><h3>Connect your company reporting</h3><p>No Google Analytics property is connected for <?php echo esc_html(WGPP_Company::context()['name']); ?> yet.</p><?php if(WGPP_Company::can('connections')): ?><p><a class="wgpa-button" href="<?php echo esc_url(add_query_arg('workspace',WGPP_Company::context()['id'],home_url('/pro-analytics/')).'#account-connections'); ?>">Set up reporting connections →</a></p><?php else: ?><p>Your company owner can connect Google Analytics under Account → Connections.</p><?php endif; ?></section><?php endif; ?><div data-report hidden><section class="wgpa-movement"><h3>Brand Axis · Balance of the Loop</h3><svg data-loop role="img" aria-label="Relative Brand movement"></svg><p data-direction></p><div data-rate class="wgpa-rate"></div></section><section class="wgpa-filter-status" aria-label="Traffic quality and filtering"><div data-filter-status></div></section><section data-campaign aria-label="Awareness, Acquisition and Retention"></section><h3>Website results · supporting detail</h3><div data-headline class="wgpa-headline"></div><details data-trends><summary>View performance over time</summary><p data-trend-status role="status">Open to load the timeline for the displayed report dates.</p><div data-trend-charts></div><button type="button" data-trend-retry hidden>Retry timeline</button></details><div data-sources class="wgpa-sources"></div><div class="wgpa-columns"><section><h3>Audience results</h3><div data-audience></div></section><section><h3>Goal events</h3><div data-goals></div></section></div><section><h3>Audience profile</h3><div data-profile></div></section><details><summary>Measurement notes</summary><div data-notes></div></details></div>
          <section data-email><h3>Email campaign performance</h3><p data-email-status role="status">Email reporting loads independently of website analytics.</p><div data-email-results></div></section>
          <details data-facebook><summary>Facebook Page performance · pilot</summary><p data-facebook-status role="status">Connect a Facebook Page under Account → Connections, then open this section to load available reporting.</p><div data-facebook-results></div></details><details data-instagram><summary>Instagram performance · pilot</summary><p data-instagram-status role="status">Connect Instagram under Account → Connections, then open this section to load reporting.</p><div data-instagram-results></div></details></section><section data-primary-panel="review"><p data-review-intro class="wgpa-small-note">Reviews use the selected reporting dates. Changing dates does not generate a review.</p><section data-review-controls><h3>Pro Analytics Review</h3><button type="button" data-review-request disabled>Request Pro Analytics Review</button><p data-review-status role="status"></p><div data-review-confirm hidden><p data-review-quote></p><button type="button" data-review-generate>Generate Pro Analytics Review</button> <button type="button" data-review-cancel>Cancel</button></div><p>Analytics and date comparisons are available to every company member. Owners and analysts may request a Pro Analytics Review. Every company member can read saved reviews. Pro includes four new reviews per company subscription month, including on annual plans. Preview workspaces use the calendar month in America/Chicago. Reopening or printing a saved review does not use the allowance. Analytical reviews work without an AI key; AI enhancement is an optional administrator setting. Changing dates does not generate a review.</p><h3>Saved Pro Analytics Reviews</h3><div data-review-history></div></section><article data-saved-review hidden></article>
          </section><section data-primary-panel="account"><?php echo $account; ?></section>
        </section>
        <?php if(is_admin() && current_user_can('manage_options') && empty($rows)): ?>
        <section class="wgpa-import"><h3>Try the dashboard with sample data</h3><p>Loads invented demonstration figures for your company workspace. No live account is connected. Available only while there are no imported snapshots.</p><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('wgpp_sample'); ?><input type="hidden" name="action" value="wgpp_sample"><button>Load sample data</button></form></section>
        <?php endif; ?>
        <?php if(is_admin() && current_user_can('manage_options')): ?>
        <details class="wgpa-import"><summary>Import private development snapshot</summary><p>Imported data is shared with your company team. Imports replace the company snapshot collection. Exact-range records are required; unavailable dates are never estimated.</p><form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('wgpp_import'); ?><input type="hidden" name="action" value="wgpp_import"><input type="file" name="snapshot" accept=".json,application/json" required><button>Import snapshot</button></form></details>
        <?php endif; return ob_get_clean();
    }
}
