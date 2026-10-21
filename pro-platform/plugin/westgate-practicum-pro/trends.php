<?php
if (!defined('ABSPATH')) { exit; }
final class WGPP_Trends {
    const CACHE='_wgpp_trends_v1';
    public static function dimension($from,$to) {
        $days=(int)WGPP_Analytics::date($from)->diff(WGPP_Analytics::date($to))->format('%a')+1;
        return $days<=31?'date':($days<=180?'isoYearIsoWeek':'yearMonth');
    }
    public static function buckets($from,$to,$dimension) {
        $rows=[];$end=WGPP_Analytics::date($to);
        for($date=WGPP_Analytics::date($from);$date<=$end;$date=$date->modify('+1 day')) {
            $key=$date->format($dimension==='date'?'Ymd':($dimension==='yearMonth'?'Ym':'oW'));
            if(!isset($rows[$key])){$rows[$key]=['key'=>(string)$key,'from'=>$date->format('Y-m-d'),'to'=>$date->format('Y-m-d')];}
            $rows[$key]['to']=$date->format('Y-m-d');
        }
        return $rows;
    }
    public static function assemble($from,$to,$dimension,$reports) {
        $rows=self::buckets($from,$to,$dimension);
        foreach($rows as &$row){foreach(['sessions','users','new_users','returning_users','form_events','phone_events'] as $key){$part=in_array($key,['sessions','users','new_users'],true)?0:($key==='returning_users'?1:2);$row[$key]=$reports[$part]['limited']?null:0;}}unset($row);
        foreach($reports[0]['rows'] as $r){$key=$r[$dimension];if(isset($rows[$key])){foreach(['sessions'=>'sessions','totalUsers'=>'users','newUsers'=>'new_users'] as $metric=>$field){$rows[$key][$field]=$r[$metric];}}}
        foreach($reports[1]['rows'] as $r){$key=$r[$dimension];if(isset($rows[$key]) && $r['newVsReturning']==='returning'){$rows[$key]['returning_users']=$r['totalUsers'];}}
        foreach($reports[2]['rows'] as $r){$key=$r[$dimension];if(!isset($rows[$key]) || $reports[2]['limited']){continue;}foreach(['form_events','phone_events'] as $goal){if(in_array($r['eventName'],$reports[2]['goals'][$goal],true)){$rows[$key][$goal]+=$r['eventCount'];}}}
        foreach($rows as &$row){$row['goal_rate']=$row['sessions']>0 && $row['form_events']!==null && $row['phone_events']!==null?100*($row['form_events']+$row['phone_events'])/$row['sessions']:null;}unset($row);
        return array_values($rows);
    }
    public static function request($company,$from,$to) {
        if(!WGPP_Company::can('view') || (int)(WGPP_Company::context()['id'] ?? 0)!==(int)$company){throw new RuntimeException('Company access required.');}
        $config=WGPP_GA4::config($company);if(!$config){return ['status'=>'unavailable','message'=>'Connect Google Analytics to load performance over time. Imported totals do not contain a timeline.'];}
        $last=(new DateTimeImmutable('yesterday',new DateTimeZone($config['timezone'])))->format('Y-m-d');
        if($to>$last){throw new InvalidArgumentException('Timeline dates must end by yesterday in the property timezone.');}
        $signature=hash('sha256',wp_json_encode($config).'|'.get_post_meta($company,'_wgpp_google_grant',true));
        $key=hash('sha256',$from.'|'.$to.'|'.$signature);$dimension=self::dimension($from,$to);
        $job=WGPP_Native_Reviews::locked($company,static function()use($company,$key){
            $cache=get_post_meta($company,self::CACHE,true) ?: [];$entry=$cache[$key] ?? null;
            if($entry && $entry['updated']>time()-21600 && $entry['status']==='complete'){return ['done'=>$entry];}
            if($entry && $entry['status']==='processing' && $entry['updated']>time()-60){return null;}
            if(!$entry || $entry['updated']<time()-21600){$entry=['step'=>0,'reports'=>[]];}
            if(count($cache)>=24 && !isset($cache[$key])){uasort($cache,static function($a,$b){return $a['updated']<=>$b['updated'];});unset($cache[array_key_first($cache)]);}
            $entry['status']='processing';$entry['updated']=time();$cache[$key]=$entry;update_post_meta($company,self::CACHE,$cache);return $entry;
        });
        if($job===null){return ['status'=>'queued'];}
        if(isset($job['done'])){return ['status'=>'complete','dimension'=>$dimension,'timezone'=>$config['timezone'],'points'=>self::assemble($from,$to,$dimension,$job['done']['reports']),'limited'=>(bool)array_filter(array_column($job['done']['reports'],'limited'))];}
        $specs=[[[$dimension],['sessions','totalUsers','newUsers']],[[$dimension,'newVsReturning'],['totalUsers']],[[$dimension,'eventName'],['eventCount']]];
        try{
            [$dims,$metrics]=$specs[$job['step']];$filter=$job['step']===2?['filter'=>['fieldName'=>'eventName','inListFilter'=>['values'=>array_values(array_unique(array_merge($config['form_events'],$config['phone_events']))),'caseSensitive'=>true]]]:null;
            $report=WGPP_GA4::query($company,$config['property'],$from,$to,$dims,$metrics,10000,$filter);
            if($report['timezone'] && $report['timezone']!==$config['timezone']){throw new RuntimeException('Property timezone changed.');}
            if($job['step']===2){$report['goals']=$config;}
            WGPP_Native_Reviews::locked($company,static function()use($company,$key,$signature,$job,$report){
                $config=WGPP_GA4::config($company);
                if(!$config || hash('sha256',wp_json_encode($config).'|'.get_post_meta($company,'_wgpp_google_grant',true))!==$signature){throw new RuntimeException('Google connection changed. Reopen the timeline.');}
                $cache=get_post_meta($company,self::CACHE,true) ?: [];if(($cache[$key]['step'] ?? -1)!==$job['step']){return;}
                $entry=$cache[$key];$entry['reports'][]=$report;$entry['step']++;$entry['updated']=time();$entry['status']=$entry['step']===3?'complete':'queued';$cache[$key]=$entry;update_post_meta($company,self::CACHE,$cache);
            });
        }catch(Throwable $e){WGPP_Native_Reviews::locked($company,static function()use($company,$key){$cache=get_post_meta($company,self::CACHE,true) ?: [];unset($cache[$key]);update_post_meta($company,self::CACHE,$cache);});throw $e;}
        return ['status'=>'queued','step'=>$job['step']+1];
    }
    public static function ajax() {
        if(!WGPP_Analytics::allowed()){wp_send_json_error(['message'=>'Company access required.'],403);return;}
        check_ajax_referer('wgpp_analytics','nonce');nocache_headers();
        try{
            $q=[];foreach(['preset','start','end','comparison'] as $field){$v=$_GET[$field] ?? '';if(!is_string($v)){throw new InvalidArgumentException('Invalid timeline dates.');}$q[$field]=sanitize_text_field(wp_unslash($v));}
            $ranges=WGPP_Analytics::ranges($q['preset'],$q['end'],$q['start'],$q['comparison']);
            wp_send_json_success(self::request(WGPP_Company::context()['id'],...$ranges['current']));
        }catch(InvalidArgumentException $e){wp_send_json_error(['message'=>$e->getMessage()],422);}catch(RuntimeException $e){wp_send_json_error(['message'=>$e->getMessage()],503);}
    }
}
