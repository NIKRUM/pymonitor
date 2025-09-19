#!/usr/bin/env python3
import requests # type: ignore
from datetime import datetime

def send_to_php_script(insert_statement, php_url="http://dateinfo.site/phpinserter.php"):
    """
    Wysyła INSERT statement do skryptu PHP na serwerze
    """
    try:
        data = {
            'sql_query': insert_statement,
            'table': 'device_stats',
            'timestamp': datetime.now().isoformat()
        }

        headers = {
            'Content-Type': 'application/x-www-form-urlencoded',
            'User-Agent': 'Python-MySQL-Inserter/1.0'
        }

        print(f"Wysyłanie danych do: {php_url}")
        response = requests.post(php_url, data=data, headers=headers, timeout=10)

        if response.status_code == 200:
            print(f"✓ Dane zostały pomyślnie wysłane do {php_url}")
            print(f"Odpowiedź serwera: {response.text}")
            return True
        else:
            print(f"✗ Błąd HTTP {response.status_code}: {response.text}")
            return False

    except requests.exceptions.RequestException as e:
        print(f"✗ Błąd podczas wysyłania danych: {e}")
        return False

def insert_from_variable(insert_statement, php_url="http://dateinfo.site/phpinserter.php"):
    """
    Wysyła podany INSERT statement do PHP
    """
    if not insert_statement.strip():
        print("✗ INSERT statement nie może być pusty!")
        return False

    print("=== Wysyłanie INSERT ze zmiennej ===")
    print(f"INSERT do wysłania:\n{insert_statement}")

    success = send_to_php_script(insert_statement, php_url)

    if success:
        print("✓ INSERT został pomyślnie wysłany!")
    else:
        print("✗ Wystąpił błąd przy wysyłaniu INSERT.")

    return success

def main():
    # Przykład użycia zmiennej z INSERT
    my_insert = "INSERT INTO device_stats (device_uuid, internet, disk_total, disk_used, disk_free, disk_percent, cpu_temp, cpu_percent, ram_used, ram_percent, ip_address, timestamp) VALUES ('ABC123XYZ', 1, 500107862016, 199749483702, 300358378314, 39.9, 90.0, 22.4, 3442408153, 20.0, '192.168.1.10', '2025-09-17 19:05:12');INSERT INTO device_stats (device_uuid, internet, disk_total, disk_used, disk_free, disk_percent, cpu_temp, cpu_percent, ram_used, ram_percent, ip_address, timestamp) VALUES ('LINUX987654', 1, 1000204886016, 600477316749, 399727569267, 60.0, 90.0, 35.9, 17161648657, 49.9, '10.0.0.5', '2025-09-17 19:05:12');"
    insert_from_variable(my_insert)

if __name__ == "__main__":
    main()
