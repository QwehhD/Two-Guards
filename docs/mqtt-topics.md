# Kontrak topic MQTT

Dokumen ini adalah kontrak antara server Two-Guards dan perangkat portal (ESP32 + RFID).
Firmware dibangun mengikuti dokumen ini; kalau kode dan dokumen ini berbeda, perbaiki
salah satunya bersamaan, jangan dibiarkan.

## Ringkasan

| Topic | Arah | QoS | Retain | Dikirim oleh |
| --- | --- | --- | --- | --- |
| `parkir/{device_id}/scan` | device → server | 1 | tidak | ESP32 |
| `parkir/{device_id}/status` | device → server | 1 | tidak | ESP32, dan broker (LWT) |
| `parkir/{device_id}/command` | server → device | 1 | tidak | `PublishDeviceCommand` (manual) atau `mqtt:listen` (auto) |

`{device_id}` adalah `id` numerik di tabel `devices`. Broker diatur lewat `MQTT_HOST`,
`MQTT_PORT`, `MQTT_USERNAME`, dan `MQTT_PASSWORD`.

Broker menolak koneksi tanpa akun, dan ACL-nya membatasi tiap akun (pemasangan:
[deploy/mosquitto/README.md](../deploy/mosquitto/README.md)):

| User | Baca | Tulis |
| --- | --- | --- |
| `two-guards-server` | `parkir/+/scan`, `parkir/+/status` | `parkir/+/command` |
| `device-{device_id}` | `parkir/{device_id}/command` | `parkir/{device_id}/scan`, `parkir/{device_id}/status` (termasuk LWT) |
| `two-guards-monitor` | `parkir/#` | tidak ada |

Hanya server yang boleh publish ke topic command. Device tidak bisa membuka gerbangnya sendiri,
apalagi gerbang device lain, walaupun password-nya bocor.

Client ID server memakai `MQTT_CLIENT_ID_PREFIX`: daemon memakai `{prefix}-listener`, setiap
publish dari job memakai `{prefix}-publisher-{acak}`. Dua client dengan ID yang sama saling
memutus koneksi di broker, jadi jangan menjalankan dua `mqtt:listen` dengan prefix yang sama.

## `parkir/{device_id}/scan`

Dikirim device setiap kali kartu ditempelkan.

```json
{"uid": "A1B2C3D4", "scanned_at": 1791707464}
```

| Field | Wajib | Keterangan |
| --- | --- | --- |
| `uid` | ya | String tidak kosong, UID kartu apa adanya. |
| `scanned_at` | tidak | Waktu scan di device. **Disarankan epoch detik** (UTC) sebagai integer, misalnya `1791707464`, sama seperti `issued_at` pada command. Juga diterima: string berisi angka saja (`"1791707464"`) dan ISO-8601 (`"2026-10-11T08:31:04Z"`, atau dengan offset seperti `+07:00`, yang dikonversi ke UTC). Pecahan detik (`1791707464.5`) dan teks lain tidak terbaca; server lalu memakai waktu terima dan mencatat warning. |

Payload yang rusak, topic yang bentuknya lain, atau `device_id` yang tidak terdaftar diabaikan
dengan warning di log.

Keputusannya:

- Device mode **auto**: kartu aktif → `approved`, selain itu → `denied`. Command langsung
  dikirim balik (lihat di bawah).
- Device mode **manual**: selalu `pending`, menunggu Approve/Reject di halaman Persetujuan.
  Bila tidak diputuskan dalam 30 detik, scan menjadi `expired` dan **tidak ada command**.

## `parkir/{device_id}/status`

Heartbeat device, sekaligus Last Will (LWT) yang dikirim broker ketika device putus.

```json
{"online": true, "portal_status": "closed"}
```

| Field | Wajib | Keterangan |
| --- | --- | --- |
| `online` | ya | `true`/`false`. |
| `portal_status` | tidak | `open` atau `closed`. Nilai lain diabaikan. |

LWT cukup `{"online": false}` tanpa `portal_status`: device yang putus tidak tahu posisi
palang, jadi server hanya menandai offline dan tidak menebak status portal.

## `parkir/{device_id}/command`

Perintah ke portal.

```json
{"action": "open", "access_log_id": 123, "issued_at": 1791707464}
```

| Field | Tipe | Keterangan |
| --- | --- | --- |
| `action` | string | `open` = buka portal, `deny` = tolak (portal tetap tertutup). |
| `access_log_id` | integer | `id` baris `access_logs` yang memutuskan command ini. |
| `issued_at` | integer | Waktu keputusan dalam epoch detik (UTC), sama dengan `processed_at`. |

Payload dan topic ini dibangun di satu tempat saja, `App\Mqtt\DeviceCommand`.

### Kapan dikirim

- **Mode auto**: oleh `mqtt:listen`, langsung setelah scan diputuskan, lewat koneksi persisten
  daemon itu sendiri (tanpa antrean, tanpa membuka koneksi baru).
- **Mode manual**: setelah Approve/Reject di website, oleh job `PublishDeviceCommand` di queue
  `device-commands` yang dikerjakan worker tersendiri.
- **Tidak pernah**: untuk scan yang `expired`.

### Jaminan dari sisi server

- **TTL.** Sebuah command hanya boleh terkirim selama `MQTT_COMMAND_TTL` detik (bawaan 10)
  sejak `issued_at`. Setelah itu tidak ada pengirim yang mengirimnya lagi: perintah `open`
  yang terlambat lebih berbahaya daripada yang gagal.
- **Mode manual.** Job menunggu PUBACK dari broker (`MQTT_PUBACK_TIMEOUT`, bawaan 2 detik),
  mencoba ulang tiap 1 detik sampai TTL, dan menolak mengirim command yang sudah basi.
  Percobaan yang macet (misalnya broker menerima koneksi tetapi tidak pernah membalas CONNACK)
  diputus setelah `MQTT_CONNECT_TIMEOUT + MQTT_PUBACK_TIMEOUT + 3` detik. Kegagalan permanen
  dicatat sekali: `PublishDeviceCommand: device command was never delivered.`
- **Mode auto.** Tidak ada kiriman ulang. Command yang belum di-PUBACK sampai TTL dibuang dari
  antrean kirim ulang php-mqtt dan dicatat:
  `mqtt:listen: broker never acknowledged a device command; dropped instead of resending.`
- Setiap log di atas memuat `access_log_id`, `device_id`, dan `action`.
- **PUBACK hanya membuktikan broker menerima command**, bukan bahwa device menerimanya atau
  portal benar-benar terbuka.

### Aturan untuk firmware

1. **Sinkronkan jam lewat NTP** sebelum memproses command. Tanpa jam yang valid, abaikan
   `open` (gagal tertutup).
2. **Abaikan command basi**: bila `now - issued_at > 15`. Angka 15 = TTL server 10 detik +
   toleransi selisih jam 5 detik. Abaikan juga `issued_at` yang lebih dari 5 detik di masa
   depan.
3. **Dedupe berdasarkan `access_log_id`**: simpan beberapa `access_log_id` terakhir dan
   abaikan yang sudah diproses. QoS 1 dan retry server berarti command yang sama bisa datang
   lebih dari sekali.
4. **Subscribe `parkir/{device_id}/command` dengan QoS 1 dan clean session**, supaya broker
   tidak menimbun command selama device offline lalu mengirimkannya sekaligus saat device
   tersambung lagi.
5. Belum ada ack end-to-end dari device. `access_log_id` sudah disiapkan untuk itu: rencananya
   device melaporkan hasil eksekusi beserta `access_log_id`.

## Mencoba tanpa hardware

Contoh di bawah membaca password dari variabel shell supaya tidak tersimpan di riwayat. Isi
dulu dengan `read -rs MQTT_DEVICE_PASSWORD` dan `read -rs MQTT_MONITOR_PASSWORD` (bash/zsh),
atau `read -s MQTT_DEVICE_PASSWORD` (fish). Simulasi scan memakai akun `device-1`, pemantauan
memakai `two-guards-monitor`.

```bash
# Pantau semua command yang dikirim server (dengan timestamp)
mosquitto_sub -h localhost -u two-guards-monitor -P "$MQTT_MONITOR_PASSWORD" \
  -t 'parkir/+/command' -F '%I | %t | %p'

# Pantau semua topic
mosquitto_sub -h localhost -u two-guards-monitor -P "$MQTT_MONITOR_PASSWORD" -t 'parkir/#' -v

# Scan di device 1 (auto: langsung dibalas command; manual: muncul di halaman Persetujuan)
mosquitto_pub -h localhost -u device-1 -P "$MQTT_DEVICE_PASSWORD" \
  -t parkir/1/scan -m '{"uid":"A1B2C3D4"}'

# Scan dengan waktu scan dari device (epoch detik, seperti yang dikirim ESP32)
mosquitto_pub -h localhost -u device-1 -P "$MQTT_DEVICE_PASSWORD" \
  -t parkir/1/scan -m "{\"uid\":\"A1B2C3D4\",\"scanned_at\":$(date +%s)}"

# Heartbeat dan LWT
mosquitto_pub -h localhost -u device-1 -P "$MQTT_DEVICE_PASSWORD" \
  -t parkir/1/status -m '{"online":true,"portal_status":"closed"}'
mosquitto_pub -h localhost -u device-1 -P "$MQTT_DEVICE_PASSWORD" \
  -t parkir/1/status -m '{"online":false}'
```

Command tidak bisa disimulasikan dengan `device-1` maupun `two-guards-monitor`: ACL menolaknya
diam-diam (penolakannya baru terlihat dengan `mosquitto_pub -V mqttv5 -q 1`). Untuk memicu
command, simulasikan scan seperti di atas.
