<?php

namespace Classes\Database;
include_once(dirname(__FILE__) . '/../../config.php');

class DatabaseFactory
{

    public static function RDSConnection()
    {
        $db = new Database();
        $conn = $db->Connection("mysql:host=" . HOST . ";dbname=" . DBNAME . "", UNAME, PASSWORD);

        if (!$conn) {
            echo "Unable to connect to db";
        } else {
        }

        $conn->exec("set foreign_key_checks =0");

        return $conn;
    }
}


