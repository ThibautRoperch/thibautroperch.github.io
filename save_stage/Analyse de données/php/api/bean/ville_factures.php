<?php

/**
 * Informations relatives aux factures d'une ville données, triées par année
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

function get_year($string) {
    preg_match("/^[0-9]{2}.[0-9]{2}.([0-9]{2,4})$/", $string, $matches);

    if (isset($matches[0])) {
        return "20" . $matches[1];
    }

    return NULL;
}

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
    } catch (PDOException $e) {
        $result["error"]["reason"] = "Problème de connexion à la base de données de la $ville";
        $result["error"]["database"] = $login;
        $result["error"]["exception"] = $e->getMessage();
    }

    // Récupération des données

    if ($conn) {
        $result["records"] = array();

        foreach ($conn->query("SELECT FAC_DATE, TFA_REF, FAC_SOMME_TTC FROM FACTURE fac WHERE FAC_VISIBLE = 1 AND FAC_VALIDE = 1") as $facture) {
            $annee = get_year($facture["FAC_DATE"]);

            // Création ou récupération de l'index de l'année dans le tableau

            if (!array_key_exists($annee, $result["records"])) {
                $result["records"][$annee]["nombre"] = 0;
                $result["records"][$annee]["types"] = array();
                $result["records"][$annee]["total_ttc"] = 0;
            }

            // Donnée : Type

            $type = $conn->query("SELECT TFA_NOM FROM TYPE_FACTURE_LANGUE WHERE TFA_REF = " . $facture["TFA_REF"])->fetch()[0];
            if (!array_key_exists($type, $result["records"][$annee]["types"])) {
                $result["records"][$annee]["types"][$type]["nombre"] = 0;
                $result["records"][$annee]["types"][$type]["total_ttc"] = 0;
            }
            $result["records"][$annee]["types"][$type]["nombre"] += 1;
            $result["records"][$annee]["types"][$type]["total_ttc"] += floatval($facture["FAC_SOMME_TTC"]);

            // Donnée : Somme TTC

            $result["records"][$annee]["nombre"] += 1;
            $result["records"][$annee]["total_ttc"] += floatval($facture["FAC_SOMME_TTC"]);
        }
    }
} else {
    $result["error"]["reason"] = "Donner le nom de la ville";
    $result["error"]["example"] = "Exemple : factures.php?ville=angers";
}

echo json_encode($result);

?>
