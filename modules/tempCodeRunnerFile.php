<?php
// ============================================================
//  modules/students.php
//  FIX: POST-Redirect-GET — after save/delete, redirect with
//       flash message so modal closes and page feels clean
// ============================================================
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

// ── DELETE → redirect with flash ─────────────────────────────
if (isset($_GET['delete'])) {
    if (!isAdmin()) die("<div class='alert alert-danger'>⛔ Access Denied!</div>");
    $id = intval($_GET['delete']);
    if ($conn->query("DELETE FROM students WHERE student_id = $id"))
        setFlash('success', '🗑️ Student deleted successfully!');
    else
        setFlash('danger', 'Delete failed: ' . $conn->error);
    header('Location: /students');
    exit();
}

// ── INSERT / UPDATE → redirect with flash ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isAdmin()) die("<div class='alert alert-danger'>⛔ Access Denied!</div>");

    $roll    = trim($_POST['roll_no']);
    $fname   = trim($_POST['first_name']);
    $lname   = trim($_POST['last_name']);
    $email   = trim($_POST['email']);
    $phone   = trim($_POST['phone']);
    $dob     = !empty($_POST['dob'])            ? $_POST['dob']            : null;
    $gender  = $_POST['gender'];
    $address = trim($_POST['address']);
    $dept_id = intval($_POST['dept_id']);
    $sem     = intval($_POST['semester']);
    $batch   = intval($_POST['batch_year']);
    $adm_dt  = !empty($_POST['admission_date']) ? $_POST['admission_date'] : null;
    $status  = $_POST['status'];

    if (!empty($_POST['student_id'])) {
        // ── UPDATE ──────────────────────────────────────────
        $sid  = intval($_POST['student_id']);
        $stmt = $conn->prepare(
            "UPDATE students
             SET roll_no=?, first_name=?, last_name=?, email=?, phone=?,
                 dob=?, gender=?, address=?, dept_id=?, semester=?,
                 batch_year=?, admission_date=?, status=?
             WHERE student_id=?"
        );
        // s s s s s  s s s  i i  i s s  i
        $stmt->bind_param(
            'ssssssssiiissi',
            $roll, $fname, $lname, $email, $phone,
            $dob, $gender, $address, $dept_id, $sem,
            $batch, $adm_dt, $status,
            $sid
        );
    } else {

        // ── CHECK DUPLICATE STUDENT ────────────────────────
        $check = $conn->prepare(
            "SELECT student_id
            FROM students
            WHERE roll_no=? OR email=?"
        );

        $check->bind_param(
            "ss",
            $roll,
            $email
        );

        $check->execute();

        $result = $check->get_result();

        // Duplicate found
        if ($result->num_rows > 0) {

            setFlash(
                'danger',
                'A student with this Roll Number or Email already exists.'
            );

            header('Location: /students');
            exit();
        }

        // ── INSERT ──────────────────────────────────────────
        $stmt = $conn->prepare(
            "INSERT INTO students
                (roll_no, first_name, last_name, email, phone,
                dob, gender, address, dept_id, semester,
                batch_year, admission_date, status)
            VALUES (?,?,?,?,?, ?,?,?,?,?, ?,?,?)"
        );

        $stmt->bind_param(
            'ssssssssiisss',
            $roll, $fname, $lname, $email, $phone,
            $dob, $gender, $address, $dept_id, $sem,
            $batch, $adm_dt, $status
        );
    }

    try {

        if ($stmt->execute()) {

            $action = !empty($_POST['student_id'])
                ? 'updated'
                : 'added';

            setFlash(
                'success',
                '✅ Student ' . $action . ' successfully!'
            );
        }

    } catch (mysqli_sql_exception $e) {

        switch ($e->getCode()) {

            case 1062:
                setFlash(
                    'danger',
                    'Duplicate Roll Number or Email found.'
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
                    'Unable to save student record.'
                );
        }

        error_log($e->getMessage());
    }

    // Redirect — closes modal, clears POST, shows flash
    header('Location: /students');
    exit();
}

// ── DATA FETCH ───────────────────────────────────────────────
$departments = $conn->query("SELECT * FROM departments ORDER BY dept_name");
$search      = isset($_GET['search']) ? trim($_GET['search']) : '';

if (isStudent()) {
    $my_id = intval($_SESSION['ref_id'] ?? 0);
    $query = "SELECT s.*, d.dept_name, d.dept_code
              FROM students s
              LEFT JOIN departments d ON s.dept_id = d.dept_id
              WHERE s.student_id = $my_id";
} else {
    $query = "SELECT s.*, d.dept_name, d.dept_code
              FROM students s
              LEFT JOIN departments d ON s.dept_id = d.dept_id";
    if ($search) {
        $safe   = $conn->real_escape_string($search);
        $query .= " WHERE s.first_name LIKE '%$safe%'
                       OR s.last_name  LIKE '%$safe%'
                       OR s.roll_no    LIKE '%$safe%'
                       OR s.email      LIKE '%$safe%'";
    }
    $query .= " ORDER BY s.created_at DESC";
}
$students = $conn->query($query);

$edit_student = null;
if (isset($_GET['edit']) && isAdmin()) {
    $edit_student = $conn->query(
        "SELECT * FROM students WHERE student_id = " . intval($_GET['edit'])
    )->fetch_assoc();
}
?>

<div class="page-header">
    <h2>
        <i class="fas fa-user-graduate"></i> Students
        <?php if (isStudent()): ?>
        <span style="font-size:0.6em;color:#999">— Your Profile</span>
        <?php endif; ?>
    </h2>
    <?php if (isAdmin()): ?>
    <button class="btn btn-primary"
            onclick="document.getElementById('studentModal').classList.add('active')">
        <i class="fas fa-plus"></i> Add Student
    </button>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <h3><?= isStudent() ? 'My Profile' : 'All Students (' . $students->num_rows . ')' ?></h3>
        <?php if (!isStudent()): ?>
        <form method="GET" style="display:flex;gap:8px;">
            <input type="text" name="search" placeholder="Search name / roll / email..."
                   value="<?= htmlspecialchars($search) ?>"
                   style="padding:7px 12px;border:1.5px solid #ddd;border-radius:6px;font-size:0.9em;">
            <button type="submit" class="btn btn-info btn-sm"><i class="fas fa-search"></i></button>
            <?php if ($search): ?>
            <a href="/students" class="btn btn-sm" style="background:#ddd;color:#333">Clear</a>
            <?php endif; ?>
        </form>
        <?php endif; ?>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>#</th><th>Roll No</th><th>Name</th><th>Email</th>
                    <th>Dept</th><th>Sem</th><th>Status</th>
                    <?php if (isAdmin()): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php
            $i        = 1;
            $has_rows = false;
            while ($s = $students->fetch_assoc()):
                $has_rows = true;
                $badge = match($s['status']) {
                    'Active'    => 'badge-success',
                    'Graduated' => 'badge-info',
                    'Inactive'  => 'badge-secondary',
                    default     => 'badge-danger'
                };
            ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><strong><?= htmlspecialchars($s['roll_no']) ?></strong></td>
                <td><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></td>
                <td><?= htmlspecialchars($s['email']) ?></td>
                <td><span class="badge badge-info"><?= $s['dept_code'] ?? 'N/A' ?></span></td>
                <td><?= $s['semester'] ?></td>
                <td><span class="badge <?= $badge ?>"><?= $s['status'] ?></span></td>
                <?php if (isAdmin()): ?>
                <td>
                    <a href="?edit=<?= $s['student_id'] ?>" class="btn btn-warning btn-sm">
                        <i class="fas fa-edit"></i>
                    </a>
                    <a href="?delete=<?= $s['student_id'] ?>" class="btn btn-danger btn-sm"
                       onclick="return confirm('Delete this student?')">
                        <i class="fas fa-trash"></i>
                    </a>
                </td>
                <?php endif; ?>
            </tr>
            <?php endwhile; ?>
            <?php if (!$has_rows): ?>
            <tr>
                <td colspan="8" style="text-align:center;color:#999;padding:20px;">
                    No students found.
                </td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (isAdmin()): ?>
<div class="modal-overlay <?= $edit_student ? 'active' : '' ?>" id="studentModal">
<div class="modal">
    <div class="modal-header">
        <h3><?= $edit_student ? 'Edit' : 'Add' ?> Student</h3>
        <button class="modal-close"
                onclick="document.getElementById('studentModal').classList.remove('active')">&times;</button>
    </div>
    <form method="POST">
        <?php if ($edit_student): ?>
        <input type="hidden" name="student_id" value="<?= $edit_student['student_id'] ?>">
        <?php endif; ?>
        <div class="form-grid">
            <div class="form-group">
                <label>Roll Number *</label>
                <input type="text" name="roll_no" required
                       value="<?= htmlspecialchars($edit_student['roll_no'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>First Name *</label>
                <input type="text" name="first_name" required
                       value="<?= htmlspecialchars($edit_student['first_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Last Name *</label>
                <input type="text" name="last_name" required
                       value="<?= htmlspecialchars($edit_student['last_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" required
                       value="<?= htmlspecialchars($edit_student['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone"
                       value="<?= htmlspecialchars($edit_student['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Date of Birth</label>
                <input type="date" name="dob"
                       value="<?= htmlspecialchars($edit_student['dob'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Gender</label>
                <select name="gender">
                    <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                    <option value="<?= $g ?>"
                        <?= ($edit_student['gender'] ?? 'Male') === $g ? 'selected' : '' ?>>
                        <?= $g ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Department</label>
                <select name="dept_id">
                    <option value="">-- Select --</option>
                    <?php $departments->data_seek(0); while ($d = $departments->fetch_assoc()): ?>
                    <option value="<?= $d['dept_id'] ?>"
                        <?= ($edit_student['dept_id'] ?? '') == $d['dept_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['dept_name']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Semester</label>
                <input type="number" name="semester" min="1" max="8"
                       value="<?= $edit_student['semester'] ?? 1 ?>">
            </div>
            <div class="form-group">
                <label>Batch Year</label>
                <input type="number" name="batch_year"
                       value="<?= $edit_student['batch_year'] ?? date('Y') ?>">
            </div>
            <div class="form-group">
                <label>Admission Date</label>
                <input type="date" name="admission_date"
                       value="<?= htmlspecialchars($edit_student['admission_date'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <?php foreach (['Active', 'Inactive', 'Graduated', 'Dropped'] as $st): ?>
                    <option value="<?= $st ?>"
                        <?= ($edit_student['status'] ?? 'Active') === $st ? 'selected' : '' ?>>
                        <?= $st ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="grid-column:1/-1">
                <label>Address</label>
                <textarea name="address" rows="2"><?= htmlspecialchars($edit_student['address'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i> Save
            </button>
            <button type="button" class="btn" style="background:#ddd;color:#333"
                    onclick="document.getElementById('studentModal').classList.remove('active')">
                Cancel
            </button>
        </div>
    </form>
</div>
</div>
<?php if ($edit_student): ?>
<script>document.getElementById('studentModal').classList.add('active');</script>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>