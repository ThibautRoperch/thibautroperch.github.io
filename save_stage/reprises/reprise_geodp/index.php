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

$src_activite_commerciale = "ACTIVITECOMMERCIALE";
$src_activite_commerciale_lang = "ACTIVITECOMMERCIALE_LANGUE";

$src_piece = "SOCIETE_PROPRIETE";
$src_piece_val = "SOCIETE_PROPRIETE_VALEUR";
$src_piece_lang = "SOCIETE_PROPRIETE_LANGUE";

$src_exploitant = "EXPLOITANT";

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

$dest_marche = "geodp_event";

$dest_tarif = "geodp_price";
// $dest_tarif = "geodp_price";

$dest_activite = "geodp_commercial_activity";

// $dest_piece = "SOCIETE_PROPRIETE";
// $dest_piece_val = "SOCIETE_PROPRIETE_VALEUR";
// $dest_piece_lang = "SOCIETE_PROPRIETE_LANGUE";

$dest_exploitant = "geodp_companies";

$dest_facture = "geodp_invoice";

$dest_host = (isset($_POST["dest_host"]) && $_POST["dest_host"] !== "") ? $_POST["dest_host"] : "192.168.1.36";
$dest_port = (isset($_POST["dest_port"]) && $_POST["dest_port"] !== "") ? $_POST["dest_port"] : "6543";
$dest_database = (isset($_POST["dest_database"]) && $_POST["dest_database"] !== "") ? $_POST["dest_database"] : "TRO";
$dest_user = (isset($_POST["dest_user"]) && $_POST["dest_user"] !== "") ? $_POST["dest_user"] : "postgres";
$dest_password = (isset($_POST["dest_password"]) && $_POST["dest_password"] !== "") ? $_POST["dest_password"] : $dest_user;

$dest_conn = pg_connect("host=$dest_host port=$dest_port dbname=$dest_database user=$dest_user password=$dest_password");

$dest_nb_content = []; // auto-computed


/*************************
 *        OPTIONS        *
 *************************/

// Date de création maximum des données à transférer
$dates_max_birth = date("d/m/y", strtotime("-6 months")); // avec '6' l'âge max des données en mois

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

function insert_into($table, $cols, $values, &$nb_executed, &$nb_to_execute) {
    $query = "INSERT INTO $table (" . implode(", ", $cols) . ") VALUES (" . implode(", ", $values) . ")";
    execute_query($query, $nb_executed, $nb_to_execute);
}

function execute_query($query, &$nb_executed, &$nb_to_execute) {
    global $display_dest_requests, $exec_dest_requests, $dest_conn, $output_file;

    if ($display_dest_requests) echo "$query<br>";

    fwrite($output_file, "$query;\n");

    if ($exec_dest_requests) {
        ++$nb_to_execute;
        $req_res = pg_query($dest_conn, $query);
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

function summarize_queries($nb_executed, $nb_to_execute, &$nb_errors) {
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
                echo "<li><a href=\"#transfert\">Transfert des données</a>";
                    echo "<ol>";
                        echo "<li><a href=\"#marches\">Marchés</a></li>";
                        echo "<li><a href=\"#tarifs\">Tarifs</a></li>";
                        echo "<li><a href=\"#exploitants\">Exploitants</a></li>";
                    echo "</ol>";
                echo "</li>";
                echo "<li><a href=\"$script_file_name\">Retour à l'accueil</a></li>";
            echo "</ol>";
            echo "</aside>";

            echo "<section>";

            echo "<summary id=\"summary\">";
                echo "<h1>$nom_reprise</h1>";
                echo "<p id=\"nb_errors\">Reprise en cours</p>";
                echo "<table id=\"nb_content\"><tr><th>Marchés</th><th>Exploitants</th></tr></table>";
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

            //// Transfert des données

            echo "<h1 id=\"transfert\">Transfert des données</h1>";

            $marche_cols = ["id_domain", "name", "id_user_create", "date_create"];
            
            $today = date("d/m/y", time());
            $user_id = pg_fetch_object(pg_query("SELECT id FROM $dest_utilisateur WHERE last_name LIKE 'ILTR' AND first_name LIKE 'Iltr'"))->id;
            $domain_id = pg_fetch_object(pg_query("SELECT id FROM $dest_domaine WHERE name LIKE 'Placier' AND del = false"))->id;

            $nb_errors = 0;
            
            // Marchés

            echo "<h2 id=\"marches\">Marchés</h2>";

            $src_nb_content["marchés"] = 0;
            $dest_nb_content["marchés"] = 0;

            $nb_to_execute = 0;
            $nb_executed = 0;

            $nb_marches = pg_fetch_object(pg_query("SELECT COUNT(*) FROM $dest_marche"))->count;

            if ($display_dest_requests) echo "<div class=\"pre\">";
            foreach ($src_conn->query("SELECT MAR_REF FROM $src_marche WHERE DCREAT > '$dates_max_birth'") as $row) {
                $src_nb_content["marchés"] += 1;

                $name = $src_conn->query("SELECT MAR_NOM FROM $src_marche_lang WHERE MAR_REF = " . $row["MAR_REF"])->fetch()[0];

                $values = ["'$domain_id'", "'$name'", "'$user_id'", "'$today'"];
                // insert_into($dest_marche, $marche_cols, $values, $nb_to_execute, $nb_executed);
            }
            if ($display_dest_requests) echo "</div>";

            summarize_queries($nb_executed, $nb_to_execute, $nb_errors);

            $nb_marches = pg_fetch_object(pg_query("SELECT COUNT(*) FROM $dest_marche"))->count - $nb_marches;
            $dest_nb_content["marchés"] = $nb_marches;

            // Tarifs

            echo "<h2 id=\"tarifs\">Tarifs</h2>";

            $src_nb_content["tarifs"] = 0;
            $dest_nb_content["tarifs"] = 0;

            $nb_to_execute = 0;
            $nb_executed = 0;

            $nb_tarifs = pg_fetch_object(pg_query("SELECT COUNT(*) FROM $dest_tarif"))->count;

            if ($display_dest_requests) echo "<div class=\"pre\">";
            foreach ($src_conn->query("SELECT MAR_REF FROM $src_article WHERE DCREAT > '$dates_max_birth'") as $row) {
                $src_nb_content["tarifs"] += 1;

                $tarif_cols = ["id_domain", "name", "id_user_create", "date_create"];

                // $name = $src_conn->query("SELECT MAR_NOM FROM $src_marche_lang WHERE MAR_REF = " . $row["MAR_REF"])->fetch()[0];

                $values = ["'$domain_id'", "'$name'", "'$user_id'", "'$today'"];
                // insert_into($dest_tarif, $tarif_cols, $values, $nb_to_execute, $nb_executed);
            }
            if ($display_dest_requests) echo "</div>";

            summarize_queries($nb_executed, $nb_to_execute, $nb_errors);

            $nb_marches = pg_fetch_object(pg_query("SELECT COUNT(*) FROM $dest_tarif"))->count - $nb_tarifs;
            $dest_nb_content["tarifs"] = $nb_marches;


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

                    dom_nb_content.innerHTML += "<tr><td>" + nb_marches_dest + "/" + nb_marches_src + "</td><td>" + nb_tarif_dest + "/" + nb_tarifs_src + "</td></tr>";
            </script>

            <?php

        } // Fin "si $get transfert = 1"

    ?>

<layer><message></message></layer>

</body>

<script src="../js/script.js"></script>

</html>
