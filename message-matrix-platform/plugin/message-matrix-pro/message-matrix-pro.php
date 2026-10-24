<?php
/**
 * Plugin Name: Social Media Message Matrix Pro
 * Description: Standalone Brand foundations, Message Matrix and campaign planning. Planning beta; no AI or publishing connections.
 * Version: 0.1.0-beta.10
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Westgate Marketing
 * License: GPL-2.0-or-later
 */
namespace WGMM;
if(!defined('ABSPATH')){exit;}
require_once __DIR__.'/includes/domain.php';
require_once __DIR__.'/includes/store.php';
require_once __DIR__.'/includes/sources.php';
require_once __DIR__.'/includes/api.php';
require_once __DIR__.'/includes/admin.php';
register_activation_hook(__FILE__,[Store::class,'install']);
add_action('plugins_loaded',static function(){if(get_option('wgmm_schema')!=='2'){Store::install();}});
add_action('rest_api_init',[API::class,'register']);
Sources::boot();
add_action('admin_menu',[Admin::class,'menu']);
add_action('admin_post_wgmm_settings',[Admin::class,'save']);
add_action('admin_post_wgmm_create',[Admin::class,'create']);
add_action('admin_post_wgmm_admin_team',[Admin::class,'team']);
add_action('template_redirect',static function(){if(is_singular()){ $post=get_queried_object();if($post&&has_shortcode($post->post_content??'','message_matrix_pro')){if(!defined('DONOTCACHEPAGE')){define('DONOTCACHEPAGE',true);}nocache_headers();}}});
add_shortcode('message_matrix_pro',static function(){
    if(!is_user_logged_in()){return '<section class="wgmm-login"><h2>Message Matrix Pro</h2><p>Sign in to open your company workspace.</p><a href="'.esc_url(wp_login_url(get_permalink())).'">Sign in</a></section>';}
    wp_enqueue_style('wgmm-app',plugins_url('assets/app.css',__FILE__),[],'0.1.0-beta.10-'.filemtime(__DIR__.'/assets/app.css'));
    wp_enqueue_script('wgmm-app',plugins_url('assets/app.js',__FILE__),[],'0.1.0-beta.10-'.filemtime(__DIR__.'/assets/app.js'),true);
    wp_add_inline_script('wgmm-app','window.WGMM_CONFIG='.wp_json_encode(['root'=>esc_url_raw(rest_url('wgmm/v1/')),'nonce'=>wp_create_nonce('wp_rest'),'user'=>['id'=>get_current_user_id(),'name'=>wp_get_current_user()->display_name],'version'=>'0.1.0-beta.10','sourceAction'=>admin_url('admin-post.php'),'sourceNonce'=>wp_create_nonce('wgmm_source'),'returnUrl'=>get_permalink(),'logo'=>plugins_url('assets/message-matrix-pro-logo_2026-10-20_13-03_CT.png',__FILE__),'openCompany'=>absint($_GET['matrix_company']??0),'notice'=>sanitize_text_field(wp_unslash($_GET['matrix_notice']??''))],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';','before');
    return '<div id="wgmm-app"><p role="status">Opening your Message Matrix Pro workspace…</p></div><noscript>Enable JavaScript to use the planning workspace.</noscript>';
});
