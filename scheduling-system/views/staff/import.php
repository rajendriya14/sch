<div class="row mb-3">
    <div class="col">
        <h2><i class="bi bi-upload"></i> Import Staff Data</h2>
        <p class="text-muted">Header kolom sudah ditentukan — Anda tinggal mengisi data baris saja.</p>
    </div>
    <div class="col text-end">
        <a href="index.php?page=staff" class="btn btn-outline-secondary">
            <i class="bi bi-list"></i> Back to List
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-dark text-white">
        <i class="bi bi-info-circle"></i> Format Kolom (Sudah Ditentukan / Locked)
    </div>
    <div class="card-body">
        <p class="mb-2">Urutan kolom <strong>TETAP</strong> seperti di bawah. Copy data dari Excel/spreadsheet (hanya baris data, <u>tanpa header</u>):</p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr>
                        <th style="width:30px">#</th>
                        <th>Kolom 1<br><small class="text-muted">NIP</small></th>
                        <th>Kolom 2<br><small class="text-muted">Agent Name</small></th>
                        <th>Kolom 3<br><small class="text-muted">Agent Email</small></th>
                        <th>Kolom 4<br><small class="text-muted">Position</small></th>
                        <th>Kolom 5<br><small class="text-muted">LOB</small></th>
                        <th>Kolom 6<br><small class="text-muted">Email TL</small></th>
                        <th>Kolom 7<br><small class="text-muted">Op. Manager</small></th>
                        <th>Kolom 8<br><small class="text-muted">Gender</small></th>
                        <th>Kolom 9<br><small class="text-muted">Domain</small></th>
                        <th>Kolom 10<br><small class="text-muted">Compo Month</small></th>
                        <th>Kolom 11<br><small class="text-muted">Year</small></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td><code>NIP001</code></td>
                        <td><code>John Doe</code></td>
                        <td><code>john@email.com</code></td>
                        <td><code>Agent</code></td>
                        <td><code>LOB A</code></td>
                        <td><code>tl@email.com</code></td>
                        <td><code>Manager A</code></td>
                        <td><code>Male</code></td>
                        <td><code>Domain A</code></td>
                        <td><code>June</code></td>
                        <td><code>2026</code></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="mb-0 small text-muted">
            <i class="bi bi-lightbulb"></i> Copy dari Excel (pilih baris data SAJA, jangan include header).<br>
            Gender: <strong>Male</strong> / <strong>Female</strong>.
        </p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="post" action="index.php?page=staff&action=store" id="importForm">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Compo Month <span class="text-danger">*</span></label>
                    <select class="form-select" id="compoMonth" name="compo_month" required>
                        <option value="">— Pilih Bulan —</option>
                        <option value="January">January</option>
                        <option value="February">February</option>
                        <option value="March">March</option>
                        <option value="April">April</option>
                        <option value="May">May</option>
                        <option value="June" selected>June</option>
                        <option value="July">July</option>
                        <option value="August">August</option>
                        <option value="September">September</option>
                        <option value="October">October</option>
                        <option value="November">November</option>
                        <option value="December">December</option>
                    </select>
                    <div id="compoStatus" class="form-text"></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Year <span class="text-danger">*</span></label>
                    <select class="form-select" id="compoYear" name="compo_year" required>
                        <option value="">— Pilih Tahun —</option>
                        <?php $currentYear = (int)date('Y'); ?>
                        <?php for ($y = $currentYear - 2; $y <= $currentYear + 2; $y++): ?>
                            <option value="<?= $y ?>" <?= $y === $currentYear ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Auto File Name</label>
                    <input type="text" class="form-control" id="fileName" readonly
                           placeholder="File_Compo_Month_Year"
                           style="background:#e9ecef;cursor:default;">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">
                    <strong>Paste Data Disini</strong>
                    <span class="text-muted small">(copy dari Excel lalu paste — header sudah terkunci di atas)</span>
                </label>
                <div class="table-responsive border rounded" style="overflow:auto;">
                    <table class="table table-sm table-bordered mb-0" id="importGridTable" style="font-size:0.82rem;">
                        <thead class="table-dark">
                            <tr>
                                <th style="width:30px;">#</th>
                                <th style="min-width:100px;">NIP</th>
                                <th style="min-width:130px;">Agent Name</th>
                                <th style="min-width:150px;">Agent Email</th>
                                <th style="min-width:100px;">Position</th>
                                <th style="min-width:80px;">LOB</th>
                                <th style="min-width:150px;">Email TL</th>
                                <th style="min-width:120px;">Op. Manager</th>
                                <th style="min-width:70px;">Gender</th>
                                <th style="min-width:100px;">Domain</th>
                                <th style="min-width:110px;">Compo Month</th>
                                <th style="min-width:70px;">Year</th>
                            </tr>
                        </thead>
                        <tbody id="gridBody">
                            <tr>
                                <td colspan="12" class="text-muted text-center py-3">
                                    <i class="bi bi-arrow-up"></i> Paste data di atas, atau klik cell untuk edit langsung<br>
                                    <small>Copy dari Excel (C) → paste (V) di sini — header otomatis dikunci</small>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <textarea id="import_data" name="import_data" style="display:none;"></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Preview <span class="text-muted small">(10 baris pertama)</span></label>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered" id="previewTable">
                        <thead class="table-dark" id="previewHead">
                            <tr>
                                <th>#</th>
                                <th>NIP</th>
                                <th>Agent Name</th>
                                <th>Agent Email</th>
                                <th>Position</th>
                                <th>LOB</th>
                                <th>Email TL</th>
                                <th>Op. Manager</th>
                                <th>Gender</th>
                                <th>Domain</th>
                                <th>Compo Month</th>
                                <th>Year</th>
                            </tr>
                        </thead>
                        <tbody id="previewBody">
                            <tr><td colspan="12" class="text-muted text-center">Paste data di atas untuk melihat preview</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" id="btnImport">
                <i class="bi bi-upload"></i> Import Data
            </button>
            <a href="index.php?page=staff" class="btn btn-secondary">
                <i class="bi bi-x-circle"></i> Cancel
            </a>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const monthSelect = document.getElementById('compoMonth');
    const yearSelect = document.getElementById('compoYear');
    const fileNameInput = document.getElementById('fileName');
    const importTextarea = document.getElementById('import_data');
    const compoStatus = document.getElementById('compoStatus');
    const btnImport = document.getElementById('btnImport');

    // Predefined column headers (locked)
    const columnHeaders = ['NIP', 'Agent Name', 'Agent Email', 'Position', 'LOB', 'Email TL', 'Op. Manager', 'Gender', 'Domain', 'Compo Month', 'Year'];
    const colCount = columnHeaders.length;

    function checkCompoExists(month, year) {
        if (!month || !year) {
            compoStatus.innerHTML = '';
            btnImport.disabled = false;
            return;
        }
        // Check via AJAX
        fetch('index.php?page=staff&action=checkCompo&month=' + encodeURIComponent(month) + '&year=' + encodeURIComponent(year))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.exists) {
                    compoStatus.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> Compo ' + month + ' ' + year + ' sudah ada! Tidak bisa upload duplikat.</span>';
                    btnImport.disabled = true;
                } else {
                    compoStatus.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> Compo ' + month + ' ' + year + ' tersedia.</span>';
                    btnImport.disabled = false;
                }
            })
            .catch(function() {
                compoStatus.innerHTML = '';
                btnImport.disabled = false;
            });
    }

    function updateFileName() {
        const month = monthSelect.value;
        const year = yearSelect.value;
        if (month && year) {
            fileNameInput.value = 'File_Compo_' + month + '_' + year;
            checkCompoExists(month, year);
        } else {
            fileNameInput.value = '';
            compoStatus.innerHTML = '';
            btnImport.disabled = false;
        }
    }

    monthSelect.addEventListener('change', updateFileName);
    yearSelect.addEventListener('change', updateFileName);
    updateFileName(); // Initial

    // Paste handler — listen for paste event on the grid table itself
    function renderGridFromData(text) {
        var lines = text.trim().split('\n');
        if (lines.length === 0 || (lines.length === 1 && lines[0] === '')) {
            document.getElementById('gridBody').innerHTML = '<tr><td colspan=\"' + colCount + '\" class=\"text-muted text-center py-3\"><i class=\"bi bi-arrow-up\"></i> Paste data di sini</td></tr>';
            importTextarea.value = '';
            return;
        }

        var bodyHtml = '';
        for (var i = 0; i < lines.length; i++) {
            var cells = lines[i].split('\t');
            if (cells.length < 2) continue;
            bodyHtml += '<tr>';
            bodyHtml += '<td class=\"text-muted text-center\" style=\"font-size:0.75rem;\">' + (i + 1) + '</td>';
            for (var c = 0; c < colCount; c++) {
                bodyHtml += '<td class=\"grid-cell\" contenteditable=\"true\">' + (cells[c] || '').trim() + '</td>';
            }
            bodyHtml += '</tr>';
        }
        document.getElementById('gridBody').innerHTML = bodyHtml;
        importTextarea.value = text; // keep hidden textarea synced
    }

    // Listen for paste on the whole grid container
    var gridTable = document.getElementById('importGridTable');
    gridTable.addEventListener('paste', function(e) {
        // If pasting into a contenteditable cell, let browser handle it
        if (e.target.closest('.grid-cell')) return;

        e.preventDefault();
        var pastedText = (e.clipboardData || window.clipboardData).getData('text');
        if (pastedText) {
            renderGridFromData(pastedText);
        }
    });

    // Also allow pasting via the old textarea for backward compatibility
    importTextarea.addEventListener('paste', function(e) {
        var self = this;
        setTimeout(function() {
            if (self.value) {
                renderGridFromData(self.value);
            }
        }, 50);
    });

    // Sync contenteditable cells back to hidden textarea on input change
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('grid-cell')) {
            // Rebuild textarea data from grid
            var rows = [];
            document.querySelectorAll('#gridBody tr').forEach(function(row) {
                var cells = [];
                row.querySelectorAll('.grid-cell').forEach(function(cell) {
                    cells.push(cell.textContent.trim());
                });
                if (cells.length > 0) {
                    rows.push(cells.join('\t'));
                }
            });
            importTextarea.value = rows.join('\n');
        }
    });

    // Sync hidden textarea before form submit
    document.getElementById('importForm').addEventListener('submit', function() {
        // If grid has data but textarea is empty, rebuild
        var gridRows = document.querySelectorAll('#gridBody tr .grid-cell');
        if (gridRows.length > 0 && !importTextarea.value.trim()) {
            var rows = [];
            document.querySelectorAll('#gridBody tr').forEach(function(row) {
                var cells = [];
                row.querySelectorAll('.grid-cell').forEach(function(cell) {
                    cells.push(cell.textContent.trim());
                });
                if (cells.length > 0) {
                    rows.push(cells.join('\t'));
                }
            });
            importTextarea.value = rows.join('\n');
        }
    });

    // Re-initialize if old placeholder is shown
    importTextarea.addEventListener('input', function() {
        if (this.value) {
            renderGridFromData(this.value);
        }
    });

    // Remove the old preview table
    var previewSection = document.querySelector('.mb-3:has(#previewTable)');
    if (previewSection) previewSection.style.display = 'none';
});
</script>
