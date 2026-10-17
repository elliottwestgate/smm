<?php

//weekly analytics function for wp cron event
function analytics_cron_data()
{
    $wp_analytify = new WP_Analytify_Simple();

    //date values for google api query (*UTC time - new day @ 6pm)
    $end_date_val = strtotime("- 2 days");
    //get class start date
    $class_start_date = get_option("class_start_date");
    //format class start date for queries
    $orderdate = explode("-", $class_start_date);
    //query start / end dates
    $start_date = $orderdate[2] . '-' . $orderdate[0] . '-' . $orderdate[1];
    $end_date = date("Y-m-d", $end_date_val);

    $content = '<table class="website_analytics_dashboard">
		<tr class="website_analytics_dashboard heading">
			<th colspan="9">Google Analytics</th>
			<th colspan="4">MailChimp</th>
		</tr>
		<tr>
			<th>Users</th>
			<th>New Users</th>
			<th>Sessions</th>
			<th>Pageviews</th>
			<th>Direct</th>
			<th>Social</th>
			<th>Organic Search</th>
			<th>Referrals</th>
			<th>Goal Comp.</th>
			<th>Subscribers</th>
			<th>Emails Sent</th>
			<th>Opens</th>
			<th>Click-Through</th>
		</tr>
		<tr>';

    //users stats
    $users_stats = $wp_analytify->pa_get_analytics_dashboard('ga:users', $start_date, $end_date, false, false, false, false);
    if (!empty($users_stats["rows"])) {
        foreach ($users_stats["rows"] as $u_stats) {
            $content .= '<td class="users_stats">' . $u_stats[0] . '</td>';
        }
    } else {
        $content .= '<td class="users_stats">0</td>';
    }

    //new users stats
    $new_users_stats = $wp_analytify->pa_get_analytics_dashboard('ga:sessions', $start_date, $end_date, 'ga:userType', false, false, false);
    if (!empty($new_users_stats["rows"])) {
        $total = ''; //create temp variable
        $new_users_total = 0;
        foreach ($new_users_stats["rows"] as $n_stats) {
            if ($n_stats[0] === 'New Visitor') {
                $new_users_total += $n_stats[1];
            }
        }
        $content .= '<td class="new_users_stats">' . $new_users_total . '</td>';
    } else {
        $content .= '<td class="new_users_stats">0</td>';
    }

    //sessions stats
    $sessions_stats = $wp_analytify->pa_get_analytics_dashboard('ga:sessions', $start_date, $end_date, false, false, false, false);
    if (!empty($sessions_stats["rows"])) {
        foreach ($sessions_stats["rows"] as $s_stats) {
            $content .= '<td class="sessions_stats">' . $s_stats[0] . '</td>';
        }
    } else {
        $content .= '<td class="sessions_stats">0</td>';
    }

    //pageviews stats
    $pageviews_stats = $wp_analytify->pa_get_analytics_dashboard('ga:pageviews', $start_date, $end_date, false, false, false, false);
    if (!empty($pageviews_stats["rows"])) {
        foreach ($pageviews_stats["rows"] as $p_stats) {
            $content .= '<td class="pageviews_stats">' . $p_stats[0] . '</td>';
        }
    } else {
        $content .= '<td class="pageviews_stats">0</td>';
    }

    //direct referral stats
    $direct_referral_stats = $wp_analytify->pa_get_analytics_dashboard('ga:users', $start_date, $end_date, 'ga:channelGrouping', false, false, false);
    if (!empty($direct_referral_stats["rows"])) {
        $direct_total = 0;
        foreach ($direct_referral_stats["rows"] as $d_stats) {
            if ($d_stats[0] === 'Direct') {
                $direct_total = $d_stats[1];
            }
        }
        $content .= '<td class="direct_referral_stats">' . $direct_total . '</td>';
    } else {
        $content .= '<td class="direct_referral_stats">0</td>';
    }

    //social referral stats
    $social_referral_stats = $wp_analytify->pa_get_analytics_dashboard('ga:users', $start_date, $end_date, 'ga:channelGrouping', false, false, false);
    if (!empty($social_referral_stats["rows"])) {
        $social_total = 0;
        foreach ($social_referral_stats["rows"] as $s_stats) {
            if ($s_stats[0] === 'Social') {
                $social_total = $s_stats[1];
            }
        }
        $content .= '<td class="social_referral_stats">' . $social_total . '</td>';
    } else {
        $content .= '<td class="social_referral_stats">0</td>';
    }

    //organic referral stats
    $organic_referral_stats = $wp_analytify->pa_get_analytics_dashboard('ga:users', $start_date, $end_date, 'ga:channelGrouping', false, false, false);
    if (!empty($organic_referral_stats["rows"])) {
        $organic_total = 0;
        foreach ($organic_referral_stats["rows"] as $o_stats) {
            if ($o_stats[0] === 'Organic Search') {
                $organic_total = $o_stats[1];
            }
        }
        $content .= '<td class="organic_referral_stats">' . $organic_total . '</td>';
    } else {
        $content .= '<td class="organic_referral_stats">0</td>';
    }

    //referral referral stats
    $referral_referral_stats = $wp_analytify->pa_get_analytics_dashboard('ga:users', $start_date, $end_date, 'ga:channelGrouping', false, false, false);
    if (!empty($referral_referral_stats["rows"])) {
        $referral_total = 0;
        foreach ($referral_referral_stats["rows"] as $r_stats) {
            if ($r_stats[0] === 'Referral') {
                $referral_total = $r_stats[1];
            }
        }
        $content .= '<td class="referral_referral_stats">' . $referral_total . '</td>';
    } else {
        $content .= '<td class="referral_referral_stats">0</td>';
    }

    //goal completion stats
    $goal_completion_stats = $wp_analytify->pa_get_analytics_dashboard('ga:goalCompletionsAll', $start_date, $end_date, false, false, false, false);
    if (!empty($goal_completion_stats["rows"])) {
        foreach ($goal_completion_stats["rows"] as $g_stats) {
            $content .= '<td class="goal_completion_stats">' . $g_stats[0] . '</td>';
        }
    } else {
        $content .= '<td class="goal_completion_stats">0</td>';
    }

    //mailchimp subscriber stats
    function mailchimp_get_subscribers_count()
    {
        $api_key = get_option("mailchimp_api_key");
        $username = get_option("mailchimp_username");
        $list_id = get_option("mailchimp_list_id");
        $dc = substr($api_key, strpos($api_key, '-') + 1);

        //request mailchimp api
        $url = "https://{$dc}.api.mailchimp.com/3.0/lists/{$list_id}";
        $request = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $api_key),
            ),
            'timeout' => 10000
        ));

        if (wp_remote_retrieve_response_code($request) == 200) {
            $body = wp_remote_retrieve_body($request);
            $body = json_decode($body, true);
            $mailchimp_subscribers_total = absint($body['stats']['member_count']);
        }

        return '<td class="mailchimp_subscribers_stats">' . $mailchimp_subscribers_total . '</td>';
    }

    $content .= mailchimp_get_subscribers_count();

    function mailchimp_get_campaigns_data()
    {
        $api_key = get_option("mailchimp_api_key");
        $username = get_option("mailchimp_username");
        $dc = substr($api_key, strpos($api_key, '-') + 1);

        $mailchimp_list_id = get_option("mailchimp_list_id");

        //create campaign id array
        $campaign_ids = [];

        //add campaign id(s) to array
        $campaign_ids[] = get_option("mailchimp_campaign_id_1");
        $campaign_ids[] = get_option("mailchimp_campaign_id_2");
        $campaign_ids[] = get_option("mailchimp_campaign_id_3");
        $campaign_ids[] = get_option("mailchimp_campaign_id_4");
        $campaign_ids[] = get_option("mailchimp_campaign_id_5");
        $campaign_ids[] = get_option("mailchimp_campaign_id_6");
        $campaign_ids[] = get_option("mailchimp_campaign_id_7");
        $campaign_ids[] = get_option("mailchimp_campaign_id_8");
        $campaign_ids[] = get_option("mailchimp_campaign_id_9");
        $campaign_ids[] = get_option("mailchimp_campaign_id_10");
        $campaign_ids[] = get_option("mailchimp_campaign_id_11");
        $campaign_ids[] = get_option("mailchimp_campaign_id_12");

        //remove empty elements
        $campaign_ids = array_filter($campaign_ids);

        $mailchimp_campaigns_opens_total = 0;
        $mailchimp_click_through_total = 0;
        $mailchimp_campaigns_emails_sent = 0;

        $max_pages = 0;
		$current_page = 0;
		$per_page = 30;

        if(!empty($campaign_ids)){
            while($current_page <= $max_pages) {
                $url = "https://{$dc}.api.mailchimp.com/3.0/campaigns/?count=".$per_page."&status=sent&offset=".($per_page * $current_page);
                if(!empty($mailchimp_list_id)) {
                    $url .= '&list_id='.$mailchimp_list_id;
                }

                $request = wp_remote_get( $url, array(
                    'headers' => array(
                        'Authorization' => 'Basic ' . base64_encode( $username . ':' . $api_key ),
                    ),
                ) );
        
                if ( wp_remote_retrieve_response_code( $request ) == 200 ) {
                    $body = wp_remote_retrieve_body( $request );
                    $body = json_decode( $body, true );	
                    
                    // get emails sent count
                    foreach ( $body['campaigns'] as $data ) {
                        if(in_array($data['id'], $campaign_ids)){
                            $mailchimp_campaigns_emails_sent += 1;
                        }
                    }
                    
                    // get unique opens and clicks count
                    foreach ( $body['campaigns'] as $data ) {
                        if(in_array($data['id'], $campaign_ids)){
                            $mailchimp_campaigns_opens_total += absint( $data['report_summary']['unique_opens'] );
                            $mailchimp_click_through_total += absint( $data['report_summary']['clicks'] );
                        }
                    }

                    $current_page++;
				    $max_pages = ceil($body['total_items'] / $per_page) - 1;
                }
            }

        }

        return '<td class="mailchimp_emails_sent_stats">' . $mailchimp_campaigns_emails_sent . '</td>
			<td class="mailchimp_opens_stats">' . $mailchimp_campaigns_opens_total . '</td>
            <td class="mailchimp_click_through_stats">' . $mailchimp_click_through_total . '</td>';
        }

    $content .= mailchimp_get_campaigns_data();

    $content .= '</tr>
		</table>';
    return $content;
}

// Save MailChimp Campaing IDs
function analytics_campaigns_data()
{
    $api_key = get_option('mailchimp_api_key');
    $username = get_option('mailchimp_username');
    $class_start_date = get_option('class_start_date');
    $class_end_date = get_option('class_end_date');



    if (!empty($api_key) && !empty($username) && !empty($class_start_date) && !empty($class_end_date)) {
        $dc = substr($api_key, strpos($api_key, '-') + 1);
        
        $mailchimp_list_id = get_option('mailchimp_list_id');

        $class_start_date = str_replace('-', '/', $class_start_date);
        $class_end_date = str_replace('-', '/', $class_end_date);

        $campaign_start_date = date('c', strtotime($class_start_date));
        $campaign_end_date = date('c', strtotime('tomorrow', strtotime($class_end_date)) - 1);

        $url = "https://{$dc}.api.mailchimp.com/3.0/campaigns/?since_send_time=" . $campaign_start_date . "&before_send_time=" . $campaign_end_date ."&status=sent&count=30";
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
            $campaigns_list_size = sizeof($campaigns_list);

            $count = 1;
            $campaigns_updated = false;

            foreach ($campaigns_list as $campaign) {
                if ($campaign['status'] == 'sent') {
                    update_option('mailchimp_campaign_id_' . $count, $campaign['id']);
                    $campaigns_updated = true;
                    $count++;
                    if ($count > 12) {
                        break;
                    }
                }
            }

            for ($count; $count <= 12; $count++) {
                update_option('mailchimp_campaign_id_' . $count, '');
            }
        }
    } else {
        for ($count = 1; $count <= 12; $count++) {
            update_option('mailchimp_campaign_id_' . $count, '');
        }
    }
}
