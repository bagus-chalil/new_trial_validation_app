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

### Template prompt fase berikutnya

```
Lanjutkan penggabungan Project Tracking PIE ke new_trial_validation_app/. Baca PROJECT_MODULE_PLAN.md (bagian 5 Fase <N>, bagian 7 Log hasil fase sebelumnya) dan CLAUDE.md. Fase <N-1> sudah selesai dan di-push. Kerjakan HANYA Fase <N>: <ringkasan tugas dari checklist>. Batasan sama seperti bagian 1. Definisi selesai: <dari bagian 5>. Di akhir ikuti ritual penutup bagian 6 dan tulis prompt Fase <N+1>.
```
