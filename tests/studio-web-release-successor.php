<?php
require __DIR__.'/../tools/staging-web-release/transaction.php';
$m=['source_commit'=>str_repeat('a',40),'archive_sha256'=>str_repeat('b',64),'plugin_digest'=>str_repeat('c',64),'files'=>[['path'=>'sanro-studio.php','sha256'=>hash('sha256','new')]],'build_marker'=>['path'=>'SANRO-STUDIO-BUILD.json','sha256'=>hash('sha256','marker')]];
$pairs=[['SANRO-STUDIO-BUILD.json',hash('sha256','marker')],['sanro-studio.php',hash('sha256','new')]];
$d=hash('sha256',json_encode($pairs,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
$j=['phase'=>'committed','candidate_digest'=>$d]+array_intersect_key($m,array_flip(['source_commit','archive_sha256','plugin_digest']));
if(!sswr_candidate_installed($j,$d,$m))throw new RuntimeException('installed candidate not recognized');
foreach(['source_commit','archive_sha256','plugin_digest','candidate_digest','phase'] as $key){$old=$j;$old[$key]='older';if(sswr_candidate_installed($old,$d,$m))throw new RuntimeException('predecessor mistaken for new candidate: '.$key);}
$changed=$m;$changed['build_marker']['sha256']=str_repeat('d',64);if(sswr_candidate_installed($j,$d,$changed))throw new RuntimeException('wrong marker accepted');
if(sswr_candidate_installed(null,$d,$m))throw new RuntimeException('missing journal accepted');
echo "PASS: successor install distinguishes old committed candidate; exact source/archive/plugin/marker/tree binding\n";
