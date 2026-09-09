# CI/CD legacy app: GitHub → GitLab → Runner → server on-premise

Development tetap di GitHub (`bagus-chalil/new_trial_validation_app`, bukan
`Damorkiansah/trial_validation_app`). Alurnya:

```
push ke branch APA SAJA (GitHub)
  -> GitHub Action mirror-push SEMUA branch/tag ke GitLab.com (full journey)
     -> GitLab CI cuma benar-benar men-deploy kalau branch-nya
        `production` atau `development`
        -> jalan di GitLab Runner on-premise (tag onprem-legacy)
           -> rsync hasil checkout ke DEPLOY_PATH_* di server,
              Nginx+PHP-FPM serve dari sana
```

Dua environment di server yang sama:
- **`production`** branch → `DEPLOY_PATH_PRODUCTION`, di-serve production sungguhan.
- **`development`** branch → `DEPLOY_PATH_DEVELOPMENT`, buat testing, folder & vhost terpisah.

Branch lain (mis. `main`, `migrate-framework`, `feat/*`) tetap ikut ter-mirror
ke GitLab (jadi histori lengkap ada di sana), tapi **tidak** memicu job deploy
apa pun — `.gitlab-ci.yml` cuma punya `rules` untuk dua branch di atas.

Scope saat ini: **legacy PHP app (repo root) saja**. `new_trial_validation_app/`
dan `ipc_app/` belum masuk pipeline ini — nanti bisa ditambahkan sebagai stage
terpisah kalau sudah siap.

File terkait:
- `.github/workflows/mirror-to-gitlab.yml` — full mirror-push (semua branch/tag) ke GitLab, trigger: push ke branch apa pun.
- `.gitlab-ci.yml` — dua deploy job (`deploy_legacy_production`, `deploy_legacy_development`), jalan di runner dengan tag `onprem-legacy`, masing-masing hanya untuk branch-nya sendiri.
- `deploy/nginx-legacy.conf.example` — vhost Nginx production.
- `deploy/nginx-development.conf.example` — vhost Nginx development/test (port/domain terpisah).

> **Keputusan yang perlu kamu ambil:** environment `development` ini pakai
> database sendiri (staging DB, terpisah dari production) atau ikut ke
> database production yang sama? **Rekomendasi: pakai DB terpisah.** Repo ini
> sendiri pernah kena insiden nyata data production ke-wipe gara-gara
> operasi yang dikira aman (lihat catatan "Shared DB wiped 2026-08-24" di
> memory) — kalau `development` numpang ke DB production, testing di sana
> bisa ikut menulis/merusak data asli. Runbook di bawah asumsikan DB
> terpisah untuk `development`; kalau kamu tetap mau share DB yang sama,
> lewati saja bagian import-DB-terpisah di langkah 5.

## 1. Buat project di GitLab.com

1. Login ke gitlab.com, klik **New project → Create blank project**.
2. Nama bebas, misal `trial-validation-legacy`. **Jangan** centang "Initialize
   repository with a README" (biar histori tidak konflik saat di-push dari GitHub).
3. Visibility: **Private**.
4. Setelah dibuat, catat path project-nya, contoh: `gitlab.com/<namespace>/trial-validation-legacy`.

## 2. Buat token untuk push-mirror (dipakai GitHub Action)

1. Di project GitLab tadi → **Settings → Access Tokens**.
2. Buat token baru: role **Developer**, scope **write_repository**, expiry sesuai kebijakan kamu (misal 1 tahun, lalu diperbarui manual — GitLab tidak punya token yang benar-benar permanen).
3. Copy token-nya (hanya muncul sekali).

## 3. Set secret di GitHub

Di repo GitHub `bagus-chalil/new_trial_validation_app` → **Settings → Secrets and variables → Actions → New repository secret**, tambahkan:

| Name | Value |
|---|---|
| `GITLAB_PROJECT_URL` | `gitlab.com/<namespace>/trial-validation-legacy.git` (tanpa `https://`) |
| `GITLAB_TOKEN` | token dari langkah 2 |

Setelah ini ada, setiap push ke branch apa pun otomatis mem-mirror ke GitLab
(termasuk `production`/`development`, yang lalu memicu deploy).

## 4. Siapkan server on-premise (Ubuntu, sudah punya sudo access)

Jalankan sebagai user dengan sudo (bukan root langsung):

```bash
sudo apt update
sudo apt install -y nginx php8.3-fpm php8.3-mysql php8.3-mbstring \
  php8.3-xml php8.3-curl php8.3-gd php8.3-zip git rsync

sudo mkdir -p /var/www/trial_validation_app /var/www/trial_validation_app-development
sudo chown -R $USER:www-data /var/www/trial_validation_app /var/www/trial_validation_app-development
```

- `/var/www/trial_validation_app` → `DEPLOY_PATH_PRODUCTION` (langkah 7).
- `/var/www/trial_validation_app-development` → `DEPLOY_PATH_DEVELOPMENT` (langkah 7).

### Vhost Nginx (dua vhost, dua environment)

```bash
sudo cp deploy/nginx-legacy.conf.example /etc/nginx/sites-available/trial-validation-legacy.conf
sudo cp deploy/nginx-development.conf.example /etc/nginx/sites-available/trial-validation-development.conf
sudo nano /etc/nginx/sites-available/trial-validation-legacy.conf       # sesuaikan server_name & versi php-fpm
sudo nano /etc/nginx/sites-available/trial-validation-development.conf # sesuaikan server_name/port & versi php-fpm
sudo ln -s /etc/nginx/sites-available/trial-validation-legacy.conf /etc/nginx/sites-enabled/
sudo ln -s /etc/nginx/sites-available/trial-validation-development.conf /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

`nginx-development.conf.example` defaultnya listen di port `8080` supaya
tidak bentrok dengan production di port `80` pada server yang sama — ganti
kalau kamu lebih suka subdomain terpisah.

## 5. Seed folder yang tidak boleh ketimpa deploy otomatis

`.gitlab-ci.yml` sengaja **meng-exclude** file-file ini dari rsync setiap
deploy, supaya kredensial dan data runtime tidak pernah tertimpa oleh isi
git — berlaku untuk kedua environment:

- `config/database.php` — kredensial DB (beda dari default di git yang `127.0.0.1/root/no-password`)
- `config/sso.php` — URL new_trial_validation_app yang sebenarnya (bukan `localhost:8001`)
- `storage/sessions/` — session file runtime
- `public/uploads/` — file attachment trial

File-file ini **harus dibuat manual sekali per environment** sebelum deploy pertama:

```bash
# Production
sudo -u www-data mkdir -p /var/www/trial_validation_app/config
sudo -u www-data mkdir -p /var/www/trial_validation_app/storage/sessions
sudo -u www-data mkdir -p /var/www/trial_validation_app/public/uploads
sudo nano /var/www/trial_validation_app/config/database.php   # kredensial DB production
sudo nano /var/www/trial_validation_app/config/sso.php        # URL new_trial_validation_app production

# Development/test
sudo -u www-data mkdir -p /var/www/trial_validation_app-development/config
sudo -u www-data mkdir -p /var/www/trial_validation_app-development/storage/sessions
sudo -u www-data mkdir -p /var/www/trial_validation_app-development/public/uploads
sudo nano /var/www/trial_validation_app-development/config/database.php   # kredensial DB staging (lihat catatan di atas)
sudo nano /var/www/trial_validation_app-development/config/sso.php        # URL new_trial_validation_app staging/dev
```

Format sama seperti versi di repo (lihat `config/database.php` /
`config/sso.php`), tinggal ganti isinya.

Setelah folder-folder ini ada, deploy otomatis tidak akan pernah menimpa atau
menghapusnya (`rsync --exclude`).

Import database: ikuti langkah di `README.md` root repo ("Database setup" —
import `trial_validation_system.sql` lalu setiap file di `database/` sesuai
urutan nama file). Kalau `development` pakai DB terpisah (rekomendasi),
lakukan import ini dua kali dengan nama database berbeda, misal
`trial_validation_system` (production) dan `trial_validation_system_dev`
(development).

## 6. Install & register GitLab Runner

```bash
curl -L "https://packages.gitlab.com/install/repositories/runner/gitlab-runner/script.deb.sh" | sudo bash
sudo apt install -y gitlab-runner
```

Di GitLab project → **Settings → CI/CD → Runners → New project runner**:
- Tags: `onprem-legacy` (harus persis sama dengan tag di `.gitlab-ci.yml` — satu runner ini yang menjalankan kedua job, production maupun development, karena keduanya di server on-premise yang sama)
- Uncheck "Run untagged jobs" (biar runner ini cuma jalanin job yang memang ditag)
- Klik **Create runner**, copy authentication token yang muncul (`glrt-...`)

Register runner di server:

```bash
sudo gitlab-runner register \
  --non-interactive \
  --url "https://gitlab.com" \
  --token "glrt-XXXXXXXXXXXXXXXXXXXX" \
  --executor "shell" \
  --tag-list "onprem-legacy" \
  --description "onprem-legacy-runner"
```

Shell executor dipakai karena job deploy cuma perlu jalan sebagai user biasa
di server yang sama (bukan container terisolasi) — sesuai dengan cara kerja
`rsync` di `.gitlab-ci.yml`.

Runner jalan sebagai user `gitlab-runner` secara default. Pastikan user itu
bisa nulis ke kedua `DEPLOY_PATH_*`:

```bash
sudo usermod -aG www-data gitlab-runner
sudo chmod -R g+rw /var/www/trial_validation_app /var/www/trial_validation_app-development
```

## 7. Set CI/CD variable di GitLab

Di GitLab project → **Settings → CI/CD → Variables → Add variable**, tambahkan
dua variable (bukan satu — job production dan development masing-masing baca
variable-nya sendiri, lihat `.gitlab-ci.yml`):

| Key | Value | Protect | Mask |
|---|---|---|---|
| `DEPLOY_PATH_PRODUCTION` | `/var/www/trial_validation_app` | disarankan centang, kalau branch `production` sudah di-set Protected | tidak perlu (bukan rahasia) |
| `DEPLOY_PATH_DEVELOPMENT` | `/var/www/trial_validation_app-development` | tidak perlu | tidak perlu (bukan rahasia) |

Kalau branch `production` mau di-set sebagai **Protected branch** di GitLab
(Settings → Repository → Protected branches), centang juga "Protect" pada
`DEPLOY_PATH_PRODUCTION` supaya value production tidak ikut ke-expose ke job
branch lain.

## 8. Test end-to-end

**Development dulu (risiko lebih rendah):**
1. Push commit apa pun ke branch `development` di GitHub.
2. Cek tab **Actions** di GitHub → workflow "Mirror all branches to GitLab" harus hijau.
3. Cek **CI/CD → Pipelines** di project GitLab → pipeline harus jalan dan job `deploy_legacy_development` sukses (job `deploy_legacy_production` tidak muncul sama sekali untuk push ini, karena `rules`-nya membatasi per branch).
4. Cek server: `ls -la /var/www/trial_validation_app-development` harus ter-update.
5. Buka URL/port development di browser, pastikan halaman login legacy app muncul.

**Baru kalau sudah yakin, production:**
1. Push/merge ke branch `production` di GitHub.
2. Sama seperti di atas, tapi job yang jalan `deploy_legacy_production`, path yang ter-update `/var/www/trial_validation_app`.
3. Buka URL production, pastikan bisa connect ke DB production yang sebenarnya.

Branch lain (`main`, `feat/*`, dst.) akan tetap muncul di GitLab (repo/branch
list) tapi **tidak** memicu pipeline apa pun — cek tab CI/CD → Pipelines,
seharusnya kosong untuk push ke branch selain `production`/`development`.

## Opsional: reload PHP-FPM tiap deploy

Kalau `opcache.validate_timestamps` di-nonaktifkan di server (biasanya untuk
performa production), file PHP yang di-rsync tidak akan langsung kepakai
sampai PHP-FPM di-reload. Kalau perlu, tambahkan sudoers rule khusus (bukan
full sudo) untuk user `gitlab-runner`:

```
# /etc/sudoers.d/gitlab-runner-php-fpm
gitlab-runner ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.3-fpm
```

Lalu tambahkan baris ini di `script:` job `deploy_legacy` pada `.gitlab-ci.yml`:

```yaml
- sudo systemctl reload php8.3-fpm
```

Default `opcache.validate_timestamps=1` di kebanyakan instalasi PHP-FPM
sebenarnya sudah otomatis mendeteksi file berubah, jadi langkah ini hanya
perlu kalau server production sengaja mematikan itu untuk performa.

## Catatan keamanan

`config/database.php` dan `config/sso.php` **sudah ter-commit di git** dengan
nilai default lokal (`127.0.0.1/root/no-password`, `localhost:8001`). Ini
bukan hal baru dari setup CI/CD ini — sudah begitu dari awal — tapi sekarang
kedua file ini juga akan ada di histori GitLab. Untuk jangka panjang,
idealnya kredensial ini dipindah keluar dari git (mis. jadi
`config/database.php.example` + file asli di-gitignore, dibaca dari env var).
Belum dikerjakan di pass ini karena scope-nya cuma setup pipeline — tanyakan
kalau mau sekalian dibenahi.
