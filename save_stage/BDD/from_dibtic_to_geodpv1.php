<?php

// Entrée : Fichiers Excel dibtic définis dans common.php et récupérés par le script index.php
// Sortie : Fichier SQL contenant les requêtes SQL à envoyer dans la base de données GEODP v1

// Génère des tables à partir des fichiers lus
// Extrait les informations voulues et exécute + donne les requêtes SQL


/*************************
 *        FICHIERS       *
 *************************/

// Répertoire contenant les fichiers sources (dibtic)
$directory = ".";

// Résumé du contenu des fichiers sources (dibtic)
$expected_content = ["marchés", "articles", "exploitants", "activités"];

// Noms possibles des fichiers sources (dibtic)
$keywords_files = ["marche/marché", "classe/article/tarif", "exploitant/assujet", "activite/activité"];

// Données extraites des fichiers sources (dibtic)
$extracted_files_content = [
    "Libellé du marché, jour du marché (s’il y a plusieurs jours, on considère que c’est toute la semaine)",
    "Nom de l’article, unité, prix unitaire, tva, marchés associés à l’article",
    "Code de l’exploitant, nom, prénom, raison sociale, adresse (rue/CP/ville), date de suppression s’il a été supprimé, numéro de téléphone et de portable, adresse mail, type d’activité et abonnements associés à l’exploitant",
    "Type de l’activité"];

// Nom du fichier de sortie contenant les requêtes SQL à envoyer (GEODP v1)
$output_filename = "output.sql"; // Si un fichier avec le même nom existe déjà, il sera écrasé


/*************************
 *    TABLES SOURCES     *
 *************************/

$src_tables = []; // auto-computed from filenames and based on the same indexation as $expected_content and $keywords_files
$src_prefixe = "src_";

// Mettre à vrai pour afficher le contenu des fichiers sources lors de leur lecture
$display_source_files_contents = true; // true | false


/*************************
 * TABLES DE DESTINATION *
 *************************/

// Nom des tables de destination (GEODP v1)
$dest_activite = "dest_activite";
$dest_activite_lang = "dest_activite_langue";

$dest_marche = "dest_marche";
$dest_marche_lang = "dest_marche_langue";
$dest_article = "dest_article";
$dest_article_lang = "dest_article_langue";
$dest_exploitant = "dest_exploitant";
$dest_societe_marche = "dest_societe_marche";
$dest_activitecomm = "dest_activitecommerciale";
$dest_activitecomm_lang = "dest_activitecommerciale_langue";
$dest_abonnement = "dest_societe_marche";

// Valeurs des champs DCREAT et UCREAT utilisées dans les requêtes SQL
$dest_dcreat = date("y/m/d", time());
$dest_ucreat = "ILTR";

// Mettre à vrai pour supprimer les données des tables de destination avant l'insertion des nouvelles, mettre à faux sinon
$erase_destination_tables = true; // true | false

// Mettre à vrai pour afficher les requêtes SQL relatives aux tables de destination, mettre à faux sinon (dans tous les cas, le fichier $output_filename est généré)
$display_dest_requests = true; // true | false

// Mettre à vrai pour exécuter les requêtes SQL relatives aux tables de destination, mettre à faux sinon (dans tous les cas, le fichier $output_filename est généré)
$exec_dest_requests = true; // true | false


/*************************
 *    CONNEXION MySQL    *
 *************************/

$mysql_host = "localhost";
$mysql_dbname = "reprise_dibtic";
$mysql_login = "root";
$mysql_password = "";
$mysql_conn = new PDO("mysql:host=$mysql_host;dbname=$mysql_dbname", "$mysql_login", "$mysql_password");


/*************************
 *    CONNEXION ORACLE   *
 *************************/

$oracle_host = "zeus";
$oracle_port = "1521";
$oracle_service = "orcl";
$oracle_login = "geodp_lyon_tr";
$oracle_password = "geodp_lyon_tr";

$oracle_conn = new PDO("oci:dbname=(DESCRIPTION = (ADDRESS_LIST = (ADDRESS = (PROTOCOL = TCP) (Host = $oracle_host) (Port = $oracle_port))) (CONNECT_DATA = (SERVICE_NAME = ".$oracle_service.")))", $oracle_login, $oracle_password);

?>

<?php
// oracle login (pas password, car <=>)
// proposer "oracle" ou "mmysql", afficher les tables en conséquence et afficher un champ "password" pour la conexion oracle
// voir pour les index from scratch
// voir pour les pieces justificatives
// verifier les injections dans Oracle
?>

<?php

function match_keywords($string, $keywords) {
    $keywords = explode("/", $keywords);
    foreach ($keywords as $keyword) {
        if (strpos($string, $keyword) > -1) return true;
    }
    return false;
}

function prune($str) {
    return substr($str, 0, strrpos($str, '.'));
}

function get_from_address($info, $address) {
    switch ($info) {
        case "num":
            $matches = array();
            preg_match('/^( )*[0-9]+/', $address, $matches);
            return isset($matches[0]) ? $matches[0] : "";
            break;
        case "voie":
            $matches = array();
            preg_match('/^( )*[0-9]+( )*,?( )*/', $address, $matches);
            return isset($matches[0]) ? substr($address, strpos($address, $matches[0]) + strlen($matches[0])) : $address;
            break;
        default:
            return $address;
    }
}

function addslashes_nullify($string) {
    if ($string === "") {
        return "NULL";
    } else {
        return "'".addslashes($string)."'";
    }
}

$directory_files = scandir($directory);

$detected_files = [];
$files_to_convert = [];
$source_files = []; // équivalent à $files_to_convert avec des indices presonnalisés

foreach ($directory_files as $file) {
    if (preg_match("/.*\.xls$/i", $file)) array_push($detected_files, $file);
}

// echo "Fichiers détectés :<ul><li>" . implode("</li><li>", $detected_files) . "</li></ul>";

foreach ($detected_files as $file) {
    for ($i = 0; $i < count($expected_content); ++$i) {
        if (!isset($files_to_convert[$i]) && match_keywords($file, $keywords_files[$i])) {
            $files_to_convert[$i] = $file;
            $source_files[$expected_content[$i]] = $file;
            break;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Conversion dibtic - GEODP v1</title>
</head>
<body>
    
    <?php
    
        /**
         * ACCUEIL
         */
        
        if (!isset($_GET["analyse"]) || $_GET["analyse"] !== "1") {

            echo "<init>";
            echo "<h1>Reprise dibtic vers GEODP v1</h1>";

            echo "<h2>Fichiers dibtic à reprendre</h2>";
            echo "<table>";
            echo "<tr><th>Contenu</th><th>Fichier correspondant</th><th>Informations transférables dans GEODP</th></tr>";
            for ($i = 0; $i < count($expected_content); ++$i) {
                echo "<tr><td>Liste des " . $expected_content[$i] . "</td><td>";
                echo (isset($files_to_convert[$i])) ? "<ok></ok>" . $files_to_convert[$i] : "<nok></nok>Fichier manquant";
                echo "</td><td>" . $extracted_files_content[$i];
                echo "</td></tr>";
            }
            echo "</table>";

            echo "<h2>Paramètres</h2>";

            if (count($files_to_convert) == count($expected_content)) {
                echo "<a class=\"button\" href=\"from_dibtic_to_geodpv1.php?analyse=1\">Créer un client GEODP à partir de ces fichiers</a>";
            } else {
                echo "Des fichiers sont manquants :<ul>";
                for ($i = 0; $i < count($expected_content); ++$i) {
                    if (!isset($files_to_convert[$i])) {
                        echo "<li>" . $expected_content[$i] . " (" . $keywords_files[$i] . ")</li>";
                    }
                }
                echo "</ul>";
            }

            echo "</init>";
        }

        /**
         * ANALYSE
         */

        if (isset($_GET["analyse"]) && $_GET["analyse"] === "1") {
            
            //// Sommaire

            echo "<aside>";
            echo "<ol id=\"sommaire\">";
                echo "<li><a href=\"#source\">Traitement des fichiers sources dibtic</a>";
                    echo "<ol>";
                        foreach ($expected_content as $exp) {
                            echo "<li><a href=\"#file_" . prune($source_files[$exp]) . "\">Fichier des $exp</a></li>";
                        }
                    echo "</ol>";
                echo "</li>";
                echo "<li><a href=\"#preparation\">Création du client GEODP</a>";
                    echo "<ol>";
                        echo "<li><a href=\"#installation\">Installation</a></li>";
                        echo "<li><a href=\"#groupe_activite\">Groupe d'activité</a></li>";
                        echo "<li><a href=\"#activite\">Activité / Activité Langue</a></li>";
                        echo "<li><a href=\"#utilisateur\">Utilisateur / Utilisateur Activité Langue</a></li>";
                    echo "</ol>";
                echo "</li>";
                echo "<li><a href=\"#destination\">Insertion des données GEODP</a>";
                    echo "<ol>";
                        echo "<li><a href=\"#datas_marches\">Marchés / Marchés Langue</a></li>";
                        echo "<li><a href=\"#datas_articles\">Articles / Articles Langue</a></li>";
                        echo "<li><a href=\"#datas_activites_comm\">Activités commerciales / Activités commerciales Langue</a></li>";
                        echo "<li><a href=\"#datas_exploitants\">Exploitants / Abonnements</a></li>";
                    echo "</ol>";
                echo "</li>";
            echo "</ol>";
            echo "</aside>";

            echo "<section>";
            
            //// Traitement des fichiers sources dibtic
            
            echo "<h1 id=\"source\">Traitement des fichiers sources dibtic</h1>";
            
            // Pour chaque fichier source (pour chaque fichier attendu, il y a le chemin du fichier en $_GET), lecture + table + insertions
            
            foreach ($expected_content as $exp) {
                $file = $source_files[$exp];

                echo "<h2 id=\"file_" . prune($file) . "\">Fichier des $exp</h2>";

                // Lecture du fichier (http://coursesweb.net/php-mysql/phpspreadsheet-read-write-excel-libreoffice-files)

                echo "<h3>Lecture du fichier</h3>";
    /*
                require 'spreadsheet/vendor/autoload.php';

                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load("$directory/$file");

                $xls_data = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
                // var_dump($xls_data);

                if ($display_source_files_contents) {
                    $html_tb ='<table><tr><th>'. implode('</th><th>', $xls_data[1]) .'</th></tr>';
                    for ($i = 2; $i <= count($xls_data); $i++) {
                        $html_tb .='<tr><td>'. implode('</td><td>', $xls_data[$i]) .'</td></tr>';
                    }
                    $html_tb .='</table>';
                    echo $html_tb;
                }
    */
                // Création de la table correspondante

                echo "<h3>Création de la table correspondante</h3>";

                $table_name = $src_prefixe . prune($file);
                $src_tables[$exp] = $table_name;

    /*
                $mysql_conn->exec("DROP TABLE IF EXISTS $source_table");

                $create_table_query = "";
                foreach ($xls_data[1] as $col) {
                    if ($create_table_query === "") {
                        $create_table_query .= "`$col` VARCHAR(250) PRIMARY KEY";
                    } else {
                        $create_table_query .= ", `$col` VARCHAR(250)";
                    }
                }
                $create_table_query = "CREATE TABLE $source_table ($create_table_query)";

                echo "<div class=\"pre\">$create_table_query</div>";
                $mysql_conn->exec($create_table_query);

                // Remplissage de la table créée avec les données du fichier

                echo "<h3>Remplissage de la table créée avec les données du fichier</h3>";

                $nb_insert = 0;

                // echo "<div class=\"pre\">";
                for ($i = 2; $i <= count($xls_data); $i++) {
                    $insert_into_query = "";
                    foreach ($xls_data[$i] as $cel) {
                        if ($insert_into_query !== "") {
                            $insert_into_query .= ", ";
                        }
                        $insert_into_query .= "'".addslashes($cel)."'";
                    }
                    $insert_into_query = "INSERT INTO $source_table VALUES ($insert_into_query)";

                    // echo "$insert_into_query<br>";
                    $nb_insert += $mysql_conn->exec($insert_into_query);
                }
                // echo "</div>";

                // Contrôle du nombre d'insertions faites par rapport au nombre de données du fichier

                echo "<h3>Contrôle du nombre d'insertions faites par rapport au nombre de données du fichier</h3>";

                echo "$nb_insert insertions réussies / " . (count($xls_data) - 1) . " données dans le fichier";
    */
            }

            // Association du nom des tables avec le contenu de leur fichier
            $src_marche = $src_tables["marchés"];
            $src_article = $src_tables["articles"];
            $src_activite = $src_tables["activités"];
            $src_exploitant = $src_tables["exploitants"];

            // Récupération des colonnes des classes d'articles (elles commencent par "classe" et sont peut-être suivies par un entier)
            $src_exploitant_classe_cols = array();
            foreach ($mysql_conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$src_exploitant'") as $row) {
                $matches = array();
                preg_match('/^classe[0-9]*$/', $row["COLUMN_NAME"], $matches);
                if (isset($matches[0])) {
                    array_push($src_exploitant_classe_cols, $matches[0]);
                }
            }

            // Récupération des colonnes des marchés (elles commencent par "m" et sont peut-être suivies par un entier)
            $src_exploitant_marche_cols = array();
            foreach ($mysql_conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$src_exploitant'") as $row) {
                $matches = array();
                preg_match('/^m[0-9]*$/', $row["COLUMN_NAME"], $matches);
                if (isset($matches[0])) {
                    array_push($src_exploitant_marche_cols, $matches[0]);
                }
            }

            $output_file = fopen($output_filename, "w+");

            //// Création du client GEODP

            echo "<h1 id=\"preparation\">Création du client GEODP</h1>";
            fwrite($output_file, "\n---------------------------------------------\n-- Création du client GEODP\n--\n\n");

            $dest_activite_cols = ["ACT_REF", "ACT_NUM_CSS", "ACT_MODULE", "DCREAT", "UCREAT", "ACT_VISIBLE"];
            $dest_activite_lang_cols = ["ACT_REF", "LAN_REF", "ACT_NOM", "DCREAT", "UCREAT"];

            // Installation

            echo "<h2 id=\"installation\">Installation</h2>";
            // TODO script qui crée le truc

            // Groupe d'activité

            echo "<h2 id=\"groupe_activite\">Groupe d'activité</h2>";
            fwrite($output_file, "\n-- Groupe d'activité\n\n");

            // Activité / Activité Langue

            echo "<h2 id=\"activite\">Activité / Activité Langue</h2>";
            fwrite($output_file, "\n-- Activité / Activité Langue\n\n");

            if ($erase_destination_tables) {
                $mysql_conn->exec("DELETE FROM $dest_activite");
                $mysql_conn->exec("DELETE FROM $dest_activite_lang");
            }

            $req_last_act_ref = $mysql_conn->query("SELECT ACT_REF FROM $dest_activite ORDER BY ACT_REF DESC LIMIT 1")->fetch();
            $last_act_ref = ($req_last_act_ref == null) ? 0 : $req_last_act_ref["ACT_REF"];
            ++$last_act_ref;

            echo "<div class=\"pre\">";

            $dest_activite_values = [$last_act_ref, 13, "'placier'", "'$dest_dcreat'", "'$dest_ucreat'", 1]; // TODO ACT_NUM_CSS = 13 ???
            // SELECT * FROM ALL_TAB_COLUMNS WHERE owner = 'GEODPANGERS' AND column_name = 'ACT_NUM_CSS'

            $insert_into_query = "INSERT INTO $dest_activite (" . implode(", ", $dest_activite_cols) . ") VALUES (" . implode(", ", $dest_activite_values) . ")";

            if ($display_dest_requests) echo "$insert_into_query<br>";
            fwrite($output_file, "$insert_into_query;\n");
            if ($exec_dest_requests) $mysql_conn->exec($insert_into_query);

            $dest_activite_lang_values = [$last_act_ref, 1, "'Marchés'", "'$dest_dcreat'", "'$dest_ucreat'"];

            $insert_into_query = "INSERT INTO $dest_activite_lang (" . implode(", ", $dest_activite_lang_cols) . ") VALUES (" . implode(", ", $dest_activite_lang_values) . ")";

            if ($display_dest_requests) echo "$insert_into_query</br>";
            fwrite($output_file, "$insert_into_query;\n");
            if ($exec_dest_requests) $mysql_conn->exec($insert_into_query);

            echo "</div>";

            // Utilisateur / Utilisateur Activité Langue

            echo "<h2 id=\"utilisateur\">Utilisateur / Utilisateur Activité Langue</h2>";
            fwrite($output_file, "\n-- Utilisateur / Utilisateur Activité Langue\n\n");

            echo "<div class=\"pre\">";

            $insert_into_query = "UPDATE $dest_utilisateur SET UTI_NOM = 'Mairie'";

            if ($display_dest_requests) echo "$insert_into_query<br>";
            fwrite($output_file, "$insert_into_query;\n");
            if ($exec_dest_requests) $mysql_conn->exec($insert_into_query);

            echo "</div>";

            //// Insertion des données GEODP

            echo "<h1 id=\"destination\">Insertion des données GEODP</h1>";
            fwrite($output_file, "\n---------------------------------------------\n-- Insertion des données GEODP\n--\n\n");
            
            $assoc_marcheCode_marcheRef = [];

            $dest_marche_cols = ["MAR_REF", "UTI_REF", "ACT_REF", "MAR_JOUR"];
            $dest_marche_lang_cols = ["MAR_REF", "LAN_REF", "MAR_NOM", "DCREAT", "UCREAT"];

            $dest_article_cols = ["ART_REF", "MAR_REF", "UTI_REF", "ART_PRIX_TTC", "ART_PRIX_HT", "ART_TAUX_TVA", "ART_COULEUR", "ART_VALIDE_DEPUIS", "ART_VALIDE_JUSQUA", "ART_VISIBLE", "DCREAT", "UCREAT"];
            $dest_article_lang_cols = ["ART_REF", "LAN_REF", "ART_NOM", "ART_UNITE", "DCREAT", "UCREAT"];
            
            $dest_activitecomm_cols = ["ACO_REF", "UTI_REF", "ACO_COULEUR", "DCREAT", "UCREAT"];
            $dest_activitecomm_lang_cols = ["ACO_REF", "LAN_REF", "ACO_NOM", "DCREAT", "UCREAT"];
            
            $dest_exploitant_cols = ["EXP_REF", "EXP_CODE", "UTI_REF", "GRA_REF", "LAN_REF", "ACO_REF", "EXP_NOM_PERS_PHYSIQUE", "EXP_PRENOM_PERS_PHYSIQUE", "EXP_RAISON_SOCIALE", "EXP_NOM", "EXP_VISIBLE", "EXP_VALIDE", "EXP_NRUE", "EXP_ADRESSE", "EXP_CP", "EXP_VILLE", "EXP_TELEPHONE", "EXP_PORTABLE", "EXP_FAX", "EXP_EMAIL"];
            
            $dest_abonnement_cols = ["EXP_REF", "MAR_REF", "ACO_REF"];

            // Marchés / Marchés Langue

            echo "<h2 id=\"datas_marches\">Marchés / Marchés Langue</h2>";
            fwrite($output_file, "\n-- Marchés / Marchés Langue\n\n");

            if ($erase_destination_tables) {
                $mysql_conn->exec("DELETE FROM $dest_marche");
                $mysql_conn->exec("DELETE FROM $dest_marche_lang");
            }

            $req_last_mar_ref = $mysql_conn->query("SELECT MAR_REF FROM $dest_marche ORDER BY MAR_REF DESC LIMIT 1")->fetch();
            $last_mar_ref = ($req_last_mar_ref == null) ? 0 : $req_last_mar_ref["MAR_REF"];

            if ($display_dest_requests) echo "<div class=\"pre\">";
            foreach ($mysql_conn->query("SELECT * FROM $src_marche") as $row) {
                $last_mar_ref += 1;

                $dest_marche_values = [];

                foreach ($dest_marche_cols as $col) {
                    switch ($col) {
                        case "MAR_REF":
                            array_push($dest_marche_values, "$last_mar_ref");
                            break;
                        case "UTI_REF":
                            array_push($dest_marche_values, "1");
                            break;
                        case "ACT_REF":
                            array_push($dest_marche_values, "2");
                            break;
                        case "MAR_JOUR":
                            $mar_day = "";
                            $days = ["lundi", "mardi", "mercredi", "jeudi", "vendredi", "samedi", "dimanche"];
                            foreach ($days as $d) {
                                if ($row[$d] === "1") {
                                    if ($mar_day === "") {
                                        $mar_day = "'$d'";
                                    } else {
                                        $mar_day = "NULL";
                                        break;
                                    }
                                }
                            }
                            array_push($dest_marche_values, $mar_day);
                            break;
                        default:
                            array_push($dest_marche_values, "'TODO'");
                            break;
                    }
                }
                
                $assoc_marcheCode_marcheRef[$row["code"]] = $last_mar_ref;

                $insert_into_query = "INSERT INTO $dest_marche (" . implode(", ", $dest_marche_cols) . ") VALUES (" . implode(", ", $dest_marche_values) . ")";

                if ($display_dest_requests) echo "$insert_into_query<br>";
                fwrite($output_file, "$insert_into_query;\n");
                if ($exec_dest_requests) $mysql_conn->exec($insert_into_query);

                // Marché langue

                $dest_marche_lang_values = [$last_mar_ref, 1, "'".addslashes($row["libelle"])."'", "'".$dest_dcreat."'", "'".$dest_ucreat."'"];

                $insert_into_query = "INSERT INTO $dest_marche_lang (" . implode(", ", $dest_marche_lang_cols) . ") VALUES (" . implode(", ", $dest_marche_lang_values) . ")";

                if ($display_dest_requests) echo "$insert_into_query<br>";
                fwrite($output_file, "$insert_into_query;\n");
                if ($exec_dest_requests) $mysql_conn->exec($insert_into_query);
            }
            if ($display_dest_requests) echo "</div>";

            // Articles / Articles Langue

            echo "<h2 id=\"datas_articles\">Articles / Articles Langue</h2>";
            fwrite($output_file, "\n-- Articles / Articles Langue\n\n");

            if ($erase_destination_tables) {
                $mysql_conn->exec("DELETE FROM $dest_article");
                $mysql_conn->exec("DELETE FROM $dest_article_lang");
            }
            
            $req_last_art_ref = $mysql_conn->query("SELECT ART_REF FROM $dest_article ORDER BY ART_REF DESC LIMIT 1")->fetch();
            $last_art_ref = ($req_last_art_ref == null) ? 0 : $req_last_art_ref["ART_REF"];

            if ($display_dest_requests) echo "<div class=\"pre\">";
            foreach ($mysql_conn->query("SELECT * FROM $src_article WHERE nom != ''") as $row) {
                $dest_marches_ref = [];
                // Pour obtenir les marchés de référence de cet article (une insertion de cet article pour chaque marché de ref trouvé, il en résultera plusieurs lignes correspondant à un même article mais associé à des marchés différents) :
                //   Regarder chaque colonne des classes et des groupes dans la table source des exploitants (classe`i`/groupe`i`)
                //     Si le combo groupe-classe correspond à celui de l'article, alors lire le marché dans la colonne correspondante
                //       Si le combo groupe-marché existe, considérer ce marché comme un marché de référence pour cet article

                $src_art_code = $row["code"];
                $src_art_numc = $row["numc"];

                foreach ($src_exploitant_classe_cols as $src_exploitant_classe_col) { // pour chaque colonne de classe possible, récupère les exploitants qui matchent avec le combo groupe-classe de l'article en cours
                    $matches = array();
                    preg_match('/[0-9]+/', $src_exploitant_classe_col, $matches);
                    $num_groupe = isset($matches[0]) ? $matches[0] : ""; // pour les groupes, la première colonne s'appelle juste 'groupe', comme pour les classes

                    foreach ($mysql_conn->query("SELECT * FROM $src_exploitant WHERE groupe$num_groupe = '$src_art_numc' AND $src_exploitant_classe_col = '$src_art_code'") as $exploitant) { // WHERE date_suppr = '' OR date_suppr = '  -   -'"
                        // $src_art_groupe = $row["groupe$num_groupe"];
                        // $src_art_classe = $row["$src_exploitant_classe_col"];

                        $num_m = isset($matches[0]) ? $matches[0] : ""; // pour les marchés, la première colonne s'appelle juste 'm', comme pour les classes et les groupes
                        $marche_code = $exploitant["m$num_m"];
                        $req_groupe_marche = $mysql_conn->query("SELECT groupe FROM $src_marche WHERE code = '$marche_code'")->fetch();
                        $groupe_marche = ($req_groupe_marche == null) ? -1 : $req_groupe_marche["groupe"];

                        if ($groupe_marche === $src_art_numc) { // ici, $src_art_numc est forcément équivalent au groupe de la colonne groupe$num_groupe de l'exploitant, donc on peut le comparer avec $groupe_marche
                            $marche_ref = $assoc_marcheCode_marcheRef[$marche_code];
                            if (!in_array($marche_ref, $dest_marches_ref)) {
                                array_push($dest_marches_ref, $marche_ref);
                            }
                        }
                    }
                }

                foreach ($dest_marches_ref as $mar_ref) {
                    $last_art_ref += 1;
        
                    $dest_article_values = [];

                    foreach ($dest_article_cols as $col) {
                        switch ($col) {
                            case "ART_REF":
                                array_push($dest_article_values, "$last_art_ref");
                                break;
                            case "UTI_REF":
                                array_push($dest_article_values, "1");
                                break;
                            case "MAR_REF":
                                array_push($dest_article_values, "$mar_ref");
                                break;
                            case "ART_PRIX_TTC":
                                if ($row["tva"] === "") {
                                    array_push($dest_article_values, "'".str_replace(' ', '', $row["prix_unit"])."'");
                                } else {
                                    array_push($dest_article_values, "NULL");
                                }
                                break;
                            case "ART_PRIX_HT":
                                if ($row["tva"] !== "") {
                                    array_push($dest_article_values, "'".str_replace(' ', '', $row["prix_unit"])."'");
                                } else {
                                    array_push($dest_article_values, "NULL");
                                }
                                break;
                            case "ART_TAUX_TVA":
                                if ($row["tva"] !== "") {
                                    array_push($dest_article_values, "'".str_replace(' ', '', $row["tva"])."'");
                                } else {
                                    array_push($dest_article_values, "NULL");
                                }
                                break;
                            case "ART_COULEUR":
                                array_push($dest_article_values, "'fff'");
                                break;
                            case "ART_VALIDE_DEPUIS":
                                array_push($dest_article_values, "'".date("y/01/01", time())."'"); // 1 janvier de cette année
                                break;
                            case "ART_VALIDE_JUSQUA":
                                array_push($dest_article_values, "'".date("y/12/31", time())."'"); // 31 décembre de cette année
                                break;
                            case "ART_VISIBLE":
                                array_push($dest_article_values, "1");
                                break;
                            case "DCREAT":
                            array_push($dest_article_values, "'".$dest_dcreat."'");
                            break;
                            case "UCREAT":
                                array_push($dest_article_values, "'$dest_ucreat'");
                                break;
                            default:
                                array_push($dest_article_values, "'TODO'");
                                break;
                        }
                    }

                    $insert_into_query = "INSERT INTO $dest_article (" . implode(", ", $dest_article_cols) . ") VALUES (" . implode(", ", $dest_article_values) . ")";

                    if ($display_dest_requests) echo "$insert_into_query<br>";
                    fwrite($output_file, "$insert_into_query;\n");
                    if ($exec_dest_requests) $mysql_conn->exec($insert_into_query);

                    // Article langue

                    $dest_article_lang_values = [$last_art_ref, 1, "'".addslashes($row["nom"])."'", "'".addslashes($row["unite"])."'", "'".$dest_dcreat."'", "'".$dest_ucreat."'"];

                    $insert_into_query = "INSERT INTO $dest_article_lang (" . implode(", ", $dest_article_lang_cols) . ") VALUES (" . implode(", ", $dest_article_lang_values) . ")";

                    if ($display_dest_requests) echo "$insert_into_query<br>";
                    fwrite($output_file, "$insert_into_query;\n");
                    if ($exec_dest_requests) $mysql_conn->exec($insert_into_query);
                } // Fin "pour chaque MAR_REF"
            }
            if ($display_dest_requests) echo "</div>";

            // Activités

            echo "<h2 id=\"datas_activites_comm\">Activités commerciales / Activités commerciales Langue</h2>";
            fwrite($output_file, "\n-- Activités commerciales / Activités commerciales Langue\n\n");

            if ($erase_destination_tables) {
                $mysql_conn->exec("DELETE FROM $dest_activitecomm");
                $mysql_conn->exec("DELETE FROM $dest_activitecomm_lang");
            }

            $req_last_actco_ref = $mysql_conn->query("SELECT ACO_REF FROM $dest_activitecomm ORDER BY ACO_REF DESC LIMIT 1")->fetch();
            $last_actco_ref = ($req_last_actco_ref == null) ? 0 : $req_last_actco_ref["ACO_REF"];

            if ($display_dest_requests) echo "<div class=\"pre\">";
            foreach ($mysql_conn->query("SELECT * FROM $src_activite") as $row) {
                $last_actco_ref += 1;

                $dest_activitecomm_values = [];
                
                foreach ($dest_activitecomm_cols as $col) {
                    switch ($col) {
                        case "ACO_REF":
                            array_push($dest_activitecomm_values, "$last_actco_ref");
                            break;
                        case "UTI_REF":
                            array_push($dest_activitecomm_values, "1");
                            break;
                        case "ACO_COULEUR":
                            array_push($dest_activitecomm_values, "'fff'");
                            break;
                        case "DCREAT":
                            array_push($dest_activitecomm_values, "'".$dest_dcreat."'");
                            break;
                        case "UCREAT":
                            array_push($dest_activitecomm_values, "'$dest_ucreat'");
                            break;
                        default:
                            array_push($dest_activitecomm_values, "'TODO'");
                            break;
                    }
                }

                $insert_into_query = "INSERT INTO $dest_activitecomm (" . implode(", ", $dest_activitecomm_cols) . ") VALUES (" . implode(", ", $dest_activitecomm_values) . ")";

                if ($display_dest_requests) echo "$insert_into_query<br>";
                fwrite($output_file, "$insert_into_query;\n");
                if ($exec_dest_requests) $mysql_conn->exec($insert_into_query);

                // Activité langue

                $dest_activitecomm_lang_values = [$last_actco_ref, 1, "'".addslashes($row["activiti"])."'", "'".$dest_dcreat."'", "'".$dest_ucreat."'"];

                $insert_into_query = "INSERT INTO $dest_activitecomm_lang (" . implode(", ", $dest_activitecomm_lang_cols) . ") VALUES (" . implode(", ", $dest_activitecomm_lang_values) . ")";

                if ($display_dest_requests) echo "$insert_into_query<br>";
                fwrite($output_file, "$insert_into_query;\n");
                if ($exec_dest_requests) $mysql_conn->exec($insert_into_query);
            }
            if ($display_dest_requests) echo "</div>";

            // Exploitants / Abonnements

            echo "<h2 id=\"datas_exploitants\">Exploitants / Abonnements</h2>";
            fwrite($output_file, "\n-- Exploitants / Abonnements\n\n");

            if ($erase_destination_tables) {
                $mysql_conn->exec("DELETE FROM $dest_exploitant");
            }

            $req_last_exp_ref = $mysql_conn->query("SELECT EXP_REF FROM $dest_exploitant ORDER BY EXP_REF DESC LIMIT 1")->fetch();
            $last_exp_ref = ($req_last_exp_ref == null) ? 0 : $req_last_exp_ref["EXP_REF"];

            if ($display_dest_requests) echo "<div class=\"pre\">";
            foreach ($mysql_conn->query("SELECT * FROM $src_exploitant WHERE date_suppr = '' OR date_suppr = '  -   -'") as $row) {
                $last_exp_ref += 1;

                $dest_exploitant_values = [];

                foreach ($dest_exploitant_cols as $col) {
                    switch ($col) {
                        case "EXP_REF":
                            array_push($dest_exploitant_values, "$last_exp_ref");
                            break;
                        case "EXP_CODE":
                            array_push($dest_exploitant_values, "'".$row["ntiers"]."'");
                            break;
                        case "UTI_REF":
                            array_push($dest_exploitant_values, "1");
                            break;
                        case "GRA_REF":
                            array_push($dest_exploitant_values, "1");
                            break;
                        case "LAN_REF":
                            array_push($dest_exploitant_values, "1");
                            break;
                        case "ACO_REF":
                            $aco_ref = "NULL";
                            if ($row["type"] !== "") {
                                $req_aco_ref = $mysql_conn->query("SELECT ACO_REF FROM $dest_activitecomm_lang WHERE ACO_NOM = '".addslashes(strtoupper($row["type"]))."'")->fetch();
                                $aco_ref = ($req_aco_ref == null) ? NULL : $req_aco_ref["ACO_REF"];
                                if ($aco_ref == NULL) {
                                    if ($display_dest_requests) echo "-- L'activité " . addslashes(strtoupper($row["type"])) . " lue dans le fichier des exploitants est manquante dans le fichier des activités.<br>";
                                    fwrite($output_file, "-- L'activité " . addslashes(strtoupper($row["type"])) . " lue dans le fichier des exploitants est manquante dans le fichier des activités.\n");

                                    $req_last_actco_ref = $mysql_conn->query("SELECT ACO_REF FROM $dest_activitecomm ORDER BY ACO_REF DESC LIMIT 1")->fetch();
                                    $last_actco_ref = ($req_last_actco_ref == null) ? 0 : $req_last_actco_ref["ACO_REF"];
                                    ++$last_actco_ref;

                                    // Activité

                                    $dest_activitecomm_values = [$last_actco_ref, 1, "'fff'", "'".$dest_dcreat."'", "'".$dest_ucreat."'"];

                                    $insert_into_query = "INSERT INTO $dest_activitecomm (" . implode(", ", $dest_activitecomm_cols) . ") VALUES (" . implode(", ", $dest_activitecomm_values) . ")";

                                    if ($display_dest_requests) echo "-- Correctif appliqué à la table $dest_activitecomm :<br>$insert_into_query<br>";
                                    fwrite($output_file, "-- Correctif appliqué à la table $dest_activitecomm :\n$insert_into_query;\n");
                                    if ($exec_dest_requests) $mysql_conn->exec($insert_into_query);

                                    // Activité langue

                                    $dest_activitecomm_lang_values = [$last_actco_ref, 1, "'".addslashes(strtoupper($row["type"]))."'", "'".$dest_dcreat."'", "'".$dest_ucreat."'"];

                                    $insert_into_query = "INSERT INTO $dest_activitecomm_lang (" . implode(", ", $dest_activitecomm_lang_cols) . ") VALUES (" . implode(", ", $dest_activitecomm_lang_values) . ")";

                                    if ($display_dest_requests) echo "-- Correctif appliqué à la table $dest_activitecomm_lang :<br>$insert_into_query<br>";
                                    fwrite($output_file, "-- Correctif appliqué à la table $dest_activitecomm_lang :\n$insert_into_query;\n");
                                    if ($exec_dest_requests) $mysql_conn->exec($insert_into_query);

                                    if ($display_dest_requests) echo "-- Fin des correctifs<br>";
                                    fwrite($output_file, "-- Fin des correctifs\n");

                                    $aco_ref = $last_actco_ref;
                                }
                            }
                            array_push($dest_exploitant_values, $aco_ref);
                            break;
                        case "EXP_NOM_PERS_PHYSIQUE":
                            array_push($dest_exploitant_values, addslashes_nullify($row["nom_deb2"]));
                            break;
                        case "EXP_PRENOM_PERS_PHYSIQUE":
                            array_push($dest_exploitant_values, addslashes_nullify($row["prenom"]));
                            break;
                        case "EXP_RAISON_SOCIALE":
                            array_push($dest_exploitant_values, addslashes_nullify($row["nom_deb"]));
                            break;
                        case "EXP_NOM":
                            array_push($dest_exploitant_values, addslashes_nullify($row["nom_deb"]));
                            break;
                        case "EXP_VISIBLE":
                            array_push($dest_exploitant_values, "1");
                            break;
                        case "EXP_VALIDE":
                            array_push($dest_exploitant_values, "1");
                            break;
                        case "EXP_NRUE":
                            array_push($dest_exploitant_values, addslashes_nullify(get_from_address("num", $row["adr1"])));
                            break;
                        case "EXP_ADRESSE":
                            array_push($dest_exploitant_values, addslashes_nullify(get_from_address("voie", $row["adr1"])));
                            break;
                        case "EXP_CP":
                            array_push($dest_exploitant_values, addslashes_nullify($row["cpvil"]));
                            break;
                        case "EXP_VILLE":
                            array_push($dest_exploitant_values, addslashes_nullify($row["adr3"]));
                            break;
                        case "EXP_TELEPHONE":
                            array_push($dest_exploitant_values, addslashes_nullify($row["tel"]));
                            break;
                        case "EXP_PORTABLE":
                            array_push($dest_exploitant_values, addslashes_nullify($row["tel_port"]));
                            break;
                        case "EXP_FAX":
                            array_push($dest_exploitant_values, addslashes_nullify($row["fax"]));
                            break;
                        case "EXP_EMAIL":
                            array_push($dest_exploitant_values, addslashes_nullify($row["mail"]));
                            break;
                        default:
                            array_push($dest_exploitant_values, "'TODO'");
                            break;
                    }
                }

                $insert_into_query = "INSERT INTO $dest_exploitant (" . implode(", ", $dest_exploitant_cols) . ") VALUES (" . implode(", ", $dest_exploitant_values) . ")";

                if ($display_dest_requests) echo "$insert_into_query<br>";
                fwrite($output_file, "$insert_into_query;\n");
                if ($exec_dest_requests) $mysql_conn->exec($insert_into_query);

                // Abonnements

                // Pour toutes les colonnes des marchés associées à l'exploitant, regarder s'il y est abonné et si le marché est bien dans son groupe, effectuer une insertion dans ce cas
                foreach ($src_exploitant_marche_cols as $src_exploitant_marche_col) {
                    $matches = array();
                    preg_match('/[0-9]+/', $src_exploitant_marche_col, $matches);
                    $num_abo = isset($matches[0]) ? $matches[0] : "1"; // pour les abonnements, la première colonne s'appelle 'abo1', contrairement à la première colonne des marchés qui s'appelle juste 'm'
                    $num_groupe = isset($matches[0]) ? $matches[0] : ""; // pour les groupes, la première colonne s'appelle juste 'groupe'
                    
                    $marche_code = $row[$src_exploitant_marche_col];
                    $req_groupe_marche = $mysql_conn->query("SELECT groupe FROM $src_marche WHERE code = '$marche_code'")->fetch();
                    $groupe_marche = ($req_groupe_marche == null) ? -1 : $req_groupe_marche["groupe"];

                    if ($marche_code !== "" && isset($row["abo$num_abo"]) && $row["abo$num_abo"] === "1" && isset($row["groupe$num_groupe"]) && $row["groupe$num_groupe"] === $groupe_marche) {
                        $mar_ref = $assoc_marcheCode_marcheRef[$marche_code];
                        $aco_ref = $dest_exploitant_values[array_search("ACO_REF", $dest_exploitant_cols)];
                        $dest_abonnement_values = [$last_exp_ref, $mar_ref, $aco_ref];

                        $insert_into_query = "INSERT INTO $dest_abonnement (" . implode(", ", $dest_abonnement_cols) . ") VALUES (" . implode(", ", $dest_abonnement_values) . ")";

                        if ($display_dest_requests) echo "$insert_into_query<br>";
                        fwrite($output_file, "$insert_into_query;\n");
                        if ($exec_dest_requests) $mysql_conn->exec($insert_into_query);
                    }
                }
            }
            if ($display_dest_requests) echo "</div>";

            // TODO pièces justificatives (table SOCIETE_PROPRIETE LANG et VALEUR)


            echo "</section>";

        } // Fin "si $get analyse = 1"

    ?>

</body>
</html>


<style>

* {
    margin: 0;
    padding: 0;
    font-family: Arial, sans-serif;
    font-size: 13px;
}

body {
    display: flex;
    flex-direction: row;
}

body > * {
    flex: 1;
    flex-direction: column;
}

/* Init */

init {
    flex: 0.75;
    display: flex;
    margin: 30px auto;
    padding: 20px;
}

init * {
    font-size: 103%;
}

init table td, init table th {
    padding: 10px;
    min-width: 200px;
    border: 1px solid rgba(0, 0, 0, 0.4);
}


/* Aside */

aside {
    flex: 0.2;
    background: #343131;
}

aside a {
    display: inline-block;
    padding: 15px 25px;
    font-size: 14px;
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
}

aside a:hover {
    background: rgba(255, 255, 255, 0.13);
}

aside ol {
    display: flex;
    position: sticky;
    top: 15px;
    flex-direction: column;
    list-style-type: none;
}

aside ol li {
    display: flex;
    flex-direction: column;
}

aside ol li a {
    flex: 1;
}

aside ol li ol {
    display: none;
    background: rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(0, 0, 0, 0.1);
}

aside ol li ol li a {
    padding: 10px 35px;
    border: none;
    color: rgba(0, 0, 0, 0.5);
}

aside ol li ol li a:hover {
    padding-left: 35px;
    border: none;
    background: rgba(0, 0, 0, 0.07);
}

aside ol li.active {
    background: white;
}

aside ol li.active > a {
    font-weight: bold;
    color: black;
}

aside ol li.active ol {
    display: flex;
}

/* Section */

section {
    display: flex;
    flex-direction: column;
    padding: 0 3%;
}

section > * {
    flex: 1;
}

/* Titres */

/*h1 {
    margin-top: 30px;
    padding: 5px 0;
    text-align: center;
    font-size: 130%;
    border-top: 1px solid black;
    border-bottom: 1px solid black;
    border-width: 3px;
    border-color: red;
}*/

h1 {
    padding-top: 30px;
    font-size: 24px;
    color: rgba(0, 0, 0, 0.8);
    text-align: center;
}

/*h2 {
    margin: 30px 0 10px 0;
    padding: 5px 0;
    text-align: center;
    font-size: 120%;
    border: 1px solid black;
    background: orange;
}*/

h2 {
    margin: 30px 0;
    padding-top: 30px;
    font-size: 20px;
    color: rgba(0, 0, 0, 0.9);
    font-weight: normal;
    border-top: 1px solid rgba(0, 0, 0, 0.2);
}

h3 {
    margin: 20px 0;
    padding-left: 10px;
    font-size: 110%;
    border-left: 10px solid orange;
}

/* Tableaux */

table {
    width: 100%;
    border-collapse: collapse;
}

table tr {
    border-bottom: 1px solid orange;
}

table td, table th {
    padding: 3px 4px;
    text-align: left;
}

/* Autres */

div.pre {
    padding: 10px;
    font-family: Consolas;
    color: rgba(255, 255, 255, 0.7);
    background: rgba(0, 0, 0, 0.8);
    /* overflow: auto; */
}

a.button {
    display: inline-block;
    width: 50%;
    margin: auto;
    margin-top: 30px;
    padding: 15px 20px;
    font-size: 104%;
    text-align: center;
    text-decoration: none;
    color: rgba(255, 255, 255, 0.8);
    background: #343131;
    border-radius: 5px;
    transition: all .1s ease;
}

ok::before {
    content: "✔";
    color: green;
    margin-right: 5px;
}

nok::before {
    content: "✘";
    color: red;
    margin-right: 5px;
}

</style>

<script>

summary_li = document.querySelector("#sommaire").querySelectorAll("li");
for (li of summary_li) {
    li.onclick = function() { clicked_link(this); }
}

function clicked_link(clicked_li) {
    var new_class_name = (clicked_li.className === "") ? "active" : "";
    for (li of summary_li) {
        li.className = "";
    }
    clicked_li.className = new_class_name;
}

</script>
