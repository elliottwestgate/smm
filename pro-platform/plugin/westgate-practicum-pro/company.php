<?php
if (!defined('ABSPATH')) { exit; }

/** Company boundary for Pro. Curriculum/student membership remains independent. */
final class WGPP_Company {
    const TYPE = 'wgpp_company';
    const ACTIVE = '_wgpp_active_company';
    public static function boot() {
        register_post_type(self::TYPE, ['public'=>false,'show_ui'=>false,'supports'=>['title']]);
        WGPP_Team::boot();
        WGPP_Documents::boot();
        add_action('admin_post_wgpp_brand',[__CLASS__,'brand_save']);
        add_action('admin_post_wgpp_switch_company',[__CLASS__,'switch_company']);
        add_action('init',[__CLASS__,'guard_request'],20);
        add_filter('script_loader_src',[__CLASS__,'asset_version'],20,2);
        add_filter('style_loader_src',[__CLASS__,'asset_version'],20,2);
        add_filter('wp_redirect',[__CLASS__,'keep_company'],20);
        add_action('wp_head',[__CLASS__,'presentation']);
        add_filter('the_content',[__CLASS__,'product_content'],30);
    }
    /** Product pages use their own masthead instead of the theme's decorative photo banner. */
    public static function presentation() {
        if(!is_page(['pro-analytics','pro-workspace','pro-analytics-overview','buy-pro-analytics'])){return;}
        echo '<style id="wgpp-product-presentation">.hero{display:none!important}</style>';
    }
    public static function product_logo() {
        $url=home_url('/wp-content/uploads/pro-analytics-logo_2026-10-19_09-55-59_CT.png');
        return '<div class="wgpp-product-brand" style="display:inline-block;max-width:100%;padding:6px 10px;background:#fff;border-radius:5px;box-sizing:border-box"><img class="wgpa-logo wgpp-pro-logo" src="'.esc_url($url).'" alt="Pro Analytics" style="display:block;width:280px!important;max-width:100%!important;height:48px!important;object-fit:cover;object-position:center;padding:0!important;margin:0!important;background:transparent"><span class="wgpp-product-byline" style="display:block;font:600 13px/1.5 Arial,Helvetica,sans-serif;letter-spacing:.06em;color:#074b78;text-align:center">Social Media Magnet Pro</span></div>';
    }
    public static function product_content($content) {
        if(!is_page(['pro-analytics-overview','buy-pro-analytics'])){return $content;}
        return preg_replace('~<img\b[^>]*Social-Media-Magnet-Pro-Horizontal-3-1536x421\.png[^>]*>~i',self::product_logo(),$content);
    }
    public static function complimentary($company) {
        $grant=get_post_meta($company,'_wgpp_complimentary',true);
        return is_array($grant) && !empty($grant['granted_by']) && !empty($grant['granted_at']) && strtotime($grant['granted_at'])>0;
    }
    public static function email_select($name,$company=0) {
        $provider=$company?get_post_meta($company,'_wgpp_email_provider',true):'';
        if(!$provider && $company && get_post_meta($company,'_wgpp_cc_tokens_v1',true)){$provider='constant_contact';}
        $html='<p><label>Email provider <select name="'.esc_attr($name).'" required><option value="">Choose your email provider</option>';
        foreach(['mailchimp'=>'Mailchimp','constant_contact'=>'Constant Contact','none'=>'No email reporting'] as $value=>$label){$html.='<option value="'.esc_attr($value).'"'.($provider===$value?' selected':'').'>'.esc_html($label).'</option>';}
        return $html.'</select></label></p>';
    }
    public static function plan() {
        return ['code'=>'pro','monthly_usd'=>99,'annual_usd'=>990,'seats'=>5,'brands'=>1,'ga4_properties'=>1,'email_accounts'=>1,'reviews_monthly'=>4,'curriculum'=>true];
    }
    /** Membership lives on each company; ACTIVE is only the last selected workspace. */
    public static function for_user($id,$user) {
        $id=(int)$id;$user=(int)$user;
        $post=$id ? get_post($id) : null;
        if (!$user || !$post || $post->post_type!==self::TYPE || $post->post_status!=='private') { return null; }
        $members=get_post_meta($id,'_wgpp_members',true);
        $role=is_array($members) ? ($members[$user] ?? null) : null;
        if (!in_array($role,['owner','analyst','viewer'],true) || !apply_filters('wgpp_company_entitled',true,$id,$post->post_author)) { return null; }
        return ['id'=>$id,'name'=>$post->post_title,'role'=>$role,'plan'=>self::plan()];
    }
    public static function companies($user) {
        if(!$user){return [];}
        $ids=get_posts(['post_type'=>self::TYPE,'post_status'=>'private','posts_per_page'=>-1,'fields'=>'ids','orderby'=>'title','order'=>'ASC','meta_query'=>[['key'=>'_wgpp_members','value'=>'i:'.(int)$user.';s:','compare'=>'LIKE']]]);
        $ids[]=(int)get_user_meta($user,self::ACTIVE,true);$out=[];
        foreach(array_unique($ids) as $id){$c=self::for_user($id,$user);if($c){$out[$c['id']]=$c;}}
        uasort($out,static function($a,$b){return strcasecmp($a['name'],$b['name']);});return $out;
    }
    public static function context() {
        $user=get_current_user_id();
        // A rendered page pins all its forms and requests to its own company, even in another tab.
        $requested=$_POST['workspace'] ?? $_GET['workspace'] ?? null;
        if($requested!==null){return is_string($requested) && ctype_digit($requested)?self::for_user((int)$requested,$user):null;}
        $c=self::for_user((int)get_user_meta($user,self::ACTIVE,true),$user);
        if($c){return $c;}
        $companies=self::companies($user);return $companies?reset($companies):null;
    }
    public static function guard_request() {
        $action=$_POST['action'] ?? $_GET['action'] ?? '';
        if(!is_string($action) || !str_starts_with($action,'wgpp_') || !get_current_user_id()){return;}
        // These operations have their own administrator, invitation or OAuth-state checks.
        if(in_array($action,['wgpp_company','wgpp_switch_company','wgpp_team_signup','wgpp_reset_review_allowance','wgpp_sync_curriculum'],true) || str_ends_with($action,'_callback') || str_ends_with($action,'_settings') || ($action==='wgpp_team' && ($_POST['team_op']??'')==='accept')){return;}
        if(!isset($_POST['workspace']) && !isset($_GET['workspace']) && count(self::companies(get_current_user_id()))>1){
            if(wp_doing_ajax()){wp_send_json_error(['message'=>'This page was opened before company switching was enabled. Reload the company dashboard and try again.'],409);}
            wp_die('Reload the company dashboard before saving. No company settings were changed.','Company selection',['response'=>409]);
        }
        if(!self::context()){if(wp_doing_ajax()){wp_send_json_error(['message'=>'Company access required.'],403);}wp_die('Company access required.','',['response'=>403]);}
    }
    public static function keep_company($url) {
        $parts=wp_parse_url($url);$home=wp_parse_url(home_url('/'));
        if(!$parts || isset($parts['query']) && str_contains($parts['query'],'workspace=') || isset($parts['host']) && strcasecmp($parts['host'],$home['host'])!==0){return $url;}
        $path=$parts['path']??'';
        if(!in_array(rtrim($path,'/'),[rtrim($home['path']??'','/').'/pro-analytics',rtrim($home['path']??'','/').'/pro-workspace'],true)){return $url;}
        $c=self::context();return $c?add_query_arg('workspace',$c['id'],$url):$url;
    }
    public static function asset_version($src,$handle){
        if(!str_starts_with($handle,'wgpp')){return $src;}
        $version='0.6.28-preview';$path=parse_url($src,PHP_URL_PATH);
        if(is_string($path)&&preg_match('~/(assets/[a-zA-Z0-9._-]+\.(?:js|css)|portal\.css)$~D',$path,$match)){
            $file=__DIR__.'/'.$match[1];if(is_file($file)){$version=substr(hash_file('sha256',$file),0,16);}
        }
        return add_query_arg('ver',$version,$src);
    }
    public static function connection_url($service='') {
        $c=self::context();$url=add_query_arg('workspace',(int)($c['id']??0),home_url('/pro-analytics/'));
        if(in_array($service,['google','email','facebook','instagram'],true)){$url=add_query_arg('connection',$service,$url);}
        return $url.'#account-connections';
    }
    public static function switch_company() {
        check_admin_referer('wgpp_switch_company');
        $raw=$_POST['company_id']??'';$c=is_string($raw)&&ctype_digit($raw)?self::for_user((int)$raw,get_current_user_id()):null;
        if(!$c){wp_die('You do not have access to that company.','',['response'=>403]);}
        update_user_meta(get_current_user_id(),self::ACTIVE,$c['id']);
        wp_safe_redirect(add_query_arg('workspace',$c['id'],home_url('/pro-analytics/')).'#report');exit;
    }
    public static function compact_selector($companies,$c) {
        ob_start(); ?>
        <style>
        .wgpa .wgpp-workspace-nav{display:flex;align-items:center;justify-content:space-between;gap:10px;border-bottom:1px solid #cbdbe8}.wgpa .wgpp-workspace-nav .wgpa-primary-tabs{border:0;margin:0!important;padding:0;flex:1}.wgpa .wgpp-company-switch{position:relative;flex:0 1 auto;padding:0;background:none;max-width:280px;overflow-wrap:normal}.wgpa .wgpp-company-switch>summary{background:#eef7fb;color:#074b78!important;border:1px solid #b7cfdf;padding:8px 12px;min-height:42px;font-size:13px;margin:0;gap:14px}.wgpa .wgpp-company-switch>summary span{max-width:220px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}.wgpa .wgpp-company-switch>summary::after{color:#074b78!important;content:'';width:7px;height:7px;border-right:2px solid currentColor;border-bottom:2px solid currentColor;transform:rotate(45deg);font-size:inherit;line-height:1}.wgpa .wgpp-company-switch[open]>summary::after{transform:rotate(225deg)}.wgpa .wgpp-company-menu{position:absolute;right:0;top:calc(100% + 5px);width:350px;max-width:calc(100vw - 40px);padding:16px;background:#fff;border:1px solid #b7cfdf;border-radius:7px;box-shadow:0 8px 22px #074b7826;z-index:110}.wgpa .wgpp-company-menu form{margin:0;display:grid;gap:10px}.wgpa .wgpp-company-menu select{width:100%}.wgpa .wgpp-company-menu p{font-size:12px;margin:10px 0 0}@media(max-width:650px){.wgpa .wgpp-workspace-nav{flex-wrap:wrap}.wgpa .wgpp-company-switch{margin:0 0 5px auto}.wgpa .wgpp-company-switch>summary{min-height:36px;padding:6px 10px}.wgpa .wgpp-workspace-nav .wgpa-primary-tabs{min-width:100%}}@media print{.wgpa .wgpp-company-switch{display:none!important}}
        </style>
        <details class="wgpp-company-switch"><summary title="Switch company"><span><?php echo esc_html(($c['name']??'Choose company').' · '.ucfirst($c['role']??'')); ?></span></summary><div class="wgpp-company-menu">
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('wgpp_switch_company'); ?><input type="hidden" name="action" value="wgpp_switch_company"><input type="hidden" name="destination" value="report"><label for="wgpp-company-select">Switch company</label><select id="wgpp-company-select" name="company_id">
        <?php foreach($companies as $company){echo '<option value="'.esc_attr($company['id']).'"'.(($c['id']??0)===$company['id']?' selected':'').'>'.esc_html($company['name'].' · '.ucfirst($company['role'])).'</option>';} ?>
        </select><button type="submit">Open company</button></form>
        <?php if($c && self::complimentary($c['id'])){echo '<p>Complimentary Pro account · Four reviews per month</p>';}if(current_user_can('manage_options')){echo '<p><a href="'.esc_url(home_url('/pro-workspace/')).'">Add or set up a company →</a></p>';} ?>
        </div></details>
        <?php return ob_get_clean();
    }
    public static function selector($destination='report') {
        $companies=self::companies(get_current_user_id());$c=self::context();
        if(!$companies){return '';}
        if($destination==='report'){return self::compact_selector($companies,$c);}
        ob_start();echo '<section class="wgpp-company-picker" aria-label="Company workspace" style="background:#eef7fb;border:1px solid #c7dfe9;border-radius:8px;padding:16px;margin:0 0 20px;color:#074b78"><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:0">';wp_nonce_field('wgpp_switch_company');
        echo '<input type="hidden" name="action" value="wgpp_switch_company"><input type="hidden" name="destination" value="'.esc_attr($destination).'"><label for="wgpp-company-select"><strong>Company</strong></label><select id="wgpp-company-select" name="company_id" style="min-width:240px;max-width:100%;color:#074b78;background:white">';
        foreach($companies as $company){echo '<option value="'.esc_attr($company['id']).'"'.(($c['id']??0)===$company['id']?' selected':'').'>'.esc_html($company['name'].' · '.ucfirst($company['role'])).'</option>';}
        echo '</select><button type="submit">Open company</button></form>';
        if($c && self::complimentary($c['id'])){echo '<p style="font-size:13px;margin:8px 0 0">Complimentary Pro account · No subscription charge · Four reviews per month</p>';}
        if(current_user_can('manage_options')){
            echo '<details style="margin-top:12px"><summary>Add a complimentary company</summary><p>Creates a separate Pro account with you as Owner. Existing companies keep their connections, team and review allowance.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('wgpp_company');echo '<input type="hidden" name="action" value="wgpp_company"><input type="hidden" name="complimentary" value="1"><label>Company name <input name="company_name" maxlength="200" required></label>'.self::email_select('email_provider').'<button>Create complimentary company</button></form></details>';
        }
        echo '</section>';return ob_get_clean();
    }
    public static function decorate($html,$destination='report') {
        $c=self::context();if(!$c){return $html;}
        // Pin every form, including provider setup and dates, before it reaches the browser.
        $html=preg_replace('/(<form\b[^>]*>)/i','$1<input type="hidden" name="workspace" value="'.(int)$c['id'].'">',$html);
        $html=str_replace('<p role="status">','<p role="status" data-account-notice style="padding:12px 16px;border-left:4px solid #0879ad;background:#eaf5fb;color:#074b78;font-weight:600">',$html);
        $html=preg_replace_callback('~href="([^"#]*(?:/pro-analytics/|/pro-workspace/))((?:#[^"]*)?)"~',static function($m)use($c){return 'href="'.esc_url(add_query_arg('workspace',$c['id'],html_entity_decode($m[1]))).$m[2].'"';},$html);
        $picker=self::selector($destination);
        if($destination==='report' && str_contains($html,'data-primary-tabs')){return preg_replace_callback('~(<nav\b[^>]*\bdata-primary-tabs[^>]*>.*?</nav>)~s',static function($m)use($picker){return '<div class="wgpp-workspace-nav">'.$m[1].$picker.'</div>';},$html,1);}
        return $picker.$html;
    }
    public static function can($action) {
        $c=self::context();
        if (!$c) { return false; }
        if ($action==='view') { return true; }
        if ($action==='review') { return in_array($c['role'],['owner','analyst'],true); }
        return $c['role']==='owner';
    }
    public static function create($name,$user,$complimentary=false) {
        $name=trim($name);
        if(!$user || !$name || strlen($name)>200 || !get_userdata($user)){throw new RuntimeException('Enter a company name and a valid owner.');}
        if($complimentary && !current_user_can('manage_options')){throw new RuntimeException('Only a site administrator can grant complimentary Pro access.');}
        $paid=WGPP_WishList::payer_active($user);
        if(!$complimentary && !$paid && !current_user_can('manage_options') && !current_user_can('westgate_practicum_access')){throw new RuntimeException('An active Pro membership is required.');}
        return WGPP_Native_Reviews::locked('new-company:'.$user,static function()use($name,$user,$complimentary,$paid){
            // A subscription covers one company. Additional free accounts are an explicit administrator grant.
            if(!$complimentary && self::companies($user)){throw new RuntimeException('You already have a company. An administrator must provision a separate company account.');}
            foreach(self::companies($user) as $existing){if(strcasecmp(trim($existing['name']),$name)===0){throw new RuntimeException('You already have a company with that name. Select it above.');}}
            $id=wp_insert_post(['post_type'=>self::TYPE,'post_status'=>'private','post_title'=>$name,'post_author'=>$user],true);
            if(is_wp_error($id)){throw new RuntimeException('Could not create company.');}
            if($complimentary){update_post_meta($id,'_wgpp_complimentary',['granted_by'=>get_current_user_id(),'granted_at'=>gmdate('c'),'reason'=>'Complimentary Pro customer account']);}
            elseif($paid){update_post_meta($id,'_wgpp_billing_owner',$user);}
            update_post_meta($id,'_wgpp_members',[$user=>'owner']);
            update_user_meta($user,self::ACTIVE,$id);return $id;
        });
    }
    public static function save() {
        if(!is_user_logged_in()){wp_die('Access denied.','',['response'=>403]);}check_admin_referer('wgpp_company');
        try{$name=$_POST['company_name']??'';if(!is_string($name)){throw new RuntimeException('Enter a company name.');}
            $provider=$_POST['email_provider']??'';if(!is_string($provider)||!in_array($provider,['','mailchimp','constant_contact','none'],true)){throw new RuntimeException('Choose your email provider.');}
            $id=self::create(sanitize_text_field(wp_unslash($name)),get_current_user_id(),($_POST['complimentary']??'')==='1');
            if($provider!==''){update_post_meta($id,'_wgpp_email_provider',$provider);}
            wp_safe_redirect(add_query_arg('workspace',$id,WGPP_Landing::url()));exit;
        }catch(RuntimeException $e){wp_die(esc_html($e->getMessage()));}
    }
    public static function brand($company) { $v=get_post_meta($company,'_wgpp_brand_v1',true);return is_array($v)?$v:[]; }
    public static function url($url) {
        if (!is_string($url)) { throw new RuntimeException('Enter a valid website address.'); }
        $url=trim(wp_unslash($url));if (!$url) { return ''; }
        $parts=wp_parse_url($url);
        if (!$parts || !in_array($parts['scheme'] ?? '',['https','http'],true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || strlen($url)>2000) { throw new RuntimeException('Use a complete http or https website or profile address.'); }
        return esc_url_raw($url);
    }
    public static function service_area($input) {
        if(!is_array($input)){throw new RuntimeException('Invalid service area.');}
        $out=[];
        foreach(['coverage','name','primary','secondary'] as $key){
            $value=$input[$key] ?? '';if(!is_string($value) || strlen($value)>4000){throw new RuntimeException('Use text up to 4,000 characters for each service-area field.');}
            $out[$key]=sanitize_text_field(trim(wp_unslash($value)));
        }
        if(!in_array($out['coverage'],['','local','regional','national','international'],true)){throw new RuntimeException('Select market coverage.');}
        return $out;
    }
    public static function brand_save() {
        check_admin_referer('wgpp_brand');
        try {
            $c=self::context();if (!$c || $c['role']!=='owner') { throw new RuntimeException('Only the owner can update Brand settings.'); }
            $website=self::url($_POST['brand_website'] ?? '');$profiles=[];
            $provider=$_POST['brand_email_provider']??null;if($provider!==null && (!is_string($provider)||!in_array($provider,['mailchimp','constant_contact','none'],true))){throw new RuntimeException('Choose the company email provider.');}
            $area=isset($_POST['brand_service_area'])?self::service_area($_POST['brand_service_area']):null;
            foreach(['Facebook','Instagram','LinkedIn','YouTube','TikTok','Reddit','X'] as $network) { $profiles[$network]=self::url($_POST['brand_social'][$network] ?? ''); }
            $logo=0;
            if (!empty($_FILES['brand_logo']['name'])) {
                if (($_FILES['brand_logo']['size'] ?? 0)>2097152 || ($_FILES['brand_logo']['error'] ?? 1)!==UPLOAD_ERR_OK) { throw new RuntimeException('Upload a PNG, JPG or WebP logo up to 2 MB.'); }
                $size=@getimagesize($_FILES['brand_logo']['tmp_name']);
                if (!$size || $size[0]>4000 || $size[1]>4000 || !in_array($size[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP],true)) { throw new RuntimeException('Use a PNG, JPG or WebP image no larger than 4000 pixels on either side.'); }
                require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/image.php';require_once ABSPATH.'wp-admin/includes/media.php';
                $logo=media_handle_upload('brand_logo',0,[],['test_form'=>false,'mimes'=>['jpg|jpeg|jpe'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp']]);
                if (is_wp_error($logo)) { throw new RuntimeException('Logo upload failed. Please try again.'); }
            }
            WGPP_Native_Reviews::locked($c['id'],static function()use($c,$website,$profiles,$logo,$area,$provider){
                if (!self::can('connections')) { throw new RuntimeException('Owner access changed.'); }
                if($provider!==null){update_post_meta($c['id'],'_wgpp_email_provider',$provider);}
                $brand=self::brand($c['id']);$brand['website']=$website;$brand['social']=$profiles;if($area!==null){$brand['service_area']=$area;}
                if ($logo) { $brand['logo_id']=$logo; }
                elseif (!empty($_POST['remove_logo'])) { unset($brand['logo_id']); }
                if (!update_post_meta($c['id'],'_wgpp_brand_v1',$brand) && self::brand($c['id'])!==$brand) { throw new RuntimeException('Could not save Brand settings.'); }
            });$message='Brand settings saved.';
        } catch (RuntimeException $e) { $message=$e->getMessage(); }
        set_transient('wgpp_notice_'.get_current_user_id(),$message,120);wp_safe_redirect(WGPP_Team::portal().'#account-company');exit;
    }
    public static function brand_render($c) {
        $b=self::brand($c['id']);ob_start();echo '<details><summary>Brand settings · Website, logo and social profiles</summary><h3>Brand settings</h3><form method="post" enctype="multipart/form-data" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('wgpp_brand');echo '<input type="hidden" name="action" value="wgpp_brand"><p><label>Company website <input type="url" name="brand_website" placeholder="https://example.com" value="'.esc_attr($b['website'] ?? '').'" style="width:100%"></label></p>'.self::email_select('brand_email_provider',$c['id']).'<p><label>Brand logo <input type="file" name="brand_logo" accept="image/png,image/jpeg,image/webp"></label></p><p>PNG, JPG or WebP, up to 2 MB and 4000 pixels. A square logo works best inside the Brand circle.</p>';
        if (!empty($b['logo_id'])) { echo wp_get_attachment_image($b['logo_id'],'thumbnail').'<p><label><input type="checkbox" name="remove_logo" value="1"> Remove current logo</label></p>'; }
        foreach(['Facebook','Instagram','LinkedIn','YouTube','TikTok','Reddit','X'] as $network) { echo '<p><label>'.esc_html($network).' profile <input type="url" name="brand_social['.esc_attr($network).']" value="'.esc_attr($b['social'][$network] ?? '').'" style="width:100%"></label></p>'; }
        $area=$b['service_area'] ?? [];
        echo '<h4>Service area</h4><p><label>Market coverage <select name="brand_service_area[coverage]">';
        foreach([''=>'Not defined','local'=>'Local','regional'=>'Regional','national'=>'National','international'=>'International'] as $key=>$label){echo '<option value="'.esc_attr($key).'"'.(($area['coverage'] ?? '')===$key?' selected':'').'>'.esc_html($label).'</option>';}
        echo '</select></label></p>';
        foreach(['name'=>'Market name (for example, Central Texas)','primary'=>'Primary market cities or counties','secondary'=>'Secondary market cities or counties'] as $key=>$label){echo '<p><label>'.esc_html($label).' <input type="text" name="brand_service_area['.esc_attr($key).']" value="'.esc_attr($area[$key] ?? '').'" maxlength="4000" style="width:100%"></label></p>';}
        echo '<p>Include the state and country with each location. List only places where you serve customers. These settings record your intended service area; geographic report filtering is not active yet.</p>';
        echo '<p>Profile links identify your company channels. Saving a link does not authorize a social account or restrict the sources shown in analytics.</p><button>Save Brand settings</button></form></details>';return ob_get_clean();
    }
    public static function render() {
        $c=self::context();
        if ($c) { return '<section class="wgpa-import"><h2>'.esc_html($c['name']).'</h2><p>'.esc_html(ucfirst($c['role'])).' · Pro company workspace · Five people including the owner</p>'.(self::can('members')?WGPP_Team::render($c).self::brand_render($c):'').'</section>'; }
        if (!current_user_can('manage_options') && !current_user_can('westgate_practicum_access') && !WGPP_WishList::can_create()) { return '<p>Your account is not assigned to a Pro company. Ask your company owner for an invitation.</p>'; }
        ob_start();echo '<section class="wgpa-import"><h2>Create a Pro workspace</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('wgpp_company');echo '<input type="hidden" name="action" value="wgpp_company"><label>Company name <input name="company_name" maxlength="200" required></label>'.self::email_select('email_provider').'<button>Create workspace</button></form></section>';return ob_get_clean();
    }
}

/** Owner invitations reserve seats; recipients accept only under the invited email. */
final class WGPP_Team {
    const INVITES='_wgpp_team_invites_v1';
    public static function boot() { add_action('admin_post_wgpp_team',[__CLASS__,'handle']);add_action('admin_post_nopriv_wgpp_team_signup',[__CLASS__,'signup']); }
    public static function portal() { return home_url('/pro-analytics/'); }
    public static function pending($company) {
        $out=[];foreach((get_post_meta($company,self::INVITES,true) ?: []) as $email=>$invite) { if (($invite['expires'] ?? 0)>time()) { $out[$email]=$invite; } }return $out;
    }
    public static function role($role) { if (!in_array($role,['analyst','viewer'],true)) { throw new RuntimeException('Choose Analyst or Viewer.'); }return $role; }
    private static function owner() { $c=WGPP_Company::context();if (!$c || $c['role']!=='owner') { throw new RuntimeException('Only the company owner can manage Team access.'); }return $c; }
    public static function reserve($company,$email,$role,$hash,$note='',$inviter='') {
        self::role($role);$members=get_post_meta($company,'_wgpp_members',true) ?: [];$pending=self::pending($company);
        $user=get_user_by('email',$email);
        if ($user && isset($members[$user->ID])) { throw new RuntimeException('This person is already on your team. Use their role controls below.'); }
        if (!isset($pending[$email]) && count($members)+count($pending)>=5) { throw new RuntimeException('All five seats are occupied or reserved. Remove a member or revoke an invitation first.'); }
        if (isset($pending[$email]) && ($pending[$email]['sent_at'] ?? 0)>time()-60) { throw new RuntimeException('Wait one minute before resending this invitation.'); }
        $stored=get_post_meta($company,self::INVITES,true) ?: [];
        if(count($stored)>=50 && !isset($stored[$email])){uasort($stored,static function($a,$b){return $a['expires']<=>$b['expires'];});foreach($stored as $old_email=>$old){if($old['expires']<time()){unset($stored[$old_email]);break;}}}
        $pending[$email]=['hash'=>$hash,'role'=>$role,'expires'=>time()+7*DAY_IN_SECONDS,'sent_at'=>time(),'message'=>$note,'sender_name'=>$inviter];
        $stored[$email]=$pending[$email];
        if (!update_post_meta($company,self::INVITES,$stored)) { throw new RuntimeException('Could not reserve the invitation.'); }
        return $pending[$email];
    }
    public static function accept($company,$token,$user) {
        $email=strtolower($user->user_email);$pending=self::pending($company);$invite=$pending[$email] ?? null;
        $post=get_post($company);
        if (!$post || $post->post_type!==WGPP_Company::TYPE || $post->post_status!=='private' || !$invite || !hash_equals($invite['hash'],hash('sha256',$token)) || !apply_filters('wgpp_company_entitled',true,$company,$post->post_author)) { throw new RuntimeException('Invitation expired, revoked, or addressed to a different email.'); }
        $members=get_post_meta($company,'_wgpp_members',true) ?: [];
        if (!isset($members[$user->ID]) && count($members)>=5) { throw new RuntimeException('The company has no available seat.'); }
        if (($members[$user->ID] ?? '')!=='owner') { $members[$user->ID]=self::role($invite['role']); }
        update_user_meta($user->ID,WGPP_Company::ACTIVE,$company);
        if ((int)get_user_meta($user->ID,WGPP_Company::ACTIVE,true)!==$company) { throw new RuntimeException('Could not save company assignment.'); }
        if (!update_post_meta($company,'_wgpp_members',$members) && get_post_meta($company,'_wgpp_members',true)!==$members) { throw new RuntimeException('Could not save membership.'); }
        $stored=get_post_meta($company,self::INVITES,true) ?: [];unset($stored[$email]);update_post_meta($company,self::INVITES,$stored);
    }
    public static function handle() {
        check_admin_referer('wgpp_team');
        try {
            if (!is_user_logged_in()) { throw new RuntimeException('Sign in first.'); }
            $op=is_string($_POST['team_op'] ?? null)?$_POST['team_op']:'';
            if ($op==='accept') {
                $company=absint($_POST['company'] ?? 0);$token=$_POST['invite'] ?? '';
                if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D',$token)) { throw new RuntimeException('Invalid invitation.'); }
                $user=wp_get_current_user();
                WGPP_Native_Reviews::locked('team-email:'.hash('sha256',strtolower($user->user_email)),static function()use($company,$token,$user){WGPP_Native_Reviews::locked($company,static function()use($company,$token,$user){self::accept($company,$token,$user);});});
                $message='You have joined the company workspace.';
            } else {
                $c=self::owner();$company=$c['id'];
                if ($op==='invite') {
                    $email=strtolower(sanitize_email(wp_unslash($_POST['team_email'] ?? '')));$role=self::role($_POST['team_role'] ?? '');
                    if (!is_email($email)) { throw new RuntimeException('Enter a valid email address.'); }
                    $note=$_POST['invite_message'] ?? '';if(!is_string($note) || strlen($note)>1500){throw new RuntimeException('Keep the invitation message under 1,500 characters.');}$note=sanitize_textarea_field(wp_unslash($note));
                    $sender=wp_get_current_user();$inviter=$sender->display_name;
                    $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
                    WGPP_Native_Reviews::locked('team-email:'.hash('sha256',$email),static function()use($company,$email,$role,$hash,$note,$inviter){WGPP_Native_Reviews::locked($company,static function()use($company,$email,$role,$hash,$note,$inviter){self::owner();self::reserve($company,$email,$role,$hash,$note,$inviter);});});
                    $url=add_query_arg(['company'=>$company,'invite'=>$token],self::portal());
                    $sender=wp_get_current_user();
                    $body=self::invitation_body($sender->display_name,$c['name'],$email,$role,$note,$url);
                    if (!WGPP_Account::mail($email,$sender->display_name.' invited you to '.$c['name'].' · Social Media Magnet Pro',$body,true)) {
                        WGPP_Native_Reviews::locked($company,static function()use($company,$email,$hash){$p=get_post_meta($company,self::INVITES,true) ?: [];if (($p[$email]['hash'] ?? '')===$hash) { unset($p[$email]);update_post_meta($company,self::INVITES,$p); }});
                        throw new RuntimeException('Email could not be sent. The seat reservation was released.');
                    }
                    $message='Invitation submitted for email delivery. It expires in seven days.';
                } else {
                    WGPP_Native_Reviews::locked($company,static function()use($company,$op){
                        self::owner();$members=get_post_meta($company,'_wgpp_members',true) ?: [];
                        if ($op==='revoke') { $email=strtolower(sanitize_email(wp_unslash($_POST['team_email'] ?? '')));$p=get_post_meta($company,self::INVITES,true) ?: [];unset($p[$email]);update_post_meta($company,self::INVITES,$p);return; }
                        $uid=absint($_POST['member'] ?? 0);
                        if (!isset($members[$uid]) || $members[$uid]==='owner') { throw new RuntimeException('The owner cannot be removed or changed here.'); }
                        if ($op==='remove') { unset($members[$uid]); }
                        elseif (in_array($op,['role','edit'],true)) { $members[$uid]=self::role($_POST['team_role'] ?? '');if($op==='edit'){$name=$_POST['member_name'] ?? '';if(!is_string($name) || !trim($name) || strlen($name)>100){throw new RuntimeException('Enter a member name up to 100 characters.');}if(is_wp_error(wp_update_user(['ID'=>$uid,'display_name'=>sanitize_text_field(wp_unslash($name))]))){throw new RuntimeException('Could not save member name.');}} }
                        else { throw new RuntimeException('Invalid Team action.'); }
                        if (!update_post_meta($company,'_wgpp_members',$members) && get_post_meta($company,'_wgpp_members',true)!==$members) { throw new RuntimeException('Could not save team change.'); }
                        if ($op==='remove' && (int)get_user_meta($uid,WGPP_Company::ACTIVE,true)===$company) { delete_user_meta($uid,WGPP_Company::ACTIVE); }
                    });$message='Team access updated.';
                }
            }
        } catch (RuntimeException $e) { $message=$e->getMessage(); }
        set_transient('wgpp_notice_'.get_current_user_id(),$message,120);wp_safe_redirect(self::portal().($op==='accept'?'':'#account-users'));exit;
    }
    public static function invitation_body($sender,$company,$email,$role,$note,$url){return "Hello,\n\n".$sender.' has invited you to join '.$company." on Social Media Magnet Pro.\n\n".($note!==''?"A message from ".$sender.":\n".$note."\n\n":'').'Your access: '.ucfirst($role).". You can view your company's website, email and social-source performance reports.".($role==='analyst'?' You may also request performance reviews when enabled.':'')."\n\nAccept your invitation within seven days:\n".$url."\n\nThis invitation is specifically for ".$email.". Use that email to sign in or create your analytics-only login. It does not enroll you in the course or change practicum access.\n\nSocial Media Magnet Pro\nIf you did not expect this invitation, ignore this message.";}
    private static function form($op) {
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('wgpp_team');echo '<input type="hidden" name="action" value="wgpp_team"><input type="hidden" name="team_op" value="'.esc_attr($op).'">';
    }
    public static function invited_email($company,$token) {
        if(!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D',$token)){return null;}
        foreach(self::pending($company) as $email=>$invite){if(hash_equals($invite['hash'],hash('sha256',$token))){return $email;}}return null;
    }
    public static function invitation() {
        $token=$_GET['invite'] ?? ''; $company=absint($_GET['company'] ?? 0);
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D',$token) || !$company) { return ''; }
        WGPP_Account::assets();$invited=self::invited_email($company,$token);
        $entry=$invited?(self::pending($company)[$invited] ?? []):[];$post=get_post($company);
        $intro=$invited?'<p>'.esc_html(($entry['sender_name'] ?? '')?:'Your company owner').' invited you to join <strong>'.esc_html($post->post_title ?? 'your company').'</strong> as '.esc_html(ucfirst($entry['role'] ?? 'viewer')).'.</p>'.(!empty($entry['message'])?'<p><strong>Invitation message</strong><br>'.nl2br(esc_html($entry['message'])).'</p>':''):'';
        if (!is_user_logged_in()) {
            if (!$invited) { return '<p>This invitation has expired or was revoked. Request a new invitation from the company owner.</p>'; }
            ob_start();echo '<section class="wgpa-import"><h2>Join your company analytics team</h2>'.$intro.'<p>Already have an account? Reuse your existing login.</p><p><a href="'.esc_url(wp_login_url(add_query_arg(['company'=>$company,'invite'=>$token],self::portal()))).'">Sign in to accept</a></p><h3>Create an analytics-only login</h3><p>This creates a personal login for company reporting. It does not enroll you in the course or change practicum access.</p><form class="wgpp-invite-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('wgpp_team_signup');echo '<input type="hidden" name="action" value="wgpp_team_signup"><input type="hidden" name="company" value="'.esc_attr($company).'"><input type="hidden" name="invite" value="'.esc_attr($token).'"><p><label>Your name <input name="display_name" autocomplete="name" maxlength="100" required></label></p><p><label>Invited email <input type="email" name="email" value="'.esc_attr($invited).'" autocomplete="email" readonly aria-readonly="true"></label></p><div data-password-field><label>Create password <input type="password" name="password" autocomplete="new-password" minlength="12" maxlength="128" required data-password-input></label><button type="button" data-password-toggle aria-pressed="false">Show password</button></div><p>Use at least 12 characters. Existing account holders should sign in above.</p><button>Create login and accept invitation</button></form></section>';return ob_get_clean();
        }
        ob_start();echo '<section class="wgpa-import"><h2>Company invitation</h2>'.$intro.'<p>Invited email: <strong>'.esc_html($invited ?? 'Invitation unavailable').'</strong>. Accept using that account. Course and practicum access are separate.</p>';self::form('accept');echo '<input type="hidden" name="company" value="'.esc_attr($company).'"><input type="hidden" name="invite" value="'.esc_attr($token).'"><button>Accept company invitation</button></form></section>';return ob_get_clean();
    }
    public static function signup() {
        nocache_headers();header('Referrer-Policy: no-referrer');check_admin_referer('wgpp_team_signup');
        try {
            if (is_user_logged_in()) { throw new RuntimeException('You are already signed in. Use Accept company invitation.'); }
            $company=absint($_POST['company'] ?? 0);$token=$_POST['invite'] ?? '';
            $email=self::invited_email($company,$token);$posted_email=$_POST['email'] ?? '';if(!is_string($posted_email) || ($email && strtolower($posted_email)!==$email)){throw new RuntimeException('This invitation is locked to its original email address.');}$name=sanitize_text_field(wp_unslash($_POST['display_name'] ?? ''));$password=$_POST['password'] ?? '';
            if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D',$token) || !$email || !is_email($email) || !$name || !is_string($password) || strlen(wp_unslash($password))<12 || strlen(wp_unslash($password))>128) { throw new RuntimeException('Enter your name and a password of 12–128 characters. The invited email cannot be changed.'); }
            $uid=WGPP_Native_Reviews::locked('team-email:'.hash('sha256',$email),static function()use($company,$email,$name,$password,$token){return WGPP_Native_Reviews::locked($company,static function()use($company,$email,$name,$password,$token){
                $p=self::pending($company);$i=$p[$email] ?? null;$post=get_post($company);
                if (!$post || $post->post_type!==WGPP_Company::TYPE || $post->post_status!=='private' || !$i || !hash_equals($i['hash'],hash('sha256',$token)) || !apply_filters('wgpp_company_entitled',true,$company,$post->post_author)) { throw new RuntimeException('Invitation expired, revoked, or addressed to another email.'); }
                if (get_user_by('email',$email)) { throw new RuntimeException('An account already uses that email. Sign in to accept this invitation.'); }
                $members=get_post_meta($company,'_wgpp_members',true) ?: [];if (count($members)>=5) { throw new RuntimeException('No company seat is available.'); }
                // Do not call WishList registration or assign any course levels.
                $uid=wp_insert_user(['user_login'=>'smm_'.bin2hex(random_bytes(10)),'user_email'=>$email,'display_name'=>$name,'user_pass'=>wp_unslash($password),'role'=>'subscriber','meta_input'=>['_wgpp_analytics_only'=>1]]);
                if (is_wp_error($uid)) { throw new RuntimeException('Could not create the account. Try again or contact Westgate.'); }
                self::accept($company,$token,get_userdata($uid));return $uid;
            });});
            wp_set_current_user($uid);wp_set_auth_cookie($uid,false,is_ssl());wp_safe_redirect(self::portal());exit;
        } catch (RuntimeException $e) { wp_die(esc_html($e->getMessage()).' Return to the invitation link to try again.','Invitation',['response'=>400]); }
    }
    public static function render($c) {
        $members=get_post_meta($c['id'],'_wgpp_members',true) ?: [];$pending=self::pending($c['id']);
        ob_start();echo '<details id="team"><summary>Team · '.count($members).' active · '.count($pending).' invited · 5 seats</summary><h3>Invite and manage your team</h3><p>Owners manage connections and team access. Analysts may request performance reviews. Viewers can read reports. Invitations expire after seven days and reserve a seat. Invited users can create an analytics-only login. Existing course members reuse their login. No WishList course levels are added.</p>';
        self::form('invite');echo '<label>Email <input type="email" name="team_email" required></label><label>Role <select name="team_role"><option value="viewer">Viewer</option><option value="analyst">Analyst</option></select></label><label>Personal message to your invitee (optional)<textarea name="invite_message" rows="3" maxlength="1500" placeholder="Explain why you are inviting them and what you would like them to review."></textarea></label><button>Send invitation</button></form><p class="wgpa-small-note">Invitation sender: <strong>Social Media Magnet Pro</strong> &lt;'.esc_html(WGPP_Account::INVITATION_FROM).'&gt;</p><h4>Members</h4>';
        foreach($members as $uid=>$role) { $u=get_userdata($uid);echo '<p><strong>'.esc_html($u?$u->display_name:'Unavailable account').'</strong> · '.esc_html($role).($u?' · '.esc_html($u->user_email):'').'</p>';if ($role==='owner') { continue; }
            self::form('edit');echo '<input type="hidden" name="member" value="'.esc_attr($uid).'"><label>Name <input name="member_name" value="'.esc_attr($u?$u->display_name:'').'" maxlength="100" required></label><label>Role <select name="team_role"><option value="viewer"'.($role==='viewer'?' selected':'').'>Viewer</option><option value="analyst"'.($role==='analyst'?' selected':'').'>Analyst</option></select></label><button>Save member</button></form>';
            self::form('remove');echo '<input type="hidden" name="member" value="'.esc_attr($uid).'"><button>Remove from team</button></form>';
        }
        $invitations=get_post_meta($c['id'],self::INVITES,true) ?: [];
        echo '<h4>Invitations</h4>';if (!$invitations) { echo '<p>No invitations.</p>'; }
        foreach($invitations as $email=>$i) {
            echo '<p>'.esc_html($email).' · '.esc_html($i['role']).' · '.($i['expires']<time()?'Expired':'Expires '.esc_html(gmdate('m/d/y',$i['expires']))).'</p>';
            self::form('invite');echo '<input type="hidden" name="team_email" value="'.esc_attr($email).'"><input type="hidden" name="team_role" value="'.esc_attr($i['role']).'"><label>Invitation message<textarea name="invite_message" rows="2" maxlength="1500">'.esc_textarea($i['message'] ?? '').'</textarea></label><button>Resend invitation</button></form>';
            self::form('revoke');echo '<input type="hidden" name="team_email" value="'.esc_attr($email).'"><button>Revoke invitation</button></form>';
        }
        echo '<p>Resending replaces the old invitation link. Removing a member only revokes this company’s analytics access; it does not delete their login or course access. Member names are shared with their site login.</p></details>';return ob_get_clean();
    }
}

/** Company subscription is attached to the original payer, never to invited seats. */
final class WGPP_WishList {
    const LEVELS=['1792352584','1792353068'];
    private static $member_cache=[];
    public static function boot(){add_filter('wgpp_company_entitled',[__CLASS__,'entitled'],20,2);add_filter('wgpp_review_billing_cycle',[__CLASS__,'billing_cycle'],20,2);add_action('admin_post_wgpp_sync_curriculum',[__CLASS__,'sync_curriculum']);}
    public static function sync_curriculum(){
        if(!current_user_can('manage_options')){wp_die('Administrator access required.','',['response'=>403]);}check_admin_referer('wgpp_sync_curriculum');
        if(!function_exists('wlmapi_get_level_pages')||!function_exists('wlmapi_add_page_to_level')){wp_die('WishList API is unavailable.');}
        $ids=[];foreach(['1571692156','1531756603','1531866216','1531866359','1532527828','1532527875','1532527893','1532527906','1532527933'] as $level){$r=wlmapi_get_level_pages($level);if(empty($r['success'])||!is_array($r['pages']['page'] ?? null)){wp_die('Could not read the course access collection. No complete synchronization was confirmed.');}foreach($r['pages']['page'] as $page){$id=(int)($page['ID'] ?? 0);if($id && get_post_type($id)==='page'){$ids[$id]=$id;}}}
        if(!$ids){wp_die('No curriculum pages found.');}foreach(self::LEVELS as $level){$r=wlmapi_add_page_to_level($level,['ContentIds'=>array_values($ids)]);if(empty($r['success'])){wp_die('Curriculum synchronization did not complete. Retry to finish; existing access is preserved.');}}
        update_option('_wgpp_pro_curriculum_sync',['count'=>count($ids),'at'=>gmdate('c')],false);wp_safe_redirect(admin_url('admin.php?page=wgpp-pro-review&curriculum=1'));exit;
    }
    public static function membership($user){$user=(int)$user;if(array_key_exists($user,self::$member_cache)){return self::$member_cache[$user];}self::$member_cache[$user]=null;if(!$user||!function_exists('wlmapi_get_member')){return null;}try{$r=wlmapi_get_member((int)$user);if(!self::active($r)){return null;}foreach($r['member'] as $member){foreach(($member['Levels'] ?? []) as $id=>$v){$v=(array)$v;if(in_array((string)($v['Level_ID'] ?? $id),self::LEVELS,true)&&in_array($v['Active'] ?? null,[1,'1',true],true)&&empty($v['Cancelled'])&&empty($v['Pending'])&&empty($v['UnConfirmed'])&&empty($v['Expired'])){return self::$member_cache[$user]=$v;}}}}catch(Throwable $e){}return null;}
    public static function window($stamp,$now){
        if(!is_numeric($stamp)||$stamp<=0||$stamp>$now){return null;}$tz=new DateTimeZone('America/Chicago');$anchor=(new DateTimeImmutable('@'.(int)$stamp))->setTimezone($tz);$today=(new DateTimeImmutable('@'.$now))->setTimezone($tz);$day=(int)$anchor->format('j');
        $at=static function($month)use($anchor,$day){return $month->setDate((int)$month->format('Y'),(int)$month->format('m'),min($day,(int)$month->format('t')))->setTime((int)$anchor->format('H'),(int)$anchor->format('i'),(int)$anchor->format('s'));};
        $month=$today->modify('first day of this month')->setTime(0,0);$start=$at($month);if($start>$today){$month=$month->modify('-1 month');$start=$at($month);}$end=$at($month->modify('+1 month'));return ['start'=>$start->format('c'),'end'=>$end->format('c'),'basis'=>'Company subscription month · four Pro Analytics Reviews, including on annual plans.'];
    }
    public static function billing_cycle($prior,$company){if(WGPP_Company::complimentary($company)){$grant=get_post_meta($company,'_wgpp_complimentary',true);$cycle=self::window(strtotime($grant['granted_at']??''),time());if($cycle){$cycle['basis']='Complimentary Pro account · Four Pro Analytics Reviews per company month.';}return $cycle;}$payer=(int)get_post_meta($company,'_wgpp_billing_owner',true);if(!$payer){return $prior;}$m=self::membership($payer);return $m?self::window($m['Timestamp'] ?? null,time()):false;}

    public static function active($response){
        if(!is_array($response)||empty($response['success'])){return false;}
        foreach(($response['member'] ?? []) as $member){foreach(($member['Levels'] ?? []) as $id=>$level){$level=(array)$level;if(!in_array((string)($level['Level_ID'] ?? $id),self::LEVELS,true)){continue;}
            if(in_array($level['Active'] ?? null,[1,'1',true],true) && empty($level['Cancelled']) && empty($level['Pending']) && empty($level['UnConfirmed']) && empty($level['Expired'])){return true;}
        }}return false;
    }
    public static function payer_active($user){return self::membership($user)!==null;}
    public static function can_create(){return self::payer_active(get_current_user_id());}
    public static function entitled($allowed,$company){if(WGPP_Company::complimentary($company)){return $allowed;}$payer=(int)get_post_meta($company,'_wgpp_billing_owner',true);return $allowed && (!$payer || self::payer_active($payer));}
}

/** Company-private PDF foundations. Bytes never enter the public Media Library. */
final class WGPP_Documents {
    const META='_wgpp_foundation_documents_v1';
    const MAX_BYTES=20971520;
    const TYPES=['strategy_canvas'=>'Strategy Canvas','brand_dyno'=>'Brand Dyno'];
    public static function boot(){add_action('admin_post_wgpp_foundation_document',[__CLASS__,'save']);add_action('admin_post_wgpp_foundation_download',[__CLASS__,'download']);}
    public static function access($company,$write=false){$c=WGPP_Company::context();if(!$c || (int)$c['id']!==(int)$company || !WGPP_Company::can($write?'review':'view')){throw new RuntimeException($write?'Owner or Analyst access required.':'Company access required.');}return $c;}
    public static function records($company){self::access($company);$r=get_post_meta($company,self::META,true);return is_array($r)?$r:[];}
    public static function validate_pdf($name,$bytes){if(!is_string($name)||strtolower(pathinfo($name,PATHINFO_EXTENSION))!=='pdf'||!is_string($bytes)||strlen($bytes)<20||strlen($bytes)>self::MAX_BYTES||!str_starts_with($bytes,'%PDF-')||!str_contains(substr($bytes,-2048),'%%EOF')||preg_match('~/Encrypt\b~',$bytes)){throw new InvalidArgumentException('Choose an unencrypted PDF report up to 20 MB.');}}
    public static function store($company,$kind,$name,$bytes){
        self::access($company,true);if(!isset(self::TYPES[$kind])){throw new InvalidArgumentException('Choose Strategy Canvas or Brand Dyno.');}self::validate_pdf($name,$bytes);
        $metadata=['kind'=>$kind,'title'=>self::TYPES[$kind],'filename'=>sanitize_file_name($name),'bytes'=>strlen($bytes),'sha256'=>hash('sha256',$bytes),'uploaded_at'=>gmdate('c'),'uploaded_by'=>get_current_user_id()];
        $encrypted=WGPP_Google::encrypt(['base64'=>base64_encode($bytes)],'foundation:'.$company.':'.$kind);
        WGPP_Native_Reviews::locked($company,static function()use($company,$kind,$metadata,$encrypted){
            self::access($company,true);
            // Keep each encrypted value below common database packet limits.
            // Immutable keys also preserve the document versions used by saved Reviews.
            $prefix='_wgpp_foundation_blob_'.bin2hex(random_bytes(16)).'_';$keys=[];
            try{
                foreach(str_split($encrypted,262144) as $index=>$chunk){
                    $key=$prefix.$index;
                    if(!add_post_meta($company,$key,$chunk,true)){throw new RuntimeException('Document storage could not be written. Please try again or contact the site administrator.');}
                    $keys[]=$key;
                }
                $r=self::records($company);$r[$kind]=['metadata'=>$metadata,'encrypted_chunks'=>$keys];
                if(!update_post_meta($company,self::META,$r) && get_post_meta($company,self::META,true)!==$r){throw new RuntimeException('The document could not be saved. Please try again.');}
            }catch(RuntimeException $e){foreach($keys as $key){delete_post_meta($company,$key);}throw $e;}
        });return $metadata;
    }
    public static function rename($company,$kind,$name){
        self::access($company,true);
        if(!isset(self::TYPES[$kind])||!is_string($name)||strlen($name)>240){throw new InvalidArgumentException('Enter a PDF filename up to 240 characters.');}
        $name=sanitize_file_name(wp_unslash($name));
        if(!$name||strtolower(pathinfo($name,PATHINFO_EXTENSION))!=='pdf'){throw new InvalidArgumentException('Enter a filename ending in .pdf.');}
        WGPP_Native_Reviews::locked($company,static function()use($company,$kind,$name){
            self::access($company,true);$r=self::records($company);
            if(!isset($r[$kind]['metadata'])){throw new InvalidArgumentException('Upload this document first.');}
            $r[$kind]['metadata']['filename']=$name;
            if(!update_post_meta($company,self::META,$r)&&get_post_meta($company,self::META,true)!==$r){throw new RuntimeException('The filename could not be saved.');}
        });
    }
    public static function save(){
        try{$c=WGPP_Company::context();self::access($c['id']??0,true);check_admin_referer('wgpp_foundation_document');$kind=$_POST['document_kind']??'';if(!is_string($kind)||!isset(self::TYPES[$kind])){throw new InvalidArgumentException('Invalid document type.');}
            if(($_POST['document_op']??'upload')==='remove'){WGPP_Native_Reviews::locked($c['id'],static function()use($c,$kind){self::access($c['id'],true);$r=self::records($c['id']);unset($r[$kind]);update_post_meta($c['id'],self::META,$r);});}
            elseif(($_POST['document_op']??'')==='rename'){self::rename($c['id'],$kind,$_POST['document_filename']??'');}
            else{$f=$_FILES['foundation_pdf']??null;if(!$f || ($f['error']??1)!==UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name']) || ($f['size']??0)>self::MAX_BYTES){throw new InvalidArgumentException('Select a PDF report up to 20 MB.');}$bytes=file_get_contents($f['tmp_name']);if($bytes===false){throw new RuntimeException('Upload could not be read.');}self::store($c['id'],$kind,$f['name'],$bytes);}
            set_transient('wgpp_notice_'.get_current_user_id(),'Brand foundations updated. New Reviews will use the current documents; saved Reviews are unchanged.',60);wp_safe_redirect(add_query_arg('workspace',$c['id'],home_url('/pro-analytics/')).'#account-foundations');exit;
        }catch(InvalidArgumentException|RuntimeException $e){wp_die(esc_html($e->getMessage()));}
    }
    public static function bytes($company,$kind,$record){
        self::access($company);
        if(isset($record['encrypted_chunks'])){
            $keys=$record['encrypted_chunks'];
            if(!is_array($keys)||!$keys||count($keys)>320){throw new RuntimeException('Document storage is invalid. Upload it again.');}
            $encrypted='';
            foreach($keys as $key){
                if(!is_string($key)||!preg_match('/^_wgpp_foundation_blob_[a-f0-9]{32}_[0-9]+$/D',$key)){throw new RuntimeException('Document storage is invalid. Upload it again.');}
                $chunk=get_post_meta($company,$key,true);
                if(!is_string($chunk)||$chunk===''||strlen($chunk)>262144){throw new RuntimeException('Document storage is incomplete. Upload it again.');}
                $encrypted.=$chunk;
            }
        }else{$encrypted=$record['encrypted']??'';}
        $v=WGPP_Google::decrypt($encrypted,'foundation:'.$company.':'.$kind);$bytes=base64_decode($v['base64']??'',true);if($bytes===false || !hash_equals($record['metadata']['sha256'],hash('sha256',$bytes))){throw new RuntimeException('Document verification failed. Upload it again.');}return $bytes;}
    public static function download(){
        try{$c=WGPP_Company::context();self::access($c['id']??0);check_admin_referer('wgpp_foundation_download');$kind=$_GET['document_kind']??'';if(!is_string($kind)||!isset(self::TYPES[$kind])){throw new InvalidArgumentException('Invalid document.');}$r=self::records($c['id'])[$kind]??null;if(!$r){throw new InvalidArgumentException('Document unavailable.');}$bytes=self::bytes($c['id'],$kind,$r);nocache_headers();header('Content-Type: application/pdf');header('X-Content-Type-Options: nosniff');header('Content-Disposition: attachment; filename="'.sanitize_file_name($r['metadata']['filename']).'"');header('Content-Length: '.strlen($bytes));echo $bytes;exit;}catch(InvalidArgumentException|RuntimeException $e){wp_die(esc_html($e->getMessage()));}
    }
    public static function capture($company){$out=['foundation_documents'=>[],'_foundation_files'=>[]];foreach(self::records($company) as $kind=>$r){if(isset(self::TYPES[$kind]) && isset($r['metadata']) && (isset($r['encrypted']) || isset($r['encrypted_chunks']))){$out['foundation_documents'][$kind]=$r['metadata'];$out['_foundation_files'][$kind]=$r;}}return $out;}
    public static function input_files($company,$snapshot){$out=[];foreach(($snapshot['context']['_foundation_files']??[]) as $kind=>$r){if(!isset(self::TYPES[$kind])){throw new RuntimeException('Invalid foundation document.');}$out[]=['type'=>'input_text','text'=>'Company foundation document: '.self::TYPES[$kind].' (document key '.$kind.'). Version SHA256 '.$r['metadata']['sha256'].'.'];$out[]=['type'=>'input_file','filename'=>$r['metadata']['filename'],'file_data'=>'data:application/pdf;base64,'.base64_encode(self::bytes($company,$kind,$r))];}return $out;}
    public static function render(){
        $c=WGPP_Company::context();if(!$c){return '';}$records=self::records($c['id']);$edit=WGPP_Company::can('review');ob_start();echo '<h4>Brand foundations</h4><p>Upload your existing Strategy Canvas and Brand Dyno reports to guide new Pro Analytics Reviews. Either is optional. Results still come from the selected analytics; these documents supply Brand direction, positioning and team context.</p><p class="wgpa-small-note">PDF reports, up to 20 MB each. Stored privately for this company, outside the public Media Library. Document-assisted Reviews send these reports to OpenAI using the site’s configured enhancement. An administrator must enable enhancement before a Review can assess uploaded reports. Saved Reviews retain the versions used when generated.</p>';
        foreach(self::TYPES as $kind=>$title){$r=$records[$kind]??null;echo '<section class="wgpa-account-card"><h4>'.esc_html($title).'</h4>';if($r){$m=$r['metadata'];$stamp=(new DateTimeImmutable($m['uploaded_at']))->setTimezone(new DateTimeZone('America/Chicago'))->format('m/d/Y g:i A').' Central Time';$url=wp_nonce_url(add_query_arg(['action'=>'wgpp_foundation_download','workspace'=>$c['id'],'document_kind'=>$kind],admin_url('admin-post.php')),'wgpp_foundation_download');echo '<p><strong>'.esc_html($m['filename']).'</strong><br>Uploaded '.esc_html($stamp).' · Version '.esc_html(substr($m['sha256'],0,12)).'</p><p><a href="'.esc_url($url).'">Download '.esc_html($title).'</a></p>';}else{echo '<p>Not uploaded · Optional</p>';}
            if($edit){echo '<form method="post" enctype="multipart/form-data" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('wgpp_foundation_document');echo '<input type="hidden" name="action" value="wgpp_foundation_document"><input type="hidden" name="document_kind" value="'.$kind.'"><p><label>'.($r?'Replace ':'Upload ').esc_html($title).' PDF <input type="file" name="foundation_pdf" accept="application/pdf,.pdf"></label></p><button name="document_op" value="upload">'.($r?'Replace report':'Upload report').'</button>';if($r){echo ' <button name="document_op" value="remove">Remove report</button>';}echo '</form>';if($r){echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('wgpp_foundation_document');echo '<input type="hidden" name="action" value="wgpp_foundation_document"><input type="hidden" name="document_kind" value="'.esc_attr($kind).'"><p><label>Report filename <input type="text" name="document_filename" maxlength="240" value="'.esc_attr($r['metadata']['filename']).'" required></label></p><button name="document_op" value="rename">Rename report</button></form>';}}
            echo '</section>';
        }if(!$edit){echo '<p>Your Owner or an Analyst can upload or replace these reports.</p>';}return ob_get_clean();
    }
}
