<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}

require 'logger.php';

if ($_SESSION['role'] !== 'admin') {
    die("Brak uprawnień.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['uuid'])) {
    $uuid = $_POST['uuid'];

    include 'config.php';

    try {
        $pdo = new PDO($db_dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // pobranie nazwy urządzenia przed usunięciem
        $stmt = $pdo->prepare("SELECT hostname FROM devices WHERE device_uuid = :uuid");
        $stmt->execute([':uuid' => $uuid]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($device) {
            $device_name = $device['hostname'];

            // usuń urządzenie
            $stmt = $pdo->prepare("DELETE FROM devices WHERE device_uuid = :uuid");
            $stmt->execute([':uuid' => $uuid]);

            // logowanie — tylko jeśli nie było już takiego logu w ciągu 2h
            log_event($pdo, $uuid, $device_name, "Skasowano urządzenie");
        }

        header("Location: devices.php");
        exit;

    } catch (PDOException $e) {
        die("Błąd: " . $e->getMessage());
    }
} else {
    header("Location: devices.php");
    exit;
}
