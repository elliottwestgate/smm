<?php
if (!defined('ABSPATH')) { exit; }

/** Local workflow preview. No provider requests or legacy data writes. */
final class WGPP_Local_Preview {
    const KEY = '_wgpp_preview_project_v1';
    const LOG = '_wgpp_preview_reflections_v1';

    public static function request($method, $path, $data) {
        $uid = get_current_user_id();
        if (!$uid) { return new WP_Error('forbidden', 'Sign in to continue.'); }
        $saved = get_user_meta($uid, self::KEY, true);
        $saved = is_array($saved) ? $saved : ['project' => null, 'revision' => 0];
        if ($method === 'GET' && $path === '/v1/project') { return $saved; }
        if ($method === 'PUT' && $path === '/v1/project') {
            if (($data['revision'] ?? -1) !== $saved['revision']) { return new WP_Error('conflict', 'Campaign changed. Reload before saving.'); }
            $fields = ['name','website','timezone','start_date','end_date','primary_action','email_provider','story','promise','belief','trust'];
            $project = [];
            foreach ($fields as $f) {
                $value = $data[$f] ?? '';
                $limit = in_array($f, ['story','promise','belief','trust'], true) ? 2000 : 250;
                if (!is_string($value) || strlen($value) > $limit * 4) { return new WP_Error('validation', 'Please shorten ' . $f . '.'); }
                $project[$f] = $value;
            }
            if (!$project['name'] || !$project['primary_action']) { return new WP_Error('validation', 'Campaign name and primary action are required.'); }
            $url = wp_parse_url($project['website']);
            if (!$url || ($url['scheme'] ?? '') !== 'https' || empty($url['host']) || !empty($url['user']) || !empty($url['pass'])) { return new WP_Error('validation', 'Enter an HTTPS website address without credentials.'); }
            if (!in_array($project['timezone'], timezone_identifiers_list(), true)) { return new WP_Error('validation', 'Use a valid timezone, for example America/Chicago.'); }
            foreach (['start_date','end_date'] as $f) {
                if (!self::valid_date($project[$f])) { return new WP_Error('validation', 'Enter valid campaign dates.'); }
            }
            if ($project['end_date'] < $project['start_date']) { return new WP_Error('validation', 'End date must follow start date.'); }
            if (!in_array($project['email_provider'], ['constant_contact','mailchimp'], true)) { return new WP_Error('validation', 'Select Constant Contact or Mailchimp.'); }
            $project['edition'] = 'pro';
            $project['week_starts_on'] = 'Sunday';
            $next = ['project' => $project, 'revision' => $saved['revision'] + 1];
            if ($saved['revision'] === 0) {
                $ok = add_user_meta($uid, self::KEY, $next, true);
            } else {
                $ok = update_user_meta($uid, self::KEY, $next, $saved);
            }
            if (!$ok) { return new WP_Error('save_failed', 'Campaign was not saved. Reload and try again.'); }
            return ['saved' => true, 'revision' => $next['revision']];
        }
        if (!$saved['project']) { return new WP_Error('setup', 'Set up your campaign first.'); }
        if ($method === 'GET' && $path === '/v1/report') {
            $groups = [
                'Brand Awareness' => ['Total users','New users','Sessions','Views'],
                'Brand Acquisition' => ['Tagged email sessions','Organic social sessions','Organic search sessions','Referral sessions','Direct / unattributed sessions'],
                'Brand Retention' => ['Primary actions','Subscribed audience','Unique email clicks','Email opens (qualified)'],
            ];
            $panels = [];
            foreach ($groups as $group => $names) { $panels[$group] = array_map(static function($name) { return ['metric' => $name, 'value' => null, 'status' => 'not_connected']; }, $names); }
            return ['panels' => $panels, 'weekly' => null, 'campaign_to_date' => null];
        }
        if ($path === '/v1/reflections') {
            if ($method === 'GET') {
                $rows = get_user_meta($uid, self::LOG, false);
                return ['reflections' => array_slice(array_reverse($rows), 0, 100)];
            }
            if ($method === 'POST') {
                $start = $data['week_start'] ?? '';
                if (!self::valid_date($start)) { return new WP_Error('validation', 'Select a valid Sunday.'); }
                $week = new DateTimeImmutable($start, new DateTimeZone('UTC'));
                if ($week->format('w') !== '0') { return new WP_Error('validation', 'Week must begin on Sunday.'); }
                $p = $saved['project'];
                if ($start > $p['end_date'] || $week->modify('+6 days')->format('Y-m-d') < $p['start_date']) { return new WP_Error('validation', 'The selected week must overlap your campaign.'); }
                $entry = [];
                foreach (['evidence','alignment','swot','experiment','success_measure'] as $f) {
                    $value = $data[$f] ?? '';
                    if (!is_string($value) || strlen($value) > 12000) { return new WP_Error('validation', 'Please shorten your reflection.'); }
                    $entry[$f] = $value;
                }
                if (!$entry['experiment'] || !$entry['success_measure']) { return new WP_Error('validation', 'Enter an experiment and a success measure.'); }
                $ok = add_user_meta($uid, self::LOG, ['week_start' => $start, 'data' => $entry, 'created' => time()]);
                return $ok ? ['saved' => true] : new WP_Error('save_failed', 'Reflection was not saved. Please try again.');
            }
        }
        return new WP_Error('route', 'Unsupported preview request.');
    }
    private static function valid_date($value) {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) { return false; }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value;
    }
}
