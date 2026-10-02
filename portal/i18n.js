// Portal translations (Indonesian, English, Korean) shared by index.html and
// sertifikat.html. Elements opt in with data-i18n="key"; the value is set as
// innerHTML, so strings may carry <b>/<span> markup (all strings live here,
// never user input). The chosen language is remembered per browser.
(function () {
  'use strict';

  var LANGS = [
    { code: 'id', label: 'ID', name: 'Bahasa Indonesia' },
    { code: 'en', label: 'EN', name: 'English' },
    { code: 'ko', label: '한국어', name: '한국어' },
  ];
  var STORAGE_KEY = 'qac-portal-lang';

  var STRINGS = {
    id: {
      // index.html
      'portal.title': 'QAC Super Apps',
      'portal.heading': 'Portal Terpadu Sistem <span>QAC</span>',
      'portal.subtitle': 'Silakan pilih aplikasi yang akan digunakan. Setiap sistem menggunakan akun dan proses masuk masing-masing.',
      'setup.title': 'Sertifikat keamanan QAC belum terpasang pada perangkat ini',
      'setup.text': 'Pemasangan cukup dilakukan satu kali agar peringatan "Tidak aman" tidak lagi muncul, kamera dapat digunakan, dan portal dapat diinstal sebagai aplikasi.',
      'setup.auto': 'Pemasangan Otomatis',
      'setup.guide': 'Panduan Pemasangan',
      'setup.downloaded': 'Silakan buka berkas <b>setup-sertifikat-qac.cmd</b> yang telah diunduh. Apabila muncul peringatan, pilih <b>Keep</b> pada browser dan <b>More info &rarr; Run anyway</b> pada Windows, kemudian klik <b>Yes</b>.',
      'install.title': 'Instal QAC Super Apps',
      'install.text': 'Akses portal secara langsung melalui ikon pada desktop atau layar utama perangkat Anda.',
      'install.ios': 'Pada Safari, ketuk tombol <b>Share</b>, kemudian pilih <b>Add to Home Screen</b>.',
      'install.button': 'Instal Aplikasi',
      'card.status': 'Aktif',
      'card.open': 'Buka Aplikasi',
      'tv.desc': 'Pengelolaan trial produksi secara menyeluruh, mulai dari pengajuan dan peninjauan lintas departemen hingga persetujuan dan pelaporan.',
      'tv.f1': 'Formulir trial, validasi parameter, dan penimbangan',
      'tv.f2': 'Peninjauan bertahap oleh setiap departemen',
      'tv.f3': 'Persetujuan, pelaporan, dan riwayat aktivitas',
      'ipc.desc': 'In Process Control &mdash; inspeksi mutu selama proses produksi, mulai dari pemeriksaan awal hingga produk jadi.',
      'ipc.f1': 'Startup Check, Filling Check, dan Packing Check',
      'ipc.f2': 'Inspeksi Finished Good dan persetujuan',
      'ipc.f3': 'Pencetakan hasil inspeksi per batch',
      'footer.text': 'QAC Super Apps &mdash; Portal Internal Quality Assurance &amp; Compliance',
      'footer.cert': 'Muncul peringatan "Tidak aman"? Pasang sertifikat QAC',

      // sertifikat.html
      'cert.title': 'Pemasangan Sertifikat QAC',
      'cert.back': '&larr; Kembali ke Portal',
      'cert.heading': 'Pemasangan Sertifikat QAC',
      'cert.lead': 'Pemasangan cukup dilakukan satu kali untuk setiap perangkat. Setelah terpasang, peringatan "Tidak aman" tidak lagi muncul pada seluruh aplikasi QAC, kamera (OCR IPC) dapat digunakan, dan portal dapat diinstal sebagai aplikasi.',
      'cert.statusOk': '&#10003; Sertifikat telah terpasang pada perangkat ini. Silakan kembali ke portal untuk menginstal aplikasi.',
      'cert.statusMissing': 'Perangkat ini belum memercayai sertifikat QAC. Silakan ikuti langkah-langkah di bawah ini.',
      'cert.win.download': 'Unduh Pemasangan Otomatis',
      'cert.win.s1': 'Klik tombol di atas. Apabila browser meminta konfirmasi, pilih <b>Keep</b> / <b>Simpan</b>.',
      'cert.win.s2': 'Buka berkas <b>setup-sertifikat-qac.cmd</b>. Apabila muncul pesan "Windows protected your PC", klik <b>More info &rarr; Run anyway</b>.',
      'cert.win.s3': 'Klik <b>Yes</b> pada peringatan keamanan Windows.',
      'cert.win.s4': 'Tutup <b>seluruh</b> jendela Chrome / Edge, kemudian tekan tombol apa saja pada jendela pemasangan. Portal akan terbuka kembali dengan ikon gembok normal.',
      'cert.win.s5': 'Klik <b>Instal Aplikasi</b> pada portal.',
      'cert.win.hint': 'Tidak memerlukan hak akses administrator. Sertifikat hanya dipasang untuk akun Windows Anda.',
      'cert.win.manual': 'Pemasangan manual (apabila berkas pemasangan diblokir)',
      'cert.win.m1': '<a href="qac-root-ca.crt" download>Unduh qac-root-ca.crt</a>, buka berkas tersebut, kemudian klik <b>Install Certificate</b>.',
      'cert.win.m2': 'Pilih <b>Current User</b> &rarr; Next.',
      'cert.win.m3': 'Pilih <b>Place all certificates in the following store</b> &rarr; Browse &rarr; <b>Trusted Root Certification Authorities</b>. <b>Jangan</b> memilih "Automatically select the certificate store", karena sertifikat akan tersimpan di lokasi yang salah dan peringatan tidak akan hilang.',
      'cert.win.m4': 'Finish &rarr; klik <b>Yes</b> pada peringatan keamanan, kemudian tutup dan buka kembali browser.',
      'cert.download': 'Unduh Sertifikat',
      'cert.step1': '1. Pasang sertifikat',
      'cert.and.step2': '2. Instal aplikasi',
      'cert.and.s1': 'Ketuk tombol di atas. Berkas <b>qac-root-ca.crt</b> akan tersimpan di folder Downloads.',
      'cert.and.s2': 'Buka <b>Settings</b>, lalu cari <b>"CA certificate"</b> pada kolom pencarian. Alternatifnya melalui <b>Security &rarr; More security settings &rarr; Encryption &amp; credentials &rarr; Install a certificate &rarr; CA certificate</b>. Nama menu dapat sedikit berbeda pada setiap merek perangkat.',
      'cert.and.s3': 'Ketuk <b>Install anyway</b>, kemudian pilih berkas <b>qac-root-ca.crt</b> dari folder Downloads.',
      'cert.and.s4': 'Apabila diminta, buat PIN / pola kunci layar terlebih dahulu. Hal ini diwajibkan oleh Android.',
      'cert.and.i1': 'Tutup Chrome sepenuhnya (geser dari daftar aplikasi yang terbuka), kemudian buka kembali portal.',
      'cert.and.i2': 'Pastikan halaman ini menampilkan "Sertifikat telah terpasang".',
      'cert.and.i3': 'Pada portal, ketuk <b>Instal Aplikasi</b>. Ikon QAC akan muncul di layar utama.',
      'cert.and.hint': 'Membuka berkas .crt langsung dari notifikasi unduhan tidak dapat dilakukan pada Android 11 ke atas. Pemasangan harus dilakukan melalui menu Settings seperti di atas.',
      'cert.ios.step2': '2. Tambahkan ke layar utama',
      'cert.ios.s1': 'Buka halaman ini melalui <b>Safari</b> (bukan Chrome), kemudian ketuk tombol di atas. Pilih <b>Allow</b>, lalu <b>Close</b>.',
      'cert.ios.s2': 'Buka <b>Settings</b>. Pada bagian atas akan muncul <b>Profile Downloaded</b>; ketuk, lalu pilih <b>Install</b> (masukkan passcode) &rarr; <b>Install</b> &rarr; <b>Done</b>. Apabila tidak muncul, buka <b>General &rarr; VPN &amp; Device Management</b>.',
      'cert.ios.s3': '<b>Wajib dan sering terlewat:</b> buka <b>Settings &rarr; General &rarr; About &rarr; Certificate Trust Settings</b>, aktifkan <b>QAC Internal Root CA</b> &rarr; Continue.',
      'cert.ios.i1': 'Tutup dan buka kembali Safari, kemudian buka portal.',
      'cert.ios.i2': 'Ketuk tombol <b>Share</b> (kotak dengan panah ke atas) &rarr; <b>Add to Home Screen</b> &rarr; <b>Add</b>.',
      'cert.ios.hint': 'iPad tidak memiliki tombol instal otomatis; langkah melalui tombol Share di atas merupakan satu-satunya cara.',
      'cert.note': 'Sertifikat ini hanya berlaku untuk server internal QAC. Apabila mengalami kendala dalam pemasangan, silakan hubungi tim IT.',

      // setup.js
      'err.notAvailable': 'Sertifikat belum tersedia di server (HTTP {status}).',
      'err.invalid': 'Berkas sertifikat di server tidak valid.',
      'cmd.title': 'Pemasangan Sertifikat QAC',
      'cmd.installing': 'Memasang sertifikat QAC ke Trusted Root (akun Windows Anda)...',
      'cmd.clickYes': 'Klik YES pada jendela peringatan keamanan Windows yang muncul.',
      'cmd.failed': 'GAGAL memasang sertifikat. Jalankan ulang berkas ini dan klik YES.',
      'cmd.success': 'Berhasil. Sertifikat QAC telah terpasang.',
      'cmd.closeBrowsers': 'Silakan TUTUP SELURUH jendela Chrome / Edge,',
      'cmd.pressKey': 'kemudian tekan tombol apa saja di sini untuk membuka kembali portal.',
    },

    en: {
      'portal.title': 'QAC Super Apps',
      'portal.heading': 'Integrated Portal for <span>QAC</span> Systems',
      'portal.subtitle': 'Please select the application you wish to access. Each system has its own account and sign-in.',
      'setup.title': 'The QAC security certificate is not installed on this device',
      'setup.text': 'A one-time installation removes the "Not secure" warning, enables camera access, and allows the portal to be installed as an application.',
      'setup.auto': 'Automatic Setup',
      'setup.guide': 'Installation Guide',
      'setup.downloaded': 'Please open the downloaded file <b>setup-sertifikat-qac.cmd</b>. If a warning appears, select <b>Keep</b> in the browser and <b>More info &rarr; Run anyway</b> in Windows, then click <b>Yes</b>.',
      'install.title': 'Install QAC Super Apps',
      'install.text': 'Access the portal directly from an icon on your desktop or home screen.',
      'install.ios': 'In Safari, tap the <b>Share</b> button, then select <b>Add to Home Screen</b>.',
      'install.button': 'Install Application',
      'card.status': 'Active',
      'card.open': 'Open Application',
      'tv.desc': 'End-to-end management of production trials, from submission and cross-department review to approval and reporting.',
      'tv.f1': 'Trial forms, parameter validation, and weighing',
      'tv.f2': 'Multi-stage review by each department',
      'tv.f3': 'Approval, reporting, and activity history',
      'ipc.desc': 'In Process Control &mdash; quality inspection throughout production, from startup check to finished goods.',
      'ipc.f1': 'Startup, Filling, and Packing Checks',
      'ipc.f2': 'Finished Good inspection and approval',
      'ipc.f3': 'Printable inspection results per batch',
      'footer.text': 'QAC Super Apps &mdash; Internal Quality Assurance &amp; Compliance Portal',
      'footer.cert': 'Seeing a "Not secure" warning? Install the QAC certificate',

      'cert.title': 'QAC Certificate Installation',
      'cert.back': '&larr; Back to Portal',
      'cert.heading': 'QAC Certificate Installation',
      'cert.lead': 'Installation is required only once per device. Once installed, the "Not secure" warning no longer appears in any QAC application, the camera (IPC OCR) can be used, and the portal can be installed as an application.',
      'cert.statusOk': '&#10003; The certificate is installed on this device. Please return to the portal to install the application.',
      'cert.statusMissing': 'This device does not yet trust the QAC certificate. Please follow the steps below.',
      'cert.win.download': 'Download Automatic Setup',
      'cert.win.s1': 'Click the button above. If the browser asks for confirmation, select <b>Keep</b>.',
      'cert.win.s2': 'Open <b>setup-sertifikat-qac.cmd</b>. If "Windows protected your PC" appears, click <b>More info &rarr; Run anyway</b>.',
      'cert.win.s3': 'Click <b>Yes</b> on the Windows security warning.',
      'cert.win.s4': 'Close <b>all</b> Chrome / Edge windows, then press any key in the setup window. The portal will reopen with the normal padlock icon.',
      'cert.win.s5': 'Click <b>Install Application</b> on the portal.',
      'cert.win.hint': 'No administrator rights are required. The certificate is installed for your Windows account only.',
      'cert.win.manual': 'Manual installation (if the setup file is blocked)',
      'cert.win.m1': '<a href="qac-root-ca.crt" download>Download qac-root-ca.crt</a>, open the file, then click <b>Install Certificate</b>.',
      'cert.win.m2': 'Select <b>Current User</b> &rarr; Next.',
      'cert.win.m3': 'Select <b>Place all certificates in the following store</b> &rarr; Browse &rarr; <b>Trusted Root Certification Authorities</b>. <b>Do not</b> select "Automatically select the certificate store", as the certificate would be placed in the wrong store and the warning would remain.',
      'cert.win.m4': 'Finish &rarr; click <b>Yes</b> on the security warning, then close and reopen the browser.',
      'cert.download': 'Download Certificate',
      'cert.step1': '1. Install the certificate',
      'cert.and.step2': '2. Install the application',
      'cert.and.s1': 'Tap the button above. The file <b>qac-root-ca.crt</b> will be saved to Downloads.',
      'cert.and.s2': 'Open <b>Settings</b> and search for <b>"CA certificate"</b>. Alternatively, go to <b>Security &rarr; More security settings &rarr; Encryption &amp; credentials &rarr; Install a certificate &rarr; CA certificate</b>. Menu names may vary slightly by device manufacturer.',
      'cert.and.s3': 'Tap <b>Install anyway</b>, then select <b>qac-root-ca.crt</b> from Downloads.',
      'cert.and.s4': 'If prompted, set up a screen lock PIN or pattern first. This is required by Android.',
      'cert.and.i1': 'Close Chrome completely (swipe it away from recent apps), then reopen the portal.',
      'cert.and.i2': 'Make sure this page shows "The certificate is installed".',
      'cert.and.i3': 'On the portal, tap <b>Install Application</b>. The QAC icon will appear on your home screen.',
      'cert.and.hint': 'Opening the .crt file directly from the download notification does not work on Android 11 and later. Installation must be done through Settings as described above.',
      'cert.ios.step2': '2. Add to Home Screen',
      'cert.ios.s1': 'Open this page in <b>Safari</b> (not Chrome), then tap the button above. Select <b>Allow</b>, then <b>Close</b>.',
      'cert.ios.s2': 'Open <b>Settings</b>. <b>Profile Downloaded</b> appears at the top; tap it, then <b>Install</b> (enter your passcode) &rarr; <b>Install</b> &rarr; <b>Done</b>. If it does not appear, go to <b>General &rarr; VPN &amp; Device Management</b>.',
      'cert.ios.s3': '<b>Required and often missed:</b> go to <b>Settings &rarr; General &rarr; About &rarr; Certificate Trust Settings</b> and enable <b>QAC Internal Root CA</b> &rarr; Continue.',
      'cert.ios.i1': 'Close and reopen Safari, then open the portal.',
      'cert.ios.i2': 'Tap the <b>Share</b> button (square with an upward arrow) &rarr; <b>Add to Home Screen</b> &rarr; <b>Add</b>.',
      'cert.ios.hint': 'iPad has no automatic install button; the Share step above is the only method.',
      'cert.note': 'This certificate applies only to QAC internal servers. If you encounter any issues during installation, please contact the IT team.',

      'err.notAvailable': 'The certificate is not yet available on the server (HTTP {status}).',
      'err.invalid': 'The certificate file on the server is invalid.',
      'cmd.title': 'QAC Certificate Setup',
      'cmd.installing': 'Installing the QAC certificate to Trusted Root (your Windows account)...',
      'cmd.clickYes': 'Click YES on the Windows security warning that appears.',
      'cmd.failed': 'FAILED to install the certificate. Run this file again and click YES.',
      'cmd.success': 'Success. The QAC certificate has been installed.',
      'cmd.closeBrowsers': 'Please CLOSE ALL Chrome / Edge windows,',
      'cmd.pressKey': 'then press any key here to reopen the portal.',
    },

    ko: {
      'portal.title': 'QAC Super Apps',
      'portal.heading': '<span>QAC</span> 통합 시스템 포털',
      'portal.subtitle': '이용하실 애플리케이션을 선택해 주십시오. 각 시스템은 별도의 계정으로 로그인합니다.',
      'setup.title': '이 기기에 QAC 보안 인증서가 설치되어 있지 않습니다',
      'setup.text': '최초 1회 설치하시면 "주의 요함" 경고가 더 이상 표시되지 않으며, 카메라를 사용하고 포털을 앱으로 설치하실 수 있습니다.',
      'setup.auto': '자동 설치',
      'setup.guide': '설치 안내',
      'setup.downloaded': '다운로드된 <b>setup-sertifikat-qac.cmd</b> 파일을 실행해 주십시오. 경고가 표시되면 브라우저에서 <b>유지(Keep)</b>를, Windows에서 <b>추가 정보 &rarr; 실행(Run anyway)</b>을 선택한 후 <b>예(Yes)</b>를 클릭해 주십시오.',
      'install.title': 'QAC Super Apps 설치',
      'install.text': '바탕 화면 또는 홈 화면의 아이콘으로 포털에 바로 접속하실 수 있습니다.',
      'install.ios': 'Safari에서 <b>공유</b> 버튼을 누른 후 <b>홈 화면에 추가</b>를 선택해 주십시오.',
      'install.button': '앱 설치',
      'card.status': '운영 중',
      'card.open': '애플리케이션 열기',
      'tv.desc': '생산 트라이얼의 신청부터 부서 간 검토, 승인 및 보고까지 전 과정을 관리합니다.',
      'tv.f1': '트라이얼 양식, 파라미터 검증 및 계량',
      'tv.f2': '부서별 단계적 검토',
      'tv.f3': '승인, 보고서 및 활동 이력',
      'ipc.desc': 'In Process Control &mdash; 스타트업 점검부터 완제품까지 생산 공정 전반의 품질 검사를 수행합니다.',
      'ipc.f1': '스타트업 점검, 충전 검사 및 포장 검사',
      'ipc.f2': '완제품 검사 및 승인',
      'ipc.f3': '배치별 검사 결과 출력',
      'footer.text': 'QAC Super Apps &mdash; 품질보증 및 컴플라이언스 내부 포털',
      'footer.cert': '"주의 요함" 경고가 표시됩니까? QAC 인증서 설치하기',

      'cert.title': 'QAC 인증서 설치',
      'cert.back': '&larr; 포털로 돌아가기',
      'cert.heading': 'QAC 인증서 설치',
      'cert.lead': '기기당 최초 1회만 설치하시면 됩니다. 설치 후에는 모든 QAC 애플리케이션에서 "주의 요함" 경고가 표시되지 않으며, 카메라(IPC OCR)를 사용하고 포털을 앱으로 설치하실 수 있습니다.',
      'cert.statusOk': '&#10003; 이 기기에 인증서가 설치되어 있습니다. 포털로 돌아가 앱을 설치해 주십시오.',
      'cert.statusMissing': '이 기기는 아직 QAC 인증서를 신뢰하지 않습니다. 아래 절차를 따라 주십시오.',
      'cert.win.download': '자동 설치 파일 다운로드',
      'cert.win.s1': '위 버튼을 클릭해 주십시오. 브라우저에서 확인을 요청하면 <b>유지(Keep)</b>를 선택해 주십시오.',
      'cert.win.s2': '<b>setup-sertifikat-qac.cmd</b> 파일을 실행해 주십시오. "Windows의 PC 보호" 창이 표시되면 <b>추가 정보 &rarr; 실행</b>을 클릭해 주십시오.',
      'cert.win.s3': 'Windows 보안 경고에서 <b>예(Yes)</b>를 클릭해 주십시오.',
      'cert.win.s4': 'Chrome / Edge 창을 <b>모두</b> 닫은 후 설치 창에서 아무 키나 눌러 주십시오. 포털이 정상적인 자물쇠 아이콘과 함께 다시 열립니다.',
      'cert.win.s5': '포털에서 <b>앱 설치</b>를 클릭해 주십시오.',
      'cert.win.hint': '관리자 권한이 필요하지 않습니다. 인증서는 사용자의 Windows 계정에만 설치됩니다.',
      'cert.win.manual': '수동 설치 (설치 파일이 차단된 경우)',
      'cert.win.m1': '<a href="qac-root-ca.crt" download>qac-root-ca.crt 다운로드</a> 후 파일을 열고 <b>인증서 설치(Install Certificate)</b>를 클릭해 주십시오.',
      'cert.win.m2': '<b>현재 사용자(Current User)</b> &rarr; 다음을 선택해 주십시오.',
      'cert.win.m3': '<b>모든 인증서를 다음 저장소에 저장(Place all certificates in the following store)</b> &rarr; 찾아보기 &rarr; <b>신뢰할 수 있는 루트 인증 기관(Trusted Root Certification Authorities)</b>을 선택해 주십시오. "인증서 종류 기반으로 자동으로 인증서 저장소 선택"은 <b>선택하지 마십시오</b>. 인증서가 잘못된 저장소에 저장되어 경고가 사라지지 않습니다.',
      'cert.win.m4': '마침 &rarr; 보안 경고에서 <b>예(Yes)</b>를 클릭한 후 브라우저를 닫았다가 다시 열어 주십시오.',
      'cert.download': '인증서 다운로드',
      'cert.step1': '1. 인증서 설치',
      'cert.and.step2': '2. 앱 설치',
      'cert.and.s1': '위 버튼을 눌러 주십시오. <b>qac-root-ca.crt</b> 파일이 다운로드 폴더에 저장됩니다.',
      'cert.and.s2': '<b>설정</b>을 열고 검색창에서 <b>"CA 인증서"</b>를 검색해 주십시오. 또는 <b>보안 &rarr; 기타 보안 설정 &rarr; 암호화 및 사용자 인증 정보 &rarr; 인증서 설치 &rarr; CA 인증서</b> 경로로 이동해 주십시오. 메뉴 이름은 제조사별로 다소 다를 수 있습니다.',
      'cert.and.s3': '<b>무시하고 설치(Install anyway)</b>를 누른 후 다운로드 폴더에서 <b>qac-root-ca.crt</b>를 선택해 주십시오.',
      'cert.and.s4': '요청 시 화면 잠금 PIN 또는 패턴을 먼저 설정해 주십시오. Android에서 필수로 요구하는 사항입니다.',
      'cert.and.i1': 'Chrome을 완전히 종료한 후(최근 앱 목록에서 밀어서 닫기) 포털을 다시 열어 주십시오.',
      'cert.and.i2': '이 페이지에 "인증서가 설치되어 있습니다"라고 표시되는지 확인해 주십시오.',
      'cert.and.i3': '포털에서 <b>앱 설치</b>를 눌러 주십시오. 홈 화면에 QAC 아이콘이 표시됩니다.',
      'cert.and.hint': 'Android 11 이상에서는 다운로드 알림에서 .crt 파일을 바로 열어 설치할 수 없습니다. 반드시 위의 설정 메뉴를 통해 설치해 주십시오.',
      'cert.ios.step2': '2. 홈 화면에 추가',
      'cert.ios.s1': '<b>Safari</b>(Chrome 아님)에서 이 페이지를 연 후 위 버튼을 눌러 주십시오. <b>허용</b>을 선택한 후 <b>닫기</b>를 눌러 주십시오.',
      'cert.ios.s2': '<b>설정</b>을 열면 상단에 <b>다운로드된 프로파일</b>이 표시됩니다. 이를 누른 후 <b>설치</b>(암호 입력) &rarr; <b>설치</b> &rarr; <b>완료</b>를 눌러 주십시오. 표시되지 않으면 <b>일반 &rarr; VPN 및 기기 관리</b>로 이동해 주십시오.',
      'cert.ios.s3': '<b>필수 단계(자주 누락됨):</b> <b>설정 &rarr; 일반 &rarr; 정보 &rarr; 인증서 신뢰 설정</b>에서 <b>QAC Internal Root CA</b>를 활성화한 후 계속을 눌러 주십시오.',
      'cert.ios.i1': 'Safari를 종료했다가 다시 실행한 후 포털을 열어 주십시오.',
      'cert.ios.i2': '<b>공유</b> 버튼(위쪽 화살표가 있는 사각형) &rarr; <b>홈 화면에 추가</b> &rarr; <b>추가</b>를 눌러 주십시오.',
      'cert.ios.hint': 'iPad에는 자동 설치 버튼이 없으며, 위의 공유 절차가 유일한 방법입니다.',
      'cert.note': '이 인증서는 QAC 내부 서버에만 적용됩니다. 설치 중 문제가 발생하면 IT 팀에 문의해 주십시오.',

      'err.notAvailable': '서버에 아직 인증서가 준비되지 않았습니다 (HTTP {status}).',
      'err.invalid': '서버의 인증서 파일이 올바르지 않습니다.',
      // cmd.* is deliberately absent: cmd.exe's default code page garbles
      // Hangul echoed from the script, so Korean falls back to English there.
    },
  };

  function isSupported(code) {
    return LANGS.some(function (lang) { return lang.code === code; });
  }

  function detect() {
    try {
      var saved = localStorage.getItem(STORAGE_KEY);
      if (isSupported(saved)) return saved;
    } catch (e) { /* storage blocked: fall through */ }
    var browser = (navigator.languages && navigator.languages[0]) || navigator.language || '';
    var base = browser.toLowerCase().split('-')[0];
    if (base === 'ko' || base === 'en') return base;
    return 'id';
  }

  var current = detect();

  // Falls back to English, then Indonesian, for keys a language leaves out.
  function t(key, params) {
    var value = STRINGS[current][key];
    if (value === undefined) value = STRINGS.en[key];
    if (value === undefined) value = STRINGS.id[key];
    if (value === undefined) return key;
    return value.replace(/\{(\w+)\}/g, function (match, name) {
      return params && name in params ? params[name] : match;
    });
  }

  function apply() {
    document.documentElement.lang = current;
    document.querySelectorAll('[data-i18n]').forEach(function (el) {
      el.innerHTML = t(el.getAttribute('data-i18n'));
    });
    document.querySelectorAll('.lang-switch button').forEach(function (button) {
      button.setAttribute('aria-pressed', String(button.dataset.lang === current));
    });
  }

  function setLang(code) {
    if (!isSupported(code) || code === current) return;
    current = code;
    try { localStorage.setItem(STORAGE_KEY, code); } catch (e) { /* not persisted */ }
    apply();
  }

  // Point an element at a different string (e.g. a state-dependent message);
  // it keeps following language switches like any static data-i18n element.
  function set(el, key) {
    el.setAttribute('data-i18n', key);
    el.innerHTML = t(key);
  }

  function renderSwitcher(container) {
    container.classList.add('lang-switch');
    container.setAttribute('role', 'group');
    container.setAttribute('aria-label', 'Language / Bahasa / 언어');
    LANGS.forEach(function (lang) {
      var button = document.createElement('button');
      button.type = 'button';
      button.dataset.lang = lang.code;
      button.lang = lang.code;
      button.title = lang.name;
      button.textContent = lang.label;
      button.addEventListener('click', function () { setLang(lang.code); });
      container.appendChild(button);
    });
  }

  var style = document.createElement('style');
  style.textContent = [
    '.lang-switch { display: inline-flex; gap: 2px; padding: 3px; border: 1px solid var(--border);',
    '  border-radius: 999px; background: var(--card-bg); }',
    '.lang-switch button { border: 0; background: transparent; color: var(--muted); font: inherit;',
    '  font-size: 0.78rem; font-weight: 700; padding: 5px 11px; border-radius: 999px; cursor: pointer; }',
    '.lang-switch button:hover { color: var(--text); }',
    '.lang-switch button[aria-pressed="true"] { background: var(--brand); color: #fff; }',
    // Korean wraps between syllables by default, splitting words mid-way.
    'html:lang(ko) body { word-break: keep-all; overflow-wrap: anywhere; }',
  ].join('\n');
  document.head.appendChild(style);

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-lang-switch]').forEach(renderSwitcher);
    apply();
  });

  window.QacI18n = { t: t, set: set, lang: function () { return current; }, setLang: setLang };
})();
