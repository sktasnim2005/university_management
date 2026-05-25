<?php
// ============================================================
//  modules/faculty.php
//  FIX: faculty can only see their own profile, cannot edit/delete
//  FIX: only admin can add, edit, delete faculty
// ============================================================
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

$msg = $err = '';

// ── DELETE (admin only) ──────────────────────────────────────
if (isset($_GET['delete'])) {
    if (!isAdmin()) die("<div class='alert alert-danger'>⛔ Access Denied!</div>");
    $id = intval($_GET['delete']);
    if ($conn->query("DELETE FROM faculty WHERE faculty_id = $id"))
        $msg = "Faculty deleted.";
    else
        $err = $conn->error;
}

// ── INSERT / UPDATE (admin only) ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isAdmin()) die("<div class='alert alert-danger'>⛔ Access Denied!</div>");

    $emp_id  = trim($_POST['employee_id']);
    $fname   = trim($_POST['first_name']);
    $lname   = trim($_POST['last_name']);
    $email   = trim($_POST['email']);
    $phone   = trim($_POST['phone']);
    $desig   = trim($_POST['designation']);
    $qual    = trim($_POST['qualification']);
    $dept_id = intval($_POST['dept_id']);
    $join_dt = !empty($_POST['joining_date']) ? $_POST['joining_date'] : null;
    $salary  = !empty($_POST['salary'])       ? floatval($_POST['salary']) : null;
    $status  = $_POST['status'];

    if (!empty($_POST['faculty_id'])) {
        $fid  = intval($_POST['faculty_id']);
        $stmt = $conn->prepare(
            "UPDATE faculty
             SET employee_id=?, first_name=?, last_name=?, email=?, phone=?,
                 designation=?, qualification=?, dept_id=?, joining_date=?,
                 salary=?, status=?
             WHERE faculty_id=?"
        );
        $stmt->bind_param(
            'sssssssissdi',
            $emp_id, $fname, $lname, $email, $phone,
            $desig, $qual, $dept_id, $join_dt,
            $salary, $status, $fid
        );
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO faculty
                (employee_id, first_name, last_name, email, phone,
                 designation, qualification, dept_id, joining_date, salary, status)
             VALUES (?,?,?,?,?, ?,?,?,?,?,?)"
        );
        $stmt->bind_param(
            'sssssssisds',
            $emp_id, $fname, $lname, $email, $phone,
            $desig, $qual, $dept_id, $join_dt, $salary, $status
        );
    }

    try {

        if ($stmt->execute()) {
            $msg = "Faculty saved successfully!";
        }

    } catch (mysqli_sql_exception $e) {

        switch ($e->getCode()) {

            // Duplicate entry
            case 1062:
                $err = "This record already exists.";
                break;

            // Cannot be null
            case 1048:
                $err = "Please fill all required fields.";
                break;

            // Foreign key issue
            case 1452:
                $err = "Invalid department selected.";
                break;

            // Data too long
            case 1406:
                $err = "One or more fields contain too much text.";
                break;

            // Default
            default:
                $err = "Unable to save data. Please check your information and try again.";
        }

        // Save actual error in server log (hidden from users)
        error_log($e->getMessage());
    }
}

// ── DATA FETCH ───────────────────────────────────────────────
$departments = $conn->query("SELECT * FROM departments ORDER BY dept_name");

// FIX: faculty sees ONLY their own row — admin sees all
if (isFaculty()) {
    $my_fac_id    = intval($_SESSION['ref_id'] ?? 0);
    $faculty_list = $conn->query("
        SELECT f.*, d.dept_code, d.dept_name
        FROM faculty f
        LEFT JOIN departments d ON f.dept_id = d.dept_id
        WHERE f.faculty_id = $my_fac_id
    ");
} else {
    $faculty_list = $conn->query("
        SELECT f.*, d.dept_code, d.dept_name
        FROM faculty f
        LEFT JOIN departments d ON f.dept_id = d.dept_id
        ORDER BY f.created_at DESC
    ");
}

// Edit — admin only
$edit_f = null;
if (isset($_GET['edit']) && isAdmin()) {
    $edit_f = $conn->query(
        "SELECT * FROM faculty WHERE faculty_id = " . intval($_GET['edit'])
    )->fetch_assoc();
}
?>

<div class="page-header">
    <h2>
        <i class="fas fa-chalkboard-teacher"></i> Faculty
        <?php if (isFaculty()): ?>
        <span style="font-size:0.6em;color:#999">— My Profile</span>
        <?php endif; ?>
    </h2>
    <?php if (isAdmin()): ?>
    <button class="btn btn-primary"
            onclick="document.getElementById('facModal').classList.add('active')">
        <i class="fas fa-plus"></i> Add Faculty
    </button>
    <?php endif; ?>
</div>

<?php if ($msg): ?>
<div class="alert alert-success auto-hide-alert">
    <i class="fas fa-check-circle"></i>
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<?php if ($err): ?>
<div class="alert alert-danger auto-hide-alert">
    <i class="fas fa-exclamation-circle"></i>
    <?= htmlspecialchars($err) ?>
</div>
<?php endif; ?>

<script>
setTimeout(() => {
    document.querySelectorAll('.auto-hide-alert').forEach(alertBox => {
        alertBox.style.transition = "opacity 0.5s ease";
        alertBox.style.opacity = "0";

        setTimeout(() => {
            alertBox.remove();
        }, 500);
    });
}, 3000); // 3 seconds
</script>

<div class="card">
    <div class="card-header">
        <h3><?= isFaculty() ? 'My Profile' : 'All Faculty (' . $faculty_list->num_rows . ')' ?></h3>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>#</th><th>Emp ID</th><th>Name</th><th>Email</th>
                    <th>Designation</th><th>Department</th><th>Status</th>
                    <?php if (isAdmin()): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php
            $i        = 1;
            $has_rows = false;
            while ($f = $faculty_list->fetch_assoc()):
                $has_rows = true;
                $badge = match($f['status']) {
                    'Active'   => 'badge-success',
                    'On Leave' => 'badge-warning',
                    default    => 'badge-secondary'
                };
            ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><strong><?= htmlspecialchars($f['employee_id']) ?></strong></td>
                <td><?= htmlspecialchars($f['first_name'] . ' ' . $f['last_name']) ?></td>
                <td><?= htmlspecialchars($f['email']) ?></td>
                <td><?= htmlspecialchars($f['designation']) ?></td>
                <td><span class="badge badge-info"><?= $f['dept_code'] ?? 'N/A' ?></span></td>
                <td><span class="badge <?= $badge ?>"><?= $f['status'] ?></span></td>
                <?php if (isAdmin()): ?>
                <td>
                    <a href="?edit=<?= $f['faculty_id'] ?>" class="btn btn-warning btn-sm">
                        <i class="fas fa-edit"></i>
                    </a>
                    <a href="?delete=<?= $f['faculty_id'] ?>" class="btn btn-danger btn-sm"
                       onclick="return confirm('Delete this faculty member?')">
                        <i class="fas fa-trash"></i>
                    </a>
                </td>
                <?php endif; ?>
            </tr>
            <?php endwhile; ?>
            <?php if (!$has_rows): ?>
            <tr>
                <td colspan="8" style="text-align:center;color:#999;padding:20px;">
                    No faculty records found.
                </td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (isAdmin()): ?>
<!-- Add / Edit Modal — admin only -->
<div class="modal-overlay <?= $edit_f ? 'active' : '' ?>" id="facModal">
<div class="modal">
    <div class="modal-header">
        <h3><?= $edit_f ? 'Edit' : 'Add' ?> Faculty</h3>
        <button class="modal-close"
                onclick="document.getElementById('facModal').classList.remove('active')">&times;</button>
    </div>
    <form method="POST">
        <?php if ($edit_f): ?>
        <input type="hidden" name="faculty_id" value="<?= $edit_f['faculty_id'] ?>">
        <?php endif; ?>
        <div class="form-grid">
            <div class="form-group">
                <label>Employee ID *</label>
                <input type="text" name="employee_id" required
                       value="<?= htmlspecialchars($edit_f['employee_id'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>First Name *</label>
                <input type="text" name="first_name" required
                       value="<?= htmlspecialchars($edit_f['first_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Last Name *</label>
                <input type="text" name="last_name" required
                       value="<?= htmlspecialchars($edit_f['last_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" required
                       value="<?= htmlspecialchars($edit_f['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone"
                       value="<?= htmlspecialchars($edit_f['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Designation</label>
                <input type="text" name="designation"
                       value="<?= htmlspecialchars($edit_f['designation'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Qualification</label>
                <input type="text" name="qualification"
                       value="<?= htmlspecialchars($edit_f['qualification'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Department</label>
                <select name="dept_id">
                    <option value="">-- Select --</option>
                    <?php $departments->data_seek(0); while ($d = $departments->fetch_assoc()): ?>
                    <option value="<?= $d['dept_id'] ?>"
                        <?= ($edit_f['dept_id'] ?? '') == $d['dept_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['dept_name']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Joining Date</label>
                <input type="date" name="joining_date"
                       value="<?= htmlspecialchars($edit_f['joining_date'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Salary (₹)</label>
                <input type="number" name="salary" step="0.01"
                       value="<?= htmlspecialchars($edit_f['salary'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <?php foreach (['Active', 'Inactive', 'On Leave'] as $st): ?>
                    <option value="<?= $st ?>"
                        <?= ($edit_f['status'] ?? 'Active') === $st ? 'selected' : '' ?>>
                        <?= $st ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i> Save
            </button>
            <button type="button" class="btn" style="background:#ddd;color:#333"
                    onclick="document.getElementById('facModal').classList.remove('active')">
                Cancel
            </button>
        </div>
    </form>
</div>
</div>
<?php if ($edit_f): ?>
<script>document.getElementById('facModal').classList.add('active');</script>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>