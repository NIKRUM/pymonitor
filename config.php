<?php
// config.php
$db_host = 'localhost';
$db_name = 'io';
$db_user = 'root';
$db_pass = '';

// Opcje PDO
$pdo_options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
];

// Tworzenie połączenia PDO
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, $pdo_options);
} catch (PDOException $e) {
    die("Błąd połączenia z bazą: " . $e->getMessage());
}
