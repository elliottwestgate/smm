<?php
//get campaign opens count via the MailChimp API.
function mailchimp_get_campaigns_data( $api_key, $username )
{
	$api_key   = $api_key;
    $username  = $username;
    $dc        = substr($api_key,strpos($api_key,'-')+1);

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

	if(!empty($campaign_ids)){
		$max_pages = 0;
		$current_page = 0;
		$per_page = 30;

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

	echo '<td class="mailchimp_emails_sent_stats">' . $mailchimp_campaigns_emails_sent . '</td>';
		echo '<td class="mailchimp_opens_stats">' . $mailchimp_campaigns_opens_total . '</td>';
		echo '<td class="mailchimp_click_through_stats">' . $mailchimp_click_through_total . '</td>';
}
?>