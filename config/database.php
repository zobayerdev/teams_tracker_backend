<?php
// $host = 'localhost';
// $db   = 'zobayer_location';
// $user = 'zobayer_location';
// $pass = 'zobayer_location';
// $charset = 'utf8mb4';

$host = 'localhost';
$db = 'team_track'; // phpMyAdmin-এ এই নামেই ডাটাবেস তৈরি করবেন
$user = 'root';             // XAMPP-এর ডিফল্ট ইউজারনেম সবসময় 'root' হয়
$pass = '';                 // XAMPP-এর ডিফল্ট পাসওয়ার্ড ফাঁকা (empty) থাকে
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}