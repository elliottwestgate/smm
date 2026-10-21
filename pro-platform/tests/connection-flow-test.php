<?php
require __DIR__.'/ga4-native-test.php';
$company=20;$uid=1;$role='owner';$_GET=[];$_POST=[];
foreach(['google','email','facebook','instagram'] as $service){
 $url=WGPP_Company::connection_url($service);parse_str(parse_url($url,PHP_URL_QUERY),$q);
 check($q['workspace']==20 && $q['connection']===$service && parse_url($url,PHP_URL_FRAGMENT)==='account-connections','Connection return pins the current company and '.$service.' section');
}
$meta[20][WGPP_Google::TOKEN]='saved-token';$meta[20][WGPP_GA4::CONFIG]=['property'=>'123','timezone'=>'America/Chicago','form_events'=>[],'phone_events'=>[]];
$meta[20]['_wgpp_email_provider']='mailchimp';$meta[20][WGPP_Mailchimp::AUTH]='saved-mailchimp';
$meta[20][WGPP_Facebook::PAGE]=WGPP_Facebook::encrypt(['id'=>'12345','name'=>'V1 MOTO','token'=>'private-page-token'],'company:20:page');
$summary=WGPP_Portal::connection_summary(WGPP_Company::context());
check(str_contains($summary,'Connected · V1 MOTO') && str_contains($summary,'Connected · Property 123'),'Saved connections show selected Page and property without requiring setup again');
check(!str_contains($summary,'private-page-token') && !str_contains($summary,'saved-token'),'Connection summary never exposes credentials');
$google=WGPP_Google::render();
check(str_contains($google,'workspace=20') && str_contains($google,'connection=google') && str_contains($google,'#account-connections'),'Google authorization carries the pinned connection return through OAuth');
check(!str_contains($google,'data-connection="google" open'),'Completed Google setup stays collapsed until opened');
$company=10;$other=WGPP_Portal::connection_summary(WGPP_Company::context());
check(!str_contains($other,'V1 MOTO'),'Another company summary does not display V1Moto’s selected Page');
