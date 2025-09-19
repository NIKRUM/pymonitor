Okej, oto pełny opis obu tabel w formie **czystego tekstu**, tak aby można było go przekazać AI.

---

Tabela devices:

Kolumny:

* id: INT AUTO\_INCREMENT PRIMARY KEY. Wewnętrzny identyfikator rekordu, nie trzeba go wypełniać przy insercie.
* device\_uuid: VARCHAR(100) UNIQUE NOT NULL. Unikalny identyfikator urządzenia (UUID lub numer seryjny). AI musi przesłać ten sam UUID dla danego urządzenia.
* hostname: VARCHAR(100). Nazwa hosta urządzenia.
* os: VARCHAR(50). System operacyjny, np. Windows 10, Ubuntu 22.04.
* cpu\_model: VARCHAR(100). Model procesora, np. Intel Core i7-10700K.
* ram\_size: BIGINT. Całkowita ilość pamięci RAM w bajtach.
* device\_type: VARCHAR(50). Typ urządzenia, np. PC, Server, Raspberry Pi.
* created\_at: TIMESTAMP DEFAULT CURRENT\_TIMESTAMP. Data dodania urządzenia do bazy, może być generowana automatycznie.

Przykładowe inserty dla devices:

INSERT INTO devices (device\_uuid, hostname, os, cpu\_model, ram\_size, device\_type) VALUES ('ABC123XYZ', 'MojPC', 'Windows 10', 'Intel(R) Core(TM) i7-10700K', 17179869184, 'PC');

INSERT INTO devices (device\_uuid, hostname, os, cpu\_model, ram\_size, device\_type) VALUES ('LINUX987654', 'server01', 'Ubuntu 22.04', 'AMD Ryzen 9 5900X', 34359738368, 'Server');

---

Tabela device\_stats:

Kolumny:

* id: INT AUTO\_INCREMENT PRIMARY KEY. Wewnętrzny identyfikator rekordu, nie trzeba go wypełniać przy insercie.
* device\_uuid: VARCHAR(100) NOT NULL. UUID urządzenia – klucz obcy do devices.device\_uuid. AI musi przesłać ten sam UUID dla danego urządzenia.
* timestamp: TIMESTAMP DEFAULT CURRENT\_TIMESTAMP. Data i godzina zebrania statystyk, może być generowana automatycznie.
* internet: TINYINT. Status połączenia z internetem: 1 = online, 0 = offline. Można sprawdzić np. pingiem do 8.8.8.8.
* disk\_total: BIGINT. Całkowita przestrzeń dyskowa w bajtach.
* disk\_used: BIGINT. Ilość zajętego miejsca na dysku w bajtach.
* disk\_free: BIGINT. Ilość wolnego miejsca na dysku w bajtach.
* disk\_percent: FLOAT. Procent użycia dysku.
* cpu\_temp: FLOAT. Temperatura CPU w stopniach C. Na Linux: psutil.sensors\_temperatures(), na Windows np. OpenHardwareMonitor.
* ram\_used: BIGINT. Ilość użytej pamięci RAM w bajtach.
* ram\_percent: FLOAT. Procent użycia RAM.
* ip\_address: VARCHAR(45). Aktualny adres IP urządzenia (IPv4 lub IPv6). Można pobrać np. socket.gethostbyname(socket.gethostname()) lub inne metody wykrywania lokalnego IP.

Przykładowe inserty dla device\_stats:

INSERT INTO device\_stats (device\_uuid, internet, disk\_total, disk\_used, disk\_free, disk\_percent, cpu\_temp, ram\_used, ram\_percent, ip\_address) VALUES ('ABC123XYZ', 1, 500107862016, 200053798400, 300054063616, 40.0, 55.3, 4294967296, 50.0, '192.168.1.10');

INSERT INTO device\_stats (device\_uuid, internet, disk\_total, disk\_used, disk\_free, disk\_percent, cpu\_temp, ram\_used, ram\_percent, ip\_address) VALUES ('ABC123XYZ', 1, 510000000000, 210000000000, 300000000000, 41.0, 57.0, 4500000000, 52.0, '192.168.1.10');

INSERT INTO device\_stats (device\_uuid, internet, disk\_total, disk\_used, disk\_free, disk\_percent, cpu\_temp, ram\_used, ram\_percent, ip\_address) VALUES ('LINUX987654', 1, 1000204886016, 600102443008, 400102443008, 60.0, 65.2, 17179869184, 50.0, '10.0.0.5');

INSERT INTO device\_stats (device\_uuid, internet, disk\_total, disk\_used, disk\_free, disk\_percent, cpu\_temp, ram\_used, ram\_percent, ip\_address) VALUES ('LINUX987654', 0, 1000204886016, 610000000000, 390204886016, 61.0, 67.0, 17500000000, 51.0, '10.0.0.5');

---

Uwagi dla AI pobierającego dane:

* device\_uuid musi być stały dla każdego urządzenia i identyczny w obu tabelach.
* timestamp może być generowany automatycznie w bazie.
* Kolumny dysku i RAM pobierać w bajtach i procentach.
* Połączenie z internetem sprawdzać np. pingiem.
* Temperatura CPU zależy od systemu i wymaga odpowiednich narzędzi.
* ip\_address zapisuje aktualny adres IP w momencie zbierania statystyk.
