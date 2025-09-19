<?php
session_start();

// konfiguracja bazy
include 'config.php';

// prosty routing CSRF (opcjonalnie)
if (isset($_GET['csrf']) && $_GET['csrf'] == '1') {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['csrf_token' => $_SESSION['csrf_token']]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $sent_csrf = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sent_csrf)) {
        echo json_encode(['success' => false, 'message' => 'Nieprawidłowy token CSRF']);
        exit;
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        echo json_encode(['success' => false, 'message' => 'Uzupełnij wszystkie pola']);
        exit;
    }

    try {
        $pdo = new PDO($db_dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // pobranie użytkownika
        $stmt = $pdo->prepare('SELECT id, username, password, role FROM users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        if (!$user || $user['password'] !== $password) {
            echo json_encode(['success' => false, 'message' => 'Nieprawidłowy login lub hasło']);
            exit;
        }

        // logowanie
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        echo json_encode(['success' => true, 'message' => 'Zalogowano']);
        exit;

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Błąd połączenia z bazą: '.$e->getMessage()]);
        exit;
    }
}

// jeśli nie POST ani csrf
http_response_code(405);
echo 'Method Not Allowed';
