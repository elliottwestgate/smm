<?php
namespace WGMM;
final class API {
    public static function permission($r){return is_user_logged_in()&&wp_verify_nonce($r->get_header('X-WP-Nonce'),'wp_rest')?true:new \WP_Error('wgmm_auth','Sign in again before accessing company data.',['status'=>401]);}
    public static function register(): void {
        $route=static function($path,$methods,$cb){register_rest_route('wgmm/v1',$path,['methods'=>$methods,'permission_callback'=>[__CLASS__,'permission'],'callback'=>static function($r)use($cb){try{$result=$cb($r);$response=rest_ensure_response($result);$response->header('Cache-Control','private, no-store, max-age=0');return $response;}catch(\Throwable $e){$status=$e instanceof \InvalidArgumentException?400:(in_array($e->getCode(),[401,403,404,409,500],true)?$e->getCode():500);return new \WP_Error('wgmm_request',$status===500?'The request could not complete. Contact the administrator; your save has not been confirmed.':$e->getMessage(),['status'=>$status]);}}]);};
        $route('/workspaces','GET',static fn()=>['workspaces'=>Store::listing(),'can_create'=>Store::membership_active(get_current_user_id())]);
        $route('/workspaces','POST',static function($r){$p=self::body($r);$id=Store::create(Domain::required($p['title']??'',200),get_current_user_id(),false);return Store::payload(Store::access($id));});
        $base='/workspaces/(?P<id>\d+)';
        $route($base,'GET',static fn($r)=>Store::payload(Store::access((int)$r['id'])));
        $route($base.'/document','PUT',static function($r){$p=self::body($r);return Store::save((int)$r['id'],self::revision($p),$p['document']??[]);});
        $route($base.'/approve-foundation','POST',static fn($r)=>Store::approve_foundation((int)$r['id'],self::revision(self::body($r))));
        $route($base.'/assets/(?P<record>[a-zA-Z0-9_-]+)/status','POST',static function($r){$p=self::body($r);$op=Domain::choice($p['operation']??'',['approve','reopen','request_changes']);return Store::asset_status((int)$r['id'],self::revision($p),Domain::id($r['record']),$op,Domain::text($p['feedback']??'',5000));});
        $route($base.'/campaigns/(?P<record>[a-zA-Z0-9_-]+)/foundation','POST',static fn($r)=>Store::pin_campaign((int)$r['id'],self::revision(self::body($r)),Domain::id($r['record'])));
        $route($base.'/team','POST',static function($r){$p=self::body($r);$email=sanitize_email($p['email']??'');if(!is_email($email)){throw new \InvalidArgumentException('Enter a valid existing user email.');}return Store::team((int)$r['id'],self::revision($p),$email,$p['role']??'viewer',($p['operation']??'save')==='remove');});
        $route($base.'/versions/(?P<record>[a-zA-Z0-9_-]+)','GET',static fn($r)=>Store::version((int)$r['id'],Domain::id($r['record'])));
    }
    private static function body($r): array {if(strlen($r->get_body())>1200000){throw new \InvalidArgumentException('The request is too large.');}$p=$r->get_json_params();if(!is_array($p)){throw new \InvalidArgumentException('Send a valid planning document.');}return $p;}
    private static function revision(array $p): int {if(!isset($p['revision'])||!is_int($p['revision'])||$p['revision']<1){throw new \InvalidArgumentException('Reload the workspace before saving.');}return $p['revision'];}
}
