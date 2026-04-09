<?php
$servername = "localhost";
$username = "nelitamy_anandhu";
$password = "iyjA^5v?42xw";
$database = "nelitamy_opulent_influncer_house";

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>