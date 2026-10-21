<?php
require __DIR__.'/dashboard-test.php';
$r=WGPP_Analytics::ranges('60','2026-10-17','','none');
check($r['current']===['2026-08-19','2026-10-17'] && $r['comparison']===null,'Compare None retains current range without a comparison range');
$company=10;$role='viewer';$uid=2;$_GET=['preset'=>'30','start'=>'','end'=>'2026-10-16','comparison'=>'none'];
try{WGPP_Analytics::ajax();}catch(StopResponse $r){check($r->status===200 && $r->data['comparison_enabled']===false && $r->data['collection']['comparison']==='off' && $r->data['analysis']['metrics']['sessions']===200.0,'Compare None returns current metrics and skips comparison collection');check($r->data['analysis']['vertical']===null && $r->data['analysis']['horizontal']===null,'Compare None does not invent directional movement');}
