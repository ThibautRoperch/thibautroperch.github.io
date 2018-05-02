<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Reprise Placier</title>
    <link rel="stylesheet" type="text/css" href="../css/style.css">
</head>

<?php

$script_file_name = "placier.php";
$title = "Reprise dibtic vers GEODP v1<br>Placier";

// Génère des tables à partir des fichiers lus, extrait les informations voulues et exécute + donne les requêtes SQL
// Entrée : Fichiers Excel dibtic placier
// Sortie : Fichier SQL contenant les requêtes SQL à envoyer dans la base de données GEODP v1

/**
 * VERSION 2.2 - 02/05/2018
 * 
 * Marchés      Ils sont tous ajoutés en conservant leur code, identifiant unique dans dibtic.
 * Articles     Tous les articles sont ajoutés, mêmes si non utilisés.
 *              Le.s marché.s de référence d'un article sont obtenus en regardant le numc de l'article et le groupe des marchés associés.
 *              Les articles qui ont le même nom ne sont pas insérés : soit ils ont juste un multiplicateur, soit il y a une ligne pour un tarif passager et une ligne pour un tarif d'abonné (à fusionner).
 * Activités    (commerciales) Une activité est insérée dans GEODP si et seulement si elle est utilisée par au moins un exploitant.
 *              Elles sont obtenue dans le fichier des exploitants, il n'y a plus de fichier des activités.
 * Exploitants  Le fichier qui met en relation les abonnements aux marchés et aux articles,
 *              avec possibilité de conflits à contrôler (ex. : un exploitant abonné à un marché à un jour, or ledit marché n'ouvre pas ledit jour d'après le fichier des marchés).
 * Présences    Les présences aux rassemblements sont lues dans le fichier qui comporte les présences et les tickets
 * 
 * Gestion de la multi utilisation du script
 * Glisser-déposer des fichiers sources dibtic
 * 2.1 - Des société_marché sont insérés pour tous les jours d'un marché lorsque l'exploitant est associé à un marché mais explicitement abonné à aucun jour de ce marché
 * 2.2 - Abonnements (factures, ...) et présences
 * 
 * @author Thibaut ROPERCH
 */


// Ouvrir cette page avec le paramètre 'analyze=1' pour effectuer des tests en local, l'ouvrir normalement en situation réelle
$analyze_mode = (isset($_GET["analyze"]) && $_GET["analyze"] === "1");
$test_mode = ($analyze_mode && isset($_POST["login"]) && $_POST["login"] !== "" && isset($_POST["password"])) ? false : true; // en test la destination est MySQL, en situatiçon réelle la destination est Oracle

$php_required_version = "7.1.9";

date_default_timezone_set("Europe/Paris");
$timestamp = date("Y-m-d H:i:s", time());


/*************************
 *       FICHIERS        *
 *************************/

// Répertoire contenant les fichiers sources (dibtic)
$directory_name = "./dibtic_placier";

// Résumé du contenu des fichiers sources (dibtic)
$expected_content = ["marchés", "articles", "exploitants", "abonnements", "présences"];

// Noms possibles des fichiers sources (dibtic)
$keywords_files = ["marche/marché", "classe/article/tarif", "exploitant/assujet", "abonn", "presence"];

// Gestion des obligations de présence des fichiers sources (dibtic)
$mandatory_files = [true, true, true, false, false];

// Données extraites des fichiers sources (dibtic)
$extracted_files_content = [
    "Libellé et jour(s) de chaque marché.",
    "Les articles qui ont le même nom seront fusionnés s'ils sont complémentaires au niveau du tarif (passagers et abonnés), ignorés sinon. Un <tt>X</tt> dans la colonne <tt>abo</tt> indique que le tarif est un tarif d'abonnés. Les codes PDA des tarifs passagers doivent être renseignés dans une colonne <tt>code_pda</tt>. Des colonnes <tt>date_debut</tt> et <tt>date_fin</tt> peuvent être ajoutées afin d'expliciter la période de validité de l'article (sur l'année en cours par défaut). Une colonne <tt>marches</tt> peut être ajoutée pour spécifier le(s) code(s) marché (en les séparant par une virgule) associé(s) à l'article (par défaut, les articles sont ajoutés aux marchés appartenant à leur groupe <tt>numc</tt>).",
    "Code de l’exploitant, raison sociale, nom/prénom, adresse, numéro de tel, mail, activité, abonnements aux marchés associés à l’exploitant, pièces justificatives avec date d'échéance. Les exploitants sans nom ou possédant une date de suppression non vide ne seront pas repris.",
    "Abonnements des exploitants (factures, ...)",
    "Présences des exploitants sur leur emplacement (rassemblements, ...)"
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
$dest_utilisateur = "dest_utilisateur";
$dest_employe = "dest_employe";
$dest_employe_marche = "dest_employe_marche";
$dest_groupe_activite_lang = "dest_groupe_activite_langue";
$dest_activite_lang = "dest_activite_langue";
$dest_marche = "dest_marche";
$dest_marche_lang = "dest_marche_langue";
$dest_article = "dest_article";
$dest_article_lang = "dest_article_langue";
$dest_activite_commerciale = "dest_activitecommerciale";
$dest_activite_commerciale_lang = "dest_activitecommerciale_langue";
$dest_piece = "dest_societe_propriete";
$dest_piece_lang = "dest_societe_propriete_langue";
$dest_exploitant = "dest_exploitant";
$dest_societe_marche = "dest_societe_marche";
$dest_exercice_comptable = "dest_exercice_comptable";
$dest_facture = "dest_facture";
$dest_article_facture = "dest_article_facture";
$dest_article_facture_lang = "dest_article_facture_langue";
$dest_abonnement = "dest_abonnement";
$dest_piece_val = "dest_societe_propriete_valeur";
$dest_compteur = "dest_compteur";
$dest_rassemblement = "dest_rassemblement";
$dest_presence = "dest_presence";

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
    $dest_activite_commerciale = "ACTIVITECOMMERCIALE";
    $dest_activite_commerciale_lang = "ACTIVITECOMMERCIALE_LANGUE";
    $dest_piece = "SOCIETE_PROPRIETE";
    $dest_piece_lang = "SOCIETE_PROPRIETE_LANGUE";
    $dest_exploitant = "EXPLOITANT";
    $dest_societe_marche = "SOCIETE_MARCHE";
    $dest_exercice_comptable = "EXERCICE_COMPTABLE";
    $dest_facture = "FACTURE";
    $dest_article_facture = "ARTICLE_FACTURE";
    $dest_article_facture_lang = "ARTICLE_FACTURE_LANGUE";
    $dest_abonnement = "ABONNEMENT";
    $dest_piece_val = "SOCIETE_PROPRIETE_VALEUR";
    $dest_compteur = "COMPTEUR";
    $dest_rassemblement = "RASSEMBLEMENT";
    $dest_presence = "PRESENCE";
}

// Mettre à vrai pour reprendre les abonnements, mettre à faux sinon (automatiquement calculé lors de la reprise sur serveur (option payante))
$insert_abonnements = (!$test_mode && isset($_POST["insert_abonnements"])) ? true_false($_POST["insert_abonnements"]) : false; // true | false

// Mettre à vrai pour reprendre les précences, mettre à faux sinon (automatiquement calculé lors de la reprise sur serveur (option payante))
$insert_presences = (!$test_mode && isset($_POST["insert_presences"])) ? true_false($_POST["insert_presences"]) : false; // true | false

// Mettre à vrai pour supprimer les données des tables de destination avant l'insertion des nouvelles, mettre à faux sinon (automatiquement calculé lors de la reprise sur serveur)
$erase_destination_tables = (!$test_mode && isset($_POST["erase_destination_tables"])) ? true_false($_POST["erase_destination_tables"]) : true; // true | false

// Mettre à vrai pour afficher les requêtes SQL relatives aux tables de destination, mettre à faux sinon (dans tous les cas, le fichier $output_filename est généré)
$display_dest_requests = false; // true | false

// Mettre à vrai pour exécuter les requêtes SQL relatives aux tables de destination, mettre à faux sinon (dans tous les cas, le fichier $output_filename est généré)
$exec_dest_requests = true; // true | false

// Nom du client (automatiquement calculé lors de la reprise sur serveur)
$client_name = (!$test_mode && isset($_POST["type"])) ? $_POST["type"] : "[ MODE TEST ]";

// Valeurs des champs DCREAT et UCREAT utilisées dans les requêtes SQL
$dest_dcreat = $test_mode ? date("y/m/d", time()) : date("d/m/y", time());
$dest_ucreat = "ILTR";


/*************************
 *    CONNEXION MySQL    *
 *************************/

$mysql_host = "localhost";
$mysql_dbname = "reprise_dibtic_placier";
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

if (!$test_mode && $analyze_mode) {
    $oracle_conn = new PDO("oci:dbname=(DESCRIPTION = (ADDRESS_LIST = (ADDRESS = (PROTOCOL = TCP) (Host = $oracle_host) (Port = $oracle_port))) (CONNECT_DATA = (SERVICE_NAME = ".$oracle_service.")));charset=UTF8", $oracle_login, $oracle_password);
}


/*************************
 *    CHOIX CONNEXION    *
 *************************/

if ($analyze_mode) {
    $src_conn = $mysql_conn;
    $dest_conn = $test_mode ? $mysql_conn : $oracle_conn;
}


/*************************
 *  MULTI-UTILISATIONS   *
 *************************/

$reprise_table = "reprise";

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

function true_false($string) {
    return ($string === "true") ? true : false;
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
    } else {
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

    return "$month/$day/$year";
}

function build_query($main, $where, $order, $limit) { // Pour $dest_conn uniquement !
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
            foreach ($_FILES as $file) {
                copy($file["tmp_name"], "$directory_name/" . $file["name"]);
            }
        }
        closedir($directory);
    }
}

?>

<body <?php echo (!$analyze_mode) ? "class=\"droparea\"" : ""; ?>>
    
    <?php
    
        /*************************
         *        ACCUEIL        *
         *************************/
        
        if (!$analyze_mode) {

            echo "<init>";
            echo "<h1>$title</h1>";

            echo "<form action=\"$script_file_name?analyze=1\" method=\"POST\" onsubmit=\"loading()\" >";

                echo "<h2>Fichiers dibtic à reprendre</h2>";

                    echo "<p>Les fichiers sources sont contenus dans le dossier <tt>$directory_name</tt> (pour reprendre d'autres fichiers, les glisser-déposer ici ou changer directement le contenu du dossier) :";
                    echo "<table>";
                        echo "<tr><th>Contenu</th><th>Fichier correspondant</th><th>Informations transférables dans GEODP</th></tr>";
                        $button_disabled = false;
                        for ($i = 0; $i < count($expected_content); ++$i) {
                            echo "<tr><td>" . ucfirst($expected_content[$i]) . "</td><td>";
                            if (isset($files_to_convert[$i])) {
                                echo "<ok></ok>$directory_name/" . $files_to_convert[$i];
                            } else {
                                if ($mandatory_files[$i]) {
                                    echo "<nok></nok>Fichier manquant";
                                    $button_disabled = true;
                                } else {
                                    echo "<mok></mok>Fichier manquant";
                                }
                            }
                            echo "</td><td>" . $extracted_files_content[$i];
                            echo "</td></tr>";
                        }
                    echo "</table>";

                    $button_disabled = $button_disabled ? "disabled" : "";

                    echo "<p>Les fichiers peuvent être pré-traités dans le but d'enlever des marchés, modifier les articles pour corriger leurs tarifs, leurs noms, leurs codes PDA, ... Les modifications risquent de corrompre les associations entre les fichiers.</p>";

                    if ($button_disabled === "") echo "<a class=\"button\" href=\"?analyze=1\" onclick=\"loading()\">Tester la reprise en local</a>";

                echo "<h2>Paramètres du client (pour reprise sur serveur uniquement)</h2>";

                    echo "<field><label for=\"servor\">Serveur de connexion Oracle</label><input id=\"servor\" type=\"disabled\" value=\"$oracle_host:$oracle_port/$oracle_service\" disabled /></field>";
                    echo "<field><label for=\"login\">Identifiant de connexion Oracle</label><input id=\"login\" name=\"login\" onchange=\"autocomplete_typing(this, 'password')\" type=\"text\" placeholder=\"geodpville\" required /></field>";
                    echo "<field><label for=\"password\">Mot de passe de connexion Oracle</label><input id=\"password\" name=\"password\" type=\"password\"/><span onmousedown=\"show_password(true)\" onmouseup=\"show_password(false) \">&#128065;</span></field>";
                    echo "<field><label for=\"type\">Nom du client</label><input id=\"type\" name=\"type\" type=\"text\" placeholder=\"A définir\"/></field>";
                    echo "<field><label for=\"erase_destination_tables\">Vider les tables de destination en amont</label>";
                        $yes_selected = $erase_destination_tables ? "selected" : "";
                        $no_selected = !$erase_destination_tables ? "selected" : "";
                        echo "<select id=\"erase_destination_tables\" name=\"erase_destination_tables\"><option value=\"true\" $yes_selected>Oui</option><option value=\"false\" $no_selected>Non</option></select>";
                    echo "</field>";
                    echo "<field><label for=\"insert_abonnements\">Reprendre les abonnements</label>";
                        $yes_selected = $insert_abonnements ? "selected" : "";
                        $no_selected = !$insert_abonnements ? "selected" : "";
                        echo "<select id=\"insert_abonnements\" name=\"insert_abonnements\"><option value=\"true\" $yes_selected>Oui</option><option value=\"false\" $no_selected>Non</option></select>";
                    echo "</field>";
                        echo "<field><label for=\"insert_presences\">Reprendre les présences</label>";
                        $yes_selected = $insert_presences ? "selected" : "";
                        $no_selected = !$insert_presences ? "selected" : "";
                        echo "<select id=\"insert_presences\" name=\"insert_presences\"><option value=\"true\" $yes_selected>Oui</option><option value=\"false\" $no_selected>Non</option></select>";
                    echo "</field>";

                    echo "<field>";
                        echo "<input type=\"submit\" value=\"Effectuer la reprise sur serveur\" $button_disabled />";
                    echo "</field>";
                
                echo "<h2>Autres paramètres</h2>";

                    echo "<field><label>Afficher les requêtes à exécuter</label><input type=\"disabled\" value=\"".yes_no($display_dest_requests)."\" disabled /></field>";
                    echo "<field><label>Exécuter les requêtes</label><input type=\"disabled\" value=\"".yes_no($exec_dest_requests)."\" disabled /></field>";
                    echo "<field><label>Fichier de sortie</label><input type=\"disabled\" value=\"$output_filename\" disabled /></field>";

                echo "<h2>Configuration de <a href=\"http://www.wampserver.com/#download-wrapper\">WAMP</a></h2>";

                    $version_ok = (phpversion() === $php_required_version) ? true : false;
                    echo "<field><label>Version de PHP</label><input type=\"disabled\" value=\"$php_required_version\" disabled />" . ok_nok($version_ok) . "</field>";
                    $version_ok = (phpversion('pdo_mysql') !== "") ? true : false;
                    echo "<field><label>Extension MySQL via PDO</label><input type=\"disabled\" value=\"php_pdo_mysql\" disabled />" . ok_nok($version_ok) . "</field>";
                    $version_ok = (phpversion('pdo_oci') !== "") ? true : false;
                    echo "<field><label>Extension OCI via PDO</label><input type=\"disabled\" value=\"php_pdo_oci\" disabled />" . ok_nok($version_ok) . "</field>";

                echo "<h2>Configuration de la BDD</h2>";

                    echo "<field><label>Nom de la base de données</label><input type=\"disabled\" value=\"$mysql_dbname\" disabled /></field>";
                    echo "<field><label>Contenu de la base de données (copie des tables GEODP v1 utilisées par la reprise)</label><input type=\"disabled\" value=\"reprise_dibtic.sql à importer\" disabled /></field>";
                    
                    echo "<p>Une table sera créée en local pour chaque fichier source lors de la reprise.<br>Lorsque la reprise est testée, des tables identiques aux tables GEODP sont utilisées en local.</p>";

                echo "<h2>Historique des reprises</h2>";

                    echo "<table>";
                    echo "<tr><th>Nom de la reprise</th><th>Date</th><th>Durée (secondes)</th><th>Etat</th></th><th>Conflits</th><th>Erreurs</th></tr>";
                    foreach ($mysql_conn->query("SELECT * FROM $reprise_table ORDER BY date_debut DESC") as $row) {
                        $duree = strtotime($row["date_fin"]) - strtotime($row["date_debut"]);
                        if ($duree < 0) $duree = 0;
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
                        echo "<tr><td>" . $row["nom"] . "</td><td>" . date("d/m/Y à H:i:s", strtotime($row["date_debut"])) . "</td><td>$duree</td><td>$etat</td><td>" . $row["conflits"] . "</td><td>" . $row["erreurs"] . "</td></tr>";
                    }
                    echo "</table>";

            echo "</form>";
            
            echo "</init>";
        }

        /*************************
         *        ANALYSE        *
         *************************/

        if ($analyze_mode) {

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
                if ($erase_destination_tables) {
                    echo "<li><a href=\"#erase\">Vidage des tables de destination</a>";
                        echo "<ol>";
                            echo "<li><a href=\"#erase_presences\">Présences / Rassemblements</a></li>";
                            echo "<li><a href=\"#erase_exploitants\">Abonnements / Pièces justificatives Valeur / Société Marché / Exploitants</a></li>";
                            echo "<li><a href=\"#erase_pices\">Pièces justificatives Langue / Pièces justificatives</a></li>";
                            echo "<li><a href=\"#erase_activites_comm\">Activités commerciales Langue / Activités commerciales</a></li>";
                            echo "<li><a href=\"#erase_articles\">Articles Langue / Articles</a></li>";
                            echo "<li><a href=\"#erase_marches\">Employé Marché / Marchés Langue / Marchés</a></li>";
                        echo "</ol>";
                    echo "</li>";
                }
                echo "<li><a href=\"#destination\">Insertion des données GEODP</a>";
                    echo "<ol>";
                        echo "<li><a href=\"#dest_utilisateur\">Utilisateur</a></li>";
                        echo "<li><a href=\"#dest_marches\">Marchés / Marchés Langue / Employé Marché</a></li>";
                        echo "<li><a href=\"#dest_articles\">Articles / Articles Langue</a></li>";
                        echo "<li><a href=\"#dest_activites_comm\">Activités commerciales / Activités commerciales Langue</a></li>";
                        echo "<li><a href=\"#dest_pieces\">Pièces justificatives / Pièces justificatives Langue</a></li>";
                        $menu_insert_abonnements = $insert_abonnements ? " / Abonnements" : "";
                        echo "<li><a href=\"#dest_exploitants\">Exploitants / Société Marché / Pièces justificatives Valeur".$menu_insert_abonnements."</a></li>";
                        if ($insert_presences) echo "<li><a href=\"#dest_presences\">Rassemblements / Présences</a></li>";
                        echo "<li><a href=\"#dest_compteurs\">Compteurs</a></li>";
                    echo "</ol>";
                echo "</li>";
                echo "<li><a href=\"$script_file_name\">Retour à l'accueil</a></li>";
            echo "</ol>";
            echo "</aside>";

            echo "<section>";

            echo "<summary id=\"summary\">";
                echo "<h1>$nom_reprise</h1>";
                echo "<p id=\"nb_errors\">Reprise en cours</p>";
                $compteur_insert_presences = $insert_presences ? "<th>Présences</th>" : "";
                echo "<table id=\"nb_content\"><tr><th>Marchés</th><th>Articles</th><th>Exploitants</th>$compteur_insert_presences</tr></table>";
                echo $test_mode ? "<div><a href=\"$script_file_name\">Retour à l'accueil</a></div>" : "<div><a target=\"_blank\" href=\"//$oracle_host/geodp.".substr($oracle_login, strlen("geodp"))."\">ares/geodp.".substr($oracle_login, strlen("geodp"))."</a></div>";
            echo "</summary>";

            $nb_errors = 0;
            $nb_warnings = 0;

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
                echo "<field><label>Reprendre les abonnements</label><input type=\"disabled\" value=\"".yes_no($insert_abonnements)."\" disabled /></field>";
                echo "<field><label>Reprendre les présences</label><input type=\"disabled\" value=\"".yes_no($insert_presences)."\" disabled /></field>";
                echo "<field><label>Afficher les requêtes à exécuter</label><input type=\"disabled\" value=\"".yes_no($display_dest_requests)."\" disabled /></field>";
                echo "<field><label>Exécuter les requêtes</label><input type=\"disabled\" value=\"".yes_no($exec_dest_requests)."\" disabled /></field>";
                echo "<field><label>Fichier de sortie</label><input type=\"disabled\" value=\"$output_filename\" disabled /></field>";
            echo "</form>";

            //// Chargement des fichiers sources dibtic
            
            echo "<h1 id=\"source\">Chargement des fichiers sources dibtic</h1>";

            if ($insert_abonnements && !isset($source_files["abonnements"])) {
                echo "<p class=\"danger\">Impossible de continuer car le fichier des abonnements est manquant</p>";
                return;
            }

            if ($insert_presences && !isset($source_files["présences"])) {
                echo "<p class=\"danger\">Impossible de continuer car le fichier des présences est manquant</p>";
                return;
            }
            
            // Pour chaque fichier source (pour chaque fichier attendu, il y a le chemin du fichier en $_GET), lecture + table + insertions
            
            foreach ($expected_content as $exp) {
                if (!isset($source_files[$exp]) && $mandatory_files[array_search($exp, $expected_content)]) {
                    echo "<p class=\"danger\">Impossible de continuer car le fichier des $exp est manquant</p>";
                    return;
                } else if (isset($source_files[$exp])) {
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

                    if ($exp === "exploitants") {
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
                        array_push($create_table_query_values, $col);
                    }

                    $create_table_query = "CREATE TABLE $table_name (`" . implode("` VARCHAR(250), `", $create_table_query_values) . "` VARCHAR(250))";
                    $src_conn->exec($create_table_query);

                    if ($exp === "articles" && !in_array("code_pda", $create_table_query_values)) {
                        echo "<p class=\"danger\">Impossible de continuer car la colonne 'code_pda' n'est pas présente dans le fichier " . $source_files[$exp] . "</p>";
                        return;
                    }

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
                            echo "<p class=\"danger\">".$src_conn->errorInfo()[2]."</p>";
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
                } // Fin "si le fichier est présent"
            }

            // Association du nom des tables avec le contenu de leur fichier
            $src_marche = $src_tables["marchés"];
            $src_article = $src_tables["articles"];
            $src_exploitant = $src_tables["exploitants"];
            $src_abonnement = $insert_abonnements ? $src_tables["abonnements"] : "";
            $src_presence = $insert_presences ? $src_tables["présences"] : "";

            // Récupération des colonnes des classes d'articles (elles commencent par "classe" et sont peut-être suivies par un entier)
            $src_exploitant_classe_cols = [];
            foreach ($src_conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$src_exploitant'") as $row) {
                $matches = [];
                preg_match('/^classe[0-9]*$/', $row["COLUMN_NAME"], $matches);
                if (isset($matches[0]) && !in_array($matches[0], $src_exploitant_classe_cols)) {
                    array_push($src_exploitant_classe_cols, $matches[0]);
                }
            }

            // Récupération des colonnes des marchés (elles commencent par "m" et sont peut-être suivies par un entier)
            $src_exploitant_marche_cols = [];
            foreach ($src_conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$src_exploitant'") as $row) {
                $matches = [];
                preg_match('/^m[0-9]*$/', $row["COLUMN_NAME"], $matches);
                if (isset($matches[0]) && !in_array($matches[0], $src_exploitant_marche_cols)) {
                    array_push($src_exploitant_marche_cols, $matches[0]);
                }
            }

            // Récupération des colonnes des pièces justificatives (elles commencent par "n" et sont peut-être suivies par un entier)
            $src_exploitant_piece_cols = [];
            foreach ($src_conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$src_exploitant'") as $row) {
                $matches = [];
                preg_match('/^n[0-9]*$/', $row["COLUMN_NAME"], $matches);
                if (isset($matches[0]) && !in_array($matches[0], $src_exploitant_piece_cols)) {
                    array_push($src_exploitant_piece_cols, $matches[0]);
                }
            }

            //// Vidage des tables de destination
            
            if ($erase_destination_tables) {
                echo "<h1 id=\"erase\">Vidage des tables de destination</h1>";

                echo "<h2 id=\"erase_presences\">Présences / Rassemblements<span><tt>$dest_presence</tt> / <tt>$dest_rassemblement</tt></span></h2>";
                
                $dest_conn->exec("DELETE FROM $dest_presence");
                $dest_conn->exec("DELETE FROM $dest_rassemblement");

                echo "<h2 id=\"erase_exploitants\">Abonnements / Pièces justificatives Valeur / Société Marché / Exploitants<span><tt>$dest_abonnement</tt> / <tt>$dest_piece_val</tt> / <tt>$dest_societe_marche</tt> / <tt>$dest_exploitant</tt></span></h2>";

                $dest_conn->exec("DELETE FROM $dest_abonnement");
                $dest_conn->exec("DELETE FROM $dest_article_facture_lang");
                $dest_conn->exec("DELETE FROM $dest_article_facture");
                $dest_conn->exec("DELETE FROM $dest_facture");

                $dest_conn->exec("DELETE FROM $dest_piece_val");
                $dest_conn->exec("DELETE FROM $dest_societe_marche");
                $dest_conn->exec("DELETE FROM $dest_exploitant");

                echo "<h2 id=\"erase_pieces\">Pièces justificatives Langue / Pièces justificatives<span><tt>$dest_piece_lang</tt> / <tt>$dest_piece</tt></span></h2>";

                $dest_conn->exec("DELETE FROM $dest_piece_lang");
                $dest_conn->exec("DELETE FROM $dest_piece");

                echo "<h2 id=\"erase_activites_comm\">Activités commerciales Langue / Activités commerciales<span><tt>$dest_activite_commerciale_lang</tt> / <tt>$dest_activite_commerciale</tt></span></h2>";

                $dest_conn->exec("DELETE FROM $dest_activite_commerciale_lang");
                $dest_conn->exec("DELETE FROM $dest_activite_commerciale");

                echo "<h2 id=\"erase_articles\">Articles Langue / Articles<span><tt>$dest_article_lang</tt> / <tt>$dest_article</tt></span></h2>";

                $dest_conn->exec("DELETE FROM $dest_article_lang");
                $dest_conn->exec("DELETE FROM $dest_article");

                echo "<h2 id=\"erase_marches\">Employé Marché / Marchés Langue / Marchés<span><tt>$dest_employe_marche</tt> / <tt>$dest_marche_lang</tt> / <tt>$dest_marche</tt></span></h2>";

                $dest_conn->exec("DELETE FROM $dest_employe_marche WHERE MAR_REF IS NOT NULL");
                $dest_conn->exec("DELETE FROM $dest_marche_lang");
                $dest_conn->exec("DELETE FROM $dest_marche");
            }

            //// Insertion des données GEODP

            echo "<h1 id=\"destination\">Insertion des données GEODP</h1>";
            fwrite($output_file, "\n---------------------------------------------\n-- Insertion des données GEODP\n--\n\n");
            
            $dest_marche_cols = ["MAR_REF", "UTI_REF", "ACT_REF", "MAR_JOUR", "MAR_CPTFACTURE", "MAR_VISIBLE", "MAR_CODE", "DCREAT", "UCREAT", "MAR_VALIDE"];
            $dest_marche_lang_cols = ["MAR_REF", "LAN_REF", "MAR_NOM", "DCREAT", "UCREAT"];
            $dest_employe_marche_cols = ["EMA_REF", "EMP_REF", "MAR_REF", "DCREAT", "UCREAT", "ACT_REF"];

            $dest_article_cols = ["ART_REF", "MAR_REF", "UTI_REF", "ART_CODE", "ART_PRIX_TTC", "ART_PRIX_HT", "ART_TAUX_TVA", "ART_ABO_PRIX_TTC", "ART_ABO_PRIX_HT", "ART_ABO_TAUX_TVA", "ART_COULEUR", "ART_ORDRE", "ART_COMMENTAIRE", "ART_VALIDE_DEPUIS", "ART_VALIDE_JUSQUA", "ART_VISIBLE", "DCREAT", "UCREAT"];
            $dest_article_lang_cols = ["ART_REF", "LAN_REF", "ART_NOM", "ART_UNITE", "DCREAT", "UCREAT"];
            
            $dest_activite_commerciale_cols = ["ACO_REF", "UTI_REF", "ACO_COULEUR", "DCREAT", "UCREAT"];
            $dest_activite_commerciale_lang_cols = ["ACO_REF", "LAN_REF", "ACO_NOM", "DCREAT", "UCREAT"];
            
            $dest_exploitant_cols = ["EXP_REF", "EXP_CODE", "UTI_REF", "GRA_REF", "LAN_REF", "ACO_REF", "EXP_NOM_PERS_PHYSIQUE", "EXP_PRENOM_PERS_PHYSIQUE", "EXP_RAISON_SOCIALE", "EXP_NOM", "EXP_VISIBLE", "EXP_VALIDE", "EXP_NRUE", "EXP_ADRESSE", "EXP_CP", "EXP_VILLE", "EXP_TELEPHONE", "EXP_PORTABLE", "EXP_FAX", "EXP_EMAIL", "DCREAT", "UCREAT"];
            
            $dest_societe_marche_cols = ["EXP_REF", "MAR_REF", "ACO_REF", "SMA_TITULAIRE", "SMA_ABONNE", "DCREAT", "UCREAT"];

            $dest_piece_cols = ["PROP_REF", "ACT_REF", "UTI_REF", "DCREAT", "UCREAT"];
            $dest_piece_lang_cols = ["PROP_REF", "LAN_REF", "PROP_NOM", "DCREAT", "UCREAT"];
            $dest_piece_valeur_cols = ["EXP_REF", "PROP_REF", "PROP_VALEUR", "PROP_DATE", "PROP_DATE_VALIDITE", "DCREAT", "UCREAT"];

            $dest_facture_cols = ["FAC_REF", "EXP_REF", "TFA_REF", "ACT_REF", "UTI_REF", "EC_REF", "FAC_NUM", "FAC_DATE", "FAC_SOMME_TTC", "FAC_SOMME_TVA", "FAC_SOMME_HT", "FAC_VALIDE", "FAC_VISIBLE", "DCREAT", "UCREAT"];
            $dest_article_facture_cols = ["ART_REF", "FAC_REF", "MAR_REF", "AFA_DATE", "AFA_QUANTITE", "AFA_PRIX_TTC", "AFA_PRIX_HT", "AFA_TAUX_TVA", "AFA_MULTIPLICATEUR", "DCREAT", "UCREAT", "AFA_BK_TOTAL_HT", "AFA_BK_TOTAL_TVA", "AFA_BK_TOTAL_TTC"];
            $dest_article_facture_lang_cols = ["FAC_REF", "ART_REF", "AFA_DATE", "LAN_REF", "AFA_LIBELLE", "DCREAT", "UCREAT"];
            $dest_abonnement_cols = ["EXP_REF", "ABO_DATE_DEB", "FAC_REF", "DCREAT", "UCREAT", "ABO_REF", "ABO_TYPE"];

            $dest_rassemblement_cols = ["RAS_REF", "MAR_REF", "RAS_DATE", "DCREAT", "UCREAT"];
            $dest_presence_cols = ["EXP_REF", "RAS_REF", "DCREAT", "UCREAT", "PRE_PRESENT"];

            $dest_compteur_cols = ["CPT_REF", "CPT_TABLE", "CPT_VAL", "DCREAT", "UCREAT"];

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

            $req_ec_ref = $dest_conn->query(build_query("SELECT EC_REF FROM $dest_exercice_comptable", "EC_ACTUEL = 1", "EC_REF DESC", "1"))->fetch();
            if ($req_ec_ref == null) {
                echo "<p class=\"danger\">Impossible de continuer car il n'y a pas d'exercice comptable en cours dans la table $dest_exercice_comptable</p>";
                return;
            }
            $ec_ref = $req_ec_ref["EC_REF"];

            // Utilisateur

            echo "<h2 id=\"dest_utilisateur\">Utilisateur<span><tt>$dest_utilisateur</tt></span></h2>";
            fwrite($output_file, "\n-- Utilisateur\n\n");

            $nb_to_update = 0;
            $nb_updated = 0;

            if ($display_dest_requests) echo "<div class=\"pre\">";
            $update_query = "UPDATE $dest_utilisateur SET UTI_NOM = '$client_name' WHERE UTI_REF = $uti_ref";
            execute_query($update_query, $nb_updated, $nb_to_update);
            if ($display_dest_requests) echo "</div>";

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

            $nb_marches = $dest_conn->query("SELECT COUNT(*) FROM $dest_marche")->fetch()[0];

            if ($display_dest_requests) echo "<div class=\"pre\">";
            foreach ($src_conn->query("SELECT * FROM $src_marche WHERE libelle != ''") as $row) {
                $verif_query = $dest_conn->query("SELECT COUNT(*) FROM $dest_marche WHERE MAR_CODE = '".$row["code"]."'")->fetch();
                if ($verif_query[0] !== "0") {
                    array_push($warnings, "Le marché " . $row["libelle"] . " n'est pas inséré pour la raison suivante :<br>Un marché portant le code " . $row["code"] . " est déjà présent dans la table $dest_marche");
                } else {
                    // Créer un marché pour chaque jour de la semaine où le marché est ouvert (il en résultera plusieurs lignes correspondant à un même marché mais associé à des jours différents)
                    // Si le marché est présent tous les jours, n'insérer qu'un seul jour et mettre à 'null' MAR_JOUR
                    // Si le marché est présent un seul jour, n'insérer qu'un seul jour et mettre à 'nom_du_jour' MAR_JOUR
                    // Si le marché n'est pésent aucun jour, considérer qu'il est présent tous les jours
                    $all_the_week = true;
                    $nb_jours = 0;
                    foreach ($usual_days as $mar_day) {
                        if ($row[$mar_day] === "0") {
                            $all_the_week = $all_the_week && false;
                        } else {
                            ++$nb_jours;
                        }
                    }
                    $all_the_week = ($nb_jours === 0) ? true : $all_the_week;

                    foreach ($usual_days as $mar_day) {
                        if ($all_the_week || $row[$mar_day] === "1") {
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

                            $complement_mar_nom = ($all_the_week || $nb_jours === 1) ? "" : " - ".ucfirst($mar_day);
                            $dest_marche_lang_values = [$last_mar_ref, 1, "'".addslashes(ucwords(strtolower($row["libelle"])))."$complement_mar_nom'", "'$dest_dcreat'", "'$dest_ucreat'"];

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

            $nb_marches = $dest_conn->query("SELECT COUNT(*) FROM $dest_marche")->fetch()[0] - $nb_marches;
            $mysql_conn->exec("UPDATE $reprise_table SET marches_dest = $nb_marches WHERE id = $reprise_id");

            // Articles / Articles Langue

            echo "<h2 id=\"dest_articles\">Articles / Articles Langue<span><tt>$dest_article</tt> / <tt>$dest_article_lang</tt></span></h2>";
            fwrite($output_file, "\n-- Articles / Articles Langue\n\n");

            $nb_to_insert = 0;
            $nb_inserted = 0;
            $warnings = [];
            
            $req_last_art_ref = $dest_conn->query(build_query("SELECT ART_REF FROM $dest_article", "", "ART_REF DESC", "1"))->fetch();
            $last_art_ref = ($req_last_art_ref == null) ? 0 : $req_last_art_ref["ART_REF"];

            $nb_articles = $dest_conn->query("SELECT COUNT(*) FROM $dest_article")->fetch()[0];

            if ($display_dest_requests) echo "<div class=\"pre\">";
            foreach ($src_conn->query("SELECT * FROM $src_article WHERE nom != ''") as $row) {
                $src_art_numc = $row["numc"];
                $src_art_code = $row["code"];
                $src_art_abo = ($row["abo"] === "") ? false : true;

                if ($src_art_abo && $row["abo"] !== "X") {
                    array_push($warnings, "La cellule de la colonne 'abo' pour l'article " . $row["nom"] . " est non vide et différente de 'X', l'article est quand même considéré comme un tarif d'abonnés");
                }

                // Ne pas insérer des articles qui sont dans le même groupe (numc) et qui portent le même nom :
                // - Soit c'est le même article avec un simple multiplicateur de quantité et de prix unitaire qui sont proportionnels
                // - Soit c'est un doublon qui complète la précédente insertion du même nom (un abonnement complète un passager, et inversement)
                // Sinon, insérer normalement l'article, et ce pour chaque marché de référence trouvé
                $verif_query = $dest_conn->query(build_query("SELECT $dest_article.* FROM $dest_article, $dest_article_lang", "$dest_article.ART_COMMENTAIRE LIKE '$src_art_numc-%' AND $dest_article_lang.ART_NOM = '".addslashes($row["nom"])."' AND $dest_article.ART_REF = $dest_article_lang.ART_REF", "", ""))->fetchAll();
                if (count($verif_query) > 0) {
                    // $verif_query peut retourner plus d'un résultat si le précédent article du même nom et du même groupe a été ajouté pour plusieurs marchés de référence
                    // Les articles retournés sont identiques au niveau des abonnements, de l'unité, ... Donc les tests sont effectués sur le premier de la liste
                    // Test pour savoir si les articles enregistrés sont complémentaires au niveau des prix par rapport à l'article doublon (prix abonnés complémentaires des prix passagers)
                    // Si le test échoue, alors l'article est un simple doublon et est ignoré
                    $old_miss_abo = $verif_query[0]["ART_ABO_PRIX_TTC"] === "0" && $verif_query[0]["ART_ABO_PRIX_HT"] === "0" && $verif_query[0]["ART_ABO_TAUX_TVA"] === "0";
                    $old_miss_non_abo = $verif_query[0]["ART_PRIX_TTC"] === "0" && $verif_query[0]["ART_PRIX_HT"] === "0" && $verif_query[0]["ART_TAUX_TVA"] === "0";

                    if ($old_miss_abo && $src_art_abo || $old_miss_non_abo && !$src_art_abo) {
                        // Le nouvel article est complémentaire du premier -> Update de chaque article

                        $warning_message = "Au moins un article (autant d'articles du même nom que de marchés associés à ces articles) appelé " . $row["nom"] . " est déjà présent dans la table $dest_marche pour le groupe $src_art_numc mais le nouvel article est complémentaire au niveau des tarifs, ils sont donc fusionnés";
                        if ($old_miss_abo && $src_art_abo) $warning_message .= " (les articles du même nom et du même groupe n'ont pas de tarif d'abonnés et le nouveau est un tarif d'abonnés)";
                        if ($old_miss_non_abo && !$src_art_abo) $warning_message .= " (les articles du même nom et du même groupe n'ont pas de tarif de passagers et le nouveau est un tarif de passagers)";
                        array_push($warnings, $warning_message);

                        foreach ($verif_query as $inserted_article) {
                            $art_ref = $inserted_article["ART_REF"];
                            $prix_ttc = ($row["tva"] === "") ? $row["prix_unit"] : 0;
                            $prix_ht = ($row["tva"] !== "") ? $row["prix_unit"] : 0;
                            $taux_tva = ($row["tva"] !== "") ? $row["tva"] : 0;
                            $update_query = "";

                            if ($old_miss_abo && $src_art_abo) {
                                $update_query = "UPDATE $dest_article SET ART_ABO_PRIX_TTC = $prix_ttc, ART_ABO_PRIX_HT = $prix_ht, ART_ABO_TAUX_TVA = $taux_tva WHERE ART_REF = $art_ref";
                            }
                            if ($old_miss_non_abo && !$src_art_abo) {
                                $update_query = "UPDATE $dest_article SET ART_PRIX_TTC = $prix_ttc, ART_PRIX_HT = $prix_ht, ART_TAUX_TVA = $taux_tva WHERE ART_REF = $art_ref";
                            }

                            if ($update_query !== "") execute_query($update_query, $nb_inserted, $nb_to_insert);
                        }
                    } else {
                        // Doublon -> Ne rien faire
                        array_push($warnings, "Au moins un article (autant d'articles du même nom que de marchés associés à ces articles) appelé " . $row["nom"] . " est déjà présent dans la table $dest_marche pour le groupe $src_art_numc, il n'est donc pas inséré");
                    }
                } else {
                    $dest_marches_ref = [];
                    // Pour obtenir les marchés de référence de cet article (une insertion de cet article pour chaque marché de ref trouvé, il en résultera plusieurs lignes correspondant à un même article mais associé à des marchés différents) :
                    //   Regarder la colonne `marches` si elle existe et si elle est non vide, sinon :
                    //     Regarder le `groupe` des marchés identique au `numc` de l'article
                    //     Considérer comme marchés de référence tous les marchés ayant pour groupe le `numc` de l'article
                    if (isset($row["marches"]) && $row["marches"] !== "") {
                        // Si cette colonne existe dans le fichier des articles, elle contient les codes des marchés à prendre en compte plutôt que les marchés du groupe `numc` de l'article
                        // Les codes sont normalement séparés par une virgule
                        $str_marches_codes = str_replace(" ", "", $row["marches"]);
                        $marches_codes = explode(",", $str_marches_codes);
                        // Il y a potentiellement plusieurs marché qui ont le même code (un marché est ouvert x jours -> x marchés insérés, qui ont tous les même code de marché)
                        // Il faut donc récupérer dans la table de destination des marchés toutes les MAR_REF où il y a ce code et les ajouter au tableau pour que l'article soit associé à tous les marchés portant ce code
                        foreach ($marches_codes as $marche_code) {
                            foreach ($dest_conn->query(build_query("SELECT MAR_REF FROM $dest_marche", "MAR_CODE = '$marche_code'", "", "")) as $m2) {
                                $marche_ref = $m2["MAR_REF"];
                                array_push($dest_marches_ref, $marche_ref);
                            }
                        }
                        if (count($dest_marches_ref) === 0) {
                            array_push($warnings, "Le(s) codes de marché(s) associé(s) à l'article " . $row["nom"] . " n'existe(nt) pas ($str_marches_codes), il n'est donc pas inséré");
                        }
                    } else {
                        foreach ($src_conn->query("SELECT code FROM $src_marche WHERE groupe = '$src_art_numc'") as $m1) {
                            $marche_code = $m1["code"];
                            // Il y a potentiellement plusieurs marché qui ont le même code (un marché est ouvert x jours -> x marchés insérés, qui ont tous les même code de marché)
                            // Il faut donc récupérer dans la table de destination des marchés tous les MAR_REF où il y a ce code et les ajouter au tableau pour que l'article soit associé à tous les marchés portant ce code
                            foreach ($dest_conn->query(build_query("SELECT MAR_REF FROM $dest_marche", "MAR_CODE = '$marche_code'", "", "")) as $m2) {
                                $marche_ref = $m2["MAR_REF"];
                                array_push($dest_marches_ref, $marche_ref);
                            }
                        }
                        if (count($dest_marches_ref) === 0) {
                            array_push($warnings, "L'article " . $row["nom"] . " a un numc qui ne correspond à aucun groupe de marché ($src_art_numc), il n'est donc pas inséré");
                        }
                    }

                    if (count($dest_marches_ref) !== 0) {
                        // $date_debut = $test_mode ? date("y/01/01", time()) : date("01/01/y", time()); // 1 janvier de cette année
                        $date_debut = string_to_date(date("01/01/y", time()), false); // 1 janvier de cette année
                        if (!isset($row["date_debut"]) || string_to_date($row["date_debut"], true) === NULL) {
                            // array_push($warnings, "La colonne 'date_debut' n'existe pas ou la date n'est pas au format dd/yy/aaaa dans le fichier des articles " . $source_files["articles"] . " pour l'article " . $row["nom"] . ", la date de début de validité de l'article sera donc mise au 1er janvier de cette année");
                        } else {
                            $date_debut = string_to_date($row["date_debut"], true);
                        }
                        
                        // $date_fin = $test_mode ? date("y/12/31", time()) : date("31/12/y", time()); // 31 décembre de cette année
                        $date_fin = string_to_date(date("31/12/y", time()), false); // 31 décembre de cette année
                        if (!isset($row["date_fin"]) || string_to_date($row["date_fin"], true) === NULL) {
                            // array_push($warnings, "La colonne 'date_fin' n'existe pas ou la date n'est pas au format dd/yy/aaaa dans le fichier des articles " . $source_files["articles"] . " pour l'article " . $row["nom"] . ", la date de fin de validité de l'article sera donc mise au 31 décembre de cette année");
                        } else {
                            $date_fin = string_to_date($row["date_fin"], true);
                        }

                        $dest_art_code = $row["code_pda"]; // sera NULL si l'article est un tarif d'abonnés
                        if (!$src_art_abo && strlen($dest_art_code) > 6) {
                            array_push($warnings, "Le code de l'article " . $row["nom"] . ", $dest_art_code, est supérieur à 6 caractères");
                        }
                        if (!$src_art_abo && strlen($dest_art_code) === "") {
                            array_push($warnings, "Le code de l'article " . $row["nom"] . " est vide");
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
                                        if ($src_art_abo) {
                                            array_push($dest_article_values, "NULL");
                                        } else {
                                            array_push($dest_article_values, "'$dest_art_code'");
                                        }
                                        break;
                                    case "ART_PRIX_TTC":
                                        if (!$src_art_abo && $row["tva"] === "") {
                                            array_push($dest_article_values, str_replace(' ', '', $row["prix_unit"]));
                                        } else {
                                            array_push($dest_article_values, "0");
                                        }
                                        break;
                                    case "ART_PRIX_HT":
                                        if (!$src_art_abo && $row["tva"] !== "") {
                                            array_push($dest_article_values, str_replace(' ', '', $row["prix_unit"]));
                                        } else {
                                            array_push($dest_article_values, "0");
                                        }
                                        break;
                                    case "ART_TAUX_TVA":
                                        if (!$src_art_abo && $row["tva"] !== "") {
                                            array_push($dest_article_values, str_replace(' ', '', $row["tva"]));
                                        } else {
                                            array_push($dest_article_values, "0");
                                        }
                                        break;
                                    case "ART_ABO_PRIX_TTC":
                                        if ($src_art_abo && $row["tva"] === "") {
                                            array_push($dest_article_values, str_replace(' ', '', $row["prix_unit"]));
                                        } else {
                                            array_push($dest_article_values, "0");
                                        }
                                        break;
                                    case "ART_ABO_PRIX_HT":
                                        if ($src_art_abo && $row["tva"] !== "") {
                                            array_push($dest_article_values, str_replace(' ', '', $row["prix_unit"]));
                                        } else {
                                            array_push($dest_article_values, "0");
                                        }
                                        break;
                                    case "ART_ABO_TAUX_TVA":
                                        if ($src_art_abo && $row["tva"] !== "") {
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
                                    case "ART_COMMENTAIRE":
                                        $dest_numc_code = "$src_art_numc-$src_art_code";
                                        array_push($dest_article_values, "'$dest_numc_code'");
                                        break;
                                    case "ART_VALIDE_DEPUIS":
                                        array_push($dest_article_values, "'$date_debut'");
                                        break;
                                    case "ART_VALIDE_JUSQUA":
                                        array_push($dest_article_values, "'$date_fin'");
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
                    } // Fin "si l'article n'a pas de marché de référence"
                } // Fin "si un article du même nom n'existe pas déjà"
            }
            if ($display_dest_requests) echo "</div>";

            summarize_queries($nb_inserted, $nb_to_insert, $nb_errors, $warnings, $nb_warnings);

            $nb_articles = $dest_conn->query("SELECT COUNT(*) FROM $dest_article")->fetch()[0] - $nb_articles;
            $mysql_conn->exec("UPDATE $reprise_table SET articles_dest = $nb_articles WHERE id = $reprise_id");

            // Activités commerciales

            echo "<h2 id=\"dest_activites_comm\">Activités commerciales / Activités commerciales Langue<span><tt>$dest_activite_commerciale</tt> / <tt>$dest_activite_commerciale_lang</tt></span></h2>";
            fwrite($output_file, "\n-- Activités commerciales / Activités commerciales Langue\n\n");

            $nb_to_insert = 0;
            $nb_inserted = 0;

            $default_activitescomm = ["VENDEUR MATELAS", "VENDEUR DE NAPPES", "POTERIE", "VENTE OEUFS FROMAGE CHEVRE", "PRET A PORTER", "PRODUIT D'INDE", "VENTE FROMAGES FRAIS-SALAISONS", "VENDEUR CHAUSSURES", "VENTE CHAUSSONS EN CUIR", "TRAITEUR", "VENDEUR BIJOUX", "ROTISSEUR", "VENTE DE BOXERS", "VENTE POUPEES EN TISSUS", "TRAITEUR ASIATIQUE", "VENDEUR MIEL", "VENDEUR SERVIETTES PLAGES", "VENTE PRODUITS REGIONAUX", "VENTE PAIN/PATISSERIE/", "VENDEUR HUILE/OLIVE", "VENTE AIL ECHALOTTE", "VENTE TABLEAUX EN VELOURS", "VENDEUR DE SAUCISSON", "VENTE MONTRE H/F/E", "FRUITS-LEGUMES", "POISSONNERIE", "FRUITS DESHYDRATES/OLIVES", "MAROQUINERIE", "COSMETIQUES", "PLATS CUISINES", "VENDEUR SUSHI", "CERAMIQUE", "BOUCHER", "VENTES USTENSILES CUISINE", "LUNETTES", "VENDEUR FIGUES-ABRICOT", "VENDEUR CASSETTES", "FLEURISTE", "VENDEUR PRODUITS ENTRETIEN", "CHAPELLERIE", "DEMONSTRATEURS", "CHAPEAUX ET GAVROCHES", "VETEMENTS EN COTON", "PRODUCTEUR OEUFS", "PRODUCTEUR FRUITS-LEGUMES", "PRODUCTEUR FROMAGE", "VETEMENTS GRANDE TAILLE", "BOULANGERIE-PATISSERIE", "PRODUCTEUR VINS", "CHARCUTERIE", "SAVON ARTISANAL", "CREATEUR DE BIJOUX", "PRODUCTEUR OEUFS FROMAGE", "VENDEUR OLIVE-ANCHOIS-TAPENADE", "FABRICATION BRACELET CUIR", "VENDEUR LIVRES", "VENDEUR EPICES-PLANTES AROMATI", "BIJOUX EN SILICONE", "VENDEUR DE SACS", "THEATRE DE GUIGNOL", "CIRQUE", "PLATS THAILANDAIS", "BONBON-NOUGATS-PRALINES", "THE-BANANES SECHEES", "VENTE DE PRODUITS POUR ANIMAUX", "VENTE DE FRIANDS", "VENTE DE MELONS", "PANCAKES"];
            
            $req_last_aco_ref = $dest_conn->query(build_query("SELECT ACO_REF FROM $dest_activite_commerciale", "", "ACO_REF DESC", "1"))->fetch();
            $last_aco_ref = ($req_last_aco_ref == null) ? 0 : $req_last_aco_ref["ACO_REF"];

            if ($display_dest_requests) echo "<div class=\"pre\">";

            // Activités commerciales par défaut de dibtic
            foreach ($default_activitescomm as $aco_nom) {
                $aco_nom = addslashes(strtoupper($aco_nom));
                $req_aco = $dest_conn->query(build_query("SELECT COUNT(*) FROM $dest_activite_commerciale_lang", "ACO_NOM = '$aco_nom'", "", ""))->fetch();
                if ($req_aco[0] === "0") {
                    $last_aco_ref += 1;

                    // Activité commerciale
                    $dest_activite_commerciale_values = [$last_aco_ref, $uti_ref, "'fff'", "'$dest_dcreat'", "'$dest_ucreat'"];
                    
                    $insert_into_query = "INSERT INTO $dest_activite_commerciale (" . implode(", ", $dest_activite_commerciale_cols) . ") VALUES (" . implode(", ", $dest_activite_commerciale_values) . ")";
                    execute_query($insert_into_query, $nb_inserted, $nb_to_insert);

                    // Activité commerciale Langue

                    $dest_activite_commerciale_lang_values = [$last_aco_ref, 1, "'$aco_nom'", "'$dest_dcreat'", "'$dest_ucreat'"];

                    $insert_into_query = "INSERT INTO $dest_activite_commerciale_lang (" . implode(", ", $dest_activite_commerciale_lang_cols) . ") VALUES (" . implode(", ", $dest_activite_commerciale_lang_values) . ")";
                    execute_query($insert_into_query, $nb_inserted, $nb_to_insert);
                }
            }

            // Activités commerciales des exploitants
            foreach ($src_conn->query("SELECT type FROM $src_exploitant WHERE type != ''") as $row) {
                // Parmi les exploitants qui ont une activité renseignée, l'ajouter à la table des activités si elle n'y est pas déjà
                $req_aco = $dest_conn->query(build_query("SELECT COUNT(*) FROM $dest_activite_commerciale_lang", "ACO_NOM = '".addslashes(strtoupper($row["type"]))."'", "", ""))->fetch();
                if ($req_aco[0] === "0") {
                    $last_aco_ref += 1;

                    $dest_activite_commerciale_values = [];
                    
                    foreach ($dest_activite_commerciale_cols as $col) {
                        switch ($col) {
                            case "ACO_REF":
                                array_push($dest_activite_commerciale_values, "$last_aco_ref");
                                break;
                            case "UTI_REF":
                                array_push($dest_activite_commerciale_values, "$uti_ref");
                                break;
                            case "ACO_COULEUR":
                                array_push($dest_activite_commerciale_values, "'fff'");
                                break;
                            case "DCREAT":
                                array_push($dest_activite_commerciale_values, "'$dest_dcreat'");
                                break;
                            case "UCREAT":
                                array_push($dest_activite_commerciale_values, "'$dest_ucreat'");
                                break;
                            default:
                                array_push($dest_activite_commerciale_values, "'TODO'");
                                break;
                        }
                    }

                    $insert_into_query = "INSERT INTO $dest_activite_commerciale (" . implode(", ", $dest_activite_commerciale_cols) . ") VALUES (" . implode(", ", $dest_activite_commerciale_values) . ")";
                    execute_query($insert_into_query, $nb_inserted, $nb_to_insert);

                    // Activité commerciale Langue

                    $dest_activite_commerciale_lang_values = [$last_aco_ref, 1, "'".addslashes($row["type"])."'", "'$dest_dcreat'", "'$dest_ucreat'"];

                    $insert_into_query = "INSERT INTO $dest_activite_commerciale_lang (" . implode(", ", $dest_activite_commerciale_lang_cols) . ") VALUES (" . implode(", ", $dest_activite_commerciale_lang_values) . ")";
                    execute_query($insert_into_query, $nb_inserted, $nb_to_insert);
                } // Fin "si l'activité n'existe pas déjà"
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

            // Exploitants / Société Marché / Pièces justificatives Valeur / Abonnements

            $menu_insert_abonnements = $insert_abonnements ? " / Abonnements" : "";
            $menu_table_abonnements = $insert_abonnements ? " / <tt>$dest_abonnement</tt>" : "";
            echo "<h2 id=\"dest_exploitants\">Exploitants / Société Marché / Pièces justificatives Valeur".$menu_insert_abonnements."<span><tt>$dest_exploitant</tt> / <tt>$dest_societe_marche</tt> / <tt>$dest_piece_val</tt>".$menu_table_abonnements."</span></h2>";
            fwrite($output_file, "\n-- Exploitants / Société Marché / Pièces justificatives Valeur".$menu_insert_abonnements."\n\n");

            $nb_to_insert = 0;
            $nb_inserted = 0;
            $warnings = [];

            $req_last_exp_ref = $dest_conn->query(build_query("SELECT EXP_REF FROM $dest_exploitant", "", "EXP_REF DESC", "1"))->fetch();
            $last_exp_ref = ($req_last_exp_ref == null) ? 0 : $req_last_exp_ref["EXP_REF"];

            $nb_exploitants = $dest_conn->query("SELECT COUNT(*) FROM $dest_exploitant")->fetch()[0];

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
                                $req_aco_ref = $dest_conn->query(build_query("SELECT ACO_REF FROM $dest_activite_commerciale_lang", "ACO_NOM = '".addslashes(strtoupper($row["type"]))."'", "", ""))->fetch();
                                $aco_ref = ($req_aco_ref == null) ? NULL : $req_aco_ref["ACO_REF"];
                                if ($aco_ref === NULL) {
                                    array_push($warnings, "L'activité " . addslashes(strtoupper($row["type"])) . " de l'exploitant " . $row["nom_deb"] . " n'existe pas dans la table $dest_activite_commerciale, l'exploitant est donc considéré sans activité");
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

                // Liens avec les marchés (société marché, abonnements aux marchés)

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

                    $header_err_message = "L'abonnement de l'exploitant " . $row["nom_deb"] . " au marché $marche_code n'est pas inséré pour la raison suivante :<br>";

                    if ($marche_code !== "") {
                        $req_groupe_marche = $src_conn->query("SELECT groupe FROM $src_marche WHERE code = '$marche_code'")->fetch();

                        if ($req_groupe_marche == null) {
                            array_push($warnings, $header_err_message . "Ce marché n'existe pas dans le fichier des marchés " . $source_files["marchés"]);
                        } else {
                            $groupe_marche = $req_groupe_marche["groupe"];
                            // Continuer ssi les groupes du marché correspondent
                            if (isset($row["groupe$num_groupe"]) && $row["groupe$num_groupe"] === $groupe_marche) {
                                if (isset($row["abo$num_abo"])) {
                                    $prefixes_days = ["l", "ma", "me", "je", "v", "s", "d"];
                                    $type_abo = $row["abo$num_abo"];

                                    $sma_titulaire = 0;
                                    $sma_abonne = 0;

                                    switch ($type_abo) {
                                        case "0":
                                            break;
                                        case "1":
                                            $sma_abonne = 1;
                                            break;
                                        case "2":
                                            $sma_titulaire = 1;
                                            break;
                                        case "3":
                                            // TODO Voir à l'usage, pour l'instant 0 aux deux SMA
                                            break;
                                        default:
                                            array_push($warnings, "L'exploitant " . $row["nom_deb"] . " a un type d'abonnement inconnu au marché $marche_code, il faut trouver une équivalence dibtic - GEODP v1 pour le type $type_abo");
                                            break;
                                    }

                                    // Si l'exploitant est abonné à ce marché, regarder les jours où il y est abonné, et insérer un abonnement en conséquence
                                    // 2.1 - Le code "0" veut quand même dire que l'exploitant est associé au marché
                                    // if ($type_abo !== "0") {
                                        // Pour chaque colonne de jour 'l`i`/ma`i`/...', regarder si la cellule contient un 1 (abonnement) ou un 0 (non abonnement) au marché de la colonne 'm`i`'
                                        // Récupérer le MAR_REF du marché correspondant et insérer l'abonnement pour ce marché (un abo pour un marché, un marché étant pour un code et jour)
                                        $at_least_one_day = false;
                                        foreach ($prefixes_days as $prefix_day) {
                                            if ($row["$prefix_day$num_day"] === "1") {
                                                $at_least_one_day = true;

                                                $day = $usual_days[array_search($prefix_day, $prefixes_days)];

                                                $req_mar_ref = $dest_conn->query(build_query("SELECT MAR_REF FROM $dest_marche", "MAR_CODE = '$marche_code' AND MAR_JOUR = '$day' OR MAR_CODE = '$marche_code' AND MAR_JOUR IS NULL", "", ""))->fetch();

                                                if ($req_mar_ref == null) {
                                                    array_push($warnings, "L'abonnement de l'exploitant " . $row["nom_deb"] . " au marché $marche_code ouvert le $day n'est pas inséré pour la raison suivante :<br>Le marché $marche_code n'ouvre pas le $day d'après le fichier des marchés " . $source_files["exploitants"]);
                                                } else {
                                                    $mar_ref = $req_mar_ref["MAR_REF"];

                                                    $dest_societe_marche_values = [$last_exp_ref, $mar_ref, $aco_ref, $sma_titulaire, $sma_abonne, "'$dest_dcreat'", "'$dest_ucreat'"];

                                                    $verif_query = $dest_conn->query("SELECT COUNT(*) FROM $dest_societe_marche WHERE EXP_REF = $last_exp_ref AND MAR_REF = $mar_ref")->fetch();
                                                    if ($verif_query[0] !== "0") {
                                                        // array_push($warnings, "L'abonnement de l'exploitant " . $row["nom_deb"] . " au marché $marche_code ouvert le $day n'est pas inséré pour la raison suivante :<br>Cet abonnement est déjà présent dans la table $dest_societe_marche");
                                                    } else {
                                                        $insert_into_query = "INSERT INTO $dest_societe_marche (" . implode(", ", $dest_societe_marche_cols) . ") VALUES (" . implode(", ", $dest_societe_marche_values) . ")";
                                                        execute_query($insert_into_query, $nb_inserted, $nb_to_insert);
                                                    }
                                                }
                                            } // Fin "si l'exploitant est abonné"
                                        }
                                    // } // Fin "si l'exploitant a un abonnement différent de 0"
                                    
                                    // L'exploitant est associé à un marché (en tant qu'abonné, titulaire, ...). Dans GEODP, un marché est défini par son code ET son jour d'ouverture.
                                    // Pour insérer un société_marché, qui est dépendant d'un MAR_REF, il faut donc récupérer dans le fichier source des epploitants le(s) jour(s) où l'exploitant est associé au marché.
                                    // Cependant, dibtic permet une utilisation déviée : un exploitant peut être associé à un marché sans préciser de jours d'abonnement.
                                    // 2.1 - Si l'exploitant est associé à un marché ($marche_code !== "") mais qu'aucun jour d'association n'est précisé, ajouter l'exploitant à tous les jours de ce marché (donc à tous les marchés portant le code $marche_code) (qui peut le plus peut le moins)
                                    if (!$at_least_one_day) {
                                        $req_marches = $dest_conn->query(build_query("SELECT MAR_REF FROM $dest_marche", "MAR_CODE = '$marche_code'", "", ""));
                                        foreach ($req_marches as $marche) {
                                            $mar_ref = $marche["MAR_REF"];

                                            $dest_societe_marche_values = [$last_exp_ref, $mar_ref, $aco_ref, $sma_titulaire, $sma_abonne, "'$dest_dcreat'", "'$dest_ucreat'"];

                                            $verif_query = $dest_conn->query("SELECT COUNT(*) FROM $dest_societe_marche WHERE EXP_REF = $last_exp_ref AND MAR_REF = $mar_ref")->fetch();
                                            if ($verif_query[0] !== "0") {
                                                // array_push($warnings, "L'abonnement de l'exploitant " . $row["nom_deb"] . " au marché $marche_code ouvert le $day n'est pas inséré pour la raison suivante :<br>Cet abonnement est déjà présent dans la table $dest_societe_marche");
                                            } else {
                                                $insert_into_query = "INSERT INTO $dest_societe_marche (" . implode(", ", $dest_societe_marche_cols) . ") VALUES (" . implode(", ", $dest_societe_marche_values) . ")";
                                                execute_query($insert_into_query, $nb_inserted, $nb_to_insert);
                                            }
                                        }
                                    } // Fin "si l'exploitant est associé à un marché mais à aucun jour"
                                } else {
                                    array_push($warnings, $header_err_message . "La colonne 'abo$num_abo' n'existe pas dans le fichier des exploitants " . $source_files["exploitants"]);
                                }
                            } else {
                                if (!isset($row["groupe$num_groupe"])) array_push($warnings, $header_err_message . "La colonne 'groupe$num_groupe' n'existe pas alors que la colonne '$src_exploitant_marche_col' existe dans le fichier des exploitants " . $source_files["exploitants"]);
                                if (isset($row["groupe$num_groupe"]) && $row["groupe$num_groupe"] !== $groupe_marche) array_push($warnings, $header_err_message . "Pour le marché $marche_code, le groupe de l'exploitant (" . $row["groupe$num_groupe"] . ") ne correspond pas au groupe du marché ($groupe_marche) dans le fichier des exploitants " . $source_files["exploitants"]);
                            }
                        } // Fin "si le marché n'existe pas"
                    }
                } // Fin "liens marchés exploitant"
                
                // Pièces justificatives Valeur

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
                            $dest_piece_val_values = [$last_exp_ref, $piece_ref, addslashes_nullify($piece_val), addslashes_nullify($piece_date), addslashes_nullify($piece_date_validite), "'$dest_dcreat'", "'$dest_ucreat'"];
                            
                            $insert_into_query = "INSERT INTO $dest_piece_val (" . implode(", ", $dest_piece_valeur_cols) . ") VALUES (" . implode(", ", $dest_piece_val_values) . ")";
                            execute_query($insert_into_query, $nb_inserted, $nb_to_insert);
                        }
                    } // Fin "si $piece_val pas vide"
                } // Fin "pièces justificatives valeur de l'exploitant"

                // Abonnements

                if ($insert_abonnements) {
                    // Facture

                    $req_last_fac_ref = $dest_conn->query(build_query("SELECT FAC_REF FROM $dest_facture", "", "FAC_REF DESC", "1"))->fetch();
                    $last_fac_ref = ($req_last_fac_ref == null) ? 0 : $req_last_fac_ref["FAC_REF"];

                    $req_last_abo_ref = $dest_conn->query(build_query("SELECT ABO_REF FROM $dest_abonnement", "", "ABO_REF DESC", "1"))->fetch();
                    $last_abo_ref = ($req_last_abo_ref == null) ? 0 : $req_last_abo_ref["ABO_REF"];

                    $last_fac_ref += 1;
                    $fac_num = date("Y-m-d", strtotime($dest_dcreat)) . "-$last_fac_ref";

                    $dest_facture_values = [$last_fac_ref, $last_exp_ref, 5, $act_ref, $uti_ref, $ec_ref, "'$fac_num'", "'$dest_dcreat'", 0, 0, 0, 1, 1, "'$dest_dcreat'", "'$dest_ucreat'"];

                    $insert_into_query = "INSERT INTO $dest_facture (" . implode(", ", $dest_facture_cols) . ") VALUES (" . implode(", ", $dest_facture_values) . ")";
                    execute_query($insert_into_query, $nb_inserted, $nb_to_insert);

                    // Liens avec les articles (abonnements - uniquement les articles de type tarif abonné)

                    $fac_somme_ttc = 0;
                    $fac_somme_ht = 0;
                    $art_facture_added_to_facture = 0;

                    foreach ($src_exploitant_classe_cols as $src_exploitant_classe_col) {
                        // Associer les exploitants avec leurs articles : prendre les articles du fichier des exploitants, trouver l'ART_REF correspondant et insérer une facture, un article facture puis un abonnement
                        // Problème : les articles utilisés dans le fichier des exploitants (numc et code) ne sont pas forcément dans la table de destination des articles (le commentaire des articles contient le numc et le code) :
                        //  - L'article peut avoir été supprimé du fichier source (donnée perdue)
                        //  - Le numc des articles peut avoir été changé pour correspondre à un marché en particulier (donnée perdue)
                        //  - Les marchés de l'article peuvent avoir été supprimés (osef de ces articles)
                        //  - L'article peut ne pas avoir été inséré en cas de doublon ou de fusion avec un autre article possédant le même nom et le même numc (donnée retrouvable avec le nom)
                        // Solution : prendre le numc et le code via le fichier des exploitants => trouver le nom de l'article dans src_article => rechercher l'article via son nom et son numc dans dest_article => prendre son ART_REF
                        // On pourra alors retrouver les articles s'ils n'ont pas été supprimés ou modifiés dans le fichier source

                        $matches = [];
                        preg_match('/[0-9]+/', $src_exploitant_classe_col, $matches);
                        $num_groupe = isset($matches[0]) ? $matches[0] : ""; // pour les groupes, la première colonne s'appelle juste 'groupe', comme pour les classes

                        $src_art_numc = $row["groupe$num_groupe"];
                        $src_art_code = $row[$src_exploitant_classe_col];

                        $header_err_message = "L'abonnement de l'exploitant " . $row["nom_deb"] . " à l'article $src_art_code n'est pas inséré pour la raison suivante :<br>";

                        if (isset($row["groupe$num_groupe"])) {
                            if ($src_art_numc && $src_art_code) {
                                $req_article = $src_conn->query("SELECT nom FROM $src_article WHERE numc = $src_art_numc AND code = $src_art_code")->fetch();
                                if (!$req_article) {
                                    array_push($warnings, $header_err_message . "L'article '$src_art_numc-$src_art_code' n'existe pas dans le fichier des articles " . $source_files["articles"]);
                                } else {
                                    $art_nom = $req_article["nom"];
                                    $art_ref = $dest_conn->query("SELECT ART_REF FROM $dest_article_lang WHERE ART_NOM = '$art_nom'")->fetch()[0];

                                    $header_err_message = "L'abonnement de l'exploitant " . $row["nom_deb"] . " à l'article $art_nom n'est pas inséré pour la raison suivante :<br>";

                                    $matches = [];
                                    preg_match('/[0-9]+/', $src_exploitant_classe_col, $matches);
                                    $num_mar = isset($matches[0]) ? $matches[0] : ""; // pour les abonnements, la première colonne s'appelle juste 'm', comme pour les classes
                                    $num_abo = isset($matches[0]) ? $matches[0] : "1"; // pour les abonnements, la première colonne s'appelle 'abo1'
                                    $num_day = isset($matches[0]) ? $matches[0] : "1"; // pour les jours, la première colonne s'appelle '`prefixe_du_jour`1'
                                    $num_qt = isset($matches[0]) ? $matches[0] : ""; // pour les quantités, la première colonne s'appelle juste 'metr'
                                    $num_mult = isset($matches[0]) ? $matches[0] : ""; // pour les multiplicateurs, la première colonne s'appelle juste 'expo'

                                    $mar_code = $row["m$num_mar"];
                                    $req_groupe_marche = $src_conn->query("SELECT groupe FROM $src_marche WHERE code = '$mar_code'")->fetch();
                                    $art_abo = $row["abo$num_abo"];
                                    
                                    // Insérer la suite si et seulement si l'article est pour les abonnées (1) et dans le même groupe que le marché qui lui est associé via l'exploitant
                                    if ($art_abo === "1" && $req_groupe_marche !== null && $req_groupe_marche["groupe"] === $src_art_numc) {
                                        // Un article par marché, un marché étant défini par son code et son jour (autant de marché que de jours où il ouvre s'il n'ouvre pas toute la semaine)
                                        // Pour chaque colonne de jour 'l`i`/ma`i`/...', regarder si la cellule contient un 1 (abonnement) ou un 0 (non abonnement) au marché de la colonne 'm`i`'
                                        // Récupérer le MAR_REF du marché correspondant et insérer l'abonnement pour ce marché (un abo pour un marché, un marché étant pour un code et jour)    
                                        $prefixes_days = ["l", "ma", "me", "je", "v", "s", "d"];
                                        $mar_ref_done = []; // Contrôler les MAR_REF déjà faits, car un même marché peut revenir plusieurs fois pour des jours d'ouverture différents s'il ouvre toute la semaine (MAR_JOUR IS NULL)

                                        foreach ($prefixes_days as $prefix_day) {
                                            if ($row["$prefix_day$num_day"] === "1") {
                                                $day = $usual_days[array_search($prefix_day, $prefixes_days)];
                                                $req_mar_ref = $dest_conn->query(build_query("SELECT MAR_REF FROM $dest_marche", "MAR_CODE = '$mar_code' AND MAR_JOUR = '$day' OR MAR_CODE = '$mar_code' AND MAR_JOUR IS NULL", "", ""))->fetch()[0];

                                                if ($req_mar_ref !== null && !in_array($mar_ref, $mar_ref_done)) {
                                                    $mar_ref = $req_mar_ref[0];
                                                    array_push($mar_ref_done, $mar_ref);

                                                    $afa_quantite = $row["metr$num_qt"];
                                                    $afa_multiplicateur = $row["expo$num_mult"];

                                                    $afa_quantite = floatval(str_replace(",", ".", $afa_quantite));
                                                    $afa_multiplicateur = floatval(str_replace(",", ".", $afa_multiplicateur));

                                                    // La quantité peut être égale à 0 lorsque l'abonnement a été supprimé, ne rien faire dans ce cas
                                                    if ($afa_quantite !== 0) {
                                                        $art_nom = $dest_conn->query(build_query("SELECT ART_NOM FROM $dest_article_lang", "ART_REF = '$art_ref'", "", ""))->fetch()[0];
                                                        $art_ttc_abo = $dest_conn->query("SELECT ART_ABO_PRIX_TTC FROM $dest_article WHERE ART_REF = '$art_ref'")->fetch()[0];
                                                      
                                                        $art_ttc_abo = floatval(str_replace(",", ".", $art_ttc_abo));

                                                        // Insérer la suite si et seulement si le tarif est un tarif d'abonnés
                                                        if ($art_ttc_abo !== 0) {
                                                            // Article Facture

                                                            $afa_multiplicateur = ($afa_multiplicateur === 0) ? 1 : $afa_multiplicateur;
                                                            $afa_prix_ttc = $art_ttc_abo * $afa_quantite * $afa_multiplicateur;
                                                            
                                                            $dest_article_facture_values = [$art_ref, $last_fac_ref, $mar_ref, "'$dest_dcreat'", $afa_quantite, $art_ttc_abo, $art_ttc_abo, 0, $afa_multiplicateur, "'$dest_dcreat'", "'$dest_ucreat'", $afa_prix_ttc, 0, $afa_prix_ttc];

                                                            $insert_into_query = "INSERT INTO $dest_article_facture (" . implode(", ", $dest_article_facture_cols) . ") VALUES (" . implode(", ", $dest_article_facture_values) . ")";
                                                            execute_query($insert_into_query, $nb_inserted, $nb_to_insert);

                                                            $fac_somme_ttc += $afa_prix_ttc;
                                                            $art_facture_added_to_facture += 1;

                                                            // Article Facture Langue

                                                            $dest_article_facture_lang_values = [$last_fac_ref, $art_ref, "'$dest_dcreat'", 1, "'$art_nom'", "'$dest_dcreat'", "'$dest_ucreat'"];

                                                            $insert_into_query = "INSERT INTO $dest_article_facture_lang (" . implode(", ", $dest_article_facture_lang_cols) . ") VALUES (" . implode(", ", $dest_article_facture_lang_values) . ")";
                                                            execute_query($insert_into_query, $nb_inserted, $nb_to_insert);

                                                            // Abonnement

                                                            // Depuis le MAR_CODE, récupérer le nom de la colonne dans la table source des abonnements
                                                            // Calculer la colonne des dates pour récupérer le type d'abonnement

                                                            $last_abo_ref += 1;

                                                            $req_abonn = $src_conn->query("SELECT * FROM $src_abonnement WHERE nom_deb = '" . addslashes($row["nom_deb"]) . "'")->fetch();
                                                            if (!$req_abonn) {
                                                                array_push($warnings, $header_err_message . "L'exploitant n'existe pas dans le fichier des abonnements " . $source_files["abonnements"]);
                                                            } else {
                                                                $src_abonn_marche_col = array_search($mar_code, $req_abonn);
                                                                $matches = [];
                                                                preg_match('/[0-9]+/', $src_abonn_marche_col, $matches);

                                                                // Il se peut que les marchés associés dans le fichier des exploitants ne soient pas associés dans le fichier des abonnements
                                                                if (!isset($matches[0])) {
                                                                    array_push($warnings, $header_err_message . "Le marché $mar_code n'est pas associé à l'exploitant dans le fichier des abonnements " . $source_files["abonnements"]);
                                                                } else {
                                                                    $num_mens = ($matches[0] === "1") ? "" : $matches[0]; // pour les mensualités, la première colonne s'appelle 'mens', contrairement à la première colonne des marchés qui s'appelle 'marche1'
                                                                    $src_abonn_mens_col = "mens$num_mens";
                                                                    
                                                                    $abo_type = $req_abonn[$src_abonn_mens_col];

                                                                    switch ($abo_type) {
                                                                        case "1":
                                                                            $abo_type = "MENSUEL";
                                                                            break;
                                                                        case "2":
                                                                            $abo_type = "BIMESTRIEL";
                                                                            break;
                                                                        case "3":
                                                                            $abo_type = "TRIMESTRIEL";
                                                                            break;
                                                                        case "4":
                                                                            $abo_type = "SEMESTRIEL";
                                                                            break;
                                                                        case "5";
                                                                            $abo_type = "ANNUEL";
                                                                            break;
                                                                        case "6":
                                                                            $abo_type = "AUCUN";
                                                                            break;
                                                                    }

                                                                    $dest_abonnement_values = [$last_exp_ref, "'$dest_dcreat'", $last_fac_ref, "'$dest_dcreat'", "'$dest_ucreat'", $last_abo_ref, "'$abo_type'"];

                                                                    $insert_into_query = "INSERT INTO $dest_abonnement (" . implode(", ", $dest_abonnement_cols) . ") VALUES (" . implode(", ", $dest_abonnement_values) . ")";
                                                                    execute_query($insert_into_query, $nb_inserted, $nb_to_insert);
                                                                } // Fin "si le marché est aussi associé à l'exploitant dans le fichier des abonnements"
                                                            } // Fin "si l'exploitant est présent dans le fichier des abonnements"
                                                        } // Fin "si on est sur un tarif de type abonnés"
                                                    } // Fin "si la quantité est différente de 0"
                                                } // Fin "si le marché existe et n'a pas été déjà traité"
                                            } // Fin "si l'exploitant est abonné au marché via l'article"
                                        } // Fin "pour chaque jour de la semaine"
                                    } // Fin "si l'article est pour les abonnées et dans le groupe du marché associé"
                                } // Fin "si l'article existe dans la table de destination"
                            }
                        } else {
                            array_push($warnings, $header_err_message . "La colonne 'groupe$num_groupe' n'existe pas alors que la colonne '$src_exploitant_classe_col' existe dans le fichier des exploitants " . $source_files["exploitants"]);
                        }
                    } // Fin "pour chaque colonne article de l'exploitant"

                    // Actualiser le total de la facture s'il y a eu au moins un article facture ajouté à la facture, la supprimer sinon
                    if ($art_facture_added_to_facture > 0) {
                        $update_query = "UPDATE $dest_facture SET FAC_SOMME_TTC = $fac_somme_ttc, FAC_SOMME_HT = $fac_somme_ttc WHERE FAC_REF = $last_fac_ref";
                        execute_query($update_query, $nb_inserted, $nb_to_insert);
                    } else {
                        $delete_query = "DELETE FROM $dest_facture WHERE FAC_REF = $last_fac_ref";
                        execute_query($delete_query, $nb_inserted, $nb_to_insert);
                    }
                } // Fin "liens articles exploitants"
            } // Fin "pour chaque exploitant de la table source"
            if ($display_dest_requests) echo "</div>";

            summarize_queries($nb_inserted, $nb_to_insert, $nb_errors, $warnings, $nb_warnings);

            $nb_exploitants = $dest_conn->query("SELECT COUNT(*) FROM $dest_exploitant")->fetch()[0] - $nb_exploitants;
            $mysql_conn->exec("UPDATE $reprise_table SET exploitants_dest = $nb_exploitants WHERE id = $reprise_id");

            // Rassemblements / Présences

            if ($insert_presences) {
                echo "<h2 id=\"dest_presences\">Rassemblements / Présences<span><tt>$dest_rassemblement</tt> / <tt>$dest_presence</tt></span></h2>";
                fwrite($output_file, "\n-- Rassemblements / Présences\n\n");

                $nb_to_insert = 0;
                $nb_inserted = 0;
                $warnings = [];
                
                $req_last_ras_ref = $dest_conn->query(build_query("SELECT RAS_REF FROM $dest_rassemblement", "", "RAS_REF DESC", "1"))->fetch();
                $last_ras_ref = ($req_last_ras_ref == null) ? 0 : $req_last_ras_ref["RAS_REF"];

                $nb_presences = $dest_conn->query("SELECT COUNT(*) FROM $dest_presence")->fetch()[0];
                
                // Parcourir la table source des présences
                //   Pour chaque présence : lire le code du marché, récupérer le MAR_REF a partir du code, récupérer le RAS_REF de ce marché et de cette date (le créer s'il n'existe pas)
                foreach ($src_conn->query("SELECT * FROM $src_presence WHERE Date1 != ''") as $row) {
                    $exp_nom = $row["Nom"];

                    $req_exp_ref = $dest_conn->query(build_query("SELECT EXP_REF FROM $dest_exploitant", "EXP_NOM_PERS_PHYSIQUE = '$exp_nom'", "", ""))->fetch();
                    if (!$req_exp_ref) {
                        array_push($warnings, "L'exploitant $exp_nom n'existe pas dans le fichier des exploitants " . $source_files["exploitants"]);
                    } else {
                        $ras_mar = $row["Marche"];

                        $req_mar_ref = $dest_conn->query(build_query("SELECT MAR_REF FROM $dest_marche", "MAR_CODE = '$ras_mar'", "", ""))->fetch();
                        if (!$req_mar_ref) {
                            array_push($warnings, "Le marché $ras_mar auquel l'exploitant $exp_nom est associé n'existe pas dans le fichier des marchés " . $source_files["marchés"]);
                        } else {
                            $mar_ref = $req_mar_ref["MAR_REF"];

                            $req_rassemblement = $dest_conn->query("SELECT RAS_REF FROM $dest_rassemblement WHERE RAS_MAR = '$mar_ref' AND RAS_DATE = '$ras_date'")->fetch();
                            $ras_ref = NULL;
                            if (!$req_rassemblement) {
                                // Créer un rassemblement pour le marché avant d'ajouter les présences des exploitants
                                $last_ras_ref += 1;
                                $ras_date = $row["Date"];
                                $dest_rassemblement_values = [$last_ras_ref, $mar_ref, "'$ras_date'", "'$dest_dcreat'", "'$dest_ucreat'"];

                                $insert_into_query = "INSERT INTO $dest_rassemblement (" . implode(", ", $dest_rassemblement_cols) . ") VALUES (" . implode(", ", $dest_rassemblement_values) . ")";
                                execute_query($insert_into_query, $nb_inserted, $nb_to_insert);

                                $ras_ref = $last_ras_ref;
                            } else {
                                $ras_ref = $req_rassemblement["RAS_REF"];
                            }
                            
                            $dest_presence_values = [$exp_ref, $ras_ref, "'$dest_dcreat'", "'$dest_ucreat'", "'" . $row["Date1"] . "'"];

                            $insert_into_query = "INSERT INTO $dest_presence (" . implode(", ", $dest_presence_cols) . ") VALUES (" . implode(", ", $dest_presence_values) . ")";
                            execute_query($insert_into_query, $nb_inserted, $nb_to_insert);
                        } // Fin "si le code du marché du fichier des présences existe dans le fichier des marchés"
                    } // Fin "si l'exploitant du fichier des présences existe dans le fichier des exploitants"
                }
                if ($display_dest_requests) echo "</div>";

                summarize_queries($nb_inserted, $nb_to_insert, $nb_errors, $warnings, $nb_warnings);

                $nb_presences = $dest_conn->query("SELECT COUNT(*) FROM $dest_presence")->fetch()[0] - $nb_presences;
                $mysql_conn->exec("UPDATE $reprise_table SET presences_dest = $nb_presences WHERE id = $reprise_id");
            }

            // Compteurs

            echo "<h2 id=\"dest_compteurs\">Compteurs<span><tt>$dest_compteur</tt></span></h2>";
            fwrite($output_file, "\n-- Compteurs\n\n");

            $nb_to_update = 0;
            $nb_updated = 0;

            if ($display_dest_requests) echo "<div class=\"pre\">";

            $update_query = "UPDATE $dest_compteur SET CPT_VAL = (SELECT MAX(MAR_REF) + 1 FROM $dest_marche) WHERE CPT_TABLE = 'actreffacture'";
            execute_query($update_query, $nb_updated, $nb_to_update);

            $verif_query = $dest_conn->query("SELECT COUNT(*) FROM $dest_compteur WHERE CPT_TABLE = 'article'")->fetch();
            if ($verif_query[0] === "0") {
                $last_cpt_ref = $dest_conn->query("SELECT MAX(CPT_REF) + 1 FROM $dest_compteur")->fetch()[0];
                $dest_compteur_values = [$last_cpt_ref, "'article'", "(SELECT MAX(ART_REF) + 1 FROM $dest_article)", "'$dest_dcreat'", "'$dest_ucreat'"];
                $insert_into_query = "INSERT INTO $dest_compteur (" . implode(", ", $dest_compteur_cols) . ") VALUES (" . implode(", ", $dest_compteur_values) . ")";
                execute_query($insert_into_query, $nb_updated, $nb_to_update);
            } else {
                $update_query = "UPDATE $dest_compteur SET CPT_VAL = (SELECT MAX(ART_REF) + 1 FROM $dest_article) WHERE CPT_TABLE = 'article'";
                execute_query($update_query, $nb_updated, $nb_to_update);
            }

            $update_query = "UPDATE $dest_compteur SET CPT_VAL = (SELECT MAX(FAC_REF) + 1 FROM $dest_facture) WHERE CPT_TABLE = 'facture'";
            execute_query($update_query, $nb_updated, $nb_to_update);
            
            $nb_compteurs = $dest_conn->query("SELECT MAX(CPT_REF) + 1 FROM $dest_compteur")->fetch()[0]; // compteur du compteur
            $update_query = "UPDATE $dest_compteur SET CPT_VAL = $nb_compteurs WHERE CPT_TABLE = 'compteur'";
            execute_query($update_query, $nb_updated, $nb_to_update);

            if ($display_dest_requests) echo "</div>";

            summarize_queries($nb_updated, $nb_to_update, $nb_errors, [], $nb_warnings);


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
                var nb_marches_src = parseInt(<?php echo $nb_content["marchés"]; ?>);
                var nb_articles_src = parseInt(<?php echo $nb_content["articles"]; ?>);
                var nb_exploitants_src = parseInt(<?php echo $nb_content["exploitants"]; ?>);
                var nb_presences_src = parseInt(<?php echo $insert_presences ? $nb_content["présences"] : -1; ?>);
                var nb_marches_dest = parseInt(<?php echo $nb_marches; ?>);
                var nb_articles_dest = parseInt(<?php echo $nb_articles; ?>);
                var nb_exploitants_dest = parseInt(<?php echo $nb_exploitants; ?>);
                var nb_presences_dest = parseInt(<?php echo $insert_presences ? $nb_presences : -1; ?>);
                dom_nb_content.innerHTML += "<tr>";
                dom_nb_content.innerHTML += "<td>" + nb_marches_dest + "/" + nb_marches_src + "</td><td>" + nb_articles_dest + "/" + nb_articles_src + "</td><td>" + nb_exploitants_dest + "/" + nb_exploitants_src + "</td>";
                if (nb_presences_src !== -1 && nb_presences_dest !== -1) dom_nb_content.innerHTML += "<td>" + nb_presences_dest + "/" + nb_presences_src + "</td>";
                dom_nb_content.innerHTML += "</tr>";
            </script>

            <?php

        } // Fin "si $get analyze = 1"

    ?>

<layer><message></message></layer>

</body>

<script>

var script_file_name = "<?php echo $script_file_name; ?>";

</script>

<script src="../js/script.js"></script>

</html>