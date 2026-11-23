<?php
// Shared in-memory SQL adapter for isolated legacy Drive harnesses.
class DriveLeaseFixtureDB {
    public $options='wp_options';
    function prepare($sql,...$args){return str_starts_with($sql,'INSERT IGNORE')?['insert'=>$args]:$args;}
    function query($args){$name=$GLOBALS['drive_lease_fixture_store'];$store=&$GLOBALS[$name];
        if(isset($args['insert'])){[$key,$value]=$args['insert'];if(isset($store[$key]))return 0;$store[$key]=unserialize($value);return 1;}
        if(count($args)===2){[$key,$before]=$args;if(serialize($store[$key]??false)!==$before)return 0;unset($store[$key]);return 1;}
        [$after,$key,$before]=$args;if(serialize($store[$key]??false)!==$before)return 0;$store[$key]=unserialize($after);return 1;
    }
}
$GLOBALS['wpdb']=new DriveLeaseFixtureDB();
