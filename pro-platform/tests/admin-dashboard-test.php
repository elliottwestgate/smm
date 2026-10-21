<?php
require __DIR__.'/dashboard-test.php';
$uid=1;$role='owner';$company=10;
$before=$reviewCalls;
ob_start();Westgate_Practicum_Pro::admin_page();$html=ob_get_clean();
check(str_contains($html,'Company administration') && str_contains($html,'Pro Analytics companies'),'Admin Dashboard opens the company index');
foreach(['report'=>'Open Report','review'=>'Open Reviews','account'=>'Manage Account'] as $tab=>$label){check(str_contains($html,'workspace=10#'.$tab) && str_contains($html,$label),'Company index provides an explicit '.$label.' destination');}
check(!str_contains($html,'data-primary-tabs') && !str_contains($html,'data-review-request') && $before===$reviewCalls,'Admin overview never embeds the last customer report or requests a Review');
$uid=2;
check(WGPP_Admin::render()==='','Company administration is inaccessible to non-administrators');
ob_start();Westgate_Practicum_Pro::admin_page();$denied=ob_get_clean();check($denied==='','Admin page callback does not expose company records to a non-administrator');
