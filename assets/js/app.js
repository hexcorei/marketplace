// JavaScript eksternal (tanpa inline script) agar kompatibel dengan Content-Security-Policy ketat.
(function () {
    'use strict';

    // Konfirmasi sebelum aksi berbahaya: <form data-confirm="Yakin?">
    // + cegah klik ganda (double submit) pada tombol kirim.
    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (!(form instanceof HTMLFormElement)) { return; }

        var msg = form.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) {
            ev.preventDefault();
            return;
        }
        var buttons = form.querySelectorAll('button[type="submit"]');
        window.setTimeout(function () {
            buttons.forEach(function (b) { b.disabled = true; });
        }, 0);
    }, true);

    // Aktifkan kembali tombol bila halaman dimuat dari cache tombol "kembali"
    window.addEventListener('pageshow', function (ev) {
        if (ev.persisted) {
            document.querySelectorAll('button[type="submit"]').forEach(function (b) { b.disabled = false; });
        }
    });
})();
