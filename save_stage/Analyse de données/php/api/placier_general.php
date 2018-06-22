<?php

/**
 * Informations relatives aux factures d'une ville donnée, triées par année
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
        
        // $activites_commerciales = array();
        // foreach ($conn->query("SELECT ACO_NOM FROM ACTIVITECOMMERCIALE_LANGUE WHERE LAN_REF = 1") as $activite_commerciale) {
        //     $activites_commerciales[$activite_commerciale["ACO_NOM"]]["nombre"] = 0;
        //     $activites_commerciales[$activite_commerciale["ACO_NOM"]]["total_ttc"] = 0;
        // }

        $types_factures = array();
        foreach ($conn->query("SELECT TFA_NOM FROM TYPE_FACTURE_LANGUE WHERE LAN_REF = 1") as $type_facture) {
            $types_factures[$type_facture["TFA_NOM"]]["nombre"] = 0;
            $types_factures[$type_facture["TFA_NOM"]]["total_ttc"] = 0;
        }

        foreach ($conn->query("SELECT FAC_DATE, TFA_REF, FAC_SOMME_TTC FROM FACTURE WHERE FAC_VISIBLE = 1 AND FAC_VALIDE = 1 AND FAC_REF > 10000") as $facture) { // AND FAC_REF > 2400 AND FAC_REF < 4000
            $fac_date = $facture["FAC_DATE"];
            $fac_somme_ttc = floatval($facture["FAC_SOMME_TTC"]);

            $annee = get_year($fac_date);
            $mois = get_month($fac_date);

            // Création ou récupération de l'année

            if (!array_key_exists($annee, $result["records"])) {
                $result["records"][$annee]["nombre"] = 0;
                // $result["records"][$annee]["activites_commerciales"] = $activites_commerciales;
                $result["records"][$annee]["types"] = $types_factures;
                $result["records"][$annee]["total_ttc"] = 0;
                for ($i = 1; $i <= 12; ++$i) {
                    $result["records"][$annee]["mois"][$i]["nombre"] = 0;
                    // $result["records"][$annee]["mois"][$i]["activites_commerciales"] = $activites_commerciales;
                    $result["records"][$annee]["mois"][$i]["types"] = $types_factures;
                    $result["records"][$annee]["mois"][$i]["total_ttc"] = 0;
                }
            }

            // Donnée : Activité commerciale

            // $activite_commerciale = $conn->query("SELECT ACO_NOM FROM ACTIVITECOMMERCIALE_LANGUE WHERE ACO_REF = " . $facture["ACO_REF"] . " AND LAN_REF = 1")->fetch()[0];
            
            // $result["records"][$annee]["activites_commerciales"][$activite]["nombre"] += 1;
            // $result["records"][$annee]["activites_commerciales"][$activite]["total_ttc"] += $fac_somme_ttc;
            // $result["records"][$annee]["mois"][$mois]["activites_commerciales"][$activite]["nombre"] += 1;
            // $result["records"][$annee]["mois"][$mois]["activites_commerciales"][$activite]["total_ttc"] += $fac_somme_ttc;

            // Donnée : Type (autorisation, devis, ...)

            $type = $conn->query("SELECT TFA_NOM FROM TYPE_FACTURE_LANGUE WHERE TFA_REF = " . $facture["TFA_REF"] . " AND LAN_REF = 1")->fetch()[0];
            
            $result["records"][$annee]["types"][$type]["nombre"] += 1;
            $result["records"][$annee]["types"][$type]["total_ttc"] += $fac_somme_ttc;
            $result["records"][$annee]["mois"][$mois]["types"][$type]["nombre"] += 1;
            $result["records"][$annee]["mois"][$mois]["types"][$type]["total_ttc"] += $fac_somme_ttc;

            // Donnée : Somme TTC

            $result["records"][$annee]["nombre"] += 1;
            $result["records"][$annee]["total_ttc"] += $fac_somme_ttc;
            $result["records"][$annee]["mois"][$mois]["nombre"] += 1;
            $result["records"][$annee]["mois"][$mois]["total_ttc"] += $fac_somme_ttc;
        }
    }
} else {
    $result["error"]["reason"] = "Donner le nom de la ville";
    $result["error"]["example"] = "Exemple : placier_general.php?ville=angers";
}

echo json_encode($result);

?>
