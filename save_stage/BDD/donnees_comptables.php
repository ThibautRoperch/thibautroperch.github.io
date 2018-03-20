<?php

header("Access-Control-Allow-Origin: *");

$conn = new PDO('mysql:host=localhost;dbname=open_datas', 'root', '');

if (isset($_GET["ville"]) && $_GET["ville"] != "") {
    $ville = $_GET["ville"];
    $annee = (isset($_GET["annee"])) ? $_GET["annee"] : "";

    /*$where = "";
    $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'fiscalites_villes'";
    foreach ($conn->query($sql) as $row) {
        if ($where != "") $where .= " OR ";
        $where .= $row["COLUMN_NAME"] . " = '$search'";
    }

    $request = "SELECT * FROM fiscalites_villes WHERE annee = $annee AND ($where)";
    foreach ($conn->query($request) as $row) {
        var_dump($row);
    }*/

    $where_ville = "nom LIKE '%$ville%'";
    $where_annee = ($annee != "") ? "AND annee = '$annee'" : "";

    $result = array();
    $cities = array();
    $nhits = 0;

    $request = "SELECT * FROM fiscalites_villes WHERE $where_ville $where_annee";
    foreach ($conn->query($request) as $row) {
        if (!in_array($row["nom"], $cities)) {
            $cities[] = $row["nom"];
        }
        $index = array_search($row["nom"], $cities);

        $result["records"][$index]["name"] = $row["nom"];

        $result["records"][$index]["years"][$row["annee"]] = $row;

        $result["records"][$index]["nyears"] = count($result["records"][$index]["years"]);

        ++$nhits;
    }

    $result["ncities"] = count($cities);
    $result["nhits"] = $nhits;

    $json_result = json_encode($result);
    echo $json_result;
} else {
    $error = array();
    $error["reason"] = "Donner le nom de la ville (et optionnellement l'année)";
    $error["example"] = "Exemple : donnees_comptables.php?ville=MILESSE";

    echo json_encode($error);
}

?>
