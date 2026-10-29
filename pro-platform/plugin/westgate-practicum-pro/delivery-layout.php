<?php
if(!defined('ABSPATH')){exit;}

/** Vector layout shared by emailed Reports; all figures come from the frozen snapshot. */
trait WGPP_Delivery_Layout {
    private function box($x,$top,$w,$h,$fill='0.94 0.97 0.98',$stroke=null){
        $this->stream.="q $fill rg ".($stroke?"$stroke RG 0.6 w ":'')."$x ".($top-$h)." $w $h re ".($stroke?'B':'f')." Q\n";
    }
    private function rule($x,$y,$to,$color='0.79 0.85 0.88'){$this->stream.="$color RG 0.5 w $x $y m $to $y l S\n";}
    private function fit($s,$width,$size=10){
        $s=preg_replace('/\s+/',' ',self::plain($s));
        if(self::text_width($s,$size)<=$width){return $s;}
        while(strlen($s)>0 && self::text_width($s.'...',$size)>$width){$s=substr($s,0,-1);}
        return rtrim($s).'...';
    }
    private function right($s,$right,$y,$size=10,$bold=false,$color='0.13 0.20 0.24'){
        $this->text($s,$right-self::text_width($s,$size,$bold),$y,$size,$bold,$color);
    }
    private static function text_width($s,$size,$bold=false){
        $font=self::fonts()[$bold?'bold':'regular'];$sum=0;
        for($i=0;$i<strlen($s);$i++){$sum+=$font['widths'][ord($s[$i])-32] ?? 600;}
        return $sum*$size/1000;
    }
    private static function fonts(){static $fonts=null;if($fonts===null){$fonts=json_decode(file_get_contents(__DIR__.'/assets/pdf-fonts.json'),true,512,JSON_THROW_ON_ERROR);}return $fonts;}
    private function paragraph($text,$width=510,$size=9,$x=51,$color='0.24 0.34 0.40'){
        $words=preg_split('/\s+/',trim(self::plain($text)));$line='';
        foreach($words as $word){$next=$line===''?$word:$line.' '.$word;if(self::text_width($next,$size)>$width && $line!==''){$this->text($line,$x,$this->y,$size,false,$color);$this->y-=$size+4;$line=$word;}else{$line=$next;}}
        if($line!==''){$this->text($line,$x,$this->y,$size,false,$color);$this->y-=$size+4;}
    }
    private function section($title,$subtitle=''){
        $this->y-=4;$this->text(self::plain($title),42,$this->y,14,true,'0.02 0.25 0.37');$this->y-=17;
        if($subtitle!==''){$this->paragraph($subtitle,528,8.5,42);}$this->y-=7;
    }
    private static function change_label($a,$b,$kind=''){
        if(!is_numeric($a)||!is_numeric($b)){return '--';}
        if($kind==='net'){return sprintf('%+g',(float)$a-(float)$b);}
        if($kind==='%'){return sprintf('%+.2f pp',(float)$a-(float)$b);}
        if((float)$a===0.0 && (float)$b===0.0){return '0.0%';}
        $v=WGPP_Analytics::percent($a,$b);return $v===null?'No baseline':sprintf('%+.1f%%',$v);
    }
    private function data_table($rows,$compare=true){
        $top=$this->y;$this->box(42,$top+10,528,25,'0.03 0.25 0.36');
        $this->text('MEASURE',51,$top-6,8,true,'1 1 1');$this->right('CURRENT',357,$top-6,8,true,'1 1 1');
        $this->right('PREVIOUS',458,$top-6,8,true,'1 1 1');$this->right('CHANGE',561,$top-6,8,true,'1 1 1');$this->y=$top-15;
        foreach($rows as $i=>$r){[$label,$a,$b]=$r;$unit=$r[3] ?? '';if($i%2===0){$this->box(42,$this->y,528,25);}$baseline=$this->y-16;
            $this->text($this->fit($label,235,9),51,$baseline,9);
            $format=static function($v)use($unit){return is_numeric($v)?($unit==='%'?number_format($v,2).'%':self::number($v)):'--';};
            $this->right($format($a),357,$baseline,10,true);$this->right($compare?$format($b):'--',458,$baseline,9);
            $this->right($compare?self::change_label($a,$b,$unit):'--',561,$baseline,9);$this->y-=25;
        }$this->y-=15;
    }
    private function note($title,$body,$warning=false){
        $words=strlen(self::plain($body));$height=36+max(1,(int)ceil($words/100))*13;
        $top=$this->y+5;$this->box(42,$top,528,$height,$warning?'1 0.96 0.87':'0.94 0.97 0.98');
        $this->text(self::plain($title),52,$top-17,10,true,'0.02 0.25 0.37');$this->y=$top-33;$this->paragraph($body,508,9,52);$this->y=$top-$height-13;
    }
    private function axis_panel($s){
        $an=$s['analysis'];$v=$an['vertical'] ?? null;$h=$an['horizontal'] ?? null;$top=$this->y;
        $this->box(42,$top,528,225,'0.94 0.97 0.98','0.76 0.85 0.90');
        $this->text('BRAND AXIS',55,$top-22,10,true,'0.02 0.30 0.46');
        $cx=221;$cy=$top-118;
        $this->stream.="0.74 0.81 0.85 RG 0.6 w 72 $cy m 368 $cy l S $cx ".($cy-63)." m $cx ".($cy+61)." l S\n";
        $this->stream.='0.06 0.47 0.66 RG 3 w 221 '.$cy.' m 170 '.($cy+71).' 89 '.($cy+65).' 89 '.$cy.' c 89 '.($cy-65).' 170 '.($cy-71).' 221 '.$cy.' c 272 '.($cy+71).' 353 '.($cy+65).' 353 '.$cy.' c 353 '.($cy-65).' 272 '.($cy-71).' 221 '.$cy." c S\n";
        $this->text('Acquisition',84,$cy-78,9,true);$this->text('Retention',293,$cy-78,9,true);
        $dx=is_numeric($h)?tanh($h/100)*95:0;$dy=is_numeric($v)?tanh($v/100)*50:0;$mx=$cx+$dx;$my=$cy+$dy;
        $distance=hypot($dx,$dy);
        if($h!==null && $v!==null && $distance>0.01){
            $ux=$dx/$distance;$uy=$dy/$distance;$length=min(8,$distance);$half=min(4,$distance/2);
            $baseX=$mx-$ux*$length;$baseY=$my-$uy*$length;
            $leftX=$baseX-$uy*$half;$leftY=$baseY+$ux*$half;$rightX=$baseX+$uy*$half;$rightY=$baseY-$ux*$half;
            $this->stream.="0.93 0.46 0.12 RG 1.5 w $cx $cy m $mx $my l S\n0.93 0.46 0.12 rg $mx $my m $leftX $leftY l $rightX $rightY l h f\n";
        }
        if($this->logo){
            $scale=min(48/$this->logo['width'],38/$this->logo['height']);$w=$this->logo['width']*$scale;$height=$this->logo['height']*$scale;
            // Keep the logo's alpha mask; no white backing rectangle.
            $this->stream.=sprintf("q %.2f 0 0 %.2f %.2f %.2f cm /Logo Do Q\n",$w,$height,$cx-$w/2,$cy-$height/2);
        }else{$this->box(194,$cy+12,54,25,'1 1 1');$this->text('Brand',206,$cy-4,11,true,'0.02 0.30 0.46');}
        $this->text('TRAFFIC MOVEMENT',389,$top-58,8,true);$this->text(is_numeric($v)?sprintf('%+.1f%%',$v):'No comparison',389,$top-84,19,true,'0.02 0.30 0.46');
        $this->text('RETURNING / NEW BALANCE',389,$top-113,7.5,true);$this->text(is_numeric($h)?sprintf('%+.1f%%',$h):'No comparison',389,$top-139,19,true,'0.02 0.30 0.46');
        $this->text('Change from Previous Period',389,$top-165,8);$this->text('Direction only; not a score',389,$top-179,7.5);
        $this->text('Horizontal: change in returning-to-new ratio. Vertical: session change.',55,$top-212,8);
        $this->y=$top-245;
    }
    private function focus_cards($s){
        $m=$s['current']['metrics'];$p=$s['comparison']['metrics'] ?? [];$ch=$s['context']['channels'] ?? [];$top=$this->y;
        $cards=[['Awareness','Facebook views',$ch['facebook']['current']['views']['total'] ?? null,$ch['facebook']['comparison']['views']['total'] ?? null,'Instagram views: '.self::number($ch['instagram']['current']['views']['total'] ?? null)],['Acquisition','Website sessions',$m['sessions'],$p['sessions'] ?? null,'New users: '.self::number($m['new_users'])],['Retention','Returning users',$m['returning_users'],$p['returning_users'] ?? null,'Returning / 100 new: '.self::number($m['new_users']>0 && $m['returning_users']!==null?100*$m['returning_users']/$m['new_users']:null)]];
        foreach($cards as $i=>$r){$x=42+$i*180;$this->box($x,$top,168,119);$this->box($x,$top,168,4,'0.05 0.47 0.66');$this->text(strtoupper($r[0]),$x+10,$top-24,9,true,'0.02 0.30 0.46');$this->text($r[1],$x+10,$top-43,9);$this->text(self::number($r[2]),$x+10,$top-68,21,true,'0.02 0.25 0.37');$this->text('Previous '.self::number($r[3]).' | '.self::change_label($r[2],$r[3]),$x+10,$top-87,8);$this->text($r[4],$x+10,$top-105,8);}
        $this->y=$top-138;
    }
    private function posts_table($label,$r){
        $this->section($label.' - Top 5 posts','Posts published in this period. Ranking uses lifetime views, not period-only views.');
        if(($r['status'] ?? '')!=='complete'){$this->paragraph('Reporting unavailable or not connected.',528,10,42);$this->y-=12;return;}
        $top=$this->y;
        foreach([['Views',$r['current']['views']['total'] ?? null,$r['comparison']['views']['total'] ?? null],['Followers',$r['current']['followers']['total'] ?? null,$r['comparison']['followers']['total'] ?? null],['Net followers',$r['current']['followers']['net'] ?? null,$r['comparison']['followers']['net'] ?? null]] as $i=>$metric){$x=42+$i*176;$this->text($metric[0].': '.self::number($metric[1]),$x,$top,10,true);$this->text('Previous '.self::number($metric[2]).' | '.($i===2?'Change ':'').self::change_label($metric[1],$metric[2],$i===2?'net':''),$x,$top-15,8);}
        $this->y-=36;$rows=array_slice($r['posts']['top'] ?? [],0,5);$top=$this->y;$this->box(42,$top+9,528,24,'0.03 0.25 0.36');
        foreach([['RANK',51],['VIEWS',92],['DATE',151],['POST TITLE',223]] as [$t,$x]){$this->text($t,$x,$top-6,8,true,'1 1 1');}$this->y=$top-15;
        foreach($rows as $i=>$post){if($i%2===0){$this->box(42,$this->y,528,28);}$y=$this->y-15;$this->text((string)($i+1),53,$y,10,true);$this->right(self::number($post['views']),133,$y,10,true);$this->text(self::date($post['date']),151,$y,9);
            $title=preg_split('/[\r\n]+/',trim($post['text']))[0] ?? 'Post';$this->text($this->fit($title,334,9),223,$y,9);$this->y-=28;
        }
        if(!$rows){$this->paragraph('No ranked posts available.',528,9,51);}
        $this->y-=13;if(empty($r['posts']['complete']) || !empty($r['errors'])){$this->text('Some metrics or post rankings are incomplete; unavailable values are not estimated.',42,$this->y,8);$this->y-=15;}
    }
    private function profile_block($label,$rows,$x,$top,$w=168){
        $this->box($x,$top,$w,25,'0.03 0.25 0.36');$this->text(strtoupper($label),$x+9,$top-16,8,true,'1 1 1');$y=$top-25;
        if(!$rows){$this->text('Unavailable / privacy thresholds',$x+9,$y-18,9);return $y-35;}
        foreach(array_slice($rows,0,5) as $i=>$r){if($i%2===0){$this->box($x,$y,$w,22);}$this->text($this->fit($r['label'],$w-60,8),$x+9,$y-15,8);$this->right(self::number($r['sessions']),$x+$w-9,$y-15,8,true);$y-=22;}return $y-10;
    }
    private function report($s){
        $a=$s['current'];$b=$s['comparison'] ?? null;$an=$s['analysis'];$m=$a['metrics'];$p=$b['metrics'] ?? [];$compare=$b!==null;$ch=$s['context']['channels'] ?? [];
        $this->section('Brand Axis - Balance of the Loop','Awareness, Acquisition and Retention work together around the Brand.');$this->axis_panel($s);$this->focus_cards($s);
        $ratio=$an['horizontal'] ?? null;$returnChange=WGPP_Analytics::percent($m['returning_users'],$p['returning_users'] ?? null);
        $message='The returning-to-new ratio describes audience balance, not customer retention or Loyalty.';
        if($ratio!==null && $returnChange!==null && $ratio>0 && $returnChange<0){$message='Returning users fell '.number_format(abs($returnChange),1).'%, while the returning-to-new ratio rose '.number_format($ratio,1).'%. The ratio improved because new users fell faster; this is not growth in returning visitors.';}
        $this->note('Read the movement correctly',$message);
        $this->paragraph('The Brand Axis uses session change and returning-to-new balance as directional evidence. It does not directly measure Brand recall, Loyalty, competitive force or proven engagement. Use the Message Matrix to align Verticals, Buckets and channels with these signals.',510,9,51);
        $this->page();$this->section('Acquisition - Website performance','Visits and recorded actions at the Brand hub.');
        $rows=[];foreach(['sessions'=>'Website sessions','users'=>'Users','new_users'=>'New users','form_events'=>'Form events','phone_events'=>'Phone events'] as $key=>$label){$rows[]=[$label,$m[$key],$p[$key] ?? null];}$rows[]=['Goal events / 100 sessions',$an['goal_rate'],$an['prior_goal_rate']];
        $count=static function($snap,$channel){if(!$snap){return null;}$n=0;foreach($snap['sources'] as $r){if(strcasecmp($r['channel'],$channel)===0){$n+=$r['sessions'];}}return $n;};
        $rows[]=['Organic social sessions',$count($a,'Organic Social'),$count($b,'Organic Social')];$rows[]=['Email-attributed sessions',$count($a,'Email'),$count($b,'Email')];$this->data_table($rows,$compare);
        if(is_numeric($m['form_events'] ?? null) && is_numeric($m['phone_events'] ?? null) && (float)$m['form_events']===0.0 && (float)$m['phone_events']===0.0){$this->note('Measurement check','No form or phone events were recorded. Confirm event setup and collection before using this as evidence of no leads, calls or sales.',true);}
        $this->section('Top 5 traffic sources','Website sessions by source. Totals can include paid traffic; paid strategy is outside this report.');$rows=[];foreach(array_slice($an['sources'],0,5) as $r){$rows[]=[$r['name'],$r['current'],$r['comparison']];}$this->data_table($rows,$compare);
        $this->paragraph('New and returning users may overlap. A session is a visit, not a unique person. Form and phone events count recorded actions, not verified customers or completed calls.',510,9,51);
        $this->page();$this->section('Awareness - Social visibility','Platform views include repeat visibility. Do not add platform counts into a unique audience total.');
        $this->posts_table('Facebook',$ch['facebook'] ?? []);$this->posts_table('Instagram',$ch['instagram'] ?? []);
        // Retention, email and audience occupy a separate evidence page.
        $this->page();$this->section('Retention - Returning audience','Returning visitors indicate repeat website use, not confirmed repeat customers.');
        $ratio=static function($v){return $v && $v['new_users']>0 && $v['returning_users']!==null?100*$v['returning_users']/$v['new_users']:null;};
        $this->data_table([['Returning users',$m['returning_users'],$p['returning_users'] ?? null],['Returning users / 100 new users',$ratio($m),$ratio($p)]],$compare);
        $this->section('Email - Campaign response','Email supports all three stages. Clicks alone do not establish Retention.');$email=$ch['email'] ?? [];$rows=[];
        foreach(['sends'=>'Emails sent','clicks'=>'Email clicks','click_rate'=>'Email click rate'] as $key=>$label){$rows[]=[$label,$email['current']['metrics'][$key] ?? null,$email['comparison']['metrics'][$key] ?? null,$key==='click_rate'?'%':''];}$this->data_table($rows,$compare);
        $this->paragraph(($email['basis'] ?? 'Email reporting unavailable or not connected.').' Clicks may include automated activity. Rate changes are percentage points (pp).',510,8,51);$this->y-=12;
        $this->section('Audience profile','Website sessions; top reported rows. Omitted or withheld rows are not zero.');$top=$this->y;
        $country=$this->profile_block('Country',$a['profile']['country'] ?? [],42,$top);
        $city=$this->profile_block('City',$a['profile']['city'] ?? [],222,$top);
        $device=$this->profile_block('Device',$a['profile']['device'] ?? [],402,$top);$this->y=min($country,$city,$device)-4;
        foreach(['age','gender'] as $key){$rows=$a['profile'][$key] ?? [];$parts=[];foreach(array_slice($rows,0,5) as $r){$parts[]=$r['label'].': '.self::number($r['sessions']);}$this->text($this->fit(ucfirst($key).': '.($parts?implode(' | ',$parts):'Unavailable / privacy thresholds.'),528,8),42,$this->y,8);$this->y-=13;}
        $this->y-=5;
        // Measurement notes remain concise and travel with the report.
        $this->section('Measurement notes');
        $this->paragraph('GA4 excludes known bots but does not supply excluded volume. Additional bot screening and service-area filtering are not applied. Geography alone does not prove bot activity. Recent GA4 data may be revised; privacy thresholds, sampling and row limits may apply. -- means unavailable or no comparison; no missing values are estimated.',528,8,42);
    }
}
