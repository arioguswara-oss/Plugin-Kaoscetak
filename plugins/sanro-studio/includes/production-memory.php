<?php
defined('ABSPATH') || exit;

/** No memory-limit changes in application code; refuse before allocating large buffers. */
function sanro_studio_memory_bytes_available($extra) {
    if(!is_int($extra)||$extra<0)return false;$limit=trim((string)ini_get('memory_limit'));if($limit==='-1')return true;
    $bytes=(int)$limit;$suffix=strtolower(substr($limit,-1));if($suffix==='g')$bytes*=1073741824;elseif($suffix==='m')$bytes*=1048576;elseif($suffix==='k')$bytes*=1024;
    return $bytes>memory_get_usage(true)+$extra;
}
