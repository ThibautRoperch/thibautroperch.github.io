<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Reprise GEODP</title>
    <link rel="stylesheet" type="text/css" href="../css/style.css">
</head>

<?php

$script_file_name = "index.php";
$title = "Reprise v1 vers v2<br>Placier";

// Transfert les données Placier des table GEODP v1 vers les tables GEODP v2

/**
 * VERSION 1.0 - 30/04/2018
 * 
 * @author Thibaut ROPERCH
 */


// Ouvrir cette page avec le paramètre 'transfert=1' pour utiliser les paramètres par défaut, l'ouvrir normalement en situation réelle
$transfert_mode = (isset($_GET["transfert"]) && $_GET["transfert"] === "1");

// Nom du fichier de sortie contenant les requêtes SQL à envoyer (GEODP v1)
$output_filename = "output.sql"; // Si un fichier avec le même nom existe déjà, il sera écrasé

$php_required_version = "7.1.9";

date_default_timezone_set("Europe/Paris");


/*************************
 *    BDD SOURCE (v1)    *
 *************************/

$src_marche = "MARCHE";
$src_marche_lang = "MARCHE_LANGUE";

$src_article = "ARTICLE";
$src_article_lang = "ARTICLE_LANGUE";

$src_activite_commerciale_lang = "ACTIVITECOMMERCIALE_LANGUE";

$src_civilite_lang = "CIVILITE_LANGUE";
$src_exploitant = "EXPLOITANT";

$src_piece = "SOCIETE_PROPRIETE";
$src_piece_val = "SOCIETE_PROPRIETE_VALEUR";
$src_piece_lang = "SOCIETE_PROPRIETE_LANGUE";

$src_facture = "FACTURE";
$src_article_facture = "ARTICLE_FACTURE";
$src_article_facture_lang = "ARTICLE_FACTURE_LANGUE";

$src_host = (isset($_POST["src_host"]) && $_POST["src_host"] !== "") ? $_POST["src_host"] : "ares"; // zeus | ares
$src_port = "1521";
$src_service = ($src_host === "zeus") ? "orcl" : "xe";
$src_database = (isset($_POST["src_database"]) && $_POST["src_database"] !== "") ? $_POST["src_database"] : "geodpthibaut";
$src_user = (isset($_POST["src_user"]) && $_POST["src_user"] !== "") ? $_POST["src_user"] : $src_database;
$src_password = (isset($_POST["src_password"]) && $_POST["src_password"] !== "") ? $_POST["src_password"] : $src_database;

$src_conn = new PDO("oci:dbname=(DESCRIPTION = (ADDRESS_LIST = (ADDRESS = (PROTOCOL = TCP) (Host = $src_host) (Port = $src_port))) (CONNECT_DATA = (SERVICE_NAME = ".$src_service.")));charset=UTF8", $src_user, $src_password);

$src_nb_content = []; // auto-computed


/*************************
 *    BDD CIBLE (v2)     *
 *************************/

$dest_utilisateur = "geodp_user";
$dest_domaine = "geodp_domain";

$dest_charging_mode = "geodp_charging_mode";
$dest_jour = "geodp_day";
$dest_attendance = "geodp_type_attendance";

$dest_marche = "geodp_event";
$dest_marche_jour = "geodp_event_day";

$dest_article = "geodp_product";
$dest_calcul_base = "geodp_calcul_base";
$dest_management_calcul = "geodp_management_calcul";
$dest_calcul_mode = "geodp_calcul_mode";
$dest_vat = "geodp_vat";
$dest_tarif = "geodp_price";
$dest_tarif_detail = "geodp_price_detail";
$dest_tarif_attendance = "geodp_price_type_attendance";

$dest_activite = "geodp_commercial_activity";

$dest_civility = "geodp_civility";
$dest_address = "geodp_address";
$dest_commercant = "geodp_company";

$dest_facture = "geodp_invoice";

$dest_host = (isset($_POST["dest_host"]) && $_POST["dest_host"] !== "") ? $_POST["dest_host"] : "192.168.1.36";
$dest_port = (isset($_POST["dest_port"]) && $_POST["dest_port"] !== "") ? $_POST["dest_port"] : "6543";
$dest_database = (isset($_POST["dest_database"]) && $_POST["dest_database"] !== "") ? $_POST["dest_database"] : "TRO";
$dest_user = (isset($_POST["dest_user"]) && $_POST["dest_user"] !== "") ? $_POST["dest_user"] : "postgres";
$dest_password = (isset($_POST["dest_password"]) && $_POST["dest_password"] !== "") ? $_POST["dest_password"] : $dest_user;

$dest_conn = pg_connect("host=$dest_host port=$dest_port dbname=$dest_database user=$dest_user password=$dest_password");

$dest_nb_content = []; // auto-computed

$pgsql_date_format = "Y-m-d H:i:s";

/*************************
 *        OPTIONS        *
 *************************/

// Date de création maximum des données à transférer
$dates_max_birth = date("d/m/y", strtotime("-6 months")); // avec '6' l'âge max des données en mois

// Mettre à vrai pour supprimer les données insérées ces dernières 72 heures dans les tables de destination avant l'insertion des nouvelles, mettre à faux sinon
$clean_destination_tables = true; // true | false

// Mettre à vrai pour supprimer les données des tables de destination avant l'insertion des nouvelles, mettre à faux sinon
$erase_destination_tables = (isset($_POST["erase_destination_tables"])) ? true_false($_POST["erase_destination_tables"]) : false; // true | false

// Mettre à vrai pour afficher les requêtes SQL relatives aux tables de destination, mettre à faux sinon (dans tous les cas, le fichier $output_filename est généré)
$display_dest_requests = true; // true | false

// Mettre à vrai pour exécuter les requêtes SQL relatives aux tables de destination, mettre à faux sinon (dans tous les cas, le fichier $output_filename est généré)
$exec_dest_requests = true; // true | false


/*************************
 *   FONCTIONS ANALYSE   *
 *************************/

$output_file = fopen($output_filename, "w+");

function yes_no($bool) {
    return $bool ? "Oui" : "Non";
}

function true_false($string) {
    return ($string === "true") ? true : false;
}

function ok_nok($bool) {
    return $bool ? "<ok></ok>" : "<nok></nok>";
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

function insert_into($table, $cols, $values, &$nb_executed, &$nb_to_execute) { // Pour $dest_conn uniquement !
    global $dest_conn;

    $query = "INSERT INTO $table (" . implode(", ", $cols) . ") VALUES (" . implode(", ", $values) . ")";
    execute_query($query, $nb_executed, $nb_to_execute);

    $where_content = "";
    for ($i = 0; $i < count($cols); ++$i) {
        $where_content .= ($where_content === "") ? "" : " AND ";
        $where_content .= ($values[$i] === "NULL") ? $cols[$i] . " IS " . $values[$i] : $cols[$i] . " = " . $values[$i];
    }
    $where_content = str_replace("\\'", "''", $where_content);
    $last_inserted_id = pg_fetch_object(pg_query("SELECT id FROM $table WHERE $where_content AND del = false ORDER BY date_create DESC"))->id;
    return $last_inserted_id;
}

function execute_query($query, &$nb_executed, &$nb_to_execute) { // Pour $dest_conn uniquement !
    global $display_dest_requests, $exec_dest_requests, $dest_conn, $output_file;

    $query = str_replace("\\'", "''", $query);

    if ($display_dest_requests) echo "$query<br>";

    fwrite($output_file, "$query;\n");

    if ($exec_dest_requests) {
        ++$nb_to_execute;
        $req_res = pg_query($dest_conn, $query);
        if ($req_res === false) {
            echo "<p class=\"danger\">".$dest_conn->errorInfo()[2]."</p>";
        } else {
            ++$nb_executed;
            if ($req_res === 0) {
                echo "<p class=\"warning\">0 lignes affectées par la requête</p>";
            }
        }
    }
}

function summarize_queries($nb_executed, $nb_to_execute, &$nb_errors, $warnings, &$nb_warnings) {
    foreach ($warnings as $warning) {
        echo "<p class=\"warning\">$warning</p>";
    }
    $nb_warnings += count($warnings);

    $success_s = ($nb_executed == 0 || $nb_executed > 1) ? "s" : "";
    $exec_s = ($nb_to_execute == 0 || $nb_to_execute > 1) ? "s" : "";

    $class = ($nb_executed == $nb_to_execute) ? "success" : "danger";
    echo "<p class=\"$class\">$nb_executed requête$success_s réussie$success_s sur $nb_to_execute requête$exec_s exécutée$exec_s</p>";
    $nb_errors += $nb_to_execute - $nb_executed;
}

?>

<body>
    
    <?php
    
        /*************************
         *        ACCUEIL        *
         *************************/
        
        if (!$transfert_mode) {

            echo "<init>";
            echo "<h1>$title</h1>";

            echo "<form action=\"$script_file_name?transfert=1\" method=\"POST\" onsubmit=\"loading()\">";

                echo "<h2>Paramètres de connexion</h2>";

                    echo "<section class=\"row\">";

                        echo "<article class=\"form\">";
                            echo "<field><input class=\"centered\" id=\"src_database\" name=\"src_database\" onchange=\"autocomplete_typing(this, 'src_user'); autocomplete_typing(this, 'src_password')\" placeholder=\"Base de données source (GEODP v1)\" required /></field>";
                            echo "<field><label for=\"src_host\">Hôte</label><input id=\"src_host\" name=\"src_host\" value=\"$src_host\" required /></field>";
                            echo "<field><label for=\"src_port\">Port</label><input id=\"src_port\" name=\"src_port\" value=\"$src_port\" required /></field>";
                            echo "<field><label for=\"src_user\">Utilisateur</label><input id=\"src_user\" name=\"src_user\"  type=\"text\" required /></field>";
                            echo "<field><label for=\"src_password\">Mot de passe</label><input id=\"src_password\" name=\"src_password\" type=\"password\"/></field>";
                            echo "<button class=\"centered\" onclick=\"test_connection(this, event, 'oracle')\">Tester la connexion Oracle</button>";
                        echo "</article>";

                        echo "<article class=\"form\">";
                            echo "<field><input class=\"centered\" id=\"dest_database\" name=\"dest_database\" placeholder=\"Base de données cible (GEODP v2)\" required /></field>";
                            echo "<field><label for=\"dest_host\">Hôte</label><input id=\"dest_host\" name=\"dest_host\" value=\"$dest_host\" required /></field>";
                            echo "<field><label for=\"dest_port\">Port</label><input id=\"dest_port\" name=\"dest_port\" value=\"$dest_port\" required /></field>";
                            echo "<field><label for=\"dest_user\">Utilisateur</label><input id=\"dest_user\" name=\"dest_user\" onchange=\"autocomplete_typing(this, 'dest_password')\" required /></field>";
                            echo "<field><label for=\"dest_password\">Mot de passe</label><input id=\"dest_password\" name=\"dest_password\" type=\"password\" /></field>";
                            echo "<button class=\"centered\" onclick=\"test_connection(this, event, 'pgsql')\">Tester la connexion pgSQL</button>";
                        echo "</article>";

                    echo "</section>";
                        
                    echo "<field><label for=\"erase_destination_tables\">Vider les tables de destination en amont</label>";
                        $yes_selected = $erase_destination_tables ? "selected" : "";
                        $no_selected = !$erase_destination_tables ? "selected" : "";
                        echo "<select id=\"erase_destination_tables\" name=\"erase_destination_tables\"><option value=\"true\" $yes_selected>Oui</option><option value=\"false\" $no_selected>Non</option></select>";
                    echo "</field>";

                    echo "<field>";
                        echo "<input type=\"submit\" value=\"Effectuer la reprise\" />";
                    echo "</field>";
                
                echo "<h2>Options</h2>";

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
                    $version_ok = (phpversion('pdo_pgsql') !== "") ? true : false;
                    echo "<field><label>Extension pgSQL via PDO</label><input type=\"disabled\" value=\"php_pdo_pgsql\" disabled />" . ok_nok($version_ok) . "</field>";

            echo "</form>";
            
            echo "</init>";
        }

        /*************************
         *        ANALYSE        *
         *************************/

        if ($transfert_mode) {

            $nom_reprise = "$src_database > $dest_database";
            
            //// Sommaire

            echo "<aside>";
            echo "<h1>$title</h1>";
            echo "<ol id=\"sommaire\">";
                echo "<h2>$nom_reprise</h2>";
                echo "<li><a href=\"#summary\">Résumé</a></li>";
                echo "<li><a href=\"#parameters\">Paramètres</a>";
                if ($clean_destination_tables) {
                    echo "<li><a href=\"#clean\">Nettoyage des tables de destination</a>";
                        echo "<ol>";
                            echo "<li><a href=\"#clean_tarifs\">Tarifs</a></li>";
                            echo "<li><a href=\"#clean_marches\">Marchés</a></li>";
                            echo "<li><a href=\"#clean_commercant\">Commerçants</a></li>";
                            echo "<li><a href=\"#clean_activites_commerciales\">Activités commerciales</a></li>";
                            echo "</ol>";
                    echo "</li>";
                }
                if ($erase_destination_tables) {
                    echo "<li><a href=\"#erase\">Vidage des tables de destination</a>";
                        echo "<ol>";
                            echo "<li><a href=\"#erase_tarifs\">Tarifs</a></li>";
                            echo "<li><a href=\"#erase_marches\">Marchés</a></li>";
                            echo "<li><a href=\"#erase_commercant\">Commerçants</a></li>";
                            echo "<li><a href=\"#erase_activites_commerciales\">Activités commerciales</a></li>";
                        echo "</ol>";
                    echo "</li>";
                }
                echo "<li><a href=\"#transfert\">Transfert des données</a>";
                    echo "<ol>";
                        echo "<li><a href=\"#marches\">Marchés</a></li>";
                        echo "<li><a href=\"#tarifs\">Tarifs</a></li>";
                        echo "<li><a href=\"#activites_commerciales\">Activités commerciales</a></li>";
                        echo "<li><a href=\"#commercants\">Commerçants</a></li>";
                    echo "</ol>";
                echo "</li>";
                echo "<li><a href=\"$script_file_name\">Retour à l'accueil</a></li>";
            echo "</ol>";
            echo "</aside>";

            echo "<section>";

            echo "<summary id=\"summary\">";
                echo "<h1>$nom_reprise</h1>";
                echo "<p id=\"nb_errors\">Reprise en cours</p>";
                echo "<table id=\"nb_content\"><tr><th>Marchés</th><th>Tarifs</th><th>Activités</th><th>Commerçants</th></tr></table>";
                echo "<div><a target=\"_blank\" href=\"//$dest_host:$dest_port\">$dest_host:$dest_port</a></div>";
            echo "</summary>";

            //// Paramètres

            echo "<h1 id=\"parameters\">Paramètres</h1>";

            echo "<h2 id=\"source\">Connexion à $src_database</h2>";
            
            echo "<form>";
                echo "<field><label>Serveur</label><input type=\"disabled\" value=\"$src_host:$src_port/$src_service\" disabled /></field>";
                echo "<field><label>Identifiant</label><input type=\"disabled\" value=\"$src_user\" disabled /></field>";
                echo "<field><label>Mot de passe</label><input type=\"disabled\" value=\"$src_password\" disabled /></field>";
            echo "</form>";

            echo "<h2 id=\"cible\">Connexion à $dest_database</h2>";
            
            echo "<form>";
            echo "<field><label>Serveur</label><input type=\"disabled\" value=\"$dest_host:$dest_port\" disabled /></field>";
            echo "<field><label>Identifiant</label><input type=\"disabled\" value=\"$dest_user\" disabled /></field>";
            echo "<field><label>Mot de passe</label><input type=\"disabled\" value=\"$dest_password\" disabled /></field>";
            echo "</form>";

            echo "<h2 id=\"source\">Options</h2>";

            echo "<form>";
                echo "<field><label>Vider les tables de destination en amont</label><input type=\"disabled\" value=\"".yes_no($erase_destination_tables)."\" disabled /></field>";
                echo "<field><label>Afficher les requêtes à exécuter</label><input type=\"disabled\" value=\"".yes_no($display_dest_requests)."\" disabled /></field>";
                echo "<field><label>Exécuter les requêtes</label><input type=\"disabled\" value=\"".yes_no($exec_dest_requests)."\" disabled /></field>";
                echo "<field><label>Fichier de sortie</label><input type=\"disabled\" value=\"$output_filename\" disabled /></field>";
            echo "</form>";
            
            $today = date($pgsql_date_format, time());
            $lasts_72_hours = date($pgsql_date_format, strtotime("-3 day"));

            //// Nettoyage des tables de destination

            if ($clean_destination_tables) {
                echo "<h1 id=\"clean\">Nettoyage des tables de destination</h1>";
                
                echo "<h2 id=\"clean_tarifs\">Tarifs<span><tt>$dest_tarif_detail</tt> / <tt>$dest_tarif_attendance</tt> / <tt>$dest_tarif</tt> / <tt>$dest_article</tt></span></h2>";

                pg_query("DELETE FROM $dest_tarif_detail WHERE date_create > '$lasts_72_hours'");
                pg_query("DELETE FROM $dest_tarif_attendance WHERE date_create > '$lasts_72_hours'");
                pg_query("DELETE FROM $dest_tarif WHERE date_create > '$lasts_72_hours'");
                pg_query("DELETE FROM $dest_article WHERE date_create > '$lasts_72_hours'");

                echo "<h2 id=\"clean_marches\">Marchés<span><tt>$dest_marche_jour</tt> / <tt>$dest_marche</tt></span></h2>";
                
                pg_query("DELETE FROM $dest_marche_jour WHERE date_create > '$lasts_72_hours'");
                pg_query("DELETE FROM $dest_marche WHERE date_create > '$lasts_72_hours'");

                echo "<h2 id=\"clean_commercants\">Commerçants<span><tt>$dest_commercant</tt> / <tt>$dest_address</tt></span></h2>";
                
                pg_query("DELETE FROM $dest_commercant WHERE date_create > '$lasts_72_hours'");
                pg_query("DELETE FROM $dest_address WHERE date_create > '$lasts_72_hours'");

                echo "<h2 id=\"clean_activites_commerciales\">Activités commerciales<span><tt>$dest_activite</tt></span></h2>";
                
                pg_query("DELETE FROM $dest_activite WHERE date_create > '$lasts_72_hours'");
            }

            //// Vidage des tables de destination

            if ($erase_destination_tables) {
                echo "<h1 id=\"erase\">Vidage des tables de destination</h1>";
                
                echo "<h2 id=\"erase_tarifs\">Tarifs<span><tt>$dest_tarif_detail</tt> / <tt>$dest_tarif_attendance</tt> / <tt>$dest_tarif</tt> / <tt>$dest_article</tt></span></h2>";

                pg_query("DELETE FROM $dest_tarif_detail");
                pg_query("DELETE FROM $dest_tarif_attendance");
                pg_query("DELETE FROM $dest_tarif");
                pg_query("DELETE FROM $dest_article");

                echo "<h2 id=\"erase_marches\">Marchés<span><tt>$dest_marche_jour</tt> / <tt>$dest_marche</tt></span></h2>";
                
                pg_query("DELETE FROM $dest_marche_jour");
                pg_query("DELETE FROM $dest_marche");

                echo "<h2 id=\"erase_commercants\">Commerçants<span><tt>$dest_commercant</tt> / <tt>$dest_address</tt></span></h2>";
                
                pg_query("DELETE FROM $dest_commercant");
                pg_query("DELETE FROM $dest_address");

                echo "<h2 id=\"erase_activites_commerciales\">Activités commerciales<span><tt>$dest_activite</tt></span></h2>";
                
                pg_query("DELETE FROM $dest_activite");
            }

            //// Transfert des données

            echo "<h1 id=\"transfert\">Transfert des données</h1>";

            $marche_cols = ["id_domain", "id_charging_mode", "name", "id_user_create", "date_create"];
            $marche_jour_cols = ["id_day", "id_event", "id_user_create", "date_create"];
            
            $calcul_base_cols = ["id_type_domain", "code", "name", "calcul", "calcul_name", "id_user_create", "date_create"];
            $calcul_base_fields_cols = ["id_calcul_base", "name", "id_user_create", "date_create"];
            $management_calcul_cols = ["id_type_domain", "name", "code", "id_user_create", "date_create"];
            $article_cols = ["id_domain", "id_calcul_base", "id_management_calcul", "id_event", "code", "name", "invoice_name", "unit", "id_user_create", "date_create"];
            $tarif_cols = ["id_product", "id_calcul_mode", "id_charging_mode", "id_vat", "name", "excluded_taxes", "id_user_create", "date_create"];
            $tarif_attendance_cols = ["id_price", "id_type_attendance", "id_user_create", "date_create"];
            $tarif_detail_cols = ["id_price", "date_start", "date_end", "unit_price", "id_user_create", "date_create"];

            $activite_cols = ["name", "id_user_create", "date_create"];

            $address_cols = ["street_number_alphanumeric", "address", "postal_code", "city", "id_user_create", "date_create"];
            $commercant_cols = ["id_domain", "id_civility", "id_commercial_activity", "id_address", "first_name", "last_name", "business_name", "sign_board", "siret", "accounting_code", "ape", "phone_number", "mobile_phone", "email", "iban", "bic", "complement_name", "photo", "id_user_create", "date_create"];
            if ($dest_database === "TRO") $commercant_cols[array_search("siret", $commercant_cols)] = "siren";

            $user_id = pg_fetch_object(pg_query("SELECT id FROM $dest_utilisateur WHERE last_name LIKE 'ILTR' AND first_name LIKE 'Iltr'"))->id;
            $domain = pg_fetch_object(pg_query("SELECT id, id_type_domain FROM $dest_domaine WHERE name LIKE 'Placier' AND del = false"));
            $domain_id = $domain->id;
            $type_domain_id = $domain->id_type_domain;

            $nb_errors = 0;
            $nb_warnings = 0;
            
            // Marchés

            echo "<h2 id=\"marches\">Marchés<span><tt>$dest_marche</tt> / <tt>$dest_marche_jour</tt></span></h2>";

            $src_nb_content["marchés"] = 0;
            $dest_nb_content["marchés"] = 0;

            $nb_to_execute = 0;
            $nb_executed = 0;

            $nb_marches = pg_fetch_object(pg_query("SELECT COUNT(*) FROM $dest_marche"))->count;

            if ($display_dest_requests) echo "<div class=\"pre\">";
            foreach ($src_conn->query("SELECT UNIQUE MAR_NOM, MAR_REF FROM $src_marche_lang WHERE DCREAT > '$dates_max_birth'") as $row) {
                $src_nb_content["marchés"] += 1;

                // Event

                $charging_mode = pg_fetch_object(pg_query("SELECT id FROM $dest_charging_mode WHERE code = 'WEEK' AND del = false"))->id;
                $name = addslashes(ucwords(strtolower($row["MAR_NOM"])));

                $values = ["'$domain_id'", "'$charging_mode'", "'$name'", "'$user_id'", "'$today'"];
                $event_id = insert_into($dest_marche, $marche_cols, $values, $nb_to_execute, $nb_executed);

                // Event day
                
                foreach($src_conn->query("SELECT MAR_JOUR FROM $src_marche WHERE MAR_REF = " . $row["MAR_REF"]) as $marche) {
                    if (!$marche["MAR_JOUR"]) {
                        $req_days = pg_query("SELECT id FROM $dest_jour");
                        while ($day = pg_fetch_object($req_days)) {
                            $values = ["'".$day->id."'", "'$event_id'", "'$user_id'", "'$today'"];
                            insert_into($dest_marche_jour, $marche_jour_cols, $values, $nb_to_execute, $nb_executed);
                        }
                    } else {
                        $mar_day = "_" . substr($marche["MAR_JOUR"], 1);
                        
                        $day = pg_fetch_object(pg_query("SELECT id FROM $dest_jour WHERE name LIKE '$mar_day'"))->id;
                        
                        $values = ["'$day'", "'$event_id'", "'$user_id'", "'$today'"];
                        insert_into($dest_marche_jour, $marche_jour_cols, $values, $nb_to_execute, $nb_executed);
                    }
                }
            }
            if ($display_dest_requests) echo "</div>";

            summarize_queries($nb_executed, $nb_to_execute, $nb_errors, [], $nb_warnings);

            $nb_marches = pg_fetch_object(pg_query("SELECT COUNT(*) FROM $dest_marche"))->count - $nb_marches;
            $dest_nb_content["marchés"] = $nb_marches;

            // Tarifs

            echo "<h2 id=\"tarifs\">Tarifs<span><tt>$dest_article</tt> / <tt>$dest_tarif</tt> / <tt>$dest_tarif_attendance</tt> / <tt>$dest_tarif_detail</tt></span></h2>";

            $src_nb_content["tarifs"] = 0;
            $dest_nb_content["tarifs"] = 0;

            $nb_to_execute = 0;
            $nb_executed = 0;
            $warnings = [];

            $nb_tarifs = pg_fetch_object(pg_query("SELECT COUNT(*) FROM $dest_tarif"))->count;

            if ($display_dest_requests) echo "<div class=\"pre\">";
            foreach ($src_conn->query("SELECT UNIQUE ART_NOM, art.*, artl.*, marl.MAR_NOM FROM $src_article art, $src_article_lang artl, $src_marche_lang marl WHERE art.DCREAT > '$dates_max_birth' AND art.ART_REF = artl.ART_REF AND ART_VISIBLE = 1 AND marl.MAR_REF = art.MAR_REF") as $row) {
                $src_nb_content["tarifs"] += 1;

                $name = addslashes(ucfirst(strtolower($row["ART_NOM"])));
                $code = $row["ART_CODE"];
                $unit = $row["ART_UNITE"];
                $mar_nom = addslashes(ucwords(strtolower($row["MAR_NOM"])));

                // Product (unité du tarif)

                $code_unit = "";
                $code_type = "";
                switch (strtoupper($unit)) {
                    case "M":
                        $code_unit = "LINE";
                        $code_type = "PA";
                        break;
                    case "ML":
                        $code_unit = "LINE";
                        $code_type = "PA";
                        break;
                    case "M2":
                        $code_unit = "SURF";
                        $code_type = "PA";
                        break;
                    case "M3":
                        $code_unit = "VOLU";
                        $code_type = "PA";
                        break;
                    case "FORFAIT":
                        $code_unit = "QUAN";
                        $code_type = "PACK";
                        break;
                    case "GRATUIT":
                        $code_unit = "QUAN";
                        $code_type = "PACK";
                        break;
                    default:
                        $code_unit = "LINE";
                        $code_type = "PA";
                        array_push($warnings, "L'unité $unit est inconnue, il faut trouver une équivalence GEODP v1 - GEODP v2");
                        break;
                }

                $req_calcul_base = pg_query("SELECT id FROM $dest_calcul_base WHERE id_type_domain = '$type_domain_id' AND code = '$code_unit' AND del = false");
                $calcul_base = NULL;
                if (pg_num_rows($req_calcul_base) > 0) {
                    $calcul_base = pg_fetch_object($req_calcul_base)->id;
                } else {
                    // Calcul base

                    $name_unit = "";
                    switch ($code_unit) {
                        case "LINE":
                            $name_unit = "Linéaire";
                            break;
                        case "SURF":
                            $name_unit = "Surface";
                            break;
                        case "QUAN":
                            $name_unit = "Quantité";
                            break;
                        case "VOLU":
                            $name_unit = "Volume";
                            break;
                        default:
                            $name_unit = "Linéaire";
                            break;
                    }

                    $values = ["'$type_domain_id'", "'$code_unit'", "'$name_unit'", "NULL", "NULL", "'$user_id'", "'$today'"];
                    $calcul_base = insert_into($dest_calcul_base, $calcul_base_cols, $values, $nb_to_execute, $nb_executed);
                    
                    // Calcul base field

                    $values = ["'$calcul_base'", "'Longueur'", "'$user_id'", "'$today'"];
                    $longueur = insert_into($calcul_base_fields, $calcul_base_fields_cols, $values, $nb_to_execute, $nb_executed);

                    $values = ["'$calcul_base'", "'Largeur'", "'$user_id'", "'$today'"];
                    $largeur = insert_into($calcul_base_fields, $calcul_base_fields_cols, $values, $nb_to_execute, $nb_executed);

                    $values = ["'$calcul_base'", "'Hauteur'", "'$user_id'", "'$today'"];
                    $hauteur = insert_into($calcul_base_fields, $calcul_base_fields_cols, $values, $nb_to_execute, $nb_executed);

                    $values = ["'$calcul_base'", "'Quantité'", "'$user_id'", "'$today'"];
                    $quantite = insert_into($calcul_base_fields, $calcul_base_fields_cols, $values, $nb_to_execute, $nb_executed);
                    
                    // Calcul base

                    $calcul = "";
                    $calcul_name = "";
                    switch ($code_unit) {
                        case "LINE":
                            $calcul_name = "[[Longueur]]";
                            $calcul = "[[$longueur]]";
                            break;
                        case "SURF":
                            $calcul_name = "[[Longueur]] * [[Largeur]]";
                            $calcul = "[[$longueur]] * [[$largeur]]";
                            break;
                        case "QUAN":
                            $calcul_name = "[[Quantité]]";
                            $calcul = "[[$quantite]]";
                            break;
                        case "VOLU":
                            $calcul_name = "[[Longueur]] * [[Largeur]] * [[Hauteur]]";
                            $calcul = "[[$longueur]] * [[$largeur]] * [[$hauteur]]";
                            break;
                        default:
                            $calcul_name = "[[Longueur]]";
                            $calcul = "[[$longueur]]";
                            break;
                    }

                    $update_query = "UPDATE $dest_calcul_base SET calcul = '$calcul', calcul_name = '$calcul_name' WHERE id = '$calcul_base'";
                    execute_query($update_query, $nb_to_execute, $nb_executed);
                } // Fin "si calcul base n'existe pas"

                $req_management_calcul = pg_query("SELECT id FROM $dest_management_calcul WHERE id_type_domain = '$type_domain_id' AND code = '$code_type' AND del = false");
                $management_calcul = NULL;
                if (pg_num_rows($req_management_calcul) > 0) {
                    $management_calcul = pg_fetch_object($req_management_calcul)->id;
                } else {
                    // Management calcul

                    $name_management = "";
                    switch ($code_type) {
                        case "PA":
                            $name_management = "Article";
                            break;
                        case "PACK":
                            $name_management = "Forfait";
                            break;
                        default:
                            $name_management = "Forfait";
                            break;
                    }

                    $values = ["'$type_domain_id'", "'$name_management'", "'$code_type'", "'$user_id'", "'$today'"];
                    $management_calcul = insert_into($dest_management_calcul, $management_calcul_cols, $values, $nb_to_execute, $nb_executed);
                } // Fin "si management calcul n'existe pas"
                
                $event = pg_fetch_object(pg_query("SELECT id FROM $dest_marche WHERE name = '$mar_nom' AND del = false ORDER BY date_create DESC"))->id; // Peu précis ; risque de tomber sur un marché non désiré

                $values = ["'$domain_id'", "'$calcul_base'", "'$management_calcul'", "'$event'", "'$code'", "'$name'", "'$name'", "'$unit'", "'$user_id'", "'$today'"];
                $product = insert_into($dest_article, $article_cols, $values, $nb_to_execute, $nb_executed);

                // Price (nom du tarif, un price par type de tarif)

                $calcul_mode = pg_fetch_object(pg_query("SELECT id FROM $dest_calcul_mode WHERE code = 'PACK' AND del = false"))->id;
                $charging_mode = pg_fetch_object(pg_query("SELECT id FROM $dest_charging_mode WHERE code = 'PACK' AND del = false"))->id;
                $vat = pg_fetch_object(pg_query("SELECT id FROM $dest_vat WHERE name LIKE '_aux _ormal' AND del = false"))->id;

                $date_start = $row["ART_VALIDE_DEPUIS"];
                $date_end = $row["ART_VALIDE_JUSQUA"];
                $art_code = floatval($row["ART_CODE"]);
                $art_ttc = floatval($row["ART_PRIX_TTC"]);
                $art_ttc_abo = floatval($row["ART_ABO_PRIX_TTC"]);

                $tarif_abonne = ($art_ttc_abo !== 0); // tarif abo si prix abo != 0
                $tarif_non_abonne = ($art_ttc !== 0 || !$tarif_abonne || $art_code !== ""); // tarif non abo si prix non abo != 0 ou si pas tarif abo (tarif non abo à 0 euros possible) ou si code pda

                if ($tarif_abonne) { // Insérer un tarif abo
                    $values = ["'$product'", "'$calcul_mode'", "'$charging_mode'", "'$vat'", "'$name'", "false", "'$user_id'", "'$today'"];
                    $price = insert_into($dest_tarif, $tarif_cols, $values, $nb_to_execute, $nb_executed);
                    
                    // Price attendance (type du tarif - fréquentation)

                    $code_attendance = "SUB";
                    $attendance = pg_fetch_object(pg_query("SELECT id FROM $dest_attendance WHERE code = '$code_attendance' AND del = false"))->id;;

                    $values = ["'$price'", "'$attendance'", "'$user_id'", "'$today'"];
                    insert_into($dest_tarif_attendance, $tarif_attendance_cols, $values, $nb_to_execute, $nb_executed);

                    // Price détail (prix du tarif)

                    $values = ["'$price'", "'$date_start'", "'$date_end'", $art_ttc_abo, "'$user_id'", "'$today'"];
                    insert_into($dest_tarif_detail, $tarif_detail_cols, $values, $nb_to_execute, $nb_executed);
                }

                if ($tarif_non_abonne) { // Insérer un autre tarif
                    $codes_attendance = ["HOLD", "PAS"];

                    foreach ($codes_attendance as $code_attendance) {
                        $values = ["'$product'", "'$calcul_mode'", "'$charging_mode'", "'$vat'", "'$name'", "false", "'$user_id'", "'$today'"];
                        $price = insert_into($dest_tarif, $tarif_cols, $values, $nb_to_execute, $nb_executed);
                        
                        // Price attendance (type du tarif - fréquentation)
    
                        $attendance = pg_fetch_object(pg_query("SELECT id FROM $dest_attendance WHERE code = '$code_attendance' AND del = false"))->id;;
    
                        $values = ["'$price'", "'$attendance'", "'$user_id'", "'$today'"];
                        insert_into($dest_tarif_attendance, $tarif_attendance_cols, $values, $nb_to_execute, $nb_executed);
    
                        // Price détail (prix du tarif)
    
                        $values = ["'$price'", "'$date_start'", "'$date_end'", $art_ttc, "'$user_id'", "'$today'"];
                        insert_into($dest_tarif_detail, $tarif_detail_cols, $values, $nb_to_execute, $nb_executed);
                    } // Fin "pour chaque code intendance"
                }
            }
            if ($display_dest_requests) echo "</div>";

            summarize_queries($nb_executed, $nb_to_execute, $nb_errors, $warnings, $nb_warnings);

            $nb_tarifs = pg_fetch_object(pg_query("SELECT COUNT(*) FROM $dest_tarif"))->count - $nb_tarifs;
            $dest_nb_content["tarifs"] = $nb_tarifs;
            
            // Activités commerciales

            echo "<h2 id=\"activites_commerciales\">Activités commerciales<span><tt>$dest_activite</tt></span></h2>";

            $src_nb_content["activités_commerciales"] = 0;
            $dest_nb_content["activités_commerciales"] = 0;

            $nb_to_execute = 0;
            $nb_executed = 0;

            $nb_activites_commerciales = pg_fetch_object(pg_query("SELECT COUNT(*) FROM $dest_activite"))->count;

            if ($display_dest_requests) echo "<div class=\"pre\">";
            foreach ($src_conn->query("SELECT ACO_NOM FROM $src_activite_commerciale_lang WHERE DCREAT > '$dates_max_birth'") as $row) {
                $src_nb_content["activités_commerciales"] += 1;

                $name = addslashes(ucfirst(strtolower($row["ACO_NOM"])));

                $values = ["'$name'", "'$user_id'", "'$today'"];
                insert_into($dest_activite, $activite_cols, $values, $nb_to_execute, $nb_executed);
            }
            if ($display_dest_requests) echo "</div>";

            summarize_queries($nb_executed, $nb_to_execute, $nb_errors, [], $nb_warnings);

            $nb_activites_commerciales = pg_fetch_object(pg_query("SELECT COUNT(*) FROM $dest_activite"))->count - $nb_activites_commerciales;
            $dest_nb_content["activités_commerciales"] = $nb_activites_commerciales;

            // Commerçants

            echo "<h2 id=\"commercants\">Commerçants<span><tt>$dest_address</tt> / <tt>$dest_commercant</tt></span></h2>";

            $src_nb_content["commerçants"] = 0;
            $dest_nb_content["commerçants"] = 0;

            $nb_to_execute = 0;
            $nb_executed = 0;
            $warnings = [];

            $nb_commercants = pg_fetch_object(pg_query("SELECT COUNT(*) FROM $dest_commercant"))->count;

            if ($display_dest_requests) echo "<div class=\"pre\">";
            foreach ($src_conn->query("SELECT exp.*, acol.ACO_NOM FROM $src_exploitant exp, $src_activite_commerciale_lang acol WHERE exp.DCREAT > '$dates_max_birth' AND exp.EXP_VISIBLE = 1 AND acol.ACO_REF = exp.ACO_REF") as $row) {
                $src_nb_content["commerçants"] += 1;

                $aco_nom = addslashes(ucfirst(strtolower($row["ACO_NOM"])));
                $civ_ref = $row["CIV_REF"];
                // $first_name = $row[""];
                
                $civility = NULL;
                if ($civ_ref) {
                    $civ_val = $src_conn->query("SELECT CIV_VALEUR FROM $src_civilite_lang WHERE CIV_REF = " . $civ_ref)->fetch()[0];

                    $name_civility = NULL;
                    switch ($civ_val) { // |v1.civilités| > |v2.civilités|
                        case "Mme" :
                            $name_civility = "_adame";
                            break;
                        case "Mlle" :
                            $name_civility = "_adame";
                            break;
                        case "M" :
                            $name_civility = "_onsieur";
                            break;
                        default:
                            $name_civility = NULL;
                            break;
                    }

                    if ($name_civility) {
                        $civility = pg_fetch_object(pg_query("SELECT id FROM $dest_civility WHERE name LIKE '$name_civility' AND del = false"))->id;
                    }
                }

                $activity = pg_fetch_object(pg_query("SELECT id FROM $dest_activite WHERE name = '$aco_nom' AND del = false ORDER BY date_create DESC"))->id; // Peu précis ; risque de tomber sur une activité non désirée
                
                $address = NULL;
                {
                    // Address

                    $exp_nrue = $row["EXP_NRUE"];
                    $exp_adresse = ucwords(strtolower($row["EXP_ADRESSE"]));
                    $exp_cp = $row["EXP_CP"];
                    $exp_ville = strtoupper(strtolower($row["EXP_VILLE"]));
                    
                    $values = [addslashes_nullify($exp_nrue), addslashes_nullify($exp_adresse), addslashes_nullify($exp_cp), addslashes_nullify($exp_ville), "'$user_id'", "'$today'"];
                    $address = insert_into($dest_address, $address_cols, $values, $nb_to_execute, $nb_executed);
                }

                $values = ["'$domain_id'", addslashes_nullify($civility), "'$activity'", "'$address'", "''", "''", "''", "''", "''", "''", "''", "''", "''", "''", "''", "''", "''", "''", "''", "''", "''", "'$user_id'", "'$today'"];
                // insert_into($dest_commercant, $commercant_cols, $values, $nb_to_execute, $nb_executed);
            }
            if ($display_dest_requests) echo "</div>";

            summarize_queries($nb_executed, $nb_to_execute, $nb_errors, $warnings, $nb_warnings);

            $nb_commercants = pg_fetch_object(pg_query("SELECT COUNT(*) FROM $dest_commercant"))->count - $nb_commercants;
            $dest_nb_content["commerçants"] = $nb_commercants;
            
            
            echo "</section>";

            ?>

            <script>
                var dom_nb_errors = document.querySelector("#nb_errors");

                    var nb_errors = parseInt(<?php echo $nb_errors; ?>);
                    dom_nb_errors.innerHTML = (nb_errors === 0) ? "<ok></ok>" : "<nok></nok>";
                    dom_nb_errors.innerHTML += "<br><br>";
                    dom_nb_errors.innerHTML += "Reprise terminée avec " + nb_errors + " erreurs";

                var dom_nb_content = document.querySelector("#nb_content");

                    var nb_marches_src = parseInt(<?php echo $src_nb_content["marchés"]; ?>);
                    var nb_marches_dest = parseInt(<?php echo $dest_nb_content["marchés"]; ?>);
                    var nb_tarifs_src = parseInt(<?php echo $src_nb_content["tarifs"]; ?>);
                    var nb_tarifs_dest = parseInt(<?php echo $dest_nb_content["tarifs"]; ?>);
                    var nb_activites_src = parseInt(<?php echo $src_nb_content["activités_commerciales"]; ?>);
                    var nb_activites_dest = parseInt(<?php echo $dest_nb_content["activités_commerciales"]; ?>);
                    var nb_commercants_src = parseInt(<?php echo $src_nb_content["commerçants"]; ?>);
                    var nb_commercants_dest = parseInt(<?php echo $dest_nb_content["commerçants"]; ?>);

                    dom_nb_content.innerHTML += "<tr><td>" + nb_marches_dest + "/" + nb_marches_src + "</td><td>" + nb_tarifs_dest + "/" + nb_tarifs_src + "</td><td>" + nb_activites_dest + "/" + nb_activites_src + "</td><td>" + nb_commercants_dest + "/" + nb_commercants_src + "</td></tr>";
            </script>

            <?php

        } // Fin "si $get transfert = 1"

    ?>

<layer><message></message></layer>

</body>

<script src="../js/script.js"></script>

</html>
