<?php

/**
 * Liste des villes
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$result = array();

if (isset($_GET["serveur"]) && $_GET["serveur"] != "" && isset($_GET["ressource"]) && isset($_GET["lettre"]) && $_GET["lettre"] != "") {
    $serveur = $_GET["serveur"];
    $ressource = $_GET["ressource"];
    $lettre = strtoupper($_GET["lettre"]);
    $filtre = (isset($_GET["filtre"])) ? strtolower($_GET["filtre"]) : "";

    // Récupération des villes du serveur

    $racine = "\\\\$serveur\\$ressource";
    system("NET USE $lettre: $racine rtli /user:TR\iltr /PERSISTENT:NO");

    $result["records"] = array();

    $repertoire = scandir("$racine");
    foreach ($repertoire as $fichier) {
        $fichier = strtolower($fichier);
        preg_match("/^geodp\.([a-z]+)$/", $fichier, $matches);
        if (!is_file($fichier) && isset($matches[0])) {
            $ville = $matches[1];
            if ($filtre === "" || ($filtre !== "" && strpos($ville, $filtre) !== false)) {
                $result["records"][$ville] = "";
            }
        }
    }

    // Vérification de la présence d'une base de données pour chaque ville

    $nbdd = 0;
    foreach ($result["records"] as $ville => &$presence_bdd) {
        $host = strtolower($serveur);
        $port = "1521";
        $service = ($host === "ares") ? "xe" : "orcl";
        $login = "geodp$ville";
        $password = $login;
    
        try {
            $conn = new PDO("oci:dbname=(DESCRIPTION = (ADDRESS_LIST = (ADDRESS = (PROTOCOL = TCP) (Host = $host) (Port = $port))) (CONNECT_DATA = (SERVICE_NAME = ".$service.")));charset=UTF8", $login, $password);
            $presence_bdd = "true";
            ++$nbdd;
        } catch (PDOException $e) {
            $presence_bdd = "false";
        }
    }

    $result["records"]["nhits"] = count($result["records"]);
    $result["records"]["nbdd"] = $nbdd;
} else {
    $result["error"]["reason"] = "Donner le nom du serveur, sa ressource partagée et sa lettre, ainsi que les lettres composant le nom de la ville (facultatif)";
    $result["error"]["example"] = "Exemple : villes.php?serveur=ZEUS&ressource=iis-root&lettre=Z&filtre=an";
}

echo json_encode($result);

?>
