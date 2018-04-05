<?php

require("common.php");

$files = scandir($directory);

$detected_files = [];
$files_to_convert = [];

foreach ($files as $file) {
    if (preg_match("/.*\.xls$/i", $file)) array_push($detected_files, $file);
}

echo "Fichiers détectés :<ul><li>" . implode("</li><li>", $detected_files) . "</li></ul>";

foreach ($detected_files as $file) {
    for ($i = 0; $i < count($expected_content); ++$i) {
        if (!isset($files_to_convert[$i]) && match_keywords($file, $keywords_files[$i])) {
            $files_to_convert[$i] = $file;
            break;
        }
    }
}

echo "Association des fichiers avec leur contenu :";
echo "<table>";
for ($i = 0; $i < count($expected_content); ++$i) {
    echo "<tr><th>Fichier contenant les " . $expected_content[$i] . "</th><td>";
    echo (isset($files_to_convert[$i])) ? $files_to_convert[$i] : "Fichier manquant";
    echo "</td></tr>";
}
echo "</table>";

echo "<hr>";

if (count($files_to_convert) == count($expected_content)) {
    $get_files = "";
    for ($i = 0; $i < count($expected_content); ++$i) {
        if ($get_files !== "") $get_files .= "&";
        $get_files .= $expected_content[$i] . "=" . $files_to_convert[$i];
    }
    echo "<a href=\"from_dibtic_to_geodpv1.php?$get_files\">Générer les requêtes SQL GEODP v1 pour ces fichiers</a>";
} else {
    echo "Des fichiers sont manquants :<ul>";
    for ($i = 0; $i < count($expected_content); ++$i) {
        if (!isset($files_to_convert[$i])) {
            echo "<li>" . $expected_content[$i] . " (" . $keywords_files[$i] . ")</li>";
        }
    }
    echo "</ul>";
}

function match_keywords($string, $keywords) {
    $keywords = explode("/", $keywords);
    foreach ($keywords as $keyword) {
        if (strpos($string, $keyword) > -1) return true;
    }
    return false;
}

?>
