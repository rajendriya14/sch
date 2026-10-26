<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2><i class="bi bi-file-text"></i> Request OFF</h2>
    </div>
    <div>
        <a href="index.php?page=leave&action=create" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> New Request
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 w-100">
                <thead class="table-dark">
                    <tr>
                        <th>Employee</th>
                        <th>Employee ID</th>
                        <th>Request Date</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leaves)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">No leave requests yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($leaves as $leave): ?>
                            <tr>
                                <td><?= htmlspecialchars($leave['employee_name']) ?></td>
                                <td><?= htmlspecialchars($leave['emp_id']) ?></td>
                                <td><?= date('d M Y', strtotime($leave['request_date'])) ?></td>
                                <td><?= htmlspecialchars($leave['reason'] ?: '—') ?></td>
                                <td>
                                    <?php
                                        $badgeClass = match($leave['status']) {
                                            'Approved' => 'bg-success',
                                            'Rejected' => 'bg-danger',
                                            default    => 'bg-warning text-dark',
                                        };
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><?= $leave['status'] ?></span>
                                </td>
                                <td>
                                    <?php if ($leave['status'] === 'Pending'): ?>
                                        <a href="index.php?page=leave&action=approve&id=<?= $leave['id'] ?>"
                                           class="btn btn-sm btn-outline-success" title="Approve">
                                            <i class="bi bi-check-circle"></i>
                                        </a>
                                        <a href="index.php?page=leave&action=reject&id=<?= $leave['id'] ?>"
                                           class="btn btn-sm btn-outline-danger" title="Reject">
                                            <i class="bi bi-x-circle"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="index.php?page=leave&action=delete&id=<?= $leave['id'] ?>"
                                       class="btn btn-sm btn-outline-danger"
                                       data-confirm="Delete this leave request?"
                                       title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
