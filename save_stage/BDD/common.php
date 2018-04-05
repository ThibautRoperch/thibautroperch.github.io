<?php

$city = "LEUCATE";

/*************************
 *        FICHIERS       *
 *************************/

$directory = ".";
$expected_content = ["marchés", "articles", "exploitants", "activités"];
$keywords_files = ["marche/marché", "classe/article/tarif", "exploitant/assujet", "activite/activité"];

/*************************
 *    TABLES SOURCES     *
 *************************/

$src_tables = []; // auto-computed and based on the same indexation as $expected_files and $keywords_files
$src_prefixe = "src_";

/*************************
 * TABLES DE DESTINATION *
 *************************/

$erase_destination_tables = true;
$dest_marche = "dest_marche";
$dest_marche_lang = "dest_marche_langue";
$dest_article = "dest_article";
$dest_article_lang = "dest_article_langue";
$dest_exploitant = "dest_exploitant";
$dest_societe_marche = "dest_societe_marche";
$dest_activite_commerciale = "dest_activitecommerciale";
$dest_activite_commerciale_lang = "dest_activitecommerciale_langue";

?>
