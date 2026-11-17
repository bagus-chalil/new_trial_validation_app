# PROJECT_MODULE_PLAN.md — Melebur Project Tracking PIE ke `new_trial_validation_app/`

Dokumen kerja untuk penggabungan **Project-Tracking-PIE-Team** (PHP polos, DB `project_tracker`) menjadi modul **Projects** di `new_trial_validation_app/` (Laravel + Inertia React). Dibuat 2026-11-17 dari kajian di sesi sebelumnya. Dokumen ini adalah sumber kebenaran untuk urutan kerja; **update dokumen ini di akhir setiap fase**.

Bahasa percakapan dengan user: Bahasa Indonesia. Kode, identifier, komentar, dan commit message: English.

## 1. Tujuan dan batasan

- Satu database tujuan: `trial_validation_system`. `project_tracker` hanya **dibaca sekali** untuk memindahkan data, lalu dipensiunkan.
- Project Tracking tidak dicangkok. UI ditulis ulang memakai komponen, tema, dan pola app baru (Table, Dialog, Combobox, toast, "detail dulu baru aksi", i18n ID/EN/KO).
- Prod sudah dipakai. **Hanya tabel baru (additive)**, prefix `pm_`, dengan guard `Schema::hasTable` seperti migration lain. Tidak ada `migrate:fresh`/`refresh`/`db:wipe`, tidak ada `migrate` manual ke DB bersama, tidak ada `--env=` tanpa memastikan file `.env.<nama>` ada (lihat insiden 2026-08-24 di `CLAUDE.md`). Test berjalan di sqlite.
- Jangan pernah memakai factory di tinker (tinker menyentuh DB dev bersama, bukan DB test).
- Command impor hanya **membaca** `project_tracker`, idempoten (kunci `legacy_id`), dan punya `--dry-run`.
- Collation beda: `project_tracker` = `utf8mb4_unicode_ci`, `trial_validation_system` = `utf8mb4_0900_ai_ci`. JOIN lintas DB wajib memakai `COLLATE` eksplisit (kalau tidak: error 1267).

## 2. Data sumber (baseline lokal 2026-11-17)

Dipakai untuk mencocokkan hasil impor.

| Tabel sumber | Baris |
|---|---|
| `projects` | 43 (Machine 18, Improvement 17, Tooling NPD 8) |
| `project_stages` | 249 (48 bernama Validasi/Validation/Trial) |
| `project_quotations` | 24 (Diterima 19, Terpilih 3, Review 2) |
| `project_milestones` | 0 |
| `project_files` | 131 (file fisik di `storage/files` repo Project Tracking) |
| `project_logs` | 859 |
| `users` | 22 (7 akun departemen bersama; hanya 4 cocok otomatis ke users Trial) |
| improvement (blob JSON `settings.tracker_state.improvements`) | 1 |

Catatan data:
- `projects.metadata` JSON dan `settings.tracker_state` menyimpan salinan stage/log/PO/improvement. Sumber kebenaran stage adalah tabel `project_stages`; blob dipakai hanya untuk improvement dan riwayat.
- ID sumber berupa string acak 36 karakter (bukan UUID asli). Simpan sebagai `legacy_id`.
- `pic`, `updated_by`, `actor` berupa teks nama. Petakan ke `users` Trial lewat tabel padanan; kalau tidak ketemu, simpan teksnya di kolom `*_name` dan biarkan `*_user_id` null.
- Semua `budget`/`actual` bernilai 0. Vendor berupa teks bebas.
- Dua PIC aktif: Zahra dan Rozy.

## 3. Keputusan terbuka (jawaban user menentukan fase terkait)

| # | Keputusan | Mempengaruhi | Default sementara |
|---|---|---|---|
| K1 | 7 akun departemen bersama (QA, PROD, PRNI, QC, BM, RNI, Validasi): jadi orang nyata, atau digantikan hak "semua user login boleh mengajukan Improvement" | Fase 1 (padanan user), Fase 3 | Jangan buatkan akun. Riwayat tetap tersimpan di `*_name` teks. |
| K2 | Siapa peninjau Improvement (department PI atau orang tertentu) | Fase 3 | Department PI |
| K3 | Budget vs Actual ikut dibawa atau tidak | Fase 2 | Bawa Quotation & PO. Kolom budget/actual dibuat tapi UI-nya ditunda. |

Kalau user belum menjawab, pakai default dan catat di bagian Log keputusan (bagian 7).

## 4. Rancangan data

| Sumber | Tujuan | Catatan |
|---|---|---|
| `projects` | `pm_projects` | `legacy_id`, `pic_user_id` (nullable) + `pic_name`, `machine_option_id` (nullable, ke `master_options`), `product_id` (nullable), `deleted_at`/`deleted_by` |
| `project_stages` | `pm_stages` | tambah `stage_type` (Validation/lainnya), `trial_id` nullable |
| `project_milestones` | `pm_milestones` | 0 baris, tetap dibuat |
| `project_quotations` | `pm_quotations` | vendor jadi `master_options` type `project_vendor` |
| `project_files` + file fisik | `pm_files` | salin 131 file ke disk baru, verifikasi ukuran. Pertahankan whitelist MIME dan penolakan ekstensi script dari Project Tracking |
| improvement (blob) | `pm_improvements`, `pm_improvement_files` | status: Baru, Review PI, Menunggu Data, Disetujui, Tidak Perlu Improvement. Keputusan: Pending, To Project, Suggestion Only |
| template stage, kategori (`settings`) | `pm_stage_templates`, `master_options` | logo base64 tidak dibawa |
| `project_logs`, `metadata.log` | `activity_logs` `module='PROJECT'` | pertahankan tanggal asli |
| `users`, `sessions`, `login_attempts` | dibuang | pakai `users` Trial |

Hak akses: admin → Admin/Super Admin (Gate `manage-projects`); user/PIC → Staff (`ProjectPolicy`: boleh ubah jika PIC atau pembuat); contributor → semua user login, hanya Improvement miliknya (`ImprovementPolicy`); viewer → Viewer baca saja.

## 5. Fase

Setiap fase = satu sesi/chat. Jangan lompat fase. Branch kerja: `feat/integration-project-tracking`.

### Fase 1 — Fondasi data
- [ ] Migration `pm_*` (semua tabel di bagian 4, dengan guard `Schema::hasTable`)
- [ ] Model Eloquent + relasi, factory hanya untuk test sqlite
- [ ] `ProjectPolicy`, `ImprovementPolicy`, Gate `manage-projects`
- [ ] Command artisan impor `projects:import-legacy` (`--dry-run`, `--connection`, idempoten, `COLLATE` eksplisit), termasuk penyalinan file dan tabel padanan user
- [ ] Test Pest: migration, impor (fixture sqlite), idempotensi, policy
- [ ] Verifikasi: dry-run di salinan DB lokal mencetak 43 / 249 / 24 / 0 / 131 / 859 / 1
- Selesai jika: gate CI hijau dan angka dry-run cocok dengan bagian 2. **Belum ada UI.** Belum ada impor sungguhan ke DB bersama kecuali user menyetujui.

### Fase 2 — Inti Project (UI)
- [ ] Sidebar grup "Projects" (Portofolio, Gantt, Improvement, Quotation & PO), role-gated, string i18n ID/EN/KO
- [ ] Portofolio: KPI, daftar + filter, project aktif, kartu "Perlu perhatian"
- [ ] Form project (buat/ubah), detail project, stage CRUD, milestone
- [ ] Quotation & PO, upload file lewat route ter-otorisasi
- [ ] Log ke `activity_logs`
- Selesai jika: PIC bisa membuat dan mengelola project penuh di app baru; gate CI hijau; diverifikasi live lewat Playwright pada data nyata.

### Fase 3 — Gantt, My Work, Improvement
- [ ] Gantt
- [ ] Project/Improvement milik user masuk ke My Work yang sudah ada (tanpa halaman "Pekerjaan Saya" terpisah)
- [ ] Board Improvement (ajukan, Tinjauan PI, "To Project" membuat project dengan data terisi)
- Selesai jika: paritas fitur dengan Project Tracking lama.

### Fase 4 — Tautan ke Trial
- [ ] Stage tipe Validation punya `trial_id`; tombol membuat draft Trial dari stage
- [ ] Status stage ikut status Trial (Approved → stage Completed)
- [ ] Tautan mesin dan produk (combobox), notifikasi
- [ ] Rencana bot: intent status project (opsional, boleh ditunda)

### Fase 5 — Cutover
- [ ] Impor final ke DB prod (user menyetujui eksplisit, backup DB dulu)
- [ ] Bekukan app lama (read-only), verifikasi jumlah baris
- [ ] Arsipkan `project_tracker`, tambahkan link/redirect dari portal
- [ ] Update `deploy/README.md` dan `portal/`

## 6. Ritual penutup setiap fase (wajib)

Saat semua item fase selesai dan gate hijau:

1. Jalankan gate lengkap di `new_trial_validation_app/` (Pint, Larastan, `php artisan test`, ESLint, Prettier, tsc, `npm run build`). Laporkan hasil apa adanya, termasuk yang gagal.
2. Update dokumen ini: centang item, isi "Hasil" fase (apa yang dibangun, angka verifikasi, keputusan yang diambil, masalah yang ditemukan).
3. Update `CLAUDE.md` (root dan `new_trial_validation_app/CLAUDE.md`): tambahkan entri status fase, dengan gaya entri Fase sebelumnya.
4. `git status` dulu, stage file spesifik (bukan `git add -A`), commit dengan pesan English, push ke `feat/integration-project-tracking`. Jangan menyertakan folder `Project-Tracking-PIE-Team/` atau file rahasia.
5. Tulis prompt untuk fase berikutnya (siap tempel di chat baru), berisi: konteks singkat, file wajib dibaca, daftar tugas fase itu, batasan, dan definisi selesai. Letakkan di akhir jawaban dan simpan ringkasannya di bagian Log.

## 7. Log keputusan dan hasil fase

_(diisi per fase)_

- 2026-11-17: kajian selesai, dokumen dibuat. K1-K3 belum dijawab, memakai default bagian 3.

## 8. Prompt siap pakai

### Prompt Fase 1 (tempel di chat baru)

```
Kita mengerjakan penggabungan Project Tracking PIE menjadi modul "Projects" di new_trial_validation_app/. Baca dulu: PROJECT_MODULE_PLAN.md (repo root, sumber kebenaran urutan kerja), CLAUDE.md di root dan new_trial_validation_app/CLAUDE.md (terutama catatan insiden DB 2026-08-24 dan aturan prod additive-only), lalu skema sumber di Project-Tracking-PIE-Team/schema.sql, db.php, api.php (cari tracker_state untuk improvement).

Kerjakan HANYA Fase 1 (Fondasi data), belum ada UI:
1. Migration tabel pm_* sesuai bagian 4 PROJECT_MODULE_PLAN.md, semua dengan guard Schema::hasTable (DB bersama tidak boleh disentuh oleh migrate manual; test jalan di sqlite).
2. Model Eloquent + relasi, ProjectPolicy, ImprovementPolicy, Gate manage-projects, mengikuti pola TrialPolicy dan gate yang sudah ada.
3. Command artisan projects:import-legacy: baca DB project_tracker (read-only, koneksi terpisah via env), --dry-run, idempoten lewat legacy_id, COLLATE eksplisit untuk JOIN lintas DB, memetakan pic/updated_by ke users Trial lewat tabel padanan (kalau tidak ketemu simpan teks di *_name, jangan buat akun), menormalisasi improvement dari blob JSON settings.tracker_state, menyalin 131 file ke disk baru dengan verifikasi ukuran, log ke activity_logs module PROJECT.
4. Test Pest (sqlite): migration, impor dari fixture, idempotensi (jalan dua kali tidak menduplikasi), policy.
5. Verifikasi: jalankan --dry-run terhadap DB lokal dan pastikan hitungan cocok: 43 projects, 249 stages, 24 quotations, 0 milestones, 131 files, 859 logs, 1 improvement. Jangan menjalankan impor sungguhan ke trial_validation_system sebelum saya setujui.

Batasan: jangan migrate:fresh/refresh/db:wipe, jangan pakai factory di tinker, jangan menyentuh Project-Tracking-PIE-Team/ (hanya dibaca), jangan commit folder itu. Keputusan K1-K3 belum dijawab: pakai default di PROJECT_MODULE_PLAN.md bagian 3.

Setelah selesai, ikuti "Ritual penutup" bagian 6: gate lengkap, update PROJECT_MODULE_PLAN.md dan CLAUDE.md, commit + push ke feat/integration-project-tracking, lalu tulis prompt Fase 2 untuk chat baru.
```

### Template prompt fase berikutnya

```
Lanjutkan penggabungan Project Tracking PIE ke new_trial_validation_app/. Baca PROJECT_MODULE_PLAN.md (bagian 5 Fase <N>, bagian 7 Log hasil fase sebelumnya) dan CLAUDE.md. Fase <N-1> sudah selesai dan di-push. Kerjakan HANYA Fase <N>: <ringkasan tugas dari checklist>. Batasan sama seperti bagian 1. Definisi selesai: <dari bagian 5>. Di akhir ikuti ritual penutup bagian 6 dan tulis prompt Fase <N+1>.
```
