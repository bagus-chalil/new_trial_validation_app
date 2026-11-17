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
- [x] Migration `pm_*` (semua tabel di bagian 4, dengan guard `Schema::hasTable`)
- [x] Model Eloquent + relasi, factory hanya untuk test sqlite
- [x] `ProjectPolicy`, `ImprovementPolicy`, Gate `manage-projects`
- [x] Command artisan impor `projects:import-legacy` (`--dry-run`, `--connection`, idempoten, `COLLATE` eksplisit), termasuk penyalinan file dan tabel padanan user
- [x] Test Pest: migration, impor (fixture sqlite), idempotensi, policy
- [x] Verifikasi: dry-run di salinan DB lokal mencetak 43 / 249 / 24 / 0 / 131 / 859 / 1
- Selesai jika: gate CI hijau dan angka dry-run cocok dengan bagian 2. **Belum ada UI.** Belum ada impor sungguhan ke DB bersama kecuali user menyetujui.

> **Fase 1 selesai 2026-11-17** (hasil lengkap di bagian 7). Impor sungguhan ke DB bersama BELUM dijalankan.

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

### Hasil Fase 1 (2026-11-17)

Dibangun (tanpa UI): migration `2026_11_17_000001/2` (8 tabel pm_*, semua `Schema::hasTable`, tanpa FK), 8 model (`Project`, `ProjectStage`, `ProjectMilestone`, `ProjectQuotation`, `ProjectFile`, `ProjectStageTemplate`, `Improvement`, `ImprovementFile`), `ProjectPolicy`, `ImprovementPolicy`, Gate `manage-projects` (Admin), koneksi `project_tracker` (env `PT_DB_*`, hanya baca), disk `project_files`, `config/projects.php`, command `projects:import-legacy` (`--dry-run`, `--connection`, `--files-path`, `--user-map`) + `App\Services\Projects\LegacyProjectImporter`. Test: `ProjectsImportLegacyTest` (7) + `ProjectPolicyTest` (3).

Hasil `--dry-run` terhadap DB lokal (baca saja, tidak menulis apa pun): projects 43, stages 249 (Validation 48), quotations 24, files 131 (130 pm_files + 1 pm_improvement_files; konteks stage 76, project 25, quotation 21, tooling 8), logs 859, improvement 1, milestones **1** (bukan 0: tabel `project_milestones` lokal sekarang berisi 1 baris), stage template 3, 4 kategori, 16 vendor.

Temuan penting dari data sumber (mengubah rancangan):
- `project_stages` dan `project_quotations` dibuat ulang dengan UUID baru setiap project disimpan di app lama, dan tabel stage membuang tanggal actual + progress. Maka stage dan quotation diimpor dari `projects.metadata` (ID stabil, lengkap: plan/actual, progress, parallel, lampiran per stage); jumlah tabel dicetak sebagai pembanding (cocok: 249 dan 24). `pm_stages` punya kolom plan_start/plan_end/actual_start/actual_end/progress/is_parallel.
- `project_logs.project_id` berisi id project ATAU label modul (AUTH 344, TOOLING 31, FILE 18, IMPROVEMENT 9, SYSTEM 11); semua 859 diimpor ke `activity_logs` (`module=PROJECT`, `action=LEGACY_LOG`, `record_id=legacy:<id>`, tanggal asli). `metadata.log` (363 entri) disimpan utuh di `pm_projects.legacy_meta` (beserta `pos`, 3 entri PO).
- **File fisik tidak ada di checkout ini**: `Project-Tracking-PIE-Team/storage/files` kosong, `PT_FILES_DIR` tidak diset. Dry-run melaporkan 131 file "missing". Salin + verifikasi ukuran sudah dites dengan fixture, tetapi belum bisa dibuktikan terhadap 131 file asli. Impor akan tetap mengimpor barisnya dengan `is_missing=true`; jalankan ulang dengan `--files-path=<folder asli>` (idempoten) saat folder tersedia.
- `settings.tracker_state` juga berisi `toolings` (fitur Check Tooling, 1 data) di luar rencana ini; tidak dimigrasi. 8 file `TLG-*` tetap disalin sebagai `pm_files.context=tooling`.
- Tidak ada JOIN lintas DB: pencocokan user/vendor dilakukan di PHP, sehingga error collation 1267 tidak mungkin terjadi. Tabel `project_tracker` lokal bercollation `utf8mb4_0900_ai_ci` (bukan unicode_ci seperti di rencana). Kalau nanti ada JOIN SQL lintas DB, wajib `COLLATE` eksplisit.
- Padanan user: otomatis (nama/email persis, tidak ambigu) + `--user-map` / `config/projects.php`. Belum cocok dan disimpan sebagai teks: Rozy, Validasi, Zahra (PIC aktif Zahra dan Rozy perlu dipetakan lewat `--user-map` sebelum impor sungguhan). Tidak pernah membuat akun.
- Impor ulang meng-update baris per `legacy_id` tetapi tidak menimpa kolom yang hanya ada di app baru (mis. `pm_stages.trial_id`); teruji.

Gate: Pint, ESLint, tsc, `npm run build`, Pest proyek ini lulus. Kegagalan yang sudah ada sebelum Fase 1 (dikonfirmasi pada baseline tanpa perubahan saya): Larastan 1 error di migration `2026_10_09_000001_rename_pi_review_team_to_pie.php`, `ExportLocaleTest` (ekstensi PHP `ZipArchive` tidak aktif di mesin ini), Prettier mengeluh `resources/ai/trial_document.schema.json`, 1 warning ESLint `incompatible-library`.

Keputusan K1-K3: memakai default bagian 3 (tidak membuat akun; peninjau Improvement = department PI/PIE; kolom budget/actual dibuat, UI ditunda).

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

### Prompt Fase 2 (tempel di chat baru)

```
Lanjutkan penggabungan Project Tracking PIE ke new_trial_validation_app/ (branch feat/integration-project-tracking). Baca dulu: PROJECT_MODULE_PLAN.md (bagian 4, 5 Fase 2, dan bagian 7 "Hasil Fase 1"), CLAUDE.md di root dan new_trial_validation_app/CLAUDE.md (bagian "Projects module — Fase 1"; juga catatan insiden DB 2026-08-24 dan aturan prod additive-only). Fase 1 sudah selesai dan di-push: tabel pm_*, model Project/ProjectStage/ProjectMilestone/ProjectQuotation/ProjectFile/ProjectStageTemplate/Improvement/ImprovementFile, ProjectPolicy, ImprovementPolicy, Gate manage-projects, command projects:import-legacy. Impor sungguhan ke DB bersama BELUM dijalankan (tabel pm_* belum ada di trial_validation_system; jangan migrate manual ke DB bersama, kerjakan dan uji di sqlite; verifikasi live hanya baca atau pakai salinan DB lokal yang disetujui user).

Kerjakan HANYA Fase 2 (Inti Project, UI):
1. Sidebar grup "Projects" (Portofolio, Gantt, Improvement, Quotation & PO) role-gated, string i18n ID/EN/KO mengikuti konvensi "Multi-language UI".
2. Portofolio: KPI, daftar + filter, project aktif, kartu "Perlu perhatian".
3. Form project (buat/ubah), halaman detail project ("detail dulu baru aksi"), CRUD stage, milestone.
4. Quotation & PO; upload dan unduh file lewat route ter-otorisasi (disk project_files, whitelist MIME dan tolak ekstensi script seperti Project Tracking lama, tanpa path publik).
5. Log ke activity_logs module PROJECT.
Pakai ProjectPolicy/Gate yang sudah ada, komponen yang sudah ada (Table, Dialog, Combobox, toast), controller + Action class + FormRequest seperti pola Fase 3 Trial. Kolom budget/actual: dibuat di DB tetapi UI-nya ditunda (K3). Data uji: pakai tabel pm_* di sqlite; boleh memakai hasil impor dari fixture.

Batasan: jangan migrate:fresh/refresh/db:wipe, jangan pakai factory di tinker, jangan menyentuh/commit Project-Tracking-PIE-Team/, tidak ada impor sungguhan sebelum user menyetujui. K1-K3 belum dijawab, pakai default bagian 3.
Definisi selesai: PIC bisa membuat dan mengelola project penuh di app baru; gate CI hijau (catat kegagalan lama yang sudah ada sebelum Fase 1: Larastan di migration rename_pi_review_team_to_pie, ExportLocaleTest/ZipArchive, Prettier resources/ai/trial_document.schema.json); diverifikasi live lewat Playwright pada data nyata. Di akhir ikuti ritual penutup bagian 6 dan tulis prompt Fase 3.
```

### Template prompt fase berikutnya

```
Lanjutkan penggabungan Project Tracking PIE ke new_trial_validation_app/. Baca PROJECT_MODULE_PLAN.md (bagian 5 Fase <N>, bagian 7 Log hasil fase sebelumnya) dan CLAUDE.md. Fase <N-1> sudah selesai dan di-push. Kerjakan HANYA Fase <N>: <ringkasan tugas dari checklist>. Batasan sama seperti bagian 1. Definisi selesai: <dari bagian 5>. Di akhir ikuti ritual penutup bagian 6 dan tulis prompt Fase <N+1>.
```
