<?php

// Fichier contenant les variables paramétrables utilisées dans le script de conversion de données dibtic contenues dans un fichier vers le format des tables GEODP v1

/*************************
 *        FICHIERS       *
 *************************/

// Répertoire contenant les fichiers sources (dibtic)
$directory = ".";

// Résumé du contenu des fichiers sources (dibtic)
$expected_content = ["marchés", "articles", "exploitants", "activités"];

// Noms possibles des fichiers sources (dibtic)
$keywords_files = ["marche/marché", "classe/article/tarif", "exploitant/assujet", "activite/activité"];

// Nom du fichier de sortie contenant les requêtes SQL à envoyer (GEODP v1)
$output_filename = "output.sql"; // Si un fichier avec le même nom existe déjà, il sera écrasé


/*************************
 *    TABLES SOURCES     *
 *************************/

$src_tables = []; // auto-computed from filenames and based on the same indexation as $expected_content and $keywords_files
$src_prefixe = "src_";


/*************************
 * TABLES DE DESTINATION *
 *************************/

// Mettre à vrai pour supprimer les données des tables de destination avant l'insertion des nouvelles, mettre à faux sinon
$erase_destination_tables = true; // true | false

// Nom des tables de destination (GEODP v1)
$dest_marche = "dest_marche";
$dest_marche_lang = "dest_marche_langue";
$dest_article = "dest_article";
$dest_article_lang = "dest_article_langue";
$dest_exploitant = "dest_exploitant";
$dest_societe_marche = "dest_societe_marche";
$dest_activite = "dest_activitecommerciale";
$dest_activite_lang = "dest_activitecommerciale_langue";

// Valeurs des champs DCREAT et UCREAT utilisées dans les requêtes SQL
$dest_dcreat = date("d/m/y", time());
$dest_ucreat = "ILTR";


/*************************
 *    CONNEXION MySQL    *
 *************************/

$host = "localhost";
$dbname = "reprise_dibtic";
$login = "root";
$password = "";

?>
