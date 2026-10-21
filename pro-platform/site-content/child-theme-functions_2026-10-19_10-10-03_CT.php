<?php

/**
 * Child theme version.
 *
 * @since 1.0.0
 *
 * @var string
 */
define( 'PRIMER_CHILD_VERSION', '1000' );

/**
 * Load custom js script
 */
function add_custom_js_script() {
    global $WishListMemberInstance;

    wp_enqueue_script( 'password-strength-meter' );
	
	wp_register_script('custom_js_script', home_url() . '/wp-content/themes/westgate-media/js/custom_js.js', array( 'jquery' ), '5.5.2');
    wp_enqueue_script('custom_js_script');

    $wpm_current_user = wp_get_current_user();
	$levels = $WishListMemberInstance->get_membership_levels($wpm_current_user->ID, null, null, null, true);
	$wpm_levels = $WishListMemberInstance->get_option('wpm_levels');
	$clevels = count($levels);
	$wishlist_level = '';

	for ($i = 0; $i < $clevels; $i++) {
		$wishlist_level .= $wpm_levels[$levels[$i]]['name'].',';
	}

	$wishlist_levels = rtrim($wishlist_level, ',');
    
    wp_localize_script( 'custom_js_script', 'custom_js_script_object', 
        array( 
            'wishlist_levels' => $wishlist_levels
        ) 
    );

}  
add_action( 'wp_enqueue_scripts', 'add_custom_js_script' );

/**
 * Load custom js script for admin
 */
function admin_custom_script_enqueue($hook) {
    // Only add to the edit.php admin page.
    // See WP docs.
    if ('user-edit.php' !== $hook) {
        return;
    }
    wp_register_script('custom-admin-script', home_url() . '/wp-content/themes/westgate-media/js/custom-admin.js', array( 'jquery' ));
	wp_enqueue_script('custom-admin-script');
}
add_action('admin_enqueue_scripts', 'admin_custom_script_enqueue');

/**
 * Load custom form script 
 */
function add_input_mask_script() {
	wp_register_script('input_mask_script', '/wp-content/themes/westgate-media/js/jquery.inputmask.bundle.js', array( 'jquery' ));
	wp_enqueue_script('input_mask_script');
}  
add_action( 'wp_enqueue_scripts', 'add_input_mask_script' );

/**
 * Load custom form script
 */
function add_form_script() {
	wp_register_script('custom_form_script', home_url() . '/wp-content/themes/westgate-media/js/dumbFormState.js', array( 'jquery' ));
	wp_enqueue_script('custom_form_script');
}  
add_action( 'wp_enqueue_scripts', 'add_form_script' );

/**
 * Move some elements around.
 *
 * @action template_redirect
 * @since  1.0.0
 */
function ascension_move_elements() {

	remove_action( 'primer_header',                'primer_add_hero',               7 );
	remove_action( 'primer_after_header',          'primer_add_primary_navigation', 11 );
	remove_action( 'primer_after_header',          'primer_add_page_title',         12 );
	remove_action( 'primer_before_header_wrapper', 'primer_video_header',           5 );

	add_action( 'primer_after_header', 'primer_add_hero',               7 );
	add_action( 'primer_header',       'primer_add_primary_navigation', 11 );
	add_action( 'primer_pre_hero',     'primer_video_header',           5 );

	if ( ! is_front_page() || ! is_active_sidebar( 'hero' ) ) {

		add_action( 'primer_hero', 'primer_add_page_title', 12 );

	}

}
add_action( 'template_redirect', 'ascension_move_elements' );

/**
 * Set hero image target element.
 *
 * @filter primer_hero_image_selector
 * @since  1.0.0
 *
 * @return string
 */
function ascension_hero_image_selector() {

	return '.hero';

}
add_filter( 'primer_hero_image_selector', 'ascension_hero_image_selector' );

/**
 * Set custom logo args.
 *
 * @filter primer_custom_logo_args
 * @since  1.0.0
 *
 * @param  array $args
 *
 * @return array
 */
function ascension_custom_logo_args( $args ) {

	$args['width']  = 325;
	$args['height'] = 70;

	return $args;

}
add_filter( 'primer_custom_logo_args', 'ascension_custom_logo_args' );

/**
 * Set sidebars.
 *
 * @filter primer_sidebars
 * @since  1.0.0
 *
 * @param  array $sidebars
 *
 * @return array
 */
function ascension_sidebars( $sidebars ) {

	$sidebars['footer-4'] = array(
		'name'          => esc_html__( 'Footer 4', 'ascension' ),
		'description'   => esc_html__( 'This sidebar is the fourth column of the footer widget area.', 'ascension' ),
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget'  => '</aside>',
		'before_title'  => '<h4 class="widget-title">',
		'after_title'   => '</h4>',
	);

	return $sidebars;

}
add_filter( 'primer_sidebars', 'ascension_sidebars' );

/**
 * Create secondary footer widget areas
 */
function secondary_footer_sidebars( $sidebars ) {

	$sidebars['secondary-footer-1'] = array(
		'name'          => esc_html__( 'Secondary Footer 1', 'ascension' ),
		'description'   => esc_html__( 'This sidebar is the first column of the secondary footer widget area.', 'ascension' ),
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget'  => '</aside>',
		'before_title'  => '<h4 class="widget-title">',
		'after_title'   => '</h4>',
	);
	
	$sidebars['secondary-footer-2'] = array(
		'name'          => esc_html__( 'Secondary Footer 2', 'ascension' ),
		'description'   => esc_html__( 'This sidebar is the second column of the secondary footer widget area.', 'ascension' ),
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget'  => '</aside>',
		'before_title'  => '<h4 class="widget-title">',
		'after_title'   => '</h4>',
	);
	
	$sidebars['secondary-footer-3'] = array(
		'name'          => esc_html__( 'Secondary Footer 3', 'ascension' ),
		'description'   => esc_html__( 'This sidebar is the third column of the secondary footer widget area.', 'ascension' ),
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget'  => '</aside>',
		'before_title'  => '<h4 class="widget-title">',
		'after_title'   => '</h4>',
	);
	
	$sidebars['secondary-footer-4'] = array(
		'name'          => esc_html__( 'Secondary Footer 4', 'ascension' ),
		'description'   => esc_html__( 'This sidebar is the fourth column of the secondary footer widget area.', 'ascension' ),
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget'  => '</aside>',
		'before_title'  => '<h4 class="widget-title">',
		'after_title'   => '</h4>',
	);

	return $sidebars;

}
add_filter( 'primer_sidebars', 'secondary_footer_sidebars' );

/**
 * Set fonts.
 *
 * @filter primer_fonts
 * @since  1.0.0
 *
 * @param  array $fonts
 *
 * @return array
 */
function ascension_fonts( $fonts ) {

	$fonts[] = 'Open Sans';

	return $fonts;

}
add_filter( 'primer_fonts', 'ascension_fonts' );

/**
 * Set font types.
 *
 * @filter primer_font_types
 * @since  1.0.0
 *
 * @param  array $font_types
 *
 * @return array
 */
function ascension_font_types( $font_types ) {

	$overrides = array(
		'site_title_font' => array(
			'default' => 'Open Sans',
		),
		'navigation_font' => array(
			'default' => 'Open Sans',
		),
		'heading_font' => array(
			'default' => 'Open Sans',
		),
		'primary_font' => array(
			'default' => 'Open Sans',
		),
		'secondary_font' => array(
			'default' => 'Open Sans',
		),
	);

	return primer_array_replace_recursive( $font_types, $overrides );

}
add_filter( 'primer_font_types', 'ascension_font_types' );

/**
 * Set colors.
 *
 * @filter primer_colors
 * @since  1.0.0
 *
 * @param  array $colors
 *
 * @return array
 */
function ascension_colors( $colors ) {

	unset(
		$colors['content_background_color'],
		$colors['footer_widget_content_background_color']
	);

	$overrides = array(
		/**
		 * Text colors
		 */
		'header_textcolor' => array(
			'default'  => '#194f6e',
		),
		'tagline_text_color' => array(
			'default'  => '#686868',
		),
		'hero_text_color' => array(
			'default' => '#ffffff',
		),
		'menu_text_color' => array(
			'default' => '#194f6e',
		),
		'heading_text_color' => array(
			'default' => '#353535',
		),
		'primary_text_color' => array(
			'default' => '#252525',
		),
		'secondary_text_color' => array(
			'default' => '#686868',
		),
		'footer_widget_heading_text_color' => array(
			'default' => '#353535',
		),
		'footer_widget_text_color' => array(
			'default' => '#252525',
		),
		'footer_menu_text_color' => array(
			'default' => '#686868',
		),
		'footer_text_color' => array(
			'default' => '#686868',
		),
		/**
		 * Link / Button colors
		 */
		'link_color' => array(
			'default'  => '#00bfff',
		),
		'button_color' => array(
			'default'  => '#00bfff',
		),
		'button_text_color' => array(
			'default'  => '#ffffff',
		),
		/**
		 * Background colors
		 */
		'background_color' => array(
			'default' => '#ffffff',
		),
		'hero_background_color' => array(
			'default' => '#252525',
		),
		'menu_background_color' => array(
			'default' => '#ffffff',
			'css'     => array(
				'.site-header' => array(
					'background-color' => '%1$s',
				),
			),
		),
		'footer_widget_background_color' => array(
			'default' => '#f5f5f5',
		),
		'footer_background_color' => array(
			'default' => '#ffffff',
		),
	);

	return primer_array_replace_recursive( $colors, $overrides );

}
add_filter( 'primer_colors', 'ascension_colors' );

/**
 * Set color schemes.
 *
 * @filter primer_color_schemes
 * @since  1.0.0
 *
 * @param  array $color_schemes
 *
 * @return array
 */
function ascension_color_schemes( $color_schemes ) {

	$overrides = array(
		'blush' => array(
			'colors' => array(
				'header_textcolor' => $color_schemes['blush']['base'],
				'menu_text_color'  => $color_schemes['blush']['base'],
				'link_color'       => $color_schemes['blush']['base'],
				'button_color'     => $color_schemes['blush']['base'],
			),
		),
		'bronze' => array(
			'colors' => array(
				'header_textcolor' => $color_schemes['bronze']['base'],
				'menu_text_color'  => $color_schemes['bronze']['base'],
				'link_color'       => $color_schemes['bronze']['base'],
				'button_color'     => $color_schemes['bronze']['base'],
			),
		),
		'canary' => array(
			'colors' => array(
				'header_textcolor' => $color_schemes['canary']['base'],
				'menu_text_color'  => $color_schemes['canary']['base'],
				'link_color'       => $color_schemes['canary']['base'],
				'button_color'     => $color_schemes['canary']['base'],
			),
		),
		'cool' => array(
			'colors' => array(
				'header_textcolor' => $color_schemes['cool']['base'],
				'menu_text_color'  => $color_schemes['cool']['base'],
				'link_color'       => $color_schemes['cool']['base'],
				'button_color'     => $color_schemes['cool']['base'],
			),
		),
		'dark' => array(
			'colors' => array(
				// Text
				'header_textcolor'                 => '#ffffff',
				'tagline_text_color'               => '#999999',
				'menu_text_color'                  => '#ffffff',
				'heading_text_color'               => '#ffffff',
				'primary_text_color'               => '#e5e5e5',
				'secondary_text_color'             => '#c1c1c1',
				'footer_widget_heading_text_color' => '#ffffff',
				'footer_widget_text_color'         => '#ffffff',
				// Backgrounds
				'background_color'               => '#222222',
				'hero_background_color'          => '#282828',
				'menu_background_color'          => '#333333',
				'footer_widget_background_color' => '#282828',
				'footer_background_color'        => '#222222',
			),
		),
		'iguana' => array(
			'colors' => array(
				'header_textcolor' => $color_schemes['iguana']['base'],
				'menu_text_color'  => $color_schemes['iguana']['base'],
				'link_color'       => $color_schemes['iguana']['base'],
				'button_color'     => $color_schemes['iguana']['base'],
			),
		),
		'muted' => array(
			'colors' => array(
				// Text
				'header_textcolor'       => '#ffffff',
				'tagline_text_color'     => '#ffffff',
				'menu_text_color'        => '#ffffff',
				'heading_text_color'     => '#4f5875',
				'primary_text_color'     => '#4f5875',
				'secondary_text_color'   => '#888c99',
				'footer_menu_text_color' => $color_schemes['muted']['base'],
				'footer_text_color'      => '#4f5875',
				// Links & Buttons
				'link_color'   => $color_schemes['muted']['base'],
				'button_color' => $color_schemes['muted']['base'],
				// Backgrounds
				'background_color'               => '#ffffff',
				'hero_background_color'          => '#4f5875',
				'menu_background_color'          => '#5a6175',
				'footer_widget_background_color' => '#d5d6e0',
				'footer_background_color'        => '#ffffff',
			),
		),
		'plum' => array(
			'colors' => array(
				'header_textcolor' => $color_schemes['plum']['base'],
				'menu_text_color'  => $color_schemes['plum']['base'],
				'link_color'       => $color_schemes['plum']['base'],
				'button_color'     => $color_schemes['plum']['base'],
			),
		),
		'rose' => array(
			'colors' => array(
				'header_textcolor' => $color_schemes['rose']['base'],
				'menu_text_color'  => $color_schemes['rose']['base'],
				'link_color'       => $color_schemes['rose']['base'],
				'button_color'     => $color_schemes['rose']['base'],
			),
		),
		'tangerine' => array(
			'colors' => array(
				'header_textcolor' => $color_schemes['tangerine']['base'],
				'menu_text_color'  => $color_schemes['tangerine']['base'],
				'link_color'       => $color_schemes['tangerine']['base'],
				'button_color'     => $color_schemes['tangerine']['base'],
			),
		),
		'turquoise' => array(
			'colors' => array(
				'header_textcolor' => $color_schemes['turquoise']['base'],
				'menu_text_color'  => $color_schemes['turquoise']['base'],
				'link_color'       => $color_schemes['turquoise']['base'],
				'button_color'     => $color_schemes['turquoise']['base'],
			),
		),
	);

	return primer_array_replace_recursive( $color_schemes, $overrides );

}
add_filter( 'primer_color_schemes', 'ascension_color_schemes' );

add_action('after_setup_theme', 'remove_admin_bar');
 
function remove_admin_bar() {
if (!current_user_can('administrator') && !is_admin()) {
  show_admin_bar(false);
}
}
function wishlist_custom_data_attribute( $classes ) {
	global $WishListMemberInstance;
	$wpm_current_user = wp_get_current_user();

	$levels = $WishListMemberInstance->get_membership_levels($wpm_current_user->ID, null, null, null, true);
	$wpm_levels = $WishListMemberInstance->get_option('wpm_levels');
	$clevels = count($levels);
	$wishlist_level = '';

	for ($i = 0; $i < $clevels; $i++) {
		$wishlist_level .= $wpm_levels[$levels[$i]]['name'].',';
	}

	$wishlist_levels = rtrim($wishlist_level, ',');

	$newattr = '" data-wishlist-level="'.$wishlist_levels;
    $classes[] = htmlspecialchars_decode($newattr);

    return $classes;
}
add_filter( 'body_class','wishlist_custom_data_attribute', 999 );

function add_enctype_to_tml_profile_form() {
    if ( $profile_form = tml_get_form( 'profile' ) ) {
        $profile_form->add_attribute( 'enctype', 'multipart/form-data' );
    }
}
add_action( 'init', 'add_enctype_to_tml_profile_form' );



if(class_exists('WP_User_Avatar')) {
	add_action('show_user_profile', array('WP_User_Avatar', 'wpua_action_show_user_profile'));
	add_action('edit_user_profile', array('WP_User_Avatar', 'wpua_action_show_user_profile'));
}

/** Remove only the WPBakery frontend-editor shortcut from the admin bar. */
add_action( 'vc_after_init', static function () {
    if ( function_exists( 'vc_frontend_editor' ) ) {
        remove_action( 'admin_bar_menu', [ vc_frontend_editor(), 'adminBarEditLink' ], 1000 );
    }
} );

