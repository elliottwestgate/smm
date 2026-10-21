<?php
require __DIR__.'/dashboard-test.php';
$menus=[];$submenus=[];$legacy=[];$removed=[];
function add_menu_page(...$args){$GLOBALS['menus'][]=$args;}
function add_submenu_page(...$args){$GLOBALS['submenus'][]=$args;}
function add_management_page(...$args){$GLOBALS['legacy'][]=$args;}
function remove_submenu_page(...$args){$GLOBALS['removed'][]=$args;}
Westgate_Practicum_Pro::menu();
Westgate_Practicum_Pro::hide_legacy_menu();
check(count($menus)===1 && $menus[0][1]==='SMM Pro Analytics' && $menus[0][2]==='manage_options','SMM Pro Analytics has one administrator-only top-level menu');
check(array_column($submenus,4)===['wgpp-preview','wgpp-pro-review'],'Dashboard and Review Settings are children of the Analytics menu');
check($legacy[0][3]==='wgpp-preview' && in_array(['tools.php','wgpp-preview'],$removed,true) && in_array(['tools.php','wgpp-pro-review'],$removed,true),'Old Tools bookmarks remain registered while both Tools links are hidden');
