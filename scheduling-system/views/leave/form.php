<div class="row mb-3">
    <div class="col">
        <h2><i class="bi bi-file-text"></i> New Leave Request</h2>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="post" action="index.php?page=leave&action=store" class="row g-3">
            <div class="col-md-4">
                <label for="email" class="form-label">Employee Email <span class="text-danger">*</span></label>
                <input type="email" class="form-control" id="email" name="email" required
                       placeholder="employee@company.com">
            </div>

            <div class="col-md-4">
                <label for="request_date" class="form-label">Request Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="request_date" name="request_date" required>
            </div>

            <div class="col-md-4">
                <label for="gender" class="form-label">Gender</label>
                <select class="form-select" id="gender" name="gender">
                    <option value="">— Select —</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
            </div>

            <div class="col-12">
                <label for="reason" class="form-label">Leave Reason</label>
                <textarea class="form-control" id="reason" name="reason" rows="3"
                          placeholder="Optional reason for leave"></textarea>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-send"></i> Submit Request
                </button>
                <a href="index.php?page=leave" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // When email is entered, auto-fill gender if employee exists
    document.getElementById('email').addEventListener('blur', function() {
        const email = this.value;
        if (!email) return;

        fetch('index.php?page=staff&action=lookup&email=' + encodeURIComponent(email))
            .then(r => r.json())
            .then(data => {
                if (data.found && data.gender) {
                    document.getElementById('gender').value = data.gender;
                }
            })
            .catch(() => {}); // Silently fail
    });
});
</script>
