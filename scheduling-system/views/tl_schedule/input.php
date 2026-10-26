<div class="row mb-3">
    <div class="col">
        <h2><i class="bi bi-person-badge"></i>Input</h2>
        <p class="text-muted">Paste data dari Excel — header otomatis terkunci.</p>
    </div>
    <div class="col text-end">
        <a href="index.php?page=tl_schedule" class="btn btn-outline-secondary">
            <i class="bi bi-list"></i> Back to List
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="post" action="index.php?page=tl_schedule&action=import" id="tlImportForm">
            <!-- WEEK SELECTOR -->
            <div class="row g-3 mb-4 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">Month</label>
                    <select class="form-select" id="monthSelect">
                        <option value="01">January</option><option value="02">February</option><option value="03">March</option>
                        <option value="04">April</option><option value="05">May</option><option value="06">June</option>
                        <option value="07" selected>July</option><option value="08">August</option><option value="09">September</option>
                        <option value="10">October</option><option value="11">November</option><option value="12">December</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Year</label>
                    <select class="form-select" id="yearSelect">
                        <option value="2026" <?= date('Y') === '2026' ? 'selected' : '' ?>>2026</option>
                        <option value="2027" <?= date('Y') === '2027' ? 'selected' : '' ?>>2027</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Week</label>
                    <select class="form-select" id="weekSelect" name="week_start" disabled>
                        <option value="">— Select Month and Year first —</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Domain</label>
                    <select class="form-select" id="domainSelect" name="domain">
                        <option value="">— Select Domain —</option>
                        <option value="Seller">Seller</option>
                        <option value="Product">Product</option>
                        <option value="Content">Content</option>
                        <option value="IPR">IPR</option>
                        <option value="UPL">UPL</option>
                        <option value="Global Appeal">Global Appeal</option>
                    </select>
                </div>
            </div>

            <!-- SECTION 1: TL SCHEDULE GRID -->
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="border-bottom pb-0 mb-0"><i class="bi bi-person-badge"></i> Team Leader Schedule</h5>
                    <button type="button" class="btn btn-sm btn-outline-dark" onclick="location.reload()" title="Reset table"><i class="bi bi-arrow-counterclockwise"></i></button>
                </div>
                <div class="table-responsive border rounded" style="overflow:auto;">
                    <table class="table table-sm table-bordered mb-0" id="pasteGrid" style="font-size:0.82rem;">
                        <thead class="table-dark">
                            <tr>
                                <th style="min-width:30px;">#</th>
                                <th style="min-width:140px;">TL Email</th>
                                <th style="min-width:70px;">Domain</th>
                                <th style="min-width:90px;" id="hdr0">Mon</th>
                                <th style="min-width:90px;" id="hdr1">Tue</th>
                                <th style="min-width:90px;" id="hdr2">Wed</th>
                                <th style="min-width:90px;" id="hdr3">Thu</th>
                                <th style="min-width:90px;" id="hdr4">Fri</th>
                                <th style="min-width:90px;" id="hdr5">Sat</th>
                                <th style="min-width:90px;" id="hdr6">Sun</th>
                            </tr>
                        </thead>
                        <tbody id="gridBody">
                            <?php for ($g = 1; $g <= 10; $g++): ?>
                            <tr><td class="text-muted text-center" style="font-size:0.75rem;"><?= $g ?></td><?php for ($c = 0; $c < 9; $c++): ?><td class="grid-cell" contenteditable="true"></td><?php endfor; ?></tr>
                            <?php endfor; ?>
                    </table>
                </div>
                <textarea id="import_data" name="import_data" style="display:none;"></textarea>
            </div>

            <!-- SECTION 2: FORECAST GRID -->
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="border-bottom pb-0 mb-0"><i class="bi bi-graph-up"></i> Forecast</h5>
                    <button type="button" class="btn btn-sm btn-outline-dark" onclick="location.reload()" title="Reset table"><i class="bi bi-arrow-counterclockwise"></i></button>
                </div>
                <div class="table-responsive border rounded" style="overflow:auto;">
                    <table class="table table-sm table-bordered mb-0" id="forecastGrid" style="font-size:0.82rem;">
                        <thead class="table-dark">
                            <tr>
                                <th style="min-width:30px;">Shift Code</th>
                                <th style="min-width:90px;" id="fhdr0">Mon</th>
                                <th style="min-width:90px;" id="fhdr1">Tue</th>
                                <th style="min-width:90px;" id="fhdr2">Wed</th>
                                <th style="min-width:90px;" id="fhdr3">Thu</th>
                                <th style="min-width:90px;" id="fhdr4">Fri</th>
                                <th style="min-width:90px;" id="fhdr5">Sat</th>
                                <th style="min-width:90px;" id="fhdr6">Sun</th>
                            </tr>
                        </thead>
                        <tbody id="forecastBody">
                            <?php if (!empty($activeShifts)): ?>
                                <?php foreach ($activeShifts as $as): ?>
                                    <tr>
                                        <td class="text-start fw-bold"><small><?= htmlspecialchars($as['shift_code']) ?></small></td>
                                        <td class="fc-cell" contenteditable="true"></td>
                                        <td class="fc-cell" contenteditable="true"></td>
                                        <td class="fc-cell" contenteditable="true"></td>
                                        <td class="fc-cell" contenteditable="true"></td>
                                        <td class="fc-cell" contenteditable="true"></td>
                                        <td class="fc-cell" contenteditable="true"></td>
                                        <td class="fc-cell" contenteditable="true"></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="8" class="text-muted text-center py-3">No active shifts. Please check Shift Master.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <textarea id="forecast_data" name="forecast_data" style="display:none;"></textarea>
            </div>

            <!-- SECTION 3: CONNECT FILE -->
            <div class="mb-4">
                <h5 class="border-bottom pb-2"><i class="bi bi-link-45deg"></i> Connect File</h5>
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Pilih File Compo</label>
                        <select class="form-select" id="connectFile">
                            <option value="">— Select File —</option>
                            <?php
                            $allEmps = \Models\Employee::all();
                            $seenFiles = [];
                            foreach ($allEmps as $e) {
                                $fn = $e['file_name'] ?? '';
                                if ($fn && !isset($seenFiles[$fn])) {
                                    $seenFiles[$fn] = true;
                                    $parts = explode('_', $fn);
                                    $label = isset($parts[2]) && isset($parts[3]) ? $parts[2] . ' ' . $parts[3] : $fn;
                                    echo '<option value="' . htmlspecialchars($fn) . '">' . htmlspecialchars($label) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Pilih File Backdate</label>
                        <select class="form-select" id="connectBackdateFile">
                            <option value="">— Select File —</option>
                            <?php
                            $allBd = \Models\Employee::all();
                            try {
                                $bdStmt = \Config\Database::getInstance()->query("SELECT DISTINCT file_name FROM backdate_data WHERE file_name IS NOT NULL AND file_name != '' ORDER BY file_name DESC");
                                $bdFiles = $bdStmt->fetchAll(\PDO::FETCH_COLUMN);
                                foreach ($bdFiles as $bfn) {
                                    echo '<option value="' . htmlspecialchars($bfn) . '">' . htmlspecialchars($bfn) . '</option>';
                                }
                            } catch (\PDOException $e) {}
                            ?>
                        </select>
                    </div>
                </div>
            </div>

            <button type="button" class="btn btn-success" id="btnRunAutomation"><i class="bi bi-play-fill"></i> Run Automation</button>            <a href="index.php?page=tl_schedule" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Cancel</a>
        </form>
    </div>
</div>

<script>
// ============================================================
// REUSABLE WEEK GENERATOR — used by TL Input, Forecast, Reports
// ============================================================

/**
 * Generate all weeks in a given month that contain at least one day in that month.
 * Week always starts Monday, ends Sunday, may cross month boundaries.
 *
 * @param {number} month - 1–12
 * @param {number} year  - e.g. 2026
 * @returns {Array<{week:number, start:string, end:string, startDate:Date, endDate:Date}>}
 */
function generateWeeks(month, year) {
    // Find the first day of the month
    var firstDay = new Date(year, month - 1, 1);
    // Find the first Monday on or before the first day of the month
    var diff = (firstDay.getDay() + 6) % 7; // Monday=0 offset
    var monday = new Date(firstDay);
    monday.setDate(firstDay.getDate() - diff);
    
    // Find the last day of the month
    var lastDay = new Date(year, month, 0);
    
    var weeks = [];
    var weekNum = 1;
    while (monday <= lastDay) {
        var sunday = new Date(monday);
        sunday.setDate(monday.getDate() + 6);
        
        // Only include if at least 1 day overlaps the selected month
        // monday <= lastDay already ensures this
        weeks.push({
            week: weekNum,
            start: formatISODate(monday),
            end: formatISODate(sunday),
            startDate: monday,
            endDate: sunday
        });
        
        var nextMonday = new Date(monday);
        nextMonday.setDate(monday.getDate() + 7);
        monday = nextMonday;
        weekNum++;
    }
    // Trim last week if its start is beyond the month
    if (weeks.length > 0) {
        var last = weeks[weeks.length - 1];
        if (last.startDate > lastDay && last.endDate > lastDay) {
            weeks.pop();
        }
    }
    return weeks;
}

/**
 * Format a Date object as YYYY-MM-DD
 */
function formatISODate(date) {
    var y = date.getFullYear();
    var m = String(date.getMonth() + 1).padStart(2, '0');
    var d = String(date.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + d;
}

/**
 * Format a Date object as "DD Mon" (e.g., "29 Jun")
 */
function formatShort(date) {
    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    var d = String(date.getDate()).padStart(2, '0');
    return d + ' ' + months[date.getMonth()];
}

/**
 * Get day name abbreviation (Mon, Tue, Wed, ...)
 */
function getDayName(date) {
    var days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    return days[date.getDay()];
}

/**
 * Format a Date object as "Day, DD-Mon-YYYY" (e.g., "Mon, 20-Jul-2026")
 */
function formatFull(date) {
    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    return getDayName(date) + ', ' + String(date.getDate()).padStart(2,'0') + '-' + months[date.getMonth()] + '-' + date.getFullYear();
}

/**
 * Populate week dropdown based on selected month+year
 * @returns {Array} The generated weeks array
 */
function populateWeekDropdown() {
    var monthSelect = document.getElementById('monthSelect');
    var yearSelect = document.getElementById('yearSelect');
    var weekSelect = document.getElementById('weekSelect');
    
    var month = parseInt(monthSelect.value);
    var year = parseInt(yearSelect.value);
    
    // Clear and disable if month or year is missing
    weekSelect.innerHTML = '';
    weekSelect.disabled = true;
    
    if (!month || !year || isNaN(month) || isNaN(year)) {
        weekSelect.innerHTML = '<option value="">— Select Month and Year first —</option>';
        return [];
    }
    
    var weeks = generateWeeks(month, year);
    
    if (weeks.length === 0) {
        weekSelect.innerHTML = '<option value="">— No weeks —</option>';
        return [];
    }
    
    weeks.forEach(function(w) {
        var opt = document.createElement('option');
        opt.value = w.start;
        opt.textContent = 'Week ' + w.week + ' (' + formatShort(w.startDate) + ' - ' + formatShort(w.endDate) + ')';
        opt.dataset.week = w.week;
        opt.dataset.start = w.start;
        opt.dataset.end = w.end;
        weekSelect.appendChild(opt);
    });
    
    weekSelect.disabled = false;
    return weeks;
}

/**
 * Get the currently selected week data
 * @returns {{week:number, start:string, end:string}|null}
 */
function getSelectedWeek() {
    var weekSelect = document.getElementById('weekSelect');
    if (!weekSelect || !weekSelect.value) return null;
    var opt = weekSelect.options[weekSelect.selectedIndex];
    return {
        week: parseInt(opt.dataset.week),
        start: opt.dataset.start,
        end: opt.dataset.end
    };
}

// ============================================================
// DAY HEADER UPDATER
// ============================================================
var DAY_LABELS = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

/**
 * Update the day column headers in TL and Forecast grids
 * based on the selected week.
 */
function updateDayHeaders() {
    var weekSelect = document.getElementById('weekSelect');
    var mondayStr = weekSelect.value;
    
    // Parse the Monday date
    var monday = null;
    if (mondayStr) {
        monday = new Date(mondayStr + 'T00:00:00');
        if (isNaN(monday.getTime())) monday = null;
    }
    
    for (var i = 0; i < 7; i++) {
        var hdr = document.getElementById('hdr' + i);
        var fhdr = document.getElementById('fhdr' + i);
        
        var label = DAY_LABELS[i];
        if (monday) {
            var dt = new Date(monday);
            dt.setDate(monday.getDate() + i);
            label += ' ' + formatShort(dt);
        }
        
        if (hdr) hdr.textContent = label;
        if (fhdr) fhdr.textContent = label;
    }
}

// ============================================================
// EVENT HANDLERS
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    var monthSel = document.getElementById('monthSelect');
    var yearSel = document.getElementById('yearSelect');
    var weekSel = document.getElementById('weekSelect');

    // === Run Automation button ===
    var runBtn = document.getElementById('btnRunAutomation');
    if (runBtn) {
        runBtn.addEventListener('click', function() {
            var weekStart = weekSel.value;
            var domain = document.getElementById('domainSelect') ? document.getElementById('domainSelect').value : '';
            if (!weekStart) {
                if (window.AppDialog) {
                    window.AppDialog.toast({
                        tone: 'warning',
                        title: 'Pilih Week dulu',
                        message: 'Silakan pilih Week terlebih dahulu sebelum menjalankan automation.'
                    });
                } else {
                    alert('Silakan pilih Week terlebih dahulu.');
                }
                return;
            }
            // Build hidden form and submit to automation
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'index.php?page=tl_schedule&action=automation';
            var inputWeek = document.createElement('input');
            inputWeek.type = 'hidden';
            inputWeek.name = 'week_start';
            inputWeek.value = weekStart;
            form.appendChild(inputWeek);
            var inputDomain = document.createElement('input');
            inputDomain.type = 'hidden';
            inputDomain.name = 'domain';
            inputDomain.value = domain;
            form.appendChild(inputDomain);
            document.body.appendChild(form);
            form.submit();
        });
    }

    // Populate weeks on month/year change
    function refreshWeeks() {
        populateWeekDropdown();
        updateDayHeaders();
    }
    
    monthSel.addEventListener('change', refreshWeeks);
    yearSel.addEventListener('change', refreshWeeks);
    weekSel.addEventListener('change', updateDayHeaders);
    
    // Initial load: set current month/year
    var today = new Date();
    monthSel.value = String(today.getMonth() + 1).padStart(2, '0');
    yearSel.value = String(today.getFullYear());
    refreshWeeks();

    // --- TL Paste handler (spread to all columns) ---
    const gridBody = document.getElementById('gridBody');
    const pasteGrid = document.getElementById('pasteGrid');
    const importTextarea = document.getElementById('import_data');
    const tlCols = 10;

    function renderTlGrid(text) {
        const lines = text.trim().split('\n');
        if (lines.length === 0 || (lines.length === 1 && lines[0] === '')) {
            gridBody.innerHTML = '<tr><td colspan="10" class="text-muted text-center py-4"><i class="bi bi-arrow-up"></i> Paste TL schedule di sini</td></tr>';
            importTextarea.value = '';
            return;
        }
        let html = '';
        for (let i = 0; i < lines.length; i++) {
            const cells = lines[i].split('\t');
            if (cells.length < 2) continue;
            html += '<tr>';
            html += '<td class="text-muted text-center" style="font-size:0.75rem;">' + (i + 1) + '</td>';
            for (let c = 0; c < 9; c++) {
                const val = (cells[c] || '').trim();
                html += '<td class="grid-cell" contenteditable="true">' + val + '</td>';
            }
            html += '</tr>';
        }
        gridBody.innerHTML = html;
        importTextarea.value = text;
    }

    // Paste anywhere in the grid (including cells) — spreads across columns
    pasteGrid.addEventListener('paste', function(e) {
        e.preventDefault();
        const text = (e.clipboardData || window.clipboardData).getData('text');
        if (!text) return;

        const lines = text.trim().split('\n');
        if (lines.length === 0) return;

        // Determine starting row from the clicked cell (if any)
        let startRow = 0;
        const clickedCell = e.target.closest('.grid-cell');
        if (clickedCell) {
            const rows = Array.from(gridBody.querySelectorAll('tr'));
            startRow = rows.indexOf(clickedCell.closest('tr'));
        }

        // Spread data from startRow across columns
        const existingRows = Array.from(gridBody.querySelectorAll('tr'));
        for (let i = 0; i < lines.length; i++) {
            const cells = lines[i].split('\t');
            const rowIdx = startRow + i;
            let row = existingRows[rowIdx];
            if (!row) {
                // Create a new row if needed
                row = document.createElement('tr');
                row.innerHTML = '<td class="text-muted text-center" style="font-size:0.75rem;">' + (existingRows.length + 1) + '</td>';
                for (let c = 0; c < 9; c++) {
                    const td = document.createElement('td');
                    td.className = 'grid-cell';
                    td.contentEditable = true;
                    row.appendChild(td);
                }
                gridBody.appendChild(row);
                existingRows.push(row);
            }
            const targetCells = row.querySelectorAll('.grid-cell');
            for (let c = 0; c < cells.length && c < targetCells.length; c++) {
                targetCells[c].textContent = cells[c].trim();
            }
        }

        // Sync to textarea
        const rows = [];
        gridBody.querySelectorAll('tr').forEach(function(row) {
            const cells = [];
            row.querySelectorAll('.grid-cell').forEach(function(c) { cells.push(c.textContent.trim()); });
            if (cells.length > 0 && cells.some(function(v) { return v !== ''; })) rows.push(cells.join('\t'));
        });
        importTextarea.value = rows.join('\n');
    });

    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('grid-cell') && e.target.closest('#gridBody')) {
            const rows = [];
            document.querySelectorAll('#gridBody tr').forEach(function(row) {
                const cells = [];
                row.querySelectorAll('.grid-cell').forEach(function(c) { cells.push(c.textContent.trim()); });
                if (cells.length > 0) rows.push(cells.join('\t'));
            });
            importTextarea.value = rows.join('\n');
        }
    });

    // --- FORECAST Paste handler ---
    const forecastBody = document.getElementById('forecastBody');
    const forecastGrid = document.getElementById('forecastGrid');
    const forecastTextarea = document.getElementById('forecast_data');
    const fcCols = 8;

    // --- Keyboard DnD sync handler ---
    function syncTlTextarea() {
        const rows = [];
        document.querySelectorAll('#gridBody tr').forEach(function(row) {
            const cells = [];
            row.querySelectorAll('.grid-cell').forEach(function(c) { cells.push(c.textContent.trim()); });
            if (cells.length > 0) rows.push(cells.join('\t'));
        });
        importTextarea.value = rows.join('\n');
    }

    function renderForecastGrid(text) {
        const lines = text.trim().split('\n');
        if (lines.length === 0 || (lines.length === 1 && lines[0] === '')) {
            // Reset to original shift rows from PHP
            location.reload();
            return;
        }
        let html = '';
        for (let i = 0; i < lines.length; i++) {
            const cells = lines[i].split('\t');
            if (cells.length < 2) continue;
            const rowName = cells[0].trim() || 'Row ' + (i + 1);
            html += '<tr>';
            html += '<td class="text-start fw-bold"><small>' + rowName + '</small></td>';
            for (let c = 1; c < 8; c++) {
                html += '<td class="fc-cell" contenteditable="true">' + (cells[c] || '').trim() + '</td>';
            }
            html += '</tr>';
        }
        forecastBody.innerHTML = html || '<tr><td colspan="8" class="text-muted text-center py-3">No data</td></tr>';
        forecastTextarea.value = text;
    }

    forecastGrid.addEventListener('paste', function(e) {
        e.preventDefault();
        const text = (e.clipboardData || window.clipboardData).getData('text');
        if (text) renderForecastGrid(text);
    });

    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('fc-cell')) {
            const rows = [];
            document.querySelectorAll('#forecastBody tr').forEach(function(row) {
                const cells = [];
                row.querySelectorAll('.fc-cell').forEach(function(c) { cells.push(c.textContent.trim()); });
                if (cells.length > 0) rows.push(cells.join('\t'));
            });
            forecastTextarea.value = rows.join('\n');
        }
    });


    // Sync before submit
    document.getElementById('tlImportForm').addEventListener('submit', function() {
        // Sync TL grid
        var tlRows = document.querySelectorAll('#gridBody tr');
        if (tlRows.length > 0 && tlRows[0].querySelector('.grid-cell')) {
            var rows = [];
            tlRows.forEach(function(row) {
                var cells = [];
                row.querySelectorAll('.grid-cell').forEach(function(c) { cells.push(c.textContent.trim()); });
                if (cells.length > 0) rows.push(cells.join('\t'));
            });
            if (rows.length > 0) importTextarea.value = rows.join('\n');
        }

        // Sync Forecast grid — first cell in each row is the shift code name
        var fcRows = document.querySelectorAll('#forecastBody tr');
        if (fcRows.length > 0 && fcRows[0].querySelector('.fc-cell')) {
            var fRows = [];
            fcRows.forEach(function(row) {
                var rowName = row.querySelector('td:first-child')?.textContent?.trim() || '';
                var cells = [rowName];
                row.querySelectorAll('.fc-cell').forEach(function(c) { cells.push(c.textContent.trim()); });
                if (cells.length > 1) rows.push(cells.join('\t'));
            });
            if (fRows.length > 0) forecastTextarea.value = fRows.join('\n');
        }
    });
});
</script>
