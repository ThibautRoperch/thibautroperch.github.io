<?php

function get_year($string) {
    preg_match("/^[0-9]{2}.[0-9]{2}.([0-9]{2,4})$/", $string, $matches);

    if (isset($matches[0])) {
        if ($matches[1] < 70)
            return "20" . $matches[1];
        else
            return "19" . $matches[1];
    }

    return NULL;
}

function get_month($string) {
    preg_match("/^[0-9]{2}.([0-9]{2}).[0-9]{2,4}$/", $string, $matches);

    if (isset($matches[0])) {
        return intval($matches[1]);
    }

    return NULL;
}

function day_to_index($day) {
    $day = mb_strtolower($day);

    switch ($day) {
        case "lundi":
            return 1;
        case "mardi":
            return 2;
        case "mercredi":
            return 3;
        case "jeudi":
            return 4;
        case "vendredi":
            return 5;
        case "samedi":
            return 6;
        case "dimanche":
            return 7;
        default:
            return NULL;
    }
}

?>
