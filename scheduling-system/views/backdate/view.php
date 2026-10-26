<div class="row mb-3">
    <div class="col">
        <h2><i class="bi bi-calendar-range"></i> Backdate Detail</h2>
        <p class="text-muted">
            File: <strong><?= htmlspecialchars($entry['file_name'] ?? '—') ?></strong>
            &middot; Week: <strong><?= htmlspecialchars($entry['week_start_date']) ?></strong>
            &middot; Group: <strong><?= htmlspecialchars($entry['group_name']) ?></strong>
        </p>
    </div>
    <div class="col text-end">
        <a href="index.php?page=backdate" class="btn btn-outline-secondary">
            <i class="bi bi-list"></i> Back to List
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <form method="post" action="index.php?page=backdate&action=store" id="editForm">
            <input type="hidden" name="week_start" value="<?= htmlspecialchars($entry['week_start_date']) ?>">
            <input type="hidden" name="group_name" value="<?= htmlspecialchars($entry['group_name']) ?>">
            <input type="hidden" name="file_name" value="<?= htmlspecialchars($entry['file_name'] ?? '') ?>">
            <input type="hidden" name="import_data" id="importDataField" value="<?= htmlspecialchars($entry['import_data'] ?? '') ?>">

            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0" style="font-size:0.82rem;" id="editGrid">
                    <thead class="table-dark">
                        <tr>
                            <th style="min-width:140px;">Staff Email</th>
                            <th style="min-width:140px;">TL Email</th>
                            <th style="min-width:60px;">Gender</th>
                            <th style="min-width:90px;">Mon</th>
                            <th style="min-width:90px;">Tue</th>
                            <th style="min-width:90px;">Wed</th>
                            <th style="min-width:90px;">Thu</th>
                            <th style="min-width:90px;">Fri</th>
                            <th style="min-width:90px;">Sat</th>
                            <th style="min-width:90px;">Sun</th>
                        </tr>
                    </thead>
                    <tbody id="editBody">
                        <?php
                        $lines = explode("\n", trim($entry['import_data'] ?? ''));
                        if (!empty($lines) && $lines[0] !== ''):
                            foreach ($lines as $line):
                                $cells = explode("\t", $line);
                                if (count($cells) < 2) continue;
                        ?>
                        <tr>
                            <?php for ($c = 0; $c < 10; $c++): ?>
                            <td class="grid-cell" contenteditable="true"><?= htmlspecialchars($cells[$c] ?? '') ?></td>
                            <?php endfor; ?>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="10" class="text-muted text-center py-3">No data.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="p-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Changes</button>
                <a href="index.php?page=backdate&action=delete&id=<?= $entry['id'] ?>" class="btn btn-danger"
                   data-confirm="Delete this entry?" data-confirm-title="Delete this entry?"
                   data-confirm-ok="Delete">
                    <i class="bi bi-trash"></i> Delete
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('editForm');
    var field = document.getElementById('importDataField');
    form.addEventListener('submit', function() {
        var rows = [];
        document.querySelectorAll('#editBody tr').forEach(function(row) {
            var cells = [];
            row.querySelectorAll('.grid-cell').forEach(function(c) { cells.push(c.textContent.trim()); });
            if (cells.length > 0 && cells.some(function(v){ return v !== ''; })) rows.push(cells.join('\t'));
        });
        field.value = rows.join('\n');
    });
});
</script>
