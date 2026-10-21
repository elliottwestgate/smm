<?php
if (!defined('ABSPATH')) { exit; }

/** Administration index; company reporting is opened explicitly on the front end. */
final class WGPP_Admin {
    public static function render() {
        if (!current_user_can('manage_options')) { return ''; }
        $ids=get_posts(['post_type'=>WGPP_Company::TYPE,'post_status'=>'private','posts_per_page'=>-1,'fields'=>'ids','orderby'=>'title','order'=>'ASC']);
        ob_start();
        echo '<div class="wrap"><h1>SMM Pro Analytics</h1><h2>Company administration</h2><p>Choose a company to open its Report, saved Reviews, or Account management. This overview does not load analytics or generate Reviews.</p>';
        echo '<p><a class="button button-primary" href="'.esc_url(home_url('/pro-workspace/')).'">Add or set up a company</a> <a class="button" href="'.esc_url(admin_url('admin.php?page=wgpp-pro-review')).'">Review Settings</a></p>';
        echo '<table class="widefat striped"><caption class="screen-reader-text">Pro Analytics companies</caption><thead><tr><th scope="col">Company</th><th scope="col">Owner</th><th scope="col">Members</th><th scope="col">Access</th><th scope="col">Actions</th></tr></thead><tbody>';
        $count=0;
        foreach($ids as $id) {
            $post=get_post((int)$id);
            if(!$post || $post->post_type!==WGPP_Company::TYPE || $post->post_status!=='private'){continue;}
            $count++;$members=get_post_meta($post->ID,'_wgpp_members',true);$members=is_array($members)?$members:[];$owner='Not assigned';$seats=0;
            foreach($members as $member=>$role){if(!in_array($role,['owner','analyst','viewer'],true)){continue;}$seats++;if($role==='owner'){$person=get_userdata((int)$member);if($person){$owner=$person->display_name;}}}
            $access=WGPP_Company::for_user($post->ID,get_current_user_id());
            echo '<tr><th scope="row">'.esc_html($post->post_title).'</th><td>'.esc_html($owner).'</td><td>'.(int)$seats.'</td><td>'.esc_html($access?ucfirst($access['role']):'Not assigned').'</td><td>';
            if($access){$base=add_query_arg('workspace',(int)$post->ID,home_url('/pro-analytics/'));foreach(['report'=>'Open Report','review'=>'Open Reviews','account'=>'Manage Account'] as $tab=>$label){echo '<a class="button" style="margin:0 6px 4px 0" href="'.esc_url($base.'#'.$tab).'">'.esc_html($label).'</a>';}}
            else{echo 'Company membership is required to open its workspace.';}
            echo '</td></tr>';
        }
        if(!$count){echo '<tr><td colspan="5">No Pro Analytics companies have been created.</td></tr>';}
        echo '</tbody></table></div>';return ob_get_clean();
    }
}
