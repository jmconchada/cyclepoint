<?php
$host = 'localhost';
$db   = 'cyclepoint_final';
$user = 'root';
$pass = '';

// Create a connection to the database using MySQLi
$conn = new mysqli($host, $user, $pass, $db);

// Check for connection errors
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
