/**
 * Family Financial Report — interaksi halaman.
 *
 * Menangani: format angka ribuan, tambah/hapus baris pemasukan, hitung
 * perkiraan biaya per bulan, simpan realisasi secara inline, dan isi form
 * saat mengubah item.
 */
(function () {
    'use strict';

    /** 1500000 -> "1.500.000" */
    function formatThousands(value) {
        var digits = String(value).replace(/[^0-9]/g, '');
        if (digits === '') return '';
        return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    /** 5000000.5 -> "5.000.000,50" (maksimal dua desimal, untuk saldo dana). */
    function formatMoney2(value) {
        var text = String(value == null ? '' : value).replace(/[^0-9,]/g, '');
        if (text === '') return '';
        var pieces = text.split(',');
        var whole = (pieces.shift() || '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        var decimals = pieces.length ? ',' + pieces.join('').slice(0, 2) : '';
        return whole + decimals;
    }

    function parseAmount(value) {
        var digits = String(value).replace(/[^0-9]/g, '');
        return digits === '' ? 0 : parseInt(digits, 10);
    }

    /** Input bertanda .ffr-amount diformat otomatis saat diketik. */
    function bindAmountInputs(scope) {
        (scope || document).querySelectorAll('.ffr-amount').forEach(function (input) {
            if (input.dataset.ffrBound === '1') return;
            input.dataset.ffrBound = '1';
            // Kolom yang boleh desimal (harga obligasi %, NAB reksa dana)
            // tidak diformat ribuan agar koma desimal tidak hilang.
            if (input.dataset.ffrDecimal === '1') return;

            function format(input) {
                return input.dataset.ffrMoney2 === '1'
                    ? formatMoney2(input.value)
                    : formatThousands(input.value);
            }

            input.value = format(input);
            input.addEventListener('input', function () {
                if (this.dataset.ffrDecimal === '1') return;
                // Kolom bisa dilepas dari format uang saat jenis aset berganti.
                if (!this.classList.contains('ffr-amount')) return;
                var pos = this.selectionStart;
                var before = this.value.length;
                this.value = format(this);
                // jaga posisi kursor kira-kira tetap di tempat yang sama
                var diff = this.value.length - before;
                if (pos !== null) {
                    this.setSelectionRange(pos + diff, pos + diff);
                }
                this.dispatchEvent(new CustomEvent('ffr:amount', { bubbles: true }));
            });
            input.addEventListener('blur', function () {
                if (this.dataset.ffrDecimal === '1') return;
                if (!this.classList.contains('ffr-amount')) return;
                this.dispatchEvent(new CustomEvent('ffr:amount', { bubbles: true }));
            });
        });
    }

    function rupiah(value) {
        var n = Math.round(Number(value) || 0);
        return 'Rp ' + n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    /** Sama seperti MoneyHelper::short() di PHP: 1.250.000 -> "1,25 jt" */
    function rupiahShort(value) {
        var n = Number(value) || 0;
        var abs = Math.abs(n);
        function trim(x) {
            return String(Number(x.toFixed(2))).replace('.', ',');
        }
        if (abs >= 1000000000) return trim(n / 1000000000) + ' M';
        if (abs >= 1000000) return trim(n / 1000000) + ' jt';
        if (abs >= 1000) return trim(n / 1000) + ' rb';
        return String(Math.round(n));
    }

    /** Teks persentase gaya Indonesia: 16.3 -> "16,3", 100 -> "100" */
    function percentText(value) {
        var n = Number(value) || 0;
        return String(Number(n.toFixed(1))).replace('.', ',');
    }

    /** Badge status item/bucket mengikuti kelas Bootstrap yang dipakai PHP. */
    function statusBadgeHtml(status) {
        var cls = status.class === 'secondary'
            ? 'bg-secondary-subtle text-secondary border border-secondary'
            : 'bg-' + status.class + '-subtle text-' + status.class + ' border border-' + status.class;
        return '<i class="bi ' + status.icon + '"></i> ' + status.label;
    }

    /** Terapkan angka hasil hitung ulang dari server tanpa reload halaman. */
    function applyActualResult(json) {
        var item = json.item || {};
        var bucket = json.bucket || {};
        var totals = json.totals || {};

        // 1) Status badge baris item yang diubah
        var badge = document.querySelector('[data-ffr-status="' + item.id + '"]');
        if (badge && item.status) {
            badge.className = 'badge ' + (item.status.class === 'secondary'
                ? 'bg-secondary-subtle text-secondary border border-secondary'
                : 'bg-' + item.status.class + '-subtle text-' + item.status.class + ' border border-' + item.status.class);
            badge.innerHTML = statusBadgeHtml(item.status);
        }

        // 2) Ringkasan kelompok (kartu atas)
        var setText = function (id, text) {
            var el = document.getElementById(id);
            if (el) el.textContent = text;
        };

        setText('ffrBucketAllocated', rupiah(bucket.allocated));
        setText('ffrBucketPlan', rupiah(bucket.plan_total));
        setText('ffrBucketPct', percentText(bucket.percent));

        // Overview "Dana Aktual": angka, catatan, dan bar progresnya.
        var actualEl = document.getElementById('ffrBucketActual');
        if (actualEl) {
            actualEl.textContent = rupiah(bucket.actual_total);
            actualEl.classList.toggle('text-danger', bucket.allocated > 0 && bucket.actual_total > bucket.allocated);
        }
        setText('ffrActualPct', percentText(bucket.actual_pct));

        var note = document.getElementById('ffrBucketActualNote');
        if (note) {
            var diff = (bucket.actual_total || 0) - (bucket.plan_total || 0);
            var text = percentText(bucket.actual_pct) + '% dari dana';
            if ((bucket.actual_total || 0) > 0 && Math.abs(diff) >= 1) {
                text += diff > 0
                    ? ' · lebih ' + rupiah(diff) + ' dari rencana'
                    : ' · hemat ' + rupiah(Math.abs(diff)) + ' dari rencana';
            }
            note.textContent = text;
        }

        var actualBar = document.getElementById('ffrActualBar');
        if (actualBar) {
            var actualCls = 'bg-secondary';
            if (bucket.allocated <= 0 && bucket.actual_total > 0) {
                actualCls = 'bg-danger';
            } else if (bucket.actual_total > bucket.allocated) {
                actualCls = 'bg-danger';
            } else if ((bucket.actual_pct || 0) >= 90) {
                actualCls = 'bg-warning';
            } else if (bucket.actual_total > 0) {
                actualCls = 'bg-success';
            }
            actualBar.className = 'progress-bar ' + actualCls;
            actualBar.style.width = (bucket.actual_pct || 0) + '%';
        }

        var remaining = document.getElementById('ffrBucketRemaining');
        if (remaining) {
            var negative = (bucket.remaining || 0) < 0;
            remaining.textContent = (negative ? '−' : '') + rupiah(Math.abs(bucket.remaining || 0));
            remaining.classList.toggle('text-danger', negative);
            remaining.classList.toggle('text-success', !negative);
        }

        var headBadge = document.getElementById('ffrBucketStatus');
        if (headBadge && bucket.status) {
            headBadge.className = 'badge ' + bucket.status.badge;
            headBadge.innerHTML = '<i class="bi ' + bucket.status.icon + '"></i> ' + bucket.status.label;
        }

        var bar = document.getElementById('ffrBucketBar');
        if (bar && bucket.status) {
            var barClass = { aman: 'bg-success', ketat: 'bg-warning', lewat: 'bg-danger', tanpa_dana: 'bg-danger' }[bucket.status.key] || 'bg-secondary';
            bar.className = 'progress-bar ' + barClass;
            bar.style.width = (bucket.percent || 0) + '%';
        }

        // Peringatan "belum punya dana" muncul/hilang mengikuti alokasi.
        var alertBox = document.getElementById('ffrBucketEmptyAlert');
        if (alertBox) {
            alertBox.classList.toggle('d-none', !bucket.empty_alert);
        }

        // 3) Footer tabel
        setText('ffrItemsPlanTotal', rupiah(totals.plan_total));
        setText('ffrItemsActualTotal', rupiah(totals.actual_total));

        // 4) Pil pemilih kelompok
        (json.buckets || []).forEach(function (b) {
            var pill = document.querySelector('[data-ffr-pill="' + b.id + '"]');
            if (!pill) return;
            var pillBadge = pill.querySelector('.badge');
            if (pillBadge) {
                pillBadge.className = 'badge ' + b.badge + ' ms-1';
                pillBadge.textContent = b.plan_label;
                pillBadge.title = b.plan_title;
            }
        });
    }

    // ---------------- Layar Rencana ----------------

    function initPlanPage() {
        var form = document.getElementById('ffrPlanForm');
        if (!form) return;

        var table = document.getElementById('ffrIncomeTable');

        /** Total pemasukan saat ini (diisi dari server, ikut berubah saat mengetik). */
        function incomeTotal() {
            var total = 0;
            form.querySelectorAll('input[name="income_amount[]"]').forEach(function (input) {
                // Nama sumber boleh kosong: server akan menyimpannya sebagai
                // "Pemasukan". Dulu baris seperti ini diabaikan sehingga total
                // tetap Rp 0 walau jumlahnya sudah diisi.
                total += parseAmount(input.value);
            });
            return total;
        }

        function allocRows() {
            return Array.prototype.slice.call(form.querySelectorAll('.ffr-alloc-row'));
        }

        function pctInput(row) {
            return row.querySelector('.ffr-pct');
        }

        function rpInput(row) {
            return row.querySelector('.ffr-alloc-rp');
        }

        function srcInput(row) {
            var id = row.dataset.bucket;
            return document.getElementById('ffrSrc' + id);
        }

        function fmtPercent(value) {
            var n = Number(value) || 0;
            var text = n.toFixed(2).replace(/\.?0+$/, '');
            return text.replace('.', ',');
        }

        function parsePercentInput(value) {
            var text = String(value == null ? '' : value).replace(/[^0-9,.]/g, '').replace(',', '.');
            var n = parseFloat(text);
            return isNaN(n) ? 0 : n;
        }

        /** Sinkronkan satu baris: dari persen ke rupiah atau sebaliknya. */
        function syncRow(row, from) {
            var income = incomeTotal();
            var pctEl = pctInput(row);
            var rpEl = rpInput(row);
            var src = srcInput(row);

            if (from === 'percent') {
                var pct = parsePercentInput(pctEl.value);
                var amount = income > 0 ? Math.round(pct / 100 * income) : 0;
                rpEl.value = amount > 0 ? formatThousands(String(amount)) : '';
                if (src) src.value = 'percent';
            } else {
                var amountRp = parseAmount(rpEl.value);
                var pctValue = income > 0 ? (amountRp / income) * 100 : 0;
                pctEl.value = amountRp > 0 ? fmtPercent(pctValue) : '';
                if (src) src.value = 'rp';
            }
        }

        /**
         * Saat pemasukan berubah, baris yang tersimpan sebagai persen dihitung
         * ulang (persen dipegang), sedangkan baris berbasis nominal rupiah
         * dibiarkan — persennya yang menyesuaikan.
         */
        function reflowFromIncome() {
            allocRows().forEach(function (row) {
                var src = srcInput(row);
                syncRow(row, src && src.value === 'percent' ? 'percent' : 'rp');
            });
        }

        function recalc() {
            var totalIncome = incomeTotal();

            var totalAlloc = 0;
            var totalPct = 0;
            allocRows().forEach(function (row) {
                totalAlloc += parseAmount(rpInput(row).value);
                totalPct += parsePercentInput(pctInput(row).value);
            });

            var sisa = totalIncome - totalAlloc;
            var sisaPct = totalIncome > 0 ? (sisa / totalIncome) * 100 : 0;

            var incomeBadge = document.getElementById('ffrIncomeBadge');
            if (incomeBadge) incomeBadge.textContent = rupiah(totalIncome);

            var totalIncomeEl = document.getElementById('ffrTotalIncome');
            if (totalIncomeEl) totalIncomeEl.textContent = rupiah(totalIncome);

            var totalAllocEl = document.getElementById('ffrTotalAlloc');
            if (totalAllocEl) totalAllocEl.textContent = rupiah(totalAlloc);

            var totalPctEl = document.getElementById('ffrTotalPct');
            if (totalPctEl) {
                totalPctEl.textContent = fmtPercent(totalPct) + '%';
                totalPctEl.classList.toggle('text-danger', totalPct > 100);
            }

            var sisaEl = document.getElementById('ffrUnallocated');
            if (sisaEl) {
                sisaEl.textContent = (sisa < 0 ? '−' : '') + rupiah(Math.abs(sisa));
                sisaEl.classList.toggle('text-danger', sisa < 0);
                sisaEl.classList.toggle('text-success', sisa >= 0);
            }

            var sisaPctEl = document.getElementById('ffrUnallocPct');
            if (sisaPctEl) {
                sisaPctEl.textContent = '= ' + (sisa < 0 ? '−' : '') + fmtPercent(Math.abs(sisaPct)) + '% dari pemasukan';
                sisaPctEl.classList.toggle('text-danger', sisa < 0);
                sisaPctEl.classList.toggle('text-muted', sisa >= 0);
            }

            var overWarn = document.getElementById('ffrOverWarn');
            if (overWarn) overWarn.classList.toggle('d-none', totalPct <= 100);

            var bar = document.getElementById('ffrUnallocBar');
            if (bar) {
                bar.classList.toggle('d-none', totalIncome <= 0);
                var fill = bar.querySelector('.progress-bar');
                if (fill) fill.style.width = Math.min(100, Math.abs(sisaPct)) + '%';
            }

            // Kolom persen ikut nonaktif bila pemasukan masih kosong atau
            // bulan sudah ditutup buku.
            var closed = form.dataset.closed === '1';
            allocRows().forEach(function (row) {
                var pctEl = pctInput(row);
                if (pctEl) pctEl.disabled = closed || totalIncome <= 0;
            });
        }

        form.addEventListener('input', function (e) {
            var target = e.target;
            if (!target) return;

            if (target.classList.contains('ffr-pct')) {
                syncRow(target.closest('.ffr-alloc-row'), 'percent');
            } else if (target.classList.contains('ffr-alloc-rp')) {
                var row = target.closest('.ffr-alloc-row');
                // Format ribuan langsung, kursor dijaga di posisi yang sama.
                var pos = target.selectionStart;
                var before = target.value.length;
                var formatted = formatThousands(target.value);
                if (target.value !== formatted) {
                    target.value = formatted;
                    var diff = formatted.length - before;
                    if (pos !== null) target.setSelectionRange(pos + diff, pos + diff);
                }
                syncRow(row, 'rp');
            } else if (target.name && target.name.indexOf('income_amount') === 0) {
                reflowFromIncome();
                recalc();
                return;
            }
            recalc();
        });

        form.addEventListener('blur', function (e) {
            var target = e.target;
            if (target && target.classList && target.classList.contains('ffr-pct') && target.value !== '') {
                target.value = fmtPercent(parsePercentInput(target.value));
            }
        }, true);

        // Tombol hapus/tambah baris pemasukan tetap memicu hitung ulang.
        form.addEventListener('ffr:amount', recalc);

        // Tambah baris pemasukan
        var addBtn = document.getElementById('ffrAddIncome');
        if (addBtn && table) {
            addBtn.addEventListener('click', function () {
                var tbody = table.querySelector('tbody');
                var firstRow = tbody.querySelector('tr');
                var row = firstRow.cloneNode(true);

                row.querySelectorAll('input').forEach(function (input) {
                    input.value = '';
                    delete input.dataset.ffrBound;
                });
                var select = row.querySelector('select');
                if (select) select.selectedIndex = 0;

                tbody.appendChild(row);
                bindAmountInputs(row);
                recalc();
            });
        }

        // Hapus baris pemasukan
        if (table) {
            table.addEventListener('click', function (e) {
                var btn = e.target.closest('.ffr-remove-row');
                if (!btn) return;
                var tbody = table.querySelector('tbody');
                if (tbody.querySelectorAll('tr').length <= 1) {
                    // sisakan satu baris kosong
                    btn.closest('tr').querySelectorAll('input').forEach(function (input) { input.value = ''; });
                } else {
                    btn.closest('tr').remove();
                }
                recalc();
            });
        }

        // Setelah draft dipulihkan atau form dikosongkan, angka ringkasan
        // harus dihitung ulang dari nilai yang sekarang tampil.
        form.addEventListener('ffr:draftRestored', function () {
            reflowFromIncome();
            recalc();
        });
        form.addEventListener('ffr:cleared', recalc);

        recalc();
    }

    // ---------------- Layar Daftar Belanja ----------------

    function initItemsPage() {
        var form = document.getElementById('ffrItemForm');
        var freq = document.getElementById('ffrItemFreq');
        var price = document.getElementById('ffrItemPrice');
        var qty = document.getElementById('ffrItemQty');
        var estimate = document.getElementById('ffrItemEstimate');

        var multipliers = { weekly: 4, biweekly: 2, monthly: 1, once: 0 };

        function recalcEstimate() {
            if (!estimate) return;
            var m = multipliers[freq ? freq.value : 'monthly'];
            var perMonth = parseAmount(price ? price.value : 0) * (parseFloat((qty ? qty.value : '1').replace(',', '.')) || 0) * m;
            estimate.textContent = rupiah(perMonth) + (m === 0 ? ' (sekali)' : '');
        }

        if (form) {
            form.addEventListener('input', recalcEstimate);
            form.addEventListener('change', recalcEstimate);
            form.addEventListener('ffr:amount', recalcEstimate);
            form.addEventListener('ffr:draftRestored', recalcEstimate);
            form.addEventListener('ffr:cleared', recalcEstimate);
            recalcEstimate();
        }

        // Isi form untuk mengubah item
        document.querySelectorAll('.ffr-edit-item').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var data = JSON.parse(this.dataset.item);

                document.getElementById('ffrItemId').value = data.id;
                document.getElementById('ffrItemName').value = data.name;
                document.getElementById('ffrItemBucket').value = data.bucket_id;
                document.getElementById('ffrItemCategory').value = data.category_id || '';
                document.getElementById('ffrItemFreq').value = data.freq_type;
                document.getElementById('ffrItemPrice').value = formatThousands(data.unit_price);
                document.getElementById('ffrItemQty').value = data.qty;
                // Aktual ikut diisi: kalau dibiarkan kosong, simpan perubahan
                // akan menghapus realisasi yang sudah tercatat.
                var actual = document.getElementById('ffrItemActual');
                if (actual) {
                    actual.value = data.actual_amount === null || data.actual_amount === undefined
                        ? ''
                        : formatThousands(data.actual_amount);
                }
                document.getElementById('ffrItemPriority').checked = data.is_priority === 1;
                document.getElementById('ffrItemNotes').value = data.notes || '';

                var title = document.getElementById('ffrFormTitle');
                var label = document.getElementById('ffrSubmitLabel');
                if (title) title.textContent = 'Ubah Item: ' + data.name;
                if (label) label.textContent = 'Simpan Perubahan';

                var cancel = document.getElementById('ffrCancelEdit');
                if (cancel) cancel.classList.remove('d-none');

                recalcEstimate();
                form.scrollIntoView({ behavior: 'smooth', block: 'center' });
                document.getElementById('ffrItemName').focus();
            });
        });

        var cancelBtn = document.getElementById('ffrCancelEdit');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                form.reset();
                document.getElementById('ffrItemId').value = '';
                var title = document.getElementById('ffrFormTitle');
                var label = document.getElementById('ffrSubmitLabel');
                if (title) title.textContent = 'Tambah Item';
                if (label) label.textContent = 'Tambah Item';
                cancelBtn.classList.add('d-none');
                recalcEstimate();
            });
        }

        // Simpan realisasi (aktual) langsung dari tabel: otomatis setelah
        // selesai mengetik (jeda singkat) maupun saat kolom ditinggalkan,
        // lalu angka turunannya langsung diperbarui tanpa reload.
        var actualTimers = new WeakMap();

        function cancelScheduledSave(input) {
            var timer = actualTimers.get(input);
            if (timer) {
                clearTimeout(timer);
                actualTimers.delete(input);
            }
        }

        function scheduleSave(input, delay) {
            cancelScheduledSave(input);
            actualTimers.set(input, setTimeout(function () {
                actualTimers.delete(input);
                saveActual.call(input);
            }, delay));
        }

        document.querySelectorAll('.ffr-actual').forEach(function (input) {
            // Nilai terakhir yang sudah tersimpan, agar blur setelah auto-save
            // tidak mengirim permintaan yang sama dua kali.
            input.dataset.ffrLastSaved = input.value.trim() === ''
                ? ''
                : String(parseAmount(input.value));

            input.addEventListener('input', function () {
                if (input.disabled) return;
                scheduleSave(input, 1000);
            });

            input.addEventListener('blur', function () {
                cancelScheduledSave(input);
                saveActual.call(input);
            });

            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.blur();
                }
            });
        });

        function saveActual() {
            var input = this;
            var itemId = input.dataset.item;
            if (!itemId) return;

            // Pastikan tampilan selalu bertitik ribuan, termasuk bila nilainya
            // diisi lewat tempel/autofill yang tidak memicu event input.
            var formatted = formatThousands(input.value);
            if (input.value !== formatted) {
                input.value = formatted;
            }

            // Kosong = realisasi dihapus (tersimpan sebagai NULL), bukan nol.
            var amount = input.value.trim() === '' ? null : parseAmount(input.value);

            if (input.dataset.ffrLastSaved === (amount === null ? '' : String(amount))) {
                return;
            }

            var body = new URLSearchParams();
            body.set('item_id', itemId);
            body.set('actual_amount', amount === null ? '' : String(amount));

            // Token CSRF dari form item (endpoint AJAX memverifikasi lewat header).
            var tokenField = document.querySelector('input[name="csrf_token"]');
            var headers = { 'Content-Type': 'application/x-www-form-urlencoded' };
            if (tokenField) {
                headers['X-CSRF-Token'] = tokenField.value;
            }

            input.classList.add('ffr-saving');

            fetch('index.php?page=ffr&action=updateActual', {
                method: 'POST',
                headers: headers,
                credentials: 'same-origin',
                body: body.toString()
            })
                .then(function (res) { return res.json(); })
                .then(function (json) {
                    input.classList.remove('ffr-saving');
                    if (!json.success) {
                        input.classList.add('is-invalid');
                        if (window.AppDialog) {
                            window.AppDialog.toast({
                                tone: 'danger',
                                title: 'Gagal menyimpan',
                                message: json.error || 'Gagal menyimpan.'
                            });
                        } else {
                            alert(json.error || 'Gagal menyimpan.');
                        }
                    } else {
                        input.classList.remove('is-invalid');
                        input.dataset.ffrLastSaved = amount === null ? '' : String(amount);
                        applyActualResult(json);
                    }
                })
                .catch(function () {
                    input.classList.remove('ffr-saving');
                    input.classList.add('is-invalid');
                });
        }
    }

    // ---------------- Layar ubah alokasi dari Daftar Belanja ----------------

    function initItemBucketSync() {
        var select = document.getElementById('ffrItemBucket');
        if (!select) return;
        select.addEventListener('change', function () {
            var id = this.value;
            var period = new URLSearchParams(window.location.search).get('period') || '';
            window.location.href = 'index.php?page=ffr&action=items&period=' + encodeURIComponent(period) + '&bucket=' + id;
        });
    }

    // ---------------- Pemilih periode (Bulan + Tahun) ----------------

    function initPeriodPicker() {
        var form = document.getElementById('ffrPeriodForm');
        if (!form) return;

        // Ganti bulan/tahun langsung memuat periode yang dipilih.
        form.querySelectorAll('.ffr-period-select').forEach(function (select) {
            select.addEventListener('change', function () {
                form.submit();
            });
        });
    }

    // ---------------- Tabunganku: ubah harga/saldo langsung dari tabel ----------------

    function initSavingsPage() {
        var inputs = document.querySelectorAll('.sv-price, .sv-qty');
        if (!inputs.length) return;

        inputs.forEach(function (input) {
            input.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                // Enter = simpan angka baru tanpa perlu klik apa pun.
                if (input.form) input.form.submit();
            });
        });
    }

    // ---------------- Tabunganku: transaksi aset (tarik/jual, transfer) ----------------

    /**
     * Modal transaksi: setor, tarik/jual, pindah ke aset lain, atau koreksi.
     * Semua perhitungan di sini hanya pratinjau; angka final dihitung ulang
     * dan divalidasi di server (SavingsTransactionService).
     */
    function initSavingsTransactions() {
        var modalEl = document.getElementById('svTxModal');
        var form = document.getElementById('svTxForm');
        if (!modalEl || !form || typeof bootstrap === 'undefined') return;

        var assets = window.SV_TX_ASSETS || [];
        var byId = {};
        assets.forEach(function (asset) { byId[String(asset.id)] = asset; });

        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        var assetIdInput = document.getElementById('svTxAssetId');
        var assetInfo = document.getElementById('svTxAssetInfo');
        var qtyInput = document.getElementById('svTxQty');
        var qtyLabel = document.getElementById('svTxQtyLabel');
        var qtyHelp = document.getElementById('svTxQtyHelp');
        var priceWrap = document.getElementById('svTxPriceWrap');
        var priceInput = document.getElementById('svTxPrice');
        var priceLabel = document.getElementById('svTxPriceLabel');
        var priceHelp = document.getElementById('svTxPriceHelp');
        var feeInput = document.getElementById('svTxFee');
        var taxInput = document.getElementById('svTxTax');
        var categoryWrap = document.getElementById('svTxCategoryWrap');
        var categoryInput = document.getElementById('svTxCategory');
        var targetWrap = document.getElementById('svTxTargetWrap');
        var targetSelect = document.getElementById('svTxTarget');
        var targetPriceInput = document.getElementById('svTxTargetPrice');
        var targetHelp = document.getElementById('svTxTargetHelp');
        var badge = document.getElementById('svTxBadge');
        var noteInfo = document.getElementById('svTxNoteInfo');
        var submit = document.getElementById('svTxSubmit');
        var current = null;
        var currentType = 'out';

        function typeRadio() {
            var checked = form.querySelector('input[name="tx_type"]:checked');
            return checked ? checked.value : 'out';
        }

        /** Angka dari teks: "1.500.000" → 1500000, "0,5" → 0.5 */
        function num(text, allowDecimal) {
            var clean = String(text == null ? '' : text).replace(/[^0-9,.\-]/g, '');
            if (clean === '') return 0;
            if (clean.indexOf(',') !== -1) {
                return parseFloat(clean.replace(/\./g, '').replace(',', '.')) || 0;
            }
            if (allowDecimal && /^-?\d*\.\d{1,4}$/.test(clean)) {
                return parseFloat(clean) || 0;
            }
            return parseFloat(clean.replace(/\./g, '')) || 0;
        }

        function fmtQty(value, asset) {
            if (asset && asset.cash) {
                return 'Rp ' + (Number(value) || 0).toLocaleString('id-ID', {
                    minimumFractionDigits: 2, maximumFractionDigits: 2
                });
            }
            var text = (Number(value) || 0).toLocaleString('id-ID', { maximumFractionDigits: 4 });
            return text + ' ' + ((asset && asset.unit) || '');
        }

        function unitValue(asset, price) {
            if (!asset || asset.cash) return 1;
            var divider = asset.mode === 'percent' ? 100 : 1;
            var rate = Number(asset.exchange_rate) > 0 ? Number(asset.exchange_rate) : 1;
            return (Number(price) || 0) / divider * rate;
        }

        function setText(id, text) {
            var el = document.getElementById(id);
            if (el) el.textContent = text;
        }

        function put(id, text, cls) {
            var el = document.getElementById(id);
            if (!el) return;
            el.textContent = text;
            el.classList.remove('text-success', 'text-danger', 'text-muted');
            if (cls) el.classList.add(cls);
        }

        function show(el, visible) {
            if (!el) return;
            el.classList.toggle('d-none', !visible);
        }

        function defaultPriceFor(asset, type) {
            if (!asset || asset.cash) return 1;
            if (type === 'out' || type === 'transfer') {
                return Number(asset.sell_price) > 0 ? asset.sell_price : asset.price;
            }
            return Number(asset.price) > 0 ? asset.price : asset.avg_price;
        }

        function applyMode() {
            currentType = typeRadio();
            var asset = current;
            if (!asset) return;

            var isOut = currentType === 'out';
            var isIn = currentType === 'in';
            var isTransfer = currentType === 'transfer';
            var isAdjust = currentType === 'adjust';

            var labels = {
                out: ['Jumlah Ditarik', 'Harga Jual / Buyback', 'Tarik / Jual'],
                in: ['Jumlah Disetor', 'Harga Beli', 'Setor / Tambah'],
                transfer: ['Jumlah Dipindah', 'Harga Jual Aset Sumber', 'Pindah Aset'],
                adjust: ['Selisih Koreksi (+/−)', 'Harga Acuan', 'Koreksi']
            };
            var label = labels[currentType] || labels.out;

            qtyLabel.textContent = label[0];
            priceLabel.textContent = label[1];
            badge.textContent = label[2];

            var unit = asset.cash ? 'Rupiah' : asset.unit;
            qtyHelp.textContent = isAdjust
                ? 'Pakai tanda minus untuk mengurangi, mis. -2.'
                : 'Saldo saat ini: ' + fmtQty(asset.quantity, asset) + ' · satuan ' + unit;
            priceHelp.textContent = asset.cash
                ? 'Dana cash selalu bernilai 1 rupiah per rupiah.'
                : 'Harga per ' + unit + ' (boleh diubah).';

            show(priceWrap, !asset.cash);
            show(categoryWrap, !isAdjust);
            show(targetWrap, isTransfer);
            document.querySelectorAll('.sv-tx-cost').forEach(function (el) {
                el.classList.toggle('d-none', isAdjust);
            });

            if (priceInput) {
                if (asset.cash) {
                    priceInput.value = '1';
                } else if (!priceInput.value || priceInput.dataset.auto === '1') {
                    priceInput.value = formatThousands(String(Math.round(defaultPriceFor(asset, currentType))));
                    priceInput.dataset.auto = '1';
                }
            }

            if (isTransfer && targetSelect) {
                Array.prototype.forEach.call(targetSelect.options, function (option) {
                    if (!option.value) return;
                    option.disabled = String(option.value) === String(asset.id);
                });
                if (targetSelect.value && String(targetSelect.value) === String(asset.id)) {
                    targetSelect.value = '';
                }
                applyTargetDefault();
            }

            if (categoryInput) {
                if (isAdjust) {
                    categoryInput.value = 'koreksi';
                } else if (categoryInput.value === 'koreksi') {
                    categoryInput.value = isTransfer ? 'rebalance' : 'rumah_tangga';
                } else if (isTransfer && categoryInput.value === 'rumah_tangga') {
                    categoryInput.value = 'rebalance';
                }
            }

            if (submit) {
                submit.className = 'btn btn-sm ' + (isOut ? 'btn-danger' : isIn ? 'btn-success'
                    : isTransfer ? 'btn-primary' : 'btn-warning');
            }

            compute();
        }

        function applyTargetDefault() {
            if (!targetSelect) return;
            var target = byId[targetSelect.value] || null;
            if (!target) {
                if (targetPriceInput) targetPriceInput.value = '';
                if (targetHelp) targetHelp.textContent = 'Harga beli per unit di aset tujuan (kosong = pakai harga kini).';
                return;
            }
            if (target.cash) {
                if (targetPriceInput) targetPriceInput.value = '1';
                if (targetHelp) targetHelp.textContent = 'Tujuan berupa dana cash: nilai Rupiah masuk apa adanya.';
            } else {
                if (targetPriceInput) {
                    targetPriceInput.value = formatThousands(String(Math.round(
                        Number(target.price) > 0 ? target.price : target.avg_price
                    )));
                }
                if (targetHelp) {
                    targetHelp.textContent = 'Harga beli per ' + target.unit + ' di aset tujuan.';
                }
            }
        }

        function compute() {
            var asset = current;
            if (!asset) return;

            var rawQty = qtyInput.value;
            var signed = currentType === 'adjust' && String(rawQty).trim().indexOf('-') === 0;
            var qty = Math.abs(num(rawQty, true));

            var price = asset.cash ? 1 : num(priceInput.value, false);
            var gross = qty * unitValue(asset, price);
            var fee = num(feeInput.value, false);
            var tax = num(taxInput.value, false);

            var net = gross;
            if (currentType === 'out' || currentType === 'transfer') {
                net = Math.max(0, gross - fee - tax);
            } else if (currentType === 'in') {
                net = gross + fee + tax;
            }

            var costBasis = qty * unitValue(asset, Number(asset.avg_price) || 0);
            var realized = net - costBasis;

            var delta = currentType === 'in' ? qty
                : currentType === 'adjust' ? (signed ? -qty : qty)
                : -qty;
            var after = Number(asset.quantity) + delta;

            put('svTxGross', rupiah(gross));
            put('svTxNet', rupiah(net));

            if (currentType === 'in' || currentType === 'adjust' || asset.cash) {
                put('svTxRealized', '—', 'text-muted');
            } else {
                put('svTxRealized',
                    (realized < 0 ? '−' : '+') + rupiah(Math.abs(realized)),
                    Math.abs(realized) < 1 ? 'text-muted' : (realized > 0 ? 'text-success' : 'text-danger'));
            }

            put('svTxBalance',
                after < -0.00005
                    ? '−' + fmtQty(Math.abs(after), asset)
                    : fmtQty(Math.max(0, after), asset),
                after < -0.00005 ? 'text-danger' : '');

            var messages = [];
            if ((currentType === 'out' || currentType === 'transfer') && qty > Number(asset.quantity) + 0.00005) {
                messages.push('<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> ' +
                    'Jumlah melebihi saldo.</span>');
            }
            if (currentType === 'transfer') {
                var target = byId[targetSelect.value] || null;
                if (!target) {
                    messages.push('Pilih aset tujuan.');
                } else {
                    var targetPrice = target.cash ? 1 : num(targetPriceInput.value, false);
                    var targetUnit = unitValue(target, targetPrice);
                    var targetQty = targetUnit > 0 ? net / targetUnit : 0;
                    messages.push('Masuk ke "' + target.name + '": <strong>' + fmtQty(targetQty, target) + '</strong>');
                }
            }
            if (currentType === 'out' && Math.abs(after) <= 0.00005 && qty > 0) {
                messages.push('Saldo akan habis — aset otomatis diarsipkan.');
            }
            noteInfo.innerHTML = messages.join('<br>');
        }

        function openFor(assetId, mode) {
            current = byId[String(assetId)] || null;
            if (!current) return;

            assetIdInput.value = current.id;
            var radio = document.getElementById(mode === 'in' ? 'svTxTypeIn' : 'svTxTypeOut');
            if (radio) radio.checked = true;

            assetInfo.innerHTML = '<strong>' + current.name + '</strong> · ' +
                current.type_label + '<br><span class="text-muted">Saldo: ' +
                fmtQty(current.quantity, current) + '</span>';

            qtyInput.value = '';
            feeInput.value = '';
            taxInput.value = '';
            targetSelect.value = '';
            targetPriceInput.value = '';
            if (priceInput) {
                priceInput.value = formatThousands(String(Math.round(defaultPriceFor(current, mode))));
                priceInput.dataset.auto = '1';
            }
            if (document.getElementById('svTxDate')) {
                document.getElementById('svTxDate').value = new Date().toISOString().slice(0, 10);
            }

            applyMode();
            modal.show();
        }

        document.querySelectorAll('.sv-tx-open').forEach(function (button) {
            button.addEventListener('click', function () {
                openFor(this.getAttribute('data-asset-id'), this.getAttribute('data-tx-mode') || 'out');
            });
        });

        form.querySelectorAll('input[name="tx_type"]').forEach(function (radio) {
            radio.addEventListener('change', applyMode);
        });
        if (priceInput) {
            priceInput.addEventListener('input', function () { this.dataset.auto = '0'; });
        }
        if (targetSelect) targetSelect.addEventListener('change', function () { applyTargetDefault(); compute(); });
        form.addEventListener('input', compute);
        form.addEventListener('change', compute);

        // Angka di modal tidak memakai format ribuan otomatis agar desimal
        // (mis. 0,5 gram) tidak berubah saat diketik.
        [qtyInput, priceInput, feeInput, taxInput, targetPriceInput].forEach(function (input) {
            if (input) input.dataset.ffrDecimal = '1';
        });
    }


    /**
     * Angka gaya Indonesia: "1.500.000" → 1500000, "0,5" → 0.5.
     * Elemen ber-class .ffr-amount selalu memakai titik sebagai pemisah
     * ribuan; kolom lain (berat, persen) menerima titik desimal juga.
     */
    function svNumber(el) {
        if (!el) return 0;
        var text = String(el.value == null ? '' : el.value).replace(/[^0-9,.\-]/g, '');
        if (text === '') return 0;
        var moneyField = el.classList && el.classList.contains('ffr-amount');
        var decimalField = el.dataset && el.dataset.ffrDecimal === '1';
        if (!moneyField || decimalField) {
            var match = /^-?\d*\.(\d+)$/.exec(text);
            if (text.indexOf(',') === -1 && match && match[1].length !== 3) {
                var dec = parseFloat(text);
                return isNaN(dec) ? 0 : dec;
            }
        }
        text = text.replace(/\./g, '').replace(',', '.');
        var num = parseFloat(text);
        return isNaN(num) ? 0 : num;
    }

    function svRupiah(value) {
        return rupiah(value);
    }

    /** Saldo dana: selalu dua desimal, mis. "Rp 5.000.000,50". */
    function svRupiah2(value) {
        var n = Number(value) || 0;
        return 'Rp ' + n.toLocaleString('id-ID', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // ---------------- Tabunganku: form per jenis aset + prediksi ----------------

    function initSavingsForm() {
        var form = document.getElementById('svAssetForm');
        if (!form) return;

        var config = window.SV_FORM_CONFIG || {};
        var institutions = window.SV_INSTITUTIONS || {};
        var datalist = document.getElementById('svInstitutionList');
        var institution = document.getElementById('svInstitution');
        var unitInput = document.getElementById('svUnit');
        var currency = document.getElementById('svCurrency');
        var rateInput = document.getElementById('svRate');

        // Elemen khusus emas (harga otomatis + panel perbandingan).
        var goldBrand = document.getElementById('svGoldBrand');
        var goldWeight = document.getElementById('svGoldWeight');
        var goldFetch = document.getElementById('svGoldFetch');
        var goldInfo = document.getElementById('svGoldFetchInfo');
        var goldCard = document.getElementById('svGoldCompareCard');
        var goldCompareWeight = document.getElementById('svGoldCompareWeight');
        var goldCompareBody = document.getElementById('svGoldCompareBody');
        var goldCompareMeta = document.getElementById('svGoldCompareMeta');
        var priceSourceInput = document.getElementById('svPriceSource');
        var priceFetchedInput = document.getElementById('svPriceFetchedAt');
        var compareToken = 0;

        function byId(id) { return document.getElementById(id); }

        function esc(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function shortDate(value) {
            if (!value) return '';
            var parts = String(value).split(' ')[0].split('-');
            return parts.length === 3 ? parts[2] + '/' + parts[1] + '/' + parts[0] : value;
        }

        function renderGoldComparison(res) {
            if (!goldCompareBody) return;

            var spotText = '—';
            if (res.spot && res.spot.idr_gram) {
                spotText = svRupiah(res.spot.idr_gram) + '/gram';
                if (res.spot.usd_oz) {
                    spotText += ' · XAU/USD $' + Number(res.spot.usd_oz).toLocaleString('id-ID', { maximumFractionDigits: 2 });
                }
            }

            if (goldCompareMeta) {
                goldCompareMeta.innerHTML = res.date
                    ? 'Per <strong>' + esc(shortDate(res.date)) + '</strong> · spot ' + esc(spotText)
                    : 'Belum ada harga tersimpan.';
            }

            if (!res.rows || !res.rows.length) {
                goldCompareBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">' +
                    'Belum ada harga. Buka halaman Harga Emas untuk mengisi.</td></tr>';
                return;
            }

            var selectedBrand = goldBrand ? goldBrand.value : '';
            var html = res.rows.map(function (row) {
                var tags = (row.tags || []).map(function (tag) {
                    var label = tag === 'termurah' ? 'Termurah'
                        : tag === 'buyback-terbaik' ? 'Buyback terbaik'
                        : tag === 'spread-tersempit' ? 'Spread tersempit' : tag;
                    return '<span class="badge bg-success-subtle text-success border border-success ms-1">' +
                        esc(label) + '</span>';
                }).join('');

                var spread = row.spread === null || row.spread === undefined
                    ? '—'
                    : percentText(row.spread) + '%';
                var premium = row.premium === null || row.premium === undefined
                    ? '—'
                    : (row.premium > 0 ? '+' : '') + percentText(row.premium) + '%';

                return '<tr class="' + (row.brand === selectedBrand ? 'table-active' : '') + '">' +
                    '<td><strong>' + esc(row.label) + '</strong>' + tags + '</td>' +
                    '<td class="text-end">' + svRupiah(row.buy_per_gram) + '</td>' +
                    '<td class="text-end">' + (row.buyback_per_gram !== null ? svRupiah(row.buyback_per_gram) : '—') + '</td>' +
                    '<td class="text-end ' + (row.spread > -0.005 ? '' : 'text-muted') + '">' + esc(spread) + '</td>' +
                    '<td class="text-end">' + esc(premium) + '</td>' +
                    '<td class="text-end small text-muted">' + esc(row.source) +
                    (row.stale ? ' <span class="badge bg-warning-subtle text-dark border">lama</span>' : '') +
                    '</td></tr>';
            }).join('');

            goldCompareBody.innerHTML = html;
        }

        function loadGoldComparison(weight) {
            if (!goldCompareBody) return;

            var token = ++compareToken;
            goldCompareBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Memuat…</td></tr>';

            fetch('index.php?page=ffr&action=goldCompare&weight=' + encodeURIComponent(weight), {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
                .then(function (response) { return response.json(); })
                .then(function (res) {
                    if (token !== compareToken) return;
                    if (!res || !res.ok) throw new Error('unavailable');
                    renderGoldComparison(res);
                })
                .catch(function () {
                    if (token !== compareToken) return;
                    goldCompareBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">' +
                        'Harga perbandingan belum tersedia.</td></tr>';
                    if (goldCompareMeta) goldCompareMeta.textContent = 'Isi harga di halaman Harga Emas.';
                });
        }

        function fetchGoldPrice() {
            if (!goldFetch || !goldBrand || !goldWeight) return;

            var brand = goldBrand.value;
            var weight = goldWeight.value;
            goldFetch.disabled = true;
            if (goldInfo) goldInfo.textContent = 'Mengambil harga…';

            fetch('index.php?page=ffr&action=fetchGoldPrice&brand=' + encodeURIComponent(brand) +
                '&weight=' + encodeURIComponent(weight), {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
                .then(function (response) { return response.json(); })
                .then(function (res) {
                    if (!res || !res.ok) {
                        if (goldInfo) goldInfo.textContent = (res && res.message) || 'Harga tidak tersedia.';
                        return;
                    }

                    var buyField = byId('svNow');
                    var sellField = byId('svSell');
                    if (buyField) buyField.value = formatThousands(String(Math.round(res.buy_per_gram)));
                    if (sellField && res.buyback_per_gram !== null) {
                        sellField.value = formatThousands(String(Math.round(res.buyback_per_gram)));
                    }
                    if (byId('svPriceDate')) byId('svPriceDate').value = res.price_date;
                    if (priceSourceInput) priceSourceInput.value = res.source;
                    if (priceFetchedInput) priceFetchedInput.value = res.fetched_at || '';

                    if (goldInfo) {
                        goldInfo.innerHTML = '<i class="bi bi-check2-circle text-success"></i> ' +
                            esc(res.label) + ' ' + esc(weight) + ' g · ' +
                            svRupiah(res.buy_per_gram) + '/gr · BB ' +
                            (res.buyback_per_gram !== null ? svRupiah(res.buyback_per_gram) + '/gr' : '—') +
                            ' · ' + esc(shortDate(res.price_date)) +
                            (res.stale ? ' (data lama)' : '');
                    }

                    compute();
                    loadGoldComparison(weight);
                })
                .catch(function () {
                    if (goldInfo) goldInfo.textContent = 'Gagal mengambil harga. Isi manual atau coba lagi.';
                })
                .finally(function () {
                    goldFetch.disabled = false;
                });
        }

        function syncGoldWeight(previous) {
            if (!goldWeight) return;
            var weight = goldWeight.value;
            var qty = byId('svQty');
            if (qty && (qty.value === '' || qty.value === previous)) {
                qty.value = weight;
            }
            if (goldCompareWeight && goldCompareWeight.value !== weight) {
                goldCompareWeight.value = weight;
            }
        }

        if (goldFetch) {
            goldFetch.addEventListener('click', fetchGoldPrice);
        }
        if (goldWeight) {
            goldWeight.dataset.prev = goldWeight.value;
            goldWeight.addEventListener('change', function () {
                syncGoldWeight(this.dataset.prev || '');
                this.dataset.prev = this.value;
                compute();
                loadGoldComparison(this.value);
            });
        }
        if (goldCompareWeight) {
            goldCompareWeight.addEventListener('change', function () {
                loadGoldComparison(this.value);
            });
        }
        if (goldBrand) {
            goldBrand.addEventListener('change', function () {
                loadGoldComparison(goldWeight ? goldWeight.value : '1');
            });
        }


        function currentType() {
            var checked = form.querySelector('[data-sv-type]:checked');
            return checked ? checked.value : 'tabungan';
        }

        function setText(id, text) {
            var el = byId(id);
            if (el && text) el.textContent = text;
        }

        function setPlaceholder(id, text) {
            var el = byId(id);
            if (el && text) el.placeholder = text;
        }

        function refreshInstitutionOptions(type) {
            if (!datalist) return;
            datalist.innerHTML = '';
            (institutions[type] || []).forEach(function (name) {
                var option = document.createElement('option');
                option.value = name;
                datalist.appendChild(option);
            });
        }

        // Draf per jenis aset: isi yang belum disimpan tidak hilang saat
        // berpindah kategori, dan kategori baru mulai dari kolom kosong
        // (bukan membawa angka kategori sebelumnya).
        var DRAFT_FIELDS = [
            'svInstitution', 'svTicker', 'svQty', 'svAvg', 'svNow', 'svSell',
            'svFeeBuy', 'svFeeSell', 'svTaxSell', 'svCoupon', 'svMaturity',
            'svCurrency', 'svRate', 'svGoldBrand', 'svGoldWeight'
        ];
        var drafts = {};
        var lastType = '';

        // Dana cash & deposito berbagi draf (sama-sama saldo Rupiah).
        function draftKey(type) {
            return (type === 'tabungan' || type === 'deposito') ? 'cash' : type;
        }

        function saveDraft(type) {
            var state = {};
            DRAFT_FIELDS.forEach(function (id) {
                var el = byId(id);
                if (el) state[id] = el.value;
            });
            drafts[draftKey(type)] = state;
        }

        function loadDraft(type) {
            var state = drafts[draftKey(type)] || null;
            DRAFT_FIELDS.forEach(function (id) {
                var el = byId(id);
                if (!el) return;
                el.value = (state && Object.prototype.hasOwnProperty.call(state, id))
                    ? state[id]
                    : '';
            });
        }

        function applyType() {
            var type = currentType();
            var cfg = config[type] || {};
            var isCash = type === 'tabungan' || type === 'deposito';

            form.querySelectorAll('[data-sv-show], [data-sv-hide]').forEach(function (el) {
                var hideList = el.hasAttribute('data-sv-hide');
                var list = el.getAttribute(hideList ? 'data-sv-hide' : 'data-sv-show').split(/\s+/);
                var matched = list.indexOf(type) !== -1;
                var hidden = hideList ? matched : !matched;

                el.classList.toggle('d-none', hidden);
                // Kolom tersembunyi dinonaktifkan agar angka kategori lain
                // tidak ikut terkirim saat form disimpan.
                el.querySelectorAll('input, select, textarea').forEach(function (field) {
                    field.disabled = hidden;
                });
            });

            setText('svNameLabel', cfg.nameLabel);
            setPlaceholder('svName', cfg.namePlaceholder);
            setText('svInstitutionLabel', cfg.institutionLabel);
            setPlaceholder('svInstitution', cfg.institutionPlaceholder);
            setText('svQtyLabel', cfg.qtyLabel);
            setText('svQtyHelp', cfg.qtyHelp);
            setPlaceholder('svQty', cfg.qtyPlaceholder);
            setText('svAvgLabel', cfg.avgLabel);
            setText('svNowLabel', cfg.nowLabel);
            setText('svSellLabel', cfg.sellLabel);

            var unit = type === 'valas' && currency ? currency.value : (cfg.unit || 'unit');
            // Valas hampir selalu mata uang asing: IDR bukan pilihan default.
            if (type === 'valas' && currency && currency.value === 'IDR') {
                currency.value = 'USD';
                unit = 'USD';
            }
            if (unitInput) unitInput.value = unit;

            // Mata uang & kurs hanya relevan untuk valas.
            if (type !== 'valas') {
                if (currency) currency.value = 'IDR';
                if (rateInput) rateInput.value = '1';
            }

            refreshInstitutionOptions(type);

            // Dana cash/deposito: nama bank diketik manual (tanpa saran),
            // karena daftar bank bisa berbeda-beda.
            if (institution) {
                if (isCash) {
                    institution.removeAttribute('list');
                } else {
                    institution.setAttribute('list', 'svInstitutionList');
                }
            }

            // Saldo dana memakai dua desimal: 5.000.000,50.
            var qtyInput = byId('svQty');
            if (qtyInput) {
                if (isCash) {
                    qtyInput.classList.add('ffr-amount');
                    qtyInput.dataset.ffrMoney2 = '1';
                    bindAmountInputs(form);
                } else {
                    qtyInput.classList.remove('ffr-amount');
                    delete qtyInput.dataset.ffrMoney2;
                }
            }

            // Harga obligasi (%) dan NAB reksa dana boleh desimal.
            var decimalPrices = type === 'obligasi' || type === 'reksadana';
            ['svAvg', 'svNow', 'svSell'].forEach(function (id) {
                var el = byId(id);
                if (!el) return;
                if (decimalPrices) {
                    el.dataset.ffrDecimal = '1';
                } else {
                    delete el.dataset.ffrDecimal;
                }
            });

            var badge = byId('svPredType');
            if (badge) badge.textContent = cfg.label || type;

            var valueLabel = byId('svPredValueLabel');
            if (valueLabel) valueLabel.textContent = isCash ? 'Saldo' : 'Nilai pasar';

            var sellLabel = byId('svPredSellLabel');
            if (sellLabel) {
                sellLabel.textContent = isCash
                    ? 'Nilai bersih'
                    : (type === 'emas' || type === 'valas' ? 'Hasil jual (buyback)' : 'Hasil jual (net)');
            }

            // Panel perbandingan emas hanya relevan untuk kategori emas.
            if (goldCard) {
                goldCard.classList.toggle('d-none', type !== 'emas');
                if (type === 'emas') {
                    if (goldWeight && !goldWeight.value) goldWeight.value = '1';
                    if (goldCompareWeight && goldWeight) goldCompareWeight.value = goldWeight.value;
                    loadGoldComparison(goldWeight ? goldWeight.value : '1');
                }
            }

            compute();
            lastType = type;
        }

        function compute() {
            var type = currentType();
            var cfg = config[type] || {};
            var isCash = type === 'tabungan' || type === 'deposito';

            var qty = svNumber(byId('svQty'));
            var avg = isCash ? 1 : svNumber(byId('svAvg'));
            var now = isCash ? 1 : svNumber(byId('svNow'));
            var sell = isCash ? 1 : svNumber(byId('svSell'));
            if (!sell) sell = now;

            var rate = type === 'valas' ? (svNumber(rateInput) || 1) : 1;
            var divisor = type === 'obligasi' ? 100 : 1;
            var feeBuy = svNumber(byId('svFeeBuy')) / 100;
            var feeSell = svNumber(byId('svFeeSell')) / 100;
            var taxSell = svNumber(byId('svTaxSell')) / 100;

            var costGross = qty * avg / divisor * rate;
            var cost = costGross * (1 + feeBuy);
            var gross = qty * now / divisor * rate;
            var sellGross = qty * sell / divisor * rate;
            var net = sellGross * (1 - feeSell - taxSell);
            var gain = net - cost;
            var unit = (unitInput && unitInput.value) || cfg.unit || 'unit';

            function put(id, text, cls) {
                var el = byId(id);
                if (!el) return;
                el.textContent = text;
                el.classList.remove('text-success', 'text-danger', 'text-muted');
                if (cls) el.classList.add(cls);
            }

            var money = isCash ? svRupiah2 : svRupiah;
            var noGain = isCash || Math.abs(gain) < 0.005;

            put('svPredCost', money(cost));
            put('svPredValue', isCash ? money(qty) : svRupiah(gross));
            put('svPredSell', money(net));
            put('svPredGain',
                noGain
                    ? money(0)
                    : (gain < 0 ? '−' : '+') + money(Math.abs(gain)),
                noGain ? 'text-muted' : (gain > 0 ? 'text-success' : 'text-danger'));

            if (!noGain && cost > 0) {
                var pct = (gain / cost) * 100;
                var gainEl = byId('svPredGain');
                if (gainEl) gainEl.textContent += ' (' + (pct >= 0 ? '+' : '') + percentText(pct) + '%)';
            }

            // Keterangan tambahan per jenis.
            var extra = byId('svPredExtra');
            var note = byId('svPredNote');
            if (extra) extra.innerHTML = '';
            if (note) note.textContent = '';

            var lines = [];

            if (isCash) {
                if (note) note.textContent = 'Dana cash dinilai sebesar saldo yang tersimpan.';
                if (type === 'deposito') {
                    var coupon = svNumber(byId('svCoupon'));
                    if (coupon > 0) {
                        var interestYear = qty * coupon / 100;
                        lines.push('Bunga per tahun: <strong>' + money(interestYear) + '</strong> (' +
                            percentText(coupon) + '% dari pokok).');
                    }
                    var maturity = byId('svMaturity') && byId('svMaturity').value;
                    if (maturity) {
                        var days = Math.max(0, Math.round((new Date(maturity) - new Date()) / 86400000));
                        lines.push('Jatuh tempo <strong>' + maturity.split('-').reverse().join('/') + '</strong> (' +
                            days + ' hari lagi).');
                    }
                }
            } else {
                var qtyText = (qty > 0 ? qty.toLocaleString('id-ID') : '0') + ' ' + unit;
                var priceDate = byId('svPriceDate') && byId('svPriceDate').value;
                var dateText = priceDate ? ' (per ' + priceDate.split('-').reverse().join('/') + ')' : '';
                var isPercentPrice = type === 'obligasi';
                var priceText = function (value) {
                    if (isPercentPrice) {
                        return percentText(value) + '%';
                    }
                    var text = type === 'reksadana'
                        ? 'Rp ' + Number(value).toLocaleString('id-ID', { maximumFractionDigits: 2 })
                        : svRupiah(value);
                    return text + '/' + unit;
                };
                var sellWord = (type === 'emas' || type === 'valas') ? 'jual/buyback' : 'jual';

                lines.push('Jumlah: <strong>' + qtyText + '</strong> · harga beli ' + priceText(avg) +
                    ' · harga kini ' + priceText(now) + dateText + '.');

                var buyNow = gross * (1 + feeBuy);
                lines.push('Bila beli hari ini: <strong>' + svRupiah(buyNow) + '</strong>' +
                    (feeBuy > 0 ? ' (termasuk biaya beli ' + percentText(feeBuy * 100) + '%)' : '') + '.');

                if (sell !== now) {
                    lines.push('Bila jual hari ini di harga ' + sellWord + ' <strong>' + priceText(sell) +
                        '</strong>: <strong>' + svRupiah(net) + '</strong> bersih' +
                        ((feeSell + taxSell) > 0
                            ? ' (setelah biaya & pajak ' + percentText((feeSell + taxSell) * 100) + '%)'
                            : '') + '.');
                } else {
                    lines.push('Bila jual hari ini: <strong>' + svRupiah(net) + '</strong> bersih' +
                        ((feeSell + taxSell) > 0
                            ? ' (setelah biaya & pajak ' + percentText((feeSell + taxSell) * 100) + '%)'
                            : '') + '.');
                }

                var couponRate = svNumber(byId('svCoupon'));
                if (type === 'obligasi' && couponRate > 0) {
                    lines.push('Kupon per tahun: <strong>' + svRupiah(qty * couponRate / 100) + '</strong>.');
                }
            }

            if (extra) {
                extra.innerHTML = lines.map(function (line) {
                    return '<div>' + line + '</div>';
                }).join('');
            }

            if (note) {
                if (type === 'emas') {
                    note.textContent = 'Harga jual memakai harga buyback hari ini. Untung/rugi dihitung dari hasil jual bersih dikurangi modal.';
                } else if (type === 'valas') {
                    note.textContent = 'Kurs jual = harga bank membeli valas Anda (buyback). Kurs beli = harga saat Anda menukar. Isi kurs ke Rupiah = 1 bila harga sudah dalam Rupiah.';
                } else if (type === 'saham' || type === 'reksadana' || type === 'kripto') {
                    note.textContent = 'Bila harga jual dikosongkan, prediksi memakai harga kini. Biaya & pajak bisa diisi pada bagian opsional.';
                }
            }
        }

        form.querySelectorAll('[data-sv-type]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                // Simpan isi kategori lama (walau belum disubmit) lalu pulihkan
                // isi kategori baru. Kolom yang tidak dipakai kategori baru
                // menjadi kosong, bukan membawa angka kategori sebelumnya.
                saveDraft(lastType || currentType());
                loadDraft(this.value);
                applyType();
            });
        });
        if (currency) currency.addEventListener('change', applyType);
        form.addEventListener('input', compute);
        form.addEventListener('change', compute);

        // Draf awal diambil dari data yang sedang ditampilkan (mode ubah).
        lastType = currentType();
        saveDraft(lastType);
        applyType();
    }

    // ---------------- Ledger: cetak, PDF/gambar, bagikan ----------------
    var LEDGER_LIBS = [
        'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js',
        'https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js'
    ];
    var ledgerLibsPromise = null;
    // Hasil render terakhir: canvas dipakai ulang untuk PDF maupun PNG,
    // jadi mengganti format tidak perlu render ulang.
    var ledgerShare = { canvas: null, pdfBlob: null, pngBlob: null, meta: null, busy: false };

    var NAMA_BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    function stampWib() {
        var now = new Date();
        var wib = new Date(now.getTime() + (now.getTimezoneOffset() * 60000) + (7 * 3600000));
        var pad = function (n) { return String(n).padStart(2, '0'); };
        return 'Dibuat ' + wib.getDate() + ' ' + NAMA_BULAN[wib.getMonth()] + ' ' + wib.getFullYear() +
            ', ' + pad(wib.getHours()) + ':' + pad(wib.getMinutes()) + ' WIB';
    }

    function loadScriptOnce(src) {
        return new Promise(function (resolve, reject) {
            var found = document.querySelector('script[data-lib="' + src + '"]');
            if (found) {
                if (found.dataset.loaded === '1') { resolve(); return; }
                found.addEventListener('load', function () { resolve(); });
                found.addEventListener('error', function () { reject(new Error('Gagal memuat ' + src)); });
                return;
            }
            var s = document.createElement('script');
            s.src = src;
            s.async = true;
            s.dataset.lib = src;
            s.onload = function () { s.dataset.loaded = '1'; resolve(); };
            s.onerror = function () { reject(new Error('Gagal memuat ' + src)); };
            document.head.appendChild(s);
        });
    }

    // Pustaka PDF/gambar dimuat saat dibutuhkan saja (bukan tiap buka halaman).
    function loadLedgerLibs() {
        if (!ledgerLibsPromise) {
            ledgerLibsPromise = Promise.all(LEDGER_LIBS.map(loadScriptOnce));
        }
        return ledgerLibsPromise;
    }

    // Geometri ledger saat dirender: lebar/tinggi CSS + posisi tiap kartu.
    // Disimpan karena kelas .ledger-exporting (lebar 1120px) dilepas lagi
    // setelah capture, sehingga pengukuran berikutnya akan berbeda.
    var ledgerLayout = null;

    function renderLedgerCanvas() {
        var el = document.getElementById('ledgerCapture');
        var stamp = document.getElementById('ledgerExportStamp');
        if (!el || !window.html2canvas) {
            return Promise.reject(new Error('Pustaka gambar belum siap.'));
        }
        if (stamp) stamp.textContent = stampWib();

        // Lebar tetap supaya hasil di ponsel & komputer sama, dan tabel tidak
        // terpotong oleh .table-responsive saat dirender.
        document.body.classList.add('ledger-exporting');

        var box = el.getBoundingClientRect();
        ledgerLayout = {
            width: box.width,
            height: box.height,
            children: Array.prototype.map.call(el.children, function (child) {
                var b = child.getBoundingClientRect();
                return { top: b.top - box.top, height: b.height };
            })
        };

        var scale = el.scrollHeight > 4000 ? 1.5 : 2;

        return window.html2canvas(el, {
            backgroundColor: '#ffffff',
            scale: scale,
            useCORS: true,
            logging: false,
            // Lebar jendela dikunci ke ukuran komputer supaya tata letak yang
            // dirender sama persis, baik halaman dibuka dari ponsel maupun PC.
            windowWidth: 1440
        }).then(function (canvas) {
            document.body.classList.remove('ledger-exporting');
            return canvas;
        }, function (err) {
            document.body.classList.remove('ledger-exporting');
            throw err;
        });
    }

    function canvasToBlob(canvas, type, quality) {
        return new Promise(function (resolve, reject) {
            canvas.toBlob(function (blob) {
                blob ? resolve(blob) : reject(new Error('Gagal membuat berkas.'));
            }, type, quality);
        });
    }

    // Potong satu bagian canvas (dipakai untuk memecah PDF per halaman).
    function sliceCanvas(canvas, startPx, heightPx) {
        var out = document.createElement('canvas');
        out.width = canvas.width;
        out.height = Math.max(1, heightPx);
        var ctx = out.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, out.width, out.height);
        ctx.drawImage(canvas, 0, startPx, canvas.width, heightPx, 0, 0, canvas.width, heightPx);
        return out;
    }

    /**
     * Susun PDF multi-halaman. Halaman dipotong tepat di batas kartu supaya
     * tidak ada baris tabel yang terbelah, dan tiap halaman hanya memuat
     * potongannya sendiri (tidak ada isi yang terduplikasi).
     */
    function buildPdf(canvas) {
        var JsPDF = (window.jspdf || {}).jsPDF;
        if (!JsPDF) throw new Error('Pustaka PDF belum siap.');

        var pdf = new JsPDF({ orientation: 'p', unit: 'mm', format: 'a4', compress: true });
        var margin = 8;
        var imgW = pdf.internal.pageSize.getWidth() - margin * 2;
        var usable = pdf.internal.pageSize.getHeight() - margin * 2;

        var layout = ledgerLayout || { width: canvas.width, height: canvas.height, children: [] };
        var cssWidth = layout.width || canvas.width;
        var pxPerCss = canvas.width / cssWidth;   // = skala render
        var mmPerCss = imgW / cssWidth;

        // Batas halaman dalam CSS px, diambil dari tepi atas kartu yang tidak
        // lagi muat di halaman berjalan.
        var bounds = [0];
        var pageTop = 0;

        // Batas bawah yang aman: tinggi canvas sebenarnya. Ukuran DOM bisa
        // sedikit berbeda dari hasil render, dan kalau batas halaman melewati
        // tinggi canvas maka potongannya kosong (halaman PDF jadi rusak).
        var maxCss = canvas.height / pxPerCss;

        // Tinggi DOM dan tinggi gambar bisa berbeda tipis (mis. satu sel
        // membungkus teks di layar sempit). Posisi tiap kartu diskalakan ke
        // tinggi gambar supaya pemotongan halaman sama di semua perangkat.
        var yScale = layout.height > 0 ? maxCss / layout.height : 1;

        layout.children.forEach(function (child) {
            var top = child.top * yScale;
            var bottom = (child.top + child.height) * yScale;
            if ((bottom - pageTop) * mmPerCss > usable && (top - pageTop) * mmPerCss > 2) {
                bounds.push(Math.min(top, maxCss));
                pageTop = top;
            }
        });
        if ((maxCss - pageTop) * mmPerCss > 1) bounds.push(maxCss);

        for (var i = 1; i < bounds.length; i++) {
            var startPx = Math.round(bounds[i - 1] * pxPerCss);
            var heightPx = Math.max(1, Math.round((bounds[i] - bounds[i - 1]) * pxPerCss));
            if (startPx >= canvas.height) break;
            heightPx = Math.min(heightPx, canvas.height - startPx);
            var slice = sliceCanvas(canvas, startPx, heightPx);
            if (i > 1) pdf.addPage();
            pdf.addImage(
                slice.toDataURL('image/jpeg', 0.92), 'JPEG',
                margin, margin, imgW, heightPx * (imgW / canvas.width), undefined, 'FAST'
            );
        }

        return pdf.output('blob');
    }

    function ledgerFormat() {
        var checked = document.querySelector('input[name="ledgerShareFmt"]:checked');
        return checked ? checked.value : 'pdf';
    }

    function ledgerFile(blob, ext) {
        var name = (ledgerShare.meta.file || 'ledger-ffr') + '.' + ext;
        var type = ext === 'pdf' ? 'application/pdf' : 'image/png';
        return new File([blob], name, { type: type });
    }

    function currentLedgerBlob() {
        return ledgerFormat() === 'pdf' ? ledgerShare.pdfBlob : ledgerShare.pngBlob;
    }

    function setLedgerButtons(enabled) {
        ['ledgerShareNative', 'ledgerShareDownload', 'ledgerShareWa'].forEach(function (id) {
            var btn = document.getElementById(id);
            if (btn) btn.disabled = !enabled;
        });
    }

    function updateLedgerInfo() {
        var info = document.getElementById('ledgerShareInfo');
        if (!info) return;
        if (!ledgerShare.canvas) return;
        var ext = ledgerFormat() === 'pdf' ? 'PDF' : 'PNG';
        var size = currentLedgerBlob() ? (currentLedgerBlob().size / 1024) : 0;
        info.textContent = ext + ' · ' + Math.max(1, Math.round(size)) + ' KB';
    }

    // Siapkan berkas untuk format terpilih (PDF dibuat sekali saja).
    function prepareLedgerFile() {
        var fmt = ledgerFormat();
        if (fmt === 'png') {
            if (!ledgerShare.pngBlob) {
                return canvasToBlob(ledgerShare.canvas, 'image/png')
                    .then(function (blob) { ledgerShare.pngBlob = blob; return blob; });
            }
            return Promise.resolve(ledgerShare.pngBlob);
        }
        if (!ledgerShare.pdfBlob) {
            ledgerShare.pdfBlob = buildPdf(ledgerShare.canvas);
        }
        return Promise.resolve(ledgerShare.pdfBlob);
    }

    function downloadLedger() {
        return prepareLedgerFile().then(function (blob) {
            var ext = ledgerFormat();
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = ledgerFile(blob, ext).name;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            setTimeout(function () { URL.revokeObjectURL(url); }, 4000);
            updateLedgerInfo();
        });
    }

    function canShareFiles() {
        try {
            return !!(navigator.canShare && navigator.canShare({ files: [new File([''], 'x.pdf', { type: 'application/pdf' })] }));
        } catch (e) {
            return false;
        }
    }

    function openLedgerShareModal() {
        var btn = document.getElementById('ledgerShare');
        var modalEl = document.getElementById('ledgerShareModal');
        var preview = document.getElementById('ledgerSharePreview');
        var nativeBtn = document.getElementById('ledgerShareNative');
        var info = document.getElementById('ledgerShareInfo');
        if (!btn || !modalEl || !window.bootstrap) return;

        ledgerShare = {
            canvas: null, pdfBlob: null, pngBlob: null, busy: true,
            meta: { file: btn.dataset.file || 'ledger-ffr', text: btn.dataset.text || '' }
        };

        preview.innerHTML = '<div class="text-center text-muted py-5">' +
            '<span class="spinner-border spinner-border-sm"></span> Menyiapkan pratinjau…</div>';
        info.textContent = 'Menyiapkan…';
        setLedgerButtons(false);

        // Tombol berbagi bawaan hanya berguna bila perangkat mendukung berkas.
        if (nativeBtn) nativeBtn.classList.toggle('d-none', !canShareFiles());

        bootstrap.Modal.getOrCreateInstance(modalEl).show();

        loadLedgerLibs()
            .then(renderLedgerCanvas)
            .then(function (canvas) {
                ledgerShare.canvas = canvas;
                ledgerShare.busy = false;
                // html2canvas menulis lebar/tinggi asli ke inline style; diganti
                // agar pratinjau menyesuaikan lebar modal.
                canvas.style.width = '100%';
                canvas.style.height = 'auto';
                preview.innerHTML = '';
                preview.appendChild(canvas);
                setLedgerButtons(true);
                return prepareLedgerFile();
            })
            .then(updateLedgerInfo)
            .catch(function (err) {
                ledgerShare.busy = false;
                preview.innerHTML = '<div class="alert alert-danger mb-0">' +
                    '<i class="bi bi-exclamation-triangle"></i> Gagal menyiapkan berkas: ' +
                    (err && err.message ? err.message : 'tidak diketahui') +
                    '<br><span class="small">Periksa koneksi internet, atau pakai tombol <strong>Cetak</strong> ' +
                    'lalu pilih “Save as PDF”.</span></div>';
                info.textContent = '';
            });
    }

    function initLedgerShare() {
        var btn = document.getElementById('ledgerShare');
        if (!btn) return;

        btn.addEventListener('click', openLedgerShareModal);

        ['ledgerFmtPdf', 'ledgerFmtPng'].forEach(function (id) {
            var input = document.getElementById(id);
            if (input) {
                input.addEventListener('change', function () {
                    if (ledgerShare.busy || !ledgerShare.canvas) return;
                    prepareLedgerFile().then(updateLedgerInfo).catch(function () {});
                });
            }
        });

        var dl = document.getElementById('ledgerShareDownload');
        if (dl) dl.addEventListener('click', function () { downloadLedger().catch(function () {}); });

        var nativeBtn = document.getElementById('ledgerShareNative');
        if (nativeBtn) {
            nativeBtn.addEventListener('click', function () {
                prepareLedgerFile().then(function (blob) {
                    var file = ledgerFile(blob, ledgerFormat());
                    return navigator.share({
                        files: [file],
                        title: 'Ledger Family Financial Report',
                        text: ledgerShare.meta.text
                    });
                }).catch(function (err) {
                    // Pengguna menutup menu berbagi → bukan error.
                    if (err && err.name === 'AbortError') return;
                    if (err && err.name === 'NotAllowedError') return;
                    downloadLedger().catch(function () {});
                });
            });
        }

        // Desktop: unduh berkas lalu buka WhatsApp (berkas dilampirkan manual,
        // karena WhatsApp tidak bisa menerima lampiran lewat tautan).
        var wa = document.getElementById('ledgerShareWa');
        if (wa) {
            wa.addEventListener('click', function () {
                prepareLedgerFile()
                    .then(function () { return downloadLedger(); })
                    .then(function () {
                        window.open('https://wa.me/?text=' + encodeURIComponent(ledgerShare.meta.text), '_blank');
                    })
                    .catch(function () {});
            });
        }
    }

    function initLedgerPage() {
        var btn = document.getElementById('ledgerPrint');
        if (btn) {
            btn.addEventListener('click', function () {
                var stamp = document.getElementById('ledgerExportStamp');
                if (stamp && !stamp.textContent) stamp.textContent = stampWib();
                window.print();
            });
        }
        initLedgerShare();
    }

    /**
     * Submenu "Annual Report" di rail FFR: saat menu sedang aktif, klik menu
     * membuka/menutup submenu (toggle). Saat menu belum aktif, klik tetap
     * berpindah ke halaman Annual Report seperti biasa.
     */
    function initRailSubmenu() {
        document.querySelectorAll('[data-ffr-rail-toggle]').forEach(function (link) {
            var target = document.querySelector(link.getAttribute('data-bs-target'));
            if (!target) return;

            function setExpanded(open) {
                link.setAttribute('aria-expanded', open ? 'true' : 'false');
            }

            target.addEventListener('shown.bs.collapse', function () { setExpanded(true); });
            target.addEventListener('hidden.bs.collapse', function () { setExpanded(false); });

            link.addEventListener('click', function (e) {
                // Submenu tertutup → biarkan tautan berpindah ke halaman
                // Annual Report (dan submenu terbuka lagi setelah render).
                if (!target.classList.contains('show')) return;
                e.preventDefault();
                if (window.bootstrap && bootstrap.Collapse) {
                    bootstrap.Collapse.getOrCreateInstance(target, { toggle: false }).toggle();
                } else {
                    target.classList.remove('show');
                    setExpanded(false);
                }
            });
        });
    }

    // ---------------- Responsif: drawer menu & tabel jadi kartu ----------------

    /**
     * Di HP, daftar menu FFR dirangkum menjadi satu tombol. Ketuk untuk
     * membuka/menutup. Di layar lebar tombol ini tidak ditampilkan (CSS).
     */
    function initRailDrawer() {
        var rail = document.querySelector('.ffr-rail');
        var toggle = document.getElementById('ffrRailToggle');
        if (!rail || !toggle) return;

        function setOpen(open) {
            rail.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        toggle.addEventListener('click', function () {
            setOpen(!rail.classList.contains('is-open'));
        });

        // Kembali ke lebar besar: pastikan menu tidak tertinggal tersembunyi.
        if (window.matchMedia) {
            var wide = window.matchMedia('(min-width: 768px)');
            var onChange = function (e) { if (e.matches) setOpen(false); };
            if (wide.addEventListener) wide.addEventListener('change', onChange);
            else if (wide.addListener) wide.addListener(onChange);
        }
    }

    /**
     * Menyiapkan tabel FFR agar bisa berubah menjadi kartu di HP.
     *
     * Hanya tabel dengan 6 kolom atau lebih yang diubah, karena tabel kecil
     * (2-3 kolom) masih nyaman dibaca apa adanya. Label tiap sel diambil
     * dari kepala tabelnya, jadi markup halaman tidak perlu diubah.
     */
    function initTableCards() {
        var tables = document.querySelectorAll('.ffr-body table');
        if (!tables.length) return;

        Array.prototype.forEach.call(tables, function (table) {
            var headRow = table.tHead && table.tHead.rows[0];
            if (!headRow) return;

            // Peta indeks kolom -> judul kolom (menghormati colspan di kepala).
            var labels = [];
            var idx = 0;
            Array.prototype.forEach.call(headRow.cells, function (cell) {
                var span = cell.colSpan || 1;
                var text = (cell.textContent || '').replace(/\s+/g, ' ').trim();
                for (var i = 0; i < span; i++) labels[idx + i] = text;
                idx += span;
            });

            var bodyRows = table.tBodies.length ? table.tBodies[0].rows : [];
            if (!bodyRows.length && table.tFoot) bodyRows = [];

            // Hitung kolom maksimum dari baris data (abaikan baris colspan).
            var maxCols = 0;
            Array.prototype.forEach.call(bodyRows, function (row) {
                var n = 0;
                Array.prototype.forEach.call(row.cells, function (cell) { n += cell.colSpan || 1; });
                if (n > maxCols) maxCols = n;
            });

            if (maxCols < 6) return;

            table.classList.add('ffr-table-cards');

            // Wadah geser horizontal tidak diperlukan lagi saat mode kartu.
            var wrap = table.closest('.table-responsive');
            if (wrap) wrap.classList.add('ffr-cards-wrap');

            // Beri data-label pada setiap sel data.
            Array.prototype.forEach.call(table.tBodies, function (body) {
                Array.prototype.forEach.call(body.rows, function (row) {
                    var col = 0;
                    Array.prototype.forEach.call(row.cells, function (cell) {
                        var span = cell.colSpan || 1;
                        if (span === 1) {
                            var label = labels[col] || '';
                            if (label && !cell.hasAttribute('data-label')) {
                                cell.setAttribute('data-label', label);
                            }
                        } else {
                            // Sel gabungan (mis. baris kosong / sub-judul).
                            cell.setAttribute('colspan', String(span));
                        }
                        col += span;
                    });

                    // Tentukan sel utama: yang memuat nama item, atau teks terpanjang.
                    if (row.cells.length > 1 && !row.querySelector('.ffr-cell-main')) {
                        var main = row.querySelector('.ffr-item-name');
                        main = main ? main.closest('td') : null;
                        if (!main) {
                            var longest = 0;
                            Array.prototype.forEach.call(row.cells, function (cell) {
                                if ((cell.colSpan || 1) > 1) return;
                                var len = (cell.textContent || '').trim().length;
                                if (len > longest) { longest = len; main = cell; }
                            });
                        }
                        if (main) main.classList.add('ffr-cell-main');
                    }
                });
            });

            // Footer: beri label juga supaya baris total terbaca di kartu.
            if (table.tFoot) {
                Array.prototype.forEach.call(table.tFoot.rows, function (row) {
                    var col = 0;
                    Array.prototype.forEach.call(row.cells, function (cell) {
                        var span = cell.colSpan || 1;
                        if (span === 1) {
                            var label = labels[col] || '';
                            if (label && !cell.hasAttribute('data-label')) {
                                cell.setAttribute('data-label', label);
                            }
                        }
                        col += span;
                    });
                });
            }
        });
    }

    // ---------------- Draft isian: Refresh & Hapus Isi ----------------

    /**
     * Menyimpan isian yang belum di-submit ke sessionStorage, sehingga tombol
     * Refresh bisa memuat ulang data dari server tanpa kehilangan ketikan.
     *
     * Draft dipisah per halaman + periode (lihat data-draft-key), memakai
     * sessionStorage sehingga hilang sendiri saat tab ditutup. Draft dibuang
     * setelah simpan berhasil (atau lewat tautan "buang draft").
     */
    function initFormDrafts() {
        var bar = document.getElementById('ffrDraftBar');
        var form = document.querySelector('form[data-draft-key]');
        if (!bar || !form) return;

        var key = 'ffrDraft:' + form.getAttribute('data-draft-key');
        var stateEl = document.getElementById('ffrDraftState');
        var restoredEl = document.getElementById('ffrDraftRestored');
        var refreshBtn = document.getElementById('ffrRefreshBtn');
        var clearBtn = document.getElementById('ffrClearBtn');
        var discardLink = document.getElementById('ffrDraftDiscard');

        // Hanya kolom yang memang diisi manusia; input hidden tidak perlu.
        function fields() {
            return Array.prototype.slice.call(form.querySelectorAll('input, select, textarea'))
                .filter(function (el) {
                    if (!el.name && !el.id) return false;
                    if (el.type === 'hidden' || el.type === 'submit' || el.type === 'button') return false;
                    if (el.disabled) return false;
                    // Pemilih kelompok langsung berpindah halaman saat diubah.
                    if (el.id === 'ffrItemBucket') return false;
                    return true;
                });
        }

        /**
         * Nama field boleh berulang (mis. income_amount[] untuk tiap baris),
         * jadi kunci draft memakai nama + nomor urut kemunculan. Tanpa ini
         * hanya baris terakhir yang tersimpan.
         */
        function keyedFields() {
            var counts = {};
            return fields().map(function (el) {
                var base = el.name || el.id;
                counts[base] = (counts[base] || 0) + 1;
                return { el: el, key: base + '#' + counts[base] };
            });
        }

        function collect() {
            var data = {};
            keyedFields().forEach(function (item) {
                var el = item.el;
                if (el.type === 'checkbox') {
                    data[item.key] = el.checked ? '1' : '';
                } else if (el.type === 'radio') {
                    if (el.checked) data[item.key] = el.value;
                } else if (el.tagName === 'SELECT' && el.multiple) {
                    data[item.key] = Array.prototype.map.call(el.selectedOptions, function (o) { return o.value; });
                } else {
                    data[item.key] = el.value;
                }
            });
            return data;
        }

        function isEmpty(data) {
            return Object.keys(data).every(function (k) {
                var v = data[k];
                if (Array.isArray(v)) return v.length === 0;
                return v === '' || v == null;
            });
        }

        function save() {
            var data = collect();
            try {
                if (isEmpty(data)) {
                    sessionStorage.removeItem(key);
                    setState(false);
                    return;
                }
                sessionStorage.setItem(key, JSON.stringify(data));
                setState(true);
            } catch (e) { /* storage penuh / diblokir — abaikan */ }
        }

        function setState(dirty) {
            if (stateEl) stateEl.classList.toggle('d-none', !dirty);
        }

        var timer = null;
        function scheduleSave() {
            clearTimeout(timer);
            timer = setTimeout(save, 400);
        }

        form.addEventListener('input', scheduleSave);
        form.addEventListener('change', scheduleSave);

        // --- Pulihkan draft (dipanggil sekali saat halaman dimuat) ---
        var raw = null;
        try { raw = sessionStorage.getItem(key); } catch (e) { raw = null; }
        if (raw) {
            try {
                var data = JSON.parse(raw);
                keyedFields().forEach(function (item) {
                    var el = item.el;
                    if (!(item.key in data)) return;
                    var v = data[item.key];
                    if (el.type === 'checkbox') {
                        el.checked = v === '1';
                    } else if (el.type === 'radio') {
                        el.checked = el.value === v;
                    } else if (el.tagName === 'SELECT' && el.multiple) {
                        Array.prototype.forEach.call(el.options, function (o) {
                            o.selected = Array.isArray(v) && v.indexOf(o.value) !== -1;
                        });
                    } else if (el.type !== 'hidden') {
                        el.value = v;
                    }
                });
                // Biarkan modul halaman menyesuaikan tampilan (mis. total & persen).
                form.dispatchEvent(new CustomEvent('ffr:draftRestored', { bubbles: true }));
                setState(true);
                if (restoredEl) restoredEl.classList.remove('d-none');
            } catch (e) { /* draft rusak — abaikan */ }
        }

        // --- Refresh: simpan draft dulu, lalu muat ulang dari server ---
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function () {
                save();
                window.location.reload();
            });
        }

        // Nilai awal tiap kolom (dipakai tombol Hapus Isi supaya dropdown dan
        // kolom berisi default kembali seperti pertama kali halaman dibuka).
        var initial = {};
        keyedFields().forEach(function (item) {
            var el = item.el;
            if (el.type === 'checkbox' || el.type === 'radio') {
                initial[item.key] = el.checked ? '1' : '';
            } else if (el.tagName === 'SELECT' && el.multiple) {
                initial[item.key] = Array.prototype.map.call(el.selectedOptions, function (o) { return o.value; });
            } else {
                initial[item.key] = el.value;
            }
        });

        // --- Hapus Isi: kosongkan layar, data tersimpan tidak disentuh ---
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                var ask = window.AppDialog
                    ? window.AppDialog.confirm({
                        tone: 'danger',
                        title: 'Kosongkan semua isian di layar?',
                        message: 'Data yang sudah tersimpan tidak akan berubah. ' +
                                 'Halaman hanya dikosongkan supaya bisa diisi ulang dari awal.',
                        confirmText: 'Ya, kosongkan',
                        cancelText: 'Batal'
                    })
                    : Promise.resolve(window.confirm(
                        'Kosongkan semua isian di layar?\n\n' +
                        'Data yang sudah tersimpan TIDAK berubah.'
                    ));

                ask.then(function (ok) {
                    if (!ok) return;

                    keyedFields().forEach(function (item) {
                        var el = item.el;
                        var v = initial[item.key];
                        if (el.type === 'checkbox' || el.type === 'radio') {
                            el.checked = v === '1';
                        } else if (el.tagName === 'SELECT' && el.multiple) {
                            Array.prototype.forEach.call(el.options, function (o) {
                                o.selected = Array.isArray(v) && v.indexOf(o.value) !== -1;
                            });
                        } else {
                            el.value = v == null ? '' : v;
                        }
                    });

                    try { sessionStorage.removeItem(key); } catch (e) { /* abaikan */ }
                    setState(false);
                    if (restoredEl) restoredEl.classList.add('d-none');
                    form.dispatchEvent(new CustomEvent('ffr:cleared', { bubbles: true }));
                });
            });
        }

        if (discardLink) {
            discardLink.addEventListener('click', function (e) {
                e.preventDefault();
                try { sessionStorage.removeItem(key); } catch (err) { /* abaikan */ }
                window.location.reload();
            });
        }

        // --- Setelah submit: buang draft yang sudah tersimpan ---
        form.addEventListener('submit', function (e) {
            var action = '';
            if (e.submitter && e.submitter.getAttribute) {
                action = e.submitter.getAttribute('formaction') || '';
            }
            // Ambil isian terbaru langsung dari form (bukan dari storage) supaya
            // ketikan terakhir yang belum sempat tersimpan tetap terhitung.
            var data = collect();
            try {
                if (action.indexOf('saveIncome') === -1) {
                    // Simpan penuh → semua isian sudah masuk database.
                    sessionStorage.removeItem(key);
                    return;
                }
                // Simpan pemasukan saja → hanya bagian pemasukan yang dibuang,
                // ketikan alokasi yang belum disimpan tetap dipulihkan.
                Object.keys(data).forEach(function (k) {
                    if (/income/i.test(k)) delete data[k];
                });
                if (isEmpty(data)) {
                    sessionStorage.removeItem(key);
                } else {
                    sessionStorage.setItem(key, JSON.stringify(data));
                }
            } catch (err) { /* abaikan */ }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindAmountInputs(document);
        initPlanPage();
        initItemsPage();
        initItemBucketSync();
        initPeriodPicker();
        initSavingsPage();
        initSavingsForm();
        initSavingsTransactions();
        initLedgerPage();
        initRailSubmenu();
        initRailDrawer();
        initTableCards();
        initFormDrafts();
    });
})();
