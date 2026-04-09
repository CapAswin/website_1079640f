<?php
$servername = "localhost";
$username = "nelitamy_anandhu";
$password = "iyjA^5v?42xw";
$database = "nelitamy_opulent_influncer_house";

$conn = null;
$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    $conn = null;
}