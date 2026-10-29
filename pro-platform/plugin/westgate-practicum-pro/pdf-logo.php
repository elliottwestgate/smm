<?php
if(!defined('ABSPATH')){exit;}

/** Alpha-aware PDF logos. Stored company uploads are never modified. */
final class WGPP_PDF_Logo {
    public static function load($path){
        if(!is_string($path)||!is_file($path)||filesize($path)>2097152){return null;}
        $size=@getimagesize($path);if(!$size || $size[0]>4000 || $size[1]>4000){return null;}
        if(function_exists('imagecreatefromstring')){
            $source=@imagecreatefromstring(file_get_contents($path));if(!$source){return null;}
            $scale=min(1,240/max($size[0],$size[1]));$w=max(1,(int)round($size[0]*$scale));$h=max(1,(int)round($size[1]*$scale));
            $image=imagecreatetruecolor($w,$h);imagealphablending($image,false);imagesavealpha($image,true);imagefill($image,0,0,imagecolorallocatealpha($image,0,0,0,127));
            imagecopyresampled($image,$source,0,0,0,0,$w,$h,$size[0],$size[1]);$rgb=$alpha='';
            for($y=0;$y<$h;$y++){for($x=0;$x<$w;$x++){$c=imagecolorsforindex($image,imagecolorat($image,$x,$y));$rgb.=chr($c['red']).chr($c['green']).chr($c['blue']);$alpha.=chr((int)round((127-$c['alpha'])*255/127));}}
            imagedestroy($source);imagedestroy($image);return self::pixels($w,$h,$rgb,$alpha);
        }
        // Portable path for common PNGs, so preserving alpha does not require GD.
        return $size[2]===IMAGETYPE_PNG?self::png(file_get_contents($path)):null;
    }
    public static function pixels($w,$h,$rgb,$alpha){
        if($w<1||$h<1||$w*$h>1000000||strlen($rgb)!==$w*$h*3||strlen($alpha)!==$w*$h){return null;}
        // Existing transparency wins: white artwork inside a transparent logo stays white.
        if(strspn($alpha,"\xff")!==strlen($alpha)){$clean=$alpha;}
        else{
            // Remove only near-white connected to an outer edge, never isolated white details.
            $clean=$alpha;$seen=str_repeat("\0",$w*$h);$queue=[];
            $visit=static function($i)use(&$queue,&$seen,&$clean,$rgb){if($seen[$i]!=="\0"){return;}$seen[$i]="\1";$n=$i*3;if(ord($rgb[$n])>=245&&ord($rgb[$n+1])>=245&&ord($rgb[$n+2])>=245){$clean[$i]="\0";$queue[]=$i;}};
            for($x=0;$x<$w;$x++){$visit($x);$visit(($h-1)*$w+$x);}for($y=0;$y<$h;$y++){$visit($y*$w);$visit($y*$w+$w-1);}
            while($queue){$i=array_pop($queue);$x=$i%$w;if($x>0){$visit($i-1);}if($x<$w-1){$visit($i+1);}if($i>=$w){$visit($i-$w);}if($i<$w*($h-1)){$visit($i+$w);}}
        }
        return ['width'=>$w,'height'=>$h,'bytes'=>gzcompress($rgb),'alpha'=>gzcompress($clean),'filter'=>'FlateDecode'];
    }
    private static function png($bytes){
        if(substr($bytes,0,8)!=="\x89PNG\r\n\x1a\n"){return null;}$offset=8;$data='';$palette=$trans='';$header=null;
        while($offset+12<=strlen($bytes)){$length=unpack('N',substr($bytes,$offset,4))[1];$type=substr($bytes,$offset+4,4);$chunk=substr($bytes,$offset+8,$length);$offset+=12+$length;if($offset>strlen($bytes)){return null;}if($type==='IHDR'){$header=unpack('Nw/Nh/Cdepth/Ctype/Ccompression/Cfilter/Cinterlace',$chunk);}elseif($type==='IDAT'){$data.=$chunk;}elseif($type==='PLTE'){$palette=$chunk;}elseif($type==='tRNS'){$trans=$chunk;}elseif($type==='IEND'){break;}}
        if(!$header||$header['depth']!==8||$header['interlace']!==0||$header['compression']!==0||$header['filter']!==0||$header['w']<1||$header['h']<1||$header['w']*$header['h']>1000000){return null;}
        $channels=[0=>1,2=>3,3=>1,4=>2,6=>4][$header['type']] ?? 0;if(!$channels){return null;}$stride=$header['w']*$channels;$raw=@gzuncompress($data,($stride+1)*$header['h']);if($raw===false||strlen($raw)!==($stride+1)*$header['h']){return null;}
        $decoded='';$prior=str_repeat("\0",$stride);$at=0;
        for($y=0;$y<$header['h'];$y++){$filter=ord($raw[$at++]);if($filter>4){return null;}$row=substr($raw,$at,$stride);$at+=$stride;
            for($x=0;$x<$stride;$x++){$a=$x>=$channels?ord($row[$x-$channels]):0;$b=ord($prior[$x]);$c=$x>=$channels?ord($prior[$x-$channels]):0;$predict=0;
                if($filter===1){$predict=$a;}elseif($filter===2){$predict=$b;}elseif($filter===3){$predict=(int)floor(($a+$b)/2);}elseif($filter===4){$p=$a+$b-$c;$pa=abs($p-$a);$pb=abs($p-$b);$pc=abs($p-$c);$predict=$pa<=$pb&&$pa<=$pc?$a:($pb<=$pc?$b:$c);}
                $row[$x]=chr((ord($row[$x])+$predict)&255);
            }$decoded.=$row;$prior=$row;
        }
        $scale=min(1,240/max($header['w'],$header['h']));$w=max(1,(int)round($header['w']*$scale));$h=max(1,(int)round($header['h']*$scale));$rgb=$alpha='';
        for($y=0;$y<$h;$y++){for($x=0;$x<$w;$x++){$n=((int)floor($y*$header['h']/$h)*$header['w']+(int)floor($x*$header['w']/$w))*$channels;$pixel=substr($decoded,$n,$channels);$a=255;
                if($header['type']===6){$rgb.=substr($pixel,0,3);$a=ord($pixel[3]);}elseif($header['type']===2){$rgb.=$pixel;if(strlen($trans)===6&&$pixel===chr(ord($trans[1])).chr(ord($trans[3])).chr(ord($trans[5]))){$a=0;}}
                elseif($header['type']===3){$index=ord($pixel[0]);$color=substr($palette,$index*3,3);if(strlen($color)!==3){return null;}$rgb.=$color;$a=isset($trans[$index])?ord($trans[$index]):255;}
                else{$rgb.=str_repeat($pixel[0],3);$a=$header['type']===4?ord($pixel[1]):(strlen($trans)===2&&ord($pixel[0])===ord($trans[1])?0:255);}$alpha.=chr($a);
            }}return self::pixels($w,$h,$rgb,$alpha);
    }
}
