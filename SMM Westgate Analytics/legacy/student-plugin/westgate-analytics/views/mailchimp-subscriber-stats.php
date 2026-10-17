<?php
//get subscribers count via the MailChimp API.
function mailchimp_get_subscribers_count( $api_key, $username, $list_id ) {
    $api_key   = $api_key;
    $username  = $username;
    $list_id   = $list_id;
    $dc        = substr($api_key,strpos($api_key,'-')+1);

	//request mailchimp api
	$url = "https://{$dc}.api.mailchimp.com/3.0/lists/{$list_id}";
	$request = wp_remote_get( $url, array(
		'headers' => array(
			'Authorization' => 'Basic ' . base64_encode( $username . ':' . $api_key ),
		),
	) );

	if ( wp_remote_retrieve_response_code( $request ) == 200 ) {
		$body = wp_remote_retrieve_body( $request );
		$body = json_decode( $body, true );
		$total = absint( $body['stats']['member_count'] );
	}
	
    echo '<td class="mailchimp_subscribers_stats">' . $total . '</td>';
} 
?>