# Mosquitto untuk Two-Guards

ESP32 konek ke broker lewat WiFi, jadi broker harus menerima koneksi dari jaringan lokal.
Karena itu broker wajib memakai autentikasi dan ACL: tanpa ACL, siapa pun yang tahu password
device bisa publish `{"action":"open"}` ke topic command dan membuka gerbang.

| Berkas | Dipasang ke | Isi |
| --- | --- | --- |
| `two-guards.conf` | `/etc/mosquitto/conf.d/two-guards.conf` | Listener `1883` di `0.0.0.0`, tanpa anonymous, `password_file`, `acl_file` |
| `acl` | `/etc/mosquitto/two-guards/acl` | Hak tiap user atas topic |
| (dibuat di server) | `/etc/mosquitto/two-guards/passwd` | Hash password dari `mosquitto_passwd`, tidak pernah masuk git |

## User dan haknya

| User | Dipakai oleh | Baca | Tulis |
| --- | --- | --- | --- |
| `two-guards-server` | `mqtt:listen` dan job `PublishDeviceCommand` | `parkir/+/scan`, `parkir/+/status` | `parkir/+/command` |
| `device-1` | ESP32 portal device 1 | `parkir/1/command` | `parkir/1/scan`, `parkir/1/status` (termasuk LWT) |
| `two-guards-monitor` | Debugging dengan `mosquitto_sub` | `parkir/#` | tidak ada |

Hanya `two-guards-server` yang boleh menulis ke topic command. Publish yang ditolak ACL tidak
menghasilkan error di client MQTT 3.1.1: pesannya dibuang broker diam-diam, dan log broker
dengan `log_type` bawaan juga tidak mencatatnya (`Denied PUBLISH` hanya muncul dengan
`log_type all`). Untuk melihat penolakannya, publish dengan MQTT 5 dan QoS 1
(`mosquitto_pub -V mqttv5 -q 1 ...`): client mencetak `Publish 1 failed: Not authorized.`

## Memasang (Arch Linux)

Semua perintah dijalankan dari root repo.

### 1. Pastikan berkas konfigurasi yang dipakai service

```bash
systemctl cat mosquitto | grep -E 'ExecStart|User'
```

Di Arch hasilnya `ExecStart=/usr/bin/mosquitto -c /etc/mosquitto/mosquitto.conf` dengan
`User=mosquitto`. Kalau berbeda (misalnya ada drop-in di `/etc/systemd/system/mosquitto.service.d/`),
sesuaikan path pada langkah 4.

### 2. Salin konfigurasi dan ACL

```bash
sudo install -d -o root -g mosquitto -m 0750 /etc/mosquitto/two-guards
sudo install -d -o root -g root -m 0755 /etc/mosquitto/conf.d
sudo install -o root -g root -m 0644 deploy/mosquitto/two-guards.conf /etc/mosquitto/conf.d/two-guards.conf
sudo install -o mosquitto -g mosquitto -m 0600 deploy/mosquitto/acl /etc/mosquitto/two-guards/acl
```

### 3. Buat password

Buat tiga password acak, misalnya dengan `openssl rand -hex 16` (heksadesimal aman dipakai di
`.env`, header C, dan shell tanpa escape). `mosquitto_passwd` menanyakannya dua kali tanpa
menampilkannya.

```bash
# -c membuat berkas baru dan MENIMPA isinya: pakai hanya untuk user pertama.
sudo mosquitto_passwd -c /etc/mosquitto/two-guards/passwd two-guards-server
sudo mosquitto_passwd /etc/mosquitto/two-guards/passwd device-1
sudo mosquitto_passwd /etc/mosquitto/two-guards/passwd two-guards-monitor

sudo chown mosquitto:mosquitto /etc/mosquitto/two-guards/passwd
sudo chmod 0600 /etc/mosquitto/two-guards/passwd
```

Mosquitto 2.1 memperingatkan berkas password atau ACL yang bisa dibaca semua user ("Future
versions will refuse to load this file"), karena itu keduanya `0600` milik `mosquitto`.

Ke mana password-nya:

- `two-guards-server` → `MQTT_PASSWORD` di `.env` server, dengan `MQTT_USERNAME=two-guards-server`.
- `device-1` → `MQTT_PASSWORD` di `include/secrets.h` firmware.
- `two-guards-monitor` → dipakai sendiri saat debugging.

Mengganti password satu user: jalankan lagi `sudo mosquitto_passwd /etc/mosquitto/two-guards/passwd <user>`
(tanpa `-c`), lalu `sudo systemctl reload mosquitto`.

### 4. Muat `conf.d` dari konfigurasi utama

Sekali saja; perintah ini tidak menambah baris kalau sudah ada:

```bash
grep -qx 'include_dir /etc/mosquitto/conf.d' /etc/mosquitto/mosquitto.conf \
  || echo 'include_dir /etc/mosquitto/conf.d' | sudo tee -a /etc/mosquitto/mosquitto.conf
```

`mosquitto.conf` bawaan paket ditandai sebagai backup di pacman, jadi perubahan ini bertahan saat
upgrade (versi baru disimpan sebagai `.pacnew`).

### 5. Cek konfigurasi, lalu restart

```bash
sudo -u mosquitto mosquitto --test-config -c /etc/mosquitto/mosquitto.conf
sudo systemctl restart mosquitto
journalctl -u mosquitto -n 20 --no-pager
```

`--test-config` dijalankan sebagai user `mosquitto` supaya masalah permission ikut ketahuan.
Di log, pastikan ada `Opening ipv4 listen socket on port 1883` dan **tidak** ada
`Starting in local only mode` maupun peringatan `world readable`.

Perubahan listener butuh `restart`. Perubahan ACL atau password cukup
`sudo systemctl reload mosquitto`.

### 6. Firewall

```bash
systemctl is-active ufw firewalld
```

Untuk **ufw**, buka port 1883 hanya untuk jaringan lokal (sesuaikan subnetnya, lihat
`ip -4 -brief addr`):

```bash
sudo ufw allow from 192.168.2.0/24 to any port 1883 proto tcp comment 'Mosquitto Two-Guards'
sudo ufw status numbered
```

Untuk **firewalld**:

```bash
sudo firewall-cmd --permanent --add-rich-rule='rule family="ipv4" source address="192.168.2.0/24" port port="1883" protocol="tcp" accept'
sudo firewall-cmd --reload
```

## Verifikasi

```bash
# Anonymous harus ditolak: "Connection Refused: not authorised."
mosquitto_pub -h localhost -t parkir/1/scan -m x

# Pantau semua topic
mosquitto_sub -h localhost -u two-guards-monitor -P "$MQTT_MONITOR_PASSWORD" -t 'parkir/#' -v

# Di terminal lain: device-1 mencoba membuka gerbang sendiri. Pesan ini TIDAK boleh muncul di
# pemantau, dan mosquitto_pub mencetak "Publish 1 failed: Not authorized."
mosquitto_pub -h localhost -u device-1 -P "$MQTT_DEVICE_PASSWORD" -V mqttv5 -q 1 \
  -t parkir/1/command -m '{"action":"open","access_log_id":1,"issued_at":0}'
```

`issued_at` 0 sengaja basi: kalau ACL ternyata keliru dan pesan ini sampai ke ESP32, firmware tetap
mengabaikannya.

Untuk menguji dari ESP32, jalankan `wifi-test` di firmware: ia mencetak apakah port broker di
laptop bisa dijangkau dari jaringan WiFi.

## Menambah device

Untuk device dengan `id` 2 di tabel `devices`:

1. Tambahkan blok `user device-2` di `acl`, sama seperti `device-1` dengan angka 2.
2. `sudo install -o mosquitto -g mosquitto -m 0600 deploy/mosquitto/acl /etc/mosquitto/two-guards/acl`
3. `sudo mosquitto_passwd /etc/mosquitto/two-guards/passwd device-2`
4. `sudo systemctl reload mosquitto`
