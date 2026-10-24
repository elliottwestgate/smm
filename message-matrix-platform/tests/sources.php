<?php
require __DIR__.'/access.php';
require __DIR__.'/../plugin/message-matrix-pro/includes/sources.php';
use WGMM\Sources as F;
function wp_salt($scheme){return 'unit-test-secret-never-production';}
function wp_generate_uuid4(){return bin2hex(random_bytes(16));}
function sanitize_file_name($name){return preg_replace('/[^a-zA-Z0-9._-]/','_',$name);}
class SourceDB extends DB {
    public $records=[];public $total=0;public $savedDoc=null;
    function get_var($q){return str_contains($q['sql'],'SUM(byte_size)')?$this->total:parent::get_var($q);}
    function insert($table,$data){$this->records[]=$data;return 1;}
    function query($q){if(is_array($q)&&str_starts_with($q['sql'],'UPDATE'))$this->savedDoc=json_decode($q['args'][0],true);return parent::query($q);}
}
function admin_url($path){return 'https://test.invalid/wp-admin/'.$path;}
function wp_create_nonce($action){return 'test-nonce';}
function add_query_arg($args,$url){return $url.'?'.http_build_query($args);}
$user=7;$active=true;$wpdb=new SourceDB;
$pdf="%PDF-1.7\n1 0 obj << /Type /Catalog >> endobj\n%%EOF";
F::validate_pdf('Report.pdf',$pdf);check(true,'PDF source accepted');
fails_source(fn()=>F::validate_pdf('Report.html',$pdf),'Wrong extension');
fails_source(fn()=>F::validate_pdf('Report.pdf','not a PDF document at all %%EOF'),'Wrong signature');
fails_source(fn()=>F::validate_pdf('Report.pdf',str_replace('%%EOF','/Encrypt 1 0 R %%EOF',$pdf)),'Encrypted PDF rejected');
$cipher=F::seal($pdf,1);check(!str_contains($cipher,'%PDF'),'Encrypted storage');check(F::unseal($cipher,1,hash('sha256',$pdf))===$pdf,'Verified round trip');
rejected(fn()=>F::unseal($cipher,2,hash('sha256',$pdf)),500,'Cross-company decrypt blocked');
rejected(fn()=>F::unseal($cipher,1,str_repeat('0',64)),500,'Hash mismatch blocked');
$raw=base64_decode($cipher);$raw[30]=chr(ord($raw[30])^1);rejected(fn()=>F::unseal(base64_encode($raw),1,hash('sha256',$pdf)),500,'Ciphertext tampering blocked');
F::save(1,3,'strategy_canvas','Report.pdf',$pdf,'Unit-test source');check(count($wpdb->records)===1,'One source inserted');check($wpdb->records[0]['workspace_id']===1,'Source company scoped');check($wpdb->savedDoc['foundation_sources'][0]['kind']==='strategy_canvas','Source reference attached');
$hash=WGMM\Domain::foundation_hash(WGMM\Domain::blank());check($hash!==WGMM\Domain::foundation_hash($wpdb->savedDoc),'Source changes foundation identity');
rejected(fn()=>F::save(1,2,'strategy_canvas','Report.pdf',$pdf,'Unit-test source'),409,'Stale source upload blocked');check(count($wpdb->records)===1,'Stale upload inserts no file');
$user=8;rejected(fn()=>F::save(1,3,'strategy_canvas','Report.pdf',$pdf,'Unit-test source'),403,'Unrelated member upload blocked');$user=7;
$wpdb->total=104857600;fails_source(fn()=>F::save(1,3,'strategy_canvas','Report.pdf',$pdf,'Unit-test source'),'Source history cap');
$links=F::links(['id'=>1,'document'=>['foundation_sources'=>[['id'=>'source_one','kind'=>'strategy_canvas']]]]);
$url=$links['strategy_canvas']['download_url'];parse_str(parse_url($url,PHP_URL_QUERY),$query);
check(!str_contains($url,'&amp;'),'Download URLs are raw JSON values');check(($query['_wpnonce']??'')==='test-nonce'&&($query['matrix_company']??'')==='1','Nonce and company survive URL parsing');
echo "Source upload, encryption, company access, revision and foundation-reference checks passed. Total assertions: $count\n";
function fails_source($fn,$name){try{$fn();}catch(InvalidArgumentException $e){check(true,$name);return;}throw new Exception('Expected rejection '.$name);}
