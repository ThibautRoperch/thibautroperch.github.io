<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Reprise ODP</title>
    <link rel="stylesheet" type="text/css" href="css/style.css">
</head>

<?php

$script_file_name = "odp.php";
$title = "Reprise dibtic vers GEODP v1<br>ODP";

// Génère des tables à partir des fichiers lus, extrait les informations voulues et exécute + donne les requêtes SQL
// Entrée : Fichiers Excel dibtic définis dans common.php et récupérés par le script index.php
// Sortie : Fichier SQL contenant les requêtes SQL à envoyer dans la base de données GEODP v1

/**
 * VERSION ODP - 3ème semaine
 * 
 * Instructions Fichier écrit à la main, non dibtic
 * Exploitants  
 * 
 */


// Ouvrir cette page avec le paramètre 'analyze=1' pour effectuer des tests en local, l'ouvrir normalement en situation réelle
$analyse_mode = (isset($_GET["analyze"]) && $_GET["analyze"] === "1");
$test_mode = ($analyse_mode && isset($_POST["login"]) && isset($_POST["login"]) !== "" && isset($_POST["password"])) ? false : true; // en test la destination est MySQL, en situatiçon réelle la destination est Oracle

$php_required_version = "7.1.9";

$client_name = (!$test_mode && isset($_POST["type"])) ? $_POST["type"] : "[ MODE TEST ]";

date_default_timezone_set("Europe/Paris");
$timestamp = date("Y-m-d H:i:s", time());


/*************************
 *       FICHIERS        *
 *************************/

// Répertoire contenant les fichiers sources (dibtic)
$directory_name = "./dibtic_odp";

// Résumé du contenu des fichiers sources (dibtic)
$expected_content = ["instructions"];

// Noms possibles des fichiers sources (dibtic)
$keywords_files = ["instruction/evenement", "exploitant/tier/assujet"];

// Données extraites des fichiers sources (dibtic)
$extracted_files_content = [
    "Instructions",
    ""
    ];

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
$dest_exploitant = "dest_exploitant";
$dest_domaine_lang = "dest_domaine_langue";
$dest_evenement = "dest_evenement";
$dest_evenement_lang = "dest_evenement_langue";
$dest_dossier = "dest_dossier";
$dest_dossier_etat = "dest_dossier_etat";
$dest_dossier_etat_lang = "dest_dossier_etat_langue";
$dest_instruction = "dest_dossier_instruction";
$dest_instruction_evenement = "dest_dossier_instruction_evenement";
$dest_dossier_document = "dest_dossier_document";
$dest_compteur = "dest_compteur";

if (!$test_mode) {
    $dest_exploitant = "EXPLOITANT";
    $dest_domaine_lang = "DOMAINE_LANGUE";
    $dest_evenement = "EVENEMENT";
    $dest_evenement_lang = "EVENEMENT_LANGUE";
    $dest_dossier = "DOSSIER";
    $dest_dossier_etat = "DOSSIER_ETAT";
    $dest_dossier_etat_lang = "DOSSIER_ETAT_LANGUE";
    $dest_instruction = "DOSSIER_INSTRUCTION";
    $dest_evenement = "DOSSIER_INSTRUCTION_EVENEMENT";
    $dest_dossier_document = "DOSSIER_DOCUMENT";
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


/*************************
 *    CONNEXION ORACLE   *
 *************************/

$oracle_host = "ares"; // zeus | ares
$oracle_port = "1521";
$oracle_service = "xe"; // orcl | xe
$oracle_login = $test_mode ? "geodpthibaut" : $_POST["login"];
$oracle_password = $test_mode ? "geodpthibaut" : $_POST["password"];

if (!$test_mode && $analyse_mode) {
    $oracle_conn = new PDO("oci:dbname=(DESCRIPTION = (ADDRESS_LIST = (ADDRESS = (PROTOCOL = TCP) (Host = $oracle_host) (Port = $oracle_port))) (CONNECT_DATA = (SERVICE_NAME = ".$oracle_service.")));charset=UTF8", $oracle_login, $oracle_password);
}


/*************************
 *    CHOIX CONNEXION    *
 *************************/

if ($analyse_mode) {
    $src_conn = $mysql_conn;
    $dest_conn = $test_mode ? $mysql_conn : $oracle_conn;
}


/*************************
 *  MULTI-UTILISATIONS   *
 *************************/

$reprise_table = "reprise_odp";

// Protection contre les utilisations simultanées du script (ressources communes : fichiers, tables sources et tables de destination)
if (isset($_GET["shutdown"]) && $_GET["shutdown"] !== "") {
    $req_wip = $mysql_conn->exec("UPDATE $reprise_table SET etat = 3, date_fin = '$timestamp' WHERE id = " . $_GET["shutdown"]);
}
$req_wip = $mysql_conn->query("SELECT COUNT(*) FROM $reprise_table WHERE date_fin IS NULL")->fetch();
if ($req_wip[0] !== "0") {
    echo "<init>";
    echo "<h1>$title</h1>";
    echo "<h2>Des reprises sont déjà en cours</h2>";
    echo "<table>";
    echo "<tr><th>Nom de la reprise</th><th>Date de début</th><th>Etat</th><th>Actions</th></tr>";
    foreach ($mysql_conn->query("SELECT * FROM $reprise_table WHERE date_fin IS NULL") as $row) {
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
        echo "<tr><td>" . $row["nom"] . "</td><td>" . $row["date_debut"] . "</td><td>$etat</td><td><a href=\"?shutdown=" . $row["id"] . "\">Interrompre</a></td></tr>";
    }
    echo "</table>";
    echo "</init>";
    return;
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

function reformate_date($string) {
    $matches = [];
    preg_match('/([0-9]+).([0-9]+).([0-9]+)/', $string, $matches); // array(4) { [0]=> string(9) "6/12/2018" [1]=> string(1) "6" [2]=> string(2) "12" [3]=> string(4) "2018" }
    if (count($matches) < 4) return NULL;

    // dd/mm/aa ou dd/mm/aaaa

    $day = (strlen($matches[1]) < 2) ? "0".$matches[1] : $matches[1];
    $month = (strlen($matches[2]) < 2) ? "0".$matches[2] : $matches[2];
    $year = substr($matches[3], -2);

    return "$day/$month/$year";
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

$detected_files = [];
$files_to_convert = [];
$source_files = []; // équivalent à $files_to_convert avec des indices presonnalisés

if (is_dir($directory_name)) {
    $directory = scandir($directory_name);

    foreach ($directory as $file) {
        if (preg_match("/.*\.xlsx$/i", $file)) array_push($detected_files, $file);
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
} else {
    mkdir($directory_name);
}


/*************************
 *    GESTION DOSSIER    *
 *************************/

if (isset($_FILES) && count($_FILES) > 0) {
    if (is_dir($directory_name)) {
        $directory = opendir($directory_name);
        echo "<alert>";
        echo "Le contenu du dossier $directory_name sera supprimé :";
        echo "<ul>";
        while (($file = readdir($directory)) !== false) {
            if (in_array($file, $detected_files)) {
                if (isset($_GET["confirm"]) && $_GET["confirm"] === "1") {
                    unlink("$directory_name/$file");
                } else {
                    echo "<li>$file</li>";
                }
            }
        }
        echo "</ul>";
        echo "</alert>";
        if (isset($_GET["confirm"]) && $_GET["confirm"] === "1") {
            var_dump($_FILES);
            foreach ($_FILES as $file) {
                copy($file["tmp_name"], "$directory_name/" . $file["name"]);
            }
        }
        closedir($directory);
    }
}

?>

<body <?php echo (!$analyse_mode) ? "class=\"droparea\"" : ""; ?>>
    
    <?php
    
        /*************************
         *        ACCUEIL        *
         *************************/
        
        if (!$analyse_mode) {

            echo "<init>";
            echo "<h1>$title</h1>";

            echo "<form action=\"$script_file_name?analyze=1\" method=\"POST\">";

                echo "<h2>Fichiers dibtic à reprendre</h2>";

                    echo "<p>Les fichiers sources sont contenus dans le dossier <tt>$directory_name</tt> (pour reprendre d'autres fichiers, les glisser-déposer ici ou changer directement le contenu du dossier) :";
                    echo "<table>";
                        echo "<tr><th>Contenu</th><th>Fichier correspondant</th><th>Informations transférables dans GEODP</th></tr>";
                        for ($i = 0; $i < count($expected_content); ++$i) {
                            echo "<tr><td>" . ucfirst($expected_content[$i]) . "</td><td>";
                            echo (isset($files_to_convert[$i])) ? "<ok></ok>$directory_name/" . $files_to_convert[$i] : "<nok></nok>Fichier manquant";
                            echo "</td><td>" . $extracted_files_content[$i];
                            echo "</td></tr>";
                        }
                    echo "</table>";

                    $button_disabled = (count($files_to_convert) != count($expected_content)) ? "disabled" : "";

                    if ($button_disabled === "") echo "<a class=\"button\" href=\"?analyze=1\" onclick=\"loading()\">Tester la reprise en local</a>";

                echo "<h2>Paramètres du client (pour reprise sur serveur uniquement)</h2>";

                    echo "<field><label for=\"servor\">Serveur de connexion Oracle</label><input id=\"servor\" type=\"disabled\" value=\"$oracle_host:$oracle_port/$oracle_service\" disabled /></field>";
                    echo "<field><label for=\"login\">Identifiant de connexion Oracle</label><input id=\"login\" name=\"login\" onchange=\"autocomplete_password(this)\" type=\"text\" placeholder=\"geodpville\" required /></field>";
                    echo "<field><label for=\"password\">Mot de passe de connexion Oracle</label><input id=\"password\" name=\"password\" type=\"password\"/><span onmousedown=\"show_password(true)\" onmouseup=\"show_password(false) \">&#128065;</span></field>";
                    echo "<field><label for=\"type\">Type de client</label><input id=\"type\" name=\"type\" type=\"text\" placeholder=\"A définir\"/></field>";

                    echo "<field>";
                        echo "<input type=\"submit\" onclick=\"loading()\" value=\"Effectuer la reprise\" $button_disabled />";
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

                echo "<h2>Configuration de la BDD</h2>";

                    echo "<field><label>Nom de la base de données</label><input type=\"disabled\" value=\"$mysql_dbname\" disabled /></field>";
                    echo "<field><label>Contenu de la base de données (copie des tables GEODP v1 utilisées par la reprise)</label><input type=\"disabled\" value=\"reprise_dibtic.sql à importer\" disabled /></field>";
                    
                    echo "<p>Une table sera créée en local pour chaque fichier source lors de la reprise.<br>Lorsque la reprise est testée, des tables identiques aux tables GEODP sont utilisées en local.</p>";

                echo "<h2>Historique des reprises</h2>";

                    echo "<table>";
                    echo "<tr><th>Nom de la reprise</th><th>Date</th><th>Durée (secondes)</th><th>Etat</th></th><th>Conflits</th><th>Erreurs</th></tr>";
                    foreach ($mysql_conn->query("SELECT * FROM $reprise_table WHERE nom != '[ MODE TEST ]'") as $row) {
                        $duree = strtotime($row["date_fin"]) - strtotime($row["date_debut"]);
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
                            case "3":
                                $etat = "Interrompue";
                                break;
                        }
                        echo "<tr><td>" . $row["nom"] . "</td><td>" . $row["date_debut"] . "</td><td>$duree</td><td>$etat</td><td>" . $row["conflits"] . "</td><td>" . $row["erreurs"] . "</td></tr>";
                    }
                    echo "</table>";

            echo "</form>";
            
            echo "</init>";
        }

        /*************************
         *        ANALYSE        *
         *************************/

        if ($analyse_mode) {

            $nom_reprise = (!$test_mode && $client_name === "") ? $oracle_login : $client_name;
            $mysql_conn->exec("INSERT INTO $reprise_table (nom, etat) VALUES ('$nom_reprise', 1)");
            $reprise_id = $mysql_conn->lastInsertId();
            
            //// Sommaire

            echo "<aside>";
            echo "<h1>$title</h1>";
            echo "<ol id=\"sommaire\">";
                echo "<h2>$nom_reprise</h2>";
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
                echo "<li><a href=\"#src_formatted_instructions\">Formatage des instructions</a></li>";
                echo "<li><a href=\"#destination\">Insertion des données GEODP</a>";
                    echo "<ol>";
                        echo "<li><a href=\"#dest_instructions\">Instructions / Évènements</a></li>";
                    echo "</ol>";
                echo "</li>";
            echo "<li><a href=\"$script_file_name\">Retour à l'accueil</a></li>";
            echo "</ol>";
            echo "</aside>";

            echo "<section>";

            echo "<summary id=\"summary\">";
                echo "<h1>$nom_reprise</h1>";
                echo "<p id=\"nb_errors\"></p>";
                echo "<table id=\"nb_content\"><tr><th></th></tr></table>";
                echo $test_mode ? "<div><a href=\"$script_file_name\">Retour à l'accueil</a></div>" : "<div><a target=\"_blank\" href=\"//ares/geodp.".substr($oracle_login, strlen("geodp"))."\">ares/geodp.".substr($oracle_login, strlen("geodp"))."</a></div>";
            echo "</summary>";

            $nb_errors = 0;
            $nb_warnings = 0;
            
            if ($erase_destination_tables) {
                $dest_conn->exec("DELETE FROM $dest_instruction");
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

                require 'spreadsheet/vendor/autoload.php';

                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load("$directory_name/$file_name");

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

                if ($exp === "exploitants") { // TODO définir le token de la table des rperises pour ODP
                    $mysql_conn->exec("UPDATE $reprise_table SET token = '" . substr(implode("", $xls_data[2]), 0, 250) . "' WHERE id = $reprise_id");
                }

                // Création de la table correspondant au fichier

                $src_tables[$exp] = $table_name;

                $src_conn->exec("DROP TABLE IF EXISTS $table_name");

                $create_table_query_values = [];
                foreach ($xls_data[1] as $col) {
                    if ($col === NULL) {
                        echo "<p class=\"danger\">Impossible de continuer car une colonne n'a pas de nom dans le fichier " . $source_files[$exp] . "</p>";
                        return;
                    }
                    $col = str_replace("\n", " ", $col);
                    while (strpos($col, "  ") !== false) $col = str_replace("  ", " ", $col);
                    if (in_array($col, $create_table_query_values)) {
                        $col .= " bis";
                    }
                    array_push($create_table_query_values, $col);
                }

                $create_table_query = "CREATE TABLE $table_name (`" . implode("` VARCHAR(250), `", $create_table_query_values) . "` VARCHAR(250))";
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

                // Contrôle du nombre d'insertions faites par rapport au nombre de données du fichier

                summarize_queries($nb_inserted, count($xls_data) - 1, $nb_errors, [], $nb_warnings);

                $reprise_col = str_replace("é", "e", $exp) . "_src";
                $mysql_conn->exec("UPDATE $reprise_table SET $reprise_col = " . intval(count($xls_data) - 1) . " WHERE id = $reprise_id");
                $nb_content[$exp] = intval(count($xls_data) - 1);
            }
            
            // Association du nom des tables avec le contenu de leur fichier
            $src_instruction = $src_tables["instructions"];
            $src_formatted_instruction = $src_prefixe . "formatted_instruction";
            
            // Récupération des colonnes des évènements
            $src_evenement_cols = ["Date du dossier complet", "Date de transmission à l'inspection", "Date d'envoi des avis", "Date d'envoi de l'arrêté à la signature", "Date de retour de l'arrêté en signature", "Résultat de la décision", "Date retour instruction", "Date de retour après avis", "Motif du retour", "Date de retour après avis bis", "Date second retour instruction", "Date de transmission au service administratif"];

            //// Formatage des instructions

            echo "<h1 id=\"src_formatted_instructions\">Formatage des instructions</h1>";

            echo "<h2><span><tt>$src_instruction</tt> vers <tt>$src_formatted_instruction</tt></span></h2>";
            fwrite($output_file, "\n-- Formatage des instructions\n\n");

            $nb_to_format = 0;
            $nb_formatted = 0;
            $warnings = [];

            $src_instruction_cols = [];
            foreach ($src_conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$src_instruction'") as $row) {
                array_push($src_instruction_cols, $row["COLUMN_NAME"]);
            }

            $src_conn->exec("DROP TABLE IF EXISTS $src_formatted_instruction");
            $create_table_query = "CREATE TABLE $src_formatted_instruction (`" . implode("` VARCHAR(250), `", $src_instruction_cols) . "` VARCHAR(250))";
            $src_conn->exec($create_table_query);

            if ($display_dest_requests) echo "<div class=\"pre\">";
            foreach ($src_conn->query("SELECT * FROM $src_instruction") as $row) {
                $instruction_num = $row["N° d'instruction"];

                if (strlen($instruction_num) > 13) {
                    array_push($warnings, "Le numéro de l'instruction $instruction_num est supérieur à 13 caractères, l'instruction n'est donc pas insérée");
                } else {
                    $dest_marche_values = [];

                    foreach ($src_instruction_cols as $col) {
                        $value = $row[$col];
                        $new_value = $value;

                        switch ($col) {
                            case "N° d'instruction":
                                $value = str_replace(" ", "", $value);
                                if ($value === "") {
                                    $new_value = "999999";
                                    array_push($warnings, "Le numéro d'instruction est vide, il est donc changé en $new_value");
                                }
                                break;
                            case "Date du dépôt":
                                $depot_date = date_create_from_format("d/m/Y", $value);
                                if ($depot_date === false) {
                                    $new_value = reformate_date($value);
                                    if ($new_value === NULL) {
                                        $new_value = "";
                                        array_push($warnings, "La date du dépôt ($value) de l'instruction $instruction_num n'est pas au format jj/mm/aaaa et ne peut être convertie, elle est donc nullifiée");
                                    } else {
                                        array_push($warnings, "La date du dépôt ($value) de l'instruction $instruction_num n'est pas au format jj/mm/aaaa, elle est donc changée en $new_value");
                                    }
                                }
                                break;
                            case "Numéro tiers": // exp_code_comptable
                                $value = str_replace(" ", "", $value);
                                $new_value = $value;
                                while (strlen($new_value) < 6) {
                                    $new_value = "0$new_value";
                                }
                                if (strlen($value) < 6) {
                                    array_push($warnings, "Le numéro tiers $value fait moins de 6 caractères, il est donc changé en $new_value");
                                }
                                break;
                            case "Date de notification de l'arrêté": // non utilisé dans les requêtes du fichier des instructions TODO
                                $notif_arrete_date = date_create_from_format("d/m/Y", $value);
                                if ($notif_arrete_date === false) {
                                    $new_value = reformate_date($value);
                                    if ($new_value === NULL) {
                                        $new_value = "";
                                        array_push($warnings, "La date de notification de l'arrêté ($value) de l'instruction $instruction_num n'est pas au format jj/mm/aaaa et ne peut être convertie, elle est donc nullifiée");
                                    } else {
                                        array_push($warnings, "La date de notification de l'arrêté ($value) de l'instruction $instruction_num n'est pas au format jj/mm/aaaa, elle est donc changée en $new_value");
                                    }
                                }
                                break;
                            case "Date du dossier complet":
                                $dossier_complet_date = date_create_from_format("d/m/Y", $value);
                                if ($dossier_complet_date === false) {
                                    $new_value = reformate_date($value);
                                    if ($new_value === NULL) {
                                        $new_value = "";
                                        array_push($warnings, "La date de dossier complet ($value) de l'instruction $instruction_num n'est pas au format jj/mm/aaaa et ne peut être convertie, elle est donc nullifiée");
                                    } else {
                                        array_push($warnings, "La date de dossier complet ($value) de l'instruction $instruction_num n'est pas au format jj/mm/aaaa, elle est donc changée en $new_value");
                                    }
                                }
                                break;
                            case "Date de transmission à l'inspection":
                                $transmission_date = date_create_from_format("d/m/Y", $value);
                                if ($transmission_date === false) {
                                    $new_value = reformate_date($value);
                                    if ($new_value === NULL) {
                                        $new_value = "";
                                        array_push($warnings, "La date de transmission à l'inspection ($value) de l'instruction $instruction_num n'est pas au format jj/mm/aaaa et ne peut être convertie, elle est donc nullifiée");
                                    } else {
                                        array_push($warnings, "La date de transmission à l'inspection ($value) de l'instruction $instruction_num n'est pas au format jj/mm/aaaa, elle est donc changée en $new_value");
                                    }
                                    $value = $new_value;
                                }
                                break;
                            case "Date d'envoi des avis":
                                $envoi_avis_date = date_create_from_format("d/m/Y", $value);
                                if ($envoi_avis_date === false) {
                                    $new_value = reformate_date($value);
                                    if ($new_value === NULL) {
                                        $new_value = "";
                                        array_push($warnings, "La date d'envoi des avis ($value) de l'instruction $instruction_num n'est pas au format jj/mm/aaaa et ne peut être convertie, elle est donc nullifiée");
                                    } else {
                                        array_push($warnings, "La date d'envoi des avis ($value) de l'instruction $instruction_num n'est pas au format jj/mm/aaaa, elle est donc changée en $new_value");
                                    }
                                }
                                break;
                            case "Date d'envoi de l'arrêté à la signature":
                                $envoi_arrete_signature_date = date_create_from_format("d/m/Y", $value);
                                if ($envoi_arrete_signature_date === false) {
                                    $new_value = reformate_date($value);
                                    if ($new_value === NULL) {
                                        $new_value = "";
                                        array_push($warnings, "La date d'envoi de l'arrêté à la signature ($value) de l'instruction $instruction_num n'est pas au format jj/mm/aaaa et ne peut être convertie, elle est donc nullifiée");
                                    } else {
                                        array_push($warnings, "La date d'envoi de l'arrêté à la signature ($value) de l'instruction $instruction_num n'est pas au format jj/mm/aaaa, elle est donc changée en $new_value");
                                    }
                                }
                                break;
                            case "Date de retour de l'arrêté en signature":
                                $retour_arrete_signature_date = date_create_from_format("d/m/Y", $value);
                                if ($retour_arrete_signature_date === false) {
                                    $new_value = reformate_date($value);
                                    if ($new_value === NULL) {
                                        array_push($warnings, "La date de retour de l'arrêté en signature ($value) de l'instruction $instruction_num n'est pas au format jj/mm/aaaa et ne peut être convertie, elle est donc nullifiée");
                                    } else {
                                        array_push($warnings, "La date de retour de l'arrêté en signature ($value) de l'instruction $instruction_num n'est pas au format jj/mm/aaaa, elle est donc changée en $new_value");
                                    }
                                }
                                break;
                            case "Objet de la demande":
                                $new_value = $value;

                                $eventaire = []; preg_match('/.ventaire/', $value, $eventaire);
                                $terrasse = []; preg_match('/.errasse/', $value, $terrasse);
                                $jardinieres = []; preg_match('/.ardini.re/', $value, $jardinieres);

                                if (isset($eventaire[0])) {
                                    $new_value = "Etalage";
                                    array_push($warnings, "L'objet de la demande ($value) de l'instruction $instruction_num correspond au domaine $new_value de GEODP, il est donc changé");
                                } else if (isset($terrasse[0])) {
                                    $new_value = "Terrasse";
                                } else if (isset($jardinieres[0])) {
                                    $new_value = "Jardinières";
                                } else {
                                    $new_value = NULL;
                                    array_push($warnings, "L'objet de la demande ($value) de l'instruction $instruction_num ne correspond a aucun domaine de GEODP, il est donc nullifié");
                                }
                                break;
                            default:
                                break;
                        }

                        array_push($dest_marche_values, addslashes_nullify($new_value));
                    }

                    $insert_into_query = "INSERT INTO $src_formatted_instruction (`" . implode("`, `", $src_instruction_cols) . "`) VALUES (" . implode(", ", $dest_marche_values) . ")";
                    execute_query($insert_into_query, $nb_formatted, $nb_to_format);
                }
            }
            if ($display_dest_requests) echo "</div>";

            summarize_queries($nb_formatted, $nb_to_format, $nb_errors, $warnings, $nb_warnings);

            //// Insertion des données GEODP

            echo "<h1 id=\"destination\">Insertion des données GEODP</h1>";
            fwrite($output_file, "\n---------------------------------------------\n-- Insertion des données GEODP\n--\n\n");

            $dest_instruction_cols = ["DI_REF", "DOS_REF", "DET_REF", "EXP_REF", "DI_NUMERO", "DI_DATE_DEPOT", "DCREAT", "UCREAT"];
            $dest_instruction_evenement_cols = ["DIE_REF", "DI_REF", "EVE_REF", "DIE_DATE_CREATION", "DCREAT", "UCREAT", "DIE_COMMENTAIRE"];

            // Instructions / Évènements

            echo "<h2 id=\"dest_instructions\">Instructions / Évènements / Document<span><tt>$dest_instruction</tt> / <tt>$dest_instruction_evenement</tt> / <tt>$dest_dossier_document</tt></span></h2>";
            fwrite($output_file, "\n-- Instructions / Évènements / Document\n\n");

            $nb_to_insert = 0;
            $nb_inserted = 0;
            $warnings = [];

            $last_di_ref = $dest_conn->query("SELECT MAX(DI_REF) FROM $dest_instruction")->fetch()[0];
            $last_die_ref = $dest_conn->query("SELECT MAX(DIE_REF) FROM $dest_instruction_evenement")->fetch()[0];

            $last_exp_ref = $dest_conn->query("SELECT MAX(EXP_REF) FROM $dest_exploitant")->fetch()[0];
            $last_dom_ref = $dest_conn->query("SELECT MAX(DOM_REF) FROM $dest_domaine_lang")->fetch()[0];
            $last_det_ref = $dest_conn->query("SELECT MAX(DET_REF) FROM $dest_dossier_etat_lang")->fetch()[0];
            $last_dos_ref = $dest_conn->query("SELECT MAX(DOS_REF) FROM $dest_dossier")->fetch()[0];

            $nb_instructions = $dest_conn->query("SELECT COUNT(*) FROM $dest_instruction")->fetch()[0];

            if ($display_dest_requests) echo "<div class=\"pre\">";
            foreach ($src_conn->query("SELECT * FROM $src_formatted_instruction WHERE `Objet de la demande` IS NOT NULL") as $row) {
                $di_numero = $row["N° d'instruction"];
                $di_code_comptable = $row["Numéro tiers"];
                $di_domaine = $row["Objet de la demande"];

                $header_err_message = "L'instruction $di_numero n'est pas insérée pour la raison suivante :<br>";

                $req_exp_ref = $dest_conn->query("SELECT EXP_REF FROM $dest_exploitant WHERE EXP_CODE_COMPTABLE = $di_code_comptable")->fetch();
                $req_dom_ref = $dest_conn->query("SELECT DOM_REF FROM $dest_domaine_lang WHERE DOM_NOM = '$di_domaine'")->fetch();

                if (!$req_exp_ref) {
                    if ($test_mode) {
                        $last_exp_ref += 1;
                        $dest_conn->exec("INSERT INTO $dest_exploitant (EXP_REF, EXP_CODE_COMPTABLE) VALUES ($last_exp_ref, $di_code_comptable)");
                        $req_exp_ref[0] = $last_exp_ref;
                        array_push($warnings, "Aucun exploitant avec le code comptable $di_code_comptable n'existe dans la table $dest_exploitant ; un tel exploitant est inséré pour le mode test");
                    } else {
                        array_push($warnings, $header_err_message . "Aucun exploitant avec le code comptable $di_code_comptable n'existe dans la table $dest_exploitant");
                    }
                } else if (!$req_dom_ref) {
                    if ($test_mode) {
                        $last_dom_ref += 1;
                        $dest_conn->exec("INSERT INTO $dest_domaine_lang (DOM_REF, DOM_NOM) VALUES ($last_dom_ref, '$di_domaine')");
                        $req_dom_ref[0] = $last_dom_ref;
                        array_push($warnings, "Aucun domaine appelé '$di_domaine' n'existe dans la table $dest_domaine_lang ; un tel domaine est inséré pour le mode test");
                    } else {
                        array_push($warnings, $header_err_message . "Aucun domaine appelé '$di_domaine' n'existe dans la table $dest_domaine_lang");
                    }
                } else {
                    $exp_ref = $req_exp_ref[0];
                    $dom_ref = $req_dom_ref[0];

                    $det_designation = "Inspection dossier";
                    $req_det_ref = $dest_conn->query("SELECT del.DET_REF FROM $dest_dossier_etat_lang del JOIN $dest_dossier_etat det ON det.DET_REF = del.DET_REF WHERE DET_VISIBLE = 1 AND DET_DESIGNATION = '$det_designation' AND DOM_REF = $dom_ref")->fetch();
                    $req_dos_ref = $dest_conn->query("SELECT DOS_REF FROM $dest_dossier WHERE EXP_REF = $exp_ref AND DOM_REF = $dom_ref")->fetch();

                    if (!$req_det_ref) {
                        if ($test_mode) {
                            $last_det_ref += 1;
                            $dest_conn->exec("INSERT INTO $dest_dossier_etat (DET_REF, DOM_REF, DET_VISIBLE) VALUES ($last_det_ref, $dom_ref, 1)");
                            $dest_conn->exec("INSERT INTO $dest_dossier_etat_lang (DET_REF, DET_DESIGNATION) VALUES ($last_det_ref, '$det_designation')");
                            $det_ref[0] = $last_det_ref;
                            array_push($warnings, "Aucun état de dossier visible désigné '$det_designation' pour le domaine $di_domaine n'existe dans la table $dest_dossier_etat_lang ; un tel état de dossier est inséré pour le mode test");
                        } else {
                            array_push($warnings, $header_err_message . "Aucun état de dossier visible désigné '$det_designation' pour le domaine $di_domaine n'existe dans la table $dest_dossier_etat_lang");
                        }
                    } else if (!$req_dos_ref) {
                        if ($test_mode) {
                            $last_dos_ref += 1;
                            $dest_conn->exec("INSERT INTO $dest_dossier (DOS_REF, EXP_REF, DOM_REF) VALUES ($last_dos_ref, $exp_ref, $dom_ref)");
                            $dos_ref[0] = $last_dos_ref;
                            array_push($warnings, "Aucun dossier associé à l'exploitant $exp_ref pour le domaine $di_domaine n'existe dans la table $dest_dossier ; un tel dossier est inséré pour le mode test");
                        } else {
                            array_push($warnings, $header_err_message . "Aucun dossier associé à l'exploitant $exp_ref pour le domaine $di_domaine n'existe dans la table $dest_dossier");
                        }
                    } else {
                        $det_ref = $req_det_ref[0];
                        $dos_ref = $req_dos_ref[0];

                        $verif_query = $dest_conn->query("SELECT COUNT(*) FROM $dest_instruction WHERE DOS_REF = $dos_ref AND DI_NUMERO = '$di_numero'")->fetch();
                        if ($verif_query[0] !== "0") {
                            array_push($warnings, $header_err_message . "Une instruction associée au dossier $dos_ref et portant le numéro '$di_numero' est déjà présente dans la table $dest_instruction");
                        } else {
                            $last_di_ref += 1;

                            $dest_instruction_values = [];

                            foreach ($dest_instruction_cols as $col) {
                                switch ($col) {
                                    case "DI_REF":
                                        array_push($dest_instruction_values, $last_di_ref);
                                        break;
                                    case "DOS_REF":
                                        array_push($dest_instruction_values, $dos_ref);
                                        break;
                                    case "DET_REF":
                                        array_push($dest_instruction_values, $det_ref);
                                        break;
                                    case "EXP_REF":
                                        array_push($dest_instruction_values, $exp_ref);
                                        break;
                                    case "DI_NUMERO":
                                        array_push($dest_instruction_values, "'$di_numero'");
                                        break;
                                    case "DI_DATE_DEPOT":
                                        array_push($dest_instruction_values, "'".string_to_date($row["Date du dépôt"], true)."'");
                                        break;
                                    case "DCREAT":
                                        array_push($dest_instruction_values, "'$dest_dcreat'");
                                        break;
                                    case "UCREAT":
                                        array_push($dest_instruction_values, "'$dest_ucreat'");
                                        break;
                                    default:
                                        array_push($dest_instruction_values, "'TODO'");
                                        break;
                                }
                            }

                            $insert_into_query = "INSERT INTO $dest_instruction (" . implode(", ", $dest_instruction_cols) . ") VALUES (" . implode(", ", $dest_instruction_values) . ")";
                            execute_query($insert_into_query, $nb_inserted, $nb_to_insert);

                            // Évènements

                            $src_evenement_values = [];
                            foreach ($src_evenement_cols as $col) {
                                $src_evenement_values[$col] = $row[$col];
                            }

                            if ($src_evenement_values["Date du dossier complet"]) {
                                $last_die_ref += 1;
                                
                                $eve_designation = "Réception de la demande";
                                $det_designation = "Administratif";
                                $req_eve_ref = $dest_conn->query("SELECT evl.eve_ref FROM $dest_evenement_lang evl JOIN $dest_evenement eve ON eve.EVE_REF = evl.EVE_REF JOIN $dest_dossier_etat de ON de.DET_REF = eve.DET_REF JOIN $dest_dossier_etat_lang del ON del.DET_REF = de.DET_REF WHERE EVE_DESIGNATION = '$eve_designation' AND EVE_VISIBLE = 1 AND DET_VISIBLE = 1 AND DOM_REF = $dom_ref AND DET_DESIGNATION = '$det_designation'")->fetch();
                               
                                if (!$req_eve_ref) {
                                    array_push($warnings, $header_err_message . "TODO y'a pas de eve_ref");
                                } else {
                                    $dest_instruction_evenement_values = [$last_die_ref, $last_di_ref, $req_eve_ref[0], "'" . string_to_date($src_evenement_cols["Date du dossier complet"], true) . "'" , "'$dest_dcreat'", "'$dest_ucreat'"];

                                    $insert_into_query = "INSERT INTO $dest_instruction_evenement (" . implode(", ", $dest_instruction_evenement_cols) . ") VALUES (" . implode(", ", $dest_instruction_evenement_values) . ")";
                                    execute_query($insert_into_query, $nb_inserted, $nb_to_insert);
                                }
                            }

                            // TODO les autres évènements

                            // Document

                            // TODO
                        } // Fin "si il n'existe pas déjà une instruction avec ce dossier et ce numéro"
                    } // Fin "si l'état du dossier ou le dossier n'existe pas"
                } // Fin "si le code comptable ou le domaine n'existe pas"
            }
            if ($display_dest_requests) echo "</div>";

            summarize_queries($nb_inserted, $nb_to_insert, $nb_errors, $warnings, $nb_warnings);

            // $nb_instructions = $dest_conn->query("SELECT COUNT(*) FROM $dest_instruction")->fetch()[0] - $nb_instructions;
            // $mysql_conn->exec("UPDATE $reprise_table SET instructions_dest = $nb_instructions WHERE id = $reprise_id");

            // Compteurs

            echo "<h2 id=\"dest_compteurs\">Compteurs<span><tt>$dest_compteur</tt></span></h2>";
            fwrite($output_file, "\n-- Compteurs\n\n");

            // TODO

            // dossier_instruction
            // dossier_instruction_evenement
            // dossier_document

            // NE PAS OUBLIER D'UTILISER string_to_date() dans les requêtes d'insertion dans les tables de destination, pour que la data ait la valeur d'un champ DATE relatif à la BDD de destination


            $mysql_conn->exec("UPDATE $reprise_table SET date_fin = '$timestamp', conflits = $nb_warnings, erreurs = $nb_errors, etat = 2 WHERE id = $reprise_id");

            echo "</section>";

            ?>

            <script>
                var dom_nb_errors = document.querySelector("#nb_errors");
                var nb_errors = parseInt(<?php echo $nb_errors; ?>);
                var nb_warnings = parseInt(<?php echo $nb_warnings; ?>);
                dom_nb_errors.innerHTML = (nb_errors === 0) ? (nb_warnings === 0) ? "<ok></ok>" : "<mok></mok>" : "<nok></nok>";
                dom_nb_errors.innerHTML += "<br><br>";
                dom_nb_errors.innerHTML += "Reprise terminée avec " + nb_errors + " erreurs et " + nb_warnings + " conflits";

                var dom_nb_content = document.querySelector("#nb_content");
                dom_nb_content.innerHTML += "<tr><td></td></tr>";
            </script>

            <?php

        } // Fin "si $get analyze = 1"

    ?>

<layer><message></message></layer>

</body>

<script>

var script_file_name = "<?php echo $script_file_name; ?>";

</script>

<script src="js/script.js"></script>

</html>
