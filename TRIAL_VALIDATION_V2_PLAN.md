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
- Aplikasi kecil penerbit **nomor IM** dan **nomor QCO** (format dan alur approval: keputusan D6/D7), tercatat di Case lewat `vd_case_links`. **Penerbit (ditetapkan user 2026-11-25): QCO diterbitkan oleh PRNI, IM diterbitkan oleh QA.** Keduanya hanya di Tahap 3 (versi 3), bukan sebelumnya.
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
| D5 | Aturan *Pending approval delete* | belum ada (Excel hanya menulis judulnya di menu Trials; tanya user) |
| D6 | Format nomor IM | **Diputuskan 2026-11-26 (Tahap 3):** `IM-{KODE CUSTOMER/BRAND}-{NAMA PRODUK UKURAN}`, versi pertama tanpa sufiks, revisi ` V2`, ` V3`; UPPER CASE, spasi tunggal (dinormalkan). Penerbit QA, tanpa approval penerbitan (rantai tanda tangan IM tetap di kertas). Dibangun di 8.17 |
| D7 | Format nomor QCO, integrasi QCO elektronik (TBD di Excel) | **Diputuskan 2026-11-26 (Tahap 3):** `QCO-{YYYY}-{NNNN}`, urutan per tahun, penerbit PRNI, tanpa approval. Integrasi QCO elektronik tetap TBD. Dibangun di 8.17 |
| D8 | Mixing: bukti revisi batch record, kategori produk | menunggu tim (Mr. Moon) |
| D9 | Siapa yang menentukan Risk (PIE saat assessment atau dihitung) | PIE saat assessment |

**Usulan Tahap 0 (menunggu persetujuan user, belum final):** D1 setuju default, tapi Tahap 1 harus memetakan 6 nilai `validation_category` yang sudah ada ke NPD/IMP/MIX (lihat 8.5). D2 per bulan (volume ±60 trial/bulan, 3 digit cukup). D3 ya. D4 pakai default, tetapi hitung **per Case**, bukan per `product_id` (lihat temuan master produk di 8.3). D5 tetap tanya user. D6/D7 penerbit sudah jelas, format menyusul sebelum Tahap 3. D9 default. **K4: pertahankan aturan yang sudah jalan, dan jangan dipakai untuk riwayat** (lihat 8.6).

## 8. Log keputusan dan hasil tahap

- **2026-11-25:** konsep disetujui user: Case No sebagai nomor induk, Mixing digabung ke v2.0 (tanpa Weighing), Improvement/Portfolio mendapat kode otomatis, IM dan QCO menyusul di Tahap 3, Tahap 0 (penyelarasan Trial ↔ Project Tracker) wajib sebelum fitur baru. Audit bagian 4 dikerjakan hari yang sama; belum ada kode atau migrasi.

### Hasil Tahap 0 (2026-11-25) — audit read-only, tanpa kode dan tanpa migrasi

Ditetapkan user di sesi ini: **QCO diterbitkan oleh PRNI, IM diterbitkan oleh QA, keduanya di Tahap 3** (D6/D7, bagian 7).

**8.1 Cara audit.** Dua skrip PHP/PDO hanya-`SELECT` di scratchpad sesi (tidak ada command atau file baru di repo, tidak ada tulis ke DB, tidak ada tinker). DB yang diaudit: `trial_validation_system_fase5` (satu-satunya DB trial yang tersisa di MySQL lokal; `.env` menunjuk ke sini). **Ini salinan dev, bukan prod:** 337 trial aktif (salinan prod 23 Nov: 389), 46 project, 278 stage, 20 tooling. Angka prod akan berbeda; ulangi audit pada salinan prod sebelum backfill (skrip bisa dijadikan command `--dry-run` di Tahap 1).

**8.2 Ringkasan per kategori**

| Kategori | Hasil |
|---|---|
| Trial tanpa `product_id` | **0** dari 337. Tidak ada `product_id` yang menunjuk produk hilang/nonaktif. Nama snapshot = nama master di semua baris |
| Snapshot `finish_good_code` beda dari master | **5** trial (id 214, 247, 315, 359, 23) |
| Tooling tanpa produk / trial | **20 dari 20**: `pm_toolings` tidak punya kolom `product_id` maupun `trial_id`. Nama produk tooling cocok persis dengan master: **0 dari 20** |
| Tooling tanpa project | **19 dari 20** (hanya tooling 1 tertaut, ke project 39) |
| Stage Validation tanpa `trial_id` | **55 dari 55** (Completed 31, Not Started 18, On Progress 6). Tidak ada satu pun stage bertaut ke trial |
| Trial tidak terhubung ke project | **337 dari 337** |
| Project tanpa `product_id` / `machine_option_id` | **46 dari 46** (hanya diisi lewat form baru sejak Fase 4) |
| Improvement tanpa project | 1 dari 4 (id 3, status Baru) |

**8.3 Temuan dan contoh nyata**

1. **Trial → Product bersih.** Tidak ada yang perlu dipadankan. Lima trial dengan kode FG snapshot berbeda dari master (produk dan kode lama → master):
   - #214 UNL Close Up Spray & Pepsodent White Now 12x8ml: 617304001 → 603309001
   - #247 MOL Moell Healthy Baby Care Healthy Glow Barrier Face Cream 40g: 672901004 → 672901008
   - #315 RPI Somethinc Calm Down! Skinpair R-Cover Cream 50ml: 612409118 → 612409115101
   - #359 TAT Studiowest Cushion Fix Cushion Foundation 13g: 664001010 → 664001007
   - #23 KKI KOO Kula Beauty The OG Tint - Puce: 649301011 → 649301010 (kode lama milik produk "Sangria", jadi ini kemungkinan salah ketik di snapshot atau master berubah). Perlu ditinjau user, mana yang benar.
2. **Master `products` kotor untuk keperluan penautan (ini temuan terpenting).** 315 produk aktif, nama tidak ada yang kembar, tetapi **43 grup `finish_good_code` kembar (112 baris)**, semuanya dipakai trial:
   - **25 produk berkode `N/A`** (mis. Nubiko Physical Sunscreen, Saff & Co Deo Potion, Moell Ultra Protect Sunscreen) dan produk uji **"Trial new apps" berkode `0`** (3 trial: In Review, Rejected, Approved).
   - Varian dari satu FG dipecah jadi beberapa baris master: "KKI RCP Re:cipe Golden PDRN Moisturizing Sunscreen" ada 3 baris (nama biasa, "FT KKI RCP OBM …", "(NO FRAG)") semua kode 620502001; "NYK KAY BEAUTY … LIP TINT" dan "HydraBase Water Creme" berbagi 663602001 (dua produk berbeda, satu kode); Jennskin Deodorant Spray 3 baris berkode 668101004.
   - Akibatnya **menghitung "berapa kali produk ini ditrial" lewat `product_id` salah**: varian tersebar ke baris berbeda (dan kode `N/A` tidak bisa dipakai sebagai kunci ke IPC `fg_code`, yang unik). Distribusi sekarang (non-Draft, per `product_id`): 251 produk 1 trial, 29 produk 2 trial, 3 produk 3 trial. Case (satu Case = satu SKU yang divalidasi) adalah satuan hitung yang benar, bukan baris master.
3. **Tooling tidak bisa dipadankan otomatis ke master.** Hampir semua tooling adalah produk NPD baru yang memang belum ada di master `products` (mis. "Cillia Lumiere Blush On", "SkaeLab Cushion", "Daisy Skincare Bubble mask", "Nutox Lifte Youth Skin Filter"). Tebakan fuzzy hanya dua yang masuk akal dan **tetap harus diputuskan manusia**: tooling 1 "The Originote Micella Refill-New PM" ↔ master id 188 "KKI SMB The Originote Hyalurose Micellar Water 250ml (Refill)" (id 188, FG 646801008); sisanya kandidat lemah atau tidak ada (mis. tooling 11 "Bian Beauty Lip Tint" menebak ke NYK Kay Beauty Lip Tint, itu salah). Rekomendasi: jangan padankan massal; tautkan lewat Case saat produk NPD didaftarkan di master.
4. **Project ↔ Trial: kandidat nama sangat sedikit dan lemah.** Dari 46 project, hanya kandidat berikut yang masuk akal, semua perlu ditinjau PIC (bukan otomatis): PRJ-049 "The Originote Micella Refill-New PM" ↔ trial #220 (Originote Micellar Water Refill, In Review); PRJ-033 "NYK Contour Stick" ↔ trial #204 (RAD Contour Stick); PRJ-009 "FFY Powder and Cream Blush" ↔ trial #300 (kecocokan lemah, nama berbeda); PRJ-008 / PRJ-046 "TAT Cushion" ↔ #291, #359, #361 (ambigu, tiga trial TAT). PRJ-052 (Saff & Co) menghasilkan 16 kandidat, tidak informatif. Sisanya (mayoritas project Machine/Improvement) tidak punya trial produk sama sekali, memang tidak berpasangan dengan trial.
5. **Kolom yang sudah ada di trial dan belum diperhitungkan di rencana.** `trials_header` sudah punya `validation_category` (6 nilai: Trial NPD base on sales forecast 109, Validation 87, MPT 70, Trial NPD under development 63, Filling 6, Rework 2) dan `risk_level` (High 156, Medium 177, Low 4). Jadi "kategori NPD/Improvement" dan "Risk" di Tahap 1 bukan field baru; yang perlu dipetakan adalah nilai lama ke prefix Case (NPD/IMP/MIX). `product_type` "Mixing" sudah ada di 152 trial (45%), jadi Mixing-sebagai-jenis-trial sudah dipakai dengan wizard Trial biasa (memengaruhi Tahap 4).
6. **Stage Completed dan K4.** 31 dari 55 stage Validation berstatus Completed dan semuanya bertaut ke nol trial. Dua di antaranya tidak konsisten (PRJ-015 stage 68 "Trial" dan 69 "Validasi": Completed tetapi progress 0).
7. **Data uji di salinan lokal:** tooling id 20 (npd_no "Quia libero in at ip", produk "Illana Terrell", dibuat 2026-11-25 04:58) berasal dari uji lokal; abaikan, bukan dari prod. Tidak ada project buatan app baru (semua dari impor).

**8.4 Tidak ada data yang diubah.** Tidak ada perbaikan diam-diam. Semua padanan di atas hanya usulan.

**8.5 Usulan pemetaan `validation_category` → jenis Case (untuk dikonfirmasi user, Tahap 1):** "Trial NPD base on sales forecast" dan "Trial NPD under development" → NPD; "Validation" (87) dan "MPT" (70) → belum jelas (Improvement atau NPD? MPT apa kepanjangannya di tim); "Filling" dan "Rework" → kemungkinan IMP; Mixing ditentukan dari `product_type = Mixing`, bukan dari kategori. Tanpa jawaban user, backfill Tahap 1 tidak boleh menebak: baris yang ambigu ditandai.

**Jawaban user 2026-11-25 (menggantikan usulan 8.5):** MPT = **Master Product Trial**. Pemetaan jenis Case: kategori yang **namanya memuat "NPD"** (Trial NPD base on sales forecast, Trial NPD under development) → **NPD**; **semua sisanya (Validation, MPT, Filling, Rework) → IMP (Improvement)**. Tidak ada lagi baris ambigu untuk kategori. **Mixing (jawaban user): jenis sendiri, prefix MIX** (beda dari Filling/Packing): trial ber-`product_type = Mixing` (152 trial) dipetakan ke **MIX**, mengalahkan aturan kategori; sisanya mengikuti aturan NPD/IMP di atas. **FG kembar (43 grup) dirapikan di Tahap 1**, lewat laporan padanan yang ditinjau user (tidak diubah diam-diam, tidak menghapus baris master yang dipakai trial).

**8.6 Keputusan K4 (usulan).** Aturan "stage Completed tidak bisa ditautkan" (keputusan 2026-11-23, sudah ditegakkan di server) **dipertahankan** untuk pekerjaan aktif, karena re-trial memang harus membuka ulang stage secara sadar. Data menunjukkan tidak ada riwayat yang pernah tertaut, sehingga yang dibutuhkan untuk riwayat adalah jalur lain: **tautan lewat Case (`vd_case_links` kind `trial` + `project`) tanpa mengubah status stage.** Dengan begitu: (a) K4 tidak menghalangi backfill historis; (b) sinkron status tidak pernah menurunkan stage Completed karena riwayat tidak menyentuh stage; (c) Project ↔ Trial historis tidak butuh stage sama sekali. Stage-level link tetap untuk alur kerja baru yang berjalan.

**Persetujuan user 2026-11-25:** semua usulan di atas disetujui (D1-D4, D9, K4 sesuai 8.6, usulan 8.5-8.6). Jawaban rinci untuk butir 2-5 di 8.7 (arti MPT, nasib FG kembar, 5 trial beda FG, tooling 1 ↔ id 188) masih harus dikonfirmasi di awal Tahap 1; usulan 8.5 dipakai sebagai default sampai ada jawaban.

**8.8 Hasil pengecekan butir 1-2 (2026-11-25, read-only):**
- **Lima trial beda FG: master yang benar.** Pada #214, #247, #315, #359, snapshot di trial adalah kode LAMA: trial lain untuk produk yang sama yang dibuat belakangan (#349, #358, #378/#379, #364) membawa kode master, artinya master dikoreksi setelah trial pertama dibuat. #23 (Puce): snapshot 649301011 adalah kode produk saudaranya (Sangria, id 65), master Puce 649301010 benar. Rekomendasi: Case dan Product Journey membaca FG dari master lewat `product_id`; baris `trials_header` tidak di-UPDATE (tabel bersama dengan app lama; koreksi data prod hanya atas perintah eksplisit user). Lima trial ditandai "FG snapshot usang" di laporan padanan.
- **Tooling 1 bukan master id 188.** Tooling 1 ("The Originote Micella Refill-New PM", Spout Pouch, launch Jan-Feb 2027, request November 2026) adalah *PM baru* untuk lini refill; master 188 ("Hyalurose Micellar Water 250ml (Refill)") sudah punya trial #220 sejak 3 September 2026, sebelum tooling diminta. Satu-satunya master berkata "Originote" hanya id 188, jadi SKU pouch baru itu belum terdaftar di master. Project PRJ-049 (tooling investment) stage Validation-nya belum mulai. Tooling 1 dibiarkan tanpa produk sampai SKU baru didaftarkan.

**8.7 Keputusan yang butuh jawaban user sebelum Tahap 1 (SEMUA SUDAH TERJAWAB, lihat 8.8 dan "Jawaban user"):**
1. Setuju D1-D4, D9 dan usulan K4 di 8.6? (D5 tetap pertanyaan terbuka.)
2. Pemetaan 8.5: apa itu **MPT**, dan apakah "Validation" = Improvement?
3. Master produk: tangani 43 grup FG kembar (25 berkode `N/A` + "Trial new apps") di Tahap 1 (rapikan bertahap lewat laporan) atau di luar v2.0? Ini menentukan apakah jejak ke IPC (`fg_code` unik) bisa jalan di Tahap 3.
4. Lima trial dengan kode FG snapshot berbeda (8.3 butir 1): mana yang benar?
5. Tooling 1 ↔ master id 188 (Originote Refill): benar atau bukan?

### Hasil Tahap 1 (2026-11-25) — fondasi Case, field baru, Product Journey minimal

Dibangun di `new_trial_validation_app/`. **Belum dijalankan ke DB prod/dev server:** migrasi dan backfill hanya dijalankan di MySQL lokal (lihat 8.9). Tidak ada ALTER ke tabel bersama, `trials_header` dan stage proyek tidak disentuh.

**8.9 Yang dibangun**
- Migrasi additive `2026_11_26_000001_create_vd_case_tables.php` (hasTable guard, tanpa FK): `vd_cases`, `vd_case_counters` (unik per jenis+periode), `vd_case_links` (unik `kind`+`ref_key`; `ref_key` selalu terisi: id sebagai teks atau kunci bisnis IM/QCO/batch, `ref_id` nullable), `vd_trial_meta` (PM ready, Bulk ready, `dates_defaulted`, bukti approval client). Ditaruh di tabel samping karena `trials_header` tidak boleh di-ALTER; `validation_category` dan `risk_level` yang sudah ada dipetakan, tidak diduplikasi.
- Model `ValidationCase`, `ValidationCaseLink`, `ValidationTrialMeta`; `ValidationCasePolicy` (lihat: semua user login; catat approval client: Staff/Admin/approver).
- `CaseNumbering` (counter dikunci `lockForUpdate`, bukan `MAX()+1`), format `VAL-{NPD|IMP|MIX}-{YYMM}-{3 digit}`. `ValidationCaseType` memakai pemetaan 8.5 yang diputuskan user.
- `TrialOutcome`: satu trial dihitung bila bukan Draft dan tidak terhapus; hasil turunan (tidak disimpan): Pass (Approved tanpa NOT OK), Pass with Approval Client (ada NOT OK + bukti client dicatat), Re-trial (Need Revision, atau Approved dengan NOT OK tanpa bukti client), Fail (Rejected), Pending (masih In Review/Ready). Bukti approval client = tanda + catatan di `vd_trial_meta`, dokumennya diunggah lewat lampiran tambahan trial yang sudah ada (tanpa mekanisme upload baru).
- `EnsureCaseForTrial`: satu Case per (jenis, `product_id`), idempoten, menomori ulang `attempt_no` (Draft null). **FG kembar tidak digabung**; pelaporan saja. Dipanggil dari `CreateTrial` (lewat `safely()`, diam sampai migrasi dijalankan, tidak pernah menggagalkan alur trial). Form trial punya dua kolom baru, PM Ready Date dan Bulk Ready Date (opsional), disimpan lewat `SaveTrialMeta`.
- `php artisan validation:backfill-cases [--dry-run]`: menanam Case di bawah semua trial yang ada (kronologis), tanggal bawaan H-1 dari tanggal pembuatan (ditandai `dates_defaulted`), tautan Project ke Case **hanya** bila jelas (stage yang sudah tertaut trial, atau produk project yang hanya punya satu Case); sisanya dicetak, tidak ditebak: konflik, produk dengan banyak Case, kandidat nama persis. Laporan padanan: grup FG kembar dan trial dengan FG snapshot usang. `--dry-run` menjalankan kode yang sama di dalam transaksi yang di-rollback.
- Product Journey: `/journey` (daftar, filter jenis + cari nomor/produk/FG) dan `/journey/{case}` (ringkasan "N kali trial" + rincian hasil, linimasa percobaan dengan hasil/status/tanggal/PM-Bulk/lead time/Line Configuration, project terkait, catat approval client). FG dibaca dari master lewat `product_id`. Menu "Product Journey" di grup Ringkasan. KPI **Realized Trial Lead Time (NPD)** di Dashboard.
- String id/en/ko di grup `journey` + `common.nav.journey`.

**8.10 Keputusan yang diambil di tahap ini (mohon dikoreksi bila salah)**
1. **KPI lead time hanya memakai tanggal asli.** Tanggal bawaan H-1 membuat setiap trial lama terbaca "1 hari" dan itu menyesatkan, jadi baris `dates_defaulted` dikeluarkan dari rata-rata dan jumlahnya ditampilkan ("N trial memakai tanggal bawaan"). Akibatnya KPI kosong sampai PIE mengisi tanggal di trial baru atau mengoreksi yang lama. Bila Anda tetap ingin angka dari tanggal bawaan, itu cukup satu baris di `CaseJourney::realizedLeadTimeKpi`.
2. Atribusi percobaan **per Case**, urut `created_at`. Satu trial yang di-revisi (Need Revision, `revision_no`) tetap satu percobaan; hanya trial baru yang menambah hitungan.
3. Case tidak otomatis berpindah bila produk/kategori trial diubah sesudah Case terbentuk (tautan tetap). Dibiarkan sampai Tahap 2 (Case dibuat dari Assessment).
4. Trial buatan app baru kini otomatis masuk Case saat dibuat; trial buatan app lama baru masuk lewat perintah backfill (aman dijalankan ulang, jadwalkan bila app lama masih hidup lama).

**8.11 Hasil uji.** Dry-run + run nyata + run ulang pada salinan lokal baru `trial_validation_system_v2t1` (dari `trial_validation_system_fase5`, 337 trial aktif): **306 Case** (NPD 141, IMP 15, MIX 150), 337 tautan trial, 27 Case dengan 2+ percobaan, run kedua 0 perubahan (idempoten), 0 tautan project (belum ada project yang berproduk atau punya stage bertrial). Laporan padanan: 39 grup FG kembar (101 baris master, 23 di antaranya berkode `N/A`) dan 5 trial FG snapshot usang (#23, #214, #247, #315, #359) persis seperti temuan Tahap 0. 19 Pest test baru (generator, pemetaan jenis, hasil turunan, backfill + dry-run + idempoten + tidak menyentuh `trials_header`, tautan project + stage Completed tak berubah, halaman Journey, filter, approval client, KPI, simpan tanggal di form + Case otomatis). Verifikasi live Playwright pada salinan itu (daftar, detail Case 3 percobaan, kartu KPI di Dashboard): tanpa error konsol. Gate: Pint bersih untuk file baru, Larastan hanya 2 error lama (`TrialMonitorController`, migrasi `rename_pi_review_team_to_pie`), `php artisan test` 719/723 lulus (1 gagal lama `ExportLocaleTest`/ZipArchive, 3 skip lama), tsc dan `npm run build` bersih, ESLint 1 error lama di `toolings/show.tsx`, Prettier 1 peringatan lama di `resources/ai/trial_document.schema.json`.

**8.12 Catatan lingkungan.** Tabel `vd_*` (kosong) dan catatan migrasinya (batch 23) ternyata sudah ada di DB lokal `trial_validation_system_fase5` (salinan dev, bukan prod) padahal saya tidak menjalankan `migrate` ke sana dengan sengaja; kemungkinan dijalankan otomatis oleh proses lokal. Tidak merusak apa pun (additive, 0 baris). Server dev/prod **belum** memilikinya: migrasi akan jalan lewat pipeline (`migrate --force`), lalu backfill dijalankan manual atas perintah Anda. Hati-hati juga: `composer run lint` (Pint tanpa `--test`) mengubah banyak file lama yang tidak terkait (line ending); saya mengembalikannya, jangan jalankan itu mentah-mentah. Pakai `composer run lint:check`.

### Hasil Tahap 2 (2026-11-26) — Validation Assessment, Validation Plan, Tooling terhubung ke Case

Dibangun di `new_trial_validation_app/`. **Belum dijalankan ke server dev/prod**; migrasi dan semua verifikasi hanya di MySQL lokal salinan `trial_validation_system_v2t2` (dari `trial_validation_system_v2t1`, nama DB dicek dulu sebelum `migrate`). Tidak ada ALTER ke tabel bersama maupun `pm_*`; `trials_header` dan `pm_toolings` tidak ditulis.

**8.13 Yang dibangun**
- Migrasi additive `2026_11_27_000001_create_vd_assessment_tables.php` (hasTable guard, tanpa FK): `vd_assessments` (satu per Case), `vd_validation_plans`, `vd_improvement_numbers`, `vd_number_counters` (counter terkunci per scope+periode, pola sama dengan `vd_case_counters`).
- **Validation Assessment** `/validation/assessments` (`ValidationAssessmentController`, `SaveValidationAssessment`): form lengkap sesuai peta menu (SKU dari master atau nama bebas bila SKU baru belum ada di master, tanggal terima, client, bulan sales, qty PO, packaging type, scope, mesin rekomendasi, nomor + link QCO manual, PM/Bulk diterima dengan tanda N/A, Risk). **Menyimpan Assessment membuat Case** lewat `CaseNumbering::create` (source `assessment`); jenis Case (NPD/IMP/MIX) dipilih saat membuat dan tidak bisa diubah (nomornya sudah terbit). Edit mengikuti produk ke Case hanya selama belum ada trial tertaut. Filter: cari (Case/SKU/FG/client/QCO), jenis, risk, packaging, client, bulan sales, rentang tanggal terima. Hapus hanya Admin dan hanya bila Case belum punya tautan apa pun (soft delete Assessment + Case).
- **Validation Plan** `/validation/plans` (`ValidationPlanController`, `SaveValidationPlan`): PIE meminta minggu trial untuk satu Case, tanggal dinormalkan ke Senin minggu itu; PPIC konfirmasi dengan catatan; peminta (atau Admin) bisa membatalkan selama belum dikonfirmasi. Notifikasi dalam app ke department PPIC.
- **Peran** (`ValidationRoles`, tanpa akun baru): PIE = `ToolingWorkflow::hasRole('pi')` (department / review unit PI atau PIE) atau Admin; PPIC = department atau review unit bernama `PPIC`, atau Admin. Semua user login boleh membaca (seperti Product Journey).
- **Cek Tooling ↔ Case** (`ValidationCaseToolingController`, `LinkCaseTooling`): dari halaman Case, PIE menautkan/melepas tooling lewat `vd_case_links` kind `tooling`. Satu tooling hanya boleh di satu Case; `pm_toolings.npd_no` tetap sebagai referensi lama dan tidak ditulis. Tooling 1 (Originote refill) **tidak** ditautkan, sesuai keputusan.
- **Nomor Improvement** `IMP-{YYYY}-{3 digit}` (`AssignImprovementNumber`): counter terkunci per tahun, disimpan di `vd_improvement_numbers` (bukan kolom baru di `pm_improvements`), diberikan otomatis saat Improvement dibuat (`SaveImprovement`, lewat `safely()` yang diam bila migrasi belum jalan) dan tampil di daftar + detail Improvement. Perintah `php artisan validation:number-improvements [--dry-run]` memberi nomor untuk yang lama (berurutan menurut tanggal submit, idempoten).
- **Buat Trial dari Case** (`trials.create?case=<id>`, `CaseTrialPrefill`): pra-isi produk, tipe produk (dari packaging type), scope, mesin, risk, kategori (Case NPD menyarankan kategori ber-"NPD") dan tanggal PM/Bulk dari Assessment; hanya nilai yang masih opsi master aktif yang dipakai supaya form tidak dimulai dengan nilai yang akan ditolak server. `StoreTrialRequest` menerima `case_id`; `EnsureCaseForTrial::linkTo` memindahkan tautan otomatis ke Case pilihan dan menomori ulang percobaan di kedua Case.
- **Halaman Case** (`/journey/{id}`): kartu Assessment (titik awal), kartu Cek Tooling (tautkan/lepas), kartu Validation Plan, tombol "Buat Trial dari Case". Menu sidebar: Validation Assessment dan Validation Plan di grup Ringkasan. String id/en/ko di grup `assessment` + `common.nav.assessment|validation_plan`.

**8.14 Keputusan yang diambil (mohon dikoreksi bila salah)**
1. Assessment tampil sebagai **kartu di atas linimasa**, bukan titik di linimasa percobaan (linimasa itu berisi percobaan trial).
2. Case hasil backfill (dari trial lama) **tidak punya Assessment**. **Diselesaikan di putaran lanjutan 2026-11-26 (8.16):** tombol "Lengkapi Assessment" di halaman Case membuka register dengan Case itu; nomor Case tidak berubah.
3. PPIC dikenali dari department/review unit `PPIC`. **Keputusan user 2026-11-26:** PPIC dibuat seperti PIE/PROD/PRNI, yaitu Review Team lewat layar Access Rights (Super Admin menambah reviewer department `PPIC`, lalu mengisi Review Team akun). Tidak perlu kode baru. Catatan: reviewer department yang aktif juga muncul sebagai pilihan department di langkah Submit for Review trial, jadi menambah `PPIC` di sana membuatnya bisa dipilih di sana juga.
4. **Improvement ↔ Case ditautkan** (keputusan user 2026-11-26, lihat 8.16).
5. Recommended machine tetap isian teks bebas dengan saran dari master mesin (kelak dari Cek Tooling), sesuai prompt.

**8.15 Hasil uji.** 14 Pest test baru (`tests/Feature/ValidationAssessmentTest.php`): Case terbit dari Assessment, peran, validasi, edit + N/A, filter, hapus, Plan (minggu dinormalkan, PPIC konfirmasi, batal), tautan tooling (tidak menulis `pm_toolings`, satu Case saja, lepas), nomor Improvement (berurutan per tahun, idempoten, perintah backfill + dry-run), pra-isi trial dari Case dan tautan ke Case pilihan, paritas bahasa. Gate: `php artisan test` 733 lulus dari 737 (1 gagal lama `ExportLocaleTest`/ZipArchive, 3 skip lama), Pint bersih untuk file baru (`composer run lint:check`; file lama berakhiran CRLF yang tidak disentuh ikut terdaftar oleh Pint), Larastan hanya 2 error lama (`TrialMonitorController`, migrasi `rename_pi_review_team_to_pie`), ESLint dan Prettier bersih untuk file yang disentuh, tsc dan `npm run build` bersih. Verifikasi live Playwright pada salinan `trial_validation_system_v2t2`: Assessment dibuat (Case `VAL-NPD-2611-020`), halaman Case menampilkan kartu, form trial terisi dari Case, permintaan Plan masuk daftar, tanpa error konsol. Salinan `v2t2` dan file tangkapan layar tidak dimasukkan ke repo.

### Putaran lanjutan Tahap 2 (2026-11-26, jawaban user atas 8.14)

**8.16 Yang ditambahkan**
- **Improvement ↔ Case** (`LinkCaseImprovement`, `ValidationCaseImprovementController`, `journey.improvements.*`): PIE/Admin menautkan atau melepas Improvement dari halaman Case lewat `vd_case_links` kind `improvement` (satu Case per Improvement, `pm_improvements` tidak ditulis). Halaman Case menampilkan kartu Improvement (nomor `IMP-YYYY-NNN`, judul, status) dan halaman detail Improvement menampilkan Case-nya. **Warisan ke Project:** saat Improvement yang sudah tertaut di-"To Project" (`ConvertImprovementToProject`), Project barunya otomatis ditautkan ke Case yang sama (kind `project`); bila Improvement baru ditautkan setelah sudah menjadi Project, Project-nya ikut. Project yang sudah tertaut ke Case lain tidak dipindah.
- **Lengkapi Assessment untuk Case lama**: Case hasil backfill tanpa Assessment mendapat tombol "Lengkapi Assessment" (`/validation/assessments?complete=<case>`); form menyimpan Assessment ke Case itu (`case_id`), nomor tidak berubah, jenis Case tidak ditanya lagi, dan satu Case hanya bisa dilengkapi sekali.
- **PPIC** diatur sebagai Review Team lewat Access Rights (tanpa kode baru).
- 3 Pest test baru (total 17 di `ValidationAssessmentTest.php`); suite penuh 736 lulus dari 740 (1 gagal lama ZipArchive, 3 skip lama); Larastan hanya 2 error lama; verifikasi live Playwright pada salinan `trial_validation_system_v2t2` (lengkapi Case 307, tautkan Improvement, halaman Improvement menunjuk balik ke Case).

### Hasil Tahap 3 (2026-11-26) — penerbit nomor QCO dan IM, lookup batch IPC read-only

**8.17 Keputusan user (jawaban atas pertanyaan format)**
- QCO: `QCO-YYYY-NNNN`, diterbitkan PRNI, tanpa approval. IM: contoh nyata dari user (`IM-ABL-SCULPT CALM LILY DEODORANT 25ML V2`, `IM-BHM- BHUMI CALMING CLEANSER 100ML- V2`, form FR.QAC.050.01) menunjukkan format **deskriptif, bukan urutan**: `IM-{kode customer/brand}-{nama produk + ukuran}[ V{n}]`. Versi pertama tanpa sufiks lalu V2, V3 (IM BHM lama memakai sufiks `A`; input historis seperti itu tetap diterima sebagai teks). Spasi dan huruf dirapikan otomatis (UPPER CASE, spasi tunggal) supaya cocok dengan IPC. Penerbit IM: QA (departemen/review unit `QA` atau `QAC`). Tanpa approval penerbitan: rantai tanda tangan di halaman Validasi IM (QA Staff, QA Part Leader, QC, Production, PPIC, QAC Leader, Customer) tetap di dokumen kertas dan tidak dibangun. Hanya nomor + metadata header yang dicatat, PDF IM tidak diunggah.

**8.18 Yang dibangun**
- Migrasi `2026_11_28_000001_create_vd_number_registers` (additive, `hasTable`, tanpa FK): `vd_qco_numbers`, `vd_im_brand_codes`, `vd_im_numbers`. Counter QCO memakai baris `vd_number_counters` terkunci (scope `qco`, period tahun), bukan `MAX()+1`; unique `(year, seq)` dan unique `number` sebagai pengaman.
- `IssueQcoNumber`, `IssueImNumber` (terbit, revisi V+1 yang menandai IM lama `Superseded` dan mencatat "Menggantikan", batalkan dengan alasan; nomor Void tidak dipakai ulang), `LinkCaseNumber` (kind `qco`/`im`, `ref_key` = nomor, satu Case per nomor), `ImNumberFormat::normalize/compose`. Peran: `ValidationRoles::isPrni` / `isQa` (Admin semuanya); semua pengguna login boleh membaca. Semua aksi masuk `activity_logs` (`QCO_NUMBER`, `IM_NUMBER`, `VALIDATION_CASE`).
- Halaman `/validation/qco` dan `/validation/im` (+ kode customer/brand baru langsung dari dialog IM), menu sidebar, kartu "Nomor QCO & IM" di halaman Case (tautkan/lepas oleh PIE atau penerbit), string `numbers.php` id/en/ko. Isian QCO manual di Assessment tidak diubah.
- **IPC read-only** (`IpcBatchLookup`): koneksi DB `ipc_system` (`IPC_DB_*`, default memakai host/kredensial `DB_*`; pakai akun SELECT-only bila ada), kartu "Batch IPC" di halaman Case. Padanan: nomor IM batch (`startup_checks.im_document_number`, dinormalkan) sama dengan IM tertaut ke Case, atau `master_products.fg_code` sama dengan FG produk Case / FG di IM (kode kosong dan `N/A` diabaikan). Tidak pernah menulis dan tidak pernah melempar: bila IPC tidak terbaca halaman menampilkan "tidak tersedia". **Tidak ada perubahan di `ipc_app/`.** Opsi lanjutan "Startup Check IPC memilih IM dari daftar terbit dan menyimpan Case No" butuh rencana dan persetujuan sendiri (migrasi IPC lewat pipeline).
- 15 Pest test baru (`tests/Feature/ValidationNumbersTest.php`); suite penuh 751 lulus dari 755 (1 gagal lama ZipArchive, 3 skip lama); Pint `lint:check` lulus; Larastan hanya 2 error lama (`TrialMonitorController`, migrasi `rename_pi_review_team_to_pie`); tsc dan build lulus; ESLint hanya 1 error lama (`toolings/show.tsx`). Verifikasi live Playwright pada salinan baru `trial_validation_system_v2t3` (dari `v2t2`, migrasi Tahap 3 dijalankan hanya di salinan): QCO-2026-0001 terbit, IM BHM terbit lalu direvisi ke V2 (V1 jadi Superseded), keduanya ditautkan ke Case 324, dan kartu Batch IPC menampilkan 2 batch nyata dari `ipc_system` lewat FG 651702009; `ipc_batches` tetap 917 baris.

**8.19 Catatan dan yang masih terbuka**
- Migrasi `2026_11_28_000001` belum dijalankan di server dev/prod (ikut `migrate --force` pipeline), begitu juga migrasi/backfill Tahap 1-2. Sampai itu jalan, halaman register menampilkan peringatan "tabel belum tersedia" dan Journey tetap jalan.
- Server dev/prod harus punya kredensial baca ke `ipc_system` (`IPC_DB_*`); bila tidak, kartu IPC menampilkan "tidak tersedia".
- Kode customer/brand diisi QA lewat dialog IM (belum ada master terpisah atau impor). Akun "Viewer PRNI/QAC" lokal bisa menerbitkan karena departemennya PRNI/QAC (sejalan K6 Tahap 4b).
- Python di Windows menulis CRLF saat membuka file teks; semua edit file lewat skrip harus memakai `newline=''` (Pint melaporkan `line_ending`).

## 9. Prompt siap pakai

### Prompt Tahap 4 (tempel di chat baru)

```text
Kita mengerjakan Trial Validation v2.0 di new_trial_validation_app/. Baca dulu: TRIAL_VALIDATION_V2_PLAN.md (repo root; bagian 2, 6 "Tahap 4", 7 (D8), dan 8 terutama "Hasil Tahap 3" 8.17-8.19), CLAUDE.md di root dan new_trial_validation_app/CLAUDE.md (insiden DB 2026-08-24, aturan prod additive-only), serta ipc_app/CLAUDE.md (hanya baca).

Konteks: Tahap 1-3 selesai (Case, Product Journey, Assessment, Validation Plan, tautan Tooling/Improvement, penerbit nomor QCO dan IM, lookup batch IPC read-only). Migrasi dan backfill v2.0 belum dijalankan di server dev/prod. Jangan membangun Tahap 5. D8 (poin TBD tim Mr. Moon: cara melampirkan bukti revisi batch record, kategori produk Mixing) belum terjawab: tanyakan ke saya lebih dulu dan jangan menebak. Tanyakan juga apakah Mixing memakai pola approval yang sama dengan Trial.

Kerjakan HANYA Tahap 4, sesuai bagian 6: Mixing sebagai jenis Case MIX dengan tabel baru vd_* (additive, hasTable guard, tanpa FK; JANGAN mengubah trials_header, app legacy masih memakainya), wizard berbasis konfigurasi langkah per jenis Case (Trial 6 langkah; Mixing tanpa Weighing), tercatat di Case lewat vd_case_links kind `mixing`, Product Journey menampilkan Mixing, id/en/ko, Pest test, gate lengkap (Pint hanya `composer run lint:check`, bukan `composer run lint`; Larastan, php artisan test, ESLint, Prettier, tsc, npm run build), verifikasi live Playwright di DB SALINAN baru dari trial_validation_system_v2t3 lewat mysqldump dengan DB_DATABASE env override (cek namanya dulu), lalu ritual penutup: update plan bagian 8, CLAUDE.md (root dan new_trial_validation_app/), commit pesan English dengan stage file spesifik, tulis prompt Tahap 5.

Batasan: jangan migrate:fresh/refresh/db:wipe, jangan migrate manual ke DB bersama/dev/prod, jangan --env= tanpa file .env.<nama>, jangan factory di tinker, jangan menyentuh Project-Tracking-PIE-Team/, jangan UPDATE trials_header, jangan menulis ke ipc_system atau mengubah ipc_app/. Edit file lewat skrip Python harus memakai newline='' (kalau tidak Windows menulis CRLF); heredoc berisi tanda kutip gagal di sini, pakai tool Write.
```

### Prompt Tahap 3 (arsip, sudah dikerjakan 2026-11-26)

```text
Kita mengerjakan Trial Validation v2.0 di new_trial_validation_app/. Baca dulu: TRIAL_VALIDATION_V2_PLAN.md (repo root; bagian 2, 6 "Tahap 3", 7 (D6/D7), dan 8 terutama "Hasil Tahap 2" 8.13-8.15), PROJECT_MODULE_PLAN.md bagian 6-7, CLAUDE.md di root dan new_trial_validation_app/CLAUDE.md (insiden DB 2026-08-24, aturan prod additive-only), serta ipc_app/CLAUDE.md (hanya baca; ipc_app proyek terpisah).

Konteks: Tahap 1 dan 2 selesai (Case, Product Journey, Assessment yang membuat Case, Validation Plan, tautan Cek Tooling, nomor Improvement, Buat Trial dari Case). Migrasi dan backfill v2.0 belum dijalankan di server dev/prod. Jangan membangun Mixing (Tahap 4). Tanyakan ke saya lebih dulu: format nomor QCO dan IM (D6/D7 belum ada format) dan alur approval-nya. Pertanyaan PPIC dan tautan Improvement sudah dijawab (8.14 dan 8.16).

Kerjakan HANYA Tahap 3, sesuai bagian 6: (1) penerbit nomor QCO (diterbitkan PRNI) dan IM (diterbitkan QA), tercatat di Case lewat vd_case_links kind `qco` dan `im` (ref_key = nomornya), tabel baru vd_* (additive, hasTable guard, tanpa FK, counter terkunci bukan MAX()+1), isian manual di Assessment tetap berfungsi; (2) di IPC hanya lookup read-only: koneksi baca ke ipc_system, padanan lewat fg_code (products.finish_good_code) + nomor IM, supaya Product Journey menampilkan batch IPC; perubahan ke ipc_app/ perlu rencana sendiri dan persetujuan saya; (3) Pest test, gate lengkap (Pint hanya `composer run lint:check`, bukan `composer run lint`; Larastan, php artisan test, ESLint, Prettier, tsc, npm run build), verifikasi live Playwright di DB SALINAN (buat salinan baru dari trial_validation_system_v2t2 lewat mysqldump, jalankan dengan DB_DATABASE env override dan cek namanya dulu); (4) ritual penutup: update plan bagian 8, CLAUDE.md (root dan new_trial_validation_app/), commit pesan English dengan stage file spesifik, tulis prompt Tahap 4.

Batasan: jangan migrate:fresh/refresh/db:wipe, jangan migrate manual ke DB bersama/dev/prod, jangan --env= tanpa file .env.<nama>, jangan factory di tinker, jangan menyentuh Project-Tracking-PIE-Team/, jangan UPDATE trials_header, tulis ke ipc_system hanya dengan persetujuan eksplisit.
```

### Prompt Tahap 2 (arsip, sudah dikerjakan 2026-11-26)

```text
Kita mengerjakan Trial Validation v2.0 di new_trial_validation_app/. Baca dulu: TRIAL_VALIDATION_V2_PLAN.md (repo root; bagian 2, 3, 5, 6 "Tahap 2", 7, dan 8 terutama "Hasil Tahap 1" 8.9-8.12), PROJECT_MODULE_PLAN.md bagian 6-7, CLAUDE.md di root dan new_trial_validation_app/CLAUDE.md (insiden DB 2026-08-24, aturan prod additive-only; bagian Projects Fase 4/4b untuk pola tautan stage-trial dan Check Tooling).

Konteks: Tahap 1 selesai (Case `vd_*`, backfill, Product Journey minimal, KPI lead time, field PM/Bulk ready). Belum dijalankan ke server dev/prod. Jangan membangun IM/QCO generator (Tahap 3) atau Mixing (Tahap 4). D5 (Pending approval delete) masih terbuka: tanyakan ke saya bila perlu.

Kerjakan HANYA Tahap 2 (Validation Assessment + Validation Plan + Tooling terhubung), sesuai bagian 6 dan peta menu bagian 5:
1. Register Validation Assessment (ADD NEW DATA): SKU (dropdown master produk), receive date, client, sales month dan qty PO, packaging type, scope, mesin/line rekomendasi (kelak dari Cek Tooling), nomor+link QCO (isian manual dulu), tanggal PM dan Bulk diterima (boleh N/A), Risk (D9: ditentukan PIE saat assessment). Menyimpan Assessment MEMBUAT Case (CaseNumbering::create, source manual) dan ia menjadi tulang punggung; semua kolom bisa difilter. Tabel baru `vd_assessments` (additive, hasTable guard, tanpa FK). Tampilkan di Product Journey sebagai titik awal linimasa.
2. Validation Plan: permintaan jadwal trial mingguan ke PPIC (SKU + Week), tabel `vd_validation_plans`, terkait Case; halaman untuk PIE (buat) dan PPIC (lihat/konfirmasi). Putuskan peran lewat pola ToolingWorkflow::hasRole; jangan buat akun baru.
3. Cek Tooling dan Improvement ditautkan ke Case lewat `vd_case_links` (kind `tooling`; Improvement mendapat nomor sendiri `IMP-{YYYY}-{urut}` lewat tabel/counter baru, bukan kolom baru di pm_*, kecuali nullable additive yang saya setujui). `pm_toolings.npd_no` tetap sebagai referensi lama. Tooling 1 (Originote refill) ditautkan lewat Case setelah SKU barunya didaftarkan di master, jangan dipadankan ke master id 188.
4. Pembuatan Trial dari Case (pra-isi produk, jenis, mesin dari Assessment), mengikuti pola Fase 4 (`trials.create?project_stage=`): trial baru langsung ditautkan ke Case yang sama sebagai percobaan berikutnya.
5. Pest test, lalu gate lengkap (Pint hanya `composer run lint:check`, JANGAN `composer run lint` mentah karena mengubah file lama; Larastan, php artisan test, ESLint, Prettier, tsc, npm run build) dan laporkan apa adanya termasuk kegagalan lama. Verifikasi live (Playwright) pada DB lokal SALINAN (pola: buat DB salinan baru dari trial_validation_system_fase5 lewat mysqldump, jalankan dengan DB_DATABASE env override dan cek namanya dulu), bukan DB bersama.
6. Ritual penutup: update TRIAL_VALIDATION_V2_PLAN.md bagian 8, CLAUDE.md (root dan new_trial_validation_app/), commit pesan English dengan stage file spesifik, lalu tulis prompt Tahap 3.

Batasan: jangan migrate:fresh/refresh/db:wipe, jangan migrate manual ke DB bersama/dev/prod, jangan --env= tanpa file .env.<nama>, jangan factory di tinker, jangan menyentuh ipc_app/ atau Project-Tracking-PIE-Team/, jangan UPDATE trials_header.
```

### Prompt Tahap 1 (arsip, sudah dikerjakan 2026-11-25)

```text
Kita mengerjakan Trial Validation v2.0 di new_trial_validation_app/. Baca dulu: TRIAL_VALIDATION_V2_PLAN.md (repo root, sumber kebenaran; terutama bagian 2, 3, 7, dan 8 "Hasil Tahap 0" yang memuat temuan audit), PROJECT_MODULE_PLAN.md bagian 6-7, CLAUDE.md di root dan new_trial_validation_app/CLAUDE.md (insiden DB 2026-08-24, aturan prod additive-only).

Konteks: Tahap 0 selesai (audit read-only, tidak ada kode). Jawaban user atas pertanyaan 8.7 akan saya berikan di awal chat; sudah diputuskan (lihat 8, "Jawaban user"): D1-D4, D9, K4 disetujui; MPT = Master Product Trial; kategori bernama NPD -> NPD, sisanya (Validation, MPT, Filling, Rework) -> IMP; 43 grup FG kembar di master produk DIRAPIKAN di Tahap 1 lewat laporan padanan yang ditinjau user. Mixing: trial product_type Mixing -> prefix MIX (mengalahkan aturan kategori). Lima trial beda FG: master yang benar (lihat 8.8), tampilkan FG dari master lewat product_id, JANGAN UPDATE trials_header. Tooling 1 BUKAN produk master id 188 (lihat 8.8): jangan ditautkan; tautan tooling ke produk dilakukan lewat Case setelah SKU barunya ada di master. Semua pertanyaan 8.7 sudah terjawab, tidak perlu ditanya ulang. QCO (penerbit PRNI) dan IM (penerbit QA) HANYA Tahap 3, jangan dibangun sekarang; di Tahap 1 keduanya tetap isian manual.

Kerjakan HANYA Tahap 1 (Fondasi Case + field baru + Product Journey minimal), sesuai bagian 6:
1. Migration additive, prefix vd_, semua dengan Schema::hasTable/hasColumn guard, tanpa FK, tanpa ALTER ke tabel bersama: vd_cases, vd_case_counters (baris dikunci, bukan MAX()+1), vd_case_links (kind, ref_id/ref_key, attempt_no, unique kind+ref), tabel samping untuk PM ready date, Bulk ready date, dan bukti approval client bila trials_header tidak boleh di-ALTER. Catatan temuan: trials_header SUDAH punya validation_category dan risk_level, jangan duplikasi; petakan saja.
2. Model, policy, generator nomor Case (VAL-{NPD|IMP|MIX}-{YYMM}-{urut 3 digit}, unique index), Trial Result turunan (Pass / Pass with Approval Client / Re-trial / Fail) dan definisi "satu kali trial" (disubmit, bukan Draft/terhapus). Hitung per Case, bukan per product_id (master produk punya FG kembar dan kode N/A).
3. Command backfill idempoten dengan --dry-run: tanam Case dari trial yang ada, default PM/Bulk ready date = H-1 dari tanggal pembuatan untuk trial lama. Baris yang ambigu ditampilkan ke user, tidak ditebak. Tautan Project <-> Trial historis lewat vd_case_links, JANGAN mengubah status stage dan jangan menautkan stage Completed.
4. Product Journey minimal (halaman per Case/produk: jumlah trial, hasil per percobaan, timeline dari data yang sudah ada) dan KPI Realized Trial Lead Time di Dashboard (hanya NPD). String id/en/ko lengkap.
5. Pest test, lalu gate lengkap (Pint, Larastan, php artisan test, ESLint, Prettier, tsc, npm run build) dan laporkan apa adanya termasuk kegagalan lama (ExportLocaleTest/ZipArchive, Larastan 2 error lama). Verifikasi live (Playwright) pada DB lokal SALINAN atau sqlite sementara, bukan DB bersama.
6. Ritual penutup PROJECT_MODULE_PLAN.md bagian 6: update TRIAL_VALIDATION_V2_PLAN.md bagian 8, CLAUDE.md (root dan new_trial_validation_app/), commit pesan English dengan stage file spesifik, lalu tulis prompt Tahap 2.

Batasan: jangan migrate:fresh/refresh/db:wipe, jangan migrate manual ke DB bersama (jalankan migration hanya pada DB salinan atau sqlite, dengan memastikan target DB lewat env override dan cek dulu), jangan --env= tanpa file .env.<nama> yang ada, jangan factory di tinker, jangan menyentuh ipc_app/ atau Project-Tracking-PIE-Team/. DB lokal saat ini hanya trial_validation_system_fase5 (salinan dev); buat salinan sendiri bila butuh DB migrasi.
```

### Prompt Tahap 0 (arsip, sudah dikerjakan 2026-11-25)

```
Kita mengerjakan Trial Validation v2.0 di new_trial_validation_app/. Baca dulu: TRIAL_VALIDATION_V2_PLAN.md (repo root, sumber kebenaran), PROJECT_MODULE_PLAN.md bagian 6-7, CLAUDE.md di root dan new_trial_validation_app/CLAUDE.md (insiden DB 2026-08-24, aturan prod additive-only), dan new_trial_validation_app/docs/DB Validation.xlsx tab TRIAL VALIDATION APPS.

Kerjakan HANYA Tahap 0 (penyelarasan Trial <-> Project Tracker), tanpa fitur baru dan tanpa migrasi:
1. Audit read-only terhadap DB lokal: trial tanpa product_id atau yang nama produknya tidak cocok dengan master products; pm_toolings tanpa produk/trial/project; pm_project_stages bertipe Validation tanpa trial_id; trial yang tidak terhubung ke project mana pun. Gunakan query/artisan command read-only (boleh command baru dengan --dry-run), jangan tinker dengan factory, jangan menulis ke DB bersama.
2. Susun laporan temuan (jumlah dan contoh nyata per kategori) dan usulan padanan untuk ditinjau saya. Jangan memperbaiki data diam-diam.
3. Bantu saya memutuskan D1-D9 di TRIAL_VALIDATION_V2_PLAN.md bagian 7, terutama K4 (stage Completed tidak bisa ditautkan).
4. Update TRIAL_VALIDATION_V2_PLAN.md bagian 8 dengan hasilnya, lalu tulis prompt Tahap 1 untuk chat baru.

Batasan: jangan migrate:fresh/refresh/db:wipe, jangan migrate manual ke DB bersama, jangan pakai factory di tinker, jangan menyentuh ipc_app/ atau Project-Tracking-PIE-Team/.
```
