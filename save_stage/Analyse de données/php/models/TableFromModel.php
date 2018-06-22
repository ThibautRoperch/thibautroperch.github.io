<?php

/**
 * Une instance de cette classe représente une table construite à partir d'un modèle de table contenu dans un fichier JSON
 */

class TableFromModel {
    private $pdo_conn;

    private $file_name;
    private $file_json;

    private $table_name;
    private $table_columns;
    private $tables_foreign_keys;

    public function __construct($pdo_conn, $file_name) {
        $this->pdo_conn = $pdo_conn;

        $this->file_name = $file_name;
        $this->file_json = json_decode(file_get_contents($file_name));

        preg_match("/(.+)\.json$/", $file_name, $matches);
        $this->table_name = $matches[1];

        $this->table_columns = [];
        $this->table_foreign_keys = []; // ["col_name" => "table_name", ...]
    }

    /**
     * Récupération des colonnes de la table dans le fichier
     */
    public function loadModel() {

        $this->table_columns["id"] = "`id` INT PRIMARY KEY NOT NULL AUTO_INCREMENT";

        foreach ($this->file_json->catégories as $id => $categorie) {
            foreach ($categorie->critères as $critere) {
                $col_name = $critere->nom;
                $col_type = $critere->type;
                $col_comment = addslashes($critere->description);

                if ($id === "@") { // clés étrangères
                    $this->table_foreign_keys[$col_name] = $col_type;
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
                $this->table_columns[$col_name] = $col_str;
            }
        }
    }

    public function tableExists() {
        // retourner true si la table existe
        // sert pour l'affichage des tables, si elle existe pas , bouton "créer la table" dans l'interface
    }

    public function differencesModelTable() {
        // retourne un tableau contenant les différences du modèles par rapport à la table
        // pour signaler dans l'interface que la table affichée n'est pas conforme à son modèle, qu'il est conseillé de recréer le modèle et charger les données
    }

    /**
     * Création de la table à partir du modèle
     * Si elle existe déjà, la table est supprimée avant d'être recréée
     * Si loadModel n'a pas été appelé, la table créée ne comportera pas de colonne
     */
    public function createTable() {
        // Création des requêtes

        $requests = [];

        $drop_table_str = "DROP TABLE IF EXISTS $this->table_name";
        array_push($requests, $drop_table_str);

        $columns_str = implode(", ", $this->table_columns);
        $create_table_str = "CREATE TABLE IF NOT EXISTS $this->table_name ($columns_str)";
        array_push($requests, $create_table_str);

        // Exécution des requêtes

        foreach ($requests as $request) {
            $this->pdo_conn->exec($request);
        }
    }

    /**
     * Insertion à vide avec seulement les clés étrangères, qui sont cherchées en fonction des valeurs données en paramètre
     * Retourne l'id de l'insertion pour modifications
     * Clés étrangères de this : ["col_name" => "table_name", ...]
     * Clés étrangères en paramètres : ["col_name" => [["column", ...], ["value", ...]], ...] (sélectionner l'id où "column" vaut "value" dans la table "table_name")
     */
    public function createData($recherche_cles_etrangeres) {
        $fk_columns = [];
        $fk_values = [];

        foreach ($recherche_cles_etrangeres as $col_name => $recherche_cle_etrangere) {
            $table = $this->table_foreign_keys[$col_name];

            $columns = $this->intoArray($recherche_cle_etrangere[0]);
            $values = $this->intoArray($recherche_cle_etrangere[1]);

            $where_str = $this->intoWhereSQL($columns, $values);
            $req_id = $this->pdo_conn->query("SELECT id FROM $table WHERE $where_str")->fetch()[0];

            array_push($fk_columns, $col_name);
            array_push($fk_values, $req_id);
        }

        $this->pdo_conn->exec("INSERT INTO $this->table_name (" . implode(", ", $fk_columns) . ") VALUES (" . implode(", ", $fk_values) . ")");
        return $this->pdo_conn->lastInsertId();
    }

    /**
     * Modification où l'id est celui donné en paramètre et les colonnes valent les valeurs
     * $columns et $values peuvent être une chaine de caractères chacun, ou un tableau de chaines de caractères
     */
    public function updateData($id, $columns, $values) {
        $columns = $this->intoArray($columns);
        $values = $this->intoArray($values);

        // for ($i = 0; $i < count($values); ++$i) {
            // column[i] = value[i]
        // }
    }

    public function deleteDatas($columns, $values) {
        $columns = $this->intoArray($columns);
        $values = $this->intoArray($values);

        // remove where chaque col = value associée
    }

    public function selectDatas($columns, $values) {
        $columns = $this->intoArray($columns);
        $values = $this->intoArray($values);

        // select where chaque col = value associée
    }

    public function intoWhereSQL($columns, $values) {
        $where_str = "";

        for ($i = 0; $i < count($values); ++$i) {
            if ($i > 0) {
                $where_str .= " AND ";
            }
            $where_str .= $columns[$i] . " = '" . $values[$i] . "'";
        }

        return $where_str;
    }

    public function intoArray($object) {
        if (!is_array($object)) {
            return [$object];
        }
        return $object;
    }
    
}

?>
