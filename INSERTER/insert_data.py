#!/usr/bin/env python3
import platform
import socket
import psutil
import uuid
import requests
import subprocess
import time
from datetime import datetime

PHP_URL = "http://dateinfo.site/phpinserter.php"
STATS_INTERVAL = 120  # 2 minuty

EXT_DEVICES_FILE = "ext_devices.txt"  # plik z konfiguracją urządzeń zewnętrznych

# ===== UUID / identyfikator lokalnego urządzenia =====

def get_device_serial():
    try:
        if platform.system() == "Windows":
            cmd = ["wmic", "bios", "get", "serialnumber"]
            serial = subprocess.check_output(cmd, universal_newlines=True).split("\n")[1].strip()
        elif platform.system() == "Linux":
            with open("/sys/class/dmi/id/product_uuid", "r") as f:
                serial = f.read().strip()
        elif platform.system() == "Darwin":
            cmd = ["ioreg", "-l"]
            output = subprocess.check_output(cmd, universal_newlines=True)
            for line in output.splitlines():
                if "IOPlatformSerialNumber" in line:
                    serial = line.split('"')[-2]
                    break
            else:
                serial = None
        else:
            serial = None
        return f"DEV_{serial}" if serial else "DEV_UNKNOWN"
    except:
        return "DEV_UNKNOWN"

# ===== Pomocnicze =====

def get_ip_address():
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        s.connect(("8.8.8.8", 80))
        ip = s.getsockname()[0]
        s.close()
        return ip
    except:
        return "127.0.0.1"

def get_cpu_temp():
    try:
        temps = psutil.sensors_temperatures()
        if not temps:
            return None
        for entries in temps.values():
            for entry in entries:
                if entry.current and entry.current > 0:
                    return round(entry.current, 2)
        return None
    except:
        return None

def ping_ip(ip):
    """Zwraca ping w ms lub None, jeśli brak odpowiedzi"""
    try:
        param = "-n" if platform.system() == "Windows" else "-c"
        output = subprocess.check_output(["ping", param, "1", ip], universal_newlines=True)
        if "time=" in output.lower():
            # Wyciągnięcie czasu w ms
            import re
            match = re.search(r'time[=<]\s*(\d+)', output.lower())
            if match:
                return int(match.group(1))
        return None
    except:
        return None

# ===== Dane lokalnego urządzenia =====

def collect_device_info(device_uuid):
    return {
        'device_uuid': device_uuid,
        'hostname': socket.gethostname(),
        'os': f"{platform.system()} {platform.release()}",
        'cpu_model': platform.processor() or "Unknown CPU",
        'ram_size': psutil.virtual_memory().total,
        'device_type': platform.system(),
        'owner': None,
        'location': None,
        'shared_with': None
    }

def collect_device_stats(device_uuid):
    disk_path = "C:\\" if platform.system() == "Windows" else "/"
    disk = psutil.disk_usage(disk_path)
    memory = psutil.virtual_memory()
    cpu_percent = psutil.cpu_percent(interval=1)
    return {
        'device_uuid': device_uuid,
        'timestamp': datetime.now().isoformat(),
        'internet': 1,
        'disk_total': disk.total,
        'disk_used': disk.used,
        'disk_free': disk.free,
        'disk_percent': round((disk.used / disk.total) * 100, 2),
        'cpu_temp': get_cpu_temp(),
        'cpu_percent': round(cpu_percent, 2),
        'ram_used': memory.used,
        'ram_percent': round(memory.percent, 2),
        'ip_address': get_ip_address()
    }

# ===== Dane zewnętrznych urządzeń =====

def load_ext_devices(file_path):
    """
    Wczytuje plik .txt w formacie: UUID;Typ;IP
    Zwraca listę słowników z hostname ustawionym na lokalny hostname
    """
    devices = []
    local_hostname = socket.gethostname()
    try:
        with open(file_path, "r", encoding="utf-8") as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith("#"):
                    continue
                parts = line.split(";")
                if len(parts) != 3:
                    continue
                devices.append({
                    'device_uuid': parts[0],
                    'device_type': parts[1],
                    'ip_address': parts[2],
                    'hostname': local_hostname
                })
    except Exception as e:
        print(f"Błąd wczytywania pliku {file_path}: {e}")
    return devices


# ===== SQL =====

def create_device_insert(device):
    return (
        f"INSERT INTO devices (device_uuid, hostname, os, cpu_model, ram_size, device_type, owner, shared_with, location) "
        f"VALUES ('{device['device_uuid']}', '{device['hostname']}', '{device['os']}', "
        f"'{device['cpu_model']}', {int(device['ram_size'])}, '{device['device_type']}', "
        f"NULL, NULL, NULL);"
    )

def create_stats_insert(stats):
    cpu_temp_val = "NULL" if stats['cpu_temp'] is None else stats['cpu_temp']
    return (
        f"INSERT INTO device_stats (device_uuid, internet, disk_total, disk_used, disk_free, disk_percent, cpu_temp, cpu_percent, ram_used, ram_percent, ip_address, timestamp) "
        f"VALUES ('{stats['device_uuid']}', {stats['internet']}, {stats['disk_total']}, "
        f"{stats['disk_used']}, {stats['disk_free']}, {stats['disk_percent']}, {cpu_temp_val}, "
        f"{stats['cpu_percent']}, {stats['ram_used']}, {stats['ram_percent']}, "
        f"'{stats['ip_address']}', '{stats['timestamp']}');"
    )

def create_ext_device_insert(device):
    return (
        f"INSERT INTO ext_devices (device_uuid, device_type, ip_address, hostname) "
        f"VALUES ('{device['device_uuid']}', '{device['device_type']}', '{device['ip_address']}', '{device['hostname']}');"
    )

def create_ext_state_insert(device_uuid, ping_ms):
    ping_val = "NULL" if ping_ms is None else ping_ms
    return (
        f"INSERT INTO ext_state (ext_device_uuid, ping_ms) "
        f"VALUES ('{device_uuid}', {ping_val});"
    )

# ===== Wysyłka =====

def send_to_php(insert_statement):
    try:
        data = {
            'sql_query': insert_statement,
            'timestamp': datetime.now().isoformat()
        }
        headers = {'Content-Type': 'application/x-www-form-urlencoded'}
        response = requests.post(PHP_URL, data=data, headers=headers, timeout=10)
        return response.status_code == 200
    except:
        return False

# ===== MAIN =====

def main():
    # UUID lokalnego urządzenia
    local_uuid = get_device_serial()

    # Jednorazowy insert do devices
    local_device = collect_device_info(local_uuid)
    send_to_php(create_device_insert(local_device))

    # Wczytanie konfiguracji zewnętrznych urządzeń
    ext_devices = load_ext_devices(EXT_DEVICES_FILE)
    for dev in ext_devices:
        send_to_php(create_ext_device_insert(dev))

    # Pętla cykliczna co 2 minuty
    while True:
        # Lokalny device_stats
        local_stats = collect_device_stats(local_uuid)
        send_to_php(create_stats_insert(local_stats))

        # Pingowanie urządzeń zewnętrznych i insert do ext_state
        for dev in ext_devices:
            ping_ms = ping_ip(dev['ip_address'])
            send_to_php(create_ext_state_insert(dev['device_uuid'], ping_ms))

        time.sleep(STATS_INTERVAL)

if __name__ == "__main__":
    main()
