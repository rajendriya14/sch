<div class="row mb-3">
    <div class="col">
        <h2><i class="bi bi-calendar2-week"></i> Schedule Detail</h2>
        <p class="text-muted">
            Week: <strong><?= date('d M Y', strtotime($header['week_start_date'])) ?></strong>
            &middot; Status: <span class="badge bg-<?= $header['status'] === 'Final' ? 'success' : 'secondary' ?>"><?= $header['status'] ?></span>
            &middot; Generated: <?= date('d M Y H:i', strtotime($header['generated_at'])) ?>
        </p>
    </div>
    <div class="col text-end">
        <a href="index.php?page=schedule_overview" class="btn btn-outline-secondary">
            <i class="bi bi-list"></i> Back to List
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0 schedule-grid" style="font-size:0.82rem;">
                <thead class="table-dark">
                    <tr>
                        <th class="text-start">Employee</th>
                        <?php foreach ($weekDates as $wd): ?>
                        <th class="text-center"><?= date('D d M', strtotime($wd)) ?></th>
                        <?php endforeach; ?>
                        <th class="text-center">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($details)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-3">No schedule data.</td></tr>
                    <?php else: ?>
                        <?php foreach ($details as $empDbId => $empData): ?>
                        <?php
                            // === Validasi per employee ===
                            $notes = [];
                            $offCount = 0;
                            $consecutive = 0;
                            $maxConsecutive = 0;
                            $prevShift = null;

                            for ($d = 0; $d < 7; $d++) {
                                $shiftCode = $empData['shifts'][$d] ?? '—';
                                if ($shiftCode === 'OFF' || $shiftCode === '—') {
                                    $offCount++;
                                    $consecutive = 0;
                                } else {
                                    $consecutive++;
                                    if ($consecutive > $maxConsecutive) $maxConsecutive = $consecutive;
                                }
                                // M3 rule
                                if ($prevShift === 'M3' && $shiftCode !== 'M3' && $shiftCode !== 'OFF' && $shiftCode !== '—') {
                                    $notes[] = 'Setelah M3 harus M3/OFF';
                                }
                                // Female restriction
                                if (($empData['gender'] ?? '') === 'Female' && in_array($shiftCode, ['S5','S6','S7','M1','M2','M3'])) {
                                    $notes[] = 'Shift ' . $shiftCode . ' terlarang untuk wanita';
                                }
                                $prevShift = $shiftCode;
                            }
                            if ($offCount < 2) $notes[] = 'Kurang hari OFF (min 2)';
                            if ($maxConsecutive > 6) $notes[] = 'Kerja ' . $maxConsecutive . ' hari berturut-turut';
                            $noteText = !empty($notes) ? implode('; ', array_unique($notes)) : 'OK';
                        ?>
                        <tr>
                            <td class="text-start">
                                <strong><?= htmlspecialchars($empData['emp_id']) ?></strong><br>
                                <small><?= htmlspecialchars($empData['name']) ?></small>
                            </td>
                            <?php for ($d = 0; $d < 7; $d++): ?>
                                <?php
                                $shiftCode = $empData['shifts'][$d] ?? '—';
                                $cat = $shiftCodes[$shiftCode] ?? '';
                                $cls = 'shift-cell';
                                if ($shiftCode === 'OFF' || $shiftCode === '—') $cls .= ' shift-OFF';
                                elseif ($cat === 'Morning') $cls .= ' shift-Morning';
                                elseif ($cat === 'Swing') $cls .= ' shift-Swing';
                                elseif ($cat === 'Night') $cls .= ' shift-Night';
                                ?>
                            <td class="text-center">
                                <span class="<?= $cls ?>"><?= htmlspecialchars($shiftCode) ?></span>
                            </td>
                            <?php endfor; ?>
                            <td class="text-center">
                                <?php if ($noteText === 'OK'): ?>
                                    <span class="badge bg-success">OK</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark" title="<?= htmlspecialchars($noteText) ?>">
                                        <i class="bi bi-exclamation-triangle"></i> Cek
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">
    <a href="index.php?page=schedule_overview&action=delete&id=<?= $header['id'] ?>" class="btn btn-danger"
       data-confirm="Hapus schedule ini?" data-confirm-title="Hapus schedule ini?" data-confirm-ok="Ya, hapus">
        <i class="bi bi-trash"></i> Delete
    </a>
</div>
