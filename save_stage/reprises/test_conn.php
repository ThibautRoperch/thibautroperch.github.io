<?php

if (isset($_GET["conn"]) && isset($_GET["host"]) && isset($_GET["port"]) && isset($_GET["database"]) && isset($_GET["user"]) && isset($_GET["password"])) {

    $host = $_GET["host"];
    $port = $_GET["port"];
    $database = $_GET["database"];
    $user = $_GET["user"];
    $password = $_GET["password"];

    if ($host === "") {
        echo "Hôte non renseigné";
    } elseif ($port === "") {
        echo "Port non renseigné";
    } elseif ($database === "") {
        echo "Base de données non renseignée";
    } elseif ($user === "") {
        echo "Utilisateur non renseigné";
    } elseif ($_GET["conn"] === "oracle") {
        // https://blog.tfrichet.fr/connexion-entre-oracle-10g-ou-11g-et-php-avec-les-pdo/

        $service = ($host === "orcl") ? "orcl" : "xe";

        $conn_string =
        "oci:dbname=(DESCRIPTION =
            (ADDRESS_LIST =
                (ADDRESS =
                    (PROTOCOL = TCP)
                    (Host = ".$host .")
                    (Port = ".$port."))
            )
            (CONNECT_DATA =
                (SERVICE_NAME = ".$service.")
            )
        )";

        try {
            $conn = new PDO($conn_string, $user, $password);
        } catch (PDOException $e) {
            echo $e->getMessage();
        }

    } elseif ($_GET["conn"] === "pgsql") {

        $conn = pg_connect("host=$host port=$port dbname=$database user=$user password=$password");

    } else {
        echo "Type de connexion différent de Oracle et de pgSQL";
    }

}

?>
