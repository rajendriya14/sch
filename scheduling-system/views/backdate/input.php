<div class="row mb-3">
    <div class="col">
        <h2><i class="bi bi-upload"></i> Input Backdate</h2>
        <p class="text-muted">Paste data jadwal sebelumnya dari Excel.</p>
    </div>
    <div class="col text-end">
        <a href="index.php?page=backdate" class="btn btn-outline-secondary">
            <i class="bi bi-list"></i> Back to List
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="post" action="index.php?page=backdate&action=store" id="backdateForm">
            <!-- WEEK SELECTOR + GROUP + FILE NAME -->
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
                        <option value="2026" selected>2026</option>
                        <option value="2027">2027</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Week</label>
                    <select class="form-select" id="weekSelect" name="week_start" disabled>
                        <option value="">— Select Month and Year first —</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Group</label>
                    <select class="form-select" id="groupSelect" name="group_name" required>
                        <option value="">— Select Group —</option>
                        <option value="Seller">Seller</option>
                        <option value="Product">Product</option>
                        <option value="Content">Content</option>
                        <option value="IPR">IPR</option>
                        <option value="UPL">UPL</option>
                        <option value="Global Appeal">Global Appeal</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">File Name <small class="text-muted">(auto)</small></label>
                    <input type="text" class="form-control" id="fileName" name="file_name" readonly
                           placeholder="File_Backdate_{Month}_{Week}_{Group}">
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0"><i class="bi bi-arrow-repeat"></i> Backdate Grid</h5>
                <button type="button" class="btn btn-sm btn-outline-dark" onclick="location.reload()" title="Reset table">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </button>
            </div>
            <div class="table-responsive border rounded" style="overflow:auto;">
                <table class="table table-sm table-bordered mb-0" style="font-size:0.82rem;" id="backdateGrid">
                    <thead class="table-dark">
                        <tr>
                            <th style="min-width:140px;">Staff Email</th>
                            <th style="min-width:140px;">TL Email</th>
                            <th style="min-width:60px;">Gender</th>
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
                        <?php for ($g = 1; $g <= 20; $g++): ?>
                        <tr>
                            <td class="grid-cell" contenteditable="true"></td>
                            <td class="grid-cell" contenteditable="true"></td>
                            <td class="grid-cell" contenteditable="true"></td>
                            <td class="grid-cell" contenteditable="true"></td>
                            <td class="grid-cell" contenteditable="true"></td>
                            <td class="grid-cell" contenteditable="true"></td>
                            <td class="grid-cell" contenteditable="true"></td>
                            <td class="grid-cell" contenteditable="true"></td>
                            <td class="grid-cell" contenteditable="true"></td>
                            <td class="grid-cell" contenteditable="true"></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            <textarea id="backdate_data" name="import_data" style="display:none;"></textarea>

            <div class="mt-3">
                <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Simpan</button>
                <a href="index.php?page=backdate" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Batal</a>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    function pad(n) { return String(n).padStart(2,'0'); }
    function iso(d) { return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate()); }
    function fmt(d) { return pad(d.getDate())+' '+months[d.getMonth()]; }

    function genWeeks(m,y) {
        var fd = new Date(y,m-1,1);
        var diff = (fd.getDay()+6)%7;
        var mon = new Date(fd); mon.setDate(fd.getDate()-diff);
        var ld = new Date(y,m,0);
        var w=[], wn=1;
        while(mon<=ld) {
            var sun = new Date(mon); sun.setDate(mon.getDate()+6);
            w.push({week:wn,start:new Date(mon),end:new Date(sun)});
            mon.setDate(mon.getDate()+7); wn++;
        }
        return w;
    }

    function populateWeeks() {
        var ms=document.getElementById('monthSelect'), ys=document.getElementById('yearSelect'), ws=document.getElementById('weekSelect');
        if(!ms||!ys||!ws) return;
        var m=parseInt(ms.value), y=parseInt(ys.value);
        ws.innerHTML=''; ws.disabled=true;
        if(!m||!y){ws.innerHTML='<option value="">— Select —</option>';updateFileName();return;}
        var weeks=genWeeks(m,y);
        if(weeks.length===0){ws.innerHTML='<option value="">— No weeks —</option>';updateFileName();return;}
        weeks.forEach(function(w){
            var opt=document.createElement('option');
            opt.value=iso(w.start);
            opt.textContent='Week '+w.week+' ('+fmt(w.start)+' - '+fmt(w.end)+')';
            ws.appendChild(opt);
        });
        ws.disabled=false;
        updateFileName();
    }

    function updateFileName() {
        var ms=document.getElementById('monthSelect'), ys=document.getElementById('yearSelect');
        var ws=document.getElementById('weekSelect'), gs=document.getElementById('groupSelect');
        var fn=document.getElementById('fileName');
        if(!fn) return;
        var m=ms.options[ms.selectedIndex]?.text||'', w=ws.options[ws.selectedIndex]?.text||'';
        var g=gs.options[gs.selectedIndex]?.value||'';
        fn.value='File_Backdate_'+(m||'')+'_'+(w||'')+'_'+(g||'');
    }

    document.addEventListener('DOMContentLoaded',function(){
        document.getElementById('monthSelect').onchange=function(){populateWeeks();updateFileName();};
        document.getElementById('yearSelect').onchange=function(){populateWeeks();updateFileName();};
        document.getElementById('weekSelect').onchange=updateFileName;
        document.getElementById('groupSelect').onchange=updateFileName;
        populateWeeks();updateFileName();

        var body = document.getElementById('gridBody');
        var textarea = document.getElementById('backdate_data');

        document.addEventListener('paste', function(e) {
            var cell = e.target.closest('.grid-cell');
            if (!cell || !cell.closest('#gridBody')) return;
            e.preventDefault();
            var text = (e.clipboardData || window.clipboardData).getData('text');
            if (!text) return;
            var lines = text.trim().split('\n');
            if (lines.length === 0) return;
            var currentRow = cell.closest('tr');
            var rows = Array.from(body.querySelectorAll('tr'));
            var startRowIdx = rows.indexOf(currentRow);
            for (var i = 0; i < lines.length; i++) {
                var cells = lines[i].split('\t');
                var targetRow = rows[startRowIdx + i];
                if (!targetRow && startRowIdx + i < 200) {
                    var newRow = document.createElement('tr');
                    for (var c = 0; c < 10; c++) {
                        var td = document.createElement('td');
                        td.className = 'grid-cell';
                        td.contentEditable = true;
                        newRow.appendChild(td);
                    }
                    body.appendChild(newRow);
                    targetRow = newRow;
                    rows = Array.from(body.querySelectorAll('tr'));
                }
                if (!targetRow) continue;
                var targetCells = targetRow.querySelectorAll('.grid-cell');
                for (var c = 0; c < cells.length && c < targetCells.length; c++) {
                    targetCells[c].textContent = cells[c].trim();
                }
            }
            var allRows = [];
            body.querySelectorAll('tr').forEach(function(r) {
                var vals = [];
                r.querySelectorAll('.grid-cell').forEach(function(td) { vals.push(td.textContent.trim()); });
                if (vals.some(function(v) { return v !== ''; })) allRows.push(vals.join('\t'));
            });
            textarea.value = allRows.join('\n');
        });

        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('grid-cell') && e.target.closest('#gridBody')) {
                var rows = [];
                document.querySelectorAll('#gridBody tr').forEach(function(row) {
                    var cells = [];
                    row.querySelectorAll('.grid-cell').forEach(function(c) { cells.push(c.textContent.trim()); });
                    if (cells.length > 0) rows.push(cells.join('\t'));
                });
                textarea.value = rows.join('\n');
            }
        });

        document.getElementById('backdateForm').addEventListener('submit', function() {
            var rows = [];
            document.querySelectorAll('#gridBody tr').forEach(function(row) {
                var cells = [];
                row.querySelectorAll('.grid-cell').forEach(function(c) { cells.push(c.textContent.trim()); });
                if (cells.length > 0) rows.push(cells.join('\t'));
            });
            if (rows.length > 0) textarea.value = rows.join('\n');
        });
    });
})();
</script>