<?php
defined('ABSPATH') || exit;

/** Fixed licensed font, independent of host OS fonts; no request-supplied paths. */
function sanro_studio_production_font() {
    $path=__DIR__.'/../assets/fonts/DejaVuSans.ttf';
    return is_file($path) && !is_link($path) && hash_file('sha256',$path)==='57f73e11f51999432bf7ab22ce55b6f945d5eca1bf824404cfa9ec2e3718c84e' ? $path : false;
}

/** Strict UTF-8 decoding without mbstring. Keep line breaks for explicit layout. */
function sanro_studio_unicode_chars($text) {
    if (!is_string($text) || strlen($text)>200000 || !preg_match('//u',$text)) return false;
    $chars=preg_split('//u',$text,-1,PREG_SPLIT_NO_EMPTY);$out=[];
    foreach($chars as $char){$b=array_values(unpack('C*',$char));$cp=$b[0];
        if($cp>=240)$cp=(($b[0]&7)<<18)|(($b[1]&63)<<12)|(($b[2]&63)<<6)|($b[3]&63);
        elseif($cp>=224)$cp=(($b[0]&15)<<12)|(($b[1]&63)<<6)|($b[2]&63);
        elseif($cp>=192)$cp=(($b[0]&31)<<6)|($b[1]&63);
        if(($cp<32&&!in_array($cp,[9,10,13],true))||$cp===127)return false;
        $out[]=['text'=>$char,'cp'=>$cp];
    }return $out;
}

/** Parse bounded trusted TrueType metrics/cmap; missing glyphs never become tofu. */
function sanro_studio_font_metrics() {
    static $cached=null;if($cached!==null)return $cached;
    $path=sanro_studio_production_font();if(!$path)return false;$data=file_get_contents($path);
    $u16=static function($at)use($data){return unpack('n',substr($data,$at,2))[1];};
    $u32=static function($at)use($data){return unpack('N',substr($data,$at,4))[1];};
    $tables=[];for($i=0,$n=$u16(4);$i<$n;$i++){$at=12+$i*16;$off=$u32($at+8);$len=$u32($at+12);if($off+$len>strlen($data))return false;$tables[substr($data,$at,4)]=[$off,$len];}
    foreach(['head','hhea','hmtx','cmap'] as $table)if(!isset($tables[$table]))return false;
    $map=[];$cm=$tables['cmap'][0];
    for($i=0,$n=$u16($cm+2);$i<$n;$i++){
        $sub=$cm+$u32($cm+4+$i*8+4);$format=$u16($sub);
        if($format===4){$count=$u16($sub+6)/2;$end=$sub+14;$start=$end+2*$count+2;$delta=$start+2*$count;$range=$delta+2*$count;
            for($j=0;$j<$count;$j++)for($cp=$u16($start+2*$j),$last=$u16($end+2*$j);$cp<=$last&&$cp<65535;$cp++){
                $r=$u16($range+2*$j);$at=$range+2*$j+$r+2*($cp-$u16($start+2*$j));
                $gid=$r?($at+2<=strlen($data)?$u16($at):0):$cp;
                if($gid)$gid=($gid+$u16($delta+2*$j))&65535;
                if($gid)$map[$cp]=$gid;
            }
        }elseif($format===12){for($j=0,$groups=$u32($sub+12);$j<$groups;$j++){$at=$sub+16+$j*12;$a=$u32($at);$z=$u32($at+4);$gid=$u32($at+8);if($z-$a>200000)return false;for($cp=$a;$cp<=$z;$cp++)if($gid+$cp-$a<65536)$map[$cp]=$gid+$cp-$a;}}
    }
    $head=$tables['head'][0];$units=$u16($head+18);$hhea=$tables['hhea'][0];$widths=[];
    for($i=0,$n=$u16($hhea+34);$i<$n;$i++)$widths[$i]=$u16($tables['hmtx'][0]+$i*4);
    $signed=static function($v){return $v>32767?$v-65536:$v;};
    return $cached=['data'=>$data,'map'=>$map,'units'=>$units,'widths'=>$widths,'bbox'=>array_map($signed,[$u16($head+36),$u16($head+38),$u16($head+40),$u16($head+42)])];
}
function sanro_studio_text_glyphs_supported($text) {
    $chars=sanro_studio_unicode_chars($text);$font=sanro_studio_font_metrics();if($chars===false||!$font)return false;
    foreach($chars as $char)if(!in_array($char['cp'],[9,10,13],true)&&empty($font['map'][$char['cp']]))return false;
    // GD/PDF v1 has no complex shaping engine: never reorder or drop such text.
    return !preg_match('/\p{M}|[\x{0590}-\x{109F}\x{1780}-\x{17FF}\x{200C}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u',$text);
}
