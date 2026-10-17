<?php 
// authenticate website for SMM
function authenticate_website(){
	$access_token = $_POST['access_token'];
	$site_url = stripslashes(site_url());
	$user_id = get_current_user_id();
		
	$url = 'http://smm.devdigdev.com/wp-json/validate-student-access-token/';
	$request = wp_remote_get($url, array(
		'headers'     => array(
							'Content-Type' => 'application/json; charset=utf-8', 
							'Authorization' => $access_token
						),
		'method'      => 'GET',
	));
	if(wp_remote_retrieve_response_code($request) == 200){
		update_user_meta( $user_id, 'access_token', $access_token );
		echo '<strong style="color: #09bc0b;">Valid Access Token</strong>';
	} 
	
	if(wp_remote_retrieve_response_code($request) == 401){
		echo '<strong style="color: #c70505;">Invalid Access Token</strong>';
	}
	
	die;
}
add_action("wp_ajax_authenticate_website", "authenticate_website");
add_action("wp_ajax_nopriv_authenticate_website", "authenticate_website");
?>