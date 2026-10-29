<?php
/**
 * Plugin Name: SMM Pro Analytics
 * Description: Company analytics dashboard and queued performance reviews within WordPress. Native Google Analytics, Constant Contact reporting and Facebook Page pilot.
 * Version: 0.6.30-preview
 * Author: Westgate Marketing
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 */
if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/local-preview.php';
require_once __DIR__ . '/analytics.php';
require_once __DIR__ . '/company.php';
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/portal.php';
require_once __DIR__ . '/account.php';
require_once __DIR__ . '/delivery-pdf.php';
require_once __DIR__ . '/report-delivery.php';
require_once __DIR__ . '/reviews.php';
require_once __DIR__ . '/native-reviews.php';
require_once __DIR__ . '/google.php';
require_once __DIR__ . '/ga4.php';
require_once __DIR__ . '/trends.php';
require_once __DIR__ . '/constant-contact.php';
require_once __DIR__ . '/cc-reports.php';
require_once __DIR__ . '/facebook.php';
require_once __DIR__ . '/facebook-reports.php';

register_activation_hook(__FILE__, ['WGPP_Report_Delivery','activate']);

final class Westgate_Practicum_Pro {
    public static function boot() {
        WGPP_Native_Reviews::boot();
        WGPP_WishList::boot();
        WGPP_Landing::boot();
        WGPP_Pro_Review::boot();
        add_filter('wp_nav_menu_items',[__CLASS__,'pro_navigation'],30,2);
        WGPP_Google::boot();
        WGPP_GA4::boot();
        WGPP_Account::boot();
        WGPP_Report_Delivery::boot();
        WGPP_Constant_Contact::boot();
        WGPP_CC_Reports::boot();
        WGPP_Facebook::boot();
        add_action('init', ['WGPP_Company','boot']);
        add_action('admin_post_wgpp_company', ['WGPP_Company','save']);
        add_action('wp_ajax_wgpp_reviews', ['WGPP_Reviews','ajax']);
        add_action('wp_ajax_wgpp_trends',[WGPP_Trends::class,'ajax']);
        add_action('wp_ajax_wgpp_snapshot', ['WGPP_Analytics', 'ajax']);
        add_action('admin_post_wgpp_sample', ['WGPP_Analytics','sample']);
        add_action('admin_post_wgpp_import', ['WGPP_Analytics', 'import']);
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_menu', [__CLASS__, 'hide_legacy_menu'], 99);
        add_action('admin_enqueue_scripts', [__CLASS__, 'styles']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'styles']);
        add_shortcode('westgate_practicum_pro', [__CLASS__, 'render']);
        add_action('admin_post_wgpp_save', [__CLASS__, 'save']);
        add_action('template_redirect', [__CLASS__, 'private_page']);
    }
    /** Applies to the primary location even when WishList chooses a member menu. */
    public static function pro_navigation($items,$args) {
        if (($args->theme_location ?? '')!=='primary' || strpos($items,'wgpp-pro-navigation')!==false || !get_page_by_path('pro-analytics-overview')) { return $items; }
        $base=home_url('/');
        $navigation='<li class="menu-item menu-item-has-children wgpp-pro-navigation"><a href="'.esc_url($base.'pro-analytics-overview/').'">Pro Analytics</a><ul class="sub-menu">';
        foreach(['pro-analytics-overview/'=>'Overview','pro-analytics/'=>'Dashboard','buy-pro-analytics/'=>'Buy'] as $path=>$label) { $navigation.='<li class="menu-item"><a href="'.esc_url($base.$path).'">'.esc_html($label).'</a></li>'; }
        return $navigation.'</ul></li>'.$items;
    }
    public static function styles($hook = '') {
        global $post;
        if (in_array($hook, ['tools_page_wgpp-preview','toplevel_page_wgpp-preview'], true) || (!is_admin() && $post && has_shortcode($post->post_content, 'westgate_practicum_pro'))) {
            wp_enqueue_style('wgpp', plugins_url('portal.css', __FILE__), [], '0.6.6-preview');
        }
    }
    public static function menu() {
        add_menu_page('SMM Pro Analytics', 'SMM Pro Analytics', 'manage_options', 'wgpp-preview', [__CLASS__, 'admin_page'], 'dashicons-chart-area', 26);
        add_submenu_page('wgpp-preview', 'SMM Pro Analytics Dashboard', 'Dashboard', 'manage_options', 'wgpp-preview', [__CLASS__, 'admin_page']);
        add_submenu_page('wgpp-preview', 'SMM Pro Analytics Review Settings', 'Review Settings', 'manage_options', 'wgpp-pro-review', ['WGPP_Pro_Review','settings_page']);
        // Retain registered legacy routes for bookmarks, but remove them from Tools.
        add_management_page('SMM Pro Analytics', 'SMM Pro Analytics', 'manage_options', 'wgpp-preview', [__CLASS__, 'admin_page']);
    }
    public static function hide_legacy_menu() {
        remove_submenu_page('tools.php', 'wgpp-preview');
        remove_submenu_page('tools.php', 'wgpp-pro-review');
    }
    public static function admin_page() {
        if (!current_user_can('manage_options')) { return; }
        echo WGPP_Admin::render();
    }
    private static function return_url() {
        return is_admin() ? admin_url('admin.php?page=wgpp-preview') : get_permalink();
    }
    private static function allowed() {
        return is_user_logged_in() && (current_user_can('manage_options') || current_user_can('westgate_practicum_access') || WGPP_Company::can('view') || WGPP_WishList::can_create());
    }
    public static function private_page() {
        global $post;
        if ($post && has_shortcode($post->post_content, 'westgate_practicum_pro')) {
            if (!defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE', true); }
            nocache_headers();
            header('X-Robots-Tag: noindex, nofollow');
            header('Referrer-Policy: no-referrer');
            if (isset($_GET['invite']) || isset($_GET['wgpp_email_confirm'])) {
                add_action('wp_head',static function(){ echo '<script>history.replaceState(null,"",location.pathname);</script>'; },-10000);
            }
        }
    }
    private static function request($method, $path, $data = null) {
        if (!defined('WESTGATE_ANALYTICS_URL') || !defined('WESTGATE_ANALYTICS_KEY') || !defined('WESTGATE_ANALYTICS_TENANT')) {
            if (defined('WESTGATE_ANALYTICS_URL') || defined('WESTGATE_ANALYTICS_KEY') || defined('WESTGATE_ANALYTICS_TENANT')) { return new WP_Error('not_configured', 'Complete or remove the partial service configuration.'); }
            return WGPP_Local_Preview::request($method, $path, $data);
        }
        $base = untrailingslashit(WESTGATE_ANALYTICS_URL);
        $parts = wp_parse_url($base);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || !empty($parts['query']) || !empty($parts['fragment']) || !empty($parts['user']) || !empty($parts['pass']) || !empty($parts['path'])) {
            return new WP_Error('invalid_config', 'Use the HTTPS service origin without a path or credentials.');
        }
        if (strlen(WESTGATE_ANALYTICS_KEY) < 32 || !preg_match('/^[a-z0-9_-]{1,64}$/D', WESTGATE_ANALYTICS_TENANT)) {
            return new WP_Error('invalid_config', 'Analytics service configuration is incomplete.');
        }
        $body = $data === null ? '' : wp_json_encode($data);
        $principal = (string) get_current_user_id();
        $stamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $canonical = implode("\n", [$method, $path, WESTGATE_ANALYTICS_TENANT, $principal, $stamp, $nonce, hash('sha256', $body)]);
        $response = wp_safe_remote_request($base . $path, [
            'method' => $method, 'timeout' => 10, 'redirection' => 0,
            'limit_response_size' => 262144, 'body' => $body,
            'headers' => ['Content-Type' => 'application/json',
                'X-Westgate-Tenant' => WESTGATE_ANALYTICS_TENANT,
                'X-Westgate-Principal' => $principal, 'X-Westgate-Timestamp' => $stamp,
                'X-Westgate-Nonce' => $nonce,
                'X-Westgate-Signature' => hash_hmac('sha256', $canonical, WESTGATE_ANALYTICS_KEY)],
        ]);
        if (is_wp_error($response)) { return new WP_Error('service_unavailable', 'Analytics is temporarily unavailable. Your saved campaign is retained.'); }
        $code = wp_remote_retrieve_response_code($response);
        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300 || !is_array($decoded)) {
            $message = in_array($code, [409, 422], true) && is_array($decoded) ? ($decoded['error'] ?? 'Please check your entries.') : 'The analytics service could not complete this request.';
            return new WP_Error('service_error', $message);
        }
        return $decoded;
    }
    public static function save() {
        if (!self::allowed()) { wp_die('Access denied.', '', ['response' => 403]); }
        check_admin_referer('wgpp_save');
        $type = isset($_POST['kind']) ? sanitize_key(wp_unslash($_POST['kind'])) : '';
        $fields = $type === 'project'
            ? ['name','website','timezone','start_date','end_date','primary_action','email_provider','story','promise','belief','trust']
            : ['week_start','evidence','alignment','swot','experiment','success_measure'];
        if (!in_array($type, ['project','reflection'], true)) { wp_die('Invalid action.', '', ['response' => 400]); }
        $data = [];
        foreach ($fields as $field) {
            if (isset($_POST[$field]) && !is_string($_POST[$field])) { wp_die('Invalid input.', '', ['response' => 400]); }
            $data[$field] = sanitize_textarea_field(wp_unslash($_POST[$field] ?? ''));
        }
        if ($type === 'project') { $data['revision'] = absint($_POST['revision'] ?? 0); }
        $result = self::request($type === 'project' ? 'PUT' : 'POST', $type === 'project' ? '/v1/project' : '/v1/reflections', $data);
        set_transient('wgpp_notice_' . get_current_user_id(), is_wp_error($result) ? $result->get_error_message() : 'Saved.', 60);
        $return = isset($_POST['return_to']) && is_string($_POST['return_to']) ? esc_url_raw(wp_unslash($_POST['return_to'])) : ''; 
        wp_safe_redirect(wp_validate_redirect($return, home_url('/')));
        exit;
    }
    private static function input($key, $label, $value = '', $type = 'text', $required = false) {
        echo '<label>' . esc_html($label) . '<input name="' . esc_attr($key) . '" type="' . esc_attr($type) . '" value="' . esc_attr($value) . '" maxlength="250"' . ($required ? ' required' : '') . '></label>';
    }
    private static function textarea($key, $label, $value = '', $required = false, $limit = 2000) {
        echo '<label>' . esc_html($label) . '<textarea name="' . esc_attr($key) . '" rows="3" maxlength="' . esc_attr($limit) . '"' . ($required ? ' required' : '') . '>' . esc_textarea($value) . '</textarea></label>';
    }
    private static function form_start($kind) {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('wgpp_save');
        echo '<input type="hidden" name="action" value="wgpp_save"><input type="hidden" name="kind" value="' . esc_attr($kind) . '"><input type="hidden" name="return_to" value="' . esc_url(self::return_url()) . '">';
    }
    public static function render() {
        if(isset($_GET['wgpp_email_confirm'])){return WGPP_Account::confirmation();}
        if (!self::allowed() && WGPP_Team::invitation()) { return WGPP_Team::invitation(); }
        if (!is_user_logged_in()) { return '<p><a href="' . esc_url(wp_login_url(get_permalink())) . '">Sign in to view your campaign.</a></p>'; }
        if (!self::allowed()) { return '<p>Your company analytics access is inactive or has not been assigned. Ask your owner for an invitation, or visit Pro Analytics → Buy.</p>'; }
        wp_enqueue_style('wgpp', plugins_url('portal.css', __FILE__), [], '0.6.6-preview');
        return WGPP_Company::decorate(WGPP_Team::invitation() . (WGPP_Company::can('view') ? WGPP_Analytics::render(WGPP_Portal::account()) : WGPP_Company::render()));
    }
    public static function legacy_workflow() {
        if (!self::allowed()) { return ''; }
        $result = self::request('GET', '/v1/project');
        ob_start();
        echo '<section class="wgpp"><header><p class="wgpp-eyebrow">WESTGATE · SOCIAL MEDIA MAGNET PRO</p><h2>Your campaign, in perspective</h2><p>Understand what attracts your audience, what brings them back, and what to try next.</p><p class="wgpp-note">Workflow preview · Saves your campaign and reflections. Live analytics are not connected.</p></header>';
        $notice = get_transient('wgpp_notice_' . get_current_user_id());
        if ($notice) { echo '<p role="status">' . esc_html($notice) . '</p>'; delete_transient('wgpp_notice_' . get_current_user_id()); }
        if (is_wp_error($result)) { echo '<p role="status">' . esc_html($result->get_error_message()) . '</p></section>'; return ob_get_clean(); }
        $p = $result['project'] ?? [];
        echo '<nav aria-label="Campaign sections"><a href="#wgpp-setup">Campaign setup</a>'; if ($p) { echo '<a href="#wgpp-results">Performance</a><a href="#wgpp-learn">Campaign notes</a>'; } echo '</nav>';
        echo '<details id="wgpp-setup"' . (!$p ? ' open' : '') . '><summary>Campaign setup &amp; brand brief</summary>';
        self::form_start('project');
        echo '<input type="hidden" name="revision" value="' . esc_attr($result['revision']) . '"><div class="wgpp-grid">';
        foreach (['name'=>'Campaign name','website'=>'Website','primary_action'=>'Primary meaningful action','timezone'=>'Reporting timezone (e.g. America/Chicago)','start_date'=>'Campaign start','end_date'=>'Campaign end'] as $key=>$label) {
            self::input($key, $label, $p[$key] ?? ($key === 'website' ? 'https://aladdincleans.com' : ($key === 'timezone' ? 'America/Chicago' : '')), in_array($key,['start_date','end_date'],true) ? 'date' : ($key==='website' ? 'url' : 'text'), true);
        }
        echo '<label>Email provider<select name="email_provider">';
        foreach (['constant_contact'=>'Constant Contact','mailchimp'=>'Mailchimp'] as $value=>$label) { echo '<option value="' . esc_attr($value) . '"' . selected($p['email_provider'] ?? 'constant_contact', $value, false) . '>' . esc_html($label) . '</option>'; }
        echo '</select></label>';
        echo '</div><p>Reporting weeks run Sunday–Saturday in your chosen timezone. Your website can use any platform.</p>';
        foreach (['story'=>'Story — why your brand exists','promise'=>'Promise — what people can rely on','belief'=>'Belief — the values behind your offer','trust'=>'Trust — evidence that supports your promise'] as $key=>$label) { self::textarea($key,$label,$p[$key] ?? ''); }
        echo '<button type="submit">Save campaign</button></form></details>';
        if ($p) {
            echo '<section id="wgpp-results"><h3>' . esc_html($p['name']) . '</h3><p>Weekly results and campaign-to-date results will appear after your sources are connected. Collection will continue during weeks without publishing.</p>';
            $report = self::request('GET','/v1/report');
            if (is_wp_error($report)) { echo '<p role="status">' . esc_html($report->get_error_message()) . '</p>'; }
            else {
                echo '<div class="wgpp-grid">';
                foreach ($report['panels'] as $group=>$metrics) {
                    echo '<article><h4>' . esc_html($group) . '</h4><dl>';
                    foreach ($metrics as $metric) { echo '<dt>' . esc_html($metric['metric']) . '</dt><dd>Not connected</dd>'; }
                    echo '</dl></article>';
                }
                echo '</div><p class="wgpp-note">Missing data is not zero. Direct traffic is not assumed to be email. Returning visits alone do not establish customer loyalty. Comparative scores await an approved Pro scoring policy.</p>';
            }
            echo '</section><section id="wgpp-learn"><h3>Campaign follow-up</h3>';
            self::form_start('reflection');
            self::input('week_start','Week beginning (Sunday)','','date',true);
            foreach (['evidence'=>'What did you observe? Include source evidence.','alignment'=>'How did your activity support your brand?','swot'=>'Strengths, weaknesses, opportunities, or threats','experiment'=>'What campaign action will you take next?','success_measure'=>'How will you judge the result?'] as $key=>$label) { self::textarea($key,$label,'',in_array($key,['experiment','success_measure'],true),3000); }
            echo '<button type="submit">Save reflection</button></form>';
            $reflections = self::request('GET','/v1/reflections');
            if (is_wp_error($reflections)) { echo '<p role="status">' . esc_html($reflections->get_error_message()) . '</p>'; }
            else { foreach ($reflections['reflections'] as $item) { echo '<article><h4>Week of ' . esc_html($item['week_start']) . '</h4>'; foreach ($item['data'] as $key=>$value) { if ($value !== '') { echo '<p><strong>' . esc_html(ucwords(str_replace('_',' ',$key))) . '</strong><br>' . nl2br(esc_html($value)) . '</p>'; } } echo '</article>'; } }
            echo '</section>';
        }
        echo '</section>';
        return ob_get_clean();
    }
}
Westgate_Practicum_Pro::boot();
