<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}

$role = $_SESSION['role'] ?? 'user'; 
if ($role !== 'admin') {
    die("Brak dostępu — tylko administrator może zarządzać użytkownikami.");
}

include 'config.php';

// logger
require 'logger.php';

// obsługa akcji: dodanie, edycja, usunięcie
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $roleNew = $_POST['role'] ?? 'user';

        if ($username && $password) {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$username, $password, $roleNew]);

            // logowanie dodania użytkownika
            $actor = $_SESSION['username'] ?? 'SYSTEM';
            $message = "Dodano użytkownika '{$username}' z rolą '{$roleNew}' przez '{$actor}'";
            // device_uuid = NULL, device_name = nazwa tworzonego użytkownika
            $ok = log_event($pdo, null, $username, $message);
            if (!$ok) {
                // zapis do error_log jeśli chcesz widzieć w php logach
                error_log("users.php: log_event failed for add_user: " . $message);
            }
        }
    }

    if (isset($_POST['update_user'])) {
        $id = intval($_POST['id']);
        $roleNew = $_POST['role'] ?? 'user';
        $password = trim($_POST['password']);

        // pobierz aktualną nazwę użytkownika
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id=?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        $username = $user ? $user['username'] : "ID $id";

        $actor = $_SESSION['username'] ?? 'SYSTEM';

        if ($password) {
            $stmt = $pdo->prepare("UPDATE users SET password=?, role=? WHERE id=?");
            $stmt->execute([$password, $roleNew, $id]);

            $message = "Zmieniono hasło i rolę użytkownika '{$username}' na '{$roleNew}' przez '{$actor}'";
            $ok = log_event($pdo, null, $username, $message);
            if (!$ok) error_log("users.php: log_event failed for update_user (password+role): " . $message);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET role=? WHERE id=?");
            $stmt->execute([$roleNew, $id]);

            $message = "Zmieniono rolę użytkownika '{$username}' na '{$roleNew}' przez '{$actor}'";
            $ok = log_event($pdo, null, $username, $message);
            if (!$ok) error_log("users.php: log_event failed for update_user (role): " . $message);
        }
    }

    if (isset($_POST['delete_user'])) {
        $id = intval($_POST['id']);

        // pobierz nazwę użytkownika przed usunięciem
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id=?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        $username = $user ? $user['username'] : "ID $id";

        $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
        $stmt->execute([$id]);

        $actor = $_SESSION['username'] ?? 'SYSTEM';
        $message = "Usunięto użytkownika '{$username}' przez '{$actor}'";
        $ok = log_event($pdo, null, $username, $message);
        if (!$ok) error_log("users.php: log_event failed for delete_user: " . $message);
    }

    header("Location: users.php");
    exit;
}

// pobierz użytkowników
$users = $pdo->query("SELECT id, username, role, created_at FROM users ORDER BY created_at DESC")->fetchAll();
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8" />
  <title>NetMon — Użytkownicy</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="src/dashboard.css" />
</head>
<body>
<div class="layout">

  <?php include 'sidebar.php'; ?> <!-- panel po lewej -->

  <!-- Main -->
  <main class="main">
    <header class="topbar">
      <div class="welcome">Witaj, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></div>
      <div class="actions">
        <a href="logout.php" class="btn">🚪 Wyloguj</a>
      </div>
    </header>

    <section class="users">
      <h2>👤 Zarządzanie użytkownikami</h2>

      <!-- formularz dodawania -->
      <form method="post" class="card">
        <h3>➕ Dodaj nowego użytkownika</h3>
        <input type="text" name="username" placeholder="Nazwa użytkownika" required />
        <input type="password" name="password" placeholder="Hasło" required />
        <select name="role">
          <option value="user">Użytkownik</option>
          <option value="admin">Administrator</option>
        </select>
        <button type="submit" name="add_user">Dodaj</button>
      </form>

      <!-- tabela użytkowników -->
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Nazwa</th>
            <th>Rola</th>
            <th>Data utworzenia</th>
            <th>Akcje</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <form method="post">
              <td><?php echo $u['id']; ?>
                <input type="hidden" name="id" value="<?php echo $u['id']; ?>" />
              </td>
              <td><?php echo htmlspecialchars($u['username']); ?></td>
              <td>
                <select name="role">
                  <option value="user" <?php if($u['role']==='user') echo 'selected'; ?>>Użytkownik</option>
                  <option value="admin" <?php if($u['role']==='admin') echo 'selected'; ?>>Administrator</option>
                </select>
              </td>
              <td><?php echo $u['created_at']; ?></td>
              <td>
                <input type="password" name="password" placeholder="Nowe hasło (opcjonalne)" />
                <button type="submit" name="update_user">💾 Zapisz</button>
                <button type="submit" name="delete_user" onclick="return confirm('Na pewno usunąć użytkownika?');">❌ Usuń</button>
              </td>
            </form>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  </main>
</div>
</body>
</html>
