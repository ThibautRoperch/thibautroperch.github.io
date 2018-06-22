<?php

require_once("../db/mysql_conn.php");
require_once("TableFromModel.php");

$table = new TableFromModel($mysql_conn, "placier_marche.json");
$table->loadModel();

// L'action doit être donnée dans l'URL
if (isset($_GET["action"])) {
    $action = $_GET["action"];

    // Si l'action est de créer la table
    if ($action === "create") {
        $table->createTable();
    }
    // Sinon, si l'action est de remplir la table avec une ville (prendre en argument un nom de ville pour permettre la parallélisation en JS du remplissage de la table placier_marche)
    elseif ($action === "fill") {
        if (isset($_GET["ville"])) {
            $ville = $_GET["ville"];

            // Suppression des données de cette ville déjà présentes dans la table

            // $table->deleteData("id_ville", $id_ville);

            // Insertion des données de cette ville

            // pour chaque marché de la ville, ajouter les infos qui nous intéressent -> insertion à vide, puis updates : dans un switch case, calculer la valeur en fonction du nom de la colonne, puis $table->updateData
            // $id_marche = $table->createData(["id_ville" => ["name", $ville]]); // crée une nouvelle donnée à update plus tard
        } else {
            echo "Nom de la ville requis (<tt>ville=nom_ville)";
        }
    } else {
        echo "<tt>action=create</tt> ou <tt>action=fill</tt>";
    }
} else {
    echo "Action requise (<tt>action=create</tt> ou <tt>action=fill</tt>)";
}

?>
