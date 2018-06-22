<?php

/**
 * Informations relatives aux activités commerciales d'une ville donnée, triées par année
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

require_once("common.php");

$result = array();

if (isset($_GET["ville"]) && $_GET["ville"] != "") {

    // Connexion à la base de données

    require_once("../db/oracle_conn.php");

    // Récupération des données

    if ($conn) {
        $result["records"] = array();

        foreach ($conn->query("SELECT ACO_REF, ACO_NOM FROM ACTIVITECOMMERCIALE_LANGUE WHERE LAN_REF = 1") as $activite_commerciale) {
            $aco_ref = $activite_commerciale["ACO_REF"];
            $aco_nom = $activite_commerciale["ACO_NOM"];

            // Création ou récupération de l'activité commerciale

            $index = count($result["records"]);

            $result["records"][$index]["nom"] = $aco_nom;

            // Donnée : Exploitants

            $nb_exploitants = intval($conn->query("SELECT COUNT(*) FROM EXPLOITANT WHERE ACO_REF = $aco_ref AND EXP_VISIBLE = 1 AND EXP_VALIDE = 1 AND EXP_DATE_CESSATION IS NULL")->fetch()[0]);
            $result["records"][$index]["exploitants"]["nombre"] = $nb_exploitants;

            // Donnée : Fréquentations

            $nb_passagers = intval($conn->query("SELECT COUNT(*) FROM SOCIETE_MARCHE WHERE ACO_REF = $aco_ref AND SMA_CESSATION_DATE IS NULL AND SMA_ABONNE_DATE IS NULL AND SMA_TITULAIRE_DATE IS NULL")->fetch()[0]);
            $nb_titulaires = intval($conn->query("SELECT COUNT(*) FROM SOCIETE_MARCHE WHERE ACO_REF = $aco_ref AND SMA_CESSATION_DATE IS NULL AND (SMA_ABONNE_DATE IS NULL OR SMA_TITULAIRE_DATE > SMA_ABONNE_DATE)")->fetch()[0]);
            $nb_abonnes = intval($conn->query("SELECT COUNT(*) FROM SOCIETE_MARCHE WHERE ACO_REF = $aco_ref AND SMA_CESSATION_DATE IS NULL AND (SMA_TITULAIRE_DATE IS NULL OR SMA_ABONNE_DATE >= SMA_TITULAIRE_DATE)")->fetch()[0]);

            $result["records"][$index]["fréquentations"]["nombre"] = $nb_passagers + $nb_titulaires + $nb_abonnes;
            $result["records"][$index]["fréquentations"]["types"]["passagers"] = $nb_passagers;
            $result["records"][$index]["fréquentations"]["types"]["titulaires"] = $nb_titulaires;
            $result["records"][$index]["fréquentations"]["types"]["abonnés"] = $nb_abonnes;

            // Donnée : Marchés

            $nb_marches = intval($conn->query("SELECT COUNT(UNIQUE MAR_REF) FROM SOCIETE_MARCHE WHERE ACO_REF = $aco_ref AND SMA_CESSATION_DATE IS NULL")->fetch()[0]);
            $result["records"][$index]["marchés"]["nombre"] = $nb_marches;
        }
    }
} else {
    $result["error"]["reason"] = "Donner le nom de la ville";
    $result["error"]["example"] = "Exemple : placier_activites_commerciales.php?ville=angers";
}

echo json_encode($result);

?>
