<div class="row mb-3">
    <div class="col">
        <h2><i class="bi bi-people"></i>
            <?= isset($employee) ? 'Edit Employee' : 'Add Employee' ?>
        </h2>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="post"
              action="index.php?page=staff&action=<?= isset($employee) ? 'update' : 'store' ?>"
              class="row g-3">

            <?php if (isset($employee)): ?>
                <input type="hidden" name="id" value="<?= $employee['id'] ?>">
            <?php endif; ?>

            <div class="col-md-4">
                <label for="employee_id" class="form-label">Employee ID <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="employee_id" name="employee_id"
                       value="<?= htmlspecialchars($employee['employee_id'] ?? '') ?>" required>
            </div>

            <div class="col-md-4">
                <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name"
                       value="<?= htmlspecialchars($employee['name'] ?? '') ?>" required>
            </div>

            <div class="col-md-4">
                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                <input type="email" class="form-control" id="email" name="email"
                       value="<?= htmlspecialchars($employee['email'] ?? '') ?>" required>
            </div>

            <div class="col-md-3">
                <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                <select class="form-select" id="gender" name="gender" required>
                    <option value="">— Select —</option>
                    <option value="Male"   <?= (isset($employee) && $employee['gender'] === 'Male')   ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= (isset($employee) && $employee['gender'] === 'Female') ? 'selected' : '' ?>>Female</option>
                </select>
            </div>

            <div class="col-md-3">
                <label for="team_leader" class="form-label">Team Leader</label>
                <input type="text" class="form-control" id="team_leader" name="team_leader"
                       value="<?= htmlspecialchars($employee['team_leader'] ?? '') ?>"
                       placeholder="e.g. John Manager">
            </div>

            <div class="col-md-3">
                <label for="employment_status" class="form-label">Status</label>
                <select class="form-select" id="employment_status" name="employment_status">
                    <option value="Active"   <?= (isset($employee) && $employee['employment_status'] === 'Active')   ? 'selected' : '' ?>>Active</option>
                    <option value="Inactive" <?= (isset($employee) && $employee['employment_status'] === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <div class="col-md-3">
                <label for="join_date" class="form-label">Join Date</label>
                <input type="date" class="form-control" id="join_date" name="join_date"
                       value="<?= htmlspecialchars($employee['join_date'] ?? '') ?>">
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> <?= isset($employee) ? 'Update' : 'Save' ?> Employee
                </button>
                <a href="index.php?page=staff" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>
