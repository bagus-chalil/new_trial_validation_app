# AI-readiness (persiapan data untuk chatbot / AI)

Tujuan: data trial sudah berbentuk tetap, bersih, dan teranonimkan, jadi saat chatbot/AI masuk
tidak perlu mengolah data dari nol. Pola mengikuti `digipark-solution/agent-training-pack`
(kontrak berskema + set evaluasi + LLM hanya memberi draf).

## Aman untuk produksi
- Semua command **hanya membaca** tabel aplikasi. Satu-satunya tulisan: tabel baru `ai_data_flags`
  (tabel samping; tidak ada `ALTER` pada `trials_*`/`users`, legacy tidak terpengaruh).
- Migrasi `2026_10_14_000001_create_ai_data_flags_table` additive + guard `hasTable`. Tanpa migrasi pun
  `ai:export` dan `ai:quality-scan` (dry run) tetap jalan; hanya pemblokiran teks sampah yang belum aktif.
- Belum ada kode yang memanggil AI/LLM dan tidak ada route/UI baru.

## Command
| Command | Fungsi |
|---|---|
| `php artisan ai:quality-scan` | Laporan teks sampah/uji (dry run, tidak menulis apa pun) |
| `php artisan ai:quality-scan --apply` | Simpan temuan ke `ai_data_flags` (idempotent) |
| `php artisan ai:export [--only-final] [--include-suspected]` | JSONL + manifest (sha256) di `storage/app/private/ai-export/` |

## Kontrak
`resources/ai/trial_document.schema.json` (versi di `config/ai.php`). Satu dokumen per trial:
header, hasil parameter, statistik timbangan (n/mean/min/max/std + nilai), review, ringkasan lampiran,
Line Configuration, `quality`, dan `retrieval_text` (potongan teks untuk embedding/RAG).
Ubah bentuk dokumen = naikkan `schema_version` + perbarui skema + tes `AiReadinessTest`.

## Anonimisasi
- Orang (email/nama) -> `u_xxxxxxxxxxxx` (HMAC-SHA256 dengan `APP_KEY`, stabil antar export).
- Produk/batch tetap ada secara default (internal). Sebelum kirim ke penyedia model eksternal:
  `AI_ANONYMIZE_PRODUCT=true` dan/atau `AI_INCLUDE_BOM=false` di `.env`.
- Teks bebas (komentar, remark) TIDAK di-scrub; nama di dalam komentar masih terbawa.

## Langkah berikutnya (belum dikerjakan)
1. Terapkan migrasi di server (otomatis lewat CI `migrate --force`), lalu `ai:quality-scan --apply`.
2. `reason_code` terstandar untuk NOT OK / Need Revision (butuh perubahan form + master option).
3. Golden set evaluasi (pertanyaan + jawaban benar hasil kurasi manusia) untuk menguji chatbot.
4. Chatbot: tool read-only yang dibungkus `TrialPolicy` / `Trial::scopeVisibleTo()`.
