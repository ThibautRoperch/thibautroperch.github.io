<?php

/**
 * Informations relatives aux marchés d'une ville donnée
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

        $jours = array();
        for ($i = 1; $i <= 7; ++$i) {
            $jours[$i] = 0;
        }

        $activites_commerciales = array();
        foreach ($conn->query("SELECT ACO_NOM FROM ACTIVITECOMMERCIALE_LANGUE WHERE LAN_REF = 1") as $activite_commerciale) {
            $activites_commerciales[$activite_commerciale["ACO_NOM"]]["exploitants"] = 0;
        }

        $noms_marches = [];

        foreach ($conn->query("SELECT mar.MAR_REF, MAR_NOM, MAR_DATECREATION, MAR_JOUR FROM MARCHE mar, MARCHE_LANGUE marl WHERE MAR_VISIBLE = 1 AND mar.MAR_REF = marl.MAR_REF") as $marche) {
            $mar_ref = $marche["MAR_REF"];
            $mar_nom = $marche["MAR_NOM"];
            $mar_creation = $marche["MAR_DATECREATION"] ? $marche["MAR_DATECREATION"] : $marche["DCREAT"];

            preg_match("/^ *(.+) *- *(.+)$/", $mar_nom, $matches);
            if ($matches) $mar_nom = $matches[1];
            while ($mar_nom[strlen($mar_nom) - 1] === " ") $mar_nom = substr($mar_nom, 0, -1);

            // Création ou récupération du marché
            
            $index = NULL;
            if (array_key_exists($mar_nom, $noms_marches)) {
                $index = $noms_marches[$mar_nom];
            } else {
                $index = count($noms_marches);
                $noms_marches[$mar_nom] = $index;

                $result["records"][$index]["nom"] = $mar_nom;
                $result["records"][$index]["creation"] = $mar_creation;
                $result["records"][$index]["jours"] = $jours;
                $result["records"][$index]["exploitants"] = 0;
                $result["records"][$index]["activités_commerciales"] = $activites_commerciales;
                $result["records"][$index]["contrats"]["abonnés"] = 0;
                $result["records"][$index]["contrats"]["titulaires"] = 0;
                $result["records"][$index]["contrats"]["passagers"] = 0;
            }

            // Donnée : Jours d'ouverture
            
            $index_mar_jour = day_to_index($marche["MAR_JOUR"]);
            if ($index_mar_jour) $result["records"][$index]["jours"][$index_mar_jour] = 1;

            // Donnée : Articles

            $today = date("d/m/y", time());
            $index_articles = 0;
            foreach ($conn->query("SELECT ART_NOM FROM ARTICLE art, ARTICLE_LANGUE artl WHERE ART_VALIDE_DEPUIS <= '$today' AND ART_VALIDE_JUSQUA >= '$today' AND ART_VISIBLE = 1 AND art.MAR_REF = $mar_ref AND artl.ART_REF = art.ART_REF") as $article) {
                $result["records"][$index]["articles"][$index_articles]["nom"] = $article["ART_NOM"];
                ++$index_articles;
            }
            $result["records"][$index]["articles"]["nombre"] = $index_articles;

            // Donnée : Activités commerciales et exploitants

            foreach ($conn->query("SELECT ACO_NOM FROM SOCIETE_MARCHE freq, ACTIVITECOMMERCIALE_LANGUE actl WHERE MAR_REF = $mar_ref AND actl.ACO_REF = freq.ACO_REF") as $frequentation) {
                $result["records"][$index]["exploitants"] += 1;
                $result["records"][$index]["activités_commerciales"][$frequentation["ACO_NOM"]]["exploitants"] += 1;
            }

            // Donnée : Contrats

            foreach ($conn->query("SELECT SMA_ABONNE, SMA_TITULAIRE FROM SOCIETE_MARCHE WHERE MAR_REF = $mar_ref") as $frequentation) {
                $type = NULL;
                if ($frequentation["SMA_ABONNE"] === "1") {
                    $type = "abonnés";
                } else if ($frequentation["SMA_TITULAIRE"]) {
                    $type = "titulaires";
                } else {
                    $type = "passagers";
                }

                $result["records"][$index]["contrats"][$type] += 1;
            }

        }
    }
} else {
    $result["error"]["reason"] = "Donner le nom de la ville";
    $result["error"]["example"] = "Exemple : placier_marches.php?ville=angers";
}

echo json_encode($result);

?>
