<?php
$host = 'localhost';
$dbname = 'jeshieco_campaign_assessment';
$username = 'jeshieco_campaign_assessment';
$password = 'jeshieco_campaign_assessment'; // Default XAMPP password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
