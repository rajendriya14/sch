<div class="row mb-3">
    <div class="col">
        <h2><i class="bi bi-person-badge"></i>
            <?= isset($entry) ? 'Edit TL Schedule Entry' : 'Add TL Schedule Entry' ?>
        </h2>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="post"
              action="index.php?page=tl_schedule&action=<?= isset($entry) ? 'update' : 'store' ?>"
              class="row g-3">

            <?php if (isset($entry)): ?>
                <input type="hidden" name="id" value="<?= $entry['id'] ?>">
            <?php endif; ?>

            <div class="col-md-4">
                <label for="team_leader" class="form-label">Team Leader <span class="text-danger">*</span></label>
                <select class="form-select" id="team_leader" name="team_leader" required>
                    <option value="">— Select —</option>
                    <?php foreach ($teamLeaders as $tl): ?>
                        <option value="<?= htmlspecialchars($tl) ?>"
                            <?= (isset($entry) && $entry['team_leader'] === $tl) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tl) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label for="day_of_week" class="form-label">Day <span class="text-danger">*</span></label>
                <select class="form-select" id="day_of_week" name="day_of_week" required>
                    <option value="">— Select —</option>
                    <?php $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']; ?>
                    <?php foreach ($dayNames as $idx => $day): ?>
                        <option value="<?= $idx ?>"
                            <?= (isset($entry) && (int)$entry['day_of_week'] === $idx) ? 'selected' : '' ?>>
                            <?= $day ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label for="shift_code" class="form-label">Shift <span class="text-danger">*</span></label>
                <select class="form-select" id="shift_code" name="shift_code" required>
                    <option value="">— Select —</option>
                    <?php foreach ($shiftCodes as $code): ?>
                        <option value="<?= htmlspecialchars($code) ?>"
                            <?= (isset($entry) && $entry['shift_code'] === $code) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($code) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> <?= isset($entry) ? 'Update' : 'Save' ?> Entry
                </button>
                <a href="index.php?page=tl_schedule" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>
