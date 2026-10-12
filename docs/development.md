# Development

## Persiapan pertama kali

```bash
touch database/database.sqlite
php artisan migrate --seed
```

Seeder membuat satu admin (`admin@example.com`) dan dua karyawan, semuanya dengan password
`password`.

Pasang juga konfigurasi Mosquitto (listener jaringan lokal, akun, dan ACL) sekali, mengikuti
[deploy/mosquitto/README.md](../deploy/mosquitto/README.md), lalu isi akun server di `.env`:

```dotenv
MQTT_USERNAME=two-guards-server
MQTT_PASSWORD=password-yang-diberikan-ke-mosquitto_passwd
```

## Akun

Pendaftaran mandiri, hapus akun sendiri, dan reset password publik semuanya ditutup. Akun
dibuat, dihapus, dan password-nya diganti hanya oleh admin di halaman **Akun** (`/users`),
dengan dua batasan:

- admin tidak bisa menghapus akunnya sendiri;
- admin terakhir tidak bisa dihapus maupun diturunkan menjadi karyawan. Aturan ini dijaga di
  model `User`, jadi berlaku juga lewat `php artisan tinker`.

### Akun yang dihapus

Menghapus akun hanya menandainya terhapus (*soft delete*, kolom `users.deleted_at`); barisnya
tetap ada supaya jejak audit tidak hilang:

- akun itu tidak bisa login lagi, dan sesinya yang masih aktif langsung tidak berlaku pada
  request berikutnya;
- riwayat akses tetap menampilkan nama pemrosesnya, ditandai "(akun dihapus)";
- halaman Akun hanya menampilkan akun aktif, dan penjaga admin terakhir hanya menghitung admin
  yang belum dihapus;
- emailnya tetap tercatat, jadi akun baru tidak bisa memakai email yang sama. Admin akan
  mendapat pesan "Email ini masih tercatat untuk akun yang sudah dihapus".

### Jalan darurat dari server

Dua perintah untuk keadaan ketika halaman Akun tidak bisa dipakai. Keduanya perlu terminal
interaktif, jadi tidak bisa dipakai dengan `--no-interaction`.

**Admin lupa password.** Ganti password langsung dari server:

```bash
php artisan user:set-password admin@example.com
```

Password baru ditanyakan dua kali tanpa ditampilkan, dengan aturan yang sama seperti di
aplikasi. Sengaja tidak bisa diberikan sebagai argumen, supaya tidak tersimpan di riwayat
shell atau terlihat di daftar proses.

**Admin habis.** Normalnya tidak mungkin, karena admin terakhir dijaga. Tetapi dua admin yang
saling menurunkan pada saat bersamaan, atau perubahan langsung di database, bisa tetap
membuatnya terjadi. Jadikan akun yang masih ada sebagai admin:

```bash
php artisan user:make-admin karyawan1@example.com
```

Perintah ini menampilkan akun itu beserta jumlah admin aktif saat ini, lalu meminta konfirmasi.
Akun yang sudah dihapus tidak bisa dijadikan admin. Kalau password akun itu juga tidak
diketahui, lanjutkan dengan `user:set-password`.

## Menjalankan

```bash
composer dev   # sama dengan: php artisan dev
```

Perintah ini menjalankan delapan proses sekaligus. Mosquitto tidak termasuk; jalankan sebagai
service sistem (`sudo systemctl start mosquitto`).

| Tab | Perintah | Untuk |
| --- | --- | --- |
| `server` | `php artisan serve` | API dan SPA di `localhost:8000` |
| `vite` | `npm run dev` | Aset frontend (HMR) |
| `reverb` | `php artisan reverb:start` | WebSocket untuk Echo |
| `queue` | `php artisan queue:listen --tries=1 --timeout=0 --sleep=0.2` | Queue `default`: event broadcast |
| `device-commands` | `queue:work --queue=device-commands --sleep=0.2` dalam loop shell | Command approve/reject ke device |
| `mqtt` | `php artisan mqtt:listen` | Scan/status dari device, command mode auto |
| `schedule` | `php artisan schedule:work` | Tugas terjadwal di `routes/console.php` |
| `logs` | `php artisan pail --timeout=0` | Log aplikasi |

Tab `queue`, `device-commands`, `mqtt`, dan `schedule` diatur di
`AppServiceProvider::configureDevCommands()` (untuk `queue`, menggantikan bawaan Laravel yang
polling tiap 3 detik); sisanya bawaan Laravel dan Reverb.

## Broker MQTT

Broker menolak koneksi tanpa akun. `mqtt:listen` dan worker `device-commands` login dengan
`MQTT_USERNAME`/`MQTT_PASSWORD` dari `.env`. Kalau salah, tab `mqtt` mencatat
`broker connection lost or failed ... The configured broker responded with unauthorized.` dan
mencoba lagi tiap 5 detik.

| Akun | Untuk |
| --- | --- |
| `two-guards-server` | Server Laravel, satu-satunya yang boleh mengirim command ke device |
| `device-1` | ESP32 device 1, dan simulasi scan dengan `mosquitto_pub` |
| `two-guards-monitor` | Memantau dengan `mosquitto_sub`, hanya baca |

Memantau semua lalu lintas device:

```bash
read -rs MQTT_MONITOR_PASSWORD   # fish: read -s MQTT_MONITOR_PASSWORD
mosquitto_sub -h localhost -u two-guards-monitor -P "$MQTT_MONITOR_PASSWORD" -t 'parkir/#' -v
```

Publish yang ditolak ACL dibuang broker diam-diam: client MQTT 3.1.1 tidak mendapat error, dan
log broker dengan `log_type` bawaan juga tidak mencatatnya. Penolakannya terlihat kalau publish
memakai MQTT 5 dan QoS 1 (`mosquitto_pub -V mqttv5 -q 1 ...` mencetak
`Publish 1 failed: Not authorized.`). Subscribe ke topic yang tidak boleh dibaca tetap "berhasil",
tetapi tidak pernah menerima pesan. Kalau pesan terasa hilang, berkas `acl` adalah tempat pertama
yang dicek.

## Tugas terjadwal

| Tugas | Jadwal | Untuk |
| --- | --- | --- |
| `access-logs:expire-pending` | tiap 5 detik, tidak tumpang tindih | Menandai scan manual yang tidak diputuskan dalam 30 detik sebagai `expired`, sekaligus menghapusnya dari halaman Persetujuan semua pengguna |

Di development, tab `schedule` yang menjalankannya. Di produksi cukup satu baris cron; untuk
tugas di bawah satu menit, `schedule:run` tetap berjalan sampai menit itu habis:

```cron
* * * * * cd /path/ke/two-guards && php artisan schedule:run >> /dev/null 2>&1
```

Cek jadwalnya dengan `php artisan schedule:list`.

## Setelah mengubah kode

| Yang diubah | Yang perlu dilakukan |
| --- | --- |
| Controller, model, route, frontend | Tidak ada. `serve` membaca ulang tiap request, Vite memakai HMR. |
| Event broadcast, listener | Tidak ada. `queue:listen` mem-boot ulang aplikasi untuk tiap job. |
| `PublishDeviceCommand`, `MqttPublisherService`, `DeviceCommand`, `config/mqtt.php` | **`php artisan queue:restart`** |
| `MqttListen`, `AccessDecisionService` | Restart tab `mqtt`: pilih tab itu lalu tekan `r` |
| Command yang dijadwalkan, `routes/console.php` | Restart tab `schedule` (`r`) |
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

## Menghentikan `mqtt:listen`

SIGTERM (misalnya dari Supervisor atau `kill`) maupun SIGINT (Ctrl+C) menghentikan daemon dengan
rapi dalam kondisi apa pun:

| Saat sinyal datang | Yang terjadi |
| --- | --- |
| Sedang mendengarkan | Loop dihentikan, koneksi ditutup (DISCONNECT), lalu keluar |
| Menunggu retry karena broker tak terjangkau | Keluar saat itu juga, tanpa mencoba lagi |
| Sedang menghubungkan ke broker | Keluar begitu percobaan itu selesai (paling lama `MQTT_CONNECT_TIMEOUT`, bawaan 3 detik) |

Command yang belum di-PUBACK saat berhenti dicatat dengan alasan `shutdown`. Satu-satunya keadaan
yang tidak bisa diakhiri sinyal pertama adalah broker yang menerima koneksi tetapi tidak pernah
membalas CONNACK; sinyal kedua langsung menghentikan proses. Untuk Supervisor, `stopsignal=TERM`
dengan `stopwaitsecs` di atas connect timeout sudah cukup.

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

Lihat bagian "Mencoba tanpa hardware" di [mqtt-topics.md](mqtt-topics.md): scan disimulasikan
dengan `mosquitto_pub -u device-1 -P ...`, dan command dipantau dengan
`mosquitto_sub -u two-guards-monitor -P ...`. Di halaman
Persetujuan juga ada tombol **Simulasikan Scan** (hanya di build development) yang memanggil
logika keputusan yang sama tanpa MQTT.

## Test

```bash
php artisan test                            # semua test
php artisan test --group=deferred-security  # test 2FA/passkey yang sengaja di-skip
```

Test di grup `deferred-security` selalu di-skip dengan alasan yang tertulis: fiturnya ditunda,
bukan dibuang, dan test-nya disimpan sebagai pengingat sampai fitur itu dibuat ulang sebagai API.

## Sebelum commit: `composer test`

`composer test` adalah pengecekan wajib sebelum setiap commit. Commit hanya kalau perintah ini
keluar dengan kode 0:

```bash
composer test; echo "exit=$?"
```

Langkahnya berurutan, dan berhenti di langkah pertama yang gagal:

| Langkah | Memeriksa |
| --- | --- |
| `php artisan config:clear` | membuang cache config, supaya test memakai config terbaru |
| `composer lint:check` | format kode PHP di seluruh project dengan Pint (`composer lint` memperbaikinya) |
| `composer phpstan:check` | analisis statis PHPStan (Larastan) level 7, terhadap baseline |
| `php artisan test` | seluruh test PHPUnit |

`composer test` belum memeriksa frontend. Kalau mengubah `resources/js`, jalankan juga
`npm run types:check` (TypeScript) dan `npm run build`.

`composer types:check` adalah nama lama `composer phpstan:check` dan masih bisa dipakai. Nama
barunya dipakai supaya tidak tertukar dengan `npm run types:check`, yang menjalankan `tsc`.

PHPStan diberi `--memory-limit=1G` oleh script composer, jadi `memory_limit` di php.ini (128M)
tidak perlu diubah. Kalau menjalankan `vendor/bin/phpstan` langsung, tambahkan opsi itu sendiri.

### Baseline PHPStan

`phpstan-baseline.neon` mencatat error PHPStan yang sudah ada ketika baseline dibuat (30 error,
12 Oktober 2026). Error yang tercatat di sana tidak menggagalkan analisis; error baru selalu
menggagalkan.

Baseline hanya boleh menyusut. Setelah sebuah error yang tercatat di baseline diperbaiki,
PHPStan justru gagal, karena baseline masih mengharapkan error itu:

```
Ignored error pattern #^Using nullsafe property access ...$# (nullsafe.neverNull) in path
.../app/Http/Resources/AccessLogResource.php was not matched in reported errors.
```

Kalau error yang sama muncul beberapa kali dan hanya sebagian yang diperbaiki, pesannya
berbunyi "is expected to occur N times, but occurred only M times". Untuk keduanya, perbarui
baseline:

```bash
composer phpstan:baseline
git diff phpstan-baseline.neon   # hanya boleh ada baris yang dihapus
```

lalu commit `phpstan-baseline.neon` bersama perbaikannya. Jangan memakai
`composer phpstan:baseline` untuk menyembunyikan error baru: kalau `git diff` menunjukkan baris
yang bertambah, perbaiki error itu alih-alih memasukkannya ke baseline. Pesan "is expected to
occur N times, but occurred M times" tanpa kata "only" juga berarti error baru: error yang
sama muncul lebih sering daripada yang tercatat.
