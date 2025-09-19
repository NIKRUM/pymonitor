<?php

include 'config.php';

// Nagłówki CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Sprawdzenie metody HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST method allowed']);
    exit;
}

try {
    // Połączenie z bazą danych
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Pobieranie danych z żądania POST
    $sql_query = $_POST['sql_query'] ?? '';
    $timestamp = $_POST['timestamp'] ?? date('Y-m-d H:i:s');

    // Walidacja podstawowa
    if (empty($sql_query)) {
        throw new Exception('Brak zapytania SQL');
    }

    // Sprawdzenie czy zapytanie zaczyna się od INSERT
    if (!preg_match('/^\s*INSERT\s+/i', trim($sql_query))) {
        throw new Exception('Dozwolone są tylko zapytania INSERT');
    }

    // Logowanie
    error_log("INSERT request: " . $sql_query . " at " . $timestamp);

    // Wykonanie zapytania
    $stmt = $pdo->prepare($sql_query);
    $result = $stmt->execute();

    if ($result) {
        $lastId = $pdo->lastInsertId();
        echo json_encode([
            'success' => true,
            'message' => 'Rekord został dodany pomyślnie',
            'last_insert_id' => $lastId,
            'timestamp' => $timestamp
        ]);
    } else {
        throw new Exception('Błąd podczas wykonywania zapytania');
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Błąd bazy danych: ' . $e->getMessage()
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
