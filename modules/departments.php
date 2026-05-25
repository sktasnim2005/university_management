<?php
// ============================================================
//  modules/departments.php
//  No major logic bugs — cleaned up paths and empty states
// ============================================================
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

$msg = $err = '';

// ── DELETE ───────────────────────────────────────────────────
if (isset($_GET['delete'])) {

    if (!isAdmin()) {
        die("<div class='alert alert-danger'>⛔ Access Denied!</div>");
    }

    $id = intval($_GET['delete']);

    try {

        if ($conn->query("DELETE FROM departments WHERE dept_id = $id")) {

            setFlash(
                'success',
                '🗑️ Department deleted successfully!'
            );

        }

    } catch (mysqli_sql_exception $e) {

        switch ($e->getCode()) {

            // Foreign key constraint
            case 1451:
                setFlash(
                    'danger',
                    'Cannot delete this department because students or faculty are assigned to it.'
                );
                break;

            default:
                setFlash(
                    'danger',
                    'Unable to delete department.'
                );
        }

        error_log($e->getMessage());
    }

    header('Location: /departments');
    exit();
}


// ── INSERT / UPDATE ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isAdmin()) {
        die("<div class='alert alert-danger'>⛔ Access Denied!</div>");
    }

    $name = trim($_POST['dept_name']);
    $code = strtoupper(trim($_POST['dept_code']));
    $hod  = trim($_POST['hod_name']);
    $yr   = intval($_POST['established_year']);

    // ── CHECK DUPLICATES ─────────────────────────────────────

    if (!empty($_POST['dept_id'])) {

        // UPDATE MODE
        $did = intval($_POST['dept_id']);

        $check = $conn->prepare(
            "SELECT dept_id
             FROM departments
             WHERE (dept_name = ? OR dept_code = ?)
             AND dept_id != ?"
        );

        $check->bind_param('ssi', $name, $code, $did);

    } else {

        // INSERT MODE
        $check = $conn->prepare(
            "SELECT dept_id
             FROM departments
             WHERE dept_name = ? OR dept_code = ?"
        );

        $check->bind_param('ss', $name, $code);
    }

    $check->execute();
    $duplicate = $check->get_result();

    if ($duplicate->num_rows > 0) {

        setFlash(
            'danger',
            'Department name or code already exists.'
        );

        header('Location: /departments');
        exit();
    }

    // ── UPDATE ───────────────────────────────────────────────
    if (!empty($_POST['dept_id'])) {

        $did  = intval($_POST['dept_id']);

        $stmt = $conn->prepare(
            "UPDATE departments
             SET dept_name=?, dept_code=?, hod_name=?, established_year=?
             WHERE dept_id=?"
        );

        $stmt->bind_param(
            'sssii',
            $name,
            $code,
            $hod,
            $yr,
            $did
        );

    } else {

        // ── INSERT ───────────────────────────────────────────
        $stmt = $conn->prepare(
            "INSERT INTO departments
                (dept_name, dept_code, hod_name, established_year)
             VALUES (?, ?, ?, ?)"
        );

        $stmt->bind_param(
            'sssi',
            $name,
            $code,
            $hod,
            $yr
        );
    }

    // ── EXECUTE ──────────────────────────────────────────────
    try {

        if ($stmt->execute()) {

            $action = !empty($_POST['dept_id'])
                ? 'updated'
                : 'added';

            setFlash(
                'success',
                '✅ Department ' . $action . ' successfully!'
            );
        }

    } catch (mysqli_sql_exception $e) {

        switch ($e->getCode()) {

            case 1062:
                setFlash(
                    'danger',
                    'Department name or code already exists.'
                );
                break;

            case 1048:
                setFlash(
                    'danger',
                    'Please fill all required fields.'
                );
                break;

            case 1406:
                setFlash(
                    'danger',
                    'One or more fields are too long.'
                );
                break;

            default:
                setFlash(
                    'danger',
                    'Unable to save department.'
                );
        }

        error_log($e->getMessage());
    }

    header('Location: /departments');
    exit();
}

// ── DATA FETCH ───────────────────────────────────────────────
$depts = $conn->query("
    SELECT d.*,
           (SELECT COUNT(*) FROM students WHERE dept_id = d.dept_id) AS student_count,
           (SELECT COUNT(*) FROM faculty  WHERE dept_id = d.dept_id) AS faculty_count
    FROM departments d
    ORDER BY d.dept_name
");

$edit_d = null;
if (isset($_GET['edit']) && isAdmin()) {
    $edit_d = $conn->query(
        "SELECT * FROM departments WHERE dept_id = " . intval($_GET['edit'])
    )->fetch_assoc();
}
?>

<div class="page-header">
    <h2><i class="fas fa-building"></i> Departments</h2>
    <?php if (isAdmin()): ?>
    <button class="btn btn-primary"
            onclick="document.getElementById('deptModal').classList.add('active')">
        <i class="fas fa-plus"></i> Add Department
    </button>
    <?php endif; ?>
</div>

<?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>All Departments (<?= $depts->num_rows ?>)</h3>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>#</th><th>Code</th><th>Department</th><th>Head of Dept</th>
                    <th>Est. Year</th><th>Students</th><th>Faculty</th>
                    <?php if (isAdmin()): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php
            $i = 1;
            $has_rows = false;
            while ($d = $depts->fetch_assoc()):
                $has_rows = true;
            ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><span class="badge badge-info"><?= htmlspecialchars($d['dept_code']) ?></span></td>
                <td><strong><?= htmlspecialchars($d['dept_name']) ?></strong></td>
                <td><?= htmlspecialchars($d['hod_name'] ?? 'N/A') ?></td>
                <td><?= $d['established_year'] ?></td>
                <td><?= $d['student_count'] ?></td>
                <td><?= $d['faculty_count'] ?></td>
                <?php if (isAdmin()): ?>
                <td>
                    <a href="?edit=<?= $d['dept_id'] ?>" class="btn btn-warning btn-sm">
                        <i class="fas fa-edit"></i>
                    </a>
                    <a href="?delete=<?= $d['dept_id'] ?>" class="btn btn-danger btn-sm"
                       onclick="return confirm('Delete this department?')">
                        <i class="fas fa-trash"></i>
                    </a>
                </td>
                <?php endif; ?>
            </tr>
            <?php endwhile; ?>
            <?php if (!$has_rows): ?>
            <tr>
                <td colspan="8" style="text-align:center;color:#999;padding:20px;">
                    No departments found.
                </td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (isAdmin()): ?>
<div class="modal-overlay <?= $edit_d ? 'active' : '' ?>" id="deptModal">
<div class="modal">
    <div class="modal-header">
        <h3><?= $edit_d ? 'Edit' : 'Add' ?> Department</h3>
        <button class="modal-close"
                onclick="document.getElementById('deptModal').classList.remove('active')">&times;</button>
    </div>
    <form method="POST">
        <?php if ($edit_d): ?>
        <input type="hidden" name="dept_id" value="<?= $edit_d['dept_id'] ?>">
        <?php endif; ?>
        <div class="form-grid">
            <div class="form-group">
                <label>Department Name *</label>
                <input type="text" name="dept_name" required
                       value="<?= htmlspecialchars($edit_d['dept_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Code *</label>
                <input type="text" name="dept_code" required
                       value="<?= htmlspecialchars($edit_d['dept_code'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Head of Department</label>
                <input type="text" name="hod_name"
                       value="<?= htmlspecialchars($edit_d['hod_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Established Year</label>
                <input type="number" name="established_year"
                       value="<?= $edit_d['established_year'] ?? date('Y') ?>">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save</button>
            <button type="button" class="btn" style="background:#ddd;color:#333"
                    onclick="document.getElementById('deptModal').classList.remove('active')">Cancel</button>
        </div>
    </form>
</div>
</div>
<?php if ($edit_d): ?>
<script>document.getElementById('deptModal').classList.add('active');</script>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>