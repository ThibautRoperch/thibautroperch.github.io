<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Reprise ODP</title>
    <link rel="stylesheet" type="text/css" href="css/style.css">
</head>

<?php

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
$keywords_files = ["instruction/evenement", "exploitant/tier"];

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
$dest_dossier = "dest_dossier";
$dest_instruction = "dest_instruction";
$dest_instruction_event = "dest_instruction_evenement";
$dest_exploitant = "dest_exploitant";

if (!$test_mode) {
    $dest_dossier = "DOSSIER";
    $dest_instruction = "DOSSIER_INSTRUCTION";
    $dest_instruction_event = "DOSSIER_INSTRUCTION_EVENEMENT";
    $dest_exploitant = "EXPLOITANT";
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

            echo "<form action=\"odp.php?analyze=1\" method=\"POST\">";

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
                    foreach ($mysql_conn->query("SELECT * FROM $reprise_table") as $row) {
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
            $mysql_conn->exec("INSERT INTO reprise (nom, etat) VALUES ('$nom_reprise', 'odp', 1)");
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
                        echo "<li><a href=\"#dest_instructions\">Instructions</a></li>";
                    echo "</ol>";
                echo "</li>";
            echo "<li><a href=\"odp.php\">Retour à l'accueil</a></li>";
            echo "</ol>";
            echo "</aside>";

            echo "<section>";

            echo "<summary id=\"summary\">";
                echo "<h1>$nom_reprise</h1>";
                echo "<p id=\"nb_errors\"></p>";
                echo "<table id=\"nb_content\"><tr><th></th></tr></table>";
                echo $test_mode ? "<div><a href=\"odp.php\">Retour à l'accueil</a></div>" : "<div><a target=\"_blank\" href=\"//ares/geodp.".substr($oracle_login, strlen("geodp"))."\">ares/geodp.".substr($oracle_login, strlen("geodp"))."</a></div>";
            echo "</summary>";

            $nb_errors = 0;
            $nb_warnings = 0;
            
            if ($erase_destination_tables) {
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
                    if (in_array("$col", $create_table_query_values)) {
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
            
            //// Formatage des instructions

            echo "<h1 id=\"src_formatted_instructions\">Formatage des instructions</h1>";

            echo "<h2><span><tt>$src_formatted_instruction</tt></span></h2>";

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

            foreach ($src_conn->query("SELECT * FROM $src_instruction") as $row) {
                $instruction_num = $row["N° d'instruction"];

                if (strlen($instruction_num) > 13) {
                    array_push($warnings, "Le numéro de l'instruction $instruction_num est supérieur à 13 caractères, l'instruction n'est donc pas insérée");
                } else {
                    $dest_marche_values = [];

                    foreach ($src_instruction_cols as $col) {
                        $value = $row[$col];

                        switch ($col) {
                            case "zefzef":
                                // CHANGE RLA VLAUE
                                break;
                            default:
                                break;
                        }

                        array_push($dest_marche_values, addslashes($value));
                    }

                    $insert_into_query = "INSERT INTO $src_formatted_instruction (`" . implode("`, `", $src_instruction_cols) . "`) VALUES ('" . implode("', '", $dest_marche_values) . "')";
                    execute_query($insert_into_query, $nb_formatted, $nb_to_format);
                }
            }

            summarize_queries($nb_formatted, $nb_to_format, $nb_errors, $warnings, $nb_warnings);

            //// Insertion des données GEODP

            echo "<h1 id=\"destination\">Insertion des données GEODP</h1>";
            fwrite($output_file, "\n---------------------------------------------\n-- Insertion des données GEODP\n--\n\n");

            // Instructions

            echo "<h2 id=\"dest_instructions\">Instructions<span><tt>$dest_instruction</tt></span></h2>";
            fwrite($output_file, "\n-- Instructions\n\n");

            $nb_to_insert = 0;
            $nb_inserted = 0;

            // TODO


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
</html>

<script>

// Accueil

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

function loading() {
    display_message("Reprise en cours", false);
}

function display_message(message, popup_mode) {
    var new_class_name = "displayed";
    new_class_name += popup_mode ? " popup" : "";
    document.querySelector("layer").className = new_class_name;
    document.querySelector("layer").querySelector("message").innerHTML = message;
}

function hide_message() {
    document.querySelector("layer").className = "";
}

// https://script-tutorials.developpez.com/tutoriels/html5/drag-drop-file-upload-html5/
var droparea = document.querySelector(".droparea");
if (droparea) {
    droparea.addEventListener("dragover", drag_over, false);
}

function drag_over(event) { // survol
    event.stopPropagation();
    event.preventDefault();

    display_message("Glisser-déposer les fichiers", false);

    var layer = document.querySelector("layer");
    layer.addEventListener("drop", drop, false);
}

var form_data = new FormData();

function drop(event) { // glisser deposer
    event.stopPropagation();
    event.preventDefault();

    dropped_files = event.dataTransfer.files;
    if (!dropped_files || !dropped_files.length) return;

    for (var i = 0; i < dropped_files.length; ++i) {
        form_data.append(""+i, dropped_files[i]);
    }

    upload_files(false);
}

function upload_files(confirm_erase) {
    var get_confirm = confirm_erase ? "?confirm=1" : "";

    var xhr = new XMLHttpRequest();
    xhr.open("POST", "odp.php" + get_confirm);
    xhr.onload = function() {
        if (!confirm_erase) {
            var parser = new DOMParser();
            var alert = parser.parseFromString(xhr.response, "text/html").querySelector("alert");
            var message = alert.innerHTML + "<buttons><a class=\"button\" onclick=\"hide_message()\" href=\"#\">Annuler</a><button onclick=\"upload_files(true)\">Confirmer</button></buttons>";
            display_message(message, true);
        } else {
            display_message("Importation des fichiers", false);
            location.reload();
        }
    };
    xhr.send(form_data);
}

// Analyse

summary_li = (document.querySelector("#sommaire")) ? document.querySelector("#sommaire").querySelectorAll("li") : [];
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
