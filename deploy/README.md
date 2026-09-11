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

Scope: rsync (stage `deploy`) selalu menyalin **seluruh repo** — legacy,
`new_trial_validation_app/`, `ipc_app/`, `portal/` — ke `DEPLOY_PATH_*`, di
setiap push ke `production`/`development` (lihat bagian 9 di bawah, itu
sudah lama begini walau nama job-nya `deploy_legacy_*`). **Sejak
2026-09-11**, ada stage kedua, `build_apps`, yang otomatis menjalankan
`composer install`/`npm run build`/`artisan migrate`/`artisan storage:link`
untuk `new_trial_validation_app/`atau `ipc_app/` — tapi **hanya kalau ada
file berubah di folder app itu** (`rules: changes:`), supaya push yang cuma
menyentuh legacy tetap cepat dan tidak ikut nge-build 2 app lain. Sebelum ini,
langkah build Laravel-nya (`composer install`, `npm run build`,
`php artisan migrate`) untuk kedua app itu manual — lihat catatan di bagian 9
untuk detail keputusan dan risiko yang belum tentu ketahuan tanpa akses SSH
langsung ke server (kepemilikan file `vendor/`/`node_modules/`/dll yang
sebelumnya dibuat manual, mungkin bukan milik user `gitlab-runner`).

File terkait:
- `.github/workflows/mirror-to-gitlab.yml` — full mirror-push (semua branch/tag) ke GitLab, trigger: push ke branch apa pun.
- `.gitlab-ci.yml` — stage `deploy` (`deploy_legacy_production`/`deploy_legacy_development`, selalu jalan) lalu stage `build_apps` (`build_new_trial_validation_app_*`/`build_ipc_app_*`, jalan kondisional per app), semuanya di runner dengan tag `onprem-legacy`, masing-masing hanya untuk branch-nya sendiri.
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
sudo apt install -y nginx php8.5-fpm php8.3-mysql php8.3-mbstring \
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

**Satu IP untuk production + development, bisa?** Bisa, dan ini memang
setup default kedua file contoh di atas: satu server (satu IP,
`100.100.160.23` di catatan `ip a` kamu) menjalankan **dua vhost Nginx**
sekaligus, dibedakan lewat **port** (`listen 80` untuk production,
`listen 8080` untuk development) — bukan lewat `server_name`, yang sengaja
dibiarkan `_` (wildcard, cocok untuk request apa pun) karena kamu akses lewat
IP, bukan domain. Nginx sendiri bisa host banyak vhost di satu IP tanpa
masalah selama kombinasi `listen`-nya tidak identik. Yang perlu dipastikan:
- Kalau ada firewall (`ufw`, atau security group kalau ini VM cloud), port
  `8080` juga harus dibuka — cuma buka `80`/`443` saja tidak cukup untuk
  development.
- `fastcgi_pass` di kedua file arahkan ke socket PHP-FPM yang sama
  (`php8.5-fpm.sock` di contoh) — sesuaikan ke versi PHP yang benar-benar
  terpasang (`ls /run/php/` untuk lihat nama socket aktual).

## 5. Seed folder yang tidak boleh ketimpa deploy otomatis

`.gitlab-ci.yml` sengaja **meng-exclude** file-file ini dari rsync setiap
deploy, supaya kredensial dan data runtime tidak pernah tertimpa oleh isi
git — berlaku untuk kedua environment:

- `config/database.php` — kredensial DB (beda dari default di git yang `127.0.0.1/root/no-password`)
- `config/sso.php` — URL new_trial_validation_app yang sebenarnya (bukan `localhost:8001`)
- `storage/sessions/` — session file runtime
- `public/uploads/` — file attachment trial
- `portal/config.js` — URL production new_trial_validation_app & ipc_app yang sebenarnya (bukan `localhost:8001`/`localhost:8002`) — lihat bagian "Routing semua aplikasi" di bawah untuk isi lengkapnya

**⚠️ Insiden 2026-09-10 + fix:** rsync di atas awalnya cuma exclude path-path
di atas (semuanya di root repo, punya legacy), padahal command-nya sendiri
menyalin **seluruh isi repo** tanpa filter path (`"$CI_PROJECT_DIR/"
"$DEPLOY_PATH/"`) — jadi walau komentar di `.gitlab-ci.yml` bilang "scope
legacy saja", tiap deploy legacy tetap ikut menyentuh `new_trial_validation_app/`
dan `ipc_app/` di server, karena kedua app itu punya placeholder `.gitignore`
yang ter-*commit* di git (`storage/logs/.gitignore`, `bootstrap/cache/.gitignore`,
dst. — pola standar Laravel). Akibatnya: `--no-owner --no-group` bikin
direktori yang disentuh ulang oleh rsync balik jadi milik user CI
(`gitlab-runner`), bukan `www-data` (PHP-FPM jadi *permission denied* nulis
log/cache); dan `--delete-after` **menghapus** apa pun yang tidak ada di git
checkout sama sekali — termasuk `.env`, `vendor/`, `node_modules/`,
`public/build/` kedua app, karena semuanya di-gitignore. Ditemukan lewat
`new_trial_validation_app` di server development yang `.env`-nya hilang dan
`storage/logs/laravel.log` kena *permission denied* setelah deploy legacy,
padahal tidak ada perubahan apa pun ke `new_trial_validation_app/` itu
sendiri. **Fix:** exclude eksplisit ditambahkan di `.gitlab-ci.yml` untuk
`new_trial_validation_app/` dan `ipc_app/` — `.env`, `storage/`,
`bootstrap/cache/`, `vendor/`, `node_modules/`, `public/build/`, `public/hot`
per app, pola yang sama seperti proteksi punya legacy di atas. Source code
kedua app **tetap** ikut auto-sync seperti sebelumnya (tidak di-exclude
seluruh foldernya) — cuma runtime state-nya sekarang dilindungi.

**⚠️ Insiden lanjutan 2026-09-11:** fix di atas ternyata belum lengkap —
pipeline masih gagal (`exit status 23`) karena `--delete-after` mencoba
menghapus `new_trial_validation_app/resources/js/{actions,routes,wayfinder}/`
(file TypeScript hasil generate `php artisan wayfinder:generate`, di-gitignore
sama seperti `vendor/`/`node_modules/` sehingga tidak pernah ada di git
checkout CI) tapi gagal `Permission denied (13)` karena file-file itu di
server sudah dimiliki user lain (proses build/`www-data`, bukan
`gitlab-runner`). Persis kelas risiko yang sama seperti insiden 2026-09-10 di
atas — folder gitignored yang muncul lagi di server tapi belum masuk exclude
list. **Fix:** ditambahkan 3 exclude lagi untuk `new_trial_validation_app/`
(`resources/js/actions/`, `resources/js/routes/`, `resources/js/wayfinder/`)
plus versi yang sama untuk `ipc_app/` sekaligus (scaffold yang sama,
`laravel/react-starter-kit`, jadi berisiko sama begitu wayfinder mulai
generate file di sana juga) — meskipun `ipc_app/.gitignore` belum
menge-gitignore ketiga folder itu sama sekali (beda dari
`new_trial_validation_app/.gitignore`), jadi itu sendiri celah terpisah yang
masih perlu dibereskan di `ipc_app/` kalau/ketika wayfinder mulai jalan di
sana.

**Audit menyeluruh, sama hari (2026-09-11):** daripada nunggu insiden ketiga,
tiap baris `.gitignore` root/`new_trial_validation_app/`/`ipc_app/` dicek satu
per satu terhadap exclude list rsync. Temuan paling penting: **`public/storage`**
(symlink hasil `php artisan storage:link`, gitignored di kedua app) belum
ter-exclude sama sekali — dan `ipc_app` **memang benar-benar pakai**
`Storage::disk('public')` di semua controller foto (Startup/Filling/Packing/
Finished Check), jadi symlink ini nyata dipakai di production, bukan cuma
teori. Beda dari kasus wayfinder (bikin pipeline gagal dengan error jelas),
kalau rsync justru **berhasil** menghapus symlink ini (tergantung siapa yang
memilikinya di server), akibatnya foto-foto IPC berhenti tampil di production
**tanpa pipeline gagal sama sekali** — silent breakage yang lebih berbahaya.
Ditambahkan juga `bootstrap/ssr/` (gitignored di kedua app, belum dipakai tapi
defensif) dan beberapa file tunggal yang gitignored dan realistis bisa muncul
di server (`.phpunit.cache`, `.phpunit.result.cache`, `.env.backup`,
`.env.production`, `npm-debug.log` — semuanya bisa muncul kalau ada yang
pernah menjalankan test atau `npm install` gagal langsung di server). Yang
**sengaja tidak** ditambahkan karena mustahil muncul di server Linux headless
ini: `.DS_Store` (butuh macOS Finder), `.phpactor.json`/`Homestead.json`/
`.fleet`/`.idea`/`.nova`/`.vscode`/`.zed` (config IDE/Vagrant lokal), dan
`auth.json` (tidak ada private Composer repo di `composer.json` kedua app).

**Batasan audit ini:** dilakukan lewat analisis statis (`.gitignore` +
`composer.json`/`package.json` scripts), **bukan** dengan SSH langsung ke
server production/development — tidak ada akses SSH ke situ dari sesi
Claude Code ini. Kalau ada folder/file yang sebelumnya dibuat manual di server
dengan cara di luar langkah standar di dokumen ini, folder itu tetap berisiko
sampai ditambahkan ke exclude list secara eksplisit. Pertimbangkan
`--filter=':- .gitignore'` (per-directory merge, rsync otomatis baca
`.gitignore` tiap folder) sebagai jaring pengaman permanen di masa depan —
belum diterapkan sekarang karena mengubah mekanisme sync yang tidak bisa
diuji tanpa menjalankan pipeline sungguhan, dan repo ini punya riwayat
insiden dari perubahan yang tidak diuji dulu (lihat catatan insiden
"shared DB wiped 2026-08-24" di memory).

File-file ini **harus dibuat manual sekali per environment**, idealnya sebelum
deploy pertama — tapi kalau pipeline-nya sudah sempat jalan duluan (folder
`config/`/`storage/`/`public/` sudah ada, dimiliki user `gitlab-runner` dari
rsync), pakai `sudo mkdir -p` biasa (bukan `sudo -u www-data mkdir`) supaya
tidak kena *Permission denied* — root selalu boleh menulis ke folder siapa
pun, siapa pun pemiliknya sekarang. Lalu `chown` folder/file runtime-nya ke
`www-data` (user PHP-FPM) supaya app tetap bisa baca `config/*.php` dan
menulis ke `storage/sessions/` & `public/uploads/`:

```bash
# Production
sudo mkdir -p /var/www/trial_validation_app/config
sudo mkdir -p /var/www/trial_validation_app/storage/sessions
sudo mkdir -p /var/www/trial_validation_app/public/uploads
sudo chown -R www-data:www-data \
  /var/www/trial_validation_app/storage/sessions \
  /var/www/trial_validation_app/public/uploads
sudo nano /var/www/trial_validation_app/config/database.php   # kredensial DB production
sudo nano /var/www/trial_validation_app/config/sso.php        # URL new_trial_validation_app production
sudo chown www-data:www-data \
  /var/www/trial_validation_app/config/database.php \
  /var/www/trial_validation_app/config/sso.php

# Development/test
sudo mkdir -p /var/www/trial_validation_app-development/config
sudo mkdir -p /var/www/trial_validation_app-development/storage/sessions
sudo mkdir -p /var/www/trial_validation_app-development/public/uploads
sudo chown -R www-data:www-data \
  /var/www/trial_validation_app-development/storage/sessions \
  /var/www/trial_validation_app-development/public/uploads
sudo nano /var/www/trial_validation_app-development/config/database.php   # kredensial DB staging (lihat catatan di atas)
sudo nano /var/www/trial_validation_app-development/config/sso.php        # URL new_trial_validation_app staging/dev
sudo chown www-data:www-data \
  /var/www/trial_validation_app-development/config/database.php \
  /var/www/trial_validation_app-development/config/sso.php
```

Format sama seperti versi di repo (lihat `config/database.php` /
`config/sso.php`), tinggal ganti isinya.

Setelah folder-folder ini ada, deploy otomatis (`rsync --exclude`) tidak akan
pernah menimpa atau menghapus isinya — **urutan tidak masalah**: kalau
pipeline sudah jalan duluan sebelum langkah ini, data yang sudah ter-rsync
(source code) tetap aman, kamu cuma menambahkan folder yang di-exclude, bukan
mengganti apa pun yang sudah ada.

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
full sudo) untuk user `gitlab-runner`.

Dulu cek dulu nama service PHP-FPM yang benar-benar terpasang di server ini
(jangan asumsikan `php8.5-fpm` — versi bisa beda):

```bash
systemctl list-units --type=service | grep php
# atau
php -v
```

**Jangan paste isi file di bawah langsung ke prompt bash** — itu akan
dieksekusi sebagai command dan gagal dengan `syntax error near unexpected
token '('`. Pakai `visudo -f` (mengecek syntax otomatis sebelum menyimpan):

```bash
sudo visudo -f /etc/sudoers.d/gitlab-runner-php-fpm
```

lalu ketik/paste baris ini **di dalam editor yang terbuka** (ganti
`php8.5-fpm` sesuai hasil cek di atas), simpan, keluar:

```
gitlab-runner ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.5-fpm
```

Alternatif non-interaktif (kalau tidak mau pakai editor):

```bash
echo 'gitlab-runner ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.5-fpm' \
  | sudo tee /etc/sudoers.d/gitlab-runner-php-fpm
sudo chmod 0440 /etc/sudoers.d/gitlab-runner-php-fpm
sudo visudo -c   # verifikasi semua file sudoers.d masih valid
```

Lalu tambahkan baris ini di `script:` job `deploy_legacy` pada `.gitlab-ci.yml`:

```yaml
- sudo systemctl reload php8.5-fpm
```

Default `opcache.validate_timestamps=1` di kebanyakan instalasi PHP-FPM
sebenarnya sudah otomatis mendeteksi file berubah, jadi langkah ini hanya
perlu kalau server production sengaja mematikan itu untuk performa.

## 9. Portal, new_trial_validation_app, dan ipc_app di server yang sama

Repo ini monorepo — rsync di `.gitlab-ci.yml` menyalin **seluruh isi repo**
(`"$CI_PROJECT_DIR/" "$DEPLOY_PATH/"`, tanpa filter path), jadi folder
`portal/`, `new_trial_validation_app/`, dan `ipc_app/` **otomatis ikut ada**
di kedua `DEPLOY_PATH_PRODUCTION`/`DEPLOY_PATH_DEVELOPMENT` setiap kali
push ke `production`/`development`, apa pun yang berubah.

**Sejak 2026-09-11**, setup Laravel-nya (`composer install`,
`php artisan optimize:clear`, `npm ci`, `npm run build`,
`npx puppeteer browsers install ...` untuk Browsershot,
`php artisan storage:link`, `php artisan migrate --force`) untuk
`new_trial_validation_app`/`ipc_app` **sudah otomatis** lewat stage
`build_apps` di `.gitlab-ci.yml` — jalan kondisional per app (`rules:
changes:`), cuma kalau ada file berubah di folder app itu. **`.env` tetap
harus dibuat manual sekali di awal** (job `build_apps` sengaja `exit 1` kalau
`.env` app itu belum ada, bukan bikin otomatis — kredensial/APP_KEY tidak
boleh ke-generate ulang tanpa sengaja tiap deploy) — step-nya ada di komentar
masing-masing file `.conf.example` di bawah, sama seperti sebelumnya.

**⚠️ Insiden lanjutan 2026-09-11 (build_apps run pertama):** setelah job
`build_apps` pertama sukses untuk `new_trial_validation_app` di
`development`, `/login` tetap 500 — bukan masalah baru, tapi cache Laravel
basi (`bootstrap/cache/services.php`/`packages.php`/`config.php`) yang
kemungkinan ditulis oleh `php artisan optimize`/`config:cache` manual jauh
sebelum stage otomatis ini ada, dari kondisi `vendor/`/`composer.lock` yang
sudah lama tidak sinkron dengan yang sekarang. Setelah `composer install`
meng-update `vendor/` ke lock file terbaru, cache lama itu jadi merujuk ke
daftar package/service-provider yang tidak lagi cocok, dan Laravel membaca
cache itu dulu daripada state kode yang sebenarnya sekarang → fatal error di
setiap request. Hilang setelah `php artisan optimize:clear` dijalankan
manual satu kali. **Fix permanen:** `php artisan optimize:clear` ditambahkan
ke `.build_app` di `.gitlab-ci.yml`, dijalankan tiap kali setelah
`composer install` dan sebelum `npm ci`/`migrate` — jadi setiap deploy mulai
dari cache bersih, tidak tergantung riwayat manual apa pun yang pernah
terjadi di server sebelum otomasi ini ada.

**⚠️ Risiko ini terkonfirmasi nyata, 2026-09-11:** awalnya ditulis lewat audit
statis (`.gitignore` + `composer.json`/`package.json`), **tanpa** akses SSH
ke server production/development untuk mengecek langsung — dan memang benar
terjadi persis seperti diprediksi. Job `build_new_trial_validation_app_development`
gagal di `npm ci` dengan `EACCES: permission denied` saat mencoba menghapus
`new_trial_validation_app/node_modules/.cache/laravel-vite-plugin` — folder itu
di server dimiliki user lain (bukan `gitlab-runner`), kemungkinan bekas
`npm install`/`npm run build` manual lewat SSH sebelum stage `build_apps`
otomatis ini ada. Kalau `vendor/`/`node_modules/`/`public/build/`/`bootstrap/cache/`/
`resources/js/{actions,routes,wayfinder}` di server dimiliki user lain,
job `build_apps` pertama kali jalan kemungkinan gagal `Permission denied` juga —
sama kelas masalah dengan insiden rsync yang diperbaiki hari yang sama (lihat
bagian 5). **Kalau itu terjadi**, jalankan sekali per app/environment via SSH:
```bash
sudo chown -R gitlab-runner:gitlab-runner \
  $DEPLOY_PATH/new_trial_validation_app/{vendor,node_modules,bootstrap/cache,bootstrap/ssr,storage,public/build,resources/js/actions,resources/js/routes,resources/js/wayfinder} \
  2>/dev/null
# ganti path & nama app untuk ipc_app juga
```
(pakai `2>/dev/null` karena tidak semua folder itu mesti sudah ada). Setelah
kepemilikan disamakan ke `gitlab-runner` sekali, deploy berikutnya seharusnya
tidak kena masalah ini lagi — rekomendasi: coba dulu di `development`,
pantau job `build_new_trial_validation_app_development`/
`build_ipc_app_development` di GitLab, baru percaya untuk `production`.

Semua 4 hal (legacy, portal, new_trial_validation_app, ipc_app) jalan di
**satu server, satu IP**, dibedakan lewat **port** (bukan domain, karena
belum ada domain publik — lihat pembahasan IP-vs-domain di histori chat).
Setiap aplikasi juga punya versi production dan development terpisah,
mengikuti pola `DEPLOY_PATH_PRODUCTION`/`DEPLOY_PATH_DEVELOPMENT` legacy:

| Aplikasi | Prod HTTP→HTTPS | Prod HTTPS | Dev HTTP→HTTPS | Dev HTTPS |
|---|---|---|---|---|
| Legacy | 80 | **443** | 8080 | **8443** |
| Portal (pintu masuk, pilih new app / ipc) | 9000 | **9001** | 9002 | **9003** |
| new_trial_validation_app | 9010 | **9011** | 9012 | **9013** |
| ipc_app | 9020 | **9021** | 9022 | **9023** |

Alur pemakaian: user buka portal (`:9001` prod / `:9003` dev) → pilih salah
satu tombol → masuk ke new_trial_validation_app (`:9011`/`:9013`) atau
ipc_app (`:9021`/`:9023`). Dari new_trial_validation_app, tombol "Aplikasi
Lama" di sidebar balik ke legacy **environment yang sama** (prod balik ke
prod `:443`, dev balik ke dev `:8443`) lewat SSO bridge — `ipc_app` tidak
ada SSO/tombol balik sama sekali, app-nya independen (lihat CLAUDE.md).

File vhost contoh (pola sama seperti legacy: HTTP redirect + HTTPS
self-signed, lihat komentar di `nginx-legacy.conf.example` untuk penjelasan
kenapa self-signed bukan Let's Encrypt):

- `deploy/nginx-portal.conf.example` / `nginx-portal-development.conf.example`
- `deploy/nginx-new-app.conf.example` / `nginx-new-app-development.conf.example`
- `deploy/nginx-ipc-app.conf.example` / `nginx-ipc-app-development.conf.example`

Cara pakai tiap file: generate self-signed cert-nya sendiri-sendiri (nama
file cert beda per file, sudah dicontohkan di komentar masing-masing),
copy ke `sites-available`, `ln -s` ke `sites-enabled`, `nginx -t && systemctl
reload nginx` — persis pola langkah 4 di atas.

### Isi file yang di-exclude dari rsync (langkah 5), versi lengkap dengan port

**`portal/config.js`** — buat manual di kedua path:

```bash
# Production: /var/www/trial_validation_app/portal/config.js
sudo -u www-data tee /var/www/trial_validation_app/portal/config.js > /dev/null <<'EOF'
window.PORTAL_CONFIG = {
  trialValidationUrl: "https://100.100.160.23:9011",
  ipcUrl: "https://100.100.160.23:9021",
};
EOF

# Development: /var/www/trial_validation_app-development/portal/config.js
sudo -u www-data tee /var/www/trial_validation_app-development/portal/config.js > /dev/null <<'EOF'
window.PORTAL_CONFIG = {
  trialValidationUrl: "https://100.100.160.23:9013",
  ipcUrl: "https://100.100.160.23:9023",
};
EOF
```

**`config/sso.php`** (legacy) — isi `new_app_url` sesuai environment:

```php
<?php
// Production: /var/www/trial_validation_app/config/sso.php
return [
  'new_app_url' => 'https://100.100.160.23:9011'
];
```

```php
<?php
// Development: /var/www/trial_validation_app-development/config/sso.php
return [
  'new_app_url' => 'https://100.100.160.23:9013'
];
```

**`new_trial_validation_app/.env`** (dibuat manual saat setup Laravel-nya,
lihat komentar di `nginx-new-app.conf.example`) — `OLD_APP_URL` mengarah ke
legacy **environment yang sama**, jangan silang (dev new-app jangan
mengarah ke legacy production):

| Environment | `APP_URL` | `OLD_APP_URL` |
|---|---|---|
| Production | `https://100.100.160.23:9011` | `https://100.100.160.23` |
| Development | `https://100.100.160.23:9013` | `https://100.100.160.23:8443` |

## Catatan keamanan

`config/database.php` dan `config/sso.php` **sudah ter-commit di git** dengan
nilai default lokal (`127.0.0.1/root/no-password`, `localhost:8001`). Ini
bukan hal baru dari setup CI/CD ini — sudah begitu dari awal — tapi sekarang
kedua file ini juga akan ada di histori GitLab. Untuk jangka panjang,
idealnya kredensial ini dipindah keluar dari git (mis. jadi
`config/database.php.example` + file asli di-gitignore, dibaca dari env var).
Belum dikerjakan di pass ini karena scope-nya cuma setup pipeline — tanyakan
kalau mau sekalian dibenahi.
