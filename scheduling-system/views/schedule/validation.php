<div class="row mt-4 mb-3">
    <div class="col">
        <h3><i class="bi bi-check-circle"></i> Validation Summary</h3>
        <p class="text-muted">
            Overall status:
            <?php if ($data['validation']['overall_passed']): ?>
                <span class="badge bg-success">All Rules Passed</span>
            <?php else: ?>
                <span class="badge bg-danger">Some Rules Need Attention</span>
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="row g-3">
    <?php foreach ($data['validation']['rules'] as $rule): ?>
        <?php
            $cardClass = $rule['passed'] ? 'passed' : 'failed';
            $icon = $rule['passed'] ? 'bi-check-circle-fill text-success' : 'bi-exclamation-triangle-fill text-danger';
        ?>
        <div class="col-md-6">
            <div class="card validation-card <?= $cardClass ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="card-title">
                                <i class="bi <?= $icon ?>"></i> <?= htmlspecialchars($rule['name']) ?>
                            </h5>
                            <p class="card-text"><?= htmlspecialchars($rule['details']) ?></p>
                        </div>
                        <div class="text-end ms-3">
                            <?php if (isset($rule['percentage'])): ?>
                                <h3 class="mb-0 <?= $rule['passed'] ? 'text-success' : 'text-danger' ?>">
                                    <?= $rule['percentage'] ?>%
                                </h3>
                                <small class="text-muted">compliance</small>
                                <div class="progress progress-thin mt-1" style="width: 80px;">
                                    <div class="progress-bar <?= $rule['passed'] ? 'bg-success' : 'bg-danger' ?>"
                                         role="progressbar"
                                         style="width: <?= $rule['percentage'] ?>%"
                                         aria-valuenow="<?= $rule['percentage'] ?>"
                                         aria-valuemin="0"
                                         aria-valuemax="100">
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($rule['violations'])): ?>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-outline-danger" type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#violations-<?= md5($rule['name']) ?>"
                                    aria-expanded="false">
                                <i class="bi bi-list"></i> View Violations (<?= count($rule['violations']) ?>)
                            </button>
                            <div class="collapse mt-2" id="violations-<?= md5($rule['name']) ?>">
                                <div class="card card-body bg-light py-2 px-3">
                                    <ul class="mb-0 small">
                                        <?php foreach ($rule['violations'] as $v): ?>
                                            <li><?= htmlspecialchars($v) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
