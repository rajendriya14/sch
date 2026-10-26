<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2><i class="bi bi-people"></i> Staff Master</h2>
    </div>
    <div>
        <a href="index.php?page=staff&action=create" class="btn btn-primary">
            <i class="bi bi-upload"></i> Import Staff
        </a>
    </div>
</div>

<?php if (empty($grouped)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-people" style="font-size:3rem;opacity:0.3;"></i>
            <p class="mt-3 text-muted">No employees registered yet.</p>
            <a href="index.php?page=staff&action=create" class="btn btn-primary">
                <i class="bi bi-upload"></i> Import Now
            </a>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($grouped as $compoKey => $group): ?>
        <?php $compoMonth = $group['compo_month']; $compoYear = $group['compo_year']; ?>
        <?php $empCount = count($group['employees']); ?>
        <div class="card mb-4">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <span>
                    <i class="bi bi-calendar-month"></i>
                    <strong><?= htmlspecialchars($compoKey) ?></strong>
                    <span class="badge bg-light text-dark ms-2"><?= $empCount ?> employees</span>
                </span>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-light"
                            data-bs-toggle="collapse" data-bs-target="#compo_<?= md5($compoKey) ?>">
                        <i class="bi bi-eye"></i> View
                    </button>
                    <?php if ($compoMonth && $compoYear): ?>
                    <form method="post" action="index.php?page=staff&action=deleteCompo"
                          style="display:inline;"
                          data-confirm="Delete ALL <?= $empCount ?> employees for <?= htmlspecialchars($compoKey) ?>?"
                          data-confirm-title="Delete all employees?"
                          data-confirm-ok="Ya, hapus semua">
                        <input type="hidden" name="month" value="<?= htmlspecialchars($compoMonth) ?>">
                        <input type="hidden" name="year" value="<?= htmlspecialchars($compoYear) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash"></i> Delete
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <div class="collapse" id="compo_<?= md5($compoKey) ?>">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>NIP</th>
                                    <th>Agent Name</th>
                                    <th>Agent Email</th>
                                    <th>Position</th>
                                    <th>LOB</th>
                                    <th>Email TL</th>
                                    <th>Op. Manager</th>
                                    <th>Gender</th>
                                    <th>Domain</th>
                                    <th>File Name</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($group['employees'] as $emp): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($emp['employee_id']) ?></strong></td>
                                        <td><?= htmlspecialchars($emp['name']) ?></td>
                                        <td><?= htmlspecialchars($emp['email']) ?></td>
                                        <td><?= htmlspecialchars($emp['position'] ?: '—') ?></td>
                                        <td><?= htmlspecialchars($emp['lob'] ?: '—') ?></td>
                                        <td><?= htmlspecialchars($emp['team_leader_email'] ?: '—') ?></td>
                                        <td><?= htmlspecialchars($emp['operational_mgr'] ?: '—') ?></td>
                                        <td>
                                            <span class="badge <?= $emp['gender'] === 'Female' ? 'bg-danger' : 'bg-primary' ?>">
                                                <?= $emp['gender'] ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($emp['domain'] ?: '—') ?></td>
                                        <td><small><?= htmlspecialchars($emp['file_name'] ?: '—') ?></small></td>
                                        <td>
                                            <span class="badge <?= $emp['employment_status'] === 'Active' ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= $emp['employment_status'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="index.php?page=staff&action=edit&id=<?= $emp['id'] ?>"
                                               class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="index.php?page=staff&action=delete&id=<?= $emp['id'] ?>"
                                               class="btn btn-sm btn-outline-danger"
                                               data-confirm="Delete <?= htmlspecialchars($emp['name']) ?>?"
                                               title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
