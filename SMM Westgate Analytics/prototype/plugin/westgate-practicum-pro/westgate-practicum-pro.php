<?php
/**
 * Plugin Name: Westgate Practicum Pro
 * Description: Pro-first portal for the independent Westgate analytics service. Development foundation.
 * Version: 0.1.0-dev
 * Author: Westgate Marketing
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 */
if (!defined('ABSPATH')) { exit; }

final class Westgate_Practicum_Pro {
    public static function boot() {
        add_shortcode('westgate_practicum_pro', [__CLASS__, 'render']);
        add_action('admin_post_wgpp_save', [__CLASS__, 'save']);
        add_action('template_redirect', [__CLASS__, 'private_page']);
    }
    private static function allowed() {
        return is_user_logged_in() && (current_user_can('manage_options') || current_user_can('westgate_practicum_access'));
    }
    public static function private_page() {
        global $post;
        if ($post && has_shortcode($post->post_content, 'westgate_practicum_pro')) {
            if (!defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE', true); }
            nocache_headers();
        }
    }
    private static function request($method, $path, $data = null) {
        if (!defined('WESTGATE_ANALYTICS_URL') || !defined('WESTGATE_ANALYTICS_KEY') || !defined('WESTGATE_ANALYTICS_TENANT')) {
            return new WP_Error('not_configured', 'The analytics service has not been configured.');
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
        $return = esc_url_raw(wp_unslash($_POST['return_to'] ?? ''));
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
        echo '<input type="hidden" name="action" value="wgpp_save"><input type="hidden" name="kind" value="' . esc_attr($kind) . '"><input type="hidden" name="return_to" value="' . esc_url(get_permalink()) . '">';
    }
    public static function render() {
        if (!is_user_logged_in()) { return '<p><a href="' . esc_url(wp_login_url(get_permalink())) . '">Sign in to view your campaign.</a></p>'; }
        if (!self::allowed()) { return '<p>Your account has not been enabled for the Practicum Pro development preview.</p>'; }
        wp_enqueue_style('wgpp', plugins_url('portal.css', __FILE__), [], '0.1.0');
        $result = self::request('GET', '/v1/project');
        ob_start();
        echo '<section class="wgpp"><header><p class="wgpp-eyebrow">WESTGATE · SOCIAL MEDIA MAGNET PRO</p><h2>Your campaign, in perspective</h2><p>Understand what attracts your audience, what brings them back, and what to try next.</p><p class="wgpp-note">Development preview · Provider connections are not available yet.</p></header>';
        $notice = get_transient('wgpp_notice_' . get_current_user_id());
        if ($notice) { echo '<p role="status">' . esc_html($notice) . '</p>'; delete_transient('wgpp_notice_' . get_current_user_id()); }
        if (is_wp_error($result)) { echo '<p role="status">' . esc_html($result->get_error_message()) . '</p></section>'; return ob_get_clean(); }
        $p = $result['project'] ?? [];
        echo '<nav aria-label="Campaign sections"><a href="#wgpp-setup">Campaign setup</a><a href="#wgpp-results">Performance</a><a href="#wgpp-learn">Reflect &amp; experiment</a></nav>';
        echo '<details id="wgpp-setup"' . (!$p ? ' open' : '') . '><summary>Campaign setup &amp; brand brief</summary>';
        self::form_start('project');
        echo '<input type="hidden" name="revision" value="' . esc_attr($result['revision']) . '"><div class="wgpp-grid">';
        foreach (['name'=>'Campaign name','website'=>'Website','primary_action'=>'Primary meaningful action','timezone'=>'Reporting timezone (e.g. America/Chicago)','start_date'=>'Campaign start','end_date'=>'Campaign end'] as $key=>$label) {
            self::input($key, $label, $p[$key] ?? '', in_array($key,['start_date','end_date'],true) ? 'date' : ($key==='website' ? 'url' : 'text'), true);
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
            echo '</section><section id="wgpp-learn"><h3>Reflect &amp; choose your next experiment</h3>';
            self::form_start('reflection');
            self::input('week_start','Week beginning (Sunday)','','date',true);
            foreach (['evidence'=>'What did you observe? Include source evidence.','alignment'=>'How did your activity support your brand?','swot'=>'Strengths, weaknesses, opportunities, or threats','experiment'=>'What will you test next?','success_measure'=>'How will you judge the result?'] as $key=>$label) { self::textarea($key,$label,'',in_array($key,['experiment','success_measure'],true),3000); }
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
