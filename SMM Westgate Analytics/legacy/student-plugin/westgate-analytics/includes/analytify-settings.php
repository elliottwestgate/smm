<?php
if (!defined('ABSPATH')) {
    exit; //exit if accessed directly
}

$wp_analytify = new WP_Analytify_Simple();

if (!function_exists('curl_init')) {
    esc_html_e('This plugin requires the CURL PHP extension');
    return false;
}

if (!function_exists('json_decode')) {
    esc_html_e('This plugin requires the JSON PHP extension');
    return false;
}

if (!function_exists('http_build_query')) {
    esc_html_e('This plugin requires http_build_query()');
    return false;
}

$url = http_build_query(
    array(
        'next' => admin_url('admin.php?page=analytify-settings'),
        'scope' => 'https://www.googleapis.com/auth/analytics',
        'response_type' => 'code',
        'redirect_uri' => 'urn:ietf:wg:oauth:2.0:oob',
        'client_id' => 'REDACTED_FOR_PUBLIC_REFERENCE'
    )
);

//save access code
if (isset($_POST["save_code"]) and isset($_POST["access_code"])) {
    if ($wp_analytify->wpa_save_data($_POST["access_code"])) {
        $update_message = '<div id="setting-error-settings_updated" class="updated settings-error below-h2">
							<p><strong>Access code saved.</strong></p></div>';
    }
}

//clear authorization and other data
if (isset($_POST["clear"])) {
    delete_option('access_code');
    delete_option('access_token');
    $update_message = '<div id="setting-error-settings_updated" class="updated settings-error below-h2"> 
							<p><strong>Authentication cleared login again.</strong></p></div>';
}

//saving profile
if (isset($_POST['save_profile'])) {
    update_option('pt_webprofile_dashboard', $_POST['webprofile_dashboard']);

    $update_message = '<div id="setting-error-settings_updated" class="updated settings-error below-h2"> 
							<p><strong>Your Google Analytics profile saved.</strong></p></div>';
}

//saving mailchimp settings
if (isset($_POST['save_mailchimp'])) {
    $update_message = '<div id="setting-error-settings_updated" class="updated settings-error below-h2"> 
							<p><strong>MailChimp settings saved.</strong></p></div>';
}

//create weekly analytics post settings
$wa_secret_key = isset($_POST['wa_secret_key']) ? $_POST['wa_secret_key'] : '';
if (isset($_POST['wa_weekly_analytics_post']) && !empty($wa_secret_key)) {

    if($wa_secret_key == '6BKoCI8dsj'){
        do_action('website_analytics_cron_event');
    } else {
        $update_message = '<div id="setting-error-settings_updated" class="notice notice-error below-h2"> 
                                    <p><strong>Please enter valid secret key to create Weekly Analytics post.</strong></p></div>';
    }
}

if (isset($_POST['save_student_authorization_token'])) {
    $smm_access_token = $_POST['smm_access_token'];
	if($wp_analytify->pa_validated_smm_access_token($smm_access_token)){
        $validated_access_token = true;
        update_option('smm_access_token', $smm_access_token);
        $wp_analytify->pa_update_smm_practicum_dates();
		$update_message = '<div id="setting-error-settings_updated" class="updated settings-error below-h2"><p><strong>Social Media Magnet Access Token updated successfully.</strong></p></div>';
    }  
    else {
        $validated_access_token = false;
        $update_message = '<div id="setting-error-settings_updated" class="notice notice-error below-h2"><p><strong>Social Media Magnet Access Token is invalid.</strong></p></div>';
	}

} else {
    if(!$wp_analytify->pa_validated_smm_access_token()) {
        $validated_access_token = false;
        $update_message = '<div id="setting-error-settings_updated" class="notice notice-error below-h2"><p><strong>Social Media Magnet Access Token is invalid. <a href="?page=analytify-settings">Click here</a> to add valid Social Media Magnet Access Token.</strong></p></div>';
    }else{
        $validated_access_token = true;
    }
}

if (isset($_GET['tab']) && $_GET['tab'] == 'wa_weekly_analytics_post') {
    $class_start_date = get_option('class_start_date');
    $class_end_date = get_option('class_end_date');

    if (empty($class_start_date) && empty($class_end_date)) {
        $update_message = '<div id="setting-error-settings_updated" class="notice notice-error below-h2"> 
                            <p><strong>Practicum Start Date or End Date is missing.</strong></p></div>';
    }
}

//saving mailchimp api key
if (isset($_POST['mailchimp_api_key'])) {
    update_option('mailchimp_api_key', $_POST['mailchimp_api_key']);
}

//saving mailchimp username
if (isset($_POST['mailchimp_username'])) {
    update_option('mailchimp_username', $_POST['mailchimp_username']);
}

//saving mailchimp list id
if (isset($_POST['mailchimp_list_id'])) {
    update_option('mailchimp_list_id', $_POST['mailchimp_list_id']);
}

//Fetch Mailchimp campaigns id
if (isset($_POST['update_campaigns_list'])) {
    $api_key = get_option('mailchimp_api_key');
    $username = get_option('mailchimp_username');
    $class_start_date = get_option('class_start_date');
    $class_end_date = get_option('class_end_date');
    $mailchimp_list_id = get_option('mailchimp_list_id');

    if (empty($api_key)) {
        $update_message = '<div id="setting-error-settings_updated" class="notice notice-error below-h2"> 
							<p><strong>Please add your Mailchimp API key.</strong></p></div>';
    }

    if (empty($username)) {
        $update_message .= '<div id="setting-error-settings_updated" class="notice notice-error below-h2"> 
							<p><strong>Please add your Mailchimp Username.</strong></p></div>';
    }

    if (empty($class_start_date)) {
        $update_message .= '<div id="setting-error-settings_updated" class="notice notice-error below-h2"> 
							<p><strong>Your Practicum Start Date is missing.</strong></p></div>';
    }

    if (empty($class_end_date)) {
        $update_message .= '<div id="setting-error-settings_updated" class="notice notice-error below-h2"> 
							<p><strong>Your Practicum End Date is missing.</strong></p></div>';
    }

    if (!empty($api_key) && !empty($username) && !empty($class_start_date) && !empty($class_end_date)) {
        $dc = substr($api_key, strpos($api_key, '-') + 1);

        $class_start_date = str_replace('-', '/', $class_start_date);
        $class_end_date = str_replace('-', '/', $class_end_date);

        $campaign_start_date = date('c', strtotime($class_start_date));
        $campaign_end_date = date('c', strtotime('tomorrow', strtotime($class_end_date)) - 1);

        $url = "https://{$dc}.api.mailchimp.com/3.0/campaigns/?since_send_time=" . $campaign_start_date . "&before_send_time=" . $campaign_end_date . "&status=sent&count=30";
        if(!empty($mailchimp_list_id)) {
            $url .= '&list_id='.$mailchimp_list_id;
        }
        
        $request = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $api_key),
            ),
            'timeout' => 10000
        ));

        if (wp_remote_retrieve_response_code($request) == 200) {
            $body = wp_remote_retrieve_body($request);
            $body = json_decode($body, true);
            $campaigns_list = $body['campaigns'];

            $sorted_campaigns_list = array();

            foreach ($campaigns_list as $campaigns_data) {
                if ($campaigns_data['status'] == 'sent') {
                    $sorted_campaigns_list[$campaigns_data['create_time']] = $campaigns_data['id'];
                }
            }

            ksort($sorted_campaigns_list);

            $count = 1;
            $campaigns_updated = false;

            foreach ($sorted_campaigns_list as $k => $v) {
                update_option('mailchimp_campaign_id_' . $count, $v);
                $campaigns_updated = true;
                $count++;
                if ($count > 12) {
                    break;
                }
            }

            if ($campaigns_updated) {
                $update_message = '<div id="setting-error-settings_updated" class="updated settings-error below-h2"> 
							<p><strong>MailChimp campaigns list updated.</strong></p></div>';
            }

            for ($count; $count <= 12; $count++) {
                update_option('mailchimp_campaign_id_' . $count, '');
            }
        }
    }

    if (empty($api_key) || empty($username) || empty($class_start_date) || empty($class_end_date)) {
        for ($count = 1; $count <= 12; $count++) {
            update_option('mailchimp_campaign_id_' . $count, '');
        }
    }
}

?>

<div class="wrap">
    <h2 class='opt-title'><span id='icon-options-general' class='analytics-options'><img src="<?php echo plugins_url('westgate-analytics/images/wp-analytics-logo.png'); ?>" alt=""></span>
        <?php echo __('Website Analytics Plugin Settings', 'wp-analytify'); ?>
    </h2>

    <?php
    if (isset($update_message))
        echo $update_message;

    if (isset($_GET['tab']))
        $wp_analytify->pa_settings_tabs($_GET['tab']);
    else
        $wp_analytify->pa_settings_tabs('site_authentication');

    if (isset($_GET['tab']))
        $tab = $_GET['tab'];
    else
        $tab = 'site_authentication';
		
		
    //site authentication tab section
    if ($tab == 'site_authentication') {
        ?>

    <form action="<?php echo str_replace('%7E', '~', $_SERVER['REQUEST_URI']); ?>" method="post" name="settings_form" id="settings_form">
        <table width="1004" class="form-table">
            <tbody>
               
                <tr>
                    <th width="115"><h2><?php esc_html_e('Website Authorization:') ?></h2>
                   
                     </th>
                  
                </tr>
                
                <tr>
                    <th><?php esc_html_e('Access Token:') ?>
                    <?php
			  if(!empty($validated_access_token)){
				echo "<span style='color: green;font-size: 25px;' class='dashicons dashicons-yes'></span>";
		       }else{
			     echo "<span style='color: red;font-size: 25px;' class='dashicons dashicons-no'></span>";
		       }
			 ?> </th>
                    <td>
                        <input type="password" name="smm_access_token" class="smm_access_token" value="<?php echo get_option('smm_access_token'); ?>" style="width:450px;" required/>
                        <span toggle="#password-field" class="dashicons dashicons-visibility toggle-password"></span>
                    </td>
                </tr>
                <tr>
                <th></th>
                <td colspan="2" style="padding: 10px;"><span style="font-size:1em;font-weight:normal;font-style:italic;">Obtain the Access Token from your Member Profile page at <a href="<?php echo SMM_WEBSITE_URL; ?>member-profile/"> <?php echo SMM_WEBSITE_URL; ?>member-profile/</a> > Class Info = Access Token. When Access Token is properly connected, a Green Check Mark will appear to indicate the connection is complete and active between your Website and Your Member Profile. A Red X means the connection is NOT complete and Must be resolved. If a new Access Token is Regenerated it will need to be updated below. They MUST match to insure proper connection.</span></td>
                </tr>
                <tr>
                    <th></th>
                    <td>
                        <p class="submit">
                            <input type="submit" class="button-primary" value="Save Changes" name="save_student_authorization_token" />
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
    </form>
<script>
jQuery("body").on('click', '.toggle-password', function() {
    jQuery(this).toggleClass("dashicons-visibility dashicons-hidden");
    var input = jQuery(".smm_access_token");
    if (input.attr("type") === "password") {
        input.attr("type", "text");
    } else {
        input.attr("type", "password");
    }

});
</script>
    <?php
	}

    //authentication tab section
    if ($tab == 'authentication') {
        ?>

    <form action="<?php echo str_replace('%7E', '~', $_SERVER['REQUEST_URI']); ?>" method="post" name="settings_form" id="settings_form">
        <table width="1004" class="form-table">
            <tbody>
                <?php if (get_option('access_token')) { ?>
                <tr>
                    <p>Do you want to re-authenticate? Click reset button and get your new access code.<p>
                </tr>
                <tr>
                    <th><?php esc_html_e('Clear Authentication', 'wp-analytify'); ?></th>
                    <td><input type="submit" class="button-primary" value="Reset" name="clear" /></td>
                </tr>
                <?php } else {
                        ?>
                <tr>
                    <th width="115"><?php esc_html_e('Authentication:') ?></th>
                    <td width="877">
                        <a target="_blank" href="javascript:void(0);" onclick="window.open('https://accounts.google.com/o/oauth2/auth?<?php echo $url ?>', 'activate', 'width=700,height=500,toolbar=0,menubar=0,location=0,status=1,scrollbars=1,resizable=1,left=0,top=0');">Click here to Authenticate</a>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Your Access Code:') ?> </th>
                    <td>
                        <input type="text" name="access_code" value="" style="width:450px;" />
                        
                    </td>
                </tr>
                <tr>
                    <th></th>
                    <td>
                        <p class="submit">
                            <input type="submit" class="button-primary" value="Save Changes" name="save_code" />
                        </p>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </form>
    <?php
    } //endif
    //choose profiles for dashboard and posts at front/back.
    if ($tab == 'profile') {
        $profiles = $wp_analytify->pt_get_analytics_accounts();
        if (isset($profiles)) {
            ?>
    <p><?php esc_html_e('Select Google Analytics profile for dashboard data.', 'wp-analytify'); ?></p>

    <form action="<?php echo str_replace('%7E', '~', $_SERVER['REQUEST_URI']); ?>" method="post">
        <table width="1004" class="form-table">
            <tbody>
                <tr>
                    <th width="115"><?php esc_html_e('Dashboard :', 'wp-analytify'); ?></th>
                    <td width="877">
                        <select name='webprofile_dashboard' id='webprofile-dashboard'>
                            <option value="">Select Profile</option>
                            <?php foreach ($profiles->items as $profile) { ?>
                            <option value="<?php echo $profile['id']; ?>" <?php selected($profile['id'], get_option('pt_webprofile_dashboard')); ?>>
                                <?php echo $profile['websiteUrl']; ?> - <?php echo $profile['name']; ?>
                            </option>
                            <?php } ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th></th>
                    <td>
                        <p class="submit">
                            <input type="submit" name="save_profile" value="Save Changes" class="button-primary">
                        </p>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </form>
    <?php
    }

    //mailchimp settings
    if ($tab == 'mailchimp') {
        ?>

    <p><?php esc_html_e('Select MailChimp settings for dashboard data.', 'wp-analytify'); ?></p>

    <form action="<?php echo str_replace('%7E', '~', $_SERVER['REQUEST_URI']); ?>" method="post">
        <table width="1004" class="form-table">
            <tbody>
                <tr>
                    <th width="115"><?php esc_html_e('API Key:') ?> </th>
                    <td>
                        <input type="text" name="mailchimp_api_key" value="<?php echo get_option('mailchimp_api_key'); ?>" style="width:450px;" />
                    </td>
                </tr>
                <tr>
                    <th width="115"><?php esc_html_e('Username:') ?> </th>
                    <td>
                        <input type="text" name="mailchimp_username" value="<?php echo get_option('mailchimp_username'); ?>" style="width:450px;" />
                    </td>
                </tr>
                <tr>
                    <th width="115"><?php esc_html_e('List ID:') ?> </th>
                    <td>
                        <input type="text" name="mailchimp_list_id" value="<?php echo get_option('mailchimp_list_id'); ?>" style="width:450px;" />
                    </td>
                </tr>
                <tr>
                    <th colspan="2" style="font-weight:normal;"><?php esc_html_e('See your Email Campaign ID(s) given below:') ?> </th>
                </tr>
                <tr>
                    <th width="115"><?php esc_html_e('Email #1: Campaign ID:') ?> </th>
                    <td>
                        <input type="text" name="mailchimp_campaign_id_1" value="<?php echo get_option('mailchimp_campaign_id_1'); ?>" style="width:450px;" readonly />
                    </td>
                </tr>
                <tr>
                    <th width="115"><?php esc_html_e('Email #2: Campaign ID:') ?> </th>
                    <td>
                        <input type="text" name="mailchimp_campaign_id_2" value="<?php echo get_option('mailchimp_campaign_id_2'); ?>" style="width:450px;" readonly />
                    </td>
                </tr>
                <tr>
                    <th width="115"><?php esc_html_e('Email #3: Campaign ID:') ?> </th>
                    <td>
                        <input type="text" name="mailchimp_campaign_id_3" value="<?php echo get_option('mailchimp_campaign_id_3'); ?>" style="width:450px;" readonly />
                    </td>
                </tr>
                <tr>
                    <th width="115"><?php esc_html_e('Email #4: Campaign ID:') ?> </th>
                    <td>
                        <input type="text" name="mailchimp_campaign_id_4" value="<?php echo get_option('mailchimp_campaign_id_4'); ?>" style="width:450px;" readonly />
                    </td>
                </tr>
                <tr>
                    <th width="115"><?php esc_html_e('Email #5: Campaign ID:') ?> </th>
                    <td>
                        <input type="text" name="mailchimp_campaign_id_5" value="<?php echo get_option('mailchimp_campaign_id_5'); ?>" style="width:450px;" readonly />
                    </td>
                </tr>
                <tr>
                    <th width="115"><?php esc_html_e('Email #6: Campaign ID:') ?> </th>
                    <td>
                        <input type="text" name="mailchimp_campaign_id_6" value="<?php echo get_option('mailchimp_campaign_id_6'); ?>" style="width:450px;" readonly />
                    </td>
                </tr>
                <tr>
                    <th width="115"><?php esc_html_e('Email #7: Campaign ID:') ?> </th>
                    <td>
                        <input type="text" name="mailchimp_campaign_id_7" value="<?php echo get_option('mailchimp_campaign_id_7'); ?>" style="width:450px;" readonly />
                    </td>
                </tr>
                <tr>
                    <th width="115"><?php esc_html_e('Email #8: Campaign ID:') ?> </th>
                    <td>
                        <input type="text" name="mailchimp_campaign_id_8" value="<?php echo get_option('mailchimp_campaign_id_8'); ?>" style="width:450px;" readonly />
                    </td>
                </tr>
                <tr>
                    <th width="115"><?php esc_html_e('Email #9: Campaign ID:') ?> </th>
                    <td>
                        <input type="text" name="mailchimp_campaign_id_9" value="<?php echo get_option('mailchimp_campaign_id_9'); ?>" style="width:450px;" readonly />
                    </td>
                </tr>
                <tr>
                    <th width="115"><?php esc_html_e('Email #10: Campaign ID:') ?> </th>
                    <td>
                        <input type="text" name="mailchimp_campaign_id_10" value="<?php echo get_option('mailchimp_campaign_id_10'); ?>" style="width:450px;" readonly />
                    </td>
                </tr>
                <tr>
                    <th width="115"><?php esc_html_e('Email #11: Campaign ID:') ?> </th>
                    <td>
                        <input type="text" name="mailchimp_campaign_id_11" value="<?php echo get_option('mailchimp_campaign_id_11'); ?>" style="width:450px;" readonly />
                    </td>
                </tr>
                <tr>
                    <th width="115"><?php esc_html_e('Email #12: Campaign ID:') ?> </th>
                    <td>
                        <input type="text" name="mailchimp_campaign_id_12" value="<?php echo get_option('mailchimp_campaign_id_12'); ?>" style="width:450px;" readonly />
                    </td>
                </tr>
                <tr>
                    <th></th>
                    <td>
                        <p class="submit">
                            <input type="submit" name="save_mailchimp" value="Save Changes" class="button-primary"> &nbsp; <input type="submit" name="update_campaigns_list" value="Update Mailchimp Campaigns List" class="button-primary">
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
    </form>
    <?php
    }

    //cron settings
    if ($tab == 'wa_weekly_analytics_post') {
        
        $acces_token  = get_option( "access_code" );

	    if( !empty($acces_token) && !empty($class_end_date) && !empty($class_end_date) ) {
        ?>
            <form action="<?php echo str_replace('%7E', '~', $_SERVER['REQUEST_URI']); ?>" method="post">
                <table width="1004" class="form-table">
                    <tbody>
                        <tr>
                            <td>
                                <strong><?php esc_html_e('Please enter secret key to create Weekly Analytics post.') ?></strong>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <input type="text" name="wa_secret_key" value="" style="width:350px;" required>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <input type="submit" name="wa_weekly_analytics_post" value="Create Weekly Analytics Post" class="button-primary">
                            </td>
                        </tr>
                    </tbody>
                </table>
            </form>
        <?php } else {
            echo '<br /><p class="description">' . __('You must be authenticated to create Weekly Analytics post.', 'wp-analytify') . '</p>';
        }
    }

    //class start stop date
    if ($tab == 'class_start_stop') {
        ?>
    <form action="<?php echo str_replace('%7E', '~', $_SERVER['REQUEST_URI']); ?>" method="post">
        <table width="1004" class="form-table">
            <tbody>
                <tr>
                    <th width="115"><?php esc_html_e('Practicum Start Date:') ?> </th>
                    <td>
                        <input type="text" name="class_start_date" value="<?php echo get_option('class_start_date'); ?>" style="width:100px;" readonly/>
                    </td>
                </tr>
                <tr>
                    <th width="115"><?php esc_html_e('Practicum End Date:') ?> </th>
                    <td>
                        <input type="text" name="class_end_date" value="<?php echo get_option('class_end_date'); ?>" style="width:100px;" readonly/>
                    </td>
                </tr>
            </tbody>
        </table>
    </form>
    <script>
        jQuery(document).ready(function($) {
            $('#class_start_date').datepicker({
                dateFormat: 'mm-dd-yy'
            });
            $('#class_end_date').datepicker({
                dateFormat: 'mm-dd-yy'
            });
        });
    </script>
    <?php } ?>

</div>
</div>
</div>
<style>

    .nav-tab-wrapper .nav-tab {
        padding: 5px 5px;
    }
</style>