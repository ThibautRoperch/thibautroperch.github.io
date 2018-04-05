<!-- http://coursesweb.net/php-mysql/phpspreadsheet-read-write-excel-libreoffice-files -->

<?php

require("common.php");

/*************************
 *    CONNEXION MySQL    *
 *************************/

$host = "localhost";
$dbname = "reprise_dibtic";
$login = "root";
$password = "";

?>

<?php

function prune($str) {
    return substr($str, 0, strrpos($str, '.'));
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
                    echo "<li><a href=\"#marches\">Marchés</a></li>";
                    echo "<li><a href=\"#articles\">Articles</a></li>";
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

        //// Extraction des nouvelles informations

        echo "<h1 id=\"destination\">Extraction des nouvelles informations</h1>";

        // Marchés

        echo "<h2 id=\"marches\">Marchés</h2>";

        if ($erase_destination_tables) {
            $conn->exec("DELETE FROM $dest_marche");
            $conn->exec("DELETE FROM $dest_marche_lang");
        }

        $dest_marche_cols = ["MAR_REF", "UTI_REF", "ACT_REF", "MAR_VILLE", "MAR_JOUR"];
        $dest_marche_lang_cols = ["MAR_REF", "LAN_REF", "MAR_NOM", "DCREAT", "UCREAT"];

        $src_marche = $src_tables[array_search("marchés", $expected_content)];

        $req_last_mar_ref = $conn->query("SELECT * FROM $dest_marche ORDER BY MAR_REF DESC LIMIT 1")->fetch();
        $last_mar_ref = ($req_last_mar_ref == null || count($req_last_mar_ref) == 0) ? 0 : $req_last_mar_ref[0]["MAR_REF"];

        echo "<pre>";
        foreach ($conn->query("SELECT * FROM $src_marche") as $row) {
            $dest_marche_values = [];

            foreach ($dest_marche_cols as $col) {
                switch ($col) {
                    case "MAR_REF":
                        $last_mar_ref += 1;
                        array_push($dest_marche_values, "'$last_mar_ref'");
                        break;
                    case "UTI_REF":
                        array_push($dest_marche_values, "'1'");
                        break;
                    case "ACT_REF":
                        array_push($dest_marche_values, "'2'");
                        break;
                    case "MAR_VILLE":
                        array_push($dest_marche_values, "'$city'");
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
            $conn->exec($insert_into_query);

            // Marché langue

            $dest_marche_lang_values = [$last_mar_ref, 1, $row["libelle"], date("d/m/y", time()), "REPRISE"];

            $insert_into_query = "INSERT INTO $dest_marche_lang (" . implode(", ", $dest_marche_lang_cols) . ") VALUES ('" . implode("', '", $dest_marche_lang_values) . "')";

            echo "$insert_into_query<br>";
            $conn->exec($insert_into_query);
        }
        echo "</pre>";

        // Articles
        
        echo "<h2 id=\"articles\">Articles</h2>";

        if ($erase_destination_tables) {
            $conn->exec("DELETE FROM $dest_article");
            $conn->exec("DELETE FROM $dest_article_lang");
        }

        $dest_article_cols = ["ART_REF", "MAR_REF", "ART_PRIX_HT"]; // utiliser les abos ?
        $dest_article_lang_cols = ["ART_REF", "LAN_REF", "ART_NOM", "ART_UNITE", "DCREAT", "UCREAT"];

        $src_article = $src_tables[array_search("articles", $expected_content)];
        
        $req_last_art_ref = $conn->query("SELECT * FROM $dest_article ORDER BY ART_REF DESC LIMIT 1")->fetch();
        $last_art_ref = ($req_last_art_ref == null || count($req_last_art_ref) == 0) ? 0 : $req_last_art_ref[0]["ART_REF"];

        echo "<pre>";
        foreach ($conn->query("SELECT * FROM $src_article") as $row) {
            if ($row["nom"] !== "") {
                $dest_article_values = [];

                foreach ($dest_article_cols as $col) {
                    switch ($col) {
                        case "ART_REF":
                            $last_art_ref += 1;
                            array_push($dest_article_values, "'$last_art_ref'");
                            break;
                        default:
                            array_push($dest_article_values, "'TODO'");
                            break;
                    }
                }

                $insert_into_query = "INSERT INTO $dest_article (" . implode(", ", $dest_article_cols) . ") VALUES (" . implode(", ", $dest_article_values) . ")";

                echo "$insert_into_query<br>";
                $conn->exec($insert_into_query);

                // Article langue

                $dest_article_lang_values = [$last_art_ref, 1, $row["nom"],  $row["unite"], date("d/m/y", time()), "REPRISE"];

                $insert_into_query = "INSERT INTO $dest_article_lang (" . implode(", ", $dest_article_lang_cols) . ") VALUES ('" . implode("', '", $dest_article_lang_values) . "')";

                echo "$insert_into_query<br>";
                $conn->exec($insert_into_query);
            }
        }
        echo "</pre>";

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
