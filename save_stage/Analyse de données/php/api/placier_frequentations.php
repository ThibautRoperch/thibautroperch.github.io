<?php

/**
 * Informations relatives aux fréquentations d'une ville donnée, triées par année
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

        $activites_commerciales = array();
        foreach ($conn->query("SELECT ACO_NOM FROM ACTIVITECOMMERCIALE_LANGUE WHERE LAN_REF = 1") as $activite_commerciale) {
            $activites_commerciales[$activite_commerciale["ACO_NOM"]]["nombre"] = 0;
        }

        foreach ($conn->query("SELECT ACO_REF, DCREAT, SMA_TITULAIRE_DATE, SMA_ABONNE_DATE, SMA_CESSATION_DATE FROM SOCIETE_MARCHE") as $frequentation) {
            
            // La fréquentation est ajoutée en passager automatiquement
            // La fréquentation devient titulaire à la place de son statut précédent (si il l'est devenu un jour)
            // La fréquentation devient abonné à la place de son statut précédent (si il l'est devenu un jour)
            // La fréquentation devient supprimé à la place de son statut précédent (si il l'est devenu un jour)

            $sma_creation_date = $frequentation["DCREAT"];
            $sma_titulaire_date = $frequentation["SMA_TITULAIRE_DATE"];
            $sma_abonne_date = $frequentation["SMA_ABONNE_DATE"];
            $sma_cessation_date = $frequentation["SMA_CESSATION_DATE"];
            $aco_ref = $frequentation["ACO_REF"];
            $aco_nom = NULL;
            if ($aco_ref) {
                $aco_nom = $conn->query("SELECT ACO_NOM FROM ACTIVITECOMMERCIALE_LANGUE WHERE LAN_REF = 1 AND ACO_REF = $aco_ref")->fetch()[0];
            }     

            $sma_dates = [$sma_creation_date, $sma_titulaire_date, $sma_abonne_date, $sma_cessation_date];
            $sma_statuts = ["passagers", "titulaires", "abonnés", "supprimé"]; // avec un "s" pour plus de clarté dans le JSON       date de création/passager non nulle, les autres peuvent être nulles

            // Tri des statuts en fonction des dates d'obtention de ces derniers
            
            $flux_statuts = [];
            for ($i = 0; $i < count($sma_dates); ++$i) {
                $date = $sma_dates[$i];
                $statut = $sma_statuts[$i];

                if ($date !== NULL) { // Une date peut être NULL
                    $annee = get_year($date);
                    $mois = get_month($date);

                    $flux_statuts[$statut] = strtotime("01." . $mois . "." . $annee);
                }
            }

            asort($flux_statuts);

            // La date d'abonnement doit être positionnée après la date de titularisation si les deux dates sont égales, pour que l'abonnement écrase la titularisation

            if (isset($flux_statuts["titulaires"]) && isset($flux_statuts["abonnés"]) && $flux_statuts["titulaires"] === $flux_statuts["abonnés"]) {
                $flux_statuts["titulaires"] -= 1;
            }

            asort($flux_statuts);

            // La date de création peut être après les autres dates, associer la date de passager à la date du premier statut si c'est le cas

            foreach ($flux_statuts as $statut => $date) {
                if ($statut !== $sma_statuts[0]) {
                    $flux_statuts[$sma_statuts[0]] = $date - 1;
                }
                break;
            }

            asort($flux_statuts);

            // Création ou récupération de l'année (pour chaque date)

            foreach ($flux_statuts as $date) {
                $annee = date("Y", $date);
                $mois = date("n", $date);

                if (!array_key_exists($annee, $result["records"])) {
                    for ($i = 0; $i < count($sma_statuts) - 1; ++$i) {
                        $statut = $sma_statuts[$i];
                        $result["records"][$annee]["types"][$statut]["nombre"] = 0;
                        $result["records"][$annee]["activités_commerciales"] = $activites_commerciales;
                        $result["records"][$annee]["types"][$statut]["activités_commerciales"] = $activites_commerciales;
                        for ($j = 1; $j <= 12; ++$j) {
                            $result["records"][$annee]["mois"][$j]["types"][$statut]["nombre"] = 0;
                        }
                    }
                }
            }

            // Ajout des données

            $statut_precedent = NULL;
            foreach ($flux_statuts as $statut => $date) {
                $annee = date("Y", $date);
                $mois = date("n", $date);

                // Si le statut est le statut de suppression
                if ($statut === end($sma_statuts)) {
                    // Décrémenter le nombre de personnes du statut précédent pour cette date, si il y a un statut précédent (donc dans le cas d'une suppression de la fréquentation)

                    if ($statut_precedent) {
                        $result["records"][$annee]["types"][$statut_precedent]["nombre"] -= 1;
                        $result["records"][$annee]["mois"][$mois]["types"][$statut_precedent]["nombre"] -= 1;
                    }

                    if ($aco_nom) {
                        $result["records"][$annee]["activités_commerciales"][$aco_nom]["nombre"] += 1;
                        $result["records"][$annee]["types"][$statut_precedent]["activités_commerciales"][$aco_nom]["nombre"] += 1;
                    }

                    break; // pour éviter de continuer si la date de suppression est avant d'autres dates
                }
                // Sinon, si le statut est un statut de création ou de changement de fréquentation
                else {
                    // Incrémenter le nombre de personnes de ce statut pour cette date
    
                    $result["records"][$annee]["types"][$statut]["nombre"] += 1;
                    $result["records"][$annee]["mois"][$mois]["types"][$statut]["nombre"] += 1;

                    if ($statut === $sma_statuts[0]) {
                        if ($aco_nom) {
                            $result["records"][$annee]["activités_commerciales"][$aco_nom]["nombre"] += 1;
                            $result["records"][$annee]["types"][$statut]["activités_commerciales"][$aco_nom]["nombre"] += 1;
                        }
                    }
    
                    // Décrémenter le nombre de personnes du statut précédent pour cette date, si il y a un statut précédent (donc dans le cas d'une modification de statut de fréquentation)

                    if ($statut_precedent) {
                        $result["records"][$annee]["types"][$statut_precedent]["nombre"] -= 1;
                        $result["records"][$annee]["mois"][$mois]["types"][$statut_precedent]["nombre"] -= 1;
                    }
                }

                $statut_precedent = $statut;
            }
        }
    }
} else {
    $result["error"]["reason"] = "Donner le nom de la ville";
    $result["error"]["example"] = "Exemple : placier_frequentations.php?ville=angers";
}

echo json_encode($result);

?>
