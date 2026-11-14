# CBT Settings Fix Plugin

Plugin WordPress untuk memperbaiki fitur CBT Bimasoft yang tidak terhubung dengan pengaturan admin (`pengaturan-test`).

## Masalah yang Diperbaiki

1. **Pendaftaran Mandiri** - Tombol "Daftar Akun Baru" tidak muncul di halaman login meskipun sudah diaktifkan di pengaturan
2. **Auto Token** - Token tidak otomatis terisi setelah login meskipun "Token Secara Otomatis" diset "Ya"
3. **Minimal Sisa Waktu** - Pengaturan minimal sisa waktu sebelum boleh mengumpulkan tidak diterapkan
4. **Sinkronisasi Settings** - Perubahan di halaman pengaturan-test tidak tersinkronisasi ke `indb.php`

## Cara Kerja

- Plugin membaca settings dari `wp_options` (tabel WordPress)
- Meng-inject JavaScript ke halaman frontend untuk menambahkan fitur yang hilang
- Setiap kali pengaturan disimpan, plugin otomatis mengupdate `indb.php` agar file-file terenkripsi juga membaca nilai terbaru

## Instalasi

1. Upload folder `cbt-settings-fix` ke `wp-content/plugins/`
2. Aktifkan plugin dari menu Plugins di WordPress Admin
3. Simpan ulang pengaturan di halaman Pengaturan Test untuk trigger sinkronisasi

## Option WordPress yang Digunakan

| Option Name | Keterangan |
|---|---|
| `siswa-melakukan-pendaftaran-sendiri` | Ya/Tidak |
| `token-secara-otomatis` | Ya/Tidak |
| `masa-aktif-token` | Menit |
| `exam-timer` | Menit |
| `minimal-sisa-waktu-sebelum-boleh-mengumpulkan` | Menit |
| `tampilkan-nilai-siswa-di-akhir-ujian` | Ya/Tidak |
| `useragent` | String |
| `timezone` | String |
| `examkey` | String |

