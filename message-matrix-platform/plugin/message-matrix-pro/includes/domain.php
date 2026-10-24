<?php
namespace WGMM;

final class Domain {
    const ROLES = ['owner','strategist','contributor','reviewer','viewer'];
    const CHANNELS = ['Blog','Facebook','Instagram','Email','LinkedIn','YouTube','Other'];
    const STAGES = ['Awareness','Acquisition','Retention'];
    const KEYS = ['foundation_sources','brand','audiences','buckets','cells','channel_plans','campaigns','events','assets','notes'];
    public static function blank(): array {
        return ['foundation_sources'=>[],'brand'=>array_fill_keys(['story','promises','beliefs','trust','positioning','voice','website','location','logo_url','strategy_canvas_implications','brand_dyno_implications'],''),'audiences'=>[],'buckets'=>[],'cells'=>[],'channel_plans'=>[],'campaigns'=>[],'events'=>[],'assets'=>[],'notes'=>''];
    }
    public static function text($v,int $max=10000): string {
        if(!is_string($v) || strlen($v)>$max || preg_match('//u',$v)!==1){throw new \InvalidArgumentException('A text field is invalid or too long.');}
        return trim(strip_tags($v));
    }
    public static function url($v): string {
        $v=self::text($v,2000);
        if($v!=='' && (!filter_var($v,FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($v,PHP_URL_SCHEME)??''),['http','https'],true))){throw new \InvalidArgumentException('Enter a complete http or https website address.');}
        return $v;
    }
    public static function id($v): string {
        if(!is_string($v) || !preg_match('/^[a-z][a-z0-9_-]{5,79}$/i',$v)){throw new \InvalidArgumentException('A record identifier is invalid. Reload the workspace.');}return $v;
    }
    public static function choice($v,array $allowed): string {if(!is_string($v)||!in_array($v,$allowed,true)){throw new \InvalidArgumentException('Choose a valid value.');}return $v;}
    public static function date($v): string {
        if(!is_string($v)){throw new \InvalidArgumentException('Choose a valid date.');}
        $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v);if(!$d||$d->format('Y-m-d')!==$v){throw new \InvalidArgumentException('Choose a valid date.');}return $v;
    }
    private static function rows($v,int $max): array {if(!is_array($v)||!array_is_list($v)||count($v)>$max){throw new \InvalidArgumentException('The record collection is invalid or exceeds this build’s limit.');}return $v;}
    private static function unique(array &$seen,string $id): void {if(isset($seen[$id])){throw new \InvalidArgumentException('Duplicate record identifiers are not allowed.');}$seen[$id]=true;}
    public static function validate($raw): array {
        if(!is_array($raw)||array_diff(array_keys($raw),self::KEYS)||array_diff(self::KEYS,array_keys($raw))){throw new \InvalidArgumentException('The workspace document is incomplete. Reload before saving.');}
        $raw['foundation_sources']=$raw['foundation_sources']??[];$d=self::blank();$sourceKinds=[];foreach(self::rows($raw['foundation_sources'],2) as $r){$kind=self::choice($r['kind']??'',['strategy_canvas','brand_dyno']);self::unique($sourceKinds,$kind);$d['foundation_sources'][]=['id'=>self::id($r['id']??''),'kind'=>$kind,'filename'=>self::text($r['filename']??'',255),'sha256'=>self::text($r['sha256']??'',64),'uploaded_at'=>self::text($r['uploaded_at']??'',40),'origin'=>self::text($r['origin']??'',250)];}if(!is_array($raw['brand'])){throw new \InvalidArgumentException('Brand fields are invalid.');}
        foreach($d['brand'] as $k=>$v){$d['brand'][$k]=in_array($k,['website','logo_url'],true)?self::url($raw['brand'][$k]??''):self::text($raw['brand'][$k]??'');}
        $ids=[];$verticals=[];$buckets=[];$cells=[];$campaigns=[];$pairs=[];
        foreach(self::rows($raw['audiences'],30) as $r){$id=self::id($r['id']??null);self::unique($ids,$id);$verticals[$id]=true;$d['audiences'][]=['id'=>$id,'name'=>self::required($r['name']??'',150),'description'=>self::text($r['description']??''),'needs'=>self::text($r['needs']??''),'geography'=>self::text($r['geography']??'',1000),'evidence'=>self::text($r['evidence']??'')];}
        foreach(self::rows($raw['buckets'],30) as $r){$id=self::id($r['id']??null);self::unique($ids,$id);$buckets[$id]=true;$d['buckets'][]=['id'=>$id,'name'=>self::required($r['name']??'',150),'description'=>self::text($r['description']??''),'brand_connection'=>self::text($r['brand_connection']??'')];}
        foreach(self::rows($raw['cells'],900) as $r){$id=self::id($r['id']??null);self::unique($ids,$id);$v=$r['audience_id']??'';$b=$r['bucket_id']??'';self::ref($v,$verticals,'audience');self::ref($b,$buckets,'bucket');$pair=$v.'|'.$b;self::unique($pairs,$pair);$cells[$id]=true;$d['cells'][]=['id'=>$id,'audience_id'=>$v,'bucket_id'=>$b,'message'=>self::text($r['message']??'',3000),'proof'=>self::text($r['proof']??''),'series'=>self::text($r['series']??''),'role'=>self::choice($r['role']??'Awareness',self::STAGES),'unused_reason'=>self::text($r['unused_reason']??'',1000)];}
        $channelPairs=[];
        foreach(self::rows($raw['channel_plans'],2000) as $r){$id=self::id($r['id']??null);self::unique($ids,$id);$cell=$r['cell_id']??'';self::ref($cell,$cells,'message path');$ch=self::choice($r['channel']??'',self::CHANNELS);self::unique($channelPairs,$cell.'|'.$ch);$d['channel_plans'][]=['id'=>$id,'cell_id'=>$cell,'channel'=>$ch,'approach'=>self::text($r['approach']??''),'format'=>self::text($r['format']??'',200),'destination'=>self::url($r['destination']??'')];}
        foreach(self::rows($raw['campaigns'],50) as $r){$id=self::id($r['id']??null);self::unique($ids,$id);$days=$r['days']??0;if(!is_int($days)||$days<1||$days>366){throw new \InvalidArgumentException('Campaigns must cover 1 to 366 days.');}$channels=self::rows($r['channels']??[],7);foreach($channels as $ch){self::choice($ch,self::CHANNELS);}if(count(array_unique($channels))!==count($channels)){throw new \InvalidArgumentException('A channel was selected twice.');}$selected=self::rows($r['selected_cells']??[],900);foreach($selected as $c){self::ref($c,$cells,'message path');}$campaigns[$id]=['start'=>self::date($r['start']??''),'days'=>$days];$d['campaigns'][]=['id'=>$id,'title'=>self::required($r['title']??'',200),'start'=>$campaigns[$id]['start'],'days'=>$days,'objective'=>self::text($r['objective']??''),'channels'=>$channels,'selected_cells'=>array_values(array_unique($selected)),'cadence'=>self::text($r['cadence']??'',1000),'refinement'=>self::text($r['refinement']??''),'foundation_version'=>self::text($r['foundation_version']??'',80)];}
        foreach(self::rows($raw['events'],2000) as $r){$id=self::id($r['id']??null);self::unique($ids,$id);$camp=$r['campaign_id']??'';self::ref($camp,$campaigns,'campaign');$date=self::date($r['date']??'');$end=(new \DateTimeImmutable($campaigns[$camp]['start']))->modify('+'.($campaigns[$camp]['days']-1).' days')->format('Y-m-d');if($date<$campaigns[$camp]['start']||$date>$end){throw new \InvalidArgumentException('A calendar item falls outside its campaign dates.');}$cell=$r['cell_id']??'';self::ref($cell,$cells,'message path');$actual=$r['actual_date']??'';if($actual!==''){self::date($actual);}$d['events'][]=['id'=>$id,'campaign_id'=>$camp,'cell_id'=>$cell,'date'=>$date,'actual_date'=>$actual,'channel'=>self::choice($r['channel']??'',self::CHANNELS),'title'=>self::required($r['title']??'',250),'theme'=>self::text($r['theme']??'',500),'role'=>self::choice($r['role']??'Awareness',self::STAGES),'status'=>self::choice($r['status']??'planned',['idea','planned','drafting','in_review','approved','scheduled_externally','published','skipped','failed'])];}
        foreach(self::rows($raw['assets'],1000) as $r){$id=self::id($r['id']??null);self::unique($ids,$id);$camp=$r['campaign_id']??'';self::ref($camp,$campaigns,'campaign');$cell=$r['cell_id']??'';self::ref($cell,$cells,'message path');$assigned=$r['assigned_user_id']??0;if(!is_int($assigned)||$assigned<0){throw new \InvalidArgumentException('Choose a valid team member.');}$d['assets'][]=['id'=>$id,'campaign_id'=>$camp,'cell_id'=>$cell,'type'=>self::choice($r['type']??'social',['blog','email','social','visual_brief']),'title'=>self::required($r['title']??'',250),'copy'=>self::text($r['copy']??'',50000),'channel'=>self::choice($r['channel']??'',self::CHANNELS),'role'=>self::choice($r['role']??'Awareness',self::STAGES),'status'=>self::choice($r['status']??'draft',['draft','in_review','changes_requested','approved']),'assigned_user_id'=>$assigned,'event_id'=>self::text($r['event_id']??'',80),'companion_asset_id'=>self::text($r['companion_asset_id']??'',80),'review_notes'=>self::text($r['review_notes']??'',5000)];}
        $eventsById=array_column($d['events'],null,'id');$assetsById=array_column($d['assets'],null,'id');
        foreach($d['assets'] as $a){
            if($a['event_id']!==''){
                self::ref($a['event_id'],$eventsById,'calendar item');$e=$eventsById[$a['event_id']];
                foreach(['campaign_id','cell_id','channel'] as $key){if($a[$key]!==$e[$key]){throw new \InvalidArgumentException('The calendar item must match the asset campaign, message path and channel.');}}
            }
            if($a['companion_asset_id']!==''){
                self::ref($a['companion_asset_id'],$assetsById,'content draft');$parent=$assetsById[$a['companion_asset_id']];
                if($a['type']!=='visual_brief'||$parent['type']==='visual_brief'||$a['id']===$parent['id']){throw new \InvalidArgumentException('Image instructions must link to a content draft.');}
                foreach(['campaign_id','cell_id','channel'] as $key){if($a[$key]!==$parent[$key]){throw new \InvalidArgumentException('Image instructions must match the linked content campaign, message path and channel.');}}
            }
        }
        $d['notes']=self::text($raw['notes']);if(strlen(json_encode($d))>1000000){throw new \InvalidArgumentException('This workspace exceeds the 1 MB planning-document limit.');}return $d;
    }
    public static function required($v,int $max): string {$v=self::text($v,$max);if($v===''){throw new \InvalidArgumentException('Complete the required name or title.');}return $v;}
    private static function ref($id,array $map,string $name): void {if(!is_string($id)||!isset($map[$id])){throw new \InvalidArgumentException('A referenced '.$name.' is missing. Reassign dependent work before removing it.');}}
    public static function foundation(array $d): array {return array_intersect_key($d,array_flip(['foundation_sources','brand','audiences','buckets','cells','channel_plans']));}
    public static function foundation_hash(array $d): string {return hash('sha256',json_encode(self::foundation($d),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));}
    /** Readiness checks also run on the server; presentation controls are not authorization. */
    public static function approval_issues(array $d,array $a,string $version): array {
        $issues=[];$campaigns=array_column($d['campaigns'],null,'id');$c=$campaigns[$a['campaign_id']]??null;
        if(!$version||!$c||($c['foundation_version']??'')!==$version){$issues[]='Approve the current Brand foundation in Step 4, then attach it to this campaign in Step 5.';}
        if(!trim($a['copy']??'')){$issues[]='Add the content in Step 6 before approval.';}
        $content=$a;
        if($a['type']==='visual_brief'){
            $assets=array_column($d['assets'],null,'id');$content=$assets[$a['companion_asset_id']??'']??null;
            if(!$content||$content['type']==='visual_brief'){$issues[]='Link these Image Instructions to their content draft in Step 6.';}
        }
        $events=array_column($d['events'],null,'id');$event=$events[$content['event_id']??'']??null;
        if(!$event){$issues[]='Link the content draft to its calendar item in Step 6.';}
        elseif(($event['role']??'')!==($a['role']??'')){$issues[]='Match the Brand Axis purpose to the linked calendar item before approval.';}
        return $issues;
    }
    public static function review_transition(array $d,array $a,string $version,string $operation,string $feedback=''): array {
        self::choice($operation,['approve','reopen','request_changes']);
        if($operation==='approve'){$issues=self::approval_issues($d,$a,$version);if($issues){throw new \InvalidArgumentException(implode(' ',$issues));}$a['status']='approved';$a['review_notes']='';}
        elseif($operation==='request_changes'){$feedback=self::required($feedback,5000);$a['status']='changes_requested';$a['review_notes']=$feedback;}
        else{$a['status']='draft';}
        return $a;
    }
    public static function can(string $role,string $action): bool {
        $map=['read'=>self::ROLES,'plan'=>['owner','strategist'],'draft'=>['owner','strategist','contributor'],'approve'=>['owner','strategist','reviewer'],'team'=>['owner']];return in_array($role,$map[$action]??[],true);
    }
    public static function changed_keys(array $a,array $b): array {return array_values(array_filter(self::KEYS,static fn($k)=>$a[$k]!==$b[$k]));}
    /** An approved asset must be explicitly reopened before any content is altered. */
    public static function guard_assets(array $old,array $new,string $role,int $user,array $member_ids): void {
        $normalize=static function($r){$r+=['event_id'=>'','companion_asset_id'=>'','review_notes'=>''];ksort($r);return $r;};$old=array_map($normalize,$old);$new=array_map($normalize,$new);
        $before=array_column($old,null,'id');$after=array_column($new,null,'id');
        foreach($before as $id=>$r){if($r['status']==='approved'&&(!isset($after[$id])||$after[$id]!==$r)){throw new \InvalidArgumentException('Reopen the approved asset before editing or deleting it.');}}
        foreach($after as $id=>$r){if(($r['review_notes']??'')!==($before[$id]['review_notes']??'')){throw new \InvalidArgumentException('Use Request changes to record review feedback.');}if($r['assigned_user_id']&&!in_array($r['assigned_user_id'],$member_ids,true)){throw new \InvalidArgumentException('Assign an asset to a current company member.');}if(($before[$id]['status']??null)!=='approved'&&$r['status']==='approved'){throw new \InvalidArgumentException('Use the approval action to approve an asset.');}}
        if($role==='contributor'){
            foreach(array_unique(array_merge(array_keys($before),array_keys($after))) as $id){if(($before[$id]??null)===($after[$id]??null)){continue;}if(!isset($before[$id],$after[$id])||$before[$id]['assigned_user_id']!==$user||$after[$id]['assigned_user_id']!==$user){throw new \InvalidArgumentException('Contributors may edit only assets assigned to them.');}foreach(['id','campaign_id','cell_id','type','channel','role','assigned_user_id','event_id','companion_asset_id'] as $k){if($before[$id][$k]!==$after[$id][$k]){throw new \InvalidArgumentException('Only the assigned draft content and review status may be changed.');}}}
        }
    }
}
