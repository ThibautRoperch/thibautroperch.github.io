<?php

// Génère des tables à partir des fichiers lus, extrait les informations voulues et exécute + donne les requêtes SQL
// Entrée : Fichiers Excel dibtic définis dans common.php et récupérés par le script index.php
// Sortie : Fichier SQL contenant les requêtes SQL à envoyer dans la base de données GEODP v1

/**
 * VERSION 1 - 1ère semaine
 * 
 * Marchés      Ils sont tous ajoutés en conservant leur code, identifiant unique dans dibtic.
 * Articles     Un article est inséré dans GEODP si et seulement si il est utilisé par au moins un exploitant,
 *              car le.s marché.s de référence de l'article sont obtenus en regardant les articles associés aux marchés des exploitants.
 * Activités    (commerciales) Elles sont ajoutées même si elles ne sont utiisées par aucun exploitant.
 *              Il peut exister dans le fichier des exploitants des activités non déclarées dans le fichier des activités.
 * Exploitants  Le fichier qui met en relation les abonnements aux marchés, les articles associés aux marchés,
 *              avec possibilité de conflits à contrôler (ex. : un exploitant abonné à un marché à un jour, or ledit marché n'ouvre pas ledit jour d'après le fichier des marchés).
 * 
 * Gestion de la multi utilisation du script
 * 
 */


// Ouvrir cette page avec le paramètre 'analyze=1' pour effectuer des tests en local, l'ouvrir normalement en situation réelle
$test_mode = (isset($_POST["login"]) && isset($_POST["login"]) !== "" && isset($_POST["password"])) ? false : true; // en test la destination est MySQL, en situatiçon réelle la destination est Oracle

$php_required_version = "7.1.9";

$client_name = (!$test_mode && isset($_POST["type"]) && $_POST["type"] !== "") ? $_POST["type"] : "[ MODE TEST ]";

date_default_timezone_set("Europe/Paris");
$timestamp = date("Y-m-d H:i:s", time());


/*************************
 *        FICHIERS       *
 *************************/

// Répertoire contenant les fichiers sources (dibtic)
$directory = "./dibtic_placier";

// Résumé du contenu des fichiers sources (dibtic)
$expected_content = ["marchés", "articles", "exploitants", "activités"];

// Noms possibles des fichiers sources (dibtic)
$keywords_files = ["marche/marché", "classe/article/tarif", "exploitant/assujet", "activite/activité"];

// Données extraites des fichiers sources (dibtic)
$extracted_files_content = [
    "Libellé du marché, jours des marchés",
    "Nom de l’article, unité, prix unitaire, TVA, marchés associés à l’article",
    "Code de l’exploitant, nom, prénom, raison sociale, adresse (rue/CP/ville), date de suppression s’il a été supprimé, numéro de téléphone et de portable, adresse mail, type d’activité et abonnements aux marchés associés à l’exploitant, pièces justificatives avec date d'échéance",
    "Type (nom) de l’activité"];

// Nom du fichier de sortie contenant les requêtes SQL à envoyer (GEODP v1)
$output_filename = "output.sql"; // Si un fichier avec le même nom existe déjà, il sera écrasé


/*************************
 *    TABLES SOURCES     *
 *************************/

$nb_content = []; // auto-computed from filenames and based on the same indexation as $expected_content and $keywords_files
$src_tables = []; // auto-computed from filenames and based on the same indexation as $expected_content and $keywords_files
$src_prefixe = "src_";

// Mettre à vrai pour afficher le contenu des fichiers sources lors de leur lecture
$display_source_files_contents = false; // true | false


/*************************
 * TABLES DE DESTINATION *
 *************************/

// Nom des tables de destination (GEODP v1)
$dest_utilisateur = "dest_utilisateur";
$dest_employe = "dest_employe";
$dest_employe_marche = "dest_employe_marche";
$dest_groupe_activite_lang = "dest_groupe_activite_langue";
$dest_activite_lang = "dest_activite_langue";
$dest_marche = "dest_marche";
$dest_marche_lang = "dest_marche_langue";
$dest_article = "dest_article";
$dest_article_lang = "dest_article_langue";
$dest_activitecomm = "dest_activitecommerciale";
$dest_activitecomm_lang = "dest_activitecommerciale_langue";
$dest_piece = "dest_societe_propriete";
$dest_piece_lang = "dest_societe_propriete_langue";
$dest_exploitant = "dest_exploitant";
$dest_abonnement = "dest_societe_marche";
$dest_piece_val = "dest_societe_propriete_valeur";
$dest_compteur = "dest_compteur";

if (!$test_mode) {
    $dest_utilisateur = "UTILISATEUR";
    $dest_employe = "EMPLOYE";
    $dest_employe_marche = "EMPLOYE_MARCHE";
    $dest_groupe_activite_lang = "GROUPE_ACTIVITE_LANGUE";
    $dest_activite_lang = "ACTIVITE_LANGUE";
    $dest_marche = "MARCHE";
    $dest_marche_lang = "MARCHE_LANGUE";
    $dest_article = "ARTICLE";
    $dest_article_lang = "ARTICLE_LANGUE";
    $dest_activitecomm = "ACTIVITECOMMERCIALE";
    $dest_activitecomm_lang = "ACTIVITECOMMERCIALE_LANGUE";
    $dest_piece = "SOCIETE_PROPRIETE";
    $dest_piece_lang = "SOCIETE_PROPRIETE_LANGUE";
    $dest_exploitant = "EXPLOITANT";
    $dest_abonnement = "SOCIETE_MARCHE";
    $dest_piece_val = "SOCIETE_PROPRIETE_VALEUR";
    $dest_compteur = "COMPTEUR";
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

// Protection contre les utilisations simultanées du script (ressources communes : fichiers, tables sources et tables de destination)
if (isset($_GET["shutdown"]) && $_GET["shutdown"] !== "") {
    $req_wip = $mysql_conn->exec("UPDATE reprise_placier SET etat = 3, date_fin = '$timestamp' WHERE id = " . $_GET["shutdown"]);
}
$req_wip = $mysql_conn->query("SELECT COUNT(*) FROM reprise_placier WHERE date_fin IS NULL")->fetch();
if ($req_wip[0] !== "0") {
    echo "Des reprises sont déjà en cours :";
    echo "<table>";
    echo "<tr><th>Nom de la reprise</th><th>Date de début</th><th>Etat</th><th></th></tr>";
    foreach ($mysql_conn->query("SELECT * FROM reprise_placier WHERE date_fin IS NULL") as $row) {
        $etat = "Etat inconnu";
        switch ($row["etat"]) {
            case "0":
                $etat = "Initialisation en cours";
                break;
            case "1":
                $etat = "Analyse en cours";
                break;
            case "2":
                $etat = "Terminée";
                break;
        }
        echo "<tr><td>" . $row["client"] . "</td><td>" . $row["date_debut"] . "</td><td>$etat</td><td><a href=\"?shutdown=" . $row["id"] . "\">Interrompre</a></td></tr>";
    }
    echo "</table>";
    return;
}


/*************************
 *    CONNEXION ORACLE   *
 *************************/

$oracle_host = "ares"; // zeus | ares
$oracle_port = "1521";
$oracle_service = "xe"; // orcl | xe
$oracle_login = $test_mode ? "geodpthibaut" : $_POST["login"];
$oracle_password = $test_mode ? "geodpthibaut" : $_POST["password"];

if (!$test_mode && isset($_GET["analyze"]) && $_GET["analyze"] === "1") {
    $oracle_conn = new PDO("oci:dbname=(DESCRIPTION = (ADDRESS_LIST = (ADDRESS = (PROTOCOL = TCP) (Host = $oracle_host) (Port = $oracle_port))) (CONNECT_DATA = (SERVICE_NAME = ".$oracle_service.")));charset=UTF8", $oracle_login, $oracle_password);
}


/*************************
 *    CHOIX CONNEXION    *
 *************************/

if (isset($_GET["analyze"]) && $_GET["analyze"] === "1") {
    $src_conn = $mysql_conn;
    $dest_conn = $test_mode ? $mysql_conn : $oracle_conn;
}


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

function ok_nok($bool) {
    return $bool ? "<ok></ok>" : "<nok></nok>";
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
    } else if ($string === NULL) {
        return "NULL";
    }else {
        return "'".addslashes($string)."'";
    }
}

function string_to_date($string, $invert_day_month) {
    global $test_mode;

    $matches = [];
    preg_match('/^([0-9]+)\/([0-9]+)\/([0-9]+)$/', $string, $matches); // array(4) { [0]=> string(9) "6/12/2018" [1]=> string(1) "6" [2]=> string(2) "12" [3]=> string(4) "2018" }

    if (strlen($string) < 8 || count($matches) < 4) {
        return NULL;
    }

    $day = (strlen($matches[1]) < 2) ? "0".$matches[1] : $matches[1];
    $month = (strlen($matches[2]) < 2) ? "0".$matches[2] : $matches[2];
    $year = substr($matches[3], -2);

    if ($invert_day_month) {
        $tmp = $day;
        $day = $month;
        $month = $tmp; 
    }

    return $test_mode ? "$year/$month/$day" : "$day/$month/$year";
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
                echo "<p class=\"warning\">0 lignes affectées par la requête</p>";
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

    $success_s = ($nb_inserted == 0 || $nb_inserted > 1) ? "s" : "";
    $exec_s = ($nb_to_insert == 0 || $nb_to_insert > 1) ? "s" : "";

    $class = ($nb_inserted == $nb_to_insert) ? "success" : "danger";
    echo "<p class=\"$class\">$nb_inserted requête$success_s réussie$success_s sur $nb_to_insert requête$exec_s exécutée$exec_s</p>";
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
                            echo "<tr><td>" . ucfirst($expected_content[$i]) . "</td><td>";
                            echo (isset($files_to_convert[$i])) ? "<ok></ok>" . $files_to_convert[$i] : "<nok></nok>Fichier manquant";
                            echo "</td><td>" . $extracted_files_content[$i];
                            echo "</td></tr>";
                        }
                    echo "</table>";

                    $button_disabled = (count($files_to_convert) != count($expected_content)) ? "disabled" : "";

                    echo "<p>Une table sera créée en local pour chaque fichier source. Lorsque la reprise est testée, des tables identiques aux tables GEODP sont utilisées en local et les paramètres du client ne sont pas pris en compte.</p>";

                echo "<h2>Paramètres du client (pour reprise sur serveur uniquement)</h2>";

                    echo "<field><label for=\"servor\">Serveur de connexion Oracle</label><input id=\"servor\" type=\"disabled\" value=\"$oracle_host:$oracle_port/$oracle_service\" disabled /></field>";
                    echo "<field><label for=\"login\">Identifiant de connexion Oracle</label><input id=\"login\" name=\"login\" onchange=\"autocomplete_password(this)\" type=\"text\" placeholder=\"geodpville\" required /></field>";
                    echo "<field><label for=\"password\">Mot de passe de connexion Oracle</label><input id=\"password\" name=\"password\" type=\"password\"/><span onmousedown=\"show_password(true)\" onmouseup=\"show_password(false) \">&#128065;</span></field>";
                    echo "<field><label for=\"type\">Type de client</label><input id=\"type\" name=\"type\" type=\"text\" placeholder=\"A définir\"/></field>";

                echo "<h2></h2>";

                    echo "<field>";
                        if ($button_disabled === "") echo "<a class=\"button\" href=\"?analyze=1\">Tester la reprise en local</a>";
                        echo "<input type=\"submit\" value=\"Créer un client GEODP à partir de ces fichiers\" $button_disabled />";
                    echo "</field>";
                
                echo "<h2>Autres paramètres</h2>";

                    echo "<field><label>Vider les tables de destination en amont</label><input type=\"disabled\" value=\"".yes_no($erase_destination_tables)."\" disabled /></field>";
                    echo "<field><label>Afficher les requêtes à exécuter</label><input type=\"disabled\" value=\"".yes_no($display_dest_requests)."\" disabled /></field>";
                    echo "<field><label>Exécuter les requêtes</label><input type=\"disabled\" value=\"".yes_no($exec_dest_requests)."\" disabled /></field>";
                    echo "<field><label>Fichier de sortie</label><input type=\"disabled\" value=\"$output_filename\" disabled /></field>";

                echo "<h2>Configuration de <a href=\"http://www.wampserver.com/#download-wrapper\">WAMP</a></h2>";

                    $version_ok = (phpversion() === $php_required_version) ? true : false;
                    echo "<field><label>Version de PHP</label><input type=\"disabled\" value=\"$php_required_version\" disabled />" . ok_nok($version_ok) . "</field>";
                    $version_ok = (phpversion('pdo_mysql') === phpversion()) ? true : false;
                    echo "<field><label>Extension MySQL via PDO</label><input type=\"disabled\" value=\"php_pdo_mysql\" disabled />" . ok_nok($version_ok) . "</field>";
                    $version_ok = (phpversion('pdo_oci') === phpversion()) ? true : false;
                    echo "<field><label>Extension OCI via PDO</label><input type=\"disabled\" value=\"php_pdo_oci\" disabled />" . ok_nok($version_ok) . "</field>";

                echo "<h2>Configuration de la BDD  (pour reprise en local uniquement)</h2>";

                    echo "<field><label>Nom de la base de données</label><input type=\"disabled\" value=\"$mysql_dbname\" disabled /></field>";
                    echo "<field><label>Contenu de la base de données (copie des tables GEODP v1 utilisées par la reprise)</label><input type=\"disabled\" value=\"reprise_dibtic.sql à importer\" disabled /></field>";

            echo "</form>";
            
            echo "</init>";
        }

        /*************************
         *        ANALYSE        *
         *************************/

        if (isset($_GET["analyze"]) && $_GET["analyze"] === "1") {

            $mysql_conn->exec("INSERT INTO reprise (client, etat) VALUES ('$client_name', 1)");
            $reprise_id = $mysql_conn->lastInsertId();
            
            //// Sommaire

            echo "<aside>";
            echo "<h1>Reprise dibtic vers GEODP v1</h1>";
            echo "<ol id=\"sommaire\">";
                echo "<h2>$client_name</h2>";
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
                            echo "<li><a href=\"#src_" . prune($source_files[$exp]) . "\">Fichier des $exp</a></li>";
                        }
                    echo "</ol>";
                echo "</li>";
                echo "<li><a href=\"#destination\">Insertion des données GEODP</a>";
                    echo "<ol>";
                        echo "<li><a href=\"#dest_utilisateur\">Utilisateur</a></li>";
                        echo "<li><a href=\"#dest_marches\">Marchés / Marchés Langue / Employé Marché</a></li>";
                        echo "<li><a href=\"#dest_articles\">Articles / Articles Langue</a></li>";
                        echo "<li><a href=\"#dest_activites_comm\">Activités commerciales / Activités commerciales Langue</a></li>";
                        echo "<li><a href=\"#dest_pieces\">Pièces justificatives / Pièces justificatives Langue</a></li>";
                        echo "<li><a href=\"#dest_exploitants\">Exploitants / Abonnements / Pièces justificatives Valeur</a></li>";
                        echo "<li><a href=\"#dest_compteurs\">Compteurs</a></li>";
                    echo "</ol>";
                echo "</li>";
            echo "<li><a href=\"from_dibtic_to_geodpv1.php\">Retour à l'accueil</a></li>";
            echo "</ol>";
            echo "</aside>";

            echo "<section>";

            echo "<summary id=\"summary\">";
                echo "<h1>$client_name</h1>";
                echo "<p id=\"nb_errors\"></p>";
                echo $test_mode ? "<div><a href=\"from_dibtic_to_geodpv1.php\">Retour à l'accueil</a></div>" : "<div><a target=\"_blank\" href=\"//ares/geodp.".substr($oracle_login, strlen("geodp"))."\">ares/geodp.".substr($oracle_login, strlen("geodp"))."</a></div>";
            echo "</summary>";

            $nb_errors = 0;
            $nb_warnings = 0;
            
            if ($erase_destination_tables) {
                $dest_conn->exec("DELETE FROM $dest_piece_val");
                $dest_conn->exec("DELETE FROM $dest_abonnement");
                $dest_conn->exec("DELETE FROM $dest_exploitant");

                $dest_conn->exec("DELETE FROM $dest_piece_lang");
                $dest_conn->exec("DELETE FROM $dest_piece");

                $dest_conn->exec("DELETE FROM $dest_activitecomm_lang");
                $dest_conn->exec("DELETE FROM $dest_activitecomm");

                $dest_conn->exec("DELETE FROM $dest_article_lang");
                $dest_conn->exec("DELETE FROM $dest_article");

                $dest_conn->exec("DELETE FROM $dest_employe_marche WHERE MAR_REF IS NOT NULL");

                $dest_conn->exec("DELETE FROM $dest_marche_lang");
                $dest_conn->exec("DELETE FROM $dest_marche");
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
                echo "<field><label>Fichier de sortie</label><input type=\"disabled\" value=\"$output_filename\" disabled /></field>";
            echo "</form>";

            //// Chargement des fichiers sources dibtic
            
            echo "<h1 id=\"source\">Chargement des fichiers sources dibtic</h1>";
            
            // Pour chaque fichier source (pour chaque fichier attendu, il y a le chemin du fichier en $_GET), lecture + table + insertions
            
            foreach ($expected_content as $exp) {
                $file_name = $source_files[$exp];
                $table_name = $src_prefixe . prune($file_name);

                echo "<h2 id=\"src_" . prune($file_name) . "\">Fichier des $exp<span><tt>$file_name</tt> vers <tt>$table_name</tt></span></h2>";

                // Lecture du fichier (http://coursesweb.net/php-mysql/phpspreadsheet-read-write-excel-libreoffice-files)
//
                require 'spreadsheet/vendor/autoload.php';

                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load("$directory/$file_name");

                $xls_data = $spreadsheet->getActiveSheet()->toArray(null, false, true, true);
                // var_dump($xls_data);

                if ($display_source_files_contents) {
                    $html_tb ='<table><tr><th>'. implode('</th><th>', $xls_data[1]) .'</th></tr>';
                    for ($i = 2; $i <= count($xls_data); $i++) {
                        $html_tb .='<tr><td>'. implode('</td><td>', $xls_data[$i]) .'</td></tr>';
                    }
                    $html_tb .='</table>';
                    echo $html_tb;
                }

                if ($exp === "exploitants") {
                    $mysql_conn->exec("UPDATE reprise_placier SET token = '" . md5(implode("", $xls_data[2])) . "' WHERE id = $reprise_id");
                }
//
                // Création de la table correspondant au fichier

                $src_tables[$exp] = $table_name;
//
                $src_conn->exec("DROP TABLE IF EXISTS $table_name");

                $create_table_query = "";
                foreach ($xls_data[1] as $col) {
                    if ($create_table_query === "") {
                        $create_table_query .= "`$col` VARCHAR(250)"; /* PRIMARY KEY à ne pas mettre */
                    } else {
                        $create_table_query .= ", `$col` VARCHAR(250)";
                    }
                }
                $create_table_query = "CREATE TABLE $table_name ($create_table_query)";
                $src_conn->exec($create_table_query);

                // Remplissage de la table créée avec les données du fichier

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
                    $insert_into_query = "INSERT INTO $table_name VALUES ($insert_into_query)";

                    // echo "$insert_into_query<br>";
                    $req_res = $src_conn->exec($insert_into_query);
                    if ($req_res === false) {
                        echo "<p class=\"danger\">".$dest_conn->errorInfo()[2]."</p>";
                    } else {
                        if ($req_res === 0) {
                            echo "<p class=\"warning\">0 lignes affectées</p>";
                        }
                        ++$nb_inserted;
                    }
                }
                // echo "</div>";

                $nb_content[$exp] = $nb_inserted;

                // Contrôle du nombre d'insertions faites par rapport au nombre de données du fichier

                summarize_queries($nb_inserted, count($xls_data) - 1, $nb_errors, [], $nb_warnings);

                $reprise_col = str_replace("é", "e", $exp);
                $mysql_conn->exec("UPDATE reprise_placier SET $reprise_col = $nb_inserted WHERE id = $reprise_id");
//
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

            //// Insertion des données GEODP

            echo "<h1 id=\"destination\">Insertion des données GEODP</h1>";
            fwrite($output_file, "\n---------------------------------------------\n-- Insertion des données GEODP\n--\n\n");
            
            $dest_marche_cols = ["MAR_REF", "UTI_REF", "ACT_REF", "MAR_JOUR", "MAR_CPTFACTURE", "MAR_VISIBLE", "MAR_CODE", "DCREAT", "UCREAT", "MAR_VALIDE"];
            $dest_marche_lang_cols = ["MAR_REF", "LAN_REF", "MAR_NOM", "DCREAT", "UCREAT"];
            $dest_employe_marche_cols = ["EMA_REF", "EMP_REF", "MAR_REF", "DCREAT", "UCREAT", "ACT_REF"];

            $dest_article_cols = ["ART_REF", "MAR_REF", "UTI_REF", "ART_CODE", "ART_PRIX_TTC", "ART_PRIX_HT", "ART_TAUX_TVA", "ART_ABO_PRIX_TTC", "ART_ABO_PRIX_HT", "ART_ABO_TAUX_TVA", "ART_COULEUR", "ART_ORDRE", "ART_VALIDE_DEPUIS", "ART_VALIDE_JUSQUA", "ART_VISIBLE", "DCREAT", "UCREAT"];
            $dest_article_lang_cols = ["ART_REF", "LAN_REF", "ART_NOM", "ART_UNITE", "DCREAT", "UCREAT"];
            
            $dest_activitecomm_cols = ["ACO_REF", "UTI_REF", "ACO_COULEUR", "DCREAT", "UCREAT"];
            $dest_activitecomm_lang_cols = ["ACO_REF", "LAN_REF", "ACO_NOM", "DCREAT", "UCREAT"];
            
            $dest_exploitant_cols = ["EXP_REF", "EXP_CODE", "UTI_REF", "GRA_REF", "LAN_REF", "ACO_REF", "EXP_NOM_PERS_PHYSIQUE", "EXP_PRENOM_PERS_PHYSIQUE", "EXP_RAISON_SOCIALE", "EXP_NOM", "EXP_VISIBLE", "EXP_VALIDE", "EXP_NRUE", "EXP_ADRESSE", "EXP_CP", "EXP_VILLE", "EXP_TELEPHONE", "EXP_PORTABLE", "EXP_FAX", "EXP_EMAIL", "DCREAT", "UCREAT"];
            
            $dest_abonnement_cols = ["EXP_REF", "MAR_REF", "ACO_REF", "SMA_TITULAIRE", "SMA_ABONNE", "DCREAT", "UCREAT"];

            $dest_piece_cols = ["PROP_REF", "ACT_REF", "UTI_REF", "DCREAT", "UCREAT"];
            $dest_piece_lang_cols = ["PROP_REF", "LAN_REF", "PROP_NOM", "DCREAT", "UCREAT"];
            $dest_piece_valeur_cols = ["EXP_REF", "PROP_REF", "PROP_VALEUR", "PROP_DATE", "PROP_DATE_VALIDITE", "DCREAT", "UCREAT"];

            $req_uti_ref = $dest_conn->query(build_query("SELECT UTI_REF FROM $dest_utilisateur", "", "UTI_REF DESC", "1"))->fetch();
            if ($req_uti_ref == null) {
                echo "<p class=\"danger\">Impossible de continuer car il n'y a aucun utilisateur dans la table $dest_utilisateur</p>";
                return;
            }
            $uti_ref = $req_uti_ref["UTI_REF"];

            $req_emp_ref = $dest_conn->query(build_query("SELECT EMP_REF FROM $dest_employe", "", "EMP_REF DESC", "1"))->fetch();
            if ($req_emp_ref == null) {
                echo "<p class=\"danger\">Impossible de continuer car il n'y a aucun employé dans la table $dest_employe</p>";
                return;
            }
            $emp_ref = $req_emp_ref["EMP_REF"];

            $req_gra_ref_lang = $dest_conn->query(build_query("SELECT GRA_REF FROM $dest_groupe_activite_lang", "GRA_NOM LIKE '_arch_s _unicipaux'", "GRA_REF DESC", "1"))->fetch();
            if ($req_gra_ref_lang == null) {
                echo "<p class=\"danger\">Impossible de continuer car le groupe d'activités 'Marchés municipaux' n'est pas présent dans la table $dest_groupe_activite_lang</p>";
                return;
            }
            $gra_ref = $req_gra_ref_lang["GRA_REF"];

            $req_act_ref_lang = $dest_conn->query(build_query("SELECT ACT_REF FROM $dest_activite_lang", "ACT_NOM LIKE '_arch_s _unicipaux'", "ACT_REF DESC", "1"))->fetch();
            if ($req_act_ref_lang == null) {
                echo "<p class=\"danger\">Impossible de continuer car l'activité 'Marchés municipaux' n'est pas présente dans la table $dest_activite_lang</p>";
                return;
            }
            $act_ref = $req_act_ref_lang["ACT_REF"];

            // Utilisateur

            echo "<h2 id=\"dest_utilisateur\">Utilisateur<span><tt>$dest_utilisateur</tt></span></h2>";
            fwrite($output_file, "\n-- Utilisateur\n\n");

            $nb_to_update = 0;
            $nb_updated = 0;

            if ($display_dest_requests) echo "<div class=\"pre\">";
            $update_query = "UPDATE $dest_utilisateur SET UTI_NOM = '$client_name' WHERE UTI_REF = $uti_ref";
            execute_query($update_query, $nb_updated, $nb_to_update);
            if ($display_dest_requests) echo "<div>";

            // Marchés / Marchés Langue / Employé Marché

            echo "<h2 id=\"dest_marches\">Marchés / Marchés Langue / Employé Marché<span><tt>$dest_marche</tt> / <tt>$dest_marche_lang</tt> / <tt>$dest_employe_marche</tt></span></h2>";
            fwrite($output_file, "\n-- Marchés / Marchés Langue / Employé Marché\n\n");

            $nb_to_insert = 0;
            $nb_inserted = 0;
            $warnings = [];

            $req_last_mar_ref = $dest_conn->query(build_query("SELECT MAR_REF FROM $dest_marche", "", "MAR_REF DESC", "1"))->fetch();
            $last_mar_ref = ($req_last_mar_ref == null) ? 0 : $req_last_mar_ref["MAR_REF"];

            $req_last_ema_ref = $dest_conn->query(build_query("SELECT EMA_REF FROM $dest_employe_marche", "", "EMA_REF DESC", "1"))->fetch();
            $last_ema_ref = ($req_last_ema_ref == null) ? 0 : $req_last_ema_ref["EMA_REF"];

            if ($display_dest_requests) echo "<div class=\"pre\">";
            foreach ($src_conn->query("SELECT * FROM $src_marche") as $row) {
                $verif_query = $dest_conn->query("SELECT COUNT(*) FROM $dest_marche WHERE MAR_CODE = '".$row["code"]."'")->fetch();
                if ($verif_query[0] !== "0") {
                    array_push($warnings, "Le marché " . $row["libelle"] . " n'est pas inséré pour la raison suivante :<br>Un marché portant le code " . $row["code"] . " est déjà présent dans la table $dest_marche");
                } else {
                    // Créer un marché pour chaque jour de la semaine où le marché est ouvert (il en résultera plusieurs lignes correspondant à un même marché mais associé à des jours différents)
                    // Si le marché est présent tous les jours, n'insérer qu'un seul jour et mettre à 'null' MAR_JOUR
                    // Si le marché est présent un seul jour, n'insérer qu'un seul jour et mettre à 'nom_du_jour' MAR_JOUR
                    $all_the_week = true;
                    $nb_jours = 0;
                    foreach ($usual_days as $mar_day) {
                        if ($row[$mar_day] === "0") {
                            $all_the_week = $all_the_week && false;
                        } else {
                            ++$nb_jours;
                        }
                    }

                    foreach ($usual_days as $mar_day) {
                        if ($row[$mar_day] === "1") {
                            $last_mar_ref += 1;
                            $last_ema_ref += 1;

                            $dest_marche_values = [];

                            foreach ($dest_marche_cols as $col) {
                                switch ($col) {
                                    case "MAR_REF":
                                        array_push($dest_marche_values, "$last_mar_ref");
                                        break;
                                    case "UTI_REF":
                                        array_push($dest_marche_values, "$uti_ref");
                                        break;
                                    case "ACT_REF":
                                        array_push($dest_marche_values, "$act_ref");
                                        break;
                                    case "MAR_JOUR":
                                        if ($all_the_week) array_push($dest_marche_values, "NULL");
                                        else array_push($dest_marche_values, "'$mar_day'");
                                        break;
                                    case "MAR_CPTFACTURE":
                                        array_push($dest_marche_values, "1");
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

                            $completement_mar_nom = ($all_the_week || $nb_jours === 1) ? "" : " ".strtoupper($mar_day);
                            $dest_marche_lang_values = [$last_mar_ref, 1, "'".addslashes($row["libelle"])."$completement_mar_nom'", "'$dest_dcreat'", "'$dest_ucreat'"];

                            $insert_into_query = "INSERT INTO $dest_marche_lang (" . implode(", ", $dest_marche_lang_cols) . ") VALUES (" . implode(", ", $dest_marche_lang_values) . ")";
                            execute_query($insert_into_query, $nb_inserted, $nb_to_insert);

                            // Employé Marché

                            $dest_employe_marche_values = [$last_ema_ref, $emp_ref, $last_mar_ref, "'$dest_dcreat'", "'$dest_ucreat'", $act_ref];

                            $insert_into_query = "INSERT INTO $dest_employe_marche (" . implode(", ", $dest_employe_marche_cols) . ") VALUES (" . implode(", ", $dest_employe_marche_values) . ")";
                            execute_query($insert_into_query, $nb_inserted, $nb_to_insert);
                        } // Fin "si $row[$mar_day] = 1"
                        if ($all_the_week) break; // Arrêter la boucle de parcours des jours si le marché est ouvert toute la semaine, car dans ce cas, une seule insertion est faite au lieu de 7
                    } // Fin "pour chaque jour de la semaine"
                } // Fin "si il n'existe pas déjà un marché avec ce code"
            }
            if ($display_dest_requests) echo "</div>";

            summarize_queries($nb_inserted, $nb_to_insert, $nb_errors, $warnings, $nb_warnings);

            // Articles / Articles Langue

            echo "<h2 id=\"dest_articles\">Articles / Articles Langue<span><tt>$dest_article</tt> / <tt>$dest_article_lang</tt></span></h2>";
            fwrite($output_file, "\n-- Articles / Articles Langue\n\n");

            $nb_to_insert = 0;
            $nb_inserted = 0;
            $warnings = [];
            
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
                            // Il y a potentiellement plusieurs marché qui ont le même code (un marché est ouvert x jours -> x marchés insérés, qui ont tous les même code de marché)
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

                if ($row["abo"] !== "" && $row["abo"] !== "X") {
                    array_push($warnings, "La valeur de la cellule 'abo' pour l'article " . $row["nom"] . " est non vide et différente de 'X'. L'article est quand même considéré comme un tarif d'abonnés.");
                }
                if (strlen($row["numc"]."-".$row["code"]) > 6) {
                    array_push($warnings, "Le code de l'article " . $row["nom"] . ", " . $row["numc"]."-".$row["code"] . ", est supérieur à 6 caractères");
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
                                array_push($dest_article_values, "$uti_ref");
                                break;
                            case "MAR_REF":
                                array_push($dest_article_values, "$mar_ref");
                                break;
                            case "ART_CODE":
                                array_push($dest_article_values, "'".$row["numc"]."-".$row["code"]."'");
                                break;
                            case "ART_PRIX_TTC":
                                if ($row["abo"] === "" && $row["tva"] === "") {
                                    array_push($dest_article_values, str_replace(' ', '', $row["prix_unit"]));
                                } else {
                                    array_push($dest_article_values, "0");
                                }
                                break;
                            case "ART_PRIX_HT":
                                if ($row["abo"] === "" && $row["tva"] !== "") {
                                    array_push($dest_article_values, str_replace(' ', '', $row["prix_unit"]));
                                } else {
                                    array_push($dest_article_values, "0");
                                }
                                break;
                            case "ART_TAUX_TVA":
                                if ($row["abo"] === "" && $row["tva"] !== "") {
                                    array_push($dest_article_values, str_replace(' ', '', $row["tva"]));
                                } else {
                                    array_push($dest_article_values, "0");
                                }
                                break;
                            case "ART_ABO_PRIX_TTC":
                                if ($row["abo"] !== "" && $row["tva"] === "") {
                                    array_push($dest_article_values, str_replace(' ', '', $row["prix_unit"]));
                                } else {
                                    array_push($dest_article_values, "0");
                                }
                                break;
                            case "ART_ABO_PRIX_HT":
                                if ($row["abo"] !== "" && $row["tva"] !== "") {
                                    array_push($dest_article_values, str_replace(' ', '', $row["prix_unit"]));
                                } else {
                                    array_push($dest_article_values, "0");
                                }
                                break;
                            case "ART_ABO_TAUX_TVA":
                                if ($row["abo"] !== "" && $row["tva"] !== "") {
                                    array_push($dest_article_values, str_replace(' ', '', $row["tva"]));
                                } else {
                                    array_push($dest_article_values, "0");
                                }
                                break;
                            case "ART_COULEUR":
                                array_push($dest_article_values, "'fff'");
                                break;
                            case "ART_ORDRE":
                                array_push($dest_article_values, "0");
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

            summarize_queries($nb_inserted, $nb_to_insert, $nb_errors, $warnings, $nb_warnings);

            // Activités

            echo "<h2 id=\"dest_activites_comm\">Activités commerciales / Activités commerciales Langue<span><tt>$dest_activitecomm</tt> / <tt>$dest_activitecomm_lang</tt></span></h2>";
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
                            array_push($dest_activitecomm_values, "$uti_ref");
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

            // Pièces justificatives / Pièces justificatives Langue / Pièces justificatives Valeur

            echo "<h2 id=\"dest_pieces\">Pièces justificatives / Pièces justificatives Langue<span><tt>$dest_piece</tt> / <tt>$dest_piece_lang</tt></span></h2>";
            fwrite($output_file, "\n-- Pièces justificatives / Pièces justificatives Langue\n\n");

            $nb_to_insert = 0;
            $nb_inserted = 0;
            $warnings = [];

            $req_last_piece_ref = $dest_conn->query(build_query("SELECT PROP_REF FROM $dest_piece", "", "PROP_REF DESC", "1"))->fetch();
            $last_piece_ref = ($req_last_piece_ref == null) ? 0 : $req_last_piece_ref["PROP_REF"];

            $assoc_PieceCol_PieceRef = [];

            if ($display_dest_requests) echo "<div class=\"pre\">";
            foreach ($src_exploitant_piece_cols as $src_exploitant_piece_col) {
                $last_piece_ref += 1;

                $assoc_PieceCol_PieceRef[$src_exploitant_piece_col] = $last_piece_ref;

                // Pièces justificatives

                $dest_piece_values = [$last_piece_ref, $act_ref, $uti_ref, "'$dest_dcreat'", "'$dest_ucreat'"];
                
                $insert_into_query = "INSERT INTO $dest_piece (" . implode(", ", $dest_piece_cols) . ") VALUES (" . implode(", ", $dest_piece_values) . ")";
                execute_query($insert_into_query, $nb_inserted, $nb_to_insert);
                
                // Pièces justificatives langue

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
                    default:
                        $piece_nom = "Pièce justificative de nature inconnue";
                        array_push($warnings, "Le type de pièce justificative $src_exploitant_piece_col est inconnu, il faut trouver une équivalence dibtic - GEODP v1 pour la colonne $src_exploitant_piece_col du fichier des exploitants " . $source_files["exploitants"]);
                        break;
                }
                
                $dest_piece_lang_values = [$last_piece_ref, 1, "'".addslashes($piece_nom)."'", "'$dest_dcreat'", "'$dest_ucreat'"];

                $insert_into_query = "INSERT INTO $dest_piece_lang (" . implode(", ", $dest_piece_lang_cols) . ") VALUES (" . implode(", ", $dest_piece_lang_values) . ")";
                execute_query($insert_into_query, $nb_inserted, $nb_to_insert);
            }
            if ($display_dest_requests) echo "</div>";

            summarize_queries($nb_inserted, $nb_to_insert, $nb_errors, $warnings, $nb_warnings);

            // Exploitants / Abonnements / Pièces justificatives

            echo "<h2 id=\"dest_exploitants\">Exploitants / Abonnements / Pièces justificatives Valeur<span><tt>$dest_exploitant</tt> / <tt>$dest_abonnement</tt> / <tt>$dest_piece_val</tt></span></h2>";
            fwrite($output_file, "\n-- Exploitants / Abonnements / Pièces justificatives Valeur\n\n");

            $nb_to_insert = 0;
            $nb_inserted = 0;
            $warnings = [];

            $req_last_exp_ref = $dest_conn->query(build_query("SELECT EXP_REF FROM $dest_exploitant", "", "EXP_REF DESC", "1"))->fetch();
            echo build_query("SELECT EXP_REF FROM $dest_exploitant", "", "EXP_REF DESC", "1");
            $last_exp_ref = ($req_last_exp_ref == null) ? 0 : $req_last_exp_ref["EXP_REF"];

            if ($display_dest_requests) echo "<div class=\"pre\">";
            foreach ($src_conn->query("SELECT * FROM $src_exploitant WHERE nom_deb != '' AND (date_suppr = '' OR date_suppr = '  -   -')") as $row) {
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
                            array_push($dest_exploitant_values, "$uti_ref");
                            break;
                        case "GRA_REF":
                            array_push($dest_exploitant_values, "$gra_ref");
                            break;
                        case "LAN_REF":
                            array_push($dest_exploitant_values, "1");
                            break;
                        case "ACO_REF":
                            $aco_ref = "NULL";
                            if ($row["type"] !== "") {
                                $req_aco_ref = $dest_conn->query(build_query("SELECT ACO_REF FROM $dest_activitecomm_lang", "ACO_NOM = '".addslashes(strtoupper($row["type"]))."'", "", ""))->fetch();
                                $aco_ref = ($req_aco_ref == null) ? NULL : $req_aco_ref["ACO_REF"];
                                if ($aco_ref === NULL) {
                                    array_push($warnings, "L'activité " . addslashes(strtoupper($row["type"])) . " de l'exploitant " . $row["nom_deb"] . " n'existe pas encore dans la table $dest_activitecomm, elle y est donc insérée");

                                    if ($display_dest_requests) echo "-- L'activité " . addslashes(strtoupper($row["type"])) . " lue dans le fichier des exploitants est manquante dans le fichier des activités.<br>";
                                    fwrite($output_file, "-- L'activité " . addslashes(strtoupper($row["type"])) . " lue dans le fichier des exploitants est manquante dans le fichier des activités.\n");
                                    
                                    $req_last_aco_ref = $dest_conn->query(build_query("SELECT ACO_REF FROM $dest_activitecomm", "", "ACO_REF DESC", "1"))->fetch();
                                    $last_aco_ref = ($req_last_aco_ref == null) ? 0 : $req_last_aco_ref["ACO_REF"];
                                    ++$last_aco_ref;

                                    // Activité

                                    $dest_activitecomm_values = [$last_aco_ref, $uti_ref, "'fff'", "'$dest_dcreat'", "'$dest_ucreat'"];

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
                            array_push($warnings, "L'abonnement de l'exploitant " . $row["nom_deb"] . " au marché $marche_code n'est pas inséré pour la raison suivante :<br>Ce marché n'existe pas dans le fichier des marchés " . $source_files["marchés"]);
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
                                                array_push($warnings, "L'exploitant " . $row["nom_deb"] . " a un type d'abonnement inconnu au marché $marche_code, il faut trouver une équivalence dibtic - GEODP v1 pour le type $type_abo");
                                                break;
                                        }

                                        // Pour chaque colonne de jour 'l`i`/ma`i`/...', regarder si la cellule contient un 1 (abonnement) ou un 0 (non abonnement) au marché de la colonne 'm`i`'
                                        // Récupérer le MAR_REF du marché correspondant et insérer l'abonnement pour ce marché (un abo pour un marché, un marché étant pour un code et jour)
                                        foreach ($prefixes_days as $prefix_day) {
                                            if ($row["$prefix_day$num_day"] === "1") {
                                                $day = $usual_days[array_search($prefix_day, $prefixes_days)];

                                                $req_mar_ref = $dest_conn->query(build_query("SELECT MAR_REF FROM $dest_marche", "MAR_CODE = '$marche_code' AND MAR_JOUR = '$day' OR MAR_CODE = '$marche_code' AND MAR_JOUR IS NULL", "", ""))->fetch();

                                                if ($req_mar_ref == null) {
                                                    array_push($warnings, "L'abonnement de l'exploitant " . $row["nom_deb"] . " au marché $marche_code ouvert le $day n'est pas inséré pour la raison suivante :<br>Le marché $marche_code n'ouvre pas le $day d'après le fichier des marchés " . $source_files["exploitants"]);
                                                } else {
                                                    $mar_ref = $req_mar_ref["MAR_REF"];

                                                    $dest_abonnement_values = [$last_exp_ref, $mar_ref, $aco_ref, $sma_titulaire, $sma_abonne, "'$dest_dcreat'", "'$dest_ucreat'"];

                                                    $verif_query = $dest_conn->query("SELECT COUNT(*) FROM $dest_abonnement WHERE EXP_REF = $last_exp_ref AND MAR_REF = $mar_ref")->fetch();
                                                    if ($verif_query[0] !== "0") {
                                                        array_push($warnings, "L'abonnement de l'exploitant " . $row["nom_deb"] . " au marché $marche_code ouvert le $day n'est pas inséré pour la raison suivante :<br>Cet abonnement est déjà présent dans la table $dest_abonnement");
                                                    } else {
                                                        $insert_into_query = "INSERT INTO $dest_abonnement (" . implode(", ", $dest_abonnement_cols) . ") VALUES (" . implode(", ", $dest_abonnement_values) . ")";
                                                        execute_query($insert_into_query, $nb_inserted, $nb_to_insert);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    array_push($warnings, "L'abonnement de l'exploitant " . $row["nom_deb"] . " au marché $marche_code n'est pas inséré pour la raison suivante :<br>La colonne 'abo$num_abo' n'existe pas dans le fichier des exploitants " . $source_files["exploitants"]);
                                }
                            } else {
                                $warning = "L'abonnement de l'exploitant " . $row["nom_deb"] . " au marché $marche_code n'est pas inséré pour la raison suivante :<br>";
                                if (!isset($row["groupe$num_groupe"])) $warning .= "La colonne 'groupe$num_groupe' n'existe pas alors que la colonne '$src_exploitant_marche_col' existe dans le fichier des exploitants " . $source_files["exploitants"];
                                if (isset($row["groupe$num_groupe"]) && $row["groupe$num_groupe"] !== $groupe_marche) $warning .= "Pour le marché $marche_code, le groupe de l'exploitant (" . $row["groupe$num_groupe"] . ") ne correspond pas au groupe du marché ($groupe_marche) dans le fichier des exploitants " . $source_files["exploitants"];
                                array_push($warnings, $warning);
                            }
                        } // Fin "si le marché n'existe pas"
                    }
                } // Fin "abonnements de l'exploitant"

                // Pièces justificatives
                
                foreach ($src_exploitant_piece_cols as $src_exploitant_piece_col) { // pour chaque colonne de pièce justificative possible, récupère la date d'échéance
                    $piece_ref = $assoc_PieceCol_PieceRef[$src_exploitant_piece_col];
                    $piece_val = $row[$src_exploitant_piece_col];

                    if ($piece_val !== "") {
                        // Si la valeur a le format d'une date, alors PROP_VALEUR est vide et PROP_DATE contient la valeur
                        $matches = [];
                        preg_match('/^[0-9]+\/[0-9]+\/[0-9]+$/', $piece_val, $matches);
                        $piece_date = isset($matches[0]) ? string_to_date($piece_val, false) : "";
                        if ($piece_date === NULL) array_push($warnings, "La date $piece_val de la pièce justificative $src_exploitant_piece_col de l'exploitant " . $row["nom_deb"] . " est mal formée (la pièce est quand même insérée)");
                        $piece_val = ($piece_date === "") ? $piece_val : "";

                        $matches = [];
                        preg_match('/[0-9]+/', $src_exploitant_piece_col, $matches);
                        $num_datech = ($matches[0] === "1") ? "" : $matches[0]; // pour les dates d'échéance, la première colonne s'appelle juste 'datech' et le jour est inversé avec le mois
                        $matches = [];
                        preg_match('/^[0-9]+\/[0-9]+\/[0-9]+$/', $row["datech$num_datech"], $matches);
                        $piece_date_validite = isset($matches[0]) ? string_to_date($row["datech$num_datech"], true) : "";
                        if ($piece_date_validite === NULL) array_push($warnings, "La date d'échéance " . $row["datech$num_datech"] . " de la pièce justificative $src_exploitant_piece_col (datech$num_datech) de l'exploitant " . $row["nom_deb"] . " est mal formée (la pièce est quand même insérée)");

                        if (!isset($row["datech$num_datech"])) {
                            array_push($warnings, "La pièce justificative $piece_nom n'est pas insérée pour la raison suivante :<br>La colonne 'datech$num_datech' n'existe pas alors que la colonne '$src_exploitant_piece_col' existe dans le fichier des exploitants " . $source_files["exploitants"]);
                        } else {
                            // Pièces justificatives valeur

                            $dest_piece_val_values = [$last_exp_ref, $piece_ref, addslashes_nullify($piece_val), addslashes_nullify($piece_date), addslashes_nullify($piece_date_validite), "'$dest_dcreat'", "'$dest_ucreat'"];
                            
                            $insert_into_query = "INSERT INTO $dest_piece_val (" . implode(", ", $dest_piece_valeur_cols) . ") VALUES (" . implode(", ", $dest_piece_val_values) . ")";
                            execute_query($insert_into_query, $nb_inserted, $nb_to_insert);
                        }
                    } // Fin "si $piece_val pas vide"
                } // Fin "pièces justificatives de l'exploitant"
            } // Fin "pour chaque exploitant de la table source"
            if ($display_dest_requests) echo "</div>";

            summarize_queries($nb_inserted, $nb_to_insert, $nb_errors, $warnings, $nb_warnings);

            // Compteurs

            echo "<h2 id=\"dest_compteurs\">Compteurs<span><tt>$dest_compteur</tt></span></h2>";
            fwrite($output_file, "\n-- Compteurs\n\n");

            $nb_to_update = 0;
            $nb_updated = 0;

            if ($display_dest_requests) echo "<div class=\"pre\">";
            $update_query = "UPDATE $dest_compteur SET CPT_VAL = (SELECT MAX(MAR_REF) + 1 FROM $dest_marche) WHERE CPT_TABLE = 'actreffacture'";
            execute_query($update_query, $nb_updated, $nb_to_update);
            if ($display_dest_requests) echo "</div>";

            summarize_queries($nb_updated, $nb_to_update, $nb_errors, [], $nb_warnings);


            $mysql_conn->exec("UPDATE reprise_placier SET date_fin = '$timestamp', conflits = $nb_warnings, erreurs = $nb_errors, etat = 2 WHERE id = $reprise_id");

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
    padding-top: 2vh;
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

aside h2 {
    margin: 30px 25px;
    margin-top: 0;
    color: white;
    text-align: center;
    border-color: rgba(255, 255, 255, 0.5);
    opacity: 0.7;
}

h2 a {
    font-size: inherit;
}

h2 span {
    float: right;
}

/*h3 {
    margin: 20px 0;
    padding-left: 10px;
    font-size: 110%;
    border-left: 10px solid orange;
}*/

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
    padding: 30px 20px;
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
    top: -1px;
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
    right: 50.5%;
    cursor: pointer;
}

form field ok, form field nok {
    flex: 0;
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

p + p, div.pre + p, table + p {
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

tt {
    margin: 0 3px;
    font-family: Courier;
    font-size: 95%;
    opacity: 0.8;
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
