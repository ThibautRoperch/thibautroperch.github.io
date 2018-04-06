<!-- http://coursesweb.net/php-mysql/phpspreadsheet-read-write-excel-libreoffice-files -->

<?php

// Entrée : Fichiers Excel dibtic définis dans common.php et récupérés par le script index.php
// Sortie : Fichier SQL contenant les requêtes SQL à envoyer dans la base de données GEODP v1

require("common.php");

?>

<?php

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

$conn = new PDO("mysql:host=$host;dbname=$dbname", "$login", "$password");

$source_files = (isset($_GET["source_files"]) && $_GET["source_files"] != "") ? explode(",", $_GET["source_files"]) : [];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Conversion dibtic - GEODP v1</title>
</head>
<body>
    
    <?php
        
        //// Sommaire

        echo "<ol>";
            echo "<li><a href=\"#source\">Traitement des fichiers sources (tables dibtic)</a>";
                echo "<ol>";
                    foreach ($expected_content as $exp) {
                        echo "<li><a href=\"#" . prune($_GET[$exp]) . "\">Fichier des $exp</a></li>";
                    }
                echo "</ol>";
            echo "</li>";
            echo "<li><a href=\"#destination\">Extraction des nouvelles informations (tables GEODP v1)</a>";
                echo "<ol>";
                    echo "<li><a href=\"#marches\">Marchés / Marchés Langue</a></li>";
                    echo "<li><a href=\"#articles\">Articles / Articles Langue</a></li>";
                    echo "<li><a href=\"#activites\">Activités / Activités Langue</a></li>";
                    echo "<li><a href=\"#exploitants\">Exploitants</a></li>";
                echo "</ol>";
            echo "</li>";
        echo "</ol>";
        
        //// Traitement des fichiers sources (tables dibtic)

        // Pour chaque fichier source (pour chaque fichier attendu, il y a le chemin du fichier en $_GET), lecture + table + insertions

        echo "<h1 id=\"source\">Traitement des fichiers sources</h1>";

        foreach ($expected_content as $exp) {
            $file = $_GET[$exp];

            echo "<h2 id=\"" . prune($file) . "\">Fichier des $exp</h2>";

            // Lecture du fichier
/*
            echo "<h3>Lecture du fichier</h3>";

            require 'spreadsheet/vendor/autoload.php';

            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load("$directory/$file");

            $xls_data = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
            // var_dump($xls_data);

            $html_tb ='<table><tr><th>'. implode('</th><th>', $xls_data[1]) .'</th></tr>';
            for ($i = 2; $i <= count($xls_data); $i++) {
                $html_tb .='<tr><td>'. implode('</td><td>', $xls_data[$i]) .'</td></tr>';
            }
            $html_tb .='</table>';

            echo $html_tb;
*/
            // Création de la table correspondante

            echo "<h3>Création de la table correspondante</h3>";

            $source_table = $src_prefixe . prune($file);
            array_push($src_tables, $source_table);
/*
            $conn->exec("DROP TABLE IF EXISTS $source_table");

            $create_table_query = "";
            foreach ($xls_data[1] as $col) {
                if ($create_table_query === "") {
                    $create_table_query .= "`$col` VARCHAR(250) PRIMARY KEY";
                } else {
                    $create_table_query .= ", `$col` VARCHAR(250)";
                }
            }
            $create_table_query = "CREATE TABLE $source_table ($create_table_query)";

            echo "<pre>$create_table_query</pre>";
            $conn->exec($create_table_query);

            // Remplissage de la table créée avec les données du fichier

            echo "<h3>Remplissage de la table créée avec les données du fichier</h3>";

            echo "<pre>";
            for ($i = 2; $i <= count($xls_data); $i++) {
                $insert_into_query = "";
                foreach ($xls_data[$i] as $cel) {
                    if ($insert_into_query !== "") {
                        $insert_into_query .= ", ";
                    }
                    $insert_into_query .= "'$cel'";
                }
                $insert_into_query = "INSERT INTO $source_table VALUES ($insert_into_query)";

                echo "$insert_into_query<br>";
                $conn->exec($insert_into_query);
            }
            echo "</pre>";
*/
        }

        $src_marche = $src_tables[array_search("marchés", $expected_content)];
        $src_article = $src_tables[array_search("articles", $expected_content)];
        $src_activite = $src_tables[array_search("activités", $expected_content)];
        $src_exploitant = $src_tables[array_search("exploitants", $expected_content)];

        //// Extraction des nouvelles informations

        echo "<h1 id=\"destination\">Extraction des nouvelles informations</h1>";
        $output_file = fopen($output_filename, "w+");

        // Marchés / Marchés Langue

        echo "<h2 id=\"marches\">Marchés / Marchés Langue</h2>";
        fwrite($output_file, "\n-- Marchés / Marchés Langue\n\n");

        if ($erase_destination_tables) {
            $conn->exec("DELETE FROM $dest_marche");
            $conn->exec("DELETE FROM $dest_marche_lang");
        }

        $assoc_marcheCode_marcheRef = [];

        $dest_marche_cols = ["MAR_REF", "UTI_REF", "ACT_REF", "MAR_JOUR"];
        $dest_marche_lang_cols = ["MAR_REF", "LAN_REF", "MAR_NOM", "DCREAT", "UCREAT"];

        $req_last_mar_ref = $conn->query("SELECT MAR_REF FROM $dest_marche ORDER BY MAR_REF DESC LIMIT 1")->fetch();
        $last_mar_ref = ($req_last_mar_ref == null) ? 0 : $req_last_mar_ref["MAR_REF"];

        echo "<pre>";
        foreach ($conn->query("SELECT * FROM $src_marche") as $row) {
            $last_mar_ref += 1;

            $dest_marche_values = [];
            $assoc_marcheCode_marcheRef[$row["code"]] = $last_mar_ref;

            foreach ($dest_marche_cols as $col) {
                switch ($col) {
                    case "MAR_REF":
                        array_push($dest_marche_values, "'$last_mar_ref'");
                        break;
                    case "UTI_REF":
                        array_push($dest_marche_values, "'1'");
                        break;
                    case "ACT_REF":
                        array_push($dest_marche_values, "'2'");
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

            $insert_into_query = "INSERT INTO $dest_marche (" . implode(", ", $dest_marche_cols) . ") VALUES (" . implode(", ", $dest_marche_values) . ")";

            echo "$insert_into_query<br>";
            fwrite($output_file, "$insert_into_query;\n");
            $conn->exec($insert_into_query);

            // Marché langue

            $dest_marche_lang_values = [$last_mar_ref, 1, $row["libelle"], $dest_dcreat, $dest_ucreat];

            $insert_into_query = "INSERT INTO $dest_marche_lang (" . implode(", ", $dest_marche_lang_cols) . ") VALUES ('" . implode("', '", $dest_marche_lang_values) . "')";

            echo "$insert_into_query<br>";
            fwrite($output_file, "$insert_into_query;\n");
            $conn->exec($insert_into_query);
        }
        echo "</pre>";

        // Articles / Articles Langue

        echo "<h2 id=\"articles\">Articles / Articles Langue</h2>";
        fwrite($output_file, "\n-- Articles / Articles Langue\n\n");

        if ($erase_destination_tables) {
            $conn->exec("DELETE FROM $dest_article");
            $conn->exec("DELETE FROM $dest_article_lang");
        }

        $dest_article_cols = ["ART_REF", "MAR_REF", "UTI_REF", "ART_PRIX_TTC", "ART_PRIX_HT", "ART_TAUX_TVA", "ART_COULEUR", "DCREAT", "UCREAT"]; // utiliser les abos ? TODO
        $dest_article_lang_cols = ["ART_REF", "LAN_REF", "ART_NOM", "ART_UNITE", "DCREAT", "UCREAT"];
        
        $req_last_art_ref = $conn->query("SELECT ART_REF FROM $dest_article ORDER BY ART_REF DESC LIMIT 1")->fetch();
        $last_art_ref = ($req_last_art_ref == null) ? 0 : $req_last_art_ref["ART_REF"];

        echo "<pre>";
        foreach ($conn->query("SELECT * FROM $src_article WHERE nom != ''") as $row) {
            $last_art_ref += 1;

            $dest_article_values = [];

            $dest_marches_ref = [];
            $src_art_numc = ($row["numc"] === "1") ? "" : $row["numc"]; // `numc` est le numéro du groupe de l'article (il correspond à des colonnes, sauf quand `numc` vaut 1)
            // Regarder les cellules de la colonne m`numc` dans la table source des exploitants pour obtenir les codes des marchés de référence associés à l'article via les exploitants
            // Les marchés sont identifiés par MAR_REF dans la table de destination des marchés, identifiant à récupérer depuis le code précédemment obtenu via le tableau de correspondance code/MAR_REF
            // Chaque identifiant obtenu donnera lieu à une insertion de l'article
            // Il en résultera plusieurs lignes correspondant à un même article associé à des marchés différents
            $src_art_numc_column = "m$src_art_numc";
            foreach ($conn->query("SELECT $src_art_numc_column FROM $src_exploitant WHERE $src_art_numc_column != '' ") as $exploitant) {
                $src_mar_code = $exploitant["$src_art_numc_column"];
                $dest_mar_ref = $assoc_marcheCode_marcheRef[$src_mar_code];
                if (!in_array($dest_mar_ref, $dest_marches_ref)) array_push($dest_marches_ref, $dest_mar_ref);
            }

            foreach ($dest_marches_ref as $mar_ref) {
                foreach ($dest_article_cols as $col) {
                    switch ($col) {
                        case "ART_REF":
                            array_push($dest_article_values, "'$last_art_ref'");
                            break;
                        case "UTI_REF":
                            array_push($dest_article_values, "'1'");
                            break;
                        case "MAR_REF":
                            array_push($dest_article_values, "'$mar_ref'");
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

                echo "$insert_into_query<br>";
                fwrite($output_file, "$insert_into_query;\n");
                $conn->exec($insert_into_query);

                // Article langue

                $dest_article_lang_values = [$last_art_ref, 1, $row["nom"],  $row["unite"], $dest_dcreat, $dest_ucreat];

                $insert_into_query = "INSERT INTO $dest_article_lang (" . implode(", ", $dest_article_lang_cols) . ") VALUES ('" . implode("', '", $dest_article_lang_values) . "')";

                echo "$insert_into_query<br>";
                fwrite($output_file, "$insert_into_query;\n");
                $conn->exec($insert_into_query);
            } // Fin "pour chaque MAR_REF"
        }
        echo "</pre>";

        // Activités

        echo "<h2 id=\"actvites\">Activités / Activités Langue</h2>";
        fwrite($output_file, "\n-- Activités / Activités Langue\n\n");

        if ($erase_destination_tables) {
            $conn->exec("DELETE FROM $dest_activite");
            $conn->exec("DELETE FROM $dest_activite_lang");
        }
        
        $dest_activite_cols = ["ACO_REF", "UTI_REF", "ACO_COULEUR", "DCREAT", "UCREAT"];
        $dest_activite_lang_cols = ["ACO_REF", "LAN_REF", "ACO_NOM", "DCREAT", "UCREAT"];

        $req_last_act_ref = $conn->query("SELECT ACO_REF FROM $dest_activite ORDER BY ACO_REF DESC LIMIT 1")->fetch();
        $last_act_ref = ($req_last_act_ref == null) ? 0 : $req_last_act_ref["ACO_REF"];

        echo "<pre>";
        foreach ($conn->query("SELECT * FROM $src_activite") as $row) {
            $last_act_ref += 1;

            $dest_activite_values = [];
            
            foreach ($dest_activite_cols as $col) {
                switch ($col) {
                    case "ACO_REF":
                        array_push($dest_activite_values, "'$last_act_ref'");
                        break;
                    case "UTI_REF":
                        array_push($dest_activite_values, "'1'");
                        break;
                    case "ACO_COULEUR":
                        array_push($dest_activite_values, "'fff'");
                        break;
                    case "DCREAT":
                        array_push($dest_activite_values, "'$dest_dcreat'");
                        break;
                    case "UCREAT":
                        array_push($dest_activite_values, "'$dest_ucreat'");
                        break;
                    default:
                        array_push($dest_activite_values, "'TODO'");
                        break;
                }
            }

            $insert_into_query = "INSERT INTO $dest_activite (" . implode(", ", $dest_activite_cols) . ") VALUES (" . implode(", ", $dest_activite_values) . ")";

            echo "$insert_into_query<br>";
            fwrite($output_file, "$insert_into_query;\n");
            $conn->exec($insert_into_query);

            // Activité langue

            $dest_activite_lang_values = [$last_act_ref, 1, $row["activiti"], $dest_dcreat, $dest_ucreat];

            $insert_into_query = "INSERT INTO $dest_activite_lang (" . implode(", ", $dest_activite_lang_cols) . ") VALUES ('" . implode("', '", $dest_activite_lang_values) . "')";

            echo "$insert_into_query<br>";
            fwrite($output_file, "$insert_into_query;\n");
            $conn->exec($insert_into_query);
        }
        echo "</pre>";

        // Exploitants

        echo "<h2 id=\"exploitants\">Exploitants</h2>";
        fwrite($output_file, "\n-- Exploitants\n\n");

        if ($erase_destination_tables) {
            $conn->exec("DELETE FROM $dest_exploitant");
        }
        
        $dest_exploitant_cols = ["EXP_REF", "EXP_CODE", "UTI_REF", "GRA_REF", "LAN_REF", "ACO_REF", "EXP_NOM_PERS_PHYSIQUE", "EXP_PRENOM_PERS_PHYSIQUE", "EXP_RAISON_SOCIALE", "EXP_NOM", "EXP_VISIBLE", "EXP_VALIDE", "EXP_NRUE", "EXP_ADRESSE", "EXP_CP", "EXP_VILLE", "EXP_TELEPHONE", "EXP_PORTABLE", "EXP_FAX", "EXP_EMAIL"];
        
        $dest_activite_cols = ["ACO_REF", "UTI_REF", "ACO_COULEUR", "DCREAT", "UCREAT"];
        $dest_activite_lang_cols = ["ACO_REF", "LAN_REF", "ACO_NOM", "DCREAT", "UCREAT"];

        $req_last_exp_ref = $conn->query("SELECT EXP_REF FROM $dest_exploitant ORDER BY EXP_REF DESC LIMIT 1")->fetch();
        $last_exp_ref = ($req_last_exp_ref == null) ? 0 : $req_last_exp_ref["EXP_REF"];

        echo "<pre>";
        foreach ($conn->query("SELECT * FROM $src_exploitant WHERE date_suppr != '' AND date_suppr != '  -   -'") as $row) {
            $last_exp_ref += 1;

            $dest_exploitant_values = [];

            foreach ($dest_exploitant_cols as $col) {
                switch ($col) {
                    case "EXP_REF":
                        array_push($dest_exploitant_values, "'$last_exp_ref'");
                        break;
                    case "EXP_CODE":
                        array_push($dest_exploitant_values, "'".$row["ntiers"]."'");
                        break;
                    case "UTI_REF":
                        array_push($dest_exploitant_values, "'1'");
                        break;
                    case "GRA_REF":
                        array_push($dest_exploitant_values, "'1'");
                        break;
                    case "LAN_REF":
                        array_push($dest_exploitant_values, "'1'");
                        break;
                    case "ACO_REF":
                        $aco_ref = "NULL";
                        if ($row["type"] !== "") {
                            $req_aco_ref = $conn->query("SELECT ACO_REF FROM $dest_activite_lang WHERE ACO_NOM = '".strtoupper($row["type"])."'")->fetch();
                            $aco_ref = ($req_aco_ref == null) ? NULL : $req_aco_ref["ACO_REF"];
                            if ($aco_ref == NULL) {
                                echo "-- L'activité " . strtoupper($row["type"]) . " lue dans le fichier des exploitants est manquante dans le fichier des activités.<br>";
                                fwrite($output_file, "-- L'activité " . strtoupper($row["type"]) . " lue dans le fichier des exploitants est manquante dans le fichier des activités.\n");

                                $req_last_act_ref = $conn->query("SELECT ACO_REF FROM $dest_activite ORDER BY ACO_REF DESC LIMIT 1")->fetch();
                                $last_act_ref = ($req_last_act_ref == null) ? 0 : $req_last_act_ref["ACO_REF"];
                                ++$last_act_ref;

                                // Activité

                                $dest_activite_values = [$last_act_ref, 1, "fff", $dest_dcreat, $dest_ucreat];

                                $insert_into_query = "INSERT INTO $dest_activite (" . implode(", ", $dest_activite_cols) . ") VALUES ('" . implode("', '", $dest_activite_values) . "')";

                                echo "-- Correctif appliqué à la table $dest_activite :<br>$insert_into_query<br>";
                                fwrite($output_file, "-- Correctif appliqué à la table $dest_activite_lang :\n$insert_into_query;\n");
                                $conn->exec($insert_into_query);

                                // Activité langue

                                $dest_activite_lang_values = [$last_act_ref, 1, $row["type"], $dest_dcreat, $dest_ucreat];

                                $insert_into_query = "INSERT INTO $dest_activite_lang (" . implode(", ", $dest_activite_lang_cols) . ") VALUES ('" . implode("', '", $dest_activite_lang_values) . "')";

                                echo "-- Correctif appliqué à la table $dest_activite_lang :<br>$insert_into_query<br>";
                                fwrite($output_file, "-- Correctif appliqué à la table $dest_activite_lang :\n$insert_into_query;\n");
                                $conn->exec($insert_into_query);

                                echo "-- Fin des correctifs<br>";
                                fwrite($output_file, "-- Fin des correctifs\n");
                            }
                        }
                        array_push($dest_exploitant_values, "'$aco_ref'");
                        break;
                    case "EXP_NOM_PERS_PHYSIQUE":
                        if ($row["prenom"] !== "") {
                            array_push($dest_exploitant_values, "'".$row["nom_deb2"]."'");
                        } else {
                            array_push($dest_exploitant_values, "NULL");
                        }
                        break;
                    case "EXP_PRENOM_PERS_PHYSIQUE":
                        if ($row["prenom"] !== "") {
                            array_push($dest_exploitant_values, "'".$row["prenom"]."'");
                        } else {
                            array_push($dest_exploitant_values, "NULL");
                        }
                        break;
                    case "EXP_RAISON_SOCIALE":
                        array_push($dest_exploitant_values, "'".$row["nom_deb"]."'");
                        break;
                    case "EXP_NOM":
                        array_push($dest_exploitant_values, "'".$row["nom_deb"]."'");
                        break;
                    case "EXP_VISIBLE":
                        array_push($dest_exploitant_values, "'1'");
                        break;
                    case "EXP_VALIDE":
                        array_push($dest_exploitant_values, "'1'");
                        break;
                    case "EXP_NRUE":
                        array_push($dest_exploitant_values, "'".get_from_address("num", $row["adr1"])."'");
                        break;
                    case "EXP_ADRESSE":
                        array_push($dest_exploitant_values, "'".get_from_address("voie", $row["adr1"])."'");
                        break;
                    case "EXP_CP":
                        array_push($dest_exploitant_values, "'".$row["cpvil"]."'");
                        break;
                    case "EXP_VILLE":
                        array_push($dest_exploitant_values, "'".$row["adr3"]."'");
                        break;
                    case "EXP_TELEPHONE":
                        array_push($dest_exploitant_values, "'".$row["tel"]."'");
                        break;
                    case "EXP_PORTABLE":
                        array_push($dest_exploitant_values, "'".$row["tel_port"]."'");
                        break;
                    case "EXP_FAX":
                        array_push($dest_exploitant_values, "'".$row["fax"]."'");
                        break;
                    case "EXP_EMAIL":
                        array_push($dest_exploitant_values, "'".$row["mail"]."'");
                        break;
                    default:
                        array_push($dest_exploitant_values, "'TODO'");
                        break;
                }
            }

            $insert_into_query = "INSERT INTO $dest_exploitant (" . implode(", ", $dest_exploitant_cols) . ") VALUES (" . implode(", ", $dest_exploitant_values) . ")";

            echo "$insert_into_query<br>";
            fwrite($output_file, "$insert_into_query;\n");
            $conn->exec($insert_into_query);
        }
        echo "</pre>";

        // TODO abonnements (table dest_societe_marche)

        // TODO pièces justificatives (table SOCIETE_...)

    ?>

</body>
</html>


<style>

* {
    margin: 0;
    padding: 0;
    font-family: Helvetica;
    font-size: 14px;
}

body {
    padding: 10px;
    display: flex;
    flex-direction: column;
}

body * {
    flex: 1;
}

h1 {
    margin-top: 30px;
    padding: 5px 0;
    text-align: center;
    font-size: 130%;
    border-top: 1px solid black;
    border-bottom: 1px solid black;
    border-width: 3px;
    border-color: red;
}

h2 {
    margin: 30px 0 10px 0;
    padding: 5px 0;
    text-align: center;
    font-size: 120%;
    border: 1px solid black;
    background: orange;
}

h3 {
    margin: 20px 0;
    padding-left: 10px;
    font-size: 110%;
    border-left: 10px solid orange;
}

ol {
    list-style-type: decimal;
    padding-left: 20px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

table tr {
    border-bottom: 1px solid orange;
}

table td, table th {
    padding: 3px 4px;
}

pre {
    padding: 10px;
    font-family: Consolas;
    color: rgba(255, 255, 255, 0.7);
    background: rgba(0, 0, 0, 0.8);
    overflow: auto;
}

</style>
