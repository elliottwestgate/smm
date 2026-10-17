<?php

/*
  Plugin Name: Social Media Magnet - (Website Analytics)
  Description: Google Analytics plugin designed for integration with Social Media Magnet.
  Version: 3.0.1
  Author: Westgate Marketing
  Author URI: https://westgatemarketing.net
  License: GPLv2+
  Icon1x: https://socialmediamagnet.net/wp-content/uploads/icon-128x128.png
  Icon2x: https://socialmediamagnet.net/wp-content/uploads/icon-256x256.png
 */

if (!defined('ABSPATH')) {
    exit; //exit if accessed directly
}

//load plugin updater
require_once plugin_dir_path(__FILE__) . 'lib/wp-package-updater/class-wp-package-updater.php';

//enable plugin updates without license check
$WP_Analytify_Simple_updater = new WP_Package_Updater(
    'https://socialmediamagnet.net',
    wp_normalize_path(__FILE__),
    wp_normalize_path(plugin_dir_path(__FILE__))
);

ini_set('include_path', dirname(__FILE__) . '/lib/google-api-php-client-master/');
define('ROOT_PATH', dirname(__FILE__));

if(!defined('SMM_WEBSITE_URL')) {
    define('SMM_WEBSITE_URL', 'https://socialmediamagnet.net/');
}

//load custom post type
include(dirname(__FILE__) . '/includes/website-analytics-post-type.php');

//load weekly analytics function (*wp cron event)
include(dirname(__FILE__) . '/includes/analytify-wp-cron.php');

//student website authentication ajax
include(dirname(__FILE__) . '/includes/analytify-wp-ajax.php');

//student apis
include(dirname(__FILE__) . '/includes/analytify-apis.php');


//load jQuery dependency files for datepicker
function add_custom_date_picker()
{
    //jQuery UI date picker file
    wp_enqueue_script('jquery-ui-datepicker');
    //jQuery UI theme css file
    wp_enqueue_style('analytics_datepicker', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.9.0/themes/base/jquery-ui.css', false, "1.9.0", false);
}

add_action('admin_enqueue_scripts', 'add_custom_date_picker');

//load custom css file 
function westgate_analytics_css()
{
    wp_enqueue_style('style', plugin_dir_url(__FILE__) . '/css/wp-analytify-front-style.css');
}

add_action('wp_enqueue_scripts', 'westgate_analytics_css');

//register wp cron event (*activation of plugin)
register_activation_hook(__FILE__, 'website_analytics_cron_activation');

//schedule wp cron event
function website_analytics_cron_activation()
{
    if (!wp_next_scheduled('website_analytics_cron_event')) {
        $first_event = strtotime('next Sunday 8 am');
        wp_schedule_single_event($first_event, 'website_analytics_cron_event');
    }
}

//wp cron event function
function weekly_analytics_cron()
{
    //get today's date
    $todays_date = date('Y-m-d');
    //get class start date
    $class_start_date = get_option("class_start_date");
    //format class start date for queries
    $orderdate = explode("-", $class_start_date);
    //query start / end dates
    $start_date = $orderdate[2] . '-' . $orderdate[0] . '-' . $orderdate[1];

    //check for start date
    if (empty($class_start_date)) {
        //set start date
        $start_date = '2038-01-01';
        //remove weekly analytics	
        remove_website_analytics($start_date);
    } else if ((!empty($class_start_date)) && ($start_date === $todays_date)) {
        //set start date
        $start_date = '2038-01-01';
        //remove weekly analytics	
        remove_website_analytics($start_date);
    } else if ((!empty($class_start_date)) && ($start_date > $todays_date)) {
        //set start date
        $start_date = '2038-01-01';
        //remove weekly analytics	
        remove_website_analytics($start_date);
    } else if ((!empty($class_start_date)) && ($start_date < $todays_date)) {
	
		$class_start_date = get_option("class_start_date");
		$start_date = explode('-', $class_start_date);
		$start_date = $start_date[1].'-'.$start_date[0].'-'.$start_date[2];

		$class_end_date = get_option("class_end_date");
		$end_date = explode('-', $class_end_date);
		$end_date = $end_date[1].'-'.$end_date[0].'-'.$end_date[2];

        $start_date_epoch = strtotime($start_date);
        $todays_date_epoch = strtotime($todays_date);
        $week = floor(abs($start_date_epoch - $todays_date_epoch) / 604800);
        $title = 'Week '.$week;
		
		if(!empty($title) && !empty($week)){
        // Update the campain id 
        analytics_campaigns_data();
        //get weekly posts count
			//$count_posts = wp_count_posts('website_analytics')->publish + 1;
        //store our post vars into variables
			//$title = 'Week ' . $count_posts;
        $content = analytics_cron_data();
        $post_type = 'website_analytics';

            $website_analytics_post = get_posts(array(
				'post_name__in' => [sanitize_title($title)],
				'post_status' => 'publish',
				'post_type' => $post_type
            ));
            
            if(empty($website_analytics_post)) {
        //arguments to be inserted with wp_insert_post
        $new_post = array(
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish',
            'post_type' => $post_type
        );

        //insert the the post into database
        $pid = wp_insert_post($new_post);
    }
		}
    }

    //set up next wp cron
    $next_event = strtotime('next Sunday 8 am');

    //clear old wp cron
    wp_clear_scheduled_hook('website_analytics_cron_event');
    
    //add new wp cron event
    wp_schedule_single_event($next_event, 'website_analytics_cron_event');
}

add_action('website_analytics_cron_event', 'weekly_analytics_cron');

//de-register wp cron event (*deactivation of plugin)
register_deactivation_hook(__FILE__, 'website_analytics_cron_deactivation');

//clear scheduled wp cron event
function website_analytics_cron_deactivation()
{
    wp_clear_scheduled_hook('website_analytics_cron_event');
}

//remove any website analytics before start date
function remove_website_analytics($start_date)
{
    $args = array(
        'post_type' => 'website_analytics',
        'posts_per_page' => -1,
        'date_query' => array(
            array(
                'after' => '1980-01-01',
                'before' => $start_date,
                'inclusive' => true,
            )
        )
    );
    $posts = new WP_Query($args);

    //delete the posts
    if ($posts->have_posts()) {
        while ($posts->have_posts()) {
            $posts->the_post();
            wp_delete_post(get_the_ID(), true);
        }
    }
}

//main plugin class file
class WP_Analytify_Simple
{

    //constructor
    function __construct()
    {

        if (!class_exists('Google_Client')) {
            require_once dirname(__FILE__) . '/lib/google-api-php-client-master/src/Google/Client.php';
            require_once dirname(__FILE__) . '/lib/google-api-php-client-master/src/Google/Service/Analytics.php';
        }

        $this->client = new Google_Client();
        $this->client->setApprovalPrompt('force');
        $this->client->setAccessType('offline');
        $this->client->setClientId('REDACTED_FOR_PUBLIC_REFERENCE');
        $this->client->setClientSecret('REDACTED_FOR_PUBLIC_REFERENCE');
        $this->client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');
        $this->client->setScopes('https://www.googleapis.com/auth/analytics');
        $this->client->setDeveloperKey('REDACTED_FOR_PUBLIC_REFERENCE');

        try {
            $this->service = new Google_Service_Analytics($this->client);
            $this->wpa_connect();
        } catch (Google_Service_Exception $e) { }

        add_action('admin_menu', array($this, 'wpa_add_menu'));
        add_action('admin_enqueue_scripts', array($this, 'wpa_styles'));

        register_activation_hook(__FILE__, array($this, 'wpa_install'));
        register_deactivation_hook(__FILE__, array($this, 'wpa_uninstall'));
    }

    //actions performed at loading of admin menu
    function wpa_add_menu()
    {

        add_menu_page('Westgate_Analytics', 'Website Analytics', 'manage_options', 'analytify-dashboard', array(
            __CLASS__,
            'wpa_page_file_path'
        ), plugins_url('images/wp-analytics-logo.png', __FILE__), '2.2.9');

        add_submenu_page('analytify-dashboard', 'Website Analytics' . ' Dashboard', ' Dashboard', 'manage_options', 'analytify-dashboard', array(
            __CLASS__,
            'wpa_page_file_path'
        ));

        add_submenu_page('analytify-dashboard', 'Website Analytics' . ' Settings', '<b style="color:#f9845b">Settings</b>', 'manage_options', 'analytify-settings', array(
            __CLASS__,
            'wpa_page_file_path'
        ));
    }

    //actions performed on loading of menu pages
    static function wpa_page_file_path()
    {
        $screen = get_current_screen();

        if (strpos($screen->base, 'analytify-settings') !== false) {
            include(dirname(__FILE__) . '/includes/analytify-settings.php');
        } else {
            include(dirname(__FILE__) . '/includes/analytify-dashboard.php');
        }
    }

    public function pa_settings_tabs($current = 'authentication')
    {
        $tabs = array(
			'site_authentication' => 'Social Media Magnet Authorization',
            'class_start_stop' => 'Practicum Settings',
            'authentication' => 'Google Analytics Authentication',
            'profile' => 'Google Analytics Profile',
            'mailchimp' => 'MailChimp Settings',
            'wa_weekly_analytics_post' => 'Create Weekly Analytics Post',
        );

        echo '<div class="left-area">';
        echo '<div id="icon-themes" class="icon32"><br></div>';
        echo '<h2 class="nav-tab-wrapper">';

        foreach ($tabs as $tab => $name) {
            $class = ($tab == $current) ? ' nav-tab-active' : '';
            echo "<a class='nav-tab$class' href='?page=analytify-settings&tab=$tab'>$name</a>";
        }

        echo '</h2>';
    }

    public function wpa_save_data($access_code)
    {
        update_option('access_code', $access_code);
        $this->wpa_connect();

        return true;
    }

    public function wpa_connect()
    {
        $access_token = get_option('access_token');

        if (!empty($access_token)) {
            $this->client->setAccessToken($access_token);
        } else {
            $authCode = get_option('access_code');

            if (empty($authCode))
                return false;

            try {
                $accessToken = $this->client->authenticate($authCode);
                //print_r($accessToken);
            } catch (Exception $e) {
                print_r($e->getMessage());
                return false;
            }

            if ($accessToken) {
                $this->client->setAccessToken($accessToken);
                update_option('access_token', $accessToken);
                return true;
            } else {
                return false;
            }
        }

        $this->token = json_decode($this->client->getAccessToken());
        return true;
    }

    //get profiles from user Google Analytics account profiles.
    public function pt_get_analytics_accounts()
    {
        try {

            if (get_option('access_token') != '') {
                $profiles = $this->service->management_profiles->listManagementProfiles("~all", "~all");
                return $profiles;
            } else {
                echo '<br /><p class="description">' . __('You must authenticate to access your web profiles.', 'wp-analytify') . '</p>';
            }
        } catch (Exception $e) {
            die('An error occurred: ' . $e->getMessage() . '\n');
        }
    }

    //grab data from Google Analytics to display on dashboard.
    public function pa_get_analytics_dashboard($metrics, $startDate, $endDate, $dimensions = false, $sort = false, $filter = false, $limit = false)
    {
        try {
            $this->service = new Google_Service_Analytics($this->client);
            $params = array();

            if ($dimensions) {
                $params['dimensions'] = $dimensions;
            }
            if ($sort) {
                $params['sort'] = $sort;
            }
            if ($filter) {
                $params['filters'] = $filter;
            }
            if ($limit) {
                $params['max-results'] = $limit;
            }

            $profile_id = get_option("pt_webprofile_dashboard");
            if (!$profile_id) {
                return false;
            }

            return $this->service->data_ga->get('ga:' . $profile_id, $startDate, $endDate, $metrics, $params);
        } catch (Google_Service_Exception $e) {
            //ahow error message only for logged in users.
            if (is_user_logged_in())
                echo $e->getMessage();
        }
    }

    //load stylesheet for plugin.
    public function wpa_styles($page)
    {
        wp_enqueue_style('wp-analytify-style', plugins_url('css/wp-analytify-style.css', __FILE__));
    }

    //actions performed on activation of plugin
    function wpa_install()
    { }

    //actions performed on de-activation of plugin
    function wpa_uninstall()
    { }

    function pa_validated_smm_access_token($smm_access_token = '') {
        if(empty($smm_access_token)) {
            $smm_access_token= get_option('smm_access_token');
        }
        
        $url = SMM_WEBSITE_URL.'wp-json/validate-student-access-token/';
        $request = wp_remote_get($url, array(
            'headers'     => array(
                                'Content-Type' => 'application/json; charset=utf-8', 
                                'SMM-AUTH-TOKEN' => $smm_access_token
                            ),
            'method'      => 'GET',
        ));
        if(wp_remote_retrieve_response_code($request) == 200){
            return true;
        } 
        
        return false;
    }

    function pa_update_smm_practicum_dates() {
        $url = SMM_WEBSITE_URL.'wp-json/get-student-practicum-dates/';
        $request = wp_remote_get($url, array(
            'headers'     => array(
                                'Content-Type' => 'application/json; charset=utf-8', 
                                'SMM-AUTH-TOKEN' => get_option('smm_access_token'),
                            ),
            'method'      => 'GET',
        ));
        
        if(wp_remote_retrieve_response_code($request) == 200){
            $response = json_decode(wp_remote_retrieve_body($request));
            update_option('class_start_date', $response->class_start_date);
            update_option('class_end_date', $response->class_end_date);
            return true;
        } 
        
        return false;
    }
}

new WP_Analytify_Simple();
