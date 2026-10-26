/**
 * Workforce Automation Scheduling System — App JS
 */

$(document).ready(function () {

    // === Confirm actions ===
    // Di halaman FFR dialog memakai AppDialog (tengah layar, bisa diatur).
    // Halaman lain tetap memakai confirm() bawaan browser.
    $('[data-confirm]').on('click', function (e) {
        var text = $(this).data('confirm') || 'Are you sure?';

        if (!window.AppDialog) {
            if (!confirm(text)) {
                e.preventDefault();
            }
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        var el = this;
        var opts = buildConfirmOptions(el, text);
        window.AppDialog.runConfirmed(el, opts);
    });

    /** Susun opsi dialog dari atribut data-confirm* + jenis elemennya. */
    function buildConfirmOptions(el, text) {
        var $el = $(el);
        var tone = $el.data('confirm-tone');
        var href = el.getAttribute ? (el.getAttribute('href') || '') : '';
        var cls = el.className || '';
        var lower = String(text).toLowerCase();

        if (!tone) {
            var risky = /btn-danger|text-danger|btn-outline-danger/.test(cls)
                || /delete|hapus/i.test(href)
                || /hapus|delete|buang|kosongkan/.test(lower);
            tone = risky ? 'danger' : 'warning';
        }

        var title = $el.data('confirm-title');
        var message = text;

        // Kalimat tanya dipakai sebagai judul, sisanya jadi isi dialog — supaya
        // judul tetap ringkas dan penjelasannya terbaca sebagai paragraf.
        if (!title) {
            var q = message.indexOf('?');
            if (q > 0 && q <= 90) {
                var rest = message.slice(q + 1).trim();
                title = message.slice(0, q + 1).trim();
                message = rest;
            }
        }

        // Kalimat pendek yang sudah berupa pertanyaan tetap dipakai apa adanya.
        if (!title && message.length <= 70 && /\?$/.test(message)) {
            title = message;
            message = '';
        }

        return {
            tone: tone,
            title: title || 'Konfirmasi',
            message: message,
            confirmText: $el.data('confirm-ok') || (tone === 'danger' ? 'Ya, lanjutkan' : 'Lanjutkan'),
            cancelText: $el.data('confirm-cancel') || 'Batal'
        };
    }

    // === Schedule generation loading state ===
    $('#btnGenerateSchedule').on('click', function () {
        const $btn = $(this);
        $btn.prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm" role="status"></span> Generating...'
        );
        $('.schedule-loading').addClass('active');
    });

    // === Auto-dismiss alerts after 5 seconds ===
    setTimeout(function () {
        $('.alert-dismissible').fadeOut('slow');
    }, 5000);

    // === Shift toggle is now handled by the Save button form (saveToggle) ===

    // === Tooltip initialization ===
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (el) {
        new bootstrap.Tooltip(el);
    });
});
