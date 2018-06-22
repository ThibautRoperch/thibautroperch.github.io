<?php

/**
 * Vérifie que la ville existe
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$result = array();

if (isset($_GET["ville"]) && $_GET["ville"] != "") {
    $ville = $_GET["ville"];

    // Connexion à la base de données

    $host = "zeus";
    $port = "1521";
    $service = "orcl";
    $login = "geodp" . strtolower($ville);
    $password = $login;
    $conn = NULL;

    try {
        $conn = new PDO("oci:dbname=(DESCRIPTION = (ADDRESS_LIST = (ADDRESS = (PROTOCOL = TCP) (Host = $host) (Port = $port))) (CONNECT_DATA = (SERVICE_NAME = ".$service.")));charset=UTF8", $login, $password);
        $result["records"] = "true";
    } catch (PDOException $e) {
        $result["records"] = "false";
    }
} else {
    $result["error"]["reason"] = "Donner le nom de la ville";
    $result["error"]["example"] = "Exemple : ville_existe.php?ville=angers";
}

echo json_encode($result);

?>
