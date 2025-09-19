<?php

include 'config.php';

// Dane połączenia do serwera MySQL
$host = $db_host;      // lub inny host
$user = $db_user;           // użytkownik MySQL
$password = $db_pass;       // hasło MySQL
$dbname = $db_name;    // nazwa bazy, którą chcesz utworzyć

// Połączenie do serwera MySQL
$conn = new mysqli($host, $user, $password);

// Sprawdzenie połączenia
if ($conn->connect_error) {
    die("Błąd połączenia: " . $conn->connect_error);
}

// Tworzenie bazy danych
$sql = "CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci";
if ($conn->query($sql) === TRUE) {
    echo "Baza danych '$dbname' utworzona pomyślnie lub już istnieje.<br>";
} else {
    die("Błąd tworzenia bazy danych: " . $conn->error);
}

// Wybór bazy danych
$conn->select_db($dbname);

// Tworzenie tabel
$table_queries = [

    "CREATE TABLE IF NOT EXISTS `devices` (
      `id` int NOT NULL AUTO_INCREMENT,
      `device_uuid` varchar(100) NOT NULL,
      `hostname` varchar(100) DEFAULT NULL,
      `os` varchar(50) DEFAULT NULL,
      `cpu_model` varchar(100) DEFAULT NULL,
      `ram_size` bigint DEFAULT NULL,
      `device_type` varchar(50) DEFAULT NULL,
      `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      `owner` varchar(100) DEFAULT NULL,
      `shared_with` varchar(255) DEFAULT NULL,
      `location` varchar(100) DEFAULT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `device_uuid` (`device_uuid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",

    "CREATE TABLE IF NOT EXISTS `device_logs` (
      `id` int NOT NULL AUTO_INCREMENT,
      `device_uuid` varchar(100) DEFAULT NULL,
      `device_name` varchar(100) DEFAULT NULL,
      `event_text` text NOT NULL,
      `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",

    "CREATE TABLE IF NOT EXISTS `device_stats` (
      `id` int NOT NULL AUTO_INCREMENT,
      `device_uuid` varchar(100) NOT NULL,
      `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      `internet` tinyint DEFAULT NULL,
      `disk_total` bigint DEFAULT NULL,
      `disk_used` bigint DEFAULT NULL,
      `disk_free` bigint DEFAULT NULL,
      `disk_percent` float DEFAULT NULL,
      `cpu_temp` float DEFAULT NULL,
      `ram_used` bigint DEFAULT NULL,
      `ram_percent` float DEFAULT NULL,
      `ip_address` varchar(45) DEFAULT NULL,
      `cpu_percent` float DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `device_uuid` (`device_uuid`),
      CONSTRAINT `device_stats_ibfk_1` FOREIGN KEY (`device_uuid`) REFERENCES `devices` (`device_uuid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",

    "CREATE TABLE IF NOT EXISTS `ext_devices` (
      `id` int NOT NULL AUTO_INCREMENT,
      `device_uuid` varchar(100) NOT NULL,
      `device_type` varchar(50) DEFAULT NULL,
      `ip_address` varchar(45) DEFAULT NULL,
      `hostname` varchar(100) DEFAULT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `device_uuid` (`device_uuid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",

    "CREATE TABLE IF NOT EXISTS `ext_state` (
      `id` int NOT NULL AUTO_INCREMENT,
      `ext_device_uuid` varchar(100) NOT NULL,
      `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      `ping_ms` int DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `ext_device_uuid` (`ext_device_uuid`),
      CONSTRAINT `ext_state_ibfk_1` FOREIGN KEY (`ext_device_uuid`) REFERENCES `ext_devices` (`device_uuid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",

    "CREATE TABLE IF NOT EXISTS `users` (
      `id` int NOT NULL AUTO_INCREMENT,
      `username` varchar(100) NOT NULL,
      `password` varchar(255) NOT NULL,
      `role` varchar(30) NOT NULL DEFAULT 'user',
      `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
];

// Wykonanie zapytań do tworzenia tabel
foreach ($table_queries as $query) {
    if ($conn->query($query) === TRUE) {
        echo "Tabela utworzona pomyślnie lub już istnieje.<br>";
    } else {
        echo "Błąd tworzenia tabeli: " . $conn->error . "<br>";
    }
}

// Dodanie admina root/root
$admin_pass = password_hash('root', PASSWORD_DEFAULT); // hasło hashowane
$insert_admin = "INSERT IGNORE INTO `users` (`username`, `password`, `role`) VALUES ('root', '$admin_pass', 'admin')";
if ($conn->query($insert_admin) === TRUE) {
    echo "Admin został dodany.<br>";
} else {
    echo "Błąd dodawania admina: " . $conn->error . "<br>";
}

// Zamknięcie połączenia
$conn->close();
?>
