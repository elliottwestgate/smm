<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; //exit if accessed directly
}

//website analytics custom post type
function website_analytics_custom_post_init() {
    //set labels
    $labels = array(
        'name' => 'Weekly Analytics',
        'singular_name' => 'Analytics',
        'add_new' => 'Add New Analytics',
        'add_new_item' => 'Add New Analytics',
        'edit_item' => 'Edit Analytics',
        'new_item' => 'New Analytics',
        'all_items' => 'All Analytics',
        'view_item' => 'View Analytics',
        'search_items' => 'Search Analytics',
        'not_found' =>  'No Analytics Found',
        'not_found_in_trash' => 'No Analytics found in Trash', 
        'parent_item_colon' => '',
        'menu_name' => 'Weekly Analytics',
    );
	
	//set capabilities
	$capabilities = array(
		'create_posts' => 'do_not_allow', //uncomment (remove add new functionality)
    );
    
    //register custom post type
    $args = array(
        'labels' => $labels,
        'public' => true, //false (remove front-end viewing)
        'has_archive' => false,
		'show_ui' => true,
		'show_in_menu' => true,
        'hierarchical' => false,
        'rewrite' => array('slug' => 'website-analytics'),
        'query_var' => true,
		'menu_position' => 3,
        'menu_icon' => 'dashicons-randomize',
		'capabilities' => $capabilities,
		'map_meta_cap' => false, //true (false removes edit / delete functionality)
		'show_in_rest' => true,
        'supports' => array(
            'title',
            'editor',
        )
    );
    register_post_type( 'website_analytics', $args ); 

    if ( get_option('wa_flush_rewrite_rules') == false ) {
        flush_rewrite_rules();
        update_option('wa_flush_rewrite_rules', true);
    }
	
}
add_action( 'init', 'website_analytics_custom_post_init' );

?>