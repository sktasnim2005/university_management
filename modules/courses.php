<?php
// ============================================================
//  modules/courses.php
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

        if ($conn->query("DELETE FROM courses WHERE course_id = $id")) {

            setFlash(
                'success',
                '🗑️ Course deleted successfully!'
            );

        }

    } catch (mysqli_sql_exception $e) {

        switch ($e->getCode()) {

            // Foreign key constraint
            case 1451:
                setFlash(
                    'danger',
                    'Cannot delete this course because students are enrolled in it.'
                );
                break;

            default:
                setFlash(
                    'danger',
                    'Unable to delete course.'
                );
        }

        error_log($e->getMessage());
    }

    header('Location: /courses');
    exit();
}


// ── INSERT / UPDATE ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isAdmin()) {
        die("<div class='alert alert-danger'>⛔ Access Denied!</div>");
    }

    $code    = strtoupper(trim($_POST['course_code']));
    $name    = trim($_POST['course_name']);
    $dept_id = intval($_POST['dept_id']);
    $credits = intval($_POST['credits']);
    $sem     = intval($_POST['semester']);
    $fac_id  = !empty($_POST['faculty_id']) ? intval($_POST['faculty_id']) : null;
    $max     = intval($_POST['max_students']);

    // ── CHECK DUPLICATES ─────────────────────────────────────

    if (!empty($_POST['course_id'])) {

        // UPDATE MODE
        $cid = intval($_POST['course_id']);

        $check = $conn->prepare(
            "SELECT course_id
             FROM courses
             WHERE (course_code = ? OR course_name = ?)
             AND course_id != ?"
        );

        $check->bind_param('ssi', $code, $name, $cid);

    } else {

        // INSERT MODE
        $check = $conn->prepare(
            "SELECT course_id
             FROM courses
             WHERE course_code = ? OR course_name = ?"
        );

        $check->bind_param('ss', $code, $name);
    }

    $check->execute();
    $duplicate = $check->get_result();

    if ($duplicate->num_rows > 0) {

        setFlash(
            'danger',
            'Course code or course name already exists.'
        );

        header('Location: /courses');
        exit();
    }

    // ── UPDATE ───────────────────────────────────────────────
    if (!empty($_POST['course_id'])) {

        $cid  = intval($_POST['course_id']);

        $stmt = $conn->prepare(
            "UPDATE courses
             SET course_code=?, course_name=?, dept_id=?, credits=?,
                 semester=?, faculty_id=?, max_students=?
             WHERE course_id=?"
        );

        $stmt->bind_param(
            'ssiiiiii',
            $code,
            $name,
            $dept_id,
            $credits,
            $sem,
            $fac_id,
            $max,
            $cid
        );

    } else {

        // ── INSERT ───────────────────────────────────────────
        $stmt = $conn->prepare(
            "INSERT INTO courses
                (course_code, course_name, dept_id, credits,
                 semester, faculty_id, max_students)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            'ssiiiii',
            $code,
            $name,
            $dept_id,
            $credits,
            $sem,
            $fac_id,
            $max
        );
    }

    // ── EXECUTE ──────────────────────────────────────────────
    try {

        if ($stmt->execute()) {

            $action = !empty($_POST['course_id'])
                ? 'updated'
                : 'added';

            setFlash(
                'success',
                '✅ Course ' . $action . ' successfully!'
            );
        }

    } catch (mysqli_sql_exception $e) {

        switch ($e->getCode()) {

            // Duplicate
            case 1062:
                setFlash(
                    'danger',
                    'Course code already exists.'
                );
                break;

            // Required field missing
            case 1048:
                setFlash(
                    'danger',
                    'Please fill all required fields.'
                );
                break;

            // Invalid foreign key
            case 1452:
                setFlash(
                    'danger',
                    'Invalid department or faculty selected.'
                );
                break;

            // Data too long
            case 1406:
                setFlash(
                    'danger',
                    'One or more fields are too long.'
                );
                break;

            default:
                setFlash(
                    'danger',
                    'Unable to save course.'
                );
        }

        error_log($e->getMessage());
    }

    header('Location: /courses');
    exit();
}

// ── DATA FETCH ───────────────────────────────────────────────
$departments  = $conn->query("SELECT * FROM departments ORDER BY dept_name");
$faculty_list = $conn->query(
    "SELECT faculty_id, CONCAT(first_name,' ',last_name) AS name
     FROM faculty WHERE status='Active' ORDER BY first_name"
);
$courses = $conn->query("
    SELECT c.*,
           d.dept_code,
           CONCAT(f.first_name,' ',f.last_name) AS faculty_name
    FROM courses c
    LEFT JOIN departments d ON c.dept_id    = d.dept_id
    LEFT JOIN faculty     f ON c.faculty_id = f.faculty_id
    ORDER BY c.created_at DESC
");

$edit_c = null;
if (isset($_GET['edit']) && isAdmin()) {
    $edit_c = $conn->query(
        "SELECT * FROM courses WHERE course_id = " . intval($_GET['edit'])
    )->fetch_assoc();
}
?>

<div class="page-header">
    <h2><i class="fas fa-book"></i> Courses</h2>
    <?php if (isAdmin()): ?>
    <button class="btn btn-primary"
            onclick="document.getElementById('courseModal').classList.add('active')">
        <i class="fas fa-plus"></i> Add Course
    </button>
    <?php endif; ?>
</div>

<?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>All Courses (<?= $courses->num_rows ?>)</h3>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>#</th><th>Code</th><th>Course Name</th><th>Dept</th>
                    <th>Credits</th><th>Semester</th><th>Faculty</th>
                    <?php if (isAdmin()): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php
            $i = 1;
            $has_rows = false;
            while ($c = $courses->fetch_assoc()):
                $has_rows = true;
            ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><strong><?= htmlspecialchars($c['course_code']) ?></strong></td>
                <td><?= htmlspecialchars($c['course_name']) ?></td>
                <td><span class="badge badge-info"><?= $c['dept_code'] ?? 'N/A' ?></span></td>
                <td><?= $c['credits'] ?></td>
                <td>Sem <?= $c['semester'] ?></td>
                <td><?= htmlspecialchars($c['faculty_name'] ?? 'Unassigned') ?></td>
                <?php if (isAdmin()): ?>
                <td>
                    <a href="?edit=<?= $c['course_id'] ?>" class="btn btn-warning btn-sm">
                        <i class="fas fa-edit"></i>
                    </a>
                    <a href="?delete=<?= $c['course_id'] ?>" class="btn btn-danger btn-sm"
                       onclick="return confirm('Delete this course?')">
                        <i class="fas fa-trash"></i>
                    </a>
                </td>
                <?php endif; ?>
            </tr>
            <?php endwhile; ?>
            <?php if (!$has_rows): ?>
            <tr>
                <td colspan="8" style="text-align:center;color:#999;padding:20px;">
                    No courses found.
                </td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (isAdmin()): ?>
<div class="modal-overlay <?= $edit_c ? 'active' : '' ?>" id="courseModal">
<div class="modal">
    <div class="modal-header">
        <h3><?= $edit_c ? 'Edit' : 'Add' ?> Course</h3>
        <button class="modal-close"
                onclick="document.getElementById('courseModal').classList.remove('active')">&times;</button>
    </div>
    <form method="POST">
        <?php if ($edit_c): ?>
        <input type="hidden" name="course_id" value="<?= $edit_c['course_id'] ?>">
        <?php endif; ?>
        <div class="form-grid">
            <div class="form-group">
                <label>Course Code *</label>
                <input type="text" name="course_code" required
                       value="<?= htmlspecialchars($edit_c['course_code'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Course Name *</label>
                <input type="text" name="course_name" required
                       value="<?= htmlspecialchars($edit_c['course_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Department</label>
                <select name="dept_id">
                    <option value="">-- Select --</option>
                    <?php $departments->data_seek(0); while ($d = $departments->fetch_assoc()): ?>
                    <option value="<?= $d['dept_id'] ?>"
                        <?= ($edit_c['dept_id'] ?? '') == $d['dept_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['dept_name']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Credits</label>
                <input type="number" name="credits" min="1" max="6"
                       value="<?= $edit_c['credits'] ?? 3 ?>">
            </div>
            <div class="form-group">
                <label>Semester</label>
                <input type="number" name="semester" min="1" max="8"
                       value="<?= $edit_c['semester'] ?? 1 ?>">
            </div>
            <div class="form-group">
                <label>Assign Faculty</label>
                <select name="faculty_id">
                    <option value="">-- Assign --</option>
                    <?php $faculty_list->data_seek(0); while ($f = $faculty_list->fetch_assoc()): ?>
                    <option value="<?= $f['faculty_id'] ?>"
                        <?= ($edit_c['faculty_id'] ?? '') == $f['faculty_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($f['name']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Max Students</label>
                <input type="number" name="max_students"
                       value="<?= $edit_c['max_students'] ?? 60 ?>">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save</button>
            <button type="button" class="btn" style="background:#ddd;color:#333"
                    onclick="document.getElementById('courseModal').classList.remove('active')">Cancel</button>
        </div>
    </form>
</div>
</div>
<?php if ($edit_c): ?>
<script>document.getElementById('courseModal').classList.add('active');</script>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>