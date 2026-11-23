<?php
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$file=realpath('/var/www/html'.rawurldecode($path));
if($file&&strpos($file,'/var/www/html/')===0&&is_file($file))return false;
require '/var/www/html/index.php';
