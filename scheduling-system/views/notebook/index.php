<?php
/**
 * View: Personal Notebook — Task Timeline, Calendar & Notes
 */
?>
<style>
/* Notebook Layout Styles */
.notebook-hero {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    color: #fff;
    border-radius: 14px;
    padding: 20px 24px;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px -3px rgba(15, 23, 42, 0.2);
}

.calendar-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.calendar-header {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 12px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    background: #cbd5e1;
    gap: 1px;
}

.cal-day-name {
    background: #f1f5f9;
    padding: 8px 4px;
    text-align: center;
    font-size: 0.75rem;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.cal-cell {
    background: #fff;
    min-height: 95px;
    padding: 6px;
    display: flex;
    flex-direction: column;
    transition: background 0.15s ease;
    cursor: pointer;
    position: relative;
}

.cal-cell:hover {
    background: #f8fafc;
}

.cal-cell.other-month {
    background: #fafafa;
    opacity: 0.45;
}

.cal-cell.is-today {
    background: #eff6ff;
}

.cal-cell.is-today .cal-num {
    background: #2563eb;
    color: #fff;
    border-radius: 50%;
    width: 22px;
    height: 22px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}

.cal-cell.is-selected {
    outline: 2px solid #2563eb;
    outline-offset: -2px;
    z-index: 2;
}

.cal-num {
    font-size: 0.8rem;
    font-weight: 600;
    color: #334155;
    margin-bottom: 4px;
    line-height: 1;
}

.cal-task-pill {
    font-size: 0.68rem;
    line-height: 1.25;
    padding: 2px 5px;
    border-radius: 4px;
    color: #fff;
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 3px;
    transition: transform 0.1s ease;
}

.cal-task-pill:hover {
    transform: scale(1.02);
}

.cal-task-pill.is-done {
    text-decoration: line-through;
    opacity: 0.65;
}

/* Timeline Cards */
.timeline-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 12px 14px;
    margin-bottom: 10px;
    transition: all 0.18s ease;
    border-left: 4px solid #cbd5e1;
}

.timeline-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 12px -2px rgba(0,0,0,0.06);
    transform: translateY(-1px);
}

.timeline-card.prio-High { border-left-color: #ef4444; }
.timeline-card.prio-Medium { border-left-color: #f59e0b; }
.timeline-card.prio-Low { border-left-color: #10b981; }

.timeline-card.is-done {
    opacity: 0.6;
    background: #f8fafc;
}

.timeline-card.is-done .task-title {
    text-decoration: line-through;
    color: #64748b;
}

.task-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
    border-radius: 4px;
}
</style>

<!-- Top Hero & Stats -->
<div class="notebook-hero d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge bg-primary px-2.5 py-1 text-uppercase" style="letter-spacing:0.05em; font-size:0.68rem;">
                <i class="bi bi-shield-lock"></i> 100% Private Notebook
            </span>
            <span class="text-white-50 small">&middot; Khusus Akun Anda</span>
        </div>
        <h2 class="h4 mb-1 fw-bold text-white d-flex align-items-center gap-2">
            <i class="bi bi-journal-bookmark-fill text-primary"></i> Personal Notebook &amp; Task Timeline
        </h2>
        <p class="text-white-50 small mb-0">
            Kelola jadwal deadline kerja, timeline personal, dan catatan operasional tanpa terlihat user lain.
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-primary shadow-sm" onclick="openTaskModal()">
            <i class="bi bi-plus-circle me-1"></i> Tambah Task Baru
        </button>
    </div>
</div>

<!-- Main Content Grid -->
<div class="row g-3">
    
    <!-- LEFT: Calendar View -->
    <div class="col-lg-7 col-xl-8">
        <div class="calendar-card">
            <div class="calendar-header">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="mb-0 fw-bold text-dark" id="calMonthYear">Bulan Tahun</h5>
                    <button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="jumpToToday()" title="Lompat ke Hari Ini" style="font-size:0.75rem;">
                        Hari Ini
                    </button>
                </div>
                <div class="d-flex align-items-center gap-1">
                    <button class="btn btn-sm btn-outline-secondary px-2.5" onclick="prevMonth()" title="Bulan Sebelumnya">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-secondary px-2.5" onclick="nextMonth()" title="Bulan Berikutnya">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>

            <!-- Calendar Days Header -->
            <div class="calendar-grid">
                <div class="cal-day-name text-danger">Min</div>
                <div class="cal-day-name">Sen</div>
                <div class="cal-day-name">Sel</div>
                <div class="cal-day-name">Rab</div>
                <div class="cal-day-name">Kam</div>
                <div class="cal-day-name">Jum</div>
                <div class="cal-day-name text-primary">Sab</div>
            </div>

            <!-- Calendar Body Cells -->
            <div class="calendar-grid" id="calendarCells">
                <!-- Rendered dynamically by JS -->
            </div>
            
            <div class="p-2.5 bg-light border-top d-flex flex-wrap align-items-center justify-content-between text-muted" style="font-size:0.75rem;">
                <div class="d-flex align-items-center gap-3">
                    <span><i class="bi bi-cursor text-primary"></i> Klik kotak tanggal untuk menambah task di hari tersebut</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-danger">High</span>
                    <span class="badge bg-warning text-dark">Medium</span>
                    <span class="badge bg-success">Low</span>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT: Timeline & Notes Sidebar -->
    <div class="col-lg-5 col-xl-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-bottom pt-3 pb-2">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-list-check text-primary"></i> Timeline Task &amp; Notes</h6>
                    <span class="badge bg-light text-dark border" id="taskTotalCount">0 Task</span>
                </div>
                
                <!-- Filter Tabs -->
                <ul class="nav nav-pills nav-fill" style="font-size:0.75rem;" id="timelineTabs">
                    <li class="nav-item">
                        <button class="nav-link active py-1" onclick="setTimelineFilter('all', this)">Semua</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link py-1" onclick="setTimelineFilter('today', this)">Hari Ini</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link py-1" onclick="setTimelineFilter('upcoming', this)">Upcoming</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link py-1" onclick="setTimelineFilter('done', this)">Done</button>
                    </li>
                </ul>
            </div>

            <!-- Search box in timeline -->
            <div class="p-2 border-bottom bg-light">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="timelineSearch" oninput="renderTimeline()" class="form-control border-start-0" placeholder="Cari task atau catatan...">
                </div>
            </div>

            <div class="card-body p-2.5 overflow-auto" id="timelineList" style="max-height: 520px;">
                <!-- Rendered dynamically by JS -->
            </div>
        </div>
    </div>

</div>

<!-- MODAL: Tambah / Edit Task -->
<div class="modal fade" id="taskModal" tabindex="-1" aria-labelledby="taskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-bottom py-3">
                <h6 class="modal-title fw-bold" id="taskModalLabel">
                    <i class="bi bi-pencil-square text-primary me-1"></i> Tambah Task &amp; Note
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="taskForm" onsubmit="handleTaskSubmit(event)">
                <input type="hidden" name="id" id="taskId" value="0">
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small mb-1">Judul Task / Deadline <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="taskTitle" required class="form-control" placeholder="Contoh: Audit BAK Bulan Oktober, Rilis Schedule W4...">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label fw-semibold small mb-1">Tanggal Deadline <span class="text-danger">*</span></label>
                            <input type="date" name="due_date" id="taskDueDate" required class="form-control">
                        </div>
                        <div class="col-5">
                            <label class="form-label fw-semibold small mb-1">Jam (Opsional)</label>
                            <input type="time" name="due_time" id="taskDueTime" class="form-control">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold small mb-1">Prioritas</label>
                            <select name="priority" id="taskPriority" class="form-select">
                                <option value="Low">Low (Rendah)</option>
                                <option value="Medium" selected>Medium (Sedang)</option>
                                <option value="High">High (Mendesak)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold small mb-1">Status</label>
                            <select name="status" id="taskStatus" class="form-select">
                                <option value="Todo" selected>Todo (Belum)</option>
                                <option value="In Progress">In Progress (Proses)</option>
                                <option value="Done">Done (Selesai)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small mb-1">Warna Penanda di Kalender</label>
                        <div class="d-flex gap-2 align-items-center">
                            <input type="color" name="color" id="taskColor" class="form-control form-control-color p-1 rounded-3" value="#2563eb" style="width: 44px; height: 36px;">
                            <span class="small text-muted">Pilih warna sorot badge di grid kalender</span>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold small mb-1">Catatan / Detail Note (Personal)</label>
                        <textarea name="notes" id="taskNotes" rows="4" class="form-control" placeholder="Tuliskan rincian langkah, link dokumen, poin checklist, atau catatan penting di sini..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top py-2 px-3 d-flex justify-content-between">
                    <div>
                        <button type="button" id="btnDeleteTask" class="btn btn-outline-danger btn-sm" onclick="handleDeleteTask()" style="display:none;">
                            <i class="bi bi-trash"></i> Hapus
                        </button>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary btn-sm px-3" id="btnSaveTask">
                            <i class="bi bi-check2"></i> Simpan Task
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Initial Data from Controller -->
<script>
window.NOTEBOOK_DATA = <?= json_encode($tasks, JSON_UNESCAPED_UNICODE) ?>;
</script>

<script>
let allTasks = Array.isArray(window.NOTEBOOK_DATA) ? window.NOTEBOOK_DATA : [];
let currentCalDate = new Date();
let currentFilter = 'all';
let selectedDateStr = null;

const MONTH_NAMES = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

function initNotebook() {
    renderCalendar();
    renderTimeline();
}

// Format YYYY-MM-DD
function formatDate(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function prevMonth() {
    currentCalDate.setMonth(currentCalDate.getMonth() - 1);
    renderCalendar();
}

function nextMonth() {
    currentCalDate.setMonth(currentCalDate.getMonth() + 1);
    renderCalendar();
}

function jumpToToday() {
    currentCalDate = new Date();
    selectedDateStr = formatDate(currentCalDate);
    renderCalendar();
    setTimelineFilter('today');
}

function renderCalendar() {
    const year = currentCalDate.getFullYear();
    const month = currentCalDate.getMonth();

    document.getElementById('calMonthYear').textContent = `${MONTH_NAMES[month]} ${year}`;

    const firstDayIndex = new Date(year, month, 1).getDay(); // 0 is Sunday
    const lastDay = new Date(year, month + 1, 0).getDate();
    const prevLastDay = new Date(year, month, 0).getDate();

    const todayStr = formatDate(new Date());

    const container = document.getElementById('calendarCells');
    container.innerHTML = '';

    // Task Lookup Map: date -> Array of tasks
    const taskMap = {};
    allTasks.forEach(t => {
        if (!taskMap[t.due_date]) taskMap[t.due_date] = [];
        taskMap[t.due_date].push(t);
    });

    // Previous month filler days
    for (let x = firstDayIndex; x > 0; x--) {
        const d = prevLastDay - x + 1;
        const prevMonthDate = new Date(year, month - 1, d);
        const dateStr = formatDate(prevMonthDate);
        container.appendChild(createCell(d, dateStr, true, todayStr, taskMap[dateStr]));
    }

    // Current month days
    for (let i = 1; i <= lastDay; i++) {
        const thisDate = new Date(year, month, i);
        const dateStr = formatDate(thisDate);
        container.appendChild(createCell(i, dateStr, false, todayStr, taskMap[dateStr]));
    }

    // Next month filler days (to make 35 or 42 cells)
    const totalCells = firstDayIndex + lastDay;
    const nextDays = (totalCells > 35 ? 42 : 35) - totalCells;
    for (let j = 1; j <= nextDays; j++) {
        const nextMonthDate = new Date(year, month + 1, j);
        const dateStr = formatDate(nextMonthDate);
        container.appendChild(createCell(j, dateStr, true, todayStr, taskMap[dateStr]));
    }
}

function createCell(dayNum, dateStr, isOtherMonth, todayStr, tasksForDay) {
    const cell = document.createElement('div');
    cell.className = 'cal-cell' + (isOtherMonth ? ' other-month' : '') + (dateStr === todayStr ? ' is-today' : '') + (dateStr === selectedDateStr ? ' is-selected' : '');
    cell.onclick = (e) => {
        // If clicking on cell background, open add task modal for this date
        if (e.target.closest('.cal-task-pill')) return;
        selectedDateStr = dateStr;
        renderCalendar();
        openTaskModal(null, dateStr);
    };

    const numSpan = document.createElement('div');
    numSpan.className = 'cal-num';
    numSpan.textContent = dayNum;
    cell.appendChild(numSpan);

    if (tasksForDay && tasksForDay.length > 0) {
        // Show max 3 pills
        const displayLimit = 3;
        tasksForDay.slice(0, displayLimit).forEach(task => {
            const pill = document.createElement('div');
            pill.className = 'cal-task-pill' + (task.status === 'Done' ? ' is-done' : '');
            pill.style.backgroundColor = task.color || '#2563eb';
            pill.title = `${task.title} (${task.status})`;
            pill.onclick = (e) => {
                e.stopPropagation();
                openTaskModal(task);
            };

            const icon = document.createElement('i');
            icon.className = task.status === 'Done' ? 'bi bi-check2' : 'bi bi-dot';
            pill.appendChild(icon);

            const titleText = document.createTextNode(task.title);
            pill.appendChild(titleText);

            cell.appendChild(pill);
        });

        if (tasksForDay.length > displayLimit) {
            const more = document.createElement('div');
            more.className = 'text-muted';
            more.style.fontSize = '0.65rem';
            more.style.fontWeight = '600';
            more.textContent = `+${tasksForDay.length - displayLimit} lainnya`;
            cell.appendChild(more);
        }
    }

    return cell;
}

function setTimelineFilter(filter, el) {
    currentFilter = filter;
    if (el) {
        document.querySelectorAll('#timelineTabs .nav-link').forEach(btn => btn.classList.remove('active'));
        el.classList.add('active');
    }
    renderTimeline();
}

function renderTimeline() {
    const list = document.getElementById('timelineList');
    const query = (document.getElementById('timelineSearch').value || '').toLowerCase().trim();
    const todayStr = formatDate(new Date());

    let filtered = allTasks.filter(t => {
        // Text filter
        if (query) {
            const inTitle = (t.title || '').toLowerCase().includes(query);
            const inNotes = (t.notes || '').toLowerCase().includes(query);
            if (!inTitle && !inNotes) return false;
        }

        // Tab filter
        if (currentFilter === 'today') {
            return t.due_date === todayStr;
        } else if (currentFilter === 'upcoming') {
            return t.due_date >= todayStr && t.status !== 'Done';
        } else if (currentFilter === 'done') {
            return t.status === 'Done';
        }
        return true;
    });

    document.getElementById('taskTotalCount').textContent = `${allTasks.length} Task`;

    if (filtered.length === 0) {
        list.innerHTML = `
            <div class="text-center py-5 text-muted">
                <i class="bi bi-journal-check" style="font-size: 2.2rem; opacity: 0.35;"></i>
                <p class="small mt-2 mb-0">Tidak ada task yang cocok.</p>
            </div>
        `;
        return;
    }

    list.innerHTML = filtered.map(t => {
        const isDone = t.status === 'Done';
        const isOverdue = t.due_date < todayStr && !isDone;
        const prioBadgeClass = t.priority === 'High' ? 'bg-danger' : (t.priority === 'Medium' ? 'bg-warning text-dark' : 'bg-success');
        
        let dueLabel = t.due_date;
        if (t.due_date === todayStr) {
            dueLabel = 'Hari ini' + (t.due_time ? ', ' + t.due_time.substring(0, 5) : '');
        } else {
            const parts = t.due_date.split('-');
            if (parts.length === 3) dueLabel = `${parts[2]}/${parts[1]}/${parts[0]}` + (t.due_time ? ' ' + t.due_time.substring(0, 5) : '');
        }

        return `
            <div class="timeline-card prio-${t.priority} ${isDone ? 'is-done' : ''}" onclick="openTaskModalById(${t.id})">
                <div class="d-flex align-items-start gap-2">
                    <input type="checkbox" class="task-checkbox mt-1" ${isDone ? 'checked' : ''} onclick="handleToggleDone(event, ${t.id}, '${isDone ? 'Todo' : 'Done'}')">
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex align-items-center justify-content-between gap-1 mb-1">
                            <span class="task-title fw-bold text-dark small text-truncate d-block">${escapeHtml(t.title)}</span>
                            <span class="badge ${prioBadgeClass} py-0 px-1.5" style="font-size:0.65rem;">${t.priority}</span>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2 text-muted" style="font-size: 0.72rem;">
                            <span class="${isOverdue ? 'text-danger fw-bold' : ''}">
                                <i class="bi bi-calendar-event me-0.5"></i> ${dueLabel}
                                ${isOverdue ? '<span class="badge bg-danger-subtle text-danger ms-1">Overdue</span>' : ''}
                            </span>
                            <span class="badge bg-secondary-subtle text-secondary py-0">${t.status}</span>
                        </div>
                        ${t.notes ? `<div class="text-muted small mt-1.5 text-truncate" style="font-size: 0.75rem;"><i class="bi bi-sticky me-1"></i>${escapeHtml(t.notes)}</div>` : ''}
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function openTaskModal(task = null, defaultDate = null) {
    const modalEl = document.getElementById('taskModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    if (task) {
        document.getElementById('taskModalLabel').innerHTML = '<i class="bi bi-pencil-square text-primary me-1"></i> Edit Task &amp; Note';
        document.getElementById('taskId').value = task.id;
        document.getElementById('taskTitle').value = task.title || '';
        document.getElementById('taskDueDate').value = task.due_date || '';
        document.getElementById('taskDueTime').value = task.due_time ? task.due_time.substring(0, 5) : '';
        document.getElementById('taskPriority').value = task.priority || 'Medium';
        document.getElementById('taskStatus').value = task.status || 'Todo';
        document.getElementById('taskColor').value = task.color || '#2563eb';
        document.getElementById('taskNotes').value = task.notes || '';
        document.getElementById('btnDeleteTask').style.display = 'inline-block';
    } else {
        document.getElementById('taskModalLabel').innerHTML = '<i class="bi bi-plus-circle text-primary me-1"></i> Tambah Task &amp; Note';
        document.getElementById('taskId').value = '0';
        document.getElementById('taskTitle').value = '';
        document.getElementById('taskDueDate').value = defaultDate || formatDate(new Date());
        document.getElementById('taskDueTime').value = '';
        document.getElementById('taskPriority').value = 'Medium';
        document.getElementById('taskStatus').value = 'Todo';
        document.getElementById('taskColor').value = '#2563eb';
        document.getElementById('taskNotes').value = '';
        document.getElementById('btnDeleteTask').style.display = 'none';
    }

    modal.show();
}

function openTaskModalById(id) {
    const task = allTasks.find(t => t.id == id);
    if (task) openTaskModal(task);
}

async function handleTaskSubmit(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSaveTask');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...';

    const form = document.getElementById('taskForm');
    const formData = new FormData(form);

    try {
        const res = await fetch('index.php?page=notebook&action=save', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        });
        const json = await res.json();
        if (json.success && json.task) {
            const idx = allTasks.findIndex(t => t.id == json.task.id);
            if (idx >= 0) {
                allTasks[idx] = json.task;
            } else {
                allTasks.push(json.task);
            }
            renderCalendar();
            renderTimeline();
            bootstrap.Modal.getInstance(document.getElementById('taskModal')).hide();
        } else {
            alert(json.message || 'Gagal menyimpan task.');
        }
    } catch (err) {
        console.error(err);
        // Fallback to form submit
        form.submit();
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check2"></i> Simpan Task';
    }
}

async function handleToggleDone(e, id, newStatus) {
    e.stopPropagation();
    const task = allTasks.find(t => t.id == id);
    if (!task) return;

    // Optimistic UI update
    task.status = newStatus;
    renderCalendar();
    renderTimeline();

    try {
        const formData = new FormData();
        formData.append('id', id);
        formData.append('status', newStatus);

        const res = await fetch('index.php?page=notebook&action=toggleStatus', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        });
        const json = await res.json();
        if (!json.success) {
            // Revert on failure
            task.status = (newStatus === 'Done') ? 'Todo' : 'Done';
            renderCalendar();
            renderTimeline();
        }
    } catch (err) {
        console.error(err);
    }
}

async function handleDeleteTask() {
    const id = document.getElementById('taskId').value;
    if (!id || id === '0') return;

    if (!confirm('Hapus task ini dari notebook Anda?')) return;

    try {
        const formData = new FormData();
        formData.append('id', id);

        const res = await fetch('index.php?page=notebook&action=delete', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        });
        const json = await res.json();
        if (json.success) {
            allTasks = allTasks.filter(t => t.id != id);
            renderCalendar();
            renderTimeline();
            bootstrap.Modal.getInstance(document.getElementById('taskModal')).hide();
        } else {
            alert(json.message || 'Gagal menghapus task.');
        }
    } catch (err) {
        console.error(err);
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>'"]/g, tag => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#39;',
        '"': '&quot;'
    }[tag] || tag));
}

document.addEventListener('DOMContentLoaded', initNotebook);
</script>

