<?php
require __DIR__.'/dashboard-test.php';
$uid=1;$role='owner';$company=10;
function sanitize_file_name($s){return preg_replace('/[^a-zA-Z0-9._-]/','-',$s);}
function wp_salt($s){return 'test-foundation-salt';}
function update_post_meta($id,$key,$value){if(!empty($GLOBALS['fail_manifest'])&&$key===WGPP_Documents::META){return false;}if(($GLOBALS['meta'][$id][$key]??null)===$value){return false;}$GLOBALS['meta'][$id][$key]=$value;return true;}
function add_post_meta($id,$key,$value,$unique){if(strlen($value)>262144||isset($GLOBALS['meta'][$id][$key])||(!empty($GLOBALS['fail_chunk_after'])&&++$GLOBALS['chunk_attempt']>=$GLOBALS['fail_chunk_after'])){return false;}$GLOBALS['meta'][$id][$key]=$value;return true;}
function delete_post_meta($id,$key){unset($GLOBALS['meta'][$id][$key]);return true;}
function foundation_envelope($company,$record){return implode('',array_map(static fn($key)=>get_post_meta($company,$key,true),$record['encrypted_chunks']));}

function wp_cache_delete(...$args){}
class FoundationDB{public $prefix='wp_';function prepare($q,...$a){return $q;}function get_var($q){return 1;}}
$wpdb=new FoundationDB();
$pdf="%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n";
$owner=WGPP_Documents::store(10,'strategy_canvas','Strategy Canvas.pdf',$pdf);
check($owner['filename']==='Strategy-Canvas.pdf','Owner can upload a foundation report');
$record=WGPP_Documents::records(10)['strategy_canvas'];
check(!str_contains(foundation_envelope(10,$record),base64_encode($pdf)) && WGPP_Documents::bytes(10,'strategy_canvas',$record)===$pdf,'PDF bytes are encrypted and verified');
try{WGPP_Documents::bytes(20,'strategy_canvas',$record);throw new Exception('Company swap');}catch(RuntimeException $e){echo "PASS encrypted PDF cannot be moved to another company\n";}
try{WGPP_Documents::bytes(10,'brand_dyno',$record);throw new Exception('Type swap');}catch(RuntimeException $e){echo "PASS encrypted PDF is bound to its document type\n";}
$role='analyst';WGPP_Documents::store(10,'brand_dyno','Dyno.pdf',$pdf);check(count(WGPP_Documents::records(10))===2,'Analyst can upload Brand Dyno without owner connection permissions');
$frozen=['context'=>WGPP_Documents::capture(10)];$input=WGPP_Pro_Review::review_input($frozen,10,['measured_facts'=>[]]);
check(count(array_filter($input[0]['content'],static fn($p)=>$p['type']==='input_file'))===2,'New Review attaches both frozen PDFs for assessment');
check(!str_contains($input[0]['content'][0]['text'],'_foundation_files')&&!str_contains($input[0]['content'][0]['text'],foundation_envelope(10,$record)),'Internal encrypted envelopes are excluded from model text');
$role='owner';WGPP_Documents::store(10,'strategy_canvas','New.pdf',str_replace('Catalog','Pages',$pdf));
check(WGPP_Documents::input_files(10,$frozen)[1]['file_data']==='data:application/pdf;base64,'.base64_encode($pdf),'Replacing a document does not change an already captured Review');
$role='viewer';check(count(WGPP_Documents::records(10))===2,'Company Viewer can read document metadata');
try{WGPP_Documents::store(10,'brand_dyno','Dyno.pdf',$pdf);throw new Exception('Viewer uploaded');}catch(RuntimeException $e){echo "PASS Viewer cannot upload or replace documents\n";}
try{WGPP_Documents::records(20);throw new Exception('Cross-company metadata');}catch(RuntimeException $e){echo "PASS company document isolation enforced\n";}
foreach([['bad.html',$pdf],['bad.pdf','not a PDF'],['encrypted.pdf',str_replace('Catalog','/Encrypt',$pdf)],['large.pdf','%PDF-1.4'.str_repeat(' ',WGPP_Documents::MAX_BYTES).'%%EOF']] as [$name,$bytes]){try{WGPP_Documents::validate_pdf($name,$bytes);throw new Exception('Invalid PDF accepted');}catch(InvalidArgumentException $e){echo "PASS invalid PDF rejected\n";}}
$recordMethod=new ReflectionMethod(WGPP_Native_Reviews::class,'record');$public=$recordMethod->invoke(null,'sample',['created_at'=>'2026-10-19T16:00:00Z','snapshot'=>$frozen,'review'=>[]]);
check(!isset($public['snapshot']['context']['_foundation_files'])&&count($public['snapshot']['context']['foundation_documents'])===2,'Saved Review exposes source metadata without raw files or encryption envelopes');
$alignment=[];foreach(['strategy_canvas','brand_dyno'] as $kind){$alignment[]=['document_key'=>$kind,'reference'=>'Page 1','connection'=>'Brand direction compared with performance','recommendation'=>'Align the next content sequence'];}
$r=WGPP_Pro_Review::document_alignment(['foundation_alignment'=>$alignment],$frozen);check(count($r['foundation_alignment'])===2 && isset($r['foundation_alignment'][0]['source']['sha256']),'Each document assessment receives the frozen source version');
try{WGPP_Pro_Review::document_alignment(['foundation_alignment'=>[]],$frozen);throw new Exception('Ignored uploads');}catch(RuntimeException $e){echo "PASS Review cannot silently omit uploaded foundations\n";}
try{$alignment[0]['document_key']='other';WGPP_Pro_Review::document_alignment(['foundation_alignment'=>$alignment],$frozen);throw new Exception('Invented source');}catch(RuntimeException $e){echo "PASS invented foundation references rejected\n";}

$role='owner';$company=10;$largePdf="%PDF-1.4\n".str_repeat(' ',(int)(5.2*1024*1024))."\n%%EOF\n";
$largeRecord=WGPP_Documents::store(10,'strategy_canvas','V1Moto-Strategy-Canvas.pdf',$largePdf);
check($largeRecord['bytes']>5*1024*1024 && WGPP_Documents::bytes(10,'strategy_canvas',WGPP_Documents::records(10)['strategy_canvas'])===$largePdf,'A 5.2 MB Strategy Canvas stores and decrypts under the larger allowance');
WGPP_Documents::validate_pdf('boundary.pdf',"%PDF-1.4".str_repeat(' ',WGPP_Documents::MAX_BYTES-13).'%%EOF');
check(true,'PDF at the 20 MB boundary is accepted');

check(count(WGPP_Documents::records(10)['strategy_canvas']['encrypted_chunks'])>1,'Large PDF is split into database-safe encrypted pieces');
$legacy=['metadata'=>$record['metadata'],'encrypted'=>foundation_envelope(10,$record)];
check(WGPP_Documents::bytes(10,'strategy_canvas',$legacy)===$pdf,'Older single-value encrypted document remains readable');
$before=WGPP_Documents::records(10)['strategy_canvas'];$snapshot=['context'=>WGPP_Documents::capture(10)];
WGPP_Documents::rename(10,'strategy_canvas','BizGift-Strategy-Canvas.pdf');$after=WGPP_Documents::records(10)['strategy_canvas'];
check($after['metadata']['filename']==='BizGift-Strategy-Canvas.pdf'&&$after['encrypted_chunks']===$before['encrypted_chunks']&&$after['metadata']['sha256']===$before['metadata']['sha256'],'Rename changes only filename and preserves encrypted bytes and version');
check($snapshot['context']['foundation_documents']['strategy_canvas']['filename']==='V1Moto-Strategy-Canvas.pdf','Rename does not relabel frozen saved Review sources');
WGPP_Documents::rename(10,'strategy_canvas','BizGift-Strategy-Canvas.pdf');check(true,'Renaming to the current filename is idempotent');
foreach(['bad.txt','',str_repeat('a',241).'.pdf',[]] as $bad){try{WGPP_Documents::rename(10,'strategy_canvas',$bad);throw new Exception('Invalid rename accepted');}catch(InvalidArgumentException $e){echo "PASS invalid rename rejected\n";}}
$role='viewer';try{WGPP_Documents::rename(10,'strategy_canvas','Other.pdf');throw new Exception('Viewer renamed');}catch(RuntimeException $e){echo "PASS Viewer cannot rename documents\n";}$role='owner';
foreach(['chunk','manifest'] as $failure){
 $saved=$GLOBALS['meta'][10];$GLOBALS['fail_chunk_after']=$failure==='chunk'?2:0;$GLOBALS['chunk_attempt']=0;$GLOBALS['fail_manifest']=$failure==='manifest';
 try{WGPP_Documents::store(10,'strategy_canvas','Failed.pdf',$largePdf);throw new Exception('Expected storage failure');}catch(RuntimeException $e){check($GLOBALS['meta'][10]===$saved,'Partial '.$failure.' failure keeps previous document and removes incomplete chunks');}
 $GLOBALS['fail_chunk_after']=0;$GLOBALS['fail_manifest']=false;
}
$broken=WGPP_Documents::records(10)['strategy_canvas'];$key=$broken['encrypted_chunks'][0];$originalChunk=$GLOBALS['meta'][10][$key];unset($GLOBALS['meta'][10][$key]);
try{WGPP_Documents::bytes(10,'strategy_canvas',$broken);throw new Exception('Missing chunk accepted');}catch(RuntimeException $e){echo "PASS missing encrypted piece fails closed\n";}$GLOBALS['meta'][10][$key]=$originalChunk;
