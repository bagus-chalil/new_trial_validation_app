// Shared helpers for the portal pages (index.html, sertifikat.html):
// platform detection, "is the QAC certificate trusted yet" check, the PWA
// install prompt, and the one-click Windows certificate setup script.
(function () {
  'use strict';

  function platform() {
    var ua = navigator.userAgent;
    if (/android/i.test(ua)) return 'android';
    // iPadOS 13+ reports itself as a Mac; touch support gives it away.
    if (/iPad|iPhone|iPod/.test(ua) || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1)) {
      return 'ios';
    }
    if (/Windows/.test(ua)) return 'windows';
    return 'other';
  }

  function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  }

  // A service worker only registers on a trusted HTTPS origin, so a
  // successful registration doubles as "the certificate is installed".
  var trustedPromise = null;
  function checkTrusted() {
    if (!trustedPromise) {
      trustedPromise = !('serviceWorker' in navigator)
        ? Promise.resolve(false)
        : navigator.serviceWorker.register('sw.js').then(
            function () { return true; },
            function () { return false; },
          );
    }
    return trustedPromise;
  }

  // Chrome/Edge (desktop + Android) fire this only once the page is
  // installable; keep it so a visible button can trigger the dialog later.
  var deferredPrompt = null;
  var installListeners = [];
  window.addEventListener('beforeinstallprompt', function (event) {
    event.preventDefault();
    deferredPrompt = event;
    installListeners.forEach(function (fn) { fn(true); });
  });
  window.addEventListener('appinstalled', function () {
    deferredPrompt = null;
    installListeners.forEach(function (fn) { fn(false); });
  });

  function onInstallAvailable(fn) {
    installListeners.push(fn);
    if (deferredPrompt) fn(true);
  }

  function promptInstall() {
    if (!deferredPrompt) return Promise.resolve(false);
    var prompt = deferredPrompt;
    deferredPrompt = null;
    prompt.prompt();
    return prompt.userChoice.then(function (choice) {
      return choice.outcome === 'accepted';
    });
  }

  // Builds setup-sertifikat-qac.cmd with the root CA embedded, so the user
  // runs one file instead of walking the certificate import wizard (whose
  // "Automatically select the store" default puts the CA in the wrong store).
  // certutil -user needs no admin rights; Windows asks for one confirmation.
  function downloadWindowsSetup() {
    return fetch('qac-root-ca.crt', { cache: 'no-store' })
      .then(function (response) {
        if (!response.ok) throw new Error('Sertifikat belum tersedia di server (HTTP ' + response.status + ').');
        return response.text();
      })
      .then(function (pem) {
        var lines = pem.trim().split(/\r?\n/);
        var valid = lines[0] === '-----BEGIN CERTIFICATE-----'
          && lines[lines.length - 1] === '-----END CERTIFICATE-----'
          && lines.slice(1, -1).every(function (line) { return /^[A-Za-z0-9+/=]+$/.test(line); });
        if (!valid) throw new Error('File sertifikat di server tidak valid.');

        var portalUrl = location.origin + location.pathname.replace(/[^/]*$/, '');
        var script = [
          '@echo off',
          'setlocal',
          'title Setup Sertifikat QAC',
          'echo.',
          'echo  Memasang sertifikat QAC ke Trusted Root (akun Windows Anda)...',
          'echo  Klik YES pada jendela peringatan keamanan Windows yang muncul.',
          'echo.',
          'set "CRT=%TEMP%\\qac-root-ca.crt"',
          '> "%CRT%" (',
        ].concat(lines.map(function (line) { return 'echo ' + line; }), [
          ')',
          'certutil -user -addstore Root "%CRT%" >nul',
          'set "RC=%ERRORLEVEL%"',
          'del "%CRT%" >nul 2>&1',
          'if not "%RC%"=="0" (',
          '  echo  GAGAL memasang sertifikat. Jalankan ulang file ini dan klik YES.',
          '  echo.',
          '  pause',
          '  exit /b 1',
          ')',
          'echo  Berhasil! Sertifikat QAC sudah terpasang.',
          'echo.',
          'echo  Sekarang TUTUP SEMUA jendela Chrome / Edge,',
          'echo  lalu tekan tombol apa saja di sini untuk membuka portal lagi.',
          'echo.',
          'pause >nul',
          'start "" "' + portalUrl + '"',
        ]).join('\r\n') + '\r\n';

        var link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob([script], { type: 'application/octet-stream' }));
        link.download = 'setup-sertifikat-qac.cmd';
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(function () { URL.revokeObjectURL(link.href); }, 10000);
      });
  }

  window.QacSetup = {
    platform: platform,
    isStandalone: isStandalone,
    checkTrusted: checkTrusted,
    onInstallAvailable: onInstallAvailable,
    promptInstall: promptInstall,
    downloadWindowsSetup: downloadWindowsSetup,
  };
})();
