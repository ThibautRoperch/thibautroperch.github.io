<?php

/**
 * (Re)créé toutes les tables à partir des JSON
 * Attention, videra toutes les données de ces tables !
 * A n'utiliser que pour des tests, utiliser le script associé à chaque JSON en temps normal
 */

$tables = [];

require_once("mysql_conn.php");

foreach (scandir(".") as $filename) {
    preg_match("/(.+)\.json$/", $filename, $matches);
    if (isset($matches[0])) {
        $tables[$matches[1]] = $filename;
    }
}

foreach ($tables as $table_name => $table_file) {

    // Récupération des colonnes de la table dans le fichier

    $json = json_decode(file_get_contents($table_file));

    $columns = ["`id` INT PRIMARY KEY NOT NULL AUTO_INCREMENT"];
    foreach ($json->catégories as $categorie) {
        foreach ($categorie->critères as $critere) {
            $col_name = $critere->nom;
            $col_type = $critere->type;
            $col_comment = addslashes($critere->description);

            if ($categorie === "@") { // clés étrangères
                $col_type = "INT";
            } else {
                switch ($col_type) {
                    case "entier":
                        $col_type = "INT";
                        break;
                    case "réel":
                        $col_type = "FLOAT";
                        break;
                    case "booléen":
                        $col_type = "BOOL";
                        break;
                    case "timestamp":
                        $col_type = "TIMESTAMP";
                        break;
                    default:
                        $col_type = "FLOAT";
                        break;
                }
            }

            $col_str = "`$col_name` $col_type DEFAULT NULL COMMENT '$col_comment'";
            array_push($columns, $col_str);
        }
    }

    // Création des requêtes

    $requests = [];

    $drop_table_str = "DROP TABLE IF EXISTS $table_name";
    array_push($requests, $drop_table_str);

    $columns_str = implode(", ", $columns);
    $create_table_str = "CREATE TABLE IF NOT EXISTS $table_name ($columns_str)";
    array_push($requests, $create_table_str);

    // Exécution des requêtes

    foreach ($requests as $request) {
        $mysql_conn->exec($request);
    }
}

?>
