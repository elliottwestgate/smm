<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; //exit if accessed directly
}

$wp_analytify = new WP_Analytify_Simple();

//date values for google api query (*UTC time - new day @ 6pm)
$end_date_val = strtotime("- 2 days");
//get today's date
$todays_date = date('Y-m-d');
//get class start date
$class_start_date = get_option( "class_start_date" );
//format class start date for queries
$orderdate = explode( "-", $class_start_date );
//query start / end dates
$start_date = $orderdate[2] . '-' . $orderdate[0] . '-' . $orderdate[1];
$end_date = date( "Y-m-d", $end_date_val);

//mailchimp credentials
$api_key = get_option ( "mailchimp_api_key" );
$username = get_option ( "mailchimp_username" );
$list_id = get_option ( "mailchimp_list_id" );

//check for start date
if ( empty ( $class_start_date ) ) {
	$class_start_date = 'empty';
}
else if ( ( !empty ( $class_start_date ) ) && ( $start_date === $todays_date ) ) {
	$class_start_date = 'same-date';
}
else if ( ( !empty ( $class_start_date ) ) && ( $start_date > $todays_date ) ) {
	$class_start_date = 'future-start-date';
}
else if ( ( !empty ( $class_start_date ) ) && ( $start_date < $todays_date ) ) {
	//push today's date back 2 days for offset
	$todays_date = date('Y-m-d', strtotime('-2 day'));
	if ( ( !empty ( $class_start_date ) ) && ( $start_date < $todays_date ) ) {
		$class_start_date = 'good-start-date';
	}
	else {
		$class_start_date = 'same-date';
	}
}

//no practicum start date
if ( $class_start_date === 'empty' )  {
	$update_message = '<div id="setting-error-settings_updated" class="updated settings-error below-h2"> 
						<p><strong>Practicum Start / End Date(s) need to be configured in <a href="./admin.php?page=analytify-settings">settings</a>.</strong></p></div>';
	echo $update_message;
}

//same practicum start date as current date
if ( $class_start_date === 'same-date' )  {
	$update_message = '<div id="setting-error-settings_updated" class="updated settings-error below-h2"> 
						<p><strong>Website Analytics software needs a few days for proper statistics.</strong></p></div>';
	echo $update_message;
} 

//practicum start date is a future date
if ( $class_start_date === 'future-start-date' )  {
	$update_message = '<div id="setting-error-settings_updated" class="updated settings-error below-h2"> 
						<p><strong>Practicum Start Date is a future date.</strong></p></div>';
	echo $update_message;
} 

?>
<div class="wrap">
	<h2 class='opt-title'><span id='icon-options-general' class='analytics-options'><img src="<?php echo plugins_url('westgate-analytics/images/wp-analytics-logo.png');?>" alt=""></span>
		<?php echo __( 'Website Analytics Dashboard', 'wp-analytify' ); ?>
	</h2>
	<?php
	
	$acces_token  = get_option( "access_code" );
	if( $acces_token ) {
  
	?>
	<div id="col-container">
		<div class="metabox-holder">
			<div class="postbox" style="width:100%;">
				<div id="main-sortables" class="meta-box-sortables ui-sortable">
					<div class="postbox ">
						<div class="handlediv" title="Click to toggle"><br />
					</div>
					<h3 class="hndle">
					<span>
						<?php 
						echo _e('Complete Statistics Starting From ', 'wp-analytify'); 
						echo _e(date("jS F, Y", strtotime($start_date))); 
						echo _e(' to ', 'wp-analytify'); 
						echo _e(date("jS F, Y", strtotime($end_date))); 
						?>
					</span>
					</h3>
					<div class="inside">
						<table class="website_analytics_dashboard">
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
						<?php

						if( $class_start_date === 'good-start-date' ) {
							echo '<tr>';
							//users stats
							$users_stats = $wp_analytify->pa_get_analytics_dashboard( 'ga:users', $start_date, $end_date, false, false, false, false );
							if ( isset( $users_stats->totalsForAllResults )) {
								include ROOT_PATH . '/views/users-stats.php'; 
								pa_include_users($wp_analytify,$users_stats);
							} else {
								echo "<td>-</td>";
							}
							
							//new users stats
							$new_users_stats = $wp_analytify->pa_get_analytics_dashboard( 'ga:sessions', $start_date, $end_date, 'ga:userType', false, false, false );
							if ( isset( $new_users_stats->totalsForAllResults )) {
								include ROOT_PATH . '/views/new-users-stats.php'; 
								pa_include_new_users($wp_analytify,$new_users_stats);
							} else {
								echo "<td>-</td>";
							}
							
							//sessions stats
							$sessions_stats = $wp_analytify->pa_get_analytics_dashboard( 'ga:sessions', $start_date, $end_date, false, false, false, false );
							if ( isset( $sessions_stats->totalsForAllResults )) {
								include ROOT_PATH . '/views/sessions-stats.php'; 
								pa_include_sessions($wp_analytify,$sessions_stats);
							} else {
								echo "<td>-</td>";
							}
						  
							//pageviews stats
							$pageviews_stats = $wp_analytify->pa_get_analytics_dashboard( 'ga:pageviews', $start_date, $end_date, false, false, false, false );
							if ( isset( $pageviews_stats->totalsForAllResults )) {
								include ROOT_PATH . '/views/pageviews-stats.php'; 
								pa_include_pageviews($wp_analytify,$pageviews_stats);
							} else {
								echo "<td>-</td>";
							}
							
							//direct referral stats
							$direct_referral_stats = $wp_analytify->pa_get_analytics_dashboard( 'ga:users', $start_date, $end_date, 'ga:channelGrouping', false, false, false );
							if ( isset( $direct_referral_stats->totalsForAllResults ) ) {
								include ROOT_PATH . '/views/direct-referral-stats.php'; 
								pa_include_direct_referral( $wp_analytify,$direct_referral_stats );
							} else {
								echo "<td>-</td>";
							}
							
							//social referral stats
							$social_referral_stats = $wp_analytify->pa_get_analytics_dashboard( 'ga:users', $start_date, $end_date, 'ga:channelGrouping', false, false, false );
							if ( isset( $social_referral_stats->totalsForAllResults ) ) {
								include ROOT_PATH . '/views/social-referral-stats.php'; 
								pa_include_social_referral( $wp_analytify,$social_referral_stats );
							} else {
								echo "<td>-</td>";
							}
							
							//organic referral stats
							$organic_referral_stats = $wp_analytify->pa_get_analytics_dashboard( 'ga:users', $start_date, $end_date, 'ga:channelGrouping', false, false, false );
							if ( isset( $organic_referral_stats->totalsForAllResults ) ) {
								include ROOT_PATH . '/views/organic-referral-stats.php'; 
								pa_include_organic_referral( $wp_analytify,$organic_referral_stats );
							} else {
								echo "<td>-</td>";
							}
							
							//referral referral stats
							$referral_referral_stats = $wp_analytify->pa_get_analytics_dashboard( 'ga:users', $start_date, $end_date, 'ga:channelGrouping', false, false, false );
							if ( isset( $referral_referral_stats->totalsForAllResults ) ) {
								include ROOT_PATH . '/views/referral-referral-stats.php'; 
								pa_include_referral_referral( $wp_analytify,$referral_referral_stats );
							} else {
								echo "<td>-</td>";
							}
							
							//goal completion stats
							$goal_completion_stats = $wp_analytify->pa_get_analytics_dashboard( 'ga:goalCompletionsAll', $start_date, $end_date, false, false, false, false );
							if ( isset( $goal_completion_stats->totalsForAllResults ) ) {
								include ROOT_PATH . '/views/goal-completion-stats.php'; 
								pa_include_goal_completion( $wp_analytify,$goal_completion_stats );
							} else {
								echo "<td>-</td>";
							}
							
							/* For testing purposes (gets total values for pageviews including source / medium)
							//total referral stats
							$total_referral_stats = $wp_analytify->pa_get_analytics_dashboard( 'ga:pageviews', $start_date, $end_date, 'ga:medium, ga:source', false, false, false );
							if ( isset( $total_referral_stats->totalsForAllResults ) ) {
								include ROOT_PATH . '/views/total-referral-stats.php'; 
								pa_include_total_referral( $wp_analytify,$total_referral_stats );
							}
							*/
							
							//mailchimp subscriber stats
							include ROOT_PATH . '/views/mailchimp-subscriber-stats.php'; 
							mailchimp_get_subscribers_count( $api_key, $username, $list_id );

							//mailchimp open stats and click through data
							include ROOT_PATH . '/views/mailchimp-campaigns-data.php'; 
							mailchimp_get_campaigns_data( $api_key, $username );
							
							echo '</tr>';
						}
						else {
							echo '<tr><td colspan="12">No website data</td></tr>';
						}
						?>
						</table>				
					</div><!-- .inside -->
				</div>
			</div>
			</div>
		</div><!-- .metabox-holder -->
	</div><!-- #col-container -->
<?php 
	} 
	else {
		print(_e( 'You must be authenticated to see the Google Analytics dashboard.', 'wp-analytify' ));
	}
?>
</div>