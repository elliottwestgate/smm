<?php
require __DIR__.'/dashboard-test.php';
check(WGPP_Portal::is_blog('/blog/rug-care/') && WGPP_Portal::is_blog('https://example.com/blog/rug-care/?utm_source=email'),'Blog classification uses platform-independent /blog/ path');
check(!WGPP_Portal::is_blog('/blogging/') && !WGPP_Portal::is_blog('/services/?next=/blog/post/'),'Blog classification does not match unrelated paths or query strings');
$company=10;$uid=2;$role='viewer';$html=Westgate_Practicum_Pro::render();
foreach(['data-primary-panel="report"','data-primary-panel="review"','data-primary-panel="account"','data-account-panel="company"','data-account-panel="connections"','data-account-panel="users"','data-account-panel="billing"'] as $marker){check(str_contains($html,$marker),'Workspace renders '.$marker);}
foreach(['data-loop','data-headline','data-trends','data-sources','data-audience','data-goals','data-profile','data-email-results','data-review-history'] as $marker){check(str_contains($html,$marker),'Existing reporting retained: '.$marker);}
check(!str_contains($html,'name="brand_website"') && !str_contains($html,'name="client_secret"'),'Viewer account does not expose owner settings forms');
check(str_contains($html,'Self-service subscription changes and invoices are not connected'),'Billing does not pretend subscription management is enabled');

function get_permalink(){return 'https://example.com/pro-analytics/';}
$GLOBALS['testAdminSurface']=false;$html=Westgate_Practicum_Pro::render();
check(!str_contains($html,'Import private development snapshot') && !str_contains($html,'Load sample data'),'Customer dashboard omits developer import and sample tools');
