<?php 
require_once __DIR__.'/../vendor/autoload.php';

use cosmos\database\MysqliDatabase;

$mysqli = new mysqli('localhost', 'my_user', 'my_password', 'my_db');
$mdb = new MysqliDatabase($mysqli);

echo $mdb->stat();
?>