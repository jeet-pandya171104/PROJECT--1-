<?php
// config.php
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'student_feedback_system';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set timezone if needed
date_default_timezone_set('Asia/Kolkata');
?>