<?php 
add_action('rest_api_init', function ($server){
    $server->register_route('update-student-practicum-dates', '/update-student-practicum-dates', array(
        'methods'  => 'POST',
        'callback' => 'update_student_practicum_dates',
        'permission_callback' => 'validate_student_access_token_permission_middleware'
    ));
});

function validate_student_access_token_permission_middleware( WP_REST_Request $request ) {    
    $access_token = $request->get_header("SMM-AUTH-TOKEN");
    if(empty($access_token)) {
        return false;
    }

    $smm_access_token = get_option('smm_access_token');

    if(!empty($smm_access_token) && md5($smm_access_token) == $access_token) {
        return true;
    }

    return false;

}

function update_student_practicum_dates(WP_REST_Request $request) {

    if(empty($request->get_param('start_date')) && empty($request->get_param('end_date'))) {
        return new WP_REST_Response('Practicum Start Date & Practicum End Date is required.', 422);
    }
    else if(empty($request->get_param('start_date'))) {
        return new WP_REST_Response('Practicum Start Date is required.', 422);
    }
    else if(empty($request->get_param('end_date'))) {
        return new WP_REST_Response('Practicum End Date is required.', 422);
    }

    $start_date_array = explode('-',$request->get_param('start_date'));

    $start_date_month = isset($start_date_array[0]) ? $start_date_array[0] : null;
    $start_date_date = isset($start_date_array[1]) ? $start_date_array[1] : null;
    $start_date_year = isset($start_date_array[2]) ? $start_date_array[2] : null;

    $end_date_array = explode('-',$request->get_param('end_date'));

    $end_date_month = isset($end_date_array[0]) ? $end_date_array[0] : null;
    $end_date_date = isset($start_date_array[1]) ? $end_date_array[1] : null;
    $end_date_year = isset($end_date_array[2]) ? $end_date_array[2] : null;

    if(!checkdate($start_date_month, $start_date_date, $start_date_year)) {
        return new WP_REST_Response('Practicum Start Date is invalid.', 422);
    } elseif(!checkdate($end_date_month, $end_date_date, $end_date_year)) {
        return new WP_REST_Response('Practicum End Date is invalid.', 422);
    }

    update_option('class_start_date', $request->get_param('start_date'));
    update_option('class_end_date', $request->get_param('end_date'));

    return new WP_REST_Response('Practicum Start Date & Practicum End Date is updated successfully.', 200);

}

add_action('rest_api_init', function ($server){
    $server->register_route('validate-access-token', '/validate-access-token', array(
        'methods'  => 'GET',
        'callback' => 'validate_access_token'
    ));
});

function validate_access_token( WP_REST_Request $request ) {    
    $access_token = $request->get_header("SMM-AUTH-TOKEN");
    
    if(empty($access_token)) {
        return false;
    }

    $smm_access_token = get_option('smm_access_token');


    if(!empty($smm_access_token) && md5($smm_access_token) == $access_token) {
        return new WP_REST_Response('Valid Access Token', 200);
    }

    return new WP_REST_Response('Invalid Access Token', 401);

}

?>