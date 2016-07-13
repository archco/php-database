<?php 
require_once __DIR__.'/../vendor/autoload.php';
include_once 'db_config.php';

use cosmos\database\MysqliDatabase;

$mysqli = new mysqli($host, $user, $password, $db_name);
$mdb = new MysqliDatabase($mysqli);

echo $mdb->stat();
?>