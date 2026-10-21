<?php
define('ABSPATH',__DIR__);
function wp_salt($scheme){return 'test-only-salt-which-is-never-used-in-production';}
function wp_json_encode($data){return json_encode($data);}
require __DIR__.'/../plugin/westgate-practicum-pro/google.php';
function check($ok,$label){if(!$ok)throw new Exception($label);echo "PASS $label\n";}
$payload=['access_token'=>'private-access','refresh_token'=>'private-refresh'];
$encoded=WGPP_Google::encrypt($payload,'company:10');
check(WGPP_Google::decrypt($encoded,'company:10')===$payload,'Native credentials encrypt and decrypt');
check(!str_contains($encoded,'private-access'),'Native envelope does not contain plaintext token');
try{WGPP_Google::decrypt($encoded,'company:20');throw new Exception('Context swap accepted');}catch(RuntimeException $e){echo "PASS encrypted credentials bound to company\n";}
$bytes=base64_decode($encoded);$bytes[30]=chr(ord($bytes[30])^1);
try{WGPP_Google::decrypt(base64_encode($bytes),'company:10');throw new Exception('Tamper accepted');}catch(RuntimeException $e){echo "PASS tampered native token rejected\n";}
$response=['access_token'=>'access','refresh_token'=>'refresh','token_type'=>'Bearer','expires_in'=>3600,'scope'=>WGPP_Google::SCOPE];
check(WGPP_Google::token_response($response,true)===$response,'Read-only offline grant accepted');
unset($response['refresh_token']);
try{WGPP_Google::token_response($response,true);throw new Exception('Missing refresh accepted');}catch(RuntimeException $e){echo "PASS missing offline permission rejected\n";}
$response['refresh_token']='refresh';$response['scope']='other';
try{WGPP_Google::token_response($response,true);throw new Exception('Missing scope accepted');}catch(RuntimeException $e){echo "PASS missing Analytics permission rejected\n";}
$response['expires_in']=-1;
try{WGPP_Google::token_response($response,false);throw new Exception('Bad expiry accepted');}catch(RuntimeException $e){echo "PASS invalid token lifetime rejected\n";}
