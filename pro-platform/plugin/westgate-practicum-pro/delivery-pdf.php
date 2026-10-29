<?php
if (!defined('ABSPATH')) { exit; }

require_once __DIR__.'/delivery-layout.php';

/** Small native PDF renderer: selectable WinAnsi text, vector headings and pagination. */
final class WGPP_Delivery_PDF {
    use WGPP_Delivery_Layout;
    private $pages=[],$stream='',$y=0,$company,$title,$logo=null,$period='';
    private function __construct($company,$title){$this->company=$company;$this->title=$title;}
    private static function logo($company) {
        if(!$company || !function_exists('get_attached_file')){return null;}
        try{$brand=WGPP_Company::brand($company);$path=get_attached_file((int)($brand['logo_id'] ?? 0));
            if(!$path || !is_file($path) || filesize($path)>2097152){return null;}
            $size=@getimagesize($path);if(!$size || $size[0]>5000 || $size[1]>5000 || !in_array($size[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG],true)){return null;}
            if($size[2]===IMAGETYPE_JPEG){return ['width'=>$size[0],'height'=>$size[1],'bytes'=>file_get_contents($path)];}
            if(!function_exists('imagecreatefromstring')){return null;}
            $source=@imagecreatefromstring(file_get_contents($path));if(!$source){return null;}
            $w=min(240,$size[0]);$h=max(1,(int)round($w*$size[1]/$size[0]));$image=imagecreatetruecolor($w,$h);imagefill($image,0,0,imagecolorallocate($image,255,255,255));imagecopyresampled($image,$source,0,0,0,0,$w,$h,$size[0],$size[1]);ob_start();imagejpeg($image,null,85);$bytes=ob_get_clean();imagedestroy($source);imagedestroy($image);return ['width'=>$w,'height'=>$h,'bytes'=>$bytes];
        }catch(Throwable $e){return null;}
    }
    public static function plain($text) {
        $text=html_entity_decode(strip_tags((string)$text),ENT_QUOTES|ENT_HTML5,'UTF-8');
        $text=preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}]/u','',$text);
        $text=str_replace(["\r","\t",'→','↑','↓','←','…','–','—'],['',' ',' to ','up ','down ','left ','...','-','-'],$text);
        if(function_exists('iconv')){$converted=iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$text);if($converted!==false){return preg_replace('/[\x00-\x08\x0b-\x1f\x7f]/','',$converted);}}
        return preg_replace('/[^\x20-\x7e\n]/','?',$text);
    }
    private static function escape($s){return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$s);}
    private function text($s,$x,$y,$size=10,$bold=false,$color='0.13 0.20 0.24') {
        $this->stream.=$color." rg BT /".($bold?'F2':'F1')." $size Tf 0 Tc 0 Tw 100 Tz 1 0 0 1 $x $y Tm (".self::escape($s).") Tj ET\n";
    }
    private function page() {
        if($this->stream!==''){$this->pages[]=$this->stream;}$this->stream='';
        $this->text(self::plain('SOCIAL MEDIA MAGNET PRO'),42,760,8,true,'0.02 0.30 0.46');
        $this->text($this->fit($this->company,440,22),42,732,22,true,'0.02 0.30 0.46');
        $this->text(self::plain($this->title),42,712,12,true,'0.02 0.30 0.46');
        $this->stream.="0.15 0.66 0.83 RG 1.5 w 42 700 m 570 700 l S\n";$this->y=677;if($this->period!==''){$this->text(self::plain($this->period),42,681,8);$this->y=656;}
        if($this->logo){$scale=min(60/$this->logo['width'],42/$this->logo['height']);$w=$this->logo['width']*$scale;$h=$this->logo['height']*$scale;$this->stream.=sprintf("q %.2f 0 0 %.2f 510 718 cm /Logo Do Q\n",$w,$h);}
    }
    private function lines($text,$size=10,$bold=false,$color='0.13 0.20 0.24') {
        $paragraphs=explode("\n",self::plain($text));
        foreach($paragraphs as $p){
            // Conservative maximum glyph width prevents clipping, including long URLs.
            $wrap=explode("\n",wordwrap($p,(int)floor(525/($size*0.62)),"\n",true));
            foreach($wrap as $line){if($this->y<63){$this->page();}$this->text($line,42,$this->y,$size,$bold,$color);$this->y-=$size+4;}
        }
        $this->y-=5;
    }
    private function heading($title){if($this->y<115){$this->page();}$this->y-=7;$this->lines($title,13,true,'0.02 0.30 0.46');}
    private static function number($v,$unit=''){return is_numeric($v)?number_format((float)$v,$unit==='%'?1:((floor((float)$v)!=(float)$v)?2:0)).$unit:'Unavailable';}
    private function metric($title,$a,$b=null,$compare=false,$unit='') {
        $text=$title.': '.self::number($a,$unit);
        if($compare){$change=WGPP_Analytics::percent($a,$b);$text.='   |   Previous: '.self::number($b,$unit).'   |   Change: '.($change===null?'Unavailable':sprintf('%+.1f%%',$change));}
        $this->lines($text);
    }
    private function website($s) {
        $a=$s['current'];$b=$s['comparison'] ?? null;$analysis=$s['analysis'];$m=$a['metrics'];$p=$b['metrics'] ?? [];$compare=$b!==null;
        $this->heading('Brand Axis - Balance of the Loop');
        $this->metric('Website traffic movement',$analysis['vertical'] ?? null,null,false,'%');
        $this->metric('Returning-to-new user balance movement',$analysis['horizontal'] ?? null,null,false,'%');
        $this->lines('Read Awareness, Acquisition and Retention together. Traffic and audience balance indicate movement; they do not directly measure Loyalty, Brand recall or competitive force. Align the Message Matrix with the Brand Axis.');
        $channels=$s['context']['channels'] ?? [];
        $this->heading('Awareness - Earn attention for the Brand');
        foreach(['facebook'=>'Facebook','instagram'=>'Instagram'] as $key=>$label){$r=$channels[$key] ?? [];if(($r['status'] ?? '')!=='complete'){$this->lines($label.': Reporting unavailable / not connected for these dates.');continue;}
            foreach(['views'=>'Views','followers'=>'Followers'] as $kind=>$name){$this->metric($label.' '.$name,$r['current'][$kind]['total'] ?? null,$r['comparison'][$kind]['total'] ?? null,$compare);}
            $this->metric($label.' net followers',$r['current']['followers']['net'] ?? null,$r['comparison']['followers']['net'] ?? null,$compare);
            if(!empty($r['errors'])){$this->lines($label.': Some metrics unavailable; provider reporting was incomplete.');}
        }
        $this->lines('Views include repeat visibility, not unique people or proven engagement. Platform counts remain separate.');
        $this->heading('Acquisition - Bring people into the Brand hub');
        foreach(['sessions'=>'Website sessions','users'=>'Users','new_users'=>'New users','form_events'=>'Form events','phone_events'=>'Phone events'] as $key=>$label){$this->metric($label,$m[$key],$p[$key] ?? null,$compare);}
        $this->metric('Goal events / 100 sessions',$analysis['goal_rate'],$analysis['prior_goal_rate'],$compare);
        $this->heading('Retention - Give people a reason to return');
        $this->metric('Returning users',$m['returning_users'],$p['returning_users'] ?? null,$compare);
        $this->metric('Returning users / 100 new users',$m['new_users']>0&&$m['returning_users']!==null?100*$m['returning_users']/$m['new_users']:null,isset($p['new_users'])&&$p['new_users']>0&&$p['returning_users']!==null?100*$p['returning_users']/$p['new_users']:null,$compare);
        foreach(['Organic Social'=>'Organic social sessions','Email'=>'Email-attributed sessions'] as $group=>$label){$count=static function($snap)use($group){if(!$snap){return null;}$sum=0;foreach($snap['sources'] as $r){if(strcasecmp($r['channel'],$group)===0){$sum+=$r['sessions'];}}return $sum;};$this->metric($label,$count($a),$count($b),$compare);}
        $email=$channels['email'] ?? [];
        if(($email['status'] ?? '')==='complete'){
            foreach(['sends'=>'Emails sent','clicks'=>'Email clicks','click_rate'=>'Email click rate'] as $key=>$label){$this->metric($label,$email['current']['metrics'][$key] ?? null,$email['comparison']['metrics'][$key] ?? null,$compare,$key==='click_rate'?'%':'');}
            $this->lines($email['basis'] ?? 'Campaign responses are cumulative through collection, not period-only responses.');
        }else{$this->lines('Email campaign statistics: Unavailable / not connected.');}
        $this->lines('Returning users are website visitors, not confirmed repeat customers. Email clicks are not returning users and may include automated activity.');
        $this->heading('Traffic sources - Top five');
        foreach(array_slice($analysis['sources'],0,5) as $r){$this->metric($r['name'],$r['current'],$r['comparison'],$compare);}
        $this->lines('Website totals include any paid traffic; paid media strategy is outside this report.');
        foreach(['facebook'=>'Facebook','instagram'=>'Instagram'] as $key=>$label){$r=$channels[$key] ?? [];if(($r['status'] ?? '')!=='complete'){continue;}$this->heading($label.' - Top five posts by lifetime views');
            foreach($r['posts']['top'] ?? [] as $i=>$post){$this->lines(($i+1).'. '.self::number($post['views']).' views | '.self::date($post['date']).' | '.$post['text']);}
            $this->lines('Posts published in the reporting range; views are lifetime counts, not views earned only in this period. '.(empty($r['posts']['complete'])?'Ranking may be incomplete.':''));
        }
        $this->heading('Audience profile');
        foreach(['age','gender','country','city','device'] as $key){$rows=$a['profile'][$key] ?? null;$this->lines(ucfirst($key),10,true);if(!$rows){$this->lines('Unavailable / privacy thresholds.');continue;}foreach(array_slice($rows,0,5) as $r){$this->lines($r['label'].': '.self::number($r['sessions']).' sessions');}}
        $this->heading('Traffic quality and measurement notes');
        $this->lines('Known bots: GA4 automatically excludes known bots; excluded volume is not supplied. Additional screening: not connected. Remaining visits are not verified human. Service-area filtering: not applied; out-of-market visits remain included. Geography alone does not establish bot activity.');
        foreach($a['notes'] ?? [] as $note){$this->lines($note);}
        $this->lines('Form and phone events are recorded events, not unique leads or verified completed calls. Pages, funnels and time on site are not supplied by this reporting contract. Unavailable evidence is not estimated.');
    }
    public static function date($s){try{return (new DateTimeImmutable($s))->format('m/d/Y');}catch(Throwable $e){return 'Unavailable';}}
    private function review($review) {
        $this->heading('The priority');$this->lines($review['summary'] ?? '');
        foreach(['stage_assessments'=>'Awareness, Acquisition and Retention','foundation_alignment'=>'Brand foundations','content_focus'=>'Message Matrix - Give the campaign dimension','findings'=>'What the results show','actions'=>'Moves to Make'] as $key=>$title){
            if(empty($review[$key])){continue;}$this->heading($title);$this->tree($review[$key]);
        }
        if(!empty($review['framework_sections'])){$this->heading('Why these moves - The SMM foundation');$this->tree($review['framework_sections']);}
        if(!empty($review['limitations'])){$this->heading('Limitations');$this->tree($review['limitations']);}
        $this->heading('About Social Media Magnet Pro');
        $this->lines('Created by author and marketing consultant J. Elliott Cunningham, Social Media Magnet connects Brand strategy, dimensional messaging and a coordinated publishing calendar. The approach has been deployed with live companies, taught in universities and used by more than a thousand program participants. The Brand Axis and Message Matrix work together to develop Awareness, Acquisition and Retention.');
    }
    private function tree($v,$depth=0) {
        if($depth>5){return;}if(is_scalar($v)){$this->lines((string)$v);return;}if(!is_array($v)){return;}
        foreach($v as $k=>$value){if(in_array($k,['id','sha256','post_refs','_foundation_files'],true)){continue;}if(is_string($k)){$this->lines(ucwords(str_replace('_',' ',$k)),10,true);}$this->tree($value,$depth+1);}
    }
    public static function build($company,$payload,$companyId=0) {
        $pdf=new self($company,isset($payload['review'])?'Pro Analytics Review':'Pro Analytics Report');$pdf->logo=self::logo($companyId);$s=$payload['snapshot'];$pdf->period=implode(' to ',array_map([__CLASS__,'date'],$s['ranges']['current'])).($s['ranges']['comparison']?' | Previous: '.implode(' to ',array_map([__CLASS__,'date'],$s['ranges']['comparison'])):' | Comparison off');$pdf->page();
        if(!isset($payload['review'])){$pdf->report($s);}else{
        $pdf->lines('Reporting period: '.implode(' to ',array_map([__CLASS__,'date'],$s['ranges']['current'])),10,true);
        if($s['ranges']['comparison']){$pdf->lines('Previous: '.implode(' to ',array_map([__CLASS__,'date'],$s['ranges']['comparison'])));}
        $pdf->lines('Prepared: '.WGPP_Report_Delivery::stamp(strtotime($payload['created'])).' | Data timezone: '.($s['current']['timezone'] ?? 'Unavailable'));
        if(isset($payload['review'])){
            $pdf->heading('Brand Axis');$pdf->metric('Traffic movement',$s['analysis']['vertical'] ?? null,null,false,'%');$pdf->metric('Audience balance movement',$s['analysis']['horizontal'] ?? null,null,false,'%');$pdf->review($payload['review']);$pdf->heading('Supporting reporting evidence');
        }
        $pdf->website($s);}
        $pdf->pages[]=$pdf->stream;
        $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',2=>'',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',4=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];$kids=[];
        foreach($pdf->pages as $i=>$stream){$page=5+$i*2;$content=$page+1;$kids[]="$page 0 R";$pdf->stream='';$pdf->text('PRIVATE COMPANY REPORT | Page '.($i+1).' of '.count($pdf->pages),42,32,8,false,'0.35 0.43 0.48');$pdf->text(self::plain('Prepared: '.WGPP_Report_Delivery::stamp(strtotime($payload['created']))),285,32,7,false,'0.35 0.43 0.48');$pdf->text(self::plain('Data timezone: '.($s['current']['timezone'] ?? 'Unavailable')),285,21,7,false,'0.35 0.43 0.48');$stream.=$pdf->stream;
            $objects[$page]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> '.($pdf->logo?'/XObject << /Logo '.(5+count($pdf->pages)*2).' 0 R >> ':'').'>> /Contents '.$content.' 0 R >>';$objects[$content]='<< /Length '.strlen($stream).">>\nstream\n".$stream."endstream";
        }
        $imageId=5+count($pdf->pages)*2;if($pdf->logo){$image=$pdf->logo;$objects[$imageId]='<< /Type /XObject /Subtype /Image /Width '.$image['width'].' /Height '.$image['height'].' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($image['bytes']).">>\nstream\n".$image['bytes']."\nendstream";}
        foreach(self::fonts() as $key=>$font){
            $fileId=count($objects)+1;$raw=base64_decode($font['bytes'],true);$objects[$fileId]='<< /Length '.strlen($raw).' /Length1 '.strlen($raw).">>\nstream\n".$raw."\nendstream";
            $descriptor=$fileId+1;$objects[$descriptor]='<< /Type /FontDescriptor /FontName /'.$font['name'].' /Flags 32 /FontBBox [-1100 -500 2100 1400] /ItalicAngle 0 /Ascent 928 /Descent -236 /CapHeight 729 /StemV 80 /FontFile2 '.$fileId.' 0 R >>';
            $objects[$key==='bold'?4:3]='<< /Type /Font /Subtype /TrueType /BaseFont /'.$font['name'].' /FirstChar 32 /LastChar 255 /Widths ['.implode(' ',$font['widths']).'] /Encoding /WinAnsiEncoding /FontDescriptor '.$descriptor.' 0 R >>';
        }
        $info=count($objects)+1;$objects[$info]='<< /Title ('.self::escape(self::plain($company.' - '.$pdf->title)).') /Author (Social Media Magnet Pro) >>';
        $objects[2]='<< /Type /Pages /Count '.count($kids).' /Kids ['.implode(' ',$kids).'] >>';ksort($objects);$out="%PDF-1.4\n%\xe2\xe3\xcf\xd3\n";$offsets=[0];foreach($objects as $id=>$object){$offsets[$id]=strlen($out);$out.="$id 0 obj\n$object\nendobj\n";}$xref=strlen($out);$out.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";foreach(array_slice($offsets,1) as $offset){$out.=sprintf("%010d 00000 n \n",$offset);}$out.='trailer << /Size '.(count($objects)+1)." /Root 1 0 R /Info $info 0 R >>\nstartxref\n$xref\n%%EOF\n";return $out;
    }
}
