<?php

// Entrée : Fichiers Excel dibtic définis dans common.php et récupérés par le script index.php
// Sortie : Fichier SQL contenant les requêtes SQL à envoyer dans la base de données GEODP v1

// Génère des tables à partir des fichiers lus
// Extrait les informations voulues et exécute + donne les requêtes SQL


// Ouvrir cette page avec le paramètre 'analyze=1' pour effectuer des tests en local, l'ouvrir normalement en situation réelle
$test_mode = (isset($_POST["login"]) && isset($_POST["login"]) !== "" && isset($_POST["password"])) ? false : true; // en test la destination est MySQL, en situatiçon réelle la destination est Oracle


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
    "Libellé du marché, jours des marchés",
    "Nom de l’article, unité, prix unitaire, TVA, marchés associés à l’article",
    "Code de l’exploitant, nom, prénom, raison sociale, adresse (rue/CP/ville), date de suppression s’il a été supprimé, numéro de téléphone et de portable, adresse mail, type d’activité et abonnements aux marchés associés à l’exploitant",
    "Type (nom) de l’activité"];

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
$dest_utilisateur = "dest_utilisateur";

$dest_marche = "dest_marche";
$dest_marche_lang = "dest_marche_langue";
$dest_article = "dest_article";
$dest_article_lang = "dest_article_langue";
$dest_activitecomm = "dest_activitecommerciale";
$dest_activitecomm_lang = "dest_activitecommerciale_langue";
$dest_exploitant = "dest_exploitant";
$dest_abonnement = "dest_societe_marche";

$dest_piece = "dest_societe_propriete";
$dest_piece_lang = "dest_societe_propriete_langue";
$dest_piece_val = "dest_societe_propriete_valeur";

if (!$test_mode) {
    $dest_activite = "ACTIVITE";
    $dest_activite_lang = "ACTIVITE_LANGUE";
    $dest_utilisateur = "UTILISATEUR";

    $dest_marche = "MARCHE";
    $dest_marche_lang = "MARCHE_LANGUE";
    $dest_article = "ARTICLE";
    $dest_article_lang = "ARTICLE_LANGUE";
    $dest_activitecomm = "ACTIVITECOMMERCIALE";
    $dest_activitecomm_lang = "ACTIVITECOMMERCIALE_LANGUE";
    $dest_exploitant = "EXPLOITANT";
    $dest_abonnement = "SOCIETE_MARCHE";

    $dest_piece = "SOCIETE_PROPRIETE";
    $dest_piece_lang = "SOCIETE_PROPRIETE_LANGUE";
    $dest_piece_val = "SOCIETE_PROPRIETE_VALEUR";
}

// Valeurs des champs DCREAT et UCREAT utilisées dans les requêtes SQL
$dest_dcreat = $test_mode ? date("y/m/d", time()) : date("d/m/y", time());
$dest_ucreat = "ILTR";

// Mettre à vrai pour supprimer les données des tables de destination avant l'insertion des nouvelles, mettre à faux sinon
$erase_destination_tables = true; // true | false

// Mettre à vrai pour afficher les requêtes SQL relatives aux tables de destination, mettre à faux sinon (dans tous les cas, le fichier $output_filename est généré)
$display_dest_requests = false; // true | false

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

$oracle_host = "ares"; // zeus | ares
$oracle_port = "1521";
$oracle_service = "xe"; // orcl | xe
$oracle_login = $test_mode ? "geodpthibaut" : $_POST["login"];
$oracle_password = $test_mode ? "geodpthibaut" : $_POST["password"];

if (!$test_mode && isset($_GET["analyze"]) && $_GET["analyze"] === "1") {
    $oracle_conn = new PDO("oci:dbname=(DESCRIPTION = (ADDRESS_LIST = (ADDRESS = (PROTOCOL = TCP) (Host = $oracle_host) (Port = $oracle_port))) (CONNECT_DATA = (SERVICE_NAME = ".$oracle_service.")))", $oracle_login, $oracle_password);
}


/*************************
 *    CHOIX CONNEXION    *
 *************************/

$src_conn = $mysql_conn;
$dest_conn = $test_mode ? $mysql_conn : $oracle_conn;


/*************************
 *   FONCTIONS ACCUEIL   *
 *************************/

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


/*************************
 *   FONCTIONS ANALYSE   *
 *************************/

$output_file = fopen($output_filename, "w+");
$usual_days = ["lundi", "mardi", "mercredi", "jeudi", "vendredi", "samedi", "dimanche"];

function yes_no($bool) {
    return $bool ? "Oui" : "Non";
}

function get_from_address($info, $address) {
    switch ($info) {
        case "num":
            $matches = [];
            preg_match('/^( )*[0-9]+/', $address, $matches);
            return isset($matches[0]) ? $matches[0] : "";
            break;
        case "voie":
            $matches = [];
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

function build_query($main, $where, $order, $limit) {
    global $test_mode;

    $query = "$main";
    if ($where !== "") {
        $query .= " WHERE $where";
    } 
    if (!$test_mode && $limit !== "") {
        $query .= ($where === "") ? " WHERE" : " AND";
        $query .= " ROWNUM <= $limit";
    }
    if ($order !== "") {
        $query .= " ORDER BY $order";
    }
    if ($test_mode && $limit !== "") {
        $query .= " LIMIT $limit";
    }

    if (!$test_mode) {
        $query = str_replace("\'", "''", $query);
    }

    return $query;
}

function execute_query($query, &$nb_executed, &$nb_to_execute) {
    global $test_mode, $display_dest_requests, $exec_dest_requests, $dest_conn, $output_file;

    if (!$test_mode) {
        $query = str_replace("\\'", "''", $query);
    }

    if ($display_dest_requests) echo "$query<br>";

    fwrite($output_file, "$query;\n");

    if ($exec_dest_requests) {
        ++$nb_to_execute;
        $req_res = $dest_conn->exec($query);
        if ($req_res === false) {
            echo "<p class=\"danger\">".$dest_conn->errorInfo()[2]."</p>";
        } else {
            if ($req_res === 0) {
                echo "<p class=\"warning\">0 lignes affectées</p>";
            }
            ++$nb_executed;
        }
    }
}

function summarize_queries($nb_inserted, $nb_to_insert, &$nb_errors, $warnings, &$nb_warnings) {
    foreach ($warnings as $warning) {
        echo "<p class=\"warning\">$warning</p>";
    }
    $nb_warnings += count($warnings);

    $class = ($nb_inserted == $nb_to_insert) ? "success" : "danger";
    echo "<p class=\"$class\">$nb_inserted requêtes réussies sur $nb_to_insert exécutées</p>";
    $nb_errors += $nb_to_insert - $nb_inserted;
}


/*************************
 *    LECTURE DOSSIER    *
 *************************/

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
    
        /*************************
         *        ACCUEIL        *
         *************************/
        
        if (!isset($_GET["analyze"]) || $_GET["analyze"] !== "1") {

            echo "<init>";
            echo "<h1>Reprise dibtic vers GEODP v1</h1>";

            echo "<form action=\"from_dibtic_to_geodpv1.php?analyze=1\" method=\"POST\">";

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

                    $button_disabled = "";
                    if (count($files_to_convert) != count($expected_content)) {
                        echo "Des fichiers sont manquants :<ul>";
                        for ($i = 0; $i < count($expected_content); ++$i) {
                            if (!isset($files_to_convert[$i])) {
                                echo "<li>" . $expected_content[$i] . " (" . $keywords_files[$i] . ")</li>";
                            }
                        }
                        echo "</ul>";
                        $button_disabled = "disabled";
                    }

                echo "<h2>Paramètres du client</h2>";

                    echo "<field><label for=\"servor\">Serveur de connexion Oracle</label><input id=\"servor\" type=\"disabled\" value=\"$oracle_host:$oracle_port/$oracle_service\" disabled /></field>";
                    echo "<field><label for=\"login\">Identifiant de connexion Oracle</label><input id=\"login\" name=\"login\" onchange=\"autocomplete_password(this)\" type=\"text\" placeholder=\"geodpville\" required /></field>";
                    echo "<field><label for=\"password\">Mot de passe de connexion Oracle</label><input id=\"password\" name=\"password\" type=\"password\"/><span onmousedown=\"show_password(true)\" onmouseup=\"show_password(false) \">&#128065;</span></field>";
                    echo "<field><label for=\"type\">Type de client</label><input id=\"type\" name=\"type\" type=\"text\" placeholder=\"A définir\"/></field>";

                echo "<h2></h2>";

                echo "<field>";
                    echo "<a class=\"button\" href=\"?analyze=1\">Tester la reprise en local</a>";
                    echo "<input type=\"submit\" value=\"Créer un client GEODP à partir de ces fichiers\" $button_disabled />";
                echo "</field>";
                
            echo "</form>";

            echo "</init>";
        }

        /*************************
         *        ANALYSE        *
         *************************/

        if (isset($_GET["analyze"]) && $_GET["analyze"] === "1") {
            
            //// Sommaire

            echo "<aside>";
            echo "<h1>Reprise dibtic vers GEODP v1</h1>";
            echo "<ol id=\"sommaire\">";
                echo "<li><a href=\"#summary\">Résumé</a></li>";
                echo "<li><a href=\"#parameters\">Paramètres</a>";
                    echo "<ol>";
                        echo "<li><a href=\"#oracle\">Serveur Oracle</a></li>";
                        echo "<li><a href=\"#src_datas\">Données source</a></li>";
                        echo "<li><a href=\"#dest_datas\">Données extraites</a></li>";
                    echo "</ol>";
                echo "</li>";
                echo "<li><a href=\"#source\">Chargement des fichiers sources dibtic</a>";
                    echo "<ol>";
                        foreach ($expected_content as $exp) {
                            echo "<li><a href=\"#file_" . prune($source_files[$exp]) . "\">Fichier des $exp</a></li>";
                        }
                    echo "</ol>";
                echo "</li>";
                echo "<li><a href=\"#preparation\">Création du client GEODP</a>";
                    echo "<ol>";
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
                        echo "<li><a href=\"#datas_pieces\">Pièces justificatives / Pièces justificatives Langue / Pièces justificatives Valeur</a></li>";
                    echo "</ol>";
                echo "</li>";
            echo "</ol>";
            echo "</aside>";

            echo "<section>";

            echo "<summary id=\"summary\">";
                echo $test_mode ? "<h1>[ MODE TEST ]</h1>" : "<h1>".$_POST["type"]."</h1>";
                echo "<p id=\"nb_errors\"></p>";
                echo $test_mode ? "<div><a href=\"from_dibtic_to_geodpv1.php\">Retour à l'accueil</a></div>" : "<div><a target=\"_blank\" href=\"//ares/geodp.".substr($oracle_login, strlen("geodp"))."\">ares/geodp.".substr($oracle_login, strlen("geodp"))."</a></div>";
            echo "</summary>";

            $nb_errors = 0;
            $nb_warnings = 0;
            
            if ($erase_destination_tables) {
                $dest_conn->exec("DELETE FROM $dest_abonnement");
                $dest_conn->exec("DELETE FROM $dest_exploitant");

                $dest_conn->exec("DELETE FROM $dest_activitecomm_lang");
                $dest_conn->exec("DELETE FROM $dest_activitecomm");

                $dest_conn->exec("DELETE FROM $dest_article_lang");
                $dest_conn->exec("DELETE FROM $dest_article");

                $dest_conn->exec("DELETE FROM $dest_marche_lang");
                $dest_conn->exec("DELETE FROM $dest_marche");

                $dest_conn->exec("DELETE FROM $dest_activite_lang");
                $dest_conn->exec("DELETE FROM $dest_activite"); // FK_UAL_UA_ACT_REF_UTI_REF dépendance introuvable // TODO
            }

            //// Paramètres

            echo "<h1 id=\"parameters\">Paramètres</h1>";

            echo "<h2 id=\"oracle\">Serveur oracle</h2>";
            
            if ($test_mode) {
                echo "<div>Aucune connexion au serveur Oracle n'est établie durant la phase de test.</div>";
            } else {
                echo "<form>";
                    echo "<field><label>Serveur de connexion Oracle</label><input type=\"disabled\" value=\"$oracle_host:$oracle_port/$oracle_service\" disabled /></field>";
                    echo "<field><label>Identifiant de connexion Oracle</label><input type=\"disabled\" value=\"$oracle_login\" disabled /></field>";
                    echo "<field><label>Mot de passe Oracle</label><input type=\"disabled\" value=\"$oracle_password\" disabled /></field>";
                echo "</form>";
            }

            echo "<h2 id=\"src_datas\">Données sources (fichiers dibtic vers tables MySQL)</h2>";
            
            echo "<form>";
                echo "<field><label>Afficher le contenu des fichiers sources lors de leur lecture</label><input type=\"disabled\" value=\"".yes_no($display_source_files_contents)."\" disabled /></field>";
            echo "</form>";

            echo ($test_mode) ? "<h2 id=\"dest_datas\">Données extraites (requêtes SQL pour tables MySQL)</h2>" : "<h2 id=\"dest_datas\">Données extraites (requêtes SQL pour tables Oracle)</h2>";

            echo "<form>";
                echo "<field><label>Vider les tables de destination en amont</label><input type=\"disabled\" value=\"".yes_no($erase_destination_tables)."\" disabled /></field>";
                echo "<field><label>Afficher les requêtes à exécuter</label><input type=\"disabled\" value=\"".yes_no($display_dest_requests)."\" disabled /></field>";
                echo "<field><label>Exécuter les requêtes</label><input type=\"disabled\" value=\"".yes_no($exec_dest_requests)."\" disabled /></field>";
            echo "</form>";

            //// Chargement des fichiers sources dibtic
            
            echo "<h1 id=\"source\">Chargement des fichiers sources dibtic</h1>";
            
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
                $src_conn->exec("DROP TABLE IF EXISTS $source_table");

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
                $src_conn->exec($create_table_query);

                // Remplissage de la table créée avec les données du fichier

                echo "<h3>Remplissage de la table créée avec les données du fichier</h3>";

                $nb_inserted = 0;

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
                    $nb_inserted += $src_conn->exec($insert_into_query);
                }
                // echo "</div>";

                // Contrôle du nombre d'insertions faites par rapport au nombre de données du fichier

                echo "<h3>Contrôle du nombre d'insertions faites par rapport au nombre de données du fichier</h3>";

                summarize_queries($nb_inserted, count($xls_data) - 1), $nb_errors, [], $nb_warnings);
    */
            }

            // Association du nom des tables avec le contenu de leur fichier
            $src_marche = $src_tables["marchés"];
            $src_article = $src_tables["articles"];
            $src_activite = $src_tables["activités"];
            $src_exploitant = $src_tables["exploitants"];

            // Récupération des colonnes des classes d'articles (elles commencent par "classe" et sont peut-être suivies par un entier)
            $src_exploitant_classe_cols = [];
            foreach ($src_conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$src_exploitant'") as $row) {
                $matches = [];
                preg_match('/^classe[0-9]*$/', $row["COLUMN_NAME"], $matches);
                if (isset($matches[0])) {
                    array_push($src_exploitant_classe_cols, $matches[0]);
                }
            }

            // Récupération des colonnes des marchés (elles commencent par "m" et sont peut-être suivies par un entier)
            $src_exploitant_marche_cols = [];
            foreach ($src_conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$src_exploitant'") as $row) {
                $matches = [];
                preg_match('/^m[0-9]*$/', $row["COLUMN_NAME"], $matches);
                if (isset($matches[0])) {
                    array_push($src_exploitant_marche_cols, $matches[0]);
                }
            }

            // Récupération des colonnes des pièces justificatives (elles commencent par "n" et sont peut-être suivies par un entier)
            $src_exploitant_piece_cols = [];
            foreach ($src_conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$src_exploitant'") as $row) {
                $matches = [];
                preg_match('/^n[0-9]*$/', $row["COLUMN_NAME"], $matches);
                if (isset($matches[0])) {
                    array_push($src_exploitant_piece_cols, $matches[0]);
                }
            }

            //// Création du client GEODP

            echo "<h1 id=\"preparation\">Création du client GEODP</h1>";
            fwrite($output_file, "\n---------------------------------------------\n-- Création du client GEODP\n--\n\n");

            $dest_activite_cols = ["ACT_REF", "ACT_NUM_CSS", "ACT_MODULE", "DCREAT", "UCREAT", "ACT_VISIBLE"];
            $dest_activite_lang_cols = ["ACT_REF", "LAN_REF", "ACT_NOM", "DCREAT", "UCREAT"];

            // Groupe d'activité

            echo "<h2 id=\"groupe_activite\">Groupe d'activité</h2>";
            fwrite($output_file, "\n-- Groupe d'activité\n\n");

            // Activité / Activité Langue

            echo "<h2 id=\"activite\">Activité / Activité Langue</h2>";
            fwrite($output_file, "\n-- Activité / Activité Langue\n\n");

            $nb_to_insert = 0;
            $nb_inserted = 0;

            $req_last_act_ref = $dest_conn->query(build_query("SELECT ACT_REF FROM $dest_activite", "", "ACT_REF DESC", "1"))->fetch();
            $last_act_ref = ($req_last_act_ref == null) ? 0 : $req_last_act_ref["ACT_REF"];
            ++$last_act_ref;

            if ($display_dest_requests) echo "<div class=\"pre\">";

            $dest_activite_values = [$last_act_ref, 13, "'placier'", "'$dest_dcreat'", "'$dest_ucreat'", 1]; // TODO ACT_NUM_CSS = 13 ???

            $insert_into_query = "INSERT INTO $dest_activite (" . implode(", ", $dest_activite_cols) . ") VALUES (" . implode(", ", $dest_activite_values) . ")";
            execute_query($insert_into_query, $nb_inserted, $nb_to_insert);

            $dest_activite_lang_values = [$last_act_ref, 1, "'Placier'", "'$dest_dcreat'", "'$dest_ucreat'"];

            $insert_into_query = "INSERT INTO $dest_activite_lang (" . implode(", ", $dest_activite_lang_cols) . ") VALUES (" . implode(", ", $dest_activite_lang_values) . ")";
            execute_query($insert_into_query, $nb_inserted, $nb_to_insert);

            if ($display_dest_requests) echo "</div>";
            
            summarize_queries($nb_inserted, $nb_to_insert, $nb_errors, [], $nb_warnings);

            // Utilisateur / Utilisateur Activité Langue

            echo "<h2 id=\"utilisateur\">Utilisateur / Utilisateur Activité Langue</h2>";
            fwrite($output_file, "\n-- Utilisateur / Utilisateur Activité Langue\n\n");

            $nb_to_insert = 0;
            $nb_inserted = 0;
            
            $req_last_uti_ref = $dest_conn->query(build_query("SELECT UTI_REF FROM $dest_utilisateur", "", "UTI_REF DESC", "1"))->fetch();
            $last_uti_ref = ($req_last_uti_ref == null) ? 0 : $req_last_uti_ref["UTI_REF"];

            if ($display_dest_requests) echo "<div class=\"pre\">";

            $uti_nom = (isset($_POST["type"]) && $_POST["type"] !== "") ? $_POST["type"] : "A définir";
            $update_query = "UPDATE $dest_utilisateur SET UTI_NOM = '$uti_nom' WHERE UTI_REF = $last_uti_ref";
            execute_query($update_query, $nb_inserted, $nb_to_insert);

            if ($display_dest_requests) echo "</div>";

            summarize_queries($nb_inserted, $nb_to_insert, $nb_errors, [], $nb_warnings);

            //// Insertion des données GEODP

            echo "<h1 id=\"destination\">Insertion des données GEODP</h1>";
            fwrite($output_file, "\n---------------------------------------------\n-- Insertion des données GEODP\n--\n\n");
            
            $dest_marche_cols = ["MAR_REF", "UTI_REF", "ACT_REF", "MAR_JOUR", "MAR_VISIBLE", "MAR_CODE", "DCREAT", "UCREAT", "MAR_VALIDE"];
            $dest_marche_lang_cols = ["MAR_REF", "LAN_REF", "MAR_NOM", "DCREAT", "UCREAT"];

            $dest_article_cols = ["ART_REF", "MAR_REF", "UTI_REF", "ART_PRIX_TTC", "ART_PRIX_HT", "ART_TAUX_TVA", "ART_COULEUR", "ART_VALIDE_DEPUIS", "ART_VALIDE_JUSQUA", "ART_VISIBLE", "DCREAT", "UCREAT"];
            $dest_article_lang_cols = ["ART_REF", "LAN_REF", "ART_NOM", "ART_UNITE", "DCREAT", "UCREAT"];
            
            $dest_activitecomm_cols = ["ACO_REF", "UTI_REF", "ACO_COULEUR", "DCREAT", "UCREAT"];
            $dest_activitecomm_lang_cols = ["ACO_REF", "LAN_REF", "ACO_NOM", "DCREAT", "UCREAT"];
            
            $dest_exploitant_cols = ["EXP_REF", "EXP_CODE", "UTI_REF", "GRA_REF", "LAN_REF", "ACO_REF", "EXP_NOM_PERS_PHYSIQUE", "EXP_PRENOM_PERS_PHYSIQUE", "EXP_RAISON_SOCIALE", "EXP_NOM", "EXP_VISIBLE", "EXP_VALIDE", "EXP_NRUE", "EXP_ADRESSE", "EXP_CP", "EXP_VILLE", "EXP_TELEPHONE", "EXP_PORTABLE", "EXP_FAX", "EXP_EMAIL", "DCREAT", "UCREAT"];
            
            $dest_abonnement_cols = ["EXP_REF", "MAR_REF", "ACO_REF", "SMA_TITULAIRE", "SMA_ABONNE", "DCREAT", "UCREAT"];

            $dest_piece_cols = ["PROP_REF", "ACT_REF", "UTI_REF", "DCREAT", "UCREAT"];
            $dest_piece_lang_cols = ["PROP_REF", "LAN_REF", "PROP_NOM", "DCREAT", "UCREAT"];
            $dest_piece_valeur_cols = ["EXP_REF", "PROP_REF", "PROP_VALEUR", "PROP_DATE", "PROP_DATE_VALIDITE", "DCREAT", "UCREAT"];

            // Marchés / Marchés Langue

            echo "<h2 id=\"datas_marches\">Marchés / Marchés Langue</h2>";
            fwrite($output_file, "\n-- Marchés / Marchés Langue\n\n");

            $nb_to_insert = 0;
            $nb_inserted = 0;

            $req_last_mar_ref = $dest_conn->query(build_query("SELECT MAR_REF FROM $dest_marche", "", "MAR_REF DESC", "1"))->fetch();
            $last_mar_ref = ($req_last_mar_ref == null) ? 0 : $req_last_mar_ref["MAR_REF"];

            if ($display_dest_requests) echo "<div class=\"pre\">";
            foreach ($src_conn->query("SELECT * FROM $src_marche") as $row) {
                // Créer un marché pour chaque jour de la semaine où le marché est ouvert (il en résultera plusieurs lignes correspondant à un même marché mais associé à des jours différents)
                // Si le marché est présent tous les jours, n'insérer qu'un seul jour et mettre à 'null' MAR_JOUR
                $all_the_week = true;
                foreach ($usual_days as $mar_day) {
                    if ($row[$mar_day] === "0") {
                        $all_the_week = $all_the_week && false;
                    }
                }

                foreach ($usual_days as $mar_day) {
                    if ($row[$mar_day] === "1") {
                        $last_mar_ref += 1;

                        $dest_marche_values = [];

                        foreach ($dest_marche_cols as $col) {
                            switch ($col) {
                                case "MAR_REF":
                                    array_push($dest_marche_values, "$last_mar_ref");
                                    break;
                                case "UTI_REF":
                                    array_push($dest_marche_values, "$last_uti_ref");
                                    break;
                                case "ACT_REF":
                                    array_push($dest_marche_values, "$last_act_ref");
                                    break;
                                case "MAR_JOUR":
                                    if ($all_the_week) array_push($dest_marche_values, "NULL");
                                    else array_push($dest_marche_values, "'$mar_day'");
                                    break;
                                case "MAR_VISIBLE":
                                    array_push($dest_marche_values, "1");
                                    break;
                                case "MAR_CODE":
                                    array_push($dest_marche_values, "'".$row["code"]."'");
                                    break;
                                case "DCREAT":
                                    array_push($dest_marche_values, "'$dest_dcreat'");
                                    break;
                                case "UCREAT":
                                    array_push($dest_marche_values, "'$dest_ucreat'");
                                    break;
                                case "MAR_VALIDE":
                                    array_push($dest_marche_values, "1");
                                    break;
                                default:
                                    array_push($dest_marche_values, "'TODO'");
                                    break;
                            }
                        }
                        
                        $insert_into_query = "INSERT INTO $dest_marche (" . implode(", ", $dest_marche_cols) . ") VALUES (" . implode(", ", $dest_marche_values) . ")";
                        execute_query($insert_into_query, $nb_inserted, $nb_to_insert);

                        // Marché langue

                        $completement_mar_nom = $all_the_week ? "" : " ".strtoupper($mar_day);
                        $dest_marche_lang_values = [$last_mar_ref, 1, "'".addslashes($row["libelle"])."$completement_mar_nom'", "'$dest_dcreat'", "'$dest_ucreat'"];

                        $insert_into_query = "INSERT INTO $dest_marche_lang (" . implode(", ", $dest_marche_lang_cols) . ") VALUES (" . implode(", ", $dest_marche_lang_values) . ")";
                        execute_query($insert_into_query, $nb_inserted, $nb_to_insert);
                    } // Fin "si $row[$mar_day] = 1"
                    if ($all_the_week) break; // Arrêter la boucle de parcours des jours si le marché est ouvert toute la semaine, car dans ce cas, une seule insertion est faite au lieu de 7
                } // Fin "pour chaque jour de la semaine"
            }
            if ($display_dest_requests) echo "</div>";

            summarize_queries($nb_inserted, $nb_to_insert, $nb_errors, [], $nb_warnings);

            // Articles / Articles Langue

            echo "<h2 id=\"datas_articles\">Articles / Articles Langue</h2>";
            fwrite($output_file, "\n-- Articles / Articles Langue\n\n");

            $nb_to_insert = 0;
            $nb_inserted = 0;
            
            $req_last_art_ref = $dest_conn->query(build_query("SELECT ART_REF FROM $dest_article", "", "ART_REF DESC", "1"))->fetch();
            $last_art_ref = ($req_last_art_ref == null) ? 0 : $req_last_art_ref["ART_REF"];

            if ($display_dest_requests) echo "<div class=\"pre\">";
            foreach ($src_conn->query("SELECT * FROM $src_article WHERE nom != ''") as $row) {
                $dest_marches_ref = [];
                // Pour obtenir les marchés de référence de cet article (une insertion de cet article pour chaque marché de ref trouvé, il en résultera plusieurs lignes correspondant à un même article mais associé à des marchés différents) :
                //   Regarder chaque colonne des classes et des groupes dans la table source des exploitants (classe`i`/groupe`i`)
                //     Si le combo groupe-classe correspond à celui de l'article, alors lire le marché dans la colonne correspondante
                //       Si le combo groupe-marché existe, considérer ce marché comme un marché de référence pour cet article

                $src_art_code = $row["code"];
                $src_art_numc = $row["numc"];

                foreach ($src_exploitant_classe_cols as $src_exploitant_classe_col) { // pour chaque colonne de classe possible, récupère les exploitants qui matchent avec le combo groupe-classe de l'article en cours
                    $matches = [];
                    preg_match('/[0-9]+/', $src_exploitant_classe_col, $matches);
                    $num_groupe = isset($matches[0]) ? $matches[0] : ""; // pour les groupes, la première colonne s'appelle juste 'groupe', comme pour les classes

                    foreach ($src_conn->query("SELECT * FROM $src_exploitant WHERE groupe$num_groupe = '$src_art_numc' AND $src_exploitant_classe_col = '$src_art_code'") as $exploitant) { // WHERE date_suppr = '' OR date_suppr = '  -   -'"
                        // $src_art_groupe = $row["groupe$num_groupe"];
                        // $src_art_classe = $row["$src_exploitant_classe_col"];

                        $num_m = isset($matches[0]) ? $matches[0] : ""; // pour les marchés, la première colonne s'appelle juste 'm', comme pour les classes et les groupes
                        $marche_code = $exploitant["m$num_m"];
                        $req_groupe_marche = $src_conn->query("SELECT groupe FROM $src_marche WHERE code = '$marche_code'")->fetch();
                        $groupe_marche = ($req_groupe_marche == null) ? -1 : $req_groupe_marche["groupe"];

                        if ($groupe_marche === $src_art_numc) { // ici, $src_art_numc est forcément équivalent au groupe de la colonne groupe$num_groupe de l'exploitant, donc on peut le comparer avec $groupe_marche
                            // Il y a plusieurs marché qui ont le même code (un marché est ouvert x jours -> x marchés insérés, qui ont tous les même code de marché)
                            // Il faut donc récupérer dans la table de destination des marchés toutes les MAR_REF où il y a ce code et les ajouter au tableau pour que l'article soit associé à tous les marchés portant ce code
                            foreach ($dest_conn->query(build_query("SELECT MAR_REF FROM $dest_marche", "MAR_CODE = '$marche_code'", "", "")) as $marche) {
                                $marche_ref = $marche["MAR_REF"];
                                if (!in_array($marche_ref, $dest_marches_ref)) {
                                    array_push($dest_marches_ref, $marche_ref);
                                }
                            }
                        }
                    }
                }

                // Insérer un nouvel article pour chacun de ses marchés de référence
                foreach ($dest_marches_ref as $mar_ref) {
                    $last_art_ref += 1;
        
                    $dest_article_values = [];

                    foreach ($dest_article_cols as $col) {
                        switch ($col) {
                            case "ART_REF":
                                array_push($dest_article_values, "$last_art_ref");
                                break;
                            case "UTI_REF":
                                array_push($dest_article_values, "$last_uti_ref");
                                break;
                            case "MAR_REF":
                                array_push($dest_article_values, "$mar_ref");
                                break;
                            case "ART_PRIX_TTC":
                                if ($row["tva"] === "") {
                                    array_push($dest_article_values, str_replace(' ', '', $row["prix_unit"]));
                                } else {
                                    array_push($dest_article_values, "NULL");
                                }
                                break;
                            case "ART_PRIX_HT":
                                if ($row["tva"] !== "") {
                                    array_push($dest_article_values, str_replace(' ', '', $row["prix_unit"]));
                                } else {
                                    array_push($dest_article_values, "NULL");
                                }
                                break;
                            case "ART_TAUX_TVA":
                                if ($row["tva"] !== "") {
                                    array_push($dest_article_values, str_replace(' ', '', $row["tva"]));
                                } else {
                                    array_push($dest_article_values, "NULL");
                                }
                                break;
                            case "ART_COULEUR":
                                array_push($dest_article_values, "'fff'");
                                break;
                            case "ART_VALIDE_DEPUIS":
                                // 1 janvier de cette année
                                $first_jan = $test_mode ? date("y/01/01", time()) : date("01/01/y", time());
                                array_push($dest_article_values, "'$first_jan'");
                                break;
                            case "ART_VALIDE_JUSQUA":
                                // 31 décembre de cette année
                                $last_dec = $test_mode ? date("y/12/31", time()) : date("31/12/y", time());
                                array_push($dest_article_values, "'$last_dec'");
                                break;
                            case "ART_VISIBLE":
                                array_push($dest_article_values, "1");
                                break;
                            case "DCREAT":
                                array_push($dest_article_values, "'$dest_dcreat'");
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
                    execute_query($insert_into_query, $nb_inserted, $nb_to_insert);

                    // Article langue

                    $dest_article_lang_values = [$last_art_ref, 1, "'".addslashes($row["nom"])."'", "'".addslashes($row["unite"])."'", "'$dest_dcreat'", "'$dest_ucreat'"];

                    $insert_into_query = "INSERT INTO $dest_article_lang (" . implode(", ", $dest_article_lang_cols) . ") VALUES (" . implode(", ", $dest_article_lang_values) . ")";
                    execute_query($insert_into_query, $nb_inserted, $nb_to_insert);
                } // Fin "pour chaque MAR_REF"
            }
            if ($display_dest_requests) echo "</div>";

            summarize_queries($nb_inserted, $nb_to_insert, $nb_errors, [], $nb_warnings);

            // Activités

            echo "<h2 id=\"datas_activites_comm\">Activités commerciales / Activités commerciales Langue</h2>";
            fwrite($output_file, "\n-- Activités commerciales / Activités commerciales Langue\n\n");

            $nb_to_insert = 0;
            $nb_inserted = 0;
            
            $req_last_aco_ref = $dest_conn->query(build_query("SELECT ACO_REF FROM $dest_activitecomm", "", "ACO_REF DESC", "1"))->fetch();
            $last_aco_ref = ($req_last_aco_ref == null) ? 0 : $req_last_aco_ref["ACO_REF"];

            if ($display_dest_requests) echo "<div class=\"pre\">";
            foreach ($src_conn->query("SELECT * FROM $src_activite") as $row) {
                $last_aco_ref += 1;

                $dest_activitecomm_values = [];
                
                foreach ($dest_activitecomm_cols as $col) {
                    switch ($col) {
                        case "ACO_REF":
                            array_push($dest_activitecomm_values, "$last_aco_ref");
                            break;
                        case "UTI_REF":
                            array_push($dest_activitecomm_values, "$last_uti_ref");
                            break;
                        case "ACO_COULEUR":
                            array_push($dest_activitecomm_values, "'fff'");
                            break;
                        case "DCREAT":
                            array_push($dest_activitecomm_values, "'$dest_dcreat'");
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
                execute_query($insert_into_query, $nb_inserted, $nb_to_insert);

                // Activité langue

                $dest_activitecomm_lang_values = [$last_aco_ref, 1, "'".addslashes($row["activiti"])."'", "'$dest_dcreat'", "'$dest_ucreat'"];

                $insert_into_query = "INSERT INTO $dest_activitecomm_lang (" . implode(", ", $dest_activitecomm_lang_cols) . ") VALUES (" . implode(", ", $dest_activitecomm_lang_values) . ")";
                execute_query($insert_into_query, $nb_inserted, $nb_to_insert);
            }
            if ($display_dest_requests) echo "</div>";

            summarize_queries($nb_inserted, $nb_to_insert, $nb_errors, [], $nb_warnings);

            // Exploitants / Abonnements

            echo "<h2 id=\"datas_exploitants\">Exploitants / Abonnements</h2>";
            fwrite($output_file, "\n-- Exploitants / Abonnements\n\n");

            $nb_to_insert = 0;
            $nb_inserted = 0;
            $warnings = [];

            $req_last_exp_ref = $dest_conn->query(build_query("SELECT EXP_REF FROM $dest_exploitant", "", "EXP_REF DESC", "1"))->fetch();
            $last_exp_ref = ($req_last_exp_ref == null) ? 0 : $req_last_exp_ref["EXP_REF"];

            if ($display_dest_requests) echo "<div class=\"pre\">";
            foreach ($src_conn->query("SELECT * FROM $src_exploitant WHERE date_suppr = '' OR date_suppr = '  -   -'") as $row) {
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
                            array_push($dest_exploitant_values, "$last_uti_ref");
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
                                $req_aco_ref = $dest_conn->query(build_query("SELECT ACO_REF FROM $dest_activitecomm_lang", "ACO_NOM = '".addslashes(strtoupper($row["type"]))."'", "", ""))->fetch();
                                $aco_ref = ($req_aco_ref == null) ? NULL : $req_aco_ref["ACO_REF"];
                                if ($aco_ref == NULL) {
                                    array_push($warnings, "L'activité " . addslashes(strtoupper($row["type"])) . " de l'exploitant " . $row["nom_deb"] . " n'existe pas encore dans la table $dest_activitecomm, elle y est donc insérée");

                                    if ($display_dest_requests) echo "-- L'activité " . addslashes(strtoupper($row["type"])) . " lue dans le fichier des exploitants est manquante dans le fichier des activités.<br>";
                                    fwrite($output_file, "-- L'activité " . addslashes(strtoupper($row["type"])) . " lue dans le fichier des exploitants est manquante dans le fichier des activités.\n");
                                    
                                    $req_last_aco_ref = $dest_conn->query(build_query("SELECT ACO_REF FROM $dest_activitecomm", "", "ACO_REF DESC", "1"))->fetch();
                                    $last_aco_ref = ($req_last_aco_ref == null) ? 0 : $req_last_aco_ref["ACO_REF"];
                                    ++$last_aco_ref;

                                    // Activité

                                    $dest_activitecomm_values = [$last_aco_ref, $last_uti_ref, "'fff'", "'$dest_dcreat'", "'$dest_ucreat'"];

                                    $insert_into_query = "INSERT INTO $dest_activitecomm (" . implode(", ", $dest_activitecomm_cols) . ") VALUES (" . implode(", ", $dest_activitecomm_values) . ")";
                                    if ($display_dest_requests) echo "-- Correctif appliqué à la table $dest_activitecomm :<br>";
                                    fwrite($output_file, "-- Correctif appliqué à la table $dest_activitecomm :\n");
                                    execute_query($insert_into_query, $nb_inserted, $nb_to_insert);

                                    // Activité langue

                                    $dest_activitecomm_lang_values = [$last_aco_ref, 1, "'".addslashes(strtoupper($row["type"]))."'", "'$dest_dcreat'", "'$dest_ucreat'"];

                                    $insert_into_query = "INSERT INTO $dest_activitecomm_lang (" . implode(", ", $dest_activitecomm_lang_cols) . ") VALUES (" . implode(", ", $dest_activitecomm_lang_values) . ")";
                                    if ($display_dest_requests) echo "-- Correctif appliqué à la table $dest_activitecomm_lang :<br>";
                                    fwrite($output_file, "-- Correctif appliqué à la table $dest_activitecomm_lang :\n");
                                    execute_query($insert_into_query, $nb_inserted, $nb_to_insert);

                                    if ($display_dest_requests) echo "-- Fin des correctifs<br>";
                                    fwrite($output_file, "-- Fin des correctifs\n");

                                    $aco_ref = $last_aco_ref;
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
                        case "DCREAT":
                            array_push($dest_exploitant_values, "'$dest_dcreat'");
                            break;
                        case "UCREAT":
                            array_push($dest_exploitant_values, "'$dest_ucreat'");
                            break;
                        default:
                            array_push($dest_exploitant_values, "'TODO'");
                            break;
                    }
                }

                $insert_into_query = "INSERT INTO $dest_exploitant (" . implode(", ", $dest_exploitant_cols) . ") VALUES (" . implode(", ", $dest_exploitant_values) . ")";
                execute_query($insert_into_query, $nb_inserted, $nb_to_insert);

                // Abonnements

                $aco_ref = $dest_exploitant_values[array_search("ACO_REF", $dest_exploitant_cols)];

                // Pour toutes les colonnes des marchés associées à l'exploitant, si le marché est bien dans son groupe, regarder s'il y est abonné et effectuer une insertion pour chaque jour où il y est abonné
                foreach ($src_exploitant_marche_cols as $src_exploitant_marche_col) {
                    $matches = [];
                    preg_match('/[0-9]+/', $src_exploitant_marche_col, $matches);
                    $num_abo = isset($matches[0]) ? $matches[0] : "1"; // pour les abonnements, la première colonne s'appelle 'abo1', contrairement à la première colonne des marchés qui s'appelle juste 'm'
                    $num_groupe = isset($matches[0]) ? $matches[0] : ""; // pour les groupes, la première colonne s'appelle juste 'groupe'
                    $num_day = isset($matches[0]) ? $matches[0] : "1"; // pour les jours, la première colonne s'appelle '`prefixe_du_jour`1'
                    $aco_ref = $dest_exploitant_values[array_search("ACO_REF", $dest_exploitant_cols)];
                    
                    $marche_code = $row[$src_exploitant_marche_col];

                    if ($marche_code !== "") {
                        $req_groupe_marche = $src_conn->query("SELECT groupe FROM $src_marche WHERE code = '$marche_code'")->fetch();

                        if ($req_groupe_marche == null) {
                            array_push($warnings, "L'abonnement de l'exploitant " . $row["nom_deb"] . " au marché $marche_code n'est pas inséré pour la raison suivante :<br>Ce marché n'existe pas dans le fichier des marchés");
                        } else {
                            $groupe_marche = $req_groupe_marche["groupe"];
                            // Continuer ssi les groupes du marché correspondent
                            if (isset($row["groupe$num_groupe"]) && $row["groupe$num_groupe"] === $groupe_marche) {
                                if (isset($row["abo$num_abo"])) {
                                    $prefixes_days = ["l", "ma", "me", "je", "v", "s", "d"];
                                    $type_abo = $row["abo$num_abo"];
                                    
                                    // Si l'exploitant est abonné à ce marché, regarder les jours où il y est abonné, et insérer un abonnement en conséquence
                                    if ($type_abo !== "0") {
                                        $sma_titulaire = 0;
                                        $sma_abonne = 0;

                                        switch ($type_abo) {
                                            case "1":
                                                $sma_abonne = 1;
                                                break;
                                            case "2":
                                                $sma_titulaire = 1;
                                                break;
                                            case "3":
                                                // TODO Voir à l'usage, pour l'instant 0 aux deux sma
                                                break;
                                            default:
                                                array_push($warnings, "L'exploitant " . $row["nom_deb"] . " a un type d'abonnement au marché $marche_code qui est inconnu, il faut trouver une équivalence dibtic - GEODP v1 pour le type $type_abo");
                                                break;
                                        }

                                        // Pour chaque colonne de jour 'l`i`/ma`i`/...', regarder si la cellule contient un 1 (abonnement) ou un 0 (non abonnement) au marché de la colonne 'm`i`'
                                        // Récupérer le MAR_REF du marché correspondant et insérer l'abonnement pour ce marché (un abo pour un marché, un marché étant pour un code et jour)
                                        foreach ($prefixes_days as $prefix_day) {
                                            if ($row["$prefix_day$num_day"] === "1") {
                                                $day = $usual_days[array_search($prefix_day, $prefixes_days)];

                                                $req_mar_ref = $dest_conn->query(build_query("SELECT MAR_REF FROM $dest_marche", "MAR_CODE = '$marche_code' AND MAR_JOUR = '$day' OR MAR_CODE = '$marche_code' AND MAR_JOUR IS NULL", "", ""))->fetch();

                                                if ($req_mar_ref == null) {
                                                    array_push($warnings, "L'abonnement de l'exploitant " . $row["nom_deb"] . " au marché $marche_code ouvert le $day n'est pas inséré pour la raison suivante :<br>Le marché $marche_code n'ouvre pas le $day d'après la table $dest_marche");
                                                } else {
                                                    $mar_ref = $req_mar_ref["MAR_REF"];

                                                    $dest_abonnement_values = [$last_exp_ref, $mar_ref, $aco_ref, $sma_titulaire, $sma_abonne, "'$dest_dcreat'", "'$dest_ucreat'"];

                                                    $insert_into_query = "INSERT INTO $dest_abonnement (" . implode(", ", $dest_abonnement_cols) . ") VALUES (" . implode(", ", $dest_abonnement_values) . ")";
                                                    execute_query($insert_into_query, $nb_inserted, $nb_to_insert);
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    array_push($warnings, "L'abonnement de l'exploitant " . $row["nom_deb"] . " au marché $marche_code n'est pas inséré pour la raison suivante :<br>La colonne 'abo$num_abo' n'existe pas dans le fichier des exploitants");
                                }
                            } else {
                                $warning = "L'abonnement de l'exploitant " . $row["nom_deb"] . " au marché $marche_code n'est pas inséré pour la raison suivante :<br>";
                                if (!isset($row["groupe$num_groupe"])) $warning .= "La colonne 'groupe$num_groupe' n'existe pas alors que la colonne '$src_exploitant_marche_col' existe dans le fichier des exploitants";
                                if (isset($row["groupe$num_groupe"]) && $row["groupe$num_groupe"] !== $groupe_marche) $warning .= "Pour le marché $marche_code, le groupe de l'exploitant (" . $row["groupe$num_groupe"] . ") ne correspond pas au groupe du marché ($groupe_marche) dans le fichier de l'exploitant";
                                array_push($warnings, $warning);
                            }
                        } // Fin "si le marché n'existe pas"
                    }
                }
            } // Fin "pour chaque exploitant de la table source"
            if ($display_dest_requests) echo "</div>";

            summarize_queries($nb_inserted, $nb_to_insert, $nb_errors, $warnings, $nb_warnings);

            // Pièces justificatives / Pièces justificatives Langue / Pièces justificatives Valeur

            echo "<h2 id=\"datas_pieces\">Pièces justificatives / Pièces justificatives Langue / Pièces justificatives Valeur</h2>";
            fwrite($output_file, "\n-- Pièces justificatives / Pièces justificatives Langue / Pièces justificatives Valeur\n\n");

            $nb_to_insert = 0;
            $nb_inserted = 0;

            $req_last_piece_ref = $dest_conn->query(build_query("SELECT PROP_REF FROM $dest_piece", "", "PROP_REF DESC", "1"))->fetch();
            $last_piece_ref = ($req_last_piece_ref == null) ? 0 : $req_last_piece_ref["PROP_REF"];

            if ($display_dest_requests) echo "<div class=\"pre\">";
            foreach ($src_conn->query("SELECT * FROM $src_exploitant") as $row) {
                foreach ($src_exploitant_piece_cols as $src_exploitant_piece_col) { // pour chaque colonne de pièce justificative possible, récupère la date d'échéance
                    $piece_val = $row[$src_exploitant_piece_col];
                    if ($piece_val !== "") {
                        $matches = [];
                        preg_match('/[0-9]+/', $src_exploitant_piece_col, $matches);
                        $num_datech = ($matches[0] === "1") ? "" : $matches[0]; // pour les dates, la première colonne s'appelle juste 'datech'

                        $piece_nom = "";
                        switch ($src_exploitant_piece_col) {
                            case "n1":
                                $piece_nom = "Carte de commerçant";
                                break;
                            case "n2":
                                $piece_nom = "Inscription RC";
                                break;
                            case "n3":
                                $piece_nom = "Inscription RM";
                                break;
                            case "n4":
                                $piece_nom = "Auto Entrepreneur";
                                break;
                            case "n5":
                                $piece_nom = "RSI";
                                break;
                            case "n6":
                                $piece_nom = "Caisse de maladie";
                                break;
                            case "n7":
                                $piece_nom = "Assurance";
                                break;
                            case "n8":
                                $piece_nom = "Protection juridique";
                                break;
                            case "n9":
                                $piece_nom = "Mutuelle Sociale Agricole";
                                break;
                            case "n10":
                                $piece_nom = "Agrément sanitaire";
                                break;
                            case "n11":
                                $piece_nom = "Déclaration centre impôts";
                                break;
                            case "n12":
                                $piece_nom = "Inscription registre Broc";
                                break;
                            case "":
                                $piece_nom = "Pièce justificative de nature inconnue";
                        }

                        if (!isset($row["datech$num_datech"])) {
                            array_push($warnings, "La pièce justificative $piece_nom n'est pas insérée pour la raison suivante :<br>La colonne 'datech$num_datech' n'existe pas alors que la colonne '$src_exploitant_piece_col' existe dans le fichier des exploitants");
                        } else {
                            $last_piece_ref += 1;


                            // $dest_piece_lang_cols = ["PROP_REF", "LAN_REF", "PROP_NOM", "DCREAT", "UCREAT"];
                            // $dest_piece_valeur_cols = ["EXP_REF", "PROP_REF", "PROP_VALEUR", "PROP_DATE", "PROP_DATE_VALIDITE", "DCREAT", "UCREAT"];

                            // Pièces justificatives

                            /*$dest_piece_values = [$last_piece_ref, $last_act_ref, $last_uti_ref, "'$dest_dcreat'", "'$dest_ucreat'"];
                            
                            $insert_into_query = "INSERT INTO $dest_piece (" . implode(", ", $dest_piece_cols) . ") VALUES (" . implode(", ", $dest_piece_values) . ")";
                            execute_query($insert_into_query, $nb_inserted, $nb_to_insert);

                            // Pièces justificatives langue

                            $dest_piece_lang_values = [$last_piece_ref, 1, ];

                            $insert_into_query = "INSERT INTO $dest_piece_lang (" . implode(", ", $dest_piece_lang_cols) . ") VALUES (" . implode(", ", $dest_piece_lang_values) . ")";
                            execute_query($insert_into_query, $nb_inserted, $nb_to_insert);

                            // Pièces justificatives langue

                            $dest_piece_val_values = [];

                            $insert_into_query = "INSERT INTO $dest_piece_val (" . implode(", ", $dest_piece_valeur_cols) . ") VALUES (" . implode(", ", $dest_piece_val_values) . ")";
                            execute_query($insert_into_query, $nb_inserted, $nb_to_insert);*/
                        }
                    } // Fin "si $piece_val pas vide"
                }
            }
            if ($display_dest_requests) echo "</div>";

            summarize_queries($nb_inserted, $nb_to_insert, $nb_errors, [], $nb_warnings);

            /*N - Carte de commerçant
            N2 - Inscription RC 
            N3 - Inscription RM
            N4 - Auto Entrepreneur
            N5 - RSI
            N6 - Caisse de Maladie
            N7 - Assurance
            N8 - Protection Juridique
            N9 - Mutuelle Sociale Agricole
            N10 - Agrément Sanitaire
            N11 - Déclaration Centre impots
            N12 - Inscription Registre Broc*/

            echo "</section>";

            ?>

            <script>
                var dom_nb_errors = document.querySelector("#nb_errors");
                var nb_errors = parseInt(<?php echo $nb_errors; ?>);
                var nb_warnings = parseInt(<?php echo $nb_warnings; ?>);
                dom_nb_errors.innerHTML = (nb_errors === 0) ? (nb_warnings === 0) ? "<ok></ok>" : "<mok></mok>" : "<nok></nok>";
                dom_nb_errors.innerHTML += "<br><br>";
                dom_nb_errors.innerHTML += "Reprise terminée avec " + nb_errors + " erreurs et " + nb_warnings + " conflits";
            </script>

            <?php

        } // Fin "si $get analyze = 1"

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
    margin-top: 4vh;
    padding-top: 4vh;
    font-size: 24px;
    color: rgba(0, 0, 0, 0.8);
    text-align: center;
}

init h1 {
    margin-top: 0;
}

aside h1 {
    margin: 30px 0;
    padding: 0;
    color: rgba(255, 255, 255, 0.8);
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

/* Liens */

a {
    color: orange;
    text-decoration: none;
    transition: all .1s ease;
}

a:hover {
    color: #343131;
}

/* Init */

init {
    flex: 0.75;
    display: flex;
    margin: 0 auto;
    padding: 20px;
}

init table th, init table td {
    padding: 10px;
    min-width: 200px;
    border: 1px solid rgba(0, 0, 0, 0.4);
}

init table th {
    color: white;
    background: #343131;
    border-right-color: rgba(255, 255, 255, 0.4);
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
}

aside a:hover {
    color: rgba(255, 255, 255, 0.7);
    background: rgba(255, 255, 255, 0.13);
    text-decoration: none;
}

aside ol {
    display: flex;
    position: sticky;
    top: 20px;
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
    color: rgba(0, 0, 0, 0.5);
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
    padding-bottom: 3%;
}

section > * {
    flex: 1;
}

/* Summary */

summary {
    flex: auto;
    display: flex;
    height: 100vh;
    flex-direction: column;
    justify-content: center;
}

summary * {
    font-size: 26px;
}

summary > * {
    margin: 35px 0;
    padding: 0;
    text-align: center;
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

/* Formulaires */

form {
    display: flex;
    flex-direction: column;
}

form field {
    display: flex;
    flex-direction: row;
    line-height: 25px;
    margin-bottom: 15px;
}

form field > * {
    padding: 5px 10px;
}

form field  label {
    padding-left: 0;
}

form field input {
    text-align: right;
    border: 1px solid rgba(0, 0, 0, 0.4);
    border-radius: 3px;
    transition: all .1s linear;
}

form field input:focus {
    border-color: orange;
}

form field > * {
    flex: 1;
}

form field span {
    position: absolute;
    right: 50%;
    cursor: pointer;
}

/* Autres */

div.pre {
    max-height: 200px;
    padding: 10px;
    font-family: Consolas;
    color: rgba(255, 255, 255, 0.7);
    background: rgba(0, 0, 0, 0.8);
    overflow: auto;
}

a.button, input[type=submit] {
    display: inline-block;
    max-width: 50%;
    margin: auto;
    text-align: center;
    padding: 15px 20px;
    font-size: 104%;
}

field a.button {
    position: absolute;
}

input[type=submit] {
    text-decoration: none;
    color: rgba(255, 255, 255, 0.8);
    background: #343131;
    border: 0;
    border-radius: 5px;
    transition: all .1s linear;
    cursor: pointer;
}

input[type=submit]:hover {
    color: #343131;
    box-shadow: 0 0 0 30px orange inset;
}

input[type=submit][disabled] {
    background: #343131;
    cursor: not-allowed;
    opacity: 0.5;
}

input[type=submit][disabled]:hover {
    color: white;
    box-shadow: 0 0 0 transparent;
}

input[type=disabled] {
    background: transparent;
    border: none;
    color: inherit;
}

ok::before, nok::before, mok::before {
    color: white;
    margin-right: 10px;
    padding: 2px 6px;
    border-radius: 25px;
    opacity: 0.8;
}

ok::before {
    content: "✔";
    background: green;
}

nok::before {
    content: "✘";
    background: red;
}

mok::before {
    content: "✔";
    background: orange;
}

p.success, p.warning, p.danger {
    padding: 15px 20px;
    border-radius: 6px;
    color: rgba(0, 0, 0, 0.7);
    border: 1px solid rgba(0, 0, 0, 0.15);
    text-shadow: 0 0 1px rgba(0, 0, 0, 0.15);
}

p {
    color: rgba(0, 0, 0, 0.8);
}

p.success {
    background: rgb(200, 240, 200);
}

p.warning {
    background: rgb(255, 235, 200);
}

p.danger {
    background: rgb(240, 200, 200);
}

p + p {
    margin-top: 20px;
}

div.pre + p {
    margin-top: 20px;
}

div.pre + p.success {
    margin-top: 0;
    border-top-width: 0;
    border-top-left-radius: 0;
    border-top-right-radius: 0;
}

div.pre p {
    margin: -18px 0 0 0;
    padding: 2px;
    float: right;
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

function autocomplete_password(user_input) {
    document.querySelector("#password").value = user_input.value;
}

function show_password(show) {
    var password_input = document.querySelector("#password");
    if (show) {
        password_input.type = "text";
    } else {
        password_input.type = "password";
    }
}

</script>
