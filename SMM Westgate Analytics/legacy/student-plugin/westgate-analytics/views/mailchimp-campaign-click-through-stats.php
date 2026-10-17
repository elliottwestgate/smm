<?php
//get campaign click through count via the MailChimp API.
function mailchimp_get_campaign_click_through_count( $api_key, $username ) {
    $api_key   = $api_key;
    $username  = $username;
    $dc        = substr($api_key,strpos($api_key,'-')+1);
	
	//create campaign id array
	$campaign_ids = [];

	//add campaign id(s) to array
	$campaign_ids[] = get_option ( "mailchimp_campaign_id_1" );
	$campaign_ids[] = get_option ( "mailchimp_campaign_id_2" );
	$campaign_ids[] = get_option ( "mailchimp_campaign_id_3" );
	$campaign_ids[] = get_option ( "mailchimp_campaign_id_4" );
	$campaign_ids[] = get_option ( "mailchimp_campaign_id_5" );
	$campaign_ids[] = get_option ( "mailchimp_campaign_id_6" );
	$campaign_ids[] = get_option ( "mailchimp_campaign_id_7" );
	$campaign_ids[] = get_option ( "mailchimp_campaign_id_8" );
	$campaign_ids[] = get_option ( "mailchimp_campaign_id_9" );
	$campaign_ids[] = get_option ( "mailchimp_campaign_id_10" );
	$campaign_ids[] = get_option ( "mailchimp_campaign_id_11" );
	$campaign_ids[] = get_option ( "mailchimp_campaign_id_12" );
	
	//remove empty elements
	$campaign_ids = array_filter( $campaign_ids );
	//remove any duplicates
	$campaign_ids = array_unique( $campaign_ids );
	
	//set total variable
	$total = 0;

	if(!empty($campaign_ids)){
		$url = "https://{$dc}.api.mailchimp.com/3.0/campaigns/?count=30";

		$request = wp_remote_get( $url, array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $username . ':' . $api_key ),
			),
		) );

		if ( wp_remote_retrieve_response_code( $request ) == 200 ) {
			$body = wp_remote_retrieve_body( $request );
			$body = json_decode( $body, true );	
			
			foreach ( $body['campaigns'] as $data ) {
				if(in_array($data['id'], $campaign_ids)){
					$total += absint( $data['report_summary']['clicks'] );
				}
			}
		}
	}
	
    echo '<td class="mailchimp_click_through_stats">' . $total . '</td>';
} 
?>