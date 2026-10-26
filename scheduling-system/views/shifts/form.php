<div class="row mb-3">
    <div class="col">
        <h2><i class="bi bi-clock"></i>
            <?= isset($shift) ? 'Edit Shift' : 'Add Shift' ?>
        </h2>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="post"
              action="index.php?page=shifts&action=<?= isset($shift) ? 'update' : 'store' ?>"
              class="row g-3">

            <?php if (isset($shift)): ?>
                <input type="hidden" name="id" value="<?= $shift['id'] ?>">
            <?php endif; ?>

            <div class="col-md-3">
                <label for="shift_code" class="form-label">Shift Code <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="shift_code" name="shift_code"
                       value="<?= htmlspecialchars($shift['shift_code'] ?? '') ?>"
                       maxlength="10" required
                       <?= isset($shift) ? 'readonly' : '' ?>>
            </div>

            <div class="col-md-3">
                <label for="start_time" class="form-label">Start Time <span class="text-danger">*</span></label>
                <input type="time" class="form-control" id="start_time" name="start_time"
                       value="<?= htmlspecialchars($shift['start_time'] ?? '') ?>" required>
            </div>

            <div class="col-md-3">
                <label for="end_time" class="form-label">End Time <span class="text-danger">*</span></label>
                <input type="time" class="form-control" id="end_time" name="end_time"
                       value="<?= htmlspecialchars($shift['end_time'] ?? '') ?>" required>
            </div>

            <div class="col-md-3">
                <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                <select class="form-select" id="category" name="category" required>
                    <option value="">— Select —</option>
                    <?php foreach (['Morning', 'Swing', 'Night'] as $cat): ?>
                        <option value="<?= $cat ?>"
                            <?= (isset($shift) && $shift['category'] === $cat) ? 'selected' : '' ?>>
                            <?= $cat ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label for="description" class="form-label">Description</label>
                <input type="text" class="form-control" id="description" name="description"
                       value="<?= htmlspecialchars($shift['description'] ?? '') ?>"
                       maxlength="255" placeholder="cth: Annual Leave">
            </div>

            <div class="col-md-3">
                <label class="form-label d-block">Female Restricted</label>
                <div class="form-check form-switch mt-2">
                    <input type="checkbox" class="form-check-input" id="female_restricted" name="female_restricted" value="1"
                           <?= !empty($isFemaleRestricted) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="female_restricted">Ya, shift ini terlarang untuk wanita</label>
                </div>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> <?= isset($shift) ? 'Update' : 'Save' ?> Shift
                </button>
                <a href="index.php?page=shifts" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>
