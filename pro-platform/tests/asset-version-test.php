<?php
require __DIR__.'/dashboard-test.php';
$url='https://example.com/wp-content/plugins/westgate-practicum-pro/assets/reviews.js';
$result=WGPP_Company::asset_version($url,'wgpp-reviews');
check(str_contains($result,'ver='.substr(hash_file('sha256',__DIR__.'/../plugin/westgate-practicum-pro/assets/reviews.js'),0,16)),'Review script uses its source content hash for cache invalidation');
check(WGPP_Company::asset_version($url,'other-plugin')===$url,'Other plugins retain their asset URLs');
check(str_contains(WGPP_Company::asset_version('https://example.com/other.js','wgpp-other'),'0.6.32-preview'),'Unrecognized Pro assets use the release version');
