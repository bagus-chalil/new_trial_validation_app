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
| D6 | Format nomor IM | **Penerbit: QA (ditetapkan 2026-11-25), Tahap 3.** Format dan alur approval belum ada; isian manual sampai Tahap 3 |
| D7 | Format nomor QCO, integrasi QCO elektronik (TBD di Excel) | **Penerbit: PRNI (ditetapkan 2026-11-25), Tahap 3.** Format belum ada; isian manual sampai Tahap 3 |
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

## 9. Prompt siap pakai

### Prompt Tahap 1 (tempel di chat baru)

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
