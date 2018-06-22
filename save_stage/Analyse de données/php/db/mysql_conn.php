<?php

$mysql_host = "localhost";
$mysql_dbname = "analyse_donnees";
$mysql_login = "root";
$mysql_password = "";

$mysql_conn = new PDO("mysql:host=$mysql_host;dbname=$mysql_dbname", "$mysql_login", "$mysql_password", array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));

?>
