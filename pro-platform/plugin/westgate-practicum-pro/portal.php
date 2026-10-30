<?php
if(!defined('ABSPATH')){exit;}
final class WGPP_Portal {
    public static function is_blog($url) {
        $path=parse_url($url,PHP_URL_PATH);
        return is_string($path) && ($path==='/blog' || str_starts_with($path,'/blog/'));
    }
    public static function connection_summary($c) {
        $id=$c['id'];$google=(bool)get_post_meta($id,WGPP_Google::TOKEN,true);$ga=$google && (bool)WGPP_GA4::config($id);$provider=WGPP_Mailchimp::provider($id);
        $email=$provider==='mailchimp'?(bool)get_post_meta($id,WGPP_Mailchimp::AUTH,true):($provider==='constant_contact'?(bool)get_post_meta($id,WGPP_Constant_Contact::TOKEN,true):false);
        $page='';$ig='';try{$saved=get_post_meta($id,WGPP_Facebook::PAGE,true);if($saved){$page=WGPP_Facebook::decrypt($saved,'company:'.$id.':page')['name']??'';}}catch(RuntimeException $e){}
        try{$saved=get_post_meta($id,WGPP_IG_Reports::ACCOUNT,true);if($saved){$ig=WGPP_Facebook::decrypt($saved,'company:'.$id.':instagram')['username']??'';}}catch(RuntimeException $e){}
        $rows=['google'=>['Google Analytics',$ga?'Connected · Property '.WGPP_GA4::config($id)['property']:($google?'Finish property verification':'Not connected')],'email'=>[$provider?WGPP_Mailchimp::label($id):'Email',$email?'Connected':($provider==='none'?'Not used':'Setup needed')],'facebook'=>['Facebook',$page?'Connected · '.$page:'Not connected'],'instagram'=>['Instagram',$ig?'Connected · @'.$ig:'Not connected']];
        $html='<nav aria-label="Saved reporting connections" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:8px;margin:12px 0">';foreach($rows as $service=>[$label,$state]){$html.='<a href="'.esc_url(WGPP_Company::connection_url($service)).'" style="display:block;padding:10px 12px;background:#eaf5fb;border:1px solid #bdd6e5;border-radius:6px;color:#074b78;text-decoration:none;font-size:13px;line-height:1.5"><strong>'.esc_html($label).'</strong><br>'.esc_html($state).'</a>';}$html.='</nav><p class="wgpa-small-note">Saved connections stay in place. Open a service below only to finish setup or make changes. Each company keeps its own selected property, audience and social accounts.</p>';return $html;
    }
    public static function account() {
        $c=WGPP_Company::context();if(!$c){return '';}
        $brand=WGPP_Company::brand($c['id']);$owner=WGPP_Company::can('connections');
        ob_start(); ?>
        <h3>Account · <?php echo esc_html($c['name']); ?></h3>
        <p><?php echo esc_html(ucfirst($c['role'])); ?> access · Company workspace</p>
        <p class="wgpa-small-note"><strong>Pro Analytics Guide:</strong> <a href="<?php echo esc_url(home_url('/pro-analytics-guide/')); ?>">Setup, reporting and Moves to Make</a> · <a href="<?php echo esc_url(home_url('/pro-analytics-guide/#smm-guide-foundation')); ?>">Brand Axis and Message Matrix explained</a>. Full Live Campaign lessons follow your existing curriculum access.</p>
        <div data-account-tabs class="wgpa-tablist" aria-label="Account sections">
          <button type="button" data-account-tab="company">1. Company</button><button type="button" data-account-tab="connections">2. Connections</button><button type="button" data-account-tab="foundations">Brand foundations</button><button type="button" data-account-tab="profile">Your login</button><button type="button" data-account-tab="users">Users &amp; Invitations</button><button type="button" data-account-tab="delivery">Report Delivery</button><button type="button" data-account-tab="billing">Billing</button>
        </div>
        <section data-account-panel="foundations"><?php echo WGPP_Documents::render(); ?></section>
        <section data-account-panel="delivery"><?php echo WGPP_Report_Delivery::render($c); ?></section>
        <section data-account-panel="profile"><?php echo WGPP_Account::profile($c); ?></section>
        <section data-account-panel="company"><h4>Company profile</h4>
          <?php if($owner){echo WGPP_Company::brand_render($c);}else{echo '<p>Company profile and service-area settings are managed by your owner.</p>';if(!empty($brand['website'])){echo '<p>Website: '.esc_html($brand['website']).'</p>';}} ?>
          <p class="wgpa-small-note">SMM blog content is identified by the <strong>/blog/</strong> URL path. This rule works independently of the website platform.</p>
        </section>
        <section data-account-panel="connections"><h4>Connected accounts</h4><?php echo self::connection_summary($c); ?><p>Connect only the services this company uses. Complete Google Analytics for website reporting; email and social are optional. Saving a website or profile link does not authorize a reporting connection.</p>
          <?php echo '<section class="wgpa-account-card" data-connection="google"><h4>1. Website analytics</h4><p>'.(WGPP_GA4::config($c['id']) && get_post_meta($c['id'],WGPP_Google::TOKEN,true)?'Connected · Website reporting is ready.':(get_post_meta($c['id'],WGPP_Google::TOKEN,true)?'Google authorized · Select and verify the company property to finish.':'Not connected · Connect Google, then select this company’s Analytics property.')).'</p>'.WGPP_Google::render().'</section>';echo WGPP_Mailchimp::render();echo '<section class="wgpa-account-card"><h4>3. Facebook and Instagram <small>Optional</small></h4><p>'.(get_post_meta($c['id'],WGPP_Facebook::PAGE,true)?'Facebook Page connected. Manage its linked Instagram account below.':'Not connected · Authorize Meta and select this company’s Facebook Page.').'</p>'.WGPP_Facebook::render().'</section>'; ?>
          <section class="wgpa-account-card"><h4>Social accounts</h4><p>Facebook and Instagram reporting connections are available above. Other direct social connections are not yet implemented. Profile links in Company identify your channels; they do not authorize API access. Website visits from social sources remain available in the Report.</p></section>
        </section>
        <section data-account-panel="users"><h4>Users &amp; Invitations</h4>
          <?php echo WGPP_Company::can('members')?WGPP_Team::render($c).WGPP_Account::owner_controls($c):'<p>Your company owner manages invitations and team roles. Analytics access does not enroll team members in the curriculum.</p>'; ?>
        </section>
        <section data-account-panel="billing"><h4>Billing</h4><?php if(WGPP_Company::complimentary($c['id'])){echo '<p><strong>Complimentary Pro account</strong> · No subscription charge. Includes five seats and four Pro Analytics Reviews per company month.</p>';} ?><p>Pro subscriptions are managed through WishList Member. Company access follows the paying account’s membership; invited seats do not buy a separate subscription. Contact SMM support for billing assistance. Self-service subscription changes and invoices are not connected in this dashboard yet.</p><p>The current workspace supports five people including the owner. Review allowances appear in the Review tab when the review service is configured.</p></section>
        <?php return ob_get_clean();
    }
}

/** Role-aware front door. Access remains governed by company and WishList entitlements. */
final class WGPP_Landing {
    public static function boot() {
        add_shortcode('wgpp_workspace',[__CLASS__,'render']);
        add_filter('login_redirect',[__CLASS__,'login_redirect'],9999,3);
        add_action('template_redirect',[__CLASS__,'route'],0);
    }
    public static function url() { return home_url('/pro-workspace/'); }
    public static function company_for($user) {
        $c=WGPP_Company::for_user((int)get_user_meta($user,WGPP_Company::ACTIVE,true),$user);
        if($c){return $c;}$companies=WGPP_Company::companies($user);return $companies?reset($companies):null;
    }
    public static function eligible($user) {
        return $user>0 && (self::company_for($user)!==null || WGPP_WishList::payer_active($user));
    }
    public static function login_redirect($redirect,$requested,$user) {
        if(!is_object($user) || empty($user->ID) || !self::eligible((int)$user->ID)){return $redirect;}
        // Preserve invitations, callbacks, course deep links and deliberate admin destinations.
        $target=$requested!==''?$requested:$redirect;
        $parts=parse_url($target);$home=parse_url(home_url('/'));
        if($parts===false || (isset($parts['host']) && strcasecmp($parts['host'],$home['host'])!==0) || !empty($parts['query']) || !empty($parts['fragment'])){return $redirect;}
        $path=rtrim($parts['path']??'','/');$base=rtrim($home['path']??'','/');
        if(in_array($path,[$base,$base.'/dashboard',$base.'/pro-workspace'],true) || ($requested==='' && $path===$base.'/wp-admin')){return self::url();}
        return $redirect;
    }
    public static function route() {
        // Retired weekly-report templates append obsolete output and can fail on current PHP.
        // Keep old links useful; the destination enforces current company/member access.
        if(is_page(2487) || is_page(2489) || is_page('member-analytics') || is_page('live-campaign-analytics')) {
            nocache_headers();wp_safe_redirect(home_url('/pro-analytics/'),302);exit;
        }
        if(is_page('pro-workspace')) {
            if(!defined('DONOTCACHEPAGE')){define('DONOTCACHEPAGE',true);}nocache_headers();
        }
        // WishList and checkout can send members to the legacy course dashboard.
        // Intercept only that generic landing, never an explicitly requested lesson.
        if(is_user_logged_in() && (is_page(372)||is_page('dashboard')) && empty($_GET['invite']) && ($_GET['courseware']??'')!=='1' && self::eligible(get_current_user_id())) {
            nocache_headers();wp_safe_redirect(self::url());exit;
        }
    }
    private static function card($title,$text,$label,$path,$primary=false) {
        echo '<article class="wgpl-card'.($primary?' wgpl-primary':'').'"><h3>'.esc_html($title).'</h3><p>'.esc_html($text).'</p><a class="wgpl-button" href="'.esc_url(home_url($path)).'">'.esc_html($label).' <span aria-hidden="true">→</span></a></article>';
    }
    public static function render() {
        if(!is_user_logged_in()){return '<p>Sign in to open your company workspace.</p><p><a href="'.esc_url(wp_login_url(self::url())).'">Sign in to Pro Analytics</a></p>';}
        $user=get_current_user_id();$c=WGPP_Company::context();$payer=WGPP_WishList::payer_active($user);
        if(!$c && !$payer){return '<h2>Your account</h2><p>No active Pro workspace is assigned to this login. Use your company invitation or contact your company owner.</p><p><a href="'.esc_url(home_url('/dashboard/')).'">Open your membership dashboard</a></p>';}
        $u=wp_get_current_user();$name=trim((string)$u->first_name);if($name===''){$name=trim((string)$u->display_name);}if($name==='' || str_starts_with($name,'smm_')){$name='back';}
        wp_enqueue_style('wgpp',plugins_url('portal.css',__FILE__),[],'0.6.7-preview');
        ob_start(); ?>
        <style>
        .wgpl{--navy:#074b70;--cyan:#20afda;font-family:Arial,Helvetica,sans-serif;color:#173d51;max-width:1160px;margin:0 auto 48px}.wgpl *{box-sizing:border-box}.wgpl-hero{background:var(--navy);color:#fff;padding:34px;border-radius:14px;border-top:5px solid var(--cyan)}.wgpl .wgpl-hero h2{color:#fff!important;font:700 clamp(28px,4vw,42px)/1.15 Arial,sans-serif;margin:8px 0 16px}.wgpl-hero p{margin:8px 0}.wgpl-kicker{font-size:13px;font-weight:700;letter-spacing:1.4px;text-transform:uppercase}.wgpl-grid{display:grid;grid-template-columns:1.35fr 1fr;gap:20px;margin:22px 0}.wgpl-card{padding:26px;border:1px solid #c7dfe9;border-radius:12px;background:#f4fafd}.wgpl .wgpl-card h3{font:700 24px/1.2 Arial,sans-serif;color:var(--navy);margin:0 0 12px}.wgpl-card p{font-size:16px;line-height:1.6;margin:0 0 20px}.wgpl-primary{border-top:4px solid var(--cyan)}.wgpl a.wgpl-button{display:inline-block;background:var(--navy);color:#fff!important;text-decoration:none;padding:12px 18px;border-radius:6px;font-weight:700;font-size:15px}.wgpl a:focus-visible{outline:3px solid var(--cyan);outline-offset:4px}.wgpl-tools{display:flex;gap:12px;flex-wrap:wrap;margin-top:16px}.wgpl-tools a{color:var(--navy);font-weight:700}.wgpl-secondary{padding:22px 26px;border-left:4px solid var(--cyan);background:#f5f8fa}.wgpl .wgpl-secondary h3{font:700 21px Arial,sans-serif;color:var(--navy);margin:0 0 10px}.wgpl-secondary p{font-size:15px;margin:8px 0}.wgpl-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:22px 0}.wgpl-steps a{padding:20px;border:1px solid #c7dfe9;border-radius:8px;color:var(--navy);text-decoration:none;background:#f4fafd}.wgpl-steps strong,.wgpl-steps span{display:block}.wgpl-steps span{font-size:14px;line-height:1.5;margin-top:8px}@media(max-width:680px){.wgpl-grid,.wgpl-steps{grid-template-columns:1fr}.wgpl-hero{padding:24px}.wgpl-card{padding:22px}}
        </style>
        <section class="wgpl" aria-label="Your Pro workspace">
          <header class="wgpl-hero"><?php echo WGPP_Company::product_logo(); ?><p class="wgpl-kicker">Your Pro workspace</p><h2>Welcome, <?php echo esc_html($name); ?>.</h2>
            <?php if($c){ ?><p><strong><?php echo esc_html($c['name']); ?></strong> · <?php echo esc_html(ucfirst($c['role'])); ?></p><p>See how your Brand is moving. Decide what to say next.</p><?php }else{ ?><p>Start by creating your company workspace. Then connect your reporting accounts.</p><?php } ?>
          </header>
          <?php if(!$c){echo WGPP_Company::render();}else{
              $brand=WGPP_Company::brand($c['id']);$connected=WGPP_GA4::config($c['id'])!==null;
              if($c['role']==='owner'){
                $profileReady=!empty($brand['website']);$provider=WGPP_Mailchimp::provider($c['id']);$emailConnected=$provider==='mailchimp'?(bool)get_post_meta($c['id'],WGPP_Mailchimp::AUTH,true):($provider==='constant_contact'?(bool)get_post_meta($c['id'],WGPP_Constant_Contact::TOKEN,true):false);
                $next=!$profileReady?'company':'connections'; ?>
                <section class="wgpl-secondary" style="margin-top:22px"><h3><?php echo $connected?'Your company setup':'Finish setting up '.esc_html($c['name']); ?></h3><p>Company details → Reporting connections → Your Report. Each company keeps its own settings and results.</p>
                <?php if(!$connected){ ?><p><a class="wgpl-button" href="<?php echo esc_url(home_url('/pro-analytics/#account-'.$next)); ?>">Continue setup →</a></p><?php } ?>
                <div class="wgpl-steps">
                  <a href="<?php echo esc_url(home_url('/pro-analytics/#account-company')); ?>"><strong>1. Company details</strong><span><?php echo $profileReady?'Website saved · Review logo and service area.':'Add the website, logo and service area.'; ?></span></a>
                  <a href="<?php echo esc_url(home_url('/pro-analytics/#account-connections')); ?>"><strong>2. Reporting connections</strong><span><?php echo $connected?'Google Analytics connected.':'Google Analytics not connected.'; ?><br><?php echo esc_html($provider==='none'?'Email reporting not used':($provider?WGPP_Mailchimp::label($c['id']).($emailConnected?' connected.':' not connected.'):'Choose your email provider.')); ?></span></a>
                  <a href="<?php echo esc_url(home_url('/pro-analytics/#account-users')); ?>"><strong>3. Team access · Optional</strong><span>Invite colleagues after company setup. You can do this later.</span></a>
                </div></section>
              <?php }
              $reports=WGPP_Native_Reviews::dispatch(null,'list',['company_id'=>$c['id'],'principal'=>$user],[])['reports'];
              $latest=$reports[0]??null;$reviewText='Turn your results into a clear content direction and practical Moves to Make.';
              if($latest){try{$at=(new DateTimeImmutable($latest['created_at']))->setTimezone(new DateTimeZone('America/Chicago'))->format('m/d/Y g:i A').' Central Time';$reviewText='Latest saved review: '.$at.'. Open the Review tab to read it with your team.';}catch(Exception $e){}}
              echo '<div class="wgpl-grid">';
              self::card('Your Brand performance','Open your reporting snapshot, change dates and compare results across your website, social channels and email.','Open Report','/pro-analytics/',true);
              self::card('Pro Analytics Review',$reviewText,$latest?'Read saved reviews':($c['role']==='viewer'?'Open Review':'Create a Pro Analytics Review'),'/pro-analytics/#review');
              echo '</div><section class="wgpl-secondary"><h3>'.($c['role']==='owner'?'Manage your workspace':'Your account').'</h3><p>'.($c['role']==='owner'?'Keep your company, connections and team up to date.':'Manage your personal login. Your company owner manages connections and invitations.').'</p><div class="wgpl-tools"><a href="'.esc_url(home_url('/pro-analytics/#account-profile')).'">Your login</a>';
              if($c['role']==='owner'){foreach(['company'=>'Company profile','connections'=>'Connections','users'=>'Users & Invitations','billing'=>'Billing assistance'] as $tab=>$label){echo '<a href="'.esc_url(home_url('/pro-analytics/#account-'.$tab)).'">'.esc_html($label).'</a>';}}
              echo '</div></section>';
          }
          if($payer){ ?><section class="wgpl-secondary" style="margin-top:20px"><h3>Build the thinking behind your results</h3><p>Your Pro membership also includes the Live Campaign curriculum. Explore the Social Media Magnet framework when you want to sharpen your Message Matrix and publishing direction.</p><a href="<?php echo esc_url(home_url('/live-campaign-library/')); ?>">Open the Live Campaign curriculum →</a></section><?php } ?>
        </section>
        <?php return WGPP_Company::decorate(ob_get_clean(),'workspace');
    }
}
