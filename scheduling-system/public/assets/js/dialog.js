/**
 * AppDialog — dialog konfirmasi & toast untuk modul FFR.
 *
 * Menggantikan window.confirm() bawaan browser yang tampilannya tidak bisa
 * diatur (kotak abu-abu kecil di tengah-atas). Dialog di sini muncul di tengah
 * layar, latar diredupkan & diburamkan, dan bisa ditutup dengan Esc.
 *
 * Pemakaian:
 *   AppDialog.confirm({ tone: 'danger', title: '...', message: '...' })
 *       .then(function (ok) { if (ok) { ... } });
 *   AppDialog.toast({ tone: 'success', title: 'Berhasil', message: '...' });
 *
 * Modul ini hanya dimuat di halaman FFR (lihat views/layouts/footer.php).
 */
(function () {
    'use strict';

    var ICONS = {
        danger:  'bi-exclamation-octagon',
        warning: 'bi-exclamation-triangle',
        info:    'bi-info-circle',
        success: 'bi-check-circle'
    };

    var CONFIRM_BTN = {
        danger:  'btn-danger',
        warning: 'btn-warning',
        info:    'btn-primary',
        success: 'btn-success'
    };

    var TOAST_ICON = {
        success: 'bi-check-circle-fill',
        warning: 'bi-exclamation-triangle-fill',
        danger:  'bi-x-circle-fill'
    };

    var TONE_TITLE = {
        success: 'Berhasil',
        warning: 'Perhatian',
        danger:  'Gagal',
        info:    'Informasi'
    };

    var overlay = null;
    var els = {};
    var resolver = null;
    var lastFocus = null;

    /** Bangun markup dialog sekali saja, lalu dipakai ulang. */
    function ensureOverlay() {
        if (overlay) return overlay;

        overlay = document.createElement('div');
        overlay.className = 'dlg-overlay';
        overlay.id = 'dlgOverlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'dlgTitle');
        overlay.setAttribute('aria-describedby', 'dlgMessage');
        overlay.innerHTML =
            '<div class="dlg-card" role="document">' +
                '<div class="dlg-icon dlg-icon--warning" id="dlgIcon">' +
                    '<i class="bi bi-exclamation-triangle" id="dlgIconGlyph"></i>' +
                '</div>' +
                '<h2 class="dlg-title" id="dlgTitle">Konfirmasi</h2>' +
                '<p class="dlg-message" id="dlgMessage"></p>' +
                '<ul class="dlg-detail d-none" id="dlgDetail"></ul>' +
                '<p class="dlg-note" id="dlgNote">Tekan Esc untuk membatalkan.</p>' +
                '<div class="dlg-actions">' +
                    '<button type="button" class="btn btn-light border" id="dlgCancel">Batal</button>' +
                    '<button type="button" class="btn btn-primary" id="dlgConfirm">Lanjutkan</button>' +
                '</div>' +
            '</div>';

        document.body.appendChild(overlay);

        els.icon = document.getElementById('dlgIcon');
        els.glyph = document.getElementById('dlgIconGlyph');
        els.title = document.getElementById('dlgTitle');
        els.message = document.getElementById('dlgMessage');
        els.detail = document.getElementById('dlgDetail');
        els.note = document.getElementById('dlgNote');
        els.cancel = document.getElementById('dlgCancel');
        els.confirm = document.getElementById('dlgConfirm');

        els.cancel.addEventListener('click', function () { close(false); });
        els.confirm.addEventListener('click', function () { close(true); });

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) close(false);   // klik latar = batal
        });

        return overlay;
    }

    function open(opts) {
        opts = opts || {};
        ensureOverlay();

        var tone = CONFIRM_BTN[opts.tone] ? opts.tone : 'warning';

        els.icon.className = 'dlg-icon dlg-icon--' + tone;
        els.glyph.className = 'bi ' + (opts.icon || ICONS[tone]);
        els.title.textContent = opts.title || 'Konfirmasi';
        els.message.textContent = opts.message || '';

        if (Array.isArray(opts.detail) && opts.detail.length) {
            els.detail.innerHTML = opts.detail.map(function (d) {
                return '<li>' + d + '</li>';
            }).join('');
            els.detail.classList.remove('d-none');
        } else {
            els.detail.innerHTML = '';
            els.detail.classList.add('d-none');
        }

        if (opts.note === false) {
            els.note.classList.add('d-none');
        } else {
            els.note.textContent = opts.note || 'Tekan Esc untuk membatalkan.';
            els.note.classList.remove('d-none');
        }

        els.cancel.textContent = opts.cancelText || 'Batal';
        els.cancel.className = 'btn btn-light border';
        els.confirm.textContent = opts.confirmText || 'Lanjutkan';
        els.confirm.className = 'btn ' + (opts.confirmClass || CONFIRM_BTN[tone]);

        lastFocus = document.activeElement;
        overlay.classList.add('is-open');
        document.body.style.overflow = 'hidden';

        // Dua frame agar transisi opacity/scale sempat berjalan.
        requestAnimationFrame(function () {
            overlay.classList.add('is-visible');
            // Aksi berbahaya: fokus ke Batal supaya Enter tidak menghapus.
            (tone === 'danger' ? els.cancel : els.confirm).focus();
        });

        return new Promise(function (resolve) { resolver = resolve; });
    }

    function close(result) {
        if (!resolver) return;

        var done = resolver;
        resolver = null;

        overlay.classList.remove('is-visible');
        document.body.style.overflow = '';

        setTimeout(function () {
            overlay.classList.remove('is-open');
        }, 180);

        if (lastFocus && lastFocus.focus) lastFocus.focus();
        done(result);
    }

    document.addEventListener('keydown', function (e) {
        if (!overlay || !overlay.classList.contains('is-open')) return;

        if (e.key === 'Escape') {
            e.preventDefault();
            close(false);
            return;
        }

        if (e.key === 'Tab') {
            // Fokus tetap berputar di dalam dialog.
            var focusables = [els.cancel, els.confirm];
            var i = focusables.indexOf(document.activeElement);
            e.preventDefault();
            var next = e.shiftKey ? i - 1 : i + 1;
            if (next < 0) next = focusables.length - 1;
            if (next >= focusables.length) next = 0;
            focusables[next].focus();
        }
    });

    // ---------------------------------------------------------------
    // Toast: notifikasi hasil simpan / peringatan ringan
    // ---------------------------------------------------------------

    function toastStack() {
        var stack = document.getElementById('toastStack');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'toast-stack';
            stack.id = 'toastStack';
            stack.setAttribute('aria-live', 'polite');
            stack.setAttribute('aria-atomic', 'true');
            document.body.appendChild(stack);
        }
        return stack;
    }

    function toast(opts) {
        opts = opts || {};
        var tone = TOAST_ICON[opts.tone] ? opts.tone : 'success';
        var stack = toastStack();

        var el = document.createElement('div');
        el.className = 'app-toast' + (tone === 'success' ? '' : ' is-' + tone);
        el.innerHTML =
            '<i class="bi ' + TOAST_ICON[tone] + ' t-icon"></i>' +
            '<div class="flex-grow-1">' +
                '<p class="t-title"></p>' +
                '<p class="t-body"></p>' +
            '</div>' +
            '<button type="button" class="t-close" aria-label="Tutup">&times;</button>';

        el.querySelector('.t-title').textContent = opts.title || TONE_TITLE[tone];
        el.querySelector('.t-body').textContent = opts.message || '';

        stack.appendChild(el);
        requestAnimationFrame(function () { el.classList.add('is-visible'); });

        var timer = setTimeout(remove, opts.duration || 5000);
        el.querySelector('.t-close').addEventListener('click', function () {
            clearTimeout(timer);
            remove();
        });

        function remove() {
            el.classList.remove('is-visible');
            setTimeout(function () { el.remove(); }, 220);
        }
    }

    // Notifikasi flash dari server (tag [data-flash-toast]) diubah jadi toast.
    function initFlashToasts() {
        var nodes = document.querySelectorAll('[data-flash-toast]');
        Array.prototype.forEach.call(nodes, function (node) {
            var tone = node.getAttribute('data-tone') || 'success';
            var message = (node.getAttribute('data-message') || '').trim();
            if (message !== '') {
                toast({
                    tone: tone,
                    title: node.getAttribute('data-title') || TONE_TITLE[tone],
                    message: message,
                    duration: tone === 'success' ? 5000 : 8000
                });
            }
            node.remove();
        });
    }

    window.AppDialog = { confirm: open, toast: toast };

    // ---------------------------------------------------------------
    // Jembatan untuk atribut data-confirm (dipakai app.js & view FFR)
    // ---------------------------------------------------------------

    /**
     * Jalankan konfirmasi lalu lanjutkan aksi aslinya.
     *
     * El yang diklik bisa berupa link, tombol submit, atau form (data-confirm
     * dipasang di <form>). Semua dicegat dulu supaya dialog sempat tampil.
     */
    function runConfirmed(el, opts) {
        opts = opts || {};

        var isForm = el.tagName === 'FORM';
        var form = isForm ? el : (el.form || el.closest('form'));
        var href = (!isForm && el.tagName === 'A') ? el.getAttribute('href') : null;

        // Aksi yang sudah punya data-confirm sendiri (mis. tombol dengan
        // formaction) tetap dihormati saat dijalankan ulang.
        var formAction = el.getAttribute ? el.getAttribute('formaction') : null;

        open(opts).then(function (ok) {
            if (!ok) return;

            if (isForm) {
                // submit() langsung agar tidak memicu handler ini lagi.
                HTMLFormElement.prototype.submit.call(el);
                return;
            }

            if (href && href !== '#') {
                window.location.href = href;
                return;
            }

            if (form) {
                if (formAction) form.setAttribute('action', formAction);
                HTMLFormElement.prototype.submit.call(form);
            }
        });
    }

    window.AppDialog.runConfirmed = runConfirmed;

    document.addEventListener('DOMContentLoaded', initFlashToasts);
})();
