# TRIAL_VALIDATION_V2_PLAN.md — Trial Validation v2.0: satu jejak dari awal sampai akhir

Dokumen kerja untuk pengembangan **Trial Validation v2.0** di `new_trial_validation_app/`. Dibuat 2026-11-25 dari kajian `new_trial_validation_app/docs/DB Validation.xlsx` (tab **TRIAL VALIDATION APPS**) dan audit kode Trial, Projects, Cek Tooling, Line Configuration, dan `ipc_app/`. Dokumen ini sumber kebenaran urutan kerja v2.0; **update di akhir setiap tahap**. Polanya mengikuti `PROJECT_MODULE_PLAN.md` (ritual penutup di bagian 6 dokumen itu berlaku juga di sini).

Bahasa percakapan dengan user: Bahasa Indonesia. Kode, identifier, komentar, commit message: English.

## 1. Tujuan dan batasan

**Tujuan:** setiap produk yang divalidasi punya satu jejak yang tidak terputus dan bisa diaudit: Assessment, Cek Tooling, Trial (termasuk Re-trial), Line Configuration, Mixing, IM, QCO, sampai QC-IPC. Atasan bisa bertanya "produk ini sudah trial berapa kali, hasilnya apa, berapa lama" dan jawabannya keluar dari satu halaman, bukan dari beberapa menu.

**Batasan (sama dengan aturan prod yang sudah ada):**
- Prod sudah dipakai dan app legacy masih hidup. **Hanya tabel baru (additive)**, prefix `vd_`, dengan guard `Schema::hasTable`. **Jangan** `ALTER` tabel bersama (`trials_header`, `products`, dst.) dan jangan menyentuh skema `pm_*` kecuali kolom nullable additive yang memang disetujui di tahap terkait.
- Tidak ada `migrate:fresh/refresh/db:wipe`, tidak ada `migrate` manual ke DB bersama, tidak ada `--env=` tanpa memastikan file `.env.<nama>` ada (insiden 2026-08-24). Test jalan di sqlite. Jangan pakai factory di tinker.
- `ipc_app/` tetap proyek terpisah (DB `ipc_system`, tanpa SSO/auth bersama). Sambungan ke sana lewat **kunci bisnis** (nomor IM, `fg_code`, Case No), bukan foreign key lintas DB.
- Nilai yang dibagi dengan app legacy (status, keputusan, kode) tidak diterjemahkan, hanya ditampilkan lewat label map (konvensi i18n id/en/ko yang sudah ada).

## 2. Konsep inti: Case No sebagai nomor induk

Satu **Case** dibuat saat *Validation Assessment (ADD NEW DATA)* disimpan. Case adalah tulang punggung: semua dokumen lain menunjuk ke Case lewat **ID** (bukan teks yang diketik), nomornya hanya ditampilkan.

- **Format:** `VAL-{NPD|IMP|MIX}-{YYMM}-{urut 3 digit}`, contoh `VAL-NPD-2611-001`. Prefix membedakan NPD, Improvement, dan Mixing. *(Format dan reset bulanan/tahunan: keputusan terbuka D1/D2.)*
- **Generator aman dari tabrakan:** tabel counter per (jenis, periode) dengan baris dikunci, bukan `MAX()+1` (pelajaran race condition `CreateTrial`). Tetap ada unique index pada nomor.
- **Re-trial tetap di Case yang sama.** Trial #1, #2, #3 punya nomor trial sendiri (`TRIAL-...`), tetapi satu Case No. (D3)
- **Portfolio (Project)** mendapat kode otomatis `PRJ-{YYYY}-{urut}`. **Improvement** (Improvement board) mendapat nomor `IMP-{YYYY}-{urut}`. Saat Improvement di-"To Project", Project mewarisi tautan ke Case/Improvement-nya.
- **Link table, bukan kolom baru.** `vd_case_links` (case_id, kind, ref_id nullable, ref_key nullable, attempt_no nullable, linked_by, timestamps) dengan unique (kind, ref). `kind`: `trial`, `tooling`, `project`, `line_config`, `mixing`, `ipc_batch`, `im`, `qco`. Dengan ini `trials_header` tidak perlu di-ALTER, dan nomor IM/QCO/batch IPC (yang berupa teks dari sistem lain) tetap punya tempat.
- Kolom lama (`pm_toolings.npd_no`, `pm_projects.code`) **tidak dibuang**; berperan sebagai referensi lama. Backfill ke Case dilakukan bertahap lewat command idempoten dengan `--dry-run`, dan hasil yang ambigu ditampilkan ke user, bukan ditebak.

## 3. Definisi "satu kali trial" dan Product Journey

**Definisi (usulan, perlu persetujuan user, D4):**
- Dihitung: trial yang **sudah disubmit untuk review** (bukan Draft, tidak soft-deleted). Rejected dan Re-trial tetap dihitung karena memang pernah dilakukan.
- Selalu ditampilkan dua angka: **total percobaan** dan **hasil per percobaan** ("3 kali: 1 Fail, 1 Re-trial, 1 Pass").
- **Trial Result (turunan, dari Excel):** Pass; Pass with Approval Client (ada item NOT OK tetapi ada approval/statement letter client); Re-trial (ada NOT OK dan client tidak approve); Fail. Bukti approval client butuh tempat penyimpanan: pakai lampiran trial yang sudah ada dengan kategori baru atau tabel `vd_*` kecil (diputuskan di Tahap 1).
- Produk dikenali lewat `product_id` (master `products`). Trial lama yang namanya tidak cocok dibersihkan satu kali lewat laporan padanan yang **ditinjau user** sebelum dipakai.

**Product Journey (output utama untuk stakeholder):** satu halaman per produk dan per Case.
- Ringkasan di atas dalam bahasa biasa: jumlah trial, hasil terakhir, lead time rata-rata.
- Timeline: Assessment (risk) → Cek Tooling → Trial #1..#n (tanggal, hasil, alasan Re-trial, siapa review/approve) → Line Configuration → Mixing → IM/QCO → batch QC-IPC. Tiap titik bisa diklik ke dokumen aslinya.
- Ekspor PDF lewat Browsershot yang sudah ada, tiga bahasa (id/en/ko; font CJK sudah jadi catatan deploy).
- Dashboard tingkat atas hanya angka ringkas; detail ada di Product Journey. Bot kelak bisa menjawab dari data terstruktur yang sama.

## 4. Temuan audit keselarasan (2026-11-25)

Dicek langsung di kode. Ini yang menjadi alasan Tahap 0.

| Sambungan | Kondisi hari ini | Celah |
|---|---|---|
| Trial → Product | `trials_header.product_id` + snapshot `product_name`/`finish_good_code` | Trial lama bisa tanpa `product_id`; perlu laporan padanan |
| Trial ↔ Project | Hanya tingkat **stage**: `pm_project_stages.trial_id` (Fase 4). Project punya `machine_option_id` dan `product_id` | Trial tidak tahu Project-nya kecuali lewat stage; stage berstatus Completed tidak bisa ditautkan (K4) |
| Cek Tooling ↔ Trial | **Tidak ada.** `pm_toolings` punya `npd_no` (teks bebas), `project_id`, `product_name` teks, `recommended_machine` teks | Tooling tidak punya `product_id` maupun `trial_id`; Improvement tidak punya nomor sama sekali |
| Line Configuration ↔ Trial | `trial_line_configuration_reports.trial_id` unik | Satu report per trial (aman); sudah selaras |
| Improvement ↔ Project | `pm_improvements.project_id` (hasil "To Project") | Improvement tanpa kode; tidak terhubung ke Tooling/Trial |
| IPC ↔ Trial | `ipc_system` terpisah. `master_products.fg_code` (unik) setara dengan `products.finish_good_code`. Batch IPC: `ipc_batches.no_batch` + `master_product_id`; `startup_checks.im_document_number` (wajib, UPPER CASE, nullable hanya untuk data lama) | Tidak ada Case No di IPC; IM hanya teks; belum ada sumber tunggal IM |

Kesimpulan: Trial ↔ Project sudah selaras di level stage, tetapi **Tooling dan Improvement belum tersambung ke Trial**, dan jejak ke IPC baru bisa lewat `fg_code` + nomor IM.

## 5. Peta menu dari Excel ke v2.0

- **Dashboard** — breakdown risk NPD (H/M/L), total NPD dan Improvement per status (In Review, Awaiting Approval, Done, Revision Required, Reject), tipe produk NPD, *Realized Trial Lead Time* (tanggal trial dikurangi tanggal PM/Bulk ready paling akhir; hanya NPD), *Sales Forecast–Trial Lead Time* (tanggal target sales forecast QCO dikurangi tanggal trial), Line Configuration (qty, bottleneck PIE/PROD).
- **My Work** — Draft, Trial Progress Report, Revision, On Progress Review, On Progress Approval (sebagian sudah ada).
- **Validation Assessment** — SKU (dropdown master), receive date, client, sales month dan qty PO (dari QCO), packaging type, scope, mesin/line rekomendasi (kelak dari Cek Tooling), nomor+link QCO, tanggal PM dan Bulk diterima (boleh N/A), Risk. Semua kolom bisa difilter.
- **Tooling Check** — Request, Antrean, Report (modul Check Tooling Fase 4b yang sudah ada, ditautkan ke Case).
- **NPD Trial / Improvement Trial** — hasil + status report + filter department/PIC; tambah **PM READY DATE** dan **BULK READY DATE** di form trial (default H-1 dari tanggal pembuatan untuk trial lama, supaya lead time bisa dihitung).
- **Validation Plan** — permintaan jadwal trial mingguan ke PPIC (SKU + Week).
- **Line Configuration** — Prepared (Staff PIE), Acknowledge (PROD: PL/TL), Approved (Leader PIE); sudah ada.
- **Mixing** — jenis 1st Batch / Pilot / Rework, **tanpa langkah Weighing**; hasil Pass/Re-mix/Fail, kesesuaian batch record, permintaan revisi, bukti penutupan RNI, verifikasi PIE bersama PPIC.
- **Trials (menu sekarang)** — My Work, On Review, On Approval, Done, Search; catatan *Pending approval delete* (aturan belum jelas, D5).

## 6. Tahap kerja

Urutan ditetapkan user 2026-11-25: **Tahap 0 harus selesai dulu.** IM dan QCO generator masuk **Tahap 3**.

**Tahap 0 — Penyelarasan Trial ↔ Project Tracker (sebelum fitur baru).** Tanpa kode fitur v2.0:
- Audit data nyata (read-only): trial tanpa `product_id`, tooling tanpa produk/trial, stage Validation tanpa trial, trial tanpa project. Hasilkan laporan padanan untuk ditinjau user.
- Putuskan cara Tooling dan Improvement tersambung ke Trial (rekomendasi: lewat Case di Tahap 1; Tahap 0 hanya memastikan data dasarnya bersih dan K4 diputuskan).
- Keluaran: laporan temuan + keputusan D1-D5. Belum ada migrasi.

**Tahap 1 — Fondasi Case + field baru + Product Journey minimal.**
- `vd_cases`, `vd_case_counters`, `vd_case_links`, generator nomor, model/policy.
- Field trial: kategori NPD/Improvement, Risk, PM ready date, Bulk ready date; tabel samping `vd_*` bila tidak boleh ALTER; Trial Result turunan + bukti approval client.
- Backfill idempoten (`--dry-run`), default H-1 untuk trial lama.
- Product Journey minimal: jumlah trial per produk dan timeline dari data yang sudah ada. KPI lead time di dashboard.

**Tahap 2 — Validation Assessment + Validation Plan + Tooling terhubung.**
- Register Assessment (ADD NEW DATA, filter lengkap), Validation Plan mingguan ke PPIC.
- Cek Tooling dan Improvement ditautkan ke Case (Improvement memakai nomor sendiri); `npd_no` lama jadi referensi.
- Pembuatan Trial dari Case (pra-isi), seperti pola Fase 4.

**Tahap 3 — IM dan QCO generator + sambungan ke IPC.**
- Aplikasi kecil penerbit **nomor IM** dan **nomor QCO** (format dan alur approval: keputusan D6/D7), tercatat di Case lewat `vd_case_links`.
- IPC: pertama **lookup read-only** (koneksi baca ke `ipc_system`, padanan lewat `fg_code` + nomor IM) sehingga Product Journey bisa menampilkan batch IPC. Lalu opsional: Startup Check di IPC memilih IM dari daftar yang diterbitkan, dan menyimpan Case No. Perubahan di `ipc_app/` perlu rencana sendiri (migrasi IPC jalan lewat pipeline).
- Sebelum Tahap 3: field QCO/IM di Case tetap isian manual.

**Tahap 4 — Mixing.** Tabel baru (bukan `trials_header`, karena app legacy masih memakainya). Wizard berbasis konfigurasi langkah per jenis Case (Trial 6 langkah; Mixing tanpa Weighing). Menunggu D8 (poin TBD tim: cara melampirkan bukti revisi batch record, kategori produk Mixing).

**Tahap 5 — Product Journey penuh + ekspor stakeholder.** Timeline lengkap lintas modul, PDF tiga bahasa, ringkasan dalam bahasa biasa, filter per produk/client/periode.

## 7. Keputusan terbuka

| Kode | Pertanyaan | Default sementara |
|---|---|---|
| D1 | Format Case No | `VAL-{NPD\|IMP\|MIX}-{YYMM}-{urut}` |
| D2 | Reset urutan per bulan atau tahun | per bulan |
| D3 | Satu Case boleh banyak Trial (Re-trial)? | ya |
| D4 | Definisi "satu kali trial" (bagian 3) | disubmit, bukan Draft/terhapus; Rejected dan Re-trial dihitung |
| D5 | Aturan *Pending approval delete* | belum ada (tanya user) |
| D6 | Format nomor IM dan siapa yang menerbitkan | belum ada |
| D7 | Format nomor QCO, integrasi QCO elektronik (TBD di Excel) | isian manual dulu |
| D8 | Mixing: bukti revisi batch record, kategori produk | menunggu tim (Mr. Moon) |
| D9 | Siapa yang menentukan Risk (PIE saat assessment atau dihitung) | PIE saat assessment |

## 8. Log keputusan dan hasil tahap

- **2026-11-25:** konsep disetujui user: Case No sebagai nomor induk, Mixing digabung ke v2.0 (tanpa Weighing), Improvement/Portfolio mendapat kode otomatis, IM dan QCO menyusul di Tahap 3, Tahap 0 (penyelarasan Trial ↔ Project Tracker) wajib sebelum fitur baru. Audit bagian 4 dikerjakan hari yang sama; belum ada kode atau migrasi.

## 9. Prompt siap pakai

### Prompt Tahap 0 (tempel di chat baru)

```
Kita mengerjakan Trial Validation v2.0 di new_trial_validation_app/. Baca dulu: TRIAL_VALIDATION_V2_PLAN.md (repo root, sumber kebenaran), PROJECT_MODULE_PLAN.md bagian 6-7, CLAUDE.md di root dan new_trial_validation_app/CLAUDE.md (insiden DB 2026-08-24, aturan prod additive-only), dan new_trial_validation_app/docs/DB Validation.xlsx tab TRIAL VALIDATION APPS.

Kerjakan HANYA Tahap 0 (penyelarasan Trial <-> Project Tracker), tanpa fitur baru dan tanpa migrasi:
1. Audit read-only terhadap DB lokal: trial tanpa product_id atau yang nama produknya tidak cocok dengan master products; pm_toolings tanpa produk/trial/project; pm_project_stages bertipe Validation tanpa trial_id; trial yang tidak terhubung ke project mana pun. Gunakan query/artisan command read-only (boleh command baru dengan --dry-run), jangan tinker dengan factory, jangan menulis ke DB bersama.
2. Susun laporan temuan (jumlah dan contoh nyata per kategori) dan usulan padanan untuk ditinjau saya. Jangan memperbaiki data diam-diam.
3. Bantu saya memutuskan D1-D9 di TRIAL_VALIDATION_V2_PLAN.md bagian 7, terutama K4 (stage Completed tidak bisa ditautkan).
4. Update TRIAL_VALIDATION_V2_PLAN.md bagian 8 dengan hasilnya, lalu tulis prompt Tahap 1 untuk chat baru.

Batasan: jangan migrate:fresh/refresh/db:wipe, jangan migrate manual ke DB bersama, jangan pakai factory di tinker, jangan menyentuh ipc_app/ atau Project-Tracking-PIE-Team/.
```
