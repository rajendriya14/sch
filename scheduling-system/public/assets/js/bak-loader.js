/**
 * BAK Stepper — browser loader.
 *
 * Server ini tidak punya toolchain Node/TypeScript, sehingga berkas
 * BAKStepper.tsx dikompilasi di browser memakai Babel Standalone lalu
 * di-mount sebagai React root.
 *
 * Konfigurasi (set sebelum script ini dimuat):
 *   window.BAK_CONFIG = {
 *     apiBaseUrl : '/api/bak',                                  // base URL API BAK
 *     mountId    : 'bak-mount',                                 // id elemen mount
 *     sourceUrl  : 'scheduling-system/public/assets/js/BAKStepper.tsx',
 *     uploadSupportingDoc : function (file) { ... }              // opsional
 *     onLocked   : function (rows) { ... },                      // opsional
 *     onError    : function (error) { ... }                      // opsional
 *   };
 */
(function () {
    'use strict';

    var config = window.BAK_CONFIG || {};
    var mountId = config.mountId || 'bak-mount';
    var sourceUrl = config.sourceUrl || 'scheduling-system/public/assets/js/BAKStepper.tsx';
    var apiBaseUrl = config.apiBaseUrl || '/api/bak';

    function mountElement() {
        return document.getElementById(mountId);
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function showError(title, detail) {
        var el = mountElement();
        if (!el) return;
        var extra = detail
            ? '<pre style="white-space:pre-wrap;text-align:left;background:#0f172a;color:#fca5a5;' +
              'padding:12px;border-radius:8px;font-size:12px;margin:10px 0 0;overflow:auto">' +
              escapeHtml(detail) +
              '</pre>'
            : '';
        el.innerHTML =
            '<div class="alert alert-danger mb-0"><strong>' +
            escapeHtml(title) +
            '</strong>' +
            extra +
            '</div>';
    }

    /** Shim `require` untuk hasil transformasi ESM -> CommonJS. */
    function requireShim(name) {
        if (name === 'react') return window.React;
        if (name === 'react-dom' || name === 'react-dom/client') return window.ReactDOM;
        throw new Error('Modul "' + name + '" tidak tersedia di browser.');
    }

    function evaluate(code) {
        var moduleObject = { exports: {} };
        // eslint-disable-next-line no-new-func
        var factory = new Function('require', 'module', 'exports', code);
        factory(requireShim, moduleObject, moduleObject.exports);
        return moduleObject.exports;
    }

    /**
     * Babel menerapkan preset dari belakang ke depan, jadi urutan array ini
     * menghasilkan: typescript (buang tipe) -> react (JSX) -> env (ESM->CJS).
     */
    function compile(source) {
        return window.Babel.transform(source, {
            filename: 'BAKStepper.tsx',
            sourceType: 'module',
            presets: [
                ['env', { modules: 'commonjs' }],
                ['react', { runtime: 'classic' }],
                ['typescript', { isTSX: true, allExtensions: true }]
            ]
        }).code;
    }

    function buildProps() {
        var props = { apiBaseUrl: apiBaseUrl };
        if (typeof config.uploadSupportingDoc === 'function') {
            props.uploadSupportingDoc = config.uploadSupportingDoc;
        }
        if (typeof config.onLocked === 'function') {
            props.onLocked = config.onLocked;
        }
        if (typeof config.onError === 'function') {
            props.onError = config.onError;
        }
        return props;
    }

    function mountComponent(exportsObject) {
        var Component = exportsObject && (exportsObject.default || exportsObject.BAKStepper);
        if (typeof Component !== 'function') {
            throw new Error('Komponen default tidak ditemukan pada hasil kompilasi BAKStepper.tsx.');
        }
        var root = window.ReactDOM.createRoot(mountElement());
        root.render(window.React.createElement(Component, buildProps()));
    }

    function boot() {
        if (!mountElement()) return;

        if (!window.React || !window.ReactDOM) {
            showError('React gagal dimuat', 'Periksa akses ke CDN cdn.jsdelivr.net.');
            return;
        }
        if (!window.Babel) {
            showError('Babel Standalone gagal dimuat', 'Periksa akses ke CDN cdn.jsdelivr.net.');
            return;
        }

        fetch(sourceUrl, { credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status + ' saat mengambil ' + sourceUrl);
                }
                return response.text();
            })
            .then(function (source) {
                return evaluate(compile(source));
            })
            .then(mountComponent)
            .catch(function (error) {
                if (typeof config.onError === 'function') config.onError(error);
                showError('Gagal memuat komponen BAK', error && error.message ? error.message : String(error));
            });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
