<?php
namespace WGMM;

/** Company-private PDF inputs. No public attachment URLs or automatic AI processing. */
final class Sources {
    const TYPES=['strategy_canvas'=>'Strategy Canvas','brand_dyno'=>'Brand Dyno'];
    const MAX_BYTES=20971520;
    public static function boot(): void {foreach(['upload','download','import'] as $op){add_action('admin_post_wgmm_source_'.$op,[__CLASS__,$op]);}}
    public static function install(): void {
        global $wpdb;$charset=$wpdb->get_charset_collate();
        dbDelta('CREATE TABLE '.Store::table('sources')." (id varchar(80) NOT NULL, workspace_id bigint unsigned NOT NULL, kind varchar(30) NOT NULL, filename varchar(255) NOT NULL, encrypted longtext NOT NULL, sha256 varchar(64) NOT NULL, byte_size bigint unsigned NOT NULL, uploaded_at datetime NOT NULL, uploaded_by bigint unsigned NOT NULL, PRIMARY KEY  (id), KEY workspace_id (workspace_id)) ENGINE=InnoDB $charset;");
    }
    public static function validate_pdf(string $name,string $bytes): void {
        if(strtolower(pathinfo($name,PATHINFO_EXTENSION))!=='pdf'||strlen($bytes)<20||strlen($bytes)>self::MAX_BYTES||!str_starts_with($bytes,'%PDF-')||!str_contains(substr($bytes,-2048),'%%EOF')||preg_match('~/Encrypt\b~',$bytes)){throw new \InvalidArgumentException('Choose an unencrypted PDF report up to 20 MB.');}
    }
    public static function seal(string $bytes,int $workspace): string {
        if(!function_exists('openssl_encrypt')){throw new \RuntimeException('Private document encryption is unavailable on this server.',500);}
        $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($bytes,'aes-256-gcm',hash('sha256',wp_salt('auth').'|wgmm-sources',true),OPENSSL_RAW_DATA,$iv,$tag,'wgmm:'.$workspace);
        if($cipher===false){throw new \RuntimeException('The document could not be encrypted.',500);}return base64_encode($iv.$tag.$cipher);
    }
    public static function unseal(string $value,int $workspace,string $sha): string {
        $raw=base64_decode($value,true);if($raw===false||strlen($raw)<29){throw new \RuntimeException('Document verification failed.',500);}
        $bytes=openssl_decrypt(substr($raw,28),'aes-256-gcm',hash('sha256',wp_salt('auth').'|wgmm-sources',true),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),'wgmm:'.$workspace);
        if($bytes===false||!hash_equals($sha,hash('sha256',$bytes))){throw new \RuntimeException('Document verification failed.',500);}return $bytes;
    }
    public static function links(array $row): array {
        $out=[];foreach($row['document']['foundation_sources'] as $m){$out[$m['kind']]=array_merge($m,['download_url'=>add_query_arg(['action'=>'wgmm_source_download','matrix_company'=>$row['id'],'source_id'=>$m['id'],'_wpnonce'=>wp_create_nonce('wgmm_source')],admin_url('admin-post.php'))]);}return $out;
    }
    /** Optional Pro Analytics adapter: access is rechecked when the user copies a source. */
    public static function available(): array {
        if(!class_exists('\WGPP_Company')||!class_exists('\WGPP_Documents')){return [];}$out=[];
        foreach(\WGPP_Company::companies(get_current_user_id()) as $c){$records=get_post_meta($c['id'],\WGPP_Documents::META,true);foreach((array)$records as $kind=>$r){if(isset(self::TYPES[$kind],$r['metadata']['filename'],$r['encrypted'])){$out[]=['company_id'=>(int)$c['id'],'company'=>$c['name'],'kind'=>$kind,'filename'=>$r['metadata']['filename']];}}}return $out;
    }
    public static function save(int $id,int $revision,string $kind,string $name,string $bytes,string $origin): void {
        global $wpdb;$row=Store::access($id,'plan');if($row['revision']!==$revision){throw new \RuntimeException('The workspace changed. Reload before attaching a source.',409);}Domain::choice($kind,array_keys(self::TYPES));self::validate_pdf($name,$bytes);
        $total=(int)$wpdb->get_var($wpdb->prepare('SELECT COALESCE(SUM(byte_size),0) FROM '.Store::table('sources').' WHERE workspace_id=%d',$id));if($total+strlen($bytes)>104857600){throw new \InvalidArgumentException('This company has reached its 100 MB source-history allowance. Contact the administrator.');}
        $record='source_'.wp_generate_uuid4();$filename=sanitize_file_name($name);$sha=hash('sha256',$bytes);$uploaded=gmdate('Y-m-d H:i:s');$encrypted=self::seal($bytes,$id);
        $d=$row['document'];$d['foundation_sources']=array_values(array_filter($d['foundation_sources'],static fn($s)=>$s['kind']!==$kind));$d['foundation_sources'][]=['id'=>$record,'kind'=>$kind,'filename'=>$filename,'sha256'=>$sha,'uploaded_at'=>$uploaded.' UTC','origin'=>$origin];
        $wpdb->query('START TRANSACTION');try{if(!$wpdb->insert(Store::table('sources'),['id'=>$record,'workspace_id'=>$id,'kind'=>$kind,'filename'=>$filename,'encrypted'=>$encrypted,'sha256'=>$sha,'byte_size'=>strlen($bytes),'uploaded_at'=>$uploaded,'uploaded_by'=>get_current_user_id()])){throw new \RuntimeException('The source document could not be saved.',500);}Store::cas($row,$d,'');$wpdb->query('COMMIT');}catch(\Throwable $e){$wpdb->query('ROLLBACK');throw $e;}
    }
    private static function inputs(): array {
        if(!get_current_user_id()){throw new \RuntimeException('Sign in to manage company documents.',403);}check_admin_referer('wgmm_source');$id=absint($_POST['matrix_company']??0);Store::access($id,'plan');$kind=Domain::choice($_POST['kind']??'',array_keys(self::TYPES));return [$id,absint($_POST['revision']??0),$kind];
    }
    private static function redirect(int $id,string $message): void {
        $fallback=home_url('/');$url=wp_validate_redirect(esc_url_raw(wp_unslash($_POST['return_url']??'')),$fallback);wp_safe_redirect(add_query_arg(['matrix_company'=>$id,'matrix_notice'=>$message],$url));exit;
    }
    private static function error(\Throwable $e): void {$code=in_array($e->getCode(),[403,409],true)?$e->getCode():400;self::redirect(absint($_POST['matrix_company']??0),$e->getCode()===500?'Document save failed. Contact the administrator.':$e->getMessage());}
    public static function upload(): void {
        try{[$id,$rev,$kind]=self::inputs();$f=$_FILES['foundation_pdf']??[];if(($f['error']??1)!==UPLOAD_ERR_OK||!is_uploaded_file($f['tmp_name']??'')||($f['size']??0)>self::MAX_BYTES){throw new \InvalidArgumentException('Select a PDF report up to 20 MB. Your host’s upload limit also applies.');}$bytes=file_get_contents($f['tmp_name']);if($bytes===false){throw new \RuntimeException('The upload could not be read.',500);}self::save($id,$rev,$kind,$f['name'],$bytes,'Uploaded in Message Matrix Pro');self::redirect($id,self::TYPES[$kind].' attached. Review its implications below; the Brand draft has not been generated or approved.');}catch(\Throwable $e){self::error($e);}
    }
    public static function import(): void {
        try{[$id,$rev,$kind]=self::inputs();$source=absint($_POST['analytics_company']??0);if(!class_exists('\WGPP_Company')||!class_exists('\WGPP_Documents')||!\WGPP_Company::for_user($source,get_current_user_id())){throw new \RuntimeException('Access to the source Pro Analytics company is required.',403);}$r=get_post_meta($source,\WGPP_Documents::META,true)[$kind]??null;if(!$r){throw new \InvalidArgumentException('That company document is no longer available.');}$c=\WGPP_Company::for_user($source,get_current_user_id());$bytes=\WGPP_Documents::bytes($source,$kind,$r);self::save($id,$rev,$kind,$r['metadata']['filename'],$bytes,Domain::text('Pro Analytics · '.$c['name'],250));self::redirect($id,self::TYPES[$kind].' copied from the selected Pro Analytics company. Review the Brand implications below.');}catch(\Throwable $e){self::error($e);}
    }
    public static function download(): void {
        global $wpdb;try{check_admin_referer('wgmm_source');$id=absint($_GET['matrix_company']??0);Store::access($id);$source=Domain::id($_GET['source_id']??'');$r=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.Store::table('sources').' WHERE id=%s AND workspace_id=%d',$source,$id),ARRAY_A);if(!$r){throw new \RuntimeException('Source document not found.',404);}$bytes=self::unseal($r['encrypted'],$id,$r['sha256']);nocache_headers();header('Content-Type: application/pdf');header('X-Content-Type-Options: nosniff');header('Content-Disposition: attachment; filename="'.sanitize_file_name($r['filename']).'"');header('Content-Length: '.strlen($bytes));echo $bytes;exit;}catch(\Throwable $e){wp_die(esc_html($e->getCode()===500?'Document could not be read.':$e->getMessage()),'Document access',['response'=>403]);}
    }
}
