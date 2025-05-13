<?php
function getDatabaseConnection() {
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $db   = 'db_arcu';  // Make sure this matches your database name

    $con = new mysqli($host, $user, $pass, $db);

    if ($con->connect_error) {
        error_log("Database connection failed: " . $con->connect_error);
        throw new Exception("Database connection failed: " . $con->connect_error);
    }

    return $con;
}
?>