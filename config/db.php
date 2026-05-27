<?php

function getConnection() {
    $host = 'localhost';
    $user = 'root';
    $pass = 'root';      
    $name = 'timetracking';
    $port = 3306;

    $conn = new mysqli($host, $user, $pass, $name, $port);

    if ($conn->connect_error) {
        die('DB connection failed: ' . $conn->connect_error);
    }

    return $conn;
}
