<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    die("Brak uprawnień.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['uuid'])) {
    $uuid = $_POST['uuid'];

    $db_dsn = 'mysql:host=localhost;dbname=io;charset=utf8mb4';
    $db_user = 'root';
    $db_pass = '';

    try {
        $pdo = new PDO($db_dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Usuń tylko z ext_devices, zostawiając historię w ext_state
        $stmt = $pdo->prepare("DELETE FROM ext_devices WHERE device_uuid = :uuid");
        $stmt->execute([':uuid' => $uuid]);

        header("Location: network.php");
        exit;

    } catch (PDOException $e) {
        die("Błąd: " . $e->getMessage());
    }
} else {
    header("Location: network.php");
    exit;
}
