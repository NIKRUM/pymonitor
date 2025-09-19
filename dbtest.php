<?php
$host = "dateinfo.site";    // adres serwera DB
$user = "dateinfo_io";         // użytkownik DB
$pass = "KvawpaXcLvkcFeKpt7u9";             // hasło DB
$db   = "dateinfo_io";         // nazwa bazy danych

// Utworzenie połączenia
$conn = new mysqli($host, $user, $pass, $db);

// Sprawdzenie połączenia
if ($conn->connect_error) {
    die("Błąd połączenia: " . $conn->connect_error);
} else {
    echo "Połączenie udane!";
}

$conn->close();
?>
