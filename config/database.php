<?php

$host = "localhost";
$db = "parliament_cafeteria";
$user = "root";
$pass = "";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {

    die("Database Connection Failed.");
}

