<?php
define('ABSPATH',__DIR__);require __DIR__.'/../plugin/westgate-practicum-pro/pdf-logo.php';
function check($ok,$message){if(!$ok){throw new RuntimeException($message);}echo "PASS $message\n";}
$rgb='';for($y=0;$y<5;$y++){for($x=0;$x<5;$x++){$ring=$x>=1&&$x<=3&&$y>=1&&$y<=3&&!($x===2&&$y===2);$rgb.=$ring?"\xdd\x22\x22":"\xff\xff\xff";}}
$logo=WGPP_PDF_Logo::pixels(5,5,$rgb,str_repeat("\xff",25));$alpha=gzuncompress($logo['alpha']);
check($alpha[0]==="\0"&&$alpha[24]==="\0",'Edge-connected white background becomes transparent');
check($alpha[12]==="\xff"&&$alpha[6]==="\xff",'Enclosed white artwork and colored artwork remain opaque');
$existing=str_repeat("\xff",25);$existing[0]="\0";$existing[1]=chr(110);$logo=WGPP_PDF_Logo::pixels(5,5,$rgb,$existing);
check(gzuncompress($logo['alpha'])===$existing,'Existing partial transparency and intentional white details are preserved');
check(WGPP_PDF_Logo::pixels(5,5,'short','short')===null,'Invalid pixel planes are rejected');
function chunk($type,$bytes){return pack('N',strlen($bytes)).$type.$bytes.pack('N',crc32($type.$bytes));}
function png($type,$rows,$extra=''){return "\x89PNG\r\n\x1a\n".chunk('IHDR',pack('NNCCCCC',5,5,8,$type,0,0,0)).$extra.chunk('IDAT',gzcompress($rows)).chunk('IEND','');}
$dir=sys_get_temp_dir().'/wgpp-logo-'.bin2hex(random_bytes(6));mkdir($dir);
try{
 $rows='';for($y=0;$y<5;$y++){$rows.="\0".substr($rgb,$y*15,15);}file_put_contents($dir.'/rgb.png',png(2,$rows));$logo=WGPP_PDF_Logo::load($dir.'/rgb.png');check($logo && gzuncompress($logo['alpha'])[0]==="\0",'PNG file loader removes only the outer white background');
 $rows='';for($y=0;$y<5;$y++){$rows.="\0";for($x=0;$x<5;$x++){$i=$y*5+$x;$rows.=substr($rgb,$i*3,3).$existing[$i];}}file_put_contents($dir.'/rgba.png',png(6,$rows));$logo=WGPP_PDF_Logo::load($dir.'/rgba.png');$a=gzuncompress($logo['alpha']);check($a[0]==="\0"&&abs(ord($a[1])-110)<=1&&$a[12]==="\xff",'RGBA file loader preserves alpha and opaque white artwork');
 $rows=str_repeat("\0".str_repeat("\0",5),5);file_put_contents($dir.'/palette.png',png(3,$rows,chunk('PLTE',"\xff\xff\xff").chunk('tRNS',"\0")));$logo=WGPP_PDF_Logo::load($dir.'/palette.png');check($logo&&gzuncompress($logo['alpha'])===str_repeat("\0",25),'Palette PNG transparency is preserved');
 if(function_exists('imagecreatefromstring')){
  $im=imagecreatefromstring(file_get_contents($dir.'/rgb.png'));imagejpeg($im,$dir.'/logo.jpg',100);imagewebp($im,$dir.'/logo.webp',IMG_WEBP_LOSSLESS);imagedestroy($im);
  foreach(['jpg','webp'] as $format){$logo=WGPP_PDF_Logo::load($dir.'/logo.'.$format);check($logo&&gzuncompress($logo['alpha'])[0]==="\0",strtoupper($format).' legacy white background is normalized to alpha');}
 }
}finally{foreach(glob($dir.'/*') as $p){unlink($p);}rmdir($dir);}
