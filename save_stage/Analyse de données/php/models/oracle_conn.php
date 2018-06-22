<?php

$ville = $_GET["ville"];

$host = "zeus";
$port = "1521";
$service = "orcl";
$login = "geodp" . strtolower($ville);
$password = $login;
$oracle_conn = NULL;

try {
    $oracle_conn = new PDO("oci:dbname=(DESCRIPTION = (ADDRESS_LIST = (ADDRESS = (PROTOCOL = TCP) (Host = $host) (Port = $port))) (CONNECT_DATA = (SERVICE_NAME = ".$service.")));charset=UTF8", $login, $password);
} catch (PDOException $e) {
    $result["error"]["reason"] = "Problème de connexion à la base de données de la $ville";
    $result["error"]["database"] = $login;
    $result["error"]["exception"] = $e->getMessage();
}

?>
