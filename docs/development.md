# Development

## Menjalankan

```bash
composer dev   # sama dengan: php artisan dev
```

Perintah ini menjalankan tujuh proses sekaligus. Mosquitto tidak termasuk; jalankan sebagai
service sistem (`sudo systemctl start mosquitto`).

| Tab | Perintah | Untuk |
| --- | --- | --- |
| `server` | `php artisan serve` | API dan SPA di `localhost:8000` |
| `vite` | `npm run dev` | Aset frontend (HMR) |
| `reverb` | `php artisan reverb:start` | WebSocket untuk Echo |
| `queue` | `php artisan queue:listen --tries=1 --timeout=0` | Queue `default`: event broadcast |
| `device-commands` | `queue:work --queue=device-commands --sleep=0.2` dalam loop shell | Command approve/reject ke device |
| `mqtt` | `php artisan mqtt:listen` | Scan/status dari device, command mode auto |
| `logs` | `php artisan pail --timeout=0` | Log aplikasi |

Tab `device-commands` dan `mqtt` didaftarkan di `AppServiceProvider::configureDevCommands()`;
sisanya bawaan Laravel dan Reverb.

## Setelah mengubah kode

| Yang diubah | Yang perlu dilakukan |
| --- | --- |
| Controller, model, route, frontend | Tidak ada. `serve` membaca ulang tiap request, Vite memakai HMR. |
| Event broadcast, listener | Tidak ada. `queue:listen` mem-boot ulang aplikasi untuk tiap job. |
| `PublishDeviceCommand`, `MqttPublisherService`, `DeviceCommand`, `config/mqtt.php` | **`php artisan queue:restart`** |
| `MqttListen`, `AccessDecisionService` | Restart tab `mqtt`: pilih tab itu lalu tekan `r` |
| `.env` | Hentikan `composer dev` (Ctrl+C), lalu jalankan lagi |

**Ingat `queue:restart`.** Worker `device-commands` memakai `queue:work`, yang tetap ter-boot
di antara job supaya latensi approve sampai device rendah (sekitar 0,1–0,2 detik). Akibatnya
ia terus menjalankan kode lama sampai di-restart. `php artisan queue:restart` menyuruhnya
berhenti setelah job yang sedang berjalan; di bawah `composer dev` loop shell langsung
menjalankan worker baru dengan kode terbaru dalam sekitar satu detik.

Kalau worker itu dijalankan sendiri di luar `composer dev`, `queue:restart` hanya
menghentikannya. Jalankan lagi secara manual.

Kenapa memakai loop shell: `php artisan dev` hanya menjalankan ulang proses yang keluar dengan
kode selain 0. Worker Laravel tidak pernah begitu: `queue:restart` membuatnya keluar dengan
kode 0, dan setelah job melewati `$timeout` worker mematikan dirinya dengan SIGKILL. Tanpa loop,
satu percobaan yang macet saja sudah membuat command approve/reject berikutnya tidak terkirim
sampai `composer dev` dijalankan ulang.

## Database (SQLite)

Beberapa proses di atas menulis ke `database/database.sqlite` bersamaan, sehingga koneksi
SQLite diatur di `config/database.php`:

- `journal_mode` = `wal`: pembaca tidak menunggu penulis.
- `busy_timeout` = 5000 ms: penulis menunggu giliran, tidak langsung gagal.
- `transaction_mode` = `IMMEDIATE`: transaksi mengambil kunci tulis sejak `BEGIN`. Tanpa ini,
  `pop()` milik database queue bisa gagal `database is locked` dan Laravel ikut menggagalkan
  job yang sedang diambilnya. Opsi ini baru berlaku di PHP 8.4 ke atas.

Mode WAL membuat berkas `database.sqlite-wal` dan `database.sqlite-shm` di sebelah database;
keduanya sudah di-gitignore. Saat menyalin database, salin ketiganya, atau hentikan semua proses
lebih dulu.

## Mensimulasikan perangkat

Lihat bagian "Mencoba tanpa hardware" di [mqtt-topics.md](mqtt-topics.md). Di halaman
Persetujuan juga ada tombol **Simulasikan Scan** (hanya di build development) yang memanggil
logika keputusan yang sama tanpa MQTT.
