<?php
// logger.php

/**
 * Zapisuje zdarzenie do tabeli device_log / device_logs.
 *
 * @param PDO $pdo
 * @param ?string $device_uuid UUID urządzenia lub NULL
 * @param ?string $device_name nazwa urządzenia / zasobu (np. nazwa użytkownika) lub NULL
 * @param string $event_text treść zdarzenia
 * @param int $dedup_hours jeśli podobny log wystąpił w ostatnich X godzin — nie dodawaj (domyślnie 2)
 * @return bool true jeśli zapisano nowy log, false jeśli pominięto (duplikat) lub wystąpił błąd
 */
function log_event(PDO $pdo, ?string $device_uuid, ?string $device_name, string $event_text, int $dedup_hours = 2): bool {
    // tabele do sprawdzenia (obsługa różnych nazw)
    $tables = ['device_log', 'device_logs'];

    // zamienniki na porównania (IFNULL używamy, więc porównujemy z pustym stringiem)
    $uuidStr = $device_uuid ?? '';
    $nameStr = $device_name ?? '';

    foreach ($tables as $table) {
        try {
            // 1) sprawdź czy tabela istnieje i ma kolumny (wyrzuci wyjątek jeśli tabela nie istnieje)
            // 2) sprawdzenie duplikatu: porównujemy IFNULL(...,'') = :val
            $sql_check = "
                SELECT 1 FROM `{$table}`
                WHERE IFNULL(device_uuid,'') = :uuid
                  AND IFNULL(device_name,'') = :name
                  AND event_text = :event_text
                  AND timestamp >= DATE_SUB(NOW(), INTERVAL :hours HOUR)
                LIMIT 1
            ";
            $stmt = $pdo->prepare($sql_check);
            $stmt->execute([
                ':uuid' => $uuidStr,
                ':name' => $nameStr,
                ':event_text' => $event_text,
                ':hours' => $dedup_hours
            ]);

            if ($stmt->fetchColumn()) {
                // taki sam log już jest w ostatnich $dedup_hours godzin → pomiń
                return false;
            }

            // 3) spróbuj wstawić wpis
            $sql_ins = "
                INSERT INTO `{$table}` (device_uuid, device_name, event_text)
                VALUES (:uuid_in, :name_in, :event_text_in)
            ";
            $ins = $pdo->prepare($sql_ins);

            // poprawne bindowanie NULL lub string
            if ($device_uuid === null) {
                $ins->bindValue(':uuid_in', null, PDO::PARAM_NULL);
            } else {
                $ins->bindValue(':uuid_in', $device_uuid, PDO::PARAM_STR);
            }

            if ($device_name === null) {
                $ins->bindValue(':name_in', null, PDO::PARAM_NULL);
            } else {
                $ins->bindValue(':name_in', $device_name, PDO::PARAM_STR);
            }

            $ins->bindValue(':event_text_in', $event_text, PDO::PARAM_STR);

            $ins->execute();
            return true;

        } catch (PDOException $e) {
            // jeśli tabela nie istnieje lub inne SQL error → spróbuj następnej nazwy tabeli
            error_log("log_event(): attempt for table {$table} failed: " . $e->getMessage());
            // dopisz dodatkowo do pliku tymczasowego — przydaje się w debugowaniu lokalnym
            @file_put_contents(sys_get_temp_dir() . '/netmon_logger_errors.log', date('c') . " log_event table {$table} error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
            continue;
        }
    }

    // jeśli doszliśmy tutaj, to wszystkie próby nie powiodły się
    error_log("log_event(): all attempts failed for event: " . $event_text);
    @file_put_contents(sys_get_temp_dir() . '/netmon_logger_errors.log', date('c') . " log_event all attempts failed: " . $event_text . PHP_EOL, FILE_APPEND);
    return false;
}
