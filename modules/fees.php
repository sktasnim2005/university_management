<?php
// ============================================================
//  modules/fees.php
//  FIX: POST-Redirect-GET — after save/delete, redirect with
//       flash message so modal closes and page feels clean
// ============================================================
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

// Faculty cannot access fees at all
if (isFaculty()) {
    echo "<div class='alert alert-danger' style='margin:20px'>
            <i class='fas fa-ban'></i> ⛔ Faculty do not have access to fee records!
          </div>
          <p style='margin:10px 20px'><a href='/dashboard'>← Back to Dashboard</a></p>";
    require_once __DIR__ . '/../includes/footer.php';
    exit();
}

// ── DELETE → redirect with flash ─────────────────────────────
if (isset($_GET['delete'])) {
    if (!isAdmin()) die("<div class='alert alert-danger'>⛔ Access Denied!</div>");
    $id = intval($_GET['delete']);
    try {
        if ($conn->query("DELETE FROM fees WHERE fee_id = $id")) {

            setFlash(
                'success',
                '🗑️ Fee record deleted successfully!'
            );

        }

    } catch (mysqli_sql_exception $e) {

        switch ($e->getCode()) {

            case 1451:
                setFlash(
                    'danger',
                    'Cannot delete this record because related data exists.'
                );
                break;

            default:
                setFlash(
                    'danger',
                    'Unable to delete fee record.'
                );
        }

        error_log($e->getMessage());
    }
    header('Location: /fees');
    exit();
}

// ── INSERT / UPDATE → redirect with flash ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isAdmin()) die("<div class='alert alert-danger'>⛔ Access Denied!</div>");

    $stu_id   = intval($_POST['student_id']);
    $ftype    = trim($_POST['fee_type']);
    $amt      = floatval($_POST['amount']);
    $paid_amt = floatval($_POST['paid_amount']);
    $ac_yr    = trim($_POST['academic_year']);
    $due      = !empty($_POST['due_date'])  ? $_POST['due_date']  : null;
    $paid_dt  = !empty($_POST['paid_date']) ? $_POST['paid_date'] : null;

    // Auto-calculate status
    $status = 'Pending';
    if ($paid_amt >= $amt)                    $status = 'Paid';
    elseif ($paid_amt > 0)                    $status = 'Partial';
    elseif ($due && strtotime($due) < time()) $status = 'Overdue';

    if (!empty($_POST['fee_id'])) {
        $fid  = intval($_POST['fee_id']);
        $stmt = $conn->prepare(
            "UPDATE fees
             SET student_id=?, fee_type=?, amount=?, due_date=?,
                 paid_date=?, paid_amount=?, status=?, academic_year=?
             WHERE fee_id=?"
        );
        $stmt->bind_param(
            'isdssdssi',
            $stu_id, $ftype, $amt, $due,
            $paid_dt, $paid_amt, $status, $ac_yr,
            $fid
        );
    } else {
        // Prevent duplicate fee records
        $check = $conn->prepare(
            "SELECT fee_id
            FROM fees
            WHERE student_id=? 
            AND fee_type=? 
            AND academic_year=?"
        );

        $check->bind_param(
            "iss",
            $stu_id,
            $ftype,
            $ac_yr
        );

        $check->execute();

        $result = $check->get_result();

        if ($result->num_rows > 0) {

            setFlash(
                'danger',
                'This fee record already exists for the student.'
            );

            header('Location: /fees');
            exit();
        }
        $stmt = $conn->prepare(
        "INSERT INTO fees
            (student_id, fee_type, amount, due_date,
                paid_date, paid_amount, status, academic_year)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'isdssdss',
            $stu_id, $ftype, $amt, $due,
            $paid_dt, $paid_amt, $status, $ac_yr
        );
    }

    try {
        if ($stmt->execute()) {

            setFlash(
                'success',
                '✅ Fee record saved successfully! Status: ' . $status
            );

        }

    } catch (mysqli_sql_exception $e) {

        switch ($e->getCode()) {

            // Duplicate entry
            case 1062:
                setFlash('danger', 'Duplicate fee record found.');
                break;

            // Required field missing
            case 1048:
                setFlash('danger', 'Please fill all required fields.');
                break;

            // Foreign key issue
            case 1452:
                setFlash('danger', 'Invalid student selected.');
                break;

            // Data too long
            case 1406:
                setFlash('danger', 'One or more fields are too long.');
                break;

            default:
                setFlash('danger', 'Unable to save fee record.');
        }

        // Save real error privately
        error_log($e->getMessage());
    }

    // Redirect — closes modal, clears POST, shows flash
    header('Location: /fees');
    exit();
}

// ── DATA FETCH ───────────────────────────────────────────────
$students = $conn->query(
    "SELECT student_id, CONCAT(first_name,' ',last_name,' (',roll_no,')') AS label
     FROM students ORDER BY first_name"
);

if (isStudent()) {
    $my_id = intval($_SESSION['ref_id'] ?? 0);
    $fees  = $conn->query("
        SELECT f.*, CONCAT(s.first_name,' ',s.last_name) AS student_name, s.roll_no
        FROM fees f
        JOIN students s ON f.student_id = s.student_id
        WHERE f.student_id = $my_id
        ORDER BY f.created_at DESC
    ");
    $stats = $conn->query(
        "SELECT SUM(amount) AS total, SUM(paid_amount) AS collected,
                SUM(amount - paid_amount) AS pending
         FROM fees WHERE student_id = $my_id"
    )->fetch_assoc();
} else {
    $fees  = $conn->query("
        SELECT f.*, CONCAT(s.first_name,' ',s.last_name) AS student_name, s.roll_no
        FROM fees f
        JOIN students s ON f.student_id = s.student_id
        ORDER BY f.created_at DESC
    ");
    $stats = $conn->query(
        "SELECT SUM(amount) AS total, SUM(paid_amount) AS collected,
                SUM(amount - paid_amount) AS pending
         FROM fees"
    )->fetch_assoc();
}

$edit_f = null;
if (isset($_GET['edit']) && isAdmin()) {
    $edit_f = $conn->query(
        "SELECT * FROM fees WHERE fee_id = " . intval($_GET['edit'])
    )->fetch_assoc();
}
?>

<div class="page-header">
    <h2>
        <i class="fas fa-money-bill-wave"></i> Fees
        <?php if (isStudent()): ?>
        <span style="font-size:0.6em;color:#999">— My Fee Records</span>
        <?php endif; ?>
    </h2>
    <?php if (isAdmin()): ?>
    <button class="btn btn-primary"
            onclick="document.getElementById('feeModal').classList.add('active')">
        <i class="fas fa-plus"></i> Add Fee Record
    </button>
    <?php endif; ?>
</div>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px;">
    <div class="stat-card blue">
        <div class="stat-icon"><i class="fas fa-file-invoice-dollar"></i></div>
        <div class="stat-info"><h3>৳<?= number_format($stats['total'] ?? 0) ?></h3><p>Total Fees</p></div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info"><h3>৳<?= number_format($stats['collected'] ?? 0) ?></h3><p>Collected</p></div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
        <div class="stat-info"><h3>৳<?= number_format($stats['pending'] ?? 0) ?></h3><p>Pending</p></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><?= isStudent() ? 'My Fee Records' : 'All Fee Records' ?></h3>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <?php if (!isStudent()): ?><th>Student</th><?php endif; ?>
                    <th>Fee Type</th><th>Amount</th><th>Paid</th>
                    <th>Balance</th><th>Due Date</th><th>Status</th>
                    <?php if (isAdmin()): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php
            $i        = 1;
            $has_rows = false;
            while ($f = $fees->fetch_assoc()):
                $has_rows = true;
                $bal = $f['amount'] - $f['paid_amount'];
                $cls = match($f['status']) {
                    'Paid'    => 'badge-success',
                    'Partial' => 'badge-warning',
                    'Overdue' => 'badge-danger',
                    default   => 'badge-secondary'
                };
            ?>
            <tr>
                <td><?= $i++ ?></td>
                <?php if (!isStudent()): ?>
                <td><?= htmlspecialchars($f['student_name']) ?></td>
                <?php endif; ?>
                <td><?= htmlspecialchars($f['fee_type']) ?></td>
                <td>৳<?= number_format($f['amount']) ?></td>
                <td>৳<?= number_format($f['paid_amount']) ?></td>
                <td>৳<?= number_format($bal) ?></td>
                <td><?= $f['due_date'] ?? '—' ?></td>
                <td><span class="badge <?= $cls ?>"><?= $f['status'] ?></span></td>
                <?php if (isAdmin()): ?>
                <td>
                    <a href="?edit=<?= $f['fee_id'] ?>" class="btn btn-warning btn-sm">
                        <i class="fas fa-edit"></i>
                    </a>
                    <a href="?delete=<?= $f['fee_id'] ?>" class="btn btn-danger btn-sm"
                       onclick="return confirm('Delete this fee record?')">
                        <i class="fas fa-trash"></i>
                    </a>
                </td>
                <?php endif; ?>
            </tr>
            <?php endwhile; ?>
            <?php if (!$has_rows): ?>
            <tr>
                <td colspan="9" style="text-align:center;color:#999;padding:20px;">
                    No fee records found.
                </td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (isAdmin()): ?>
<div class="modal-overlay <?= $edit_f ? 'active' : '' ?>" id="feeModal">
<div class="modal">
    <div class="modal-header">
        <h3><?= $edit_f ? 'Edit' : 'Add' ?> Fee Record</h3>
        <button class="modal-close"
                onclick="document.getElementById('feeModal').classList.remove('active')">&times;</button>
    </div>
    <form method="POST">
        <?php if ($edit_f): ?>
        <input type="hidden" name="fee_id" value="<?= $edit_f['fee_id'] ?>">
        <?php endif; ?>
        <div class="form-grid">
            <div class="form-group">
                <label>Student *</label>
                <select name="student_id" required>
                    <option value="">-- Select --</option>
                    <?php $students->data_seek(0); while ($s = $students->fetch_assoc()): ?>
                    <option value="<?= $s['student_id'] ?>"
                        <?= ($edit_f['student_id'] ?? '') == $s['student_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['label']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Fee Type *</label>
                <select name="fee_type" required>
                    <?php foreach (['Tuition Fee','Hostel Fee','Library Fee','Lab Fee','Exam Fee','Transport Fee','Other'] as $ft): ?>
                    <option value="<?= $ft ?>"
                        <?= ($edit_f['fee_type'] ?? '') === $ft ? 'selected' : '' ?>>
                        <?= $ft ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Total Amount (৳) *</label>
                <input type="number" name="amount" step="0.01" required
                       value="<?= $edit_f['amount'] ?? '' ?>">
            </div>
            <div class="form-group">
                <label>Paid Amount (৳)</label>
                <input type="number" name="paid_amount" step="0.01"
                       value="<?= $edit_f['paid_amount'] ?? 0 ?>">
            </div>
            <div class="form-group">
                <label>Due Date</label>
                <input type="date" name="due_date"
                       value="<?= htmlspecialchars($edit_f['due_date'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Paid Date</label>
                <input type="date" name="paid_date"
                       value="<?= htmlspecialchars($edit_f['paid_date'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Academic Year</label>
                <input type="text" name="academic_year" placeholder="2024-25"
                       value="<?= htmlspecialchars($edit_f['academic_year'] ?? '2024-25') ?>">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i> Save
            </button>
            <button type="button" class="btn" style="background:#ddd;color:#333"
                    onclick="document.getElementById('feeModal').classList.remove('active')">
                Cancel
            </button>
        </div>
    </form>
</div>
</div>
<?php if ($edit_f): ?>
<script>document.getElementById('feeModal').classList.add('active');</script>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>