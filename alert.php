<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}

include 'config.php';

$username = htmlspecialchars($_SESSION['username']);
$role = $_SESSION['role'] ?? 'user'; 

// Pobranie logów z bazy, najnowsze na górze
$logs_stmt = $pdo->prepare("SELECT * FROM device_logs ORDER BY timestamp DESC LIMIT 100");
$logs_stmt->execute();
$logs = $logs_stmt->fetchAll();

?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8" />
  <title>NetMon — Logi urządzeń</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="src/dashboard.css" />
  <style>
    .logs-table {
        width: 100%;
        border-collapse: collapse;
        background: #182536;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
    }
    .logs-table th, .logs-table td {
        padding: 12px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        text-align: left;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        transition: all 0.3s ease;
    }
    .logs-table th {
        background: #1b2330;
        color: #9aa7b2;
        font-size: 13px;
        text-transform: uppercase;
    }
    .logs-table td:hover {
        white-space: normal;
        background: rgba(255, 255, 255, 0.05);
    }
    .logs-table tr:nth-child(even) {
        background: #182536;
    }
    .logs-table tr:hover {
        background: rgba(255, 255, 255, 0.05);
    }
    .log-type {
        font-weight: 600;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 13px;
    }
    .log-info { background: #0d3f5e; color: #00c2ff; }
    .log-warn { background: #4a3720; color: #ffb347; }
    .log-crit { background: #401b1b; color: #ff6b6b; }
  </style>
</head>
<body>
<div class="layout">

<?php include 'sidebar.php'; ?>

<main class="main">
    <header class="topbar">
      <div class="welcome">Witaj, <strong><?php echo $username; ?></strong></div>
      <div class="actions">
        <a href="logout.php" class="btn">🚪 Wyloguj</a>
      </div>
    </header>

    <section class="alerts">
        <h2>Logi urządzeń</h2>
        <table class="logs-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Urządzenie</th>
                    <th>UUID</th>
                    <th>Treść zdarzenia</th>
                    <th>Data / godzina</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log):
                    // automatyczna klasyfikacja alertów na podstawie słów kluczowych
                    $text_lower = strtolower($log['event_text']);
                    if (str_contains($text_lower, 'error') || str_contains($text_lower, 'offline') || str_contains($text_lower, 'crit')) {
                        $type_class = 'log-crit';
                    } elseif (str_contains($text_lower, 'warn') || str_contains($text_lower, 'warning')) {
                        $type_class = 'log-warn';
                    } else {
                        $type_class = 'log-info';
                    }
                ?>
                <tr>
                    <td><?php echo $log['id']; ?></td>
                    <td><?php echo htmlspecialchars($log['device_name'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($log['device_uuid'] ?? '-'); ?></td>
                    <td><span class="log-type <?php echo $type_class; ?>"><?php echo htmlspecialchars($log['event_text']); ?></span></td>
                    <td><?php echo $log['timestamp']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</main>
</div>

</body>
</html>
