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
- [x] Sidebar grup "Projects" (Portofolio, Quotation & PO), role-gated, string i18n ID/EN/KO. **Gantt dan Improvement belum ada di sidebar** (halamannya baru dibangun di Fase 3; tidak ada link ke halaman yang belum ada)
- [x] Portofolio: KPI, daftar + filter, project aktif, kartu "Perlu perhatian"
- [x] Form project (buat/ubah), detail project, stage CRUD, milestone
- [x] Quotation & PO, upload file lewat route ter-otorisasi
- [x] Log ke `activity_logs` (module PROJECT)
- Selesai jika: PIC bisa membuat dan mengelola project penuh di app baru; gate CI hijau; diverifikasi live lewat Playwright pada data nyata.

> **Fase 2 selesai 2026-11-17** (hasil lengkap di bagian 7).

### Fase 3 — Gantt, My Work, Improvement
- [x] Gantt
- [x] Project/Improvement milik user masuk ke My Work yang sudah ada (tanpa halaman "Pekerjaan Saya" terpisah)
- [x] Board Improvement (ajukan, Tinjauan PI, "To Project" membuat project dengan data terisi)
- Selesai jika: paritas fitur dengan Project Tracking lama.

> **Fase 3 selesai 2026-11-17** (hasil lengkap di bagian 7).

### Fase 4 — Tautan ke Trial
- [x] Stage tipe Validation punya `trial_id`; tombol membuat draft Trial dari stage
- [x] Status stage ikut status Trial (Approved → stage Completed)
- [x] Tautan mesin dan produk (combobox), notifikasi
- [ ] Rencana bot: intent status project (opsional) — **ditunda**, tidak dikerjakan di Fase 4

> **Fase 4 selesai 2026-11-23** (hasil lengkap di bagian 7).

### Fase 4b — Check Tooling (ditambahkan 2026-11-23, syarat sebelum cutover prod)
App lama prod mendapat modul "Check Tooling" setelah 17 Nov (tidak ada di salinan lokal yang dipakai Fase 1-4). User memutuskan: **bangun dulu di app baru, cutover ditunda**, app lama tetap jalan. Temuan lengkap di bagian 7 "Hasil Fase 5 Tahap C (sementara)".
- [x] Tabel `pm_toolings` (+ item tools, approvals, history; file pakai `pm_files` context `tooling` yang sudah diimpor) — additive, `Schema::hasTable`
- [x] Importer: baca `settings.tracker_state.toolings` (idempoten via `legacy_id`), tautkan file `TLG-*` ke tooling-nya; sekalian petakan `improvements[].afterAttachments` (file `IMP-AFTER-*`, sekarang jatuh ke `pm_files` tanpa project)
- [x] Alur status sesuai app lama (`api.php` ~1130-1160): Menunggu RNI & Sampel → Menunggu Review RNI / Menunggu Sampel → Cek Tooling PI → Review Production → Approved (+ Revisi / Hold, Ditolak); approval per peran PRNI/RNI/PI/Production dengan tanda tangan; status sampel
- [x] Peran "Kontributor" app lama (akun PRNI/RNI/Production, hanya Improvement + Check Tooling) dipetakan ke user/departemen app baru (keputusan K5, bagian 7)
- [x] UI daftar + detail ("detail dulu baru aksi"), My Work, string id/en/ko, Pest
- [x] Gladi ulang lokal dengan sumber prod (`project_tracker_prod` → salinan baru `trial_validation_system_fase4b`, bagian 7)

> **Fase 4b selesai 2026-11-23** (hasil lengkap di bagian 7). Cutover prod (Fase 5 Tahap C) belum dijalankan.

### Fase 5 — Cutover
Tahap A (gladi bersih MySQL lokal) dan Tahap B (runbook, disetujui user 2026-11-23) selesai; eksekusi prod menunggu perintah user. Runbook: `deploy/README.md` §14.
- [x] Gladi bersih lokal: backup, 2 migration pm_* dengan `--path` (sejak Fase 4b: 3 migration), dry-run + impor + sync, verifikasi live, uji tautan stage-trial lalu dipulihkan
- [x] Keputusan K1/K3/K4 + jadwal sync (bagian 7)
- [x] Runbook cutover prod + rollback (`deploy/README.md` §14); `.env.example` memuat `PT_*`/`PROJECT_FILES_PATH`
- [x] `portal/`: tidak perlu entri baru (modul ada di dalam app Trial); app lama dialihkan lewat nginx (runbook b#12)
- [ ] Impor final ke DB prod (user menyetujui eksplisit, backup DB dulu) — runbook §14 b#1-11
- [ ] Bekukan app lama, verifikasi jumlah baris, alihkan ke `/projects` — runbook §14 b#1, b#8, b#12
- [ ] Arsipkan `project_tracker` (dump + folder file offline, cabut hak tulis; jangan hapus < 1 bulan) — runbook §14 c

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

### Hasil Fase 2 (2026-11-17)

Dibangun: `routes/projects.php` (`projects.*`), controller per slice (`ProjectController` index/create/store/show/edit/update/destroy, `ProjectStageController`, `ProjectMilestoneController`, `ProjectQuotationController` termasuk daftar lintas project, `ProjectFileController` store/show/destroy), 11 Action class di `app/Actions/Projects/` (`SaveProject`, `DeleteProject` soft delete, `SaveProjectStage`/`DeleteProjectStage`, milestone, quotation, `StoreProjectFile`/`DeleteProjectFile`, `RecalculateProjectProgress`, `LogProjectActivity`), FormRequest di `app/Http/Requests/Projects/`, `App\Support\ProjectOptions` (kosakata status + whitelist MIME). UI: `pages/projects/{index,form,show,quotations}.tsx`, `components/projects/{project-dialogs,progress-bar}.tsx`, `lib/project.ts`; sidebar grup Project, breadcrumb, lang group `projects` (id/en/ko, paritas key dites).

Keputusan/perilaku penting:
- Hak akses memakai `ProjectPolicy` apa adanya (semua login boleh lihat; Staff PIC/pembuat atau Admin boleh ubah; hanya Admin hapus). Tombol aksi disembunyikan lewat prop `can`, server tetap menolak (403).
- Progress project = rata-rata progress stage (dihitung ulang tiap simpan/hapus stage); input progress di form hanya aktif selama belum ada stage. Stage status Completed memaksa 100%, progress 100 memaksa Completed. Stage bernama valid/trial otomatis bertipe Validation (kecuali dipilih).
- File: disk `project_files` privat, nama acak `<32hex>.<ext>`, whitelist ekstensi + MIME terdeteksi harus cocok, nama berekstensi script di mana pun ditolak (`shell.php.png`), maks 100 MB, unduh lewat route ber-policy dengan `nosniff` + CSP sandbox, tampil inline hanya untuk tipe aman. Upload quotation menggantikan file lama (satu file per quotation, karena `pm_files` tidak punya kolom quotation_id; tautannya `pm_quotations.file_id`). Hapus file = hapus baris + file fisik.
- "Perlu perhatian": belum Completed dan (status Delayed/On Hold, lewat end_date, atau ada stage belum selesai lewat plan_end), maks 6.
- Vendor quotation: teks bebas (datalist dari master `project_vendor`); `vendor_option_id` terisi hanya jika nama cocok master, tidak pernah membuat master baru. Kolom budget/actual project tidak ada di UI (K3); nilai quotation ada.
- Log: CREATE/UPDATE/DELETE, *_STAGE/_MILESTONE/_QUOTATION, UPLOAD_FILE/DELETE_FILE, `record_id` = id project; halaman detail menampilkan 30 terakhir.

Verifikasi: `ProjectsModuleTest` 17 test (portfolio/filter/KPI/attention, create/update/delete + policy, stage/milestone/quotation CRUD, upload/unduh/hapus, penolakan script/MIME/target asing, 404 file hilang, paritas lang). Suite penuh 639 test: 635 lulus, 3 skip lama, 1 gagal lama (`ExportLocaleTest`/ZipArchive). Pint, ESLint (1 warning lama), tsc, `npm run build` lulus; Prettier hanya mengeluh `resources/ai/trial_document.schema.json` (lama); Larastan 1 error lama (migration rename_pi_review_team_to_pie). Live Playwright (Chromium headless, 1440 + 390px, id/en/ko) terhadap **DB sqlite sementara di scratchpad yang diisi `projects:import-legacy` dari `project_tracker` lokal (baca saja)**: DB bersama `trial_validation_system` tidak disentuh, tidak ada migrate ke sana. Data nyata: 43 project, KPI/atensi/filter benar; buat project + stage + milestone + quotation + upload + unduh + edit + hapus berjalan; anonim diarahkan ke login; tanpa error console; tanpa overflow horizontal di mobile.

Temuan:
- Riwayat impor (`LEGACY_LOG`, `record_id=legacy:<id>`) tidak muncul di Riwayat Aktivitas project karena kunci log baru adalah id numerik; perlu pemetaan `legacy_id` -> id (Fase 3 atau saat cutover).
- Item sidebar "Portofolio" tidak menyala di halaman detail/form (aktif hanya pada URL persis); kosmetik.
- `UploadedFile::fake()` menebak MIME dari nama, bukan isi; tes ketidakcocokan isi memakai file temp sungguhan.

### Hasil Fase 3 (2026-11-17)

Dibangun: `ProjectGanttController` (`projects.gantt`, read-only, filter project/PIC/status, maks 60 project) + `pages/projects/gantt.tsx` (CSS grid tanpa library: bar rencana vs aktual+progres, stage paralel, diamond milestone, garis hari ini, kolom label sticky, scroll horizontal hanya di dalam kartu). Board Improvement: `ImprovementController` (index/store/show/update/review/convert/destroy), `ImprovementFileController`, 7 Action di `app/Actions/Improvements/`, FormRequest di `app/Http/Requests/Improvements/`, halaman `improvements/{index,show}.tsx` + `ImprovementDialog`; lang group baru `improvements` (id/en/ko, paritas dites). `App\Support\ProjectUploadGuard` mengekstrak cek upload (nama script, whitelist ekstensi, MIME cocok, ukuran) dan dipakai bersama oleh `StoreProjectFile` dan `StoreImprovementFile`. My Work: `DashboardController::myWorkData()` menambah `myProjects`, `myImprovements`, dan hitungan improvement yang menunggu tinjauan PI; kartu "Project Saya" dan "Improvement Saya" di `MyWorkSection`. Sidebar: item Gantt + Improvement; `NavItem.activePattern` menyalakan Portofolio di halaman detail/form.

Keputusan/perilaku:
- Improvement: semua non-Viewer boleh mengajukan; kontributor biasa hanya melihat miliknya, Staff/Admin/PI/PIE melihat semua (`ImprovementPolicy::seesAll`). Pengaju mengubah hanya selama status `Baru`. Tinjauan (status, keputusan, umpan balik PI) hanya PI/PIE atau Admin; tiap simpan menambah entri `feedback_log`. Hapus = soft delete, hanya Admin. File improvement memakai disk `project_files` dan route ber-policy dengan pola yang sama seperti file project.
- "To Project" adalah aksi terpisah (`improvements.convert`): membuat project kategori Improvement (status Not Started, prioritas Medium, PIC = pengaju bila masih user aktif, jika tidak yang mengonversi; deskripsi dari Masalah/Latar belakang/Tujuan/Target), menautkan `project_id`, mengubah keputusan jadi To Project dan status Disetujui. Satu improvement hanya bisa dikonversi sekali (error validasi `convert`).
- Log ke `activity_logs` module IMPROVEMENT (CREATE/UPDATE/REVIEW/CONVERT/DELETE/UPLOAD_FILE/DELETE_FILE).

Temuan: asumsi Fase 2 salah. Riwayat impor (`LEGACY_LOG`) memakai `record_id = legacy:<id baris log>`, bukan id project; id project asal ada di `new_data.scope`. Detail project kini mencocokkan lewat `new_data LIKE '%"scope":"<legacy_id>"%'` (432 dari 859 log punya project; sisanya label modul AUTH/TOOLING dst.).

Verifikasi: `ProjectsPhase3Test` 11 test (gantt+filter, hak ajukan, visibilitas, edit hanya saat Baru, tinjauan, konversi sekali, file + guard script/MIME, hapus Admin, My Work, riwayat legacy, paritas lang). Suite penuh 650 test: 646 lulus, 3 skip lama, 1 gagal lama (`ExportLocaleTest`/ZipArchive). Pint, ESLint (1 warning lama), tsc, `npm run build` lulus; Prettier hanya `resources/ai/trial_document.schema.json` (lama); Larastan hanya error lama migration `rename_pi_review_team_to_pie`. Live Playwright (Chromium headless, 1440 + 390px) terhadap sqlite sementara di scratchpad yang diisi `projects:import-legacy` dari `project_tracker` lokal (baca saja; DB bersama tidak disentuh): Gantt menggambar 249 bar rencana + 170 bar aktual dari data nyata, filter PIC jalan, Portofolio menyala di halaman detail, kontributor hanya melihat miliknya, alur ajukan -> tinjau -> buat project (pra-isi benar) -> tautan balik, My Work kedua kartu, tanpa error console, tanpa overflow horizontal di mobile.

### Hasil Fase 4 (2026-11-23)

Awal sesi: branch `feat/integration-project-tracking` tertinggal 38 commit dari `development`; `development` di-merge dulu (konflik hanya di `new_trial_validation_app/CLAUDE.md`, dua section ditambahkan di tempat yang sama, keduanya dipertahankan).

Dibangun:
- **Mesin & produk di project:** `SaveProjectRequest` + `SaveProject` menerima `machine_option_id` (master_options `machine_used`, aktif) dan `product_id` (products aktif); hanya disentuh bila dikirim form, jadi "To Project" dari Improvement tidak menghapus tautan. Relasi `Project::machine()`/`product()`. Form project punya kartu "Tautan Mesin & Produk" (2 Combobox); detail project menampilkan keduanya.
- **Buat Draft Trial dari stage:** tombol di stage Validation membuka form Trial biasa (`trials.create?project_stage=<id>`) dengan banner dan produk/mesin terisi dari project; `TrialController::store()` memeriksa stage dulu (Validation, belum tertaut, user boleh ubah project) lalu `CreateTrial` + tautan dalam satu transaksi, jadi tidak ada draft yatim. Semua aturan validasi Trial tetap berlaku.
- **Tautkan/lepas trial yang sudah ada:** `ProjectStageTrialController` (`projects.stages.trial.store/destroy`) + `LinkProjectStageTrial` (hanya stage Validation, satu trial per stage, satu stage per trial, user harus bisa melihat trial). Dialog Combobox berisi maks 300 trial terbaru yang terlihat user dan belum tertaut.
- **Sinkron status:** `SyncProjectStagesFromTrial` dipanggil dari `SubmitTrialForReview`, `SaveDepartmentReview` (saat jadi Ready for Approval) dan `SaveApprovalDecision`, lewat `safely()` (try/catch + `report()`, dan cek `Schema::hasTable('pm_stages')`) sehingga alur Trial tidak pernah gagal karena modul Project. Pemetaan: Draft 20 / Need Revision 40 / In Review 60 / Ready for Approval 80 → On Progress; Approved → Completed 100; Rejected → stage tidak diubah (tampil sebagai badge trial). Actual start/end diisi dari tanggal trial hanya bila kosong. Progress project dihitung ulang, log `SYNC_TRIAL_STAGE`.
- **Notifikasi:** PIC project (`pic_user_id`) dapat notifikasi `type=project` setiap trial tertaut berubah status (kecuali PIC sendiri pelakunya); judul memuat status agar dedup `CreateNotification` tidak menelan perubahan berikutnya.
- **Catch-up:** `php artisan projects:sync-trial-stages [--dry-run]` untuk perubahan status yang dibuat app lama (yang menulis `trials_header` langsung). Tanpa notifikasi. Belum dijadwalkan.
- **Tautan balik:** halaman Report Summary trial menampilkan "Bagian dari project … (stage …)".
- String id/en/ko: `projects.trial_link.*`, `fields.machine/product`, `form.*`, `toast.trial_linked/unlinked`, label log `LINK_TRIAL`/`UNLINK_TRIAL`/`SYNC_TRIAL_STAGE`.

Verifikasi: `ProjectsPhase4Test` 11 test. Suite penuh 679 test: 675 lulus, 3 skip lama, 1 gagal lama (`ExportLocaleTest`/ZipArchive). Pint, tsc, `npm run build` lulus; ESLint 1 warning lama; Prettier hanya `resources/ai/trial_document.schema.json` (lama); Larastan hanya 2 error lama (`TrialMonitorController`, migration `rename_pi_review_team_to_pie`). Live Playwright (Chromium headless, 1440 + 390px) terhadap sqlite sementara di scratchpad yang diisi `projects:import-legacy` dari `project_tracker` lokal (baca saja; DB bersama tidak disentuh): set mesin+produk lewat form → tampil di detail; "Buat Draft Trial" → banner + produk/mesin terisi; simpan → masuk wizard Validasi; stage menampilkan kode trial + status Draft, progres 20%; Report Summary menampilkan tautan balik; tanpa error console; tanpa overflow di mobile.

Keputusan terbuka baru:
- **K4:** stage Validation hasil impor yang sudah *Completed* tetap menawarkan "Buat Draft Trial"/"Tautkan Trial". Begitu ditautkan ke draft baru, stage turun ke On Progress 20% (trial jadi sumber kebenaran). Terlihat saat verifikasi live (stage "Machine Validation" project PRJ-005). Pilihan: biarkan (re-trial memang membuka ulang stage), sembunyikan tombol pada stage Completed, atau jangan pernah menurunkan stage Completed. Default sementara: biarkan.
- Bot intent status project: ditunda.

### Hasil Fase 5 Tahap A+B (2026-11-23)

**Gladi bersih di MySQL lokal `trial_validation_system`** (setiap langkah tulis disetujui user):
1. Backup: `C:\Users\cosmax\db-backups\fase5-2026-11-23\trial_validation_system_before_pm_20261123_122702.sql` (11,96 MB, 31 tabel). Di folder yang sama: `counts.sh` (jumlah baris semua tabel lewat satu query `UNION ALL`), `counts_before/after_migrate/after_import/after_rehearsal.txt`, `user-map.json`, `dryrun.txt`, `import.txt`.
2. `migrate:status` hanya punya 2 Pending (pm_*). `migrate --path=...000001` lalu `--path=...000002`: 31 tabel lama identik; hanya `migrations` +2 dan 8 tabel pm_* kosong.
3. Dry-run dengan `--user-map` (Zahra → zahra.syahda@cosmax.com id 27, Rozy → fahrul.rozy@cosmax.com id 19; keduanya Staff/PI, aktif): 43 / 249 (Validation 48) / 24 / 1 milestone / 130+1 file / 859 log / 1 improvement / 3 template, 4 kategori + 16 vendor; teks tanpa padanan hanya "Validasi". Impor sungguhan: semua `created`; `pm_projects` Zahra 23 / Rozy 20 dengan `pic_user_id`; `activity_logs` +860 (859 `LEGACY_LOG` + 1 `IMPORT`), `master_options` +20; tabel lain tidak berubah. Dry-run ulang: "Already in target" penuh. `projects:sync-trial-stages`: dry-run 0 tautan, lalu sungguhan 0 perubahan. File fisik 131 tetap `is_missing` (folder asli tidak ada di mesin ini).
4. Verifikasi live (`php artisan serve` :9001 + Playwright Chromium 1440/390px, admin@local.test): Portofolio (43 project, KPI 19 berjalan / 17 selesai / 7 terlambat), detail PRJ-009/PRJ-005/PRJ-003, Gantt, Improvement + detail, Quotation & PO, Dashboard → 18/18 halaman 200, tanpa error console, tanpa overflow mobile; dialog "Tautkan Trial" berisi 300 trial nyata. Uji tulis (disetujui): stage 47 "Validation Trial" (PRJ-009) ditautkan lewat UI ke trial nyata 380 (In Review) → stage On Progress 60%, actual_start 2026-11-18, log LINK_TRIAL + SYNC_TRIAL_STAGE, Report Summary trial 380 menampilkan tautan balik, trial 380 tidak berubah, tanpa notifikasi; lalu dilepas lewat UI. Pemulihan: impor ulang (idempoten; terbukti mengembalikan stage 47 ke Not Started 0% dan project ke 39%) + hapus 3 log uji. Sisa di DB lokal: 1 log `IMPORT` tambahan.

**Masalah yang ditemukan dan diperbaiki** (tidak terlihat di uji sqlite karena fixture tidak memakai format data asli):
- Riwayat Aktivitas project hanya menampilkan "Riwayat aplikasi lama · Zahra" tanpa isi: `detail` sudah dikirim (berisi seluruh `new_data`) tapi tidak dirender. Sekarang `ProjectController::logDetail()` mengirim hanya `new_data.text` untuk `LEGACY_LOG` dan halaman menampilkannya di bawah label.
- Riwayat umpan balik Improvement menampilkan angka epoch mentah (`1794289531410`) dan teksnya kosong: format app lama `{at: epoch-ms, feedback, action}` vs format app baru `{at: 'Y-m-d H:i:s', text}`. `ImprovementController::feedbackLog()` menormalkan saat ditampilkan (epoch → `app.timezone`, `feedback` + `action` → `text`); data tersimpan tidak diubah.

**Temuan lain (dicatat, tidak diubah):**
- `.env` lokal `APP_TIMEZONE=UTC` (CLAUDE.md menyebut Asia/Jakarta). Prod wajib Asia/Jakarta; masuk runbook §14 a#4.
- Progres project hasil impor (dari app lama) tidak sama dengan rata-rata stage (PRJ-009: 39 vs rata-rata 50). Simpan/hapus stage pertama di app baru akan menghitung ulang (39 → 50). Wajar, tapi PIC perlu tahu.
- "Lepas Tautan" tidak mengembalikan status/progres stage (stage tetap 60%). Disengaja (riwayat), PIC bisa mengubah stage manual.

**Keputusan (diputuskan bersama user 2026-11-23):**
- **K1:** tidak membuat akun departemen bersama; tiap orang login dengan akunnya sendiri; nama lama tetap teks.
- **K3:** UI budget/actual ditunda (semua nilai sumber 0; kolom sudah ada).
- **K4:** stage Validation berstatus Completed tidak bisa ditautkan/dibuatkan draft trial; tombol diganti petunjuk "ubah status stage dulu". Ditegakkan juga di server (`LinkProjectStageTrial::assertLinkable`, dipakai tautan dan pembuatan draft). String `projects.trial_link.completed(_hint)` id/en/ko.
- **Sync:** `projects:sync-trial-stages` dijadwalkan tiap jam (`routes/console.php`, `withoutOverlapping`) selama app Trial lama aktif; hapus saat app lama dimatikan. Butuh cron `schedule:run` (deploy §12).

Test baru: `ProjectsPhase3Test` +1 (normalisasi feedback lama) + 1 assertion `logs.0.detail`; `ProjectsPhase4Test` +2 (K4, jadwal tiap jam). Gate: suite penuh 682 test: 678 lulus, 3 skip lama, 1 gagal lama (`ExportLocaleTest`/ZipArchive). Pint (setelah perbaikan import di test), tsc, `npm run build` lulus; ESLint 1 warning lama; Prettier hanya `resources/ai/trial_document.schema.json` (lama); Larastan hanya 2 error lama (`TrialMonitorController`, migration `rename_pi_review_team_to_pie`).

**Tahap B:** runbook cutover prod + rollback ditulis di `deploy/README.md` §14 (persiapan, 13 langkah hari H dengan hasil yang diharapkan, arsip, rollback tanpa restore penuh). Lokasi app lama di server prod, folder 131 file asli, dan kredensial `project_tracker` prod belum diketahui: diisi di §14 a#1-2 sebelum hari H.

### Hasil Fase 5 Tahap C (sementara, 2026-11-23) — dihentikan sebelum menulis ke server

Tidak ada yang ditulis ke server atau DB server. Pengecekan server hanya-baca (skrip discovery dijalankan user lewat SSH):
- App baru prod/dev ada di server `100.100.160.23` (`/var/www/trial_validation_app[-development]`), **DB di host lain `100.100.160.27`** (user `cosmaxadmin`), DB terpisah: `new_trial_validation_system` (prod) dan `new_trial_validation_system_dev` (dev). `APP_TIMEZONE=Asia/Jakarta` di keduanya (§14 a#4 OK). Cron `schedule:run` ada di crontab `www-data` untuk prod (§14 a#5 OK).
- Prod: hanya 2 migration pm_* Pending. **Dev: juga 4 migration data `2026_11_19_*` (pengalihan Ingyu Ko) masih Pending** — ikut jalan saat merge ke development; butuh akun zahra/fahrul/moonsh/jaejung di DB dev, belum dicek.
- **App Project Tracking lama tidak berjalan di server ini** (tidak ada site nginx; folder `Project-Tracking-PIE-Team` di deploy path hanya entri gitlink kosong). Lokasi app lama yang live belum diketahui; §14 b#1/b#12 (bekukan/alihkan nginx) harus disesuaikan dengan tempat app itu sebenarnya berjalan (ada `start.bat`, kemungkinan Windows).
- User memberi dump prod `project_tracker.sql` (HeidiSQL, 23 Nov 15:13) dan zip app prod (`Project-Tracking-PIE-Team-production.zip`, berisi `.env` dengan kredensial — jangan disebar). Salinan prod app Trial ada di MySQL lokal sebagai `trial_validation_system_production` (migration s/d `2026_11_19_000004` batch 16, 389 trial, user id Zahra 27 / Rozy 19 / Ingyu 74 / Moon 100 / Jaejung 105 — sama dengan gladi).

**Sumber prod jauh lebih besar dari salinan lokal 17 Nov:** 45 project / 274 stage (Validation 53) / 31 quotation / 1 milestone / 1155 log / 4 improvement / 190 file (155 project + 6 `IMP-` + 29 `TLG-`), plus **15 Check Tooling** di `settings.tracker_state.toolings` (14 masih berjalan: 9 "Menunggu RNI & Sampel", 5 "Review Production", 1 Approved). 190 file di zip cocok 100% dengan dump (nama + ukuran).

**Dry-run lokal** (disetujui user): dump dimuat ke DB lokal baru `project_tracker_prod` (nama DB di dump diganti di salinan scratchpad; `project_tracker` lokal tidak disentuh), lalu `DB_DATABASE=trial_validation_system_production PT_DB_DATABASE=project_tracker_prod PT_FILES_PATH=<folder hasil ekstrak> php artisan projects:import-legacy --dry-run --user-map=...` (override dicek dulu lewat tinker). Hasil: semua angka = sumber; files 186 + 4 improvement; konteks stage 98 / tooling 29 / project 31 / quotation 28; `Physical files vs source dir: {"ok":190}`; 4 kategori + 20 vendor; nama tanpa padanan: `PROD MU`, `PROD MU / Santo valentino`, `PROD MU/Santo Valentino`, `Validasi`. Dry-run tidak menulis apa pun.

**Celah importer:** (1) data Check Tooling tidak diimpor sama sekali (file `TLG-` sudah disimpan sebagai `context=tooling`); (2) `improvements[].afterAttachments` tidak dipetakan, 2 file `IMP-AFTER-*` masuk `pm_files` tanpa project. **Keputusan user: bangun Check Tooling dulu (Fase 4b), cutover ditunda.**

### Hasil Fase 4b (2026-11-23) — Check Tooling

Sumber fitur: kode app lama PROD (zip 23 Nov, diekstrak ke scratchpad tanpa `.env`, tidak dijalankan): `db.php` `VALID_TOOLING_*`/`normalizeToolingPayload()`, `api.php` bagian toolings (~1022-1290), `public/app.js` `effToolingStatus()`/`toolingBottleneck()`/`saveToolingReview()`/`confirmReceiveSample()`/`createProjectFromTooling()`.

**Keputusan (bersama user 2026-11-23):**
- **K5 peran Kontributor:** ditentukan dari departemen akun (`department` atau `review_unit`): PRNI → buat request + TTD PRNI, RNI → isi formula + TTD RNI, PROD/PRD → TTD Production, PIE/PI → terima sampel, cek tooling, TTD PIE, Revisi/Hold/Tolak, jadikan project. Admin memegang semua peran; role Viewer tidak punya peran (hanya baca). Tidak ada akun departemen bersama (K1).
- **Tanda tangan:** e-signature password akun sendiri, sama seperti Approval Trial (`messages.validation.signature_wrong`). Tanda tangan gambar dari app lama diimpor (`signature_method=legacy`, gambar `data:image/png|jpeg` saja, maks 64 KB) dan hanya ditampilkan.

**Dibangun:**
- Migration `2026_11_23_000001_create_pm_tooling_tables.php`: `pm_toolings`, `pm_tooling_items`, `pm_tooling_approvals` (unik per tooling+role); kolom baru pada tabel pm_* yang belum ada di prod: `pm_files.tooling_id`, `pm_improvements.after_condition/after_result/after_date/after_pic/after_links`, `pm_improvement_files.phase` (before/after). Semua dijaga `hasTable`/`hasColumn`, `down()` kosong.
- Model `Tooling`, `ToolingItem`, `ToolingApproval`; vocab `App\Support\ToolingOptions` (nilai mentah sama dengan app lama); aturan `App\Services\Projects\ToolingWorkflow` (peran, status turunan dari tanda tangan + status sampel kecuali Revisi/Hold dan Ditolak yang manual, posisi/bottleneck, aksi berikut per user, SLA PIE 5 hari kerja, nomor NPD berikut, tube/cushion otomatis kritis); `ToolingPolicy`.
- Alur: setiap langkah POST terpisah `projects.toolings.steps` (`sign-prni`, `send-sample`, `sign-rni` + formula, `receive-sample`, `pie-check` + tools/mesin/invest/risiko, `sign-production`, `hold`, `reject`, `resume`) lewat `ToolingStepRequest` + `ToolingSteps`; tiap langkah menulis `history_log` (timeline di halaman) dan `activity_logs` `module=TOOLING`. PIE hanya bisa cek setelah sampel diterima dan PRNI+RNI tanda tangan; Production terakhir. Need Invest = YES → "Buat Project Investasi" (kategori Tooling NPD, sekali).
- UI: `toolings/index` (KPI yang bisa diklik, filter Tampilkan/Status/cari, kolom tanda tangan PRNI/RNI/PIE/PRO, lewat SLA), `toolings/show` (banner "Giliran Anda" dengan tombol aksi, 4 kotak tanda tangan, A request / sampel / B formula / C hasil cek, lampiran, riwayat lama + baru), `toolings/form` (buat/ubah request A). Sidebar Project → Check Tooling. My Work: kartu Check Tooling (langkah yang menunggu peran user). `ConfirmDialog` mendapat prop opsional `contentClassName`.
- Improvement: status `Selesai` dan `Ditolak` (prod lama), bagian "Hasil (After)" (diisi peninjau PI di form tinjauan), file "bukti hasil" (`phase=after`, upload oleh peninjau).
- Importer: `toolings` dari `settings.tracker_state` (status turunan kecuali Approved/Revisi/Ditolak; `Submitted`/`Draft` lama dipetakan ke status turunan, dicatat sebagai warning), item tool (diganti utuh tiap run), approval per role, tautan file `TLG-*` via `attachments[].file`, `afterAttachments` + `after*` Improvement; baris `pm_files` IMP-AFTER dari impor lama dipindahkan ke `pm_improvement_files`. Dry-run mencetak jumlah tooling, baris tool/tanda tangan, file tooling tertaut, file improvement before/after, sebaran status.
- String id/en/ko: grup baru `toolings`, plus `improvements` (status, after) dan `common.nav.project_toolings`.

**Verifikasi:**
- Pest: `ProjectsToolingTest` 9 + `ProjectsImportLegacyTest` +2. Suite penuh 693 test: 689 lulus, 3 skip lama, 1 gagal lama (`ExportLocaleTest`/ZipArchive). Pint, tsc, `npm run build` lulus; ESLint hanya 1 warning lama; Larastan hanya 2 error lama.
- Gladi data prod (disetujui user): `trial_validation_system_production` disalin (mysqldump) ke DB lokal **baru** `trial_validation_system_fase4b` (31 tabel, 389 trial, identik); `migrate:status` hanya 3 pm_* Pending; 3 migration dengan `--path` (31 → 42 tabel). Impor dari `project_tracker_prod` (file ke folder scratchpad lewat `PROJECT_FILES_PATH`): 45 / 274 / 31 / 1 / 184+6 file (after 2) / 1155 log / 4 improvement / **15 tooling, 72 baris tool, 37 kotak approval (28 bertanda tangan)**, 29 file TLG tertaut, `files_copy {"copied":190}`, status 9/5/1 sama dengan sumber (tanpa warning). Impor kedua: semua `updated`, `present:190`, jumlah tetap. Tabel non-pm: hanya `activity_logs` +1157 (1155 + 2 IMPORT dari dua run) dan `master_options` +24; `users`/`trials_header`/`trials_review`/`notifications`/`products` sama. `trial_validation_system_production` sendiri tidak ditulis.
- Playwright (Chromium, 1440 + 390 px, server `:9004` ke DB salinan): daftar 14 aktif / 15 semua, detail 1138 (tanda tangan gambar lama tampil) dan 1126 (tautan project PRJ), Improvement, My Work — semua 200, tanpa error console, tanpa overflow mobile; akun PRD melihat 5 tugas "Review & tanda tangan Production". Uji tulis lewat UI di salinan: buat request → password salah ditolak → TTD PRNI → kirim sampel → RNI → PIE terima + cek → Production = **Approved** (tooling #16 "PLAYWRIGHT Fase4b Test Cushion", NPD 1154, kritis otomatis, 7 log). Perbaikan dari cek visual: kolom produk di daftar tidak membungkus (status/tanda tangan terpotong), baris tool kosong dari data lama ikut tampil, label filter cakupan.

**Temuan / keputusan terbuka:**
- **K6 (perlu keputusan sebelum cutover):** akun `prni@local.test`, `rni@local.test`, `prd@local.test`, `pi@local.test` bernama "Viewer …" tapi role-nya PRNI/RNI/PRD/PI (sama dengan staf asli), jadi menurut K5 mereka **bisa menandatangani**. Pilihan: ubah role akun itu menjadi `Viewer` di prod (perubahan data `users`, atas perintah user), nonaktifkan, atau biarkan. Default sementara: biarkan, dicatat. Password `prni@local.test` di salinan prod berbeda dari README.
- 9 nama penanda tangan lama tidak cocok otomatis ke akun (Mella, Elvara, Sri, Gita PRNI, Adhit Widhi, Naya - RNI, Nadia Shafa, Anggi, Yuni); tetap teks (keputusan user: biarkan; bisa ditambah ke `user-map.json` sebelum cutover).
- Belum ada notifikasi per langkah Check Tooling (app lama juga tidak punya); tugas terlihat lewat My Work + filter "Perlu aksi saya".
- Lokal `APP_TIMEZONE=UTC`, jadi waktu tanda tangan di gladi tampil UTC; prod Asia/Jakarta.
- App lama masih dipakai; saat cutover ambil dump + folder file terbaru (angka akan lebih besar dari 23 Nov).
- DB lokal `trial_validation_system_fase4b` dan folder file scratchpad hanya untuk gladi; boleh dihapus (tidak ada yang bergantung).

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

### Prompt Fase 3 (tempel di chat baru)

```
Lanjutkan penggabungan Project Tracking PIE ke new_trial_validation_app/ (branch feat/integration-project-tracking). Baca dulu: PROJECT_MODULE_PLAN.md (bagian 4, 5 Fase 3, bagian 7 "Hasil Fase 1" dan "Hasil Fase 2"), CLAUDE.md di root dan new_trial_validation_app/CLAUDE.md (bagian "Projects module" Fase 1 + Fase 2; insiden DB 2026-08-24; aturan prod additive-only). Fase 1 + 2 sudah selesai dan di-push: tabel pm_*, model, ProjectPolicy/ImprovementPolicy, importer, serta UI inti (portofolio, form, detail, stage, milestone, quotation, file) di routes/projects.php + app/Actions/Projects/.

Kerjakan HANYA Fase 3:
1. Gantt: halaman `projects.gantt` (sidebar grup Project) dari pm_stages (plan vs actual, paralel, milestone), filter project/PIC, i18n ID/EN/KO. Tanpa library berat kalau bisa (SVG/CSS grid).
2. Project/Improvement milik user masuk ke My Work yang sudah ada (DashboardController::myWork + MyWorkSection), tanpa halaman "Pekerjaan Saya" terpisah.
3. Board Improvement: ajukan (semua user login non-Viewer), tinjauan PI (ImprovementPolicy; K2 default department PI/PIE), status Baru/Review PI/Menunggu Data/Disetujui/Tidak Perlu Improvement, keputusan Pending/To Project/Suggestion Only; "To Project" membuat project dengan data terisi dan menautkan project_id. Upload file improvement memakai pola file Fase 2 (pm_improvement_files).
4. Tambahkan item sidebar Gantt + Improvement; perbaiki highlight sidebar Portofolio di halaman detail/form; tampilkan riwayat LEGACY_LOG di detail project (map record_id `legacy:<id>` lewat pm_projects.legacy_id) bila sederhana.
Gunakan pola Fase 2 (controller + Action + FormRequest, ConfirmDialog/Dialog/FilterBar, lang group `projects`, log activity_logs module PROJECT/IMPROVEMENT).

Batasan: jangan migrate:fresh/refresh/db:wipe, jangan migrate manual ke DB bersama, jangan pakai factory di tinker, jangan menyentuh/commit Project-Tracking-PIE-Team/, tidak ada impor sungguhan sebelum user menyetujui. Verifikasi live: buat sqlite sementara di scratchpad (export DB_CONNECTION=sqlite DB_DATABASE=<path> SESSION_DRIVER=file CACHE_STORE=file PROJECT_FILES_PATH=<dir>, cek config('database.default') dulu), migrate --database=sqlite, `projects:import-legacy`, lalu Playwright (playwright-core + chromium cache ms-playwright/chromium-1248). Jangan taskkill php.exe massal (matikan hanya PID di port server). K1-K3 pakai default bagian 3.
Definisi selesai: paritas fitur dengan Project Tracking lama (Gantt, My Work, Improvement); gate CI hijau (kegagalan lama: Larastan migration rename_pi_review_team_to_pie, ExportLocaleTest/ZipArchive, Prettier resources/ai/trial_document.schema.json); live Playwright pada data impor nyata. Di akhir ikuti ritual penutup bagian 6 dan tulis prompt Fase 4.
```

### Prompt Fase 4 (tempel di chat baru)

```
Lanjutkan penggabungan Project Tracking PIE ke new_trial_validation_app/ (branch feat/integration-project-tracking). Baca dulu: PROJECT_MODULE_PLAN.md (bagian 4, 5 Fase 4, bagian 7 "Hasil Fase 1-3"), CLAUDE.md di root dan new_trial_validation_app/CLAUDE.md (bagian "Projects module" Fase 1-3; insiden DB 2026-08-24; aturan prod additive-only). Fase 1-3 sudah selesai dan di-push: tabel pm_*, importer, UI inti, Gantt, My Work, board Improvement.

Kerjakan HANYA Fase 4 (Tautan ke Trial):
1. Stage tipe Validation punya trial_id (kolom sudah ada di pm_stages); tombol "Buat Draft Trial" dari stage memakai Action CreateTrial yang sudah ada (data awal dari project: produk/mesin bila tersedia) lalu menyimpan trial_id.
2. Status stage mengikuti status Trial (Approved -> stage Completed, 100%; Rejected/Need Revision ditampilkan di stage), lewat Action yang dipanggil dari SaveApprovalDecision, bukan polling.
3. Tautan mesin dan produk (combobox, master_options machine + products), notifikasi ke PIC project saat trial tertaut berubah status (pakai CreateNotification).
4. Rencana bot (opsional, boleh ditunda): intent status project.
Pakai pola Fase 2-3 (controller + Action + FormRequest, lang group projects id/en/ko, log activity_logs).

Batasan: jangan migrate:fresh/refresh/db:wipe, jangan migrate manual ke DB bersama, jangan pakai factory di tinker, jangan menyentuh/commit Project-Tracking-PIE-Team/, tidak ada impor sungguhan sebelum user menyetujui. Verifikasi live: sqlite sementara di scratchpad (export DB_CONNECTION=sqlite DB_DATABASE=<path> SESSION_DRIVER=file CACHE_STORE=file PROJECT_FILES_PATH=<dir>, cek config('database.default') dulu; tinker --execute tanpa env itu menembak DB bersama), migrate --database=sqlite, projects:import-legacy, Playwright (playwright-core di scratchpad + chromium cache ms-playwright/chromium-1248, chrome-win64/chrome.exe). Jangan taskkill php.exe massal (matikan hanya PID di port server).
Definisi selesai: dari stage Validation PIC bisa membuat draft Trial dan statusnya tersinkron; gate CI hijau (kegagalan lama: Larastan migration rename_pi_review_team_to_pie, ExportLocaleTest/ZipArchive, Prettier resources/ai/trial_document.schema.json); live Playwright. Di akhir ikuti ritual penutup bagian 6 dan tulis prompt Fase 5 (cutover).
```

### Prompt Fase 5 (tempel di chat baru)

```
Lanjutkan penggabungan Project Tracking PIE ke new_trial_validation_app/ (branch feat/integration-project-tracking). Baca dulu: PROJECT_MODULE_PLAN.md (bagian 1, 2, 5 Fase 5, bagian 7 "Hasil Fase 1-4" termasuk K1-K4), CLAUDE.md di root dan new_trial_validation_app/CLAUDE.md (bagian "Projects module" Fase 1-4; insiden DB 2026-08-24; aturan prod additive-only), deploy/README.md. Fase 1-4 selesai dan di-push.

Kondisi per 2026-11-23 (sudah dicek, baca saja): MySQL lokal (Laragon, root@127.0.0.1) berisi ipc_system, project_tracker, trial_validation_system, vendorhub_it. Di trial_validation_system lokal tabel pm_* BELUM ada (migration 2026_11_17_000001 dan _000002 masih Pending) dan belum ada impor apa pun; Fase 1-4 hanya diuji di sqlite sementara. project_tracker lokal terbaca lewat koneksi `project_tracker` (43 project; PT_DB_* tidak diset di .env, memakai default server yang sama). Kandidat padanan PIC: "Zahra" -> user "zahra.syahda", "Rozy" -> user "Fahrul Rozy" (konfirmasi ke saya dulu).

Kerjakan HANYA Fase 5, dalam dua tahap. MINTA PERSETUJUAN SAYA sebelum SETIAP langkah yang menulis ke database (lokal maupun prod):

Tahap A — gladi bersih di MySQL lokal:
1. Backup trial_validation_system lokal (mysqldump ke folder di luar repo), tunjukkan ukuran file.
2. Jalankan HANYA dua migration pm_* (`php artisan migrate --path=database/migrations/2026_11_17_000001_create_pm_project_tables.php` lalu _000002), setelah memastikan `migrate:status` tidak punya migration Pending lain yang ikut terbawa. Bandingkan jumlah baris tabel inti sebelum/sesudah (harus sama).
3. `projects:import-legacy --dry-run` dengan --user-map (Zahra/Rozy), cocokkan angka bagian 2, lalu impor sungguhan, lalu `projects:sync-trial-stages`.
4. Verifikasi live di app lokal (Playwright): portofolio, detail, Gantt, Improvement, tautan stage-trial dengan trial nyata. Catat masalah.

Tahap B — rencana cutover prod (dokumen + checklist, eksekusi hanya atas perintah saya): urutan deploy branch ini (pipeline menjalankan `migrate --force`), backup DB prod, freeze app lama (read-only), impor final (dry-run dulu, --files-path ke folder 131 file asli), verifikasi baris, sync stage, portal/config.js + deploy/README.md, arsip project_tracker.

Putuskan bersama saya: K1 (akun departemen bersama), K3 (UI budget/actual), K4 (stage Completed yang ditautkan ulang), dan apakah `projects:sync-trial-stages` dijadwalkan selama app lama masih aktif.

Batasan: jangan migrate:fresh/refresh/db:wipe, jangan `migrate` tanpa --path, jangan pakai factory di tinker, jangan pakai --env= tanpa memastikan file .env.<nama> ada, jangan menyentuh/commit Project-Tracking-PIE-Team/.
Definisi selesai: gladi bersih lokal sukses dan terverifikasi; checklist cutover prod disetujui saya. Di akhir ikuti ritual penutup bagian 6.
```

### Prompt Fase 5 Tahap C — eksekusi cutover prod (tempel di chat baru, hanya saat user siap)

```
Lanjutkan Fase 5 penggabungan Project Tracking PIE: Tahap C, eksekusi cutover di server (development dulu, lalu production). Baca dulu: deploy/README.md §14 (runbook, wajib diikuti urutannya), PROJECT_MODULE_PLAN.md bagian 7 "Hasil Fase 5 Tahap A+B", CLAUDE.md (insiden DB 2026-08-24, aturan prod additive-only). Tahap A (gladi bersih lokal) dan B (runbook) selesai dan di-push di branch feat/integration-project-tracking.

Saya tidak punya akses server; Claude menyiapkan perintah, saya yang menjalankannya lewat SSH dan menempelkan hasilnya. Fase 4b (Check Tooling) sudah selesai: ada 3 migration pm_* (`2026_11_17_000001/2`, `2026_11_23_000001`), angka acuan dry-run ada di §14 b#6 (gladi data prod 23 Nov), dan keputusan K6 di PROJECT_MODULE_PLAN.md bagian 7 "Hasil Fase 4b" (akun "Viewer PRNI/RNI/PRD/PI" mendapat hak tanda tangan) harus diputuskan sebelum cutover. Sebelum mulai, minta saya mengisi §14 a#1-2: lokasi app Project Tracking lama yang live (catatan Tahap C: tidak berjalan di server app; kemungkinan Windows), host/kredensial DB project_tracker, dan folder file asli (190 file per 23 Nov; ambil dump + file terbaru karena app lama masih dipakai). Cek juga APP_TIMEZONE prod dan cron schedule:run.

Urutan: development lengkap (merge branch ke development, backup DB dev, §14 b#1-13, verifikasi) → laporan → baru production atas perintah saya (backup DB prod SEBELUM push ke production, karena pipeline menjalankan migrate --force). MINTA PERSETUJUAN SAYA sebelum SETIAP langkah yang menulis ke DB atau mengubah nginx. Bandingkan jumlah baris sebelum/sesudah di setiap langkah tulis; berhenti bila angka dry-run tidak cocok dengan sumber atau ada file missing/size-differs.

Batasan: jangan migrate:fresh/refresh/db:wipe, jangan restore backup penuh sebagai rollback (pakai §14 d), jangan hapus project_tracker atau folder file lama, jangan menyentuh/commit Project-Tracking-PIE-Team/.
Definisi selesai: data prod terimpor dan terverifikasi (§14 b#8-11), app lama dialihkan, project_tracker diarsip; centang Fase 5 di PROJECT_MODULE_PLAN.md, update CLAUDE.md, commit + push.
```

### Prompt Fase 4b — Check Tooling (tempel di chat baru)

```
Lanjutkan penggabungan Project Tracking PIE ke new_trial_validation_app/ (branch feat/integration-project-tracking): Fase 4b Check Tooling. Baca dulu PROJECT_MODULE_PLAN.md bagian 5 "Fase 4b" dan bagian 7 "Hasil Fase 5 Tahap C (sementara)", CLAUDE.md (insiden DB 2026-08-24, aturan prod additive-only), new_trial_validation_app/CLAUDE.md bagian Projects module.

Sumber kebenaran fitur: kode app lama PROD (bukan folder Project-Tracking-PIE-Team/ lokal, itu versi 17 Nov tanpa Check Tooling). Saya taruh zip-nya di C:\Users\cosmax\Downloads\Project-Tracking-PIE-Team-production.zip: ekstrak ke scratchpad (hanya baca, jangan jalankan, jangan buka .env); lihat db.php (VALID_TOOLING_*, normalizeToolingPayload) dan api.php (bagian toolings, transisi status ~1130-1160, peran viewer/Kontributor), public/app.js untuk tampilan. Data sumber prod: DB lokal project_tracker_prod (dump 23 Nov); salinan prod app Trial: trial_validation_system_production. File asli: folder hasil ekstrak storage/files.

Kerjakan: tabel pm_toolings (+ turunan) additive dengan guard Schema::hasTable; perluas projects:import-legacy (toolings idempoten via legacy_id, tautkan file TLG-*, petakan improvements[].afterAttachments); Policy + alur status + approval per peran PRNI/RNI/PI/Production; UI daftar + detail ("detail dulu baru aksi"), My Work, id/en/ko, Pest (sqlite). Putuskan bersama saya: pemetaan peran Kontributor app lama ke user/departemen app baru, dan tanda tangan approval (pakai e-signature password seperti Approval Trial atau cukup klik).

Verifikasi: dry-run + impor ke DB lokal SALINAN (bukan trial_validation_system_production langsung tanpa backup; minta persetujuan sebelum menulis), lalu Playwright. Batasan: jangan migrate:fresh/refresh/db:wipe, jangan migrate tanpa --path ke DB MySQL, jangan factory di tinker, jangan --env= tanpa file .env.<nama>, jangan menyentuh/commit Project-Tracking-PIE-Team/ atau isi zip. Di akhir ikuti ritual penutup bagian 6; setelah itu baru lanjut "Prompt Fase 5 Tahap C" (catatan server di bagian 7 masih berlaku).
```

### Template prompt fase berikutnya

```
Lanjutkan penggabungan Project Tracking PIE ke new_trial_validation_app/. Baca PROJECT_MODULE_PLAN.md (bagian 5 Fase <N>, bagian 7 Log hasil fase sebelumnya) dan CLAUDE.md. Fase <N-1> sudah selesai dan di-push. Kerjakan HANYA Fase <N>: <ringkasan tugas dari checklist>. Batasan sama seperti bagian 1. Definisi selesai: <dari bagian 5>. Di akhir ikuti ritual penutup bagian 6 dan tulis prompt Fase <N+1>.
```
